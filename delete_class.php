<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage_classes.php");
    exit();
}

$id = (int)$_GET["id"];

/* Get class */
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();

if (!$class) {
    header("Location: manage_classes.php");
    exit();
}

/* Delete after confirmation */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt2 = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $stmt2->bind_param("i", $id);

    if ($stmt2->execute()) {
        header("Location: manage_classes.php?deleted=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Class</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f4f7fb;
      font-family: Arial, sans-serif;
    }

    .page-wrap {
      max-width: 700px;
      margin: 50px auto;
      padding: 0 15px;
    }

    .card-box {
      background: white;
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
  </style>
</head>
<body>

<div class="page-wrap">
  <div class="card-box">
    <h1 class="mb-3 text-danger">Delete Class</h1>
    <p>Are you sure you want to delete this class record?</p>

    <table class="table table-bordered">
      <tr>
        <th>Teacher</th>
        <td><?php echo htmlspecialchars($class["teacher_name"]); ?></td>
      </tr>
      <tr>
        <th>Student</th>
        <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
      </tr>
      <tr>
        <th>Date</th>
        <td><?php echo htmlspecialchars($class["class_date"]); ?></td>
      </tr>
      <tr>
        <th>Time</th>
        <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
      </tr>
      <tr>
        <th>Type</th>
        <td><?php echo htmlspecialchars($class["type"]); ?></td>
      </tr>
      <tr>
        <th>Details</th>
        <td><?php echo htmlspecialchars($class["details"]); ?></td>
      </tr>
    </table>

    <form method="POST" class="d-flex gap-2">
      <button type="submit" class="btn btn-danger">Yes, Delete</button>
      <a href="manage_classes.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>

</body>
</html>