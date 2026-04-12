<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET["id"] ?? 0);

$stmt = $conn->prepare("
    SELECT 
        ta.id,
        ta.teacher_id,
        ta.available_date,
        ta.available_time,
        ta.status,
        u.username AS teacher_name
    FROM teacher_availability ta
    JOIN users u ON ta.teacher_id = u.id
    WHERE ta.id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$slot = $result->fetch_assoc();
$stmt->close();

if (!$slot) {
    die("Slot not found");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacherId = (int)$slot["teacher_id"];
    $teacherName = $slot["teacher_name"];
    $date = $slot["available_date"];
    $time = $slot["available_time"];

    $student = trim($_POST["student_name"] ?? "");
    $type = trim($_POST["type"] ?? "");
    $details = trim($_POST["details"] ?? "");

    if ($student === "" || $type === "") {
        die("Student name and type are required.");
    }

    $insert = $conn->prepare("
        INSERT INTO classes (teacher_id, teacher_name, student_name, class_date, class_time, type, details)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insert) {
        die("Insert prepare failed: " . $conn->error);
    }

    $insert->bind_param("issssss", $teacherId, $teacherName, $student, $date, $time, $type, $details);
    $insert->execute();
    $insert->close();

    $delete = $conn->prepare("DELETE FROM teacher_availability WHERE id = ?");

    if (!$delete) {
        die("Delete prepare failed: " . $conn->error);
    }

    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();

    header("Location: manage_classes.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Class</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<h2>Create Class</h2>

<form method="POST">

  <div class="mb-3">
    <label>Teacher</label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars($slot["teacher_name"]); ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Date</label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars($slot["available_date"]); ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Time</label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars($slot["available_time"]); ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Student Name</label>
    <input type="text" name="student_name" class="form-control" required>
  </div>

  <div class="mb-3">
    <label>Type</label>
    <select name="type" class="form-control" required>
      <option value="Paid">Paid</option>
      <option value="Demo">Demo</option>
      <option value="Half Pay">Half Pay</option>
      <option value="No Pay">No Pay</option>
    </select>
  </div>

  <div class="mb-3">
    <label>Details</label>
    <textarea name="details" class="form-control"></textarea>
  </div>

  <button class="btn btn-success">Create Class</button>

</form>

</body>
</html>