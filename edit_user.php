<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = $_GET["id"] ?? null;
if (!$id) {
    header("Location: manage_users.php?error=1");
    exit();
}

$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: manage_users.php?error=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $role = trim($_POST["role"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($username === "" || $role === "") {
        header("Location: edit_user.php?id=" . $id . "&error=1");
        exit();
    }

    if ($password !== "") {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, plain_password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $hashedPassword, $password, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $role, $id);
    }

    if ($stmt->execute()) {
        header("Location: manage_users.php?success=1");
        exit();
    } else {
        header("Location: edit_user.php?id=" . $id . "&error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit User</title>
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
      <h2 class="mb-4">Edit User</h2>

      <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">Please fix the form and try again.</div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user["username"]); ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" placeholder="Leave empty to keep current password">
        </div>

        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="admin" <?php echo $user["role"] === "admin" ? "selected" : ""; ?>>Admin</option>
            <option value="teacher" <?php echo $user["role"] === "teacher" ? "selected" : ""; ?>>Teacher</option>
            <option value="student" <?php echo $user["role"] === "student" ? "selected" : ""; ?>>Student</option>
          </select>
        </div>

        <button type="submit" class="btn-main">Update User</button>
        <a href="manage_users.php" class="btn btn-secondary ms-2">Back</a>
      </form>
    </div>
  </div>
</body>
</html>