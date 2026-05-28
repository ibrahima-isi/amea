<?php
namespace Amea\Service;

use Amea\Core\View;
use DateTimeImmutable;
use PDO;
use Throwable;

class PasswordResetService
{
    public const TOKEN_TTL_SECONDS = 300;

    private View $view;

    public function __construct(
        private PDO $pdo,
        private string $appUrl,
        private string $projectRoot
    ) {
        $this->view = new View($projectRoot);
    }

    public function requestForEmail(string $email, callable $sendMail): bool
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $user = $this->findActiveUserByEmail($email);
        if ($user === null) {
            return true;
        }

        return $this->issueResetLink($user, $sendMail);
    }

    public function requestForUserId(int $userId, callable $sendMail): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id_user, email, nom, prenom FROM users WHERE id_user = ? AND est_actif = 1 LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !filter_var((string)$user['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return $this->issueResetLink($user, $sendMail);
    }

    /**
     * @return array{success:bool,message:string,confirmation_sent?:bool}
     */
    public function resetPassword(
        string $rawToken,
        string $password,
        string $confirmPassword,
        callable $sendMail
    ): array {
        $rawToken = trim($rawToken);

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
        }

        $passwordError = $this->validatePassword($password);
        if ($passwordError !== null) {
            return ['success' => false, 'message' => $passwordError];
        }

        $reset = $this->findValidResetByRawToken($rawToken);
        if ($reset === null) {
            return ['success' => false, 'message' => 'Ce lien de réinitialisation est invalide ou expiré.'];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
            $stmt->execute([$hashedPassword, (int)$reset['id_user']]);

            $stmt = $this->pdo->prepare(
                "UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id_user = ?"
            );
            $stmt->execute([(int)$reset['id_user']]);

            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([(string)$reset['email']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[PasswordResetService] Password reset failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Impossible de réinitialiser ce mot de passe.'];
        }

        $confirmationSent = $this->sendPasswordChangedConfirmation($reset, $sendMail);

        return [
            'success' => true,
            'message' => 'Votre mot de passe a été mis à jour. Connectez-vous avec votre nouveau mot de passe.',
            'confirmation_sent' => $confirmationSent,
        ];
    }

    public function isTokenUsable(string $rawToken): bool
    {
        return $this->findValidResetByRawToken($rawToken) !== null;
    }

    private function issueResetLink(array $user, callable $sendMail): bool
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = $this->hashToken($rawToken);
        $expiresAt = (new DateTimeImmutable('+' . self::TOKEN_TTL_SECONDS . ' seconds'))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([(string)$user['email']]);

            $stmt = $this->pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([(string)$user['email'], $tokenHash, $expiresAt]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[PasswordResetService] Reset token creation failed: ' . $e->getMessage());
            return false;
        }

        $body = $this->view->render('emails/password-reset-email.html', [
            'prenom' => (string)($user['prenom'] ?? ''),
            'nom' => (string)($user['nom'] ?? ''),
            'reset_link' => $this->resetLink($rawToken),
            'expires_in' => '5 minutes',
        ]);

        if (!$sendMail((string)$user['email'], 'Réinitialisation de votre mot de passe', $body)) {
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$tokenHash]);
            error_log('[PasswordResetService] Reset email delivery failed.');
            return false;
        }

        return true;
    }

    private function findActiveUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_user, email, nom, prenom FROM users WHERE email = ? AND est_actif = 1 LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    private function findValidResetByRawToken(string $rawToken): ?array
    {
        if (!$this->hasExpectedTokenShape($rawToken)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT pr.email, pr.expires_at, u.id_user, u.nom, u.prenom
             FROM password_resets pr
             INNER JOIN users u ON u.email = pr.email
             WHERE pr.token = ? AND u.est_actif = 1
             LIMIT 1"
        );
        $stmt->execute([$this->hashToken($rawToken)]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            return null;
        }

        if (new DateTimeImmutable((string)$reset['expires_at']) < new DateTimeImmutable()) {
            $delete = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete->execute([(string)$reset['email']]);
            return null;
        }

        return $reset;
    }

    private function sendPasswordChangedConfirmation(array $user, callable $sendMail): bool
    {
        $body = $this->view->render('emails/password-updated-confirmation.html', [
            'prenom' => (string)($user['prenom'] ?? ''),
            'nom' => (string)($user['nom'] ?? ''),
        ]);

        $sent = $sendMail(
            (string)$user['email'],
            'Confirmation de changement de mot de passe',
            $body
        );

        if (!$sent) {
            error_log('[PasswordResetService] Password change confirmation email failed.');
        }

        return $sent;
    }

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Le mot de passe doit contenir au moins 12 caractères.';
        }

        return null;
    }

    private function resetLink(string $rawToken): string
    {
        return rtrim($this->appUrl !== '' ? $this->appUrl : 'http://localhost', '/')
            . '/reset-password.php?token=' . urlencode($rawToken);
    }

    private function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function hasExpectedTokenShape(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }
}
