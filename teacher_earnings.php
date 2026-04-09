<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$earnings = [];

$result = $conn->query("SELECT * FROM teacher_earnings ORDER BY id DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $earnings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teacher Earnings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: Arial, sans-serif;
    }

    .page-box {
      max-width: 1200px;
      margin: 40px auto;
      background: white;
      border-radius: 20px;
      box-shadow: 0 12px 28px rgba(15,23,42,0.08);
      padding: 30px;
    }

    .money-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      background: #ecfdf5;
      color: #065f46;
      font-weight: bold;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>

<div class="page-box">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="mb-1">Teacher Earnings</h1>
      <p class="text-muted mb-0">Admin can view all teacher earning records here.</p>
    </div>
    <div>
      <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>

  <?php if (isset($_GET["updated"])): ?>
    <div class="alert alert-success">Earning updated successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET["deleted"])): ?>
    <div class="alert alert-success">Earning deleted successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET["added"])): ?>
    <div class="alert alert-success">Earning added successfully.</div>
  <?php endif; ?>

  <div class="mb-3">
    <a href="add_earning.php" class="btn btn-primary">+ Add New Earning</a>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Teacher Name</th>
          <th>Lesson Title</th>
          <th>Amount</th>
          <th>Lesson Date</th>
          <th>Notes</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($earnings)): ?>
          <?php foreach ($earnings as $earning): ?>
            <tr>
              <td><?php echo $earning["id"]; ?></td>
              <td><?php echo htmlspecialchars($earning["teacher_name"]); ?></td>
              <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
              <td>
                <span class="money-badge">
                  $<?php echo number_format($earning["amount"], 2); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars($earning["lesson_date"]); ?></td>
              <td><?php echo htmlspecialchars($earning["notes"]); ?></td>
              <td>
                <a href="edit_earning.php?id=<?php echo $earning["id"]; ?>" class="btn btn-warning btn-sm">Edit</a>
                <a href="delete_earning.php?id=<?php echo $earning["id"]; ?>" class="btn btn-danger btn-sm">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No earning records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>