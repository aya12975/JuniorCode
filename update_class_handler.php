<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id           = (int)($_POST['id']           ?? 0);
$teacher_id   = (int)($_POST['teacher_id']   ?? 0);
$teacher_name = trim($_POST['teacher_name']  ?? '');
$student_name = trim($_POST['student_name']  ?? '');
$class_date   = trim($_POST['class_date']    ?? '');
$class_time   = trim($_POST['class_time']    ?? '');
$type         = trim($_POST['type']          ?? '');
$details      = trim($_POST['details']       ?? '');
$zoom_link    = trim($_POST['zoom_link']     ?? '');

if (!$id || !$teacher_name || !$student_name || !$class_date || !$class_time || !$type) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit();
}

if ($teacher_id === 0) {
    $tq = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'teacher' LIMIT 1");
    if ($tq) {
        $tq->bind_param("s", $teacher_name);
        $tq->execute();
        $tRow = $tq->get_result()->fetch_assoc();
        if ($tRow) $teacher_id = (int)$tRow['id'];
    }
}

$stmt = $conn->prepare("UPDATE classes SET teacher_id=?, teacher_name=?, student_name=?, class_date=?, class_time=?, type=?, details=?, zoom_link=? WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit();
}
$stmt->bind_param("isssssssi", $teacher_id, $teacher_name, $student_name, $class_date, $class_time, $type, $details, $zoom_link, $id);
echo $stmt->execute()
    ? json_encode(['success' => true, 'teacher_name' => $teacher_name, 'student_name' => $student_name, 'class_date' => $class_date, 'class_time' => $class_time, 'type' => $type, 'details' => $details])
    : json_encode(['success' => false, 'message' => $stmt->error]);
