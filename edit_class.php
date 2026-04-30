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
$message = "";

/* Get current class */
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();

if (!$class) {
    header("Location: manage_classes.php");
    exit();
}

/* Update class */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_name = trim($_POST["teacher_name"]);
    $student_name = trim($_POST["student_name"]);
    $class_date   = $_POST["class_date"];
    $class_time   = $_POST["class_time"];
    $type         = trim($_POST["type"]);
    $details      = trim($_POST["details"]);
    $zoom_link    = trim($_POST["zoom_link"] ?? "");

    if ($teacher_name !== "" && $student_name !== "" && $class_date !== "" && $class_time !== "" && $type !== "") {
        $stmt2 = $conn->prepare("UPDATE classes SET teacher_name = ?, student_name = ?, class_date = ?, class_time = ?, type = ?, details = ?, zoom_link = ? WHERE id = ?");
        $stmt2->bind_param("sssssssi", $teacher_name, $student_name, $class_date, $class_time, $type, $details, $zoom_link, $id);

        if ($stmt2->execute()) {
            header("Location: manage_classes.php?updated=1");
            exit();
        } else {
            $message = "Error updating class.";
        }
    } else {
        $message = "Please fill all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Class</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f4f7fb;
      font-family: Arial, sans-serif;
    }

    .page-wrap {
      max-width: 900px;
      margin: 40px auto;
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
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h1 class="mb-0">Edit Class</h1>
      <a href="manage_classes.php" class="btn btn-secondary">Back</a>
    </div>

    <?php if ($message !== ""): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Teacher Name</label>
          <input type="text" name="teacher_name" class="form-control" value="<?php echo htmlspecialchars($class["teacher_name"]); ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Student Name</label>
          <input type="text" name="student_name" class="form-control" value="<?php echo htmlspecialchars($class["student_name"]); ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Class Date</label>
          <input type="date" name="class_date" class="form-control" value="<?php echo htmlspecialchars($class["class_date"]); ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Class Time</label>
          <input type="time" name="class_time" class="form-control" value="<?php echo htmlspecialchars($class["class_time"]); ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select name="type" class="form-select" required>
            <option value="Paid" <?php if ($class["type"] === "Paid") echo "selected"; ?>>Paid</option>
            <option value="Demo" <?php if ($class["type"] === "Demo") echo "selected"; ?>>Demo</option>
            <option value="Half Pay" <?php if ($class["type"] === "Half Pay") echo "selected"; ?>>Half Pay</option>
            <option value="No Pay" <?php if ($class["type"] === "No Pay") echo "selected"; ?>>No Pay</option>
            <option value="Demo Enrolled" <?php if ($class["type"] === "Demo Enrolled") echo "selected"; ?>>Demo Enrolled</option>
            <option value="Demo Pending" <?php if ($class["type"] === "Demo Pending") echo "selected"; ?>>Demo Pending</option>
            <option value="Demo Other" <?php if ($class["type"] === "Demo Other") echo "selected"; ?>>Demo Other</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Details</label>
          <textarea name="details" class="form-control" rows="3"><?php echo htmlspecialchars($class["details"]); ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Zoom Link <span class="text-muted fw-normal">(optional)</span></label>
          <input type="url" name="zoom_link" class="form-control" placeholder="https://zoom.us/j/..." value="<?php echo htmlspecialchars($class["zoom_link"] ?? ""); ?>">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">Update Class</button>
        </div>
      </div>
    </form>
  </div>
</div>

</body>
</html>