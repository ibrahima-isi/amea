<?php

declare(strict_types=1);

use Amea\Core\View;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Service\CommunicationService;
use Amea\Service\EmailService;
use Amea\Service\MailQuotaService;
use PHPMailer\PHPMailer\PHPMailer;

// Both callers supply a disposable database. Queueing never opens SMTP.
return static function (PDO $db): void {
    $repository = new CommunicationRepository($db);
    $service = new CommunicationService(
        $repository,
        new MailQuotaService(new MailDailyUsageRepository($db)),
        new SettingRepository($db),
        new EmailService('', '', 'staff@example.org', 'AMEA'),
        new View(dirname(__DIR__, 2))
    );
    $filters = ['institution' => 'Email parity fixture'];
    $insert = $db->prepare("INSERT INTO students
        (first_name, last_name, gender, status, institution, email)
        VALUES ('Email', 'Fixture', 'Masculin', 'ETUDIANT', ?, ?)");
    $expected = [];
    $baseline = $repository->getCommunicationStats()['reachable_with_email'];
    $addresses = [
        'Alice@example.org', '  plain@example.org  ', '"member"@example.org', '"a@b".other@example.org',
        '"a\\ b"@example.org', 'user@[127.0.0.1]', 'six@[IPv6:2001:db8::1]',
        'mapped@[IPv6:::ffff:192.0.2.1]', 'full@[IPv6:1:2:3:4:5:6:7:8]',
        str_repeat('x', 64) . '@example.org', str_repeat('y', 65) . '@example.org',
        'bad@[256.0.0.1]', 'bad@[IPv6:1::2::3]', 'dot..dot@example.org',
        'broken@-example.org', 'numeric@example.123', 'bare@localhost',
        'unclosed"@example.org', 'k@exampKe.org', "line@example.org\n", null,
    ];
    foreach ($addresses as $address) {
        $insert->execute([$filters['institution'], $address]);
        $normalized = trim($address ?? '', ' ');
        if (PHPMailer::validateAddress($normalized, 'php')) {
            $expected[] = $normalized;
        }
        if ($repository->getCommunicationStats()['reachable_with_email'] !== $baseline + count($expected)
            || $service->countAudience($filters) !== count($expected)) {
            throw new RuntimeException('Email summary or preview disagrees with PHPMailer for ' . json_encode($address));
        }
    }
    $verifyQueue = static function () use ($db, $service, $filters, $expected): void {
        $campaign = $service->queueBulkCommunication('Parity', 'Fixture', $filters, null, count($expected));
        $query = $db->prepare('SELECT email FROM communication_recipients WHERE communication_id = ? ORDER BY id');
        $query->execute([$campaign['id']]);
        if ($query->fetchAll(PDO::FETCH_COLUMN) !== $expected) {
            throw new RuntimeException('Queued addresses disagree with PHPMailer');
        }
    };
    $verifyQueue();

    $previousValidator = PHPMailer::$validator;
    try {
        PHPMailer::$validator = 'php';
        $mailer = new PHPMailer(true);
        $mailer->addAddress('josé@example.org');
        if (PHPMailer::$validator !== 'eai') {
            throw new RuntimeException('Expected PHPMailer to switch to its EAI validator');
        }
        if ($repository->getCommunicationStats()['reachable_with_email'] !== $baseline + count($expected)
            || $service->countAudience($filters) !== count($expected)) {
            throw new RuntimeException('Email summary or preview changed after PHPMailer switched to EAI');
        }
        $verifyQueue();
    } finally {
        PHPMailer::$validator = $previousValidator;
    }
};
