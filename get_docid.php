<?php
require_once "connect.php";

$last = (int)$conn
    ->query("SELECT MAX(DocID) FROM documents")
    ->fetchColumn();

echo json_encode([
    'last' => $last,
    'next' => $last + 1
]);
