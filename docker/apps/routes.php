<?php
// Deny by default, before dispatch and legacy fallback. Expand staff per tickets 06/07.
return [
    'public' => ['/', '/register.php', '/registration-details.php', '/registration-preview.php', '/kyc-correction.php', '/accept-cgu.php', '/legal-notice.php'],
    'staff' => ['/', '/login.php', '/logout.php', '/forgot-password.php', '/reset-password.php'],
];
