<?php
require_once "security.php";
require_once "connect.php";
requireLogin();

$adminId = $_SESSION['UserID'];
$requestId = (int)$_POST['request_id'];
$message = trim($_POST['message']);

$stmt = $conn->prepare("
INSERT INTO supply_request_messages
(request_id,sender_id,sender_role,message)
VALUES (?,?,?,?)
");

$stmt->execute([
    $requestId,
    $adminId,
    'admin',
    $message
]);

echo 'ok';
