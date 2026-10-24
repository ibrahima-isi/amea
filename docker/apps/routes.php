<?php
// Deny by default, before dispatch and legacy fallback. Expand staff per tickets 06/07.
return [
    'public' => ['/', '/register.php', '/registration-details.php', '/registration-preview.php', '/kyc-correction.php', '/accept-cgu.php', '/legal-notice.php'],
    'staff' => ['/', '/login.php', '/logout.php', '/forgot-password.php', '/reset-password.php', '/dashboard.php', '/students.php', '/student-details.php', '/edit-student.php', '/kyc-list.php', '/kyc-detail.php', '/trash.php', '/download.php', '/manage-slider.php', '/communications.php', '/communications-count.php', '/export.php', '/export-preview.php', '/settings.php', '/users.php', '/add-user.php', '/edit-user.php', '/profile.php', '/upgrade-levels.php', '/confirm-upgrade.php'],
];
