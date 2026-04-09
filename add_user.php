<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $role = trim($_POST["role"] ?? "");

    if ($username === "" || $password === "" || $role === "") {
        header("Location: add_user.php?error=1");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashedPassword, $role);

    if ($stmt->execute()) {
        header("Location: manage_users.php?success=1");
        exit();
    } else {
        header("Location: add_user.php?error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add User</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f4f8ff;
      font-family: Arial, Helvetica, sans-serif;
    }
    .wrap {
      max-width: 600px;
      margin: 50px auto;
    }
    .card-box {
      background: white;
      padding: 28px;
      border-radius: 22px;
      box-shadow: 0 16px 35px rgba(37,99,235,0.08);
    }
    .btn-main {
      background: linear-gradient(135deg, #2563eb, #38bdf8);
      color: white;
      border: none;
      border-radius: 14px;
      padding: 12px 18px;
      font-weight: 800;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card-box">
      <h2 class="mb-4">Add User</h2>

      <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">Please fill all fields correctly.</div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="">Select role</option>
            <option value="admin">Admin</option>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
          </select>
        </div>

        <button type="submit" class="btn-main">Add User</button>
        <a href="manage_users.php" class="btn btn-secondary ms-2">Back</a>
      </form>
    </div>
  </div>
</body>
</html>