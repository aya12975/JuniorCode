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
$message = "";

// Get current record
$stmt = $conn->prepare("SELECT * FROM teacher_earnings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: teacher_earnings.php");
    exit();
}

$earning = $result->fetch_assoc();

// Update record
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_name = trim($_POST["teacher_name"]);
    $lesson_title = trim($_POST["lesson_title"]);
    $amount = trim($_POST["amount"]);
    $lesson_date = trim($_POST["lesson_date"]);
    $notes = trim($_POST["notes"]);

    $update = $conn->prepare("UPDATE teacher_earnings SET teacher_name = ?, lesson_title = ?, amount = ?, lesson_date = ?, notes = ? WHERE id = ?");
    $update->bind_param("ssdssi", $teacher_name, $lesson_title, $amount, $lesson_date, $notes, $id);

    if ($update->execute()) {
        header("Location: teacher_earnings.php?updated=1");
        exit();
    } else {
        $message = "Error updating earning.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Earning</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: Arial, sans-serif;
    }
    .form-box {
      max-width: 650px;
      margin: 50px auto;
      background: white;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 12px 28px rgba(15,23,42,0.08);
    }
  </style>
</head>
<body>

<div class="form-box">
  <h2 class="mb-4">Edit Teacher Earning</h2>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?php echo $message; ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Teacher Name</label>
      <input type="text" name="teacher_name" class="form-control"
             value="<?php echo htmlspecialchars($earning["teacher_name"]); ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Lesson Title</label>
      <input type="text" name="lesson_title" class="form-control"
             value="<?php echo htmlspecialchars($earning["lesson_title"]); ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Amount ($)</label>
      <input type="number" step="0.01" name="amount" class="form-control"
             value="<?php echo htmlspecialchars($earning["amount"]); ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Lesson Date</label>
      <input type="date" name="lesson_date" class="form-control"
             value="<?php echo htmlspecialchars($earning["lesson_date"]); ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Notes</label>
      <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($earning["notes"]); ?></textarea>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">Update</button>
      <a href="teacher_earnings.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

</body>
</html>