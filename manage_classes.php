<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$message = "";

/* Add new class */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_name = trim($_POST["teacher_name"]);
    $student_name = trim($_POST["student_name"]);
    $class_date   = $_POST["class_date"];
    $class_time   = $_POST["class_time"];
    $type         = trim($_POST["type"]);
    $details      = trim($_POST["details"]);

    if ($teacher_name !== "" && $student_name !== "" && $class_date !== "" && $class_time !== "" && $type !== "") {
        $stmt = $conn->prepare("INSERT INTO classes (teacher_name, student_name, class_date, class_time, type, details) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $teacher_name, $student_name, $class_date, $class_time, $type, $details);

        if ($stmt->execute()) {
            header("Location: manage_classes.php?added=1");
            exit();
        } else {
            $message = "Error adding class.";
        }
    } else {
        $message = "Please fill all required fields.";
    }
}

/* Get all classes */
$classes = [];
$result = $conn->query("SELECT * FROM classes ORDER BY class_date DESC, class_time ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Classes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f4f7fb;
      font-family: Arial, sans-serif;
    }

    .page-wrap {
      max-width: 1250px;
      margin: 35px auto;
      padding: 0 15px;
    }

    .page-header {
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: white;
      border-radius: 24px;
      padding: 28px;
      margin-bottom: 24px;
      box-shadow: 0 14px 35px rgba(37, 99, 235, 0.18);
    }

    .card-box {
      background: white;
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      margin-bottom: 24px;
    }

    .section-title {
      font-size: 1.2rem;
      font-weight: bold;
      margin-bottom: 18px;
    }

    .badge-paid {
      background: #dcfce7;
      color: #166534;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .badge-demo {
      background: #fef3c7;
      color: #92400e;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .badge-other {
      background: #e0e7ff;
      color: #3730a3;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: bold;
    }
  </style>
</head>
<body>

<div class="page-wrap">

  <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h1 class="mb-2">Manage Classes</h1>
      <p class="mb-0">Admin can add, edit, and delete teacher classes from here.</p>
    </div>
    <div>
      <a href="admin_dashboard.php" class="btn btn-light fw-bold">Back to Dashboard</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>

  <?php if (isset($_GET["added"])): ?>
    <div class="alert alert-success">Class added successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET["updated"])): ?>
    <div class="alert alert-success">Class updated successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET["deleted"])): ?>
    <div class="alert alert-success">Class deleted successfully.</div>
  <?php endif; ?>

  <?php if ($message !== ""): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <div class="card-box">
    <div class="section-title">Add New Class</div>

    <form method="POST">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Teacher Name</label>
          <input type="text" name="teacher_name" class="form-control" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Student Name</label>
          <input type="text" name="student_name" class="form-control" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select name="type" class="form-select" required>
            <option value="">Choose type</option>
            <option value="Paid">Paid</option>
            <option value="Demo">Demo</option>
            <option value="Half Pay">Half Pay</option>
            <option value="No Pay">No Pay</option>
            <option value="Demo Enrolled">Demo Enrolled</option>
            <option value="Demo Pending">Demo Pending</option>
            <option value="Demo Other">Demo Other</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Class Date</label>
          <input type="date" name="class_date" class="form-control" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Class Time</label>
          <input type="time" name="class_time" class="form-control" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Details</label>
          <input type="text" name="details" class="form-control" placeholder="Python basics / Demo class / etc.">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">Add Class</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card-box">
    <div class="section-title">All Classes</div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Teacher</th>
            <th>Student</th>
            <th>Date</th>
            <th>Time</th>
            <th>Type</th>
            <th>Details</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($classes)): ?>
            <?php foreach ($classes as $class): ?>
              <tr>
                <td><?php echo $class["id"]; ?></td>
                <td><?php echo htmlspecialchars($class["teacher_name"]); ?></td>
                <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_date"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
                <td>
                  <?php
                    $type = strtolower($class["type"]);
                    if ($type === "paid") {
                        echo '<span class="badge-paid">Paid</span>';
                    } elseif ($type === "demo") {
                        echo '<span class="badge-demo">Demo</span>';
                    } else {
                        echo '<span class="badge-other">' . htmlspecialchars($class["type"]) . '</span>';
                    }
                  ?>
                </td>
                <td><?php echo htmlspecialchars($class["details"]); ?></td>
                <td>
                  <a href="edit_class.php?id=<?php echo $class["id"]; ?>" class="btn btn-warning btn-sm">Edit</a>
                  <a href="delete_class.php?id=<?php echo $class["id"]; ?>" class="btn btn-danger btn-sm">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center text-muted">No classes found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

</body>
</html>