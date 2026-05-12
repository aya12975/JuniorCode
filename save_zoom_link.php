<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$classId = (int)($_POST['class_id'] ?? 0);
$link    = trim($_POST['zoom_link'] ?? '');

if (!$classId || $link === '') {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

$stmt = $conn->prepare("UPDATE classes SET zoom_link = ? WHERE id = ?");
$stmt->bind_param("si", $link, $classId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'join_url' => $link]);
