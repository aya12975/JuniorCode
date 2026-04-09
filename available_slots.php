<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$slots = [];

$result = $conn->query("
    SELECT * FROM teacher_availability
    WHERE status='available'
    ORDER BY available_date ASC, available_time ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Available Slots</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<h2 class="mb-4">Teacher Available Slots</h2>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Teacher</th>
      <th>Date</th>
      <th>Time</th>
      <th>Action</th>
    </tr>
  </thead>

  <tbody>
    <?php foreach ($slots as $slot): ?>
      <tr>
        <td><?php echo $slot["teacher_name"]; ?></td>
        <td><?php echo $slot["available_date"]; ?></td>
        <td><?php echo $slot["available_time"]; ?></td>
        <td>
          <a href="create_class.php?id=<?php echo $slot["id"]; ?>" class="btn btn-primary">
            Create Class
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>