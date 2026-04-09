<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: teacher_earnings.php");
    exit();
}

$id = (int) $_GET["id"];

$stmt = $conn->prepare("SELECT * FROM teacher_earnings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: teacher_earnings.php");
    exit();
}

$earning = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $delete = $conn->prepare("DELETE FROM teacher_earnings WHERE id = ?");
    $delete->bind_param("i", $id);

    if ($delete->execute()) {
        header("Location: teacher_earnings.php?deleted=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Earning</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: Arial, sans-serif;
    }
    .delete-box {
      max-width: 650px;
      margin: 60px auto;
      background: white;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 12px 28px rgba(15,23,42,0.08);
    }
  </style>
</head>
<body>

<div class="delete-box">
  <h2 class="mb-3 text-danger">Delete Earning</h2>
  <p>Are you sure you want to delete this earning record?</p>

  <ul class="list-group mb-4">
    <li class="list-group-item"><strong>Teacher:</strong> <?php echo htmlspecialchars($earning["teacher_name"]); ?></li>
    <li class="list-group-item"><strong>Lesson:</strong> <?php echo htmlspecialchars($earning["lesson_title"]); ?></li>
    <li class="list-group-item"><strong>Amount:</strong> $<?php echo number_format($earning["amount"], 2); ?></li>
    <li class="list-group-item"><strong>Date:</strong> <?php echo htmlspecialchars($earning["lesson_date"]); ?></li>
  </ul>

  <form method="POST">
    <button type="submit" class="btn btn-danger">Yes, Delete</button>
    <a href="teacher_earnings.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>

</body>
</html>