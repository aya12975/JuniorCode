<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = $_GET["id"] ?? 0;

$stmt = $conn->prepare("SELECT * FROM teacher_availability WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$slot = $result->fetch_assoc();

if (!$slot) {
    die("Slot not found");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $teacher = $slot["teacher_name"];
    $date = $slot["available_date"];
    $time = $slot["available_time"];

    $student = $_POST["student_name"];
    $type = $_POST["type"];
    $details = $_POST["details"];

    $insert = $conn->prepare("
        INSERT INTO classes (teacher_name, student_name, class_date, class_time, type, details)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insert->bind_param("ssssss", $teacher, $student, $date, $time, $type, $details);
    $insert->execute();

    // remove slot after using it
    $delete = $conn->prepare("DELETE FROM teacher_availability WHERE id=?");
    $delete->bind_param("i", $id);
    $delete->execute();

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
    <input type="text" class="form-control" value="<?php echo $slot["teacher_name"]; ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Date</label>
    <input type="text" class="form-control" value="<?php echo $slot["available_date"]; ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Time</label>
    <input type="text" class="form-control" value="<?php echo $slot["available_time"]; ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Student Name</label>
    <input type="text" name="student_name" class="form-control" required>
  </div>

  <div class="mb-3">
    <label>Type</label>
    <select name="type" class="form-control">
      <option>Paid</option>
      <option>Demo</option>
      <option>Half Pay</option>
      <option>No Pay</option>
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