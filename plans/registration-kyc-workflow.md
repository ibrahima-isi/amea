# Implementation Plan: Registration KYC Workflow Refactoring

This plan outlines the refactoring of the student registration process into a robust KYC (Know Your Customer) workflow.

## 1. Objective
Transform the registration process from a simple capture into a multi-stage review cycle:
1.  **Stage 1: Registration** - Data capture and policy acceptance.
2.  **Stage 2: Review (Under Review)** - Administrative oversight.
3.  **Stage 3: Decisions** - Approval, Rejection, or "Needs Clarification".
4.  **Stage 4: Correction Loop** - Students provide additional info as requested.

## 2. Key Changes

### 2.1 Database Schema (Migrations)
-   Add `kyc_status` to `personnes` table: `ENUM('PENDING_CONFIRMATION', 'UNDER_REVIEW', 'APPROVED', 'NEEDS_CLARIFICATION', 'REJECTED')`. Default: `PENDING_CONFIRMATION`.
-   Add `kyc_notes` (TEXT) to store internal admin notes or feedback for students.
-   Add `kyc_updated_at` (DATETIME).
-   Add `review_token` (VARCHAR(64)) to allow students to access their form for corrections without a full login (secure, one-time or time-limited).

### 2.2 Models & Repositories
-   Update `Amea\Model\Student` to include new KYC fields.
-   Update `Amea\Repository\StudentRepository` to support filtering by `kyc_status` and updating KYC fields.

### 2.3 Controllers & Workflow logic
-   **`RegistrationController` (New)**:
    -   `register()`: Handles initial capture. **No email sent yet.**
    -   `review()`: Replaces `registration-details.php`.
    -   `confirm()`: Moves status to `UNDER_REVIEW`. **Sends "Dossier under review" email.**
-   **`Admin\KYCController` (New)**:
    -   `index()`: Lists students awaiting review.
    -   `review(int $id)`: Detailed view for admins with decision buttons (Approve, Needs Clarification, Reject).
    -   `decide(int $id)`: Processes the decision.
        -   If **Approve**: Set status `APPROVED`. **Sends "Dossier Approved" email.**
        -   If **Needs Clarification**: Set status `NEEDS_CLARIFICATION`, store notes. **Sends "Clarification Requested" email with a link to the form.**
-   **`CorrectionController` (New)**:
    -   `edit(string $token)`: Allows students to edit their data based on admin feedback.

### 2.4 Email Templates
-   `registration-received.html`: "Your dossier is received and currently under review."
-   `registration-approved.html`: "Your registration has been approved."
-   `registration-clarification.html`: "We need more details. Please see notes and update your dossier here: [link]."

## 3. Phased Implementation Plan

### Phase 1: Database & Model Preparation
1.  Create and run migration `migration_add_kyc_workflow.php`.
2.  Update `Student.php` and `StudentRepository.php`.
3.  Add unit tests for status transitions in `test_oop_unit.php`.

### Phase 2: Refactoring Registration Entry Point
1.  Port `register.php` and `registration-details.php` to `src/Controller/RegistrationController.php`.
2.  Update `src/bootstrap.php` with new routes.
3.  Modify logic to prevent initial email and implement the `confirm()` step.

### Phase 4: Administrative KYC Dashboard
1.  Create `templates/admin/pages/kyc-list.html` and `kyc-detail.html`.
2.  Implement `Admin\KYCController.php`.
3.  Implement decision logic and email triggers.

### Phase 5: Correction Loop (The KYC "Feedback" part)
1.  Implement `review_token` generation.
2.  Create the correction form view (reusing `register.html` but pre-filled).
3.  Implement `CorrectionController.php`.

## 4. Verification & Testing
-   **Unit Tests**: Verify that `kyc_status` changes correctly in the repository.
-   **Integration Tests**: Mock `EmailService` to verify that emails are sent at the correct stages and NOT during initial registration.
-   **Manual Testing**: 
    1. Register a student -> Check DB for `PENDING_CONFIRMATION`.
    2. Confirm on review page -> Check DB for `UNDER_REVIEW`, check email received.
    3. Admin logs in -> Views list, selects student, requests clarification.
    4. Student receives email -> Clicks link, updates field -> Status returns to `UNDER_REVIEW`.
    5. Admin approves -> Check DB for `APPROVED`, check approval email.

## 5. Security Considerations
-   `review_token` must be cryptographically secure and expired after approval.
-   Administrative actions must be strictly guarded by the `admin` role and CSRF protection.
-   Inputs in the correction form must undergo the same strict validation as the initial registration.
