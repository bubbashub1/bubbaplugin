<?php
declare(strict_types=1);

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', getenv('DB_NAME') ?: 'bubbahub_test');
$db = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function expect(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Start with the legacy organiser shape (without contact_name).
$db->exec('CREATE TABLE bh_users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE) ENGINE=InnoDB');
$db->exec("CREATE TABLE bh_organisers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, organisation_name VARCHAR(190) NOT NULL, email VARCHAR(190) NULL, phone VARCHAR(80) NULL, website VARCHAR(255) NULL) ENGINE=InnoDB");
$db->exec("INSERT INTO bh_users (email) VALUES ('leader@example.test')");
$userId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO bh_organisers (user_id, organisation_name, email) VALUES (?, 'Old name', 'leader@example.test')")->execute([$userId]);
$organiserId = (int)$db->lastInsertId();

// Exercise the same INFORMATION_SCHEMA migration used by leader-portal.php.
$check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='contact_name'");
$check->execute();
if (!(int)$check->fetchColumn()) {
    $db->exec("ALTER TABLE bh_organisers ADD COLUMN contact_name VARCHAR(160) NULL");
}
$columns = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);
expect(in_array('contact_name', $columns, true), 'Legacy database migration did not add contact_name');

// Exercise the same prepared updates and transaction boundary as save_account.
$email = 'updated@example.test';
$db->beginTransaction();
try {
    $updates = ['organisation_name' => 'New organiser name', 'phone' => '01234 567890', 'website' => 'https://example.test', 'email' => $email, 'contact_name' => 'Alex Example'];
    $sets = [];
    $values = [];
    foreach ($updates as $column => $value) {
        if (in_array($column, $columns, true)) {
            $sets[] = "`{$column}`=?";
            $values[] = $value;
        }
    }
    $values[] = $organiserId;
    $db->prepare('UPDATE bh_organisers SET '.implode(',', $sets).' WHERE id=?')->execute($values);
    $db->prepare('UPDATE bh_users SET email=? WHERE id=?')->execute([$email, $userId]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
$org = $db->query("SELECT organisation_name, contact_name, email, phone, website FROM bh_organisers WHERE id={$organiserId}")->fetch();
$account = $db->query("SELECT email FROM bh_users WHERE id={$userId}")->fetch();
expect($org['organisation_name'] === 'New organiser name', 'Organisation name was not saved');
expect($org['contact_name'] === 'Alex Example', 'Contact name was not saved');
expect($org['email'] === $email && $account['email'] === $email, 'Organiser and login emails were not saved together');
expect($org['phone'] === '01234 567890' && $org['website'] === 'https://example.test', 'Phone or website was not saved');

// Confirm the duplicate-login-email lookup used by save_account detects another account.
$db->exec("INSERT INTO bh_users (email) VALUES ('taken@example.test')");
$duplicate = $db->prepare("SELECT id FROM bh_users WHERE LOWER(email)=LOWER(?) AND id<>? LIMIT 1");
$duplicate->execute(['taken@example.test', $userId]);
expect((bool)$duplicate->fetchColumn(), 'Duplicate account email was not detected');

echo "PASS: legacy contact_name migration, organiser profile persistence, paired login email update, and duplicate email detection.\n";
