<?php
require_once __DIR__ . '/../config/config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$value = (int)($_POST['value'] ?? 0) ? 1 : 0;

$stmt = $pdo->prepare('UPDATE deals SET issue_invoice = ?, updated_at = ? WHERE id = ?');
$stmt->execute([$value, date('Y-m-d H:i:s'), $id]);

echo json_encode(['ok' => true, 'issue_invoice' => $value]);
