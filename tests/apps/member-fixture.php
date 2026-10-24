<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
require '/opt/amea/config/database.php';
$conn->prepare('INSERT INTO users (username,password,last_name,first_name,email,role) VALUES (?,?,?,?,?,?)')->execute(['member-reader',password_hash('Fixture-password-123!', PASSWORD_DEFAULT),'Reader','Staff','reader@example.invalid','user']);
$conn->exec("UPDATE users SET permissions='[\"students\"]' WHERE username='expired'");
@mkdir('/opt/amea/uploads/students',0770,true);
file_put_contents('/opt/amea/uploads/students/member-review.pdf', "%PDF-1.4\nShared synthetic identity\n%%EOF\n");
$conn->prepare('INSERT INTO students(last_name,first_name,gender,status,phone,email,identity_document,cv_path,kyc_status,review_token) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(['SharedMember','Aminata','Féminin','ETUDIANT','771234568','shared@example.invalid','uploads/students/member-review.pdf','uploads/students/member-review.pdf','UNDER_REVIEW',str_repeat('d',64)]);
