<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_name = $_POST["teacher_name"];
    $lesson_title = $_POST["lesson_title"];
    $amount = $_POST["amount"];
    $lesson_date = $_POST["lesson_date"];
    $notes = $_POST["notes"];

    $stmt = $conn->prepare("INSERT INTO teacher_earnings (teacher_name, lesson_title, amount, lesson_date, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $teacher_name, $lesson_title, $amount, $lesson_date, $notes);

    if ($stmt->execute()) {
        $message = "Earning added successfully!";
    } else {
        $message = "Error adding earning.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Earning</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background: #f8fafc;
}
.form-box {
    max-width: 600px;
    margin: 60px auto;
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
</style>
</head>
<body>

<div class="form-box">
    <h2 class="mb-4">Add Teacher Earning</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Teacher Name</label>
            <input type="text" name="teacher_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Lesson Title</label>
            <input type="text" name="lesson_title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Amount ($)</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Lesson Date</label>
            <input type="date" name="lesson_date" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Notes</label>
            <textarea name="notes" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">Save</button>
    </form>

    <div class="mt-3 text-center">
        <a href="teacher_earnings.php">Back</a>
    </div>
</div>

</body>
</html>