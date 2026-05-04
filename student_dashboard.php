<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"];

$totalClasses = 0;
$upcomingClasses = 0;
$latestClassDate = "-";
$classes = [];

/* Summary */
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_classes,
        SUM(CASE WHEN class_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_classes,
        MAX(class_date) AS latest_class_date
    FROM classes
    WHERE student_name = ?
");
$stmt->bind_param("s", $studentName);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $totalClasses = (int)($row["total_classes"] ?? 0);
    $upcomingClasses = (int)($row["upcoming_classes"] ?? 0);
    $latestClassDate = $row["latest_class_date"] ? $row["latest_class_date"] : "-";
}

/* Student classes */
$stmt2 = $conn->prepare("
    SELECT id, teacher_name, class_date, class_time, type, details
    FROM classes
    WHERE student_name = ?
    ORDER BY class_date DESC, class_time ASC
");
$stmt2->bind_param("s", $studentName);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $classes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      background: #f4f7fb;
      font-family: Arial, sans-serif;
      color: #1e293b;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 255px;
      height: 100vh;
      background: #ffffff;
      border-right: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
    }

    .sidebar-top {
      padding: 18px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 6px 8px 18px 8px;
      border-bottom: 1px solid #eef2f7;
      margin-bottom: 18px;
    }

    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 18px;
    }

    .brand-title {
      font-size: 1.1rem;
      font-weight: bold;
      margin: 0;
    }

    .brand-subtitle {
      font-size: 0.85rem;
      color: #64748b;
      margin: 0;
    }

    .student-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .student-avatar {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: #dbeafe;
      color: #1d4ed8;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }

    .student-name {
      font-weight: bold;
      margin: 0;
    }

    .student-role {
      margin: 0;
      color: #64748b;
      font-size: 0.9rem;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #334155;
      padding: 13px 14px;
      border-radius: 12px;
      margin: 6px 4px;
      font-weight: 600;
      transition: 0.25s;
    }

    .nav-link-custom:hover,
    .nav-link-custom.active {
      background: #e8f0ff;
      color: #1d4ed8;
    }

    .nav-icon {
      width: 20px;
      text-align: center;
      font-size: 16px;
    }

    .sidebar-bottom {
      padding: 18px;
      border-top: 1px solid #eef2f7;
    }

    .main {
      margin-left: 255px;
      padding: 26px;
    }

    .topbar {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      padding: 14px 18px;
      margin-bottom: 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .topbar-user {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: bold;
    }

    .small-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #1d4ed8;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    .hero {
      background: linear-gradient(135deg, #0f4fd6, #1d4ed8);
      color: white;
      border-radius: 22px;
      padding: 22px 24px;
      margin-bottom: 22px;
      box-shadow: 0 12px 30px rgba(29, 78, 216, 0.18);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .hero h2 {
      margin: 0;
      font-size: 1.6rem;
      font-weight: bold;
    }

    .hero p {
      margin: 4px 0 0;
      opacity: 0.92;
    }

    .panel-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 22px;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }

    .panel-title {
      font-size: 1.05rem;
      font-weight: bold;
      margin-bottom: 14px;
    }

    .soft-stat {
      border-radius: 16px;
      padding: 18px;
      text-align: center;
      font-weight: bold;
    }

    .soft-green { background: #ecfeff; color: #0f766e; }
    .soft-blue { background: #eff6ff; color: #1d4ed8; }
    .soft-purple { background: #f5f3ff; color: #6d28d9; }

    .big-stat {
      font-size: 1.8rem;
      font-weight: bold;
      margin-top: 8px;
    }

    .table thead th {
      background: #f8fafc;
      color: #475569;
      font-size: 0.9rem;
      border-bottom: 1px solid #e5e7eb;
    }

    .badge-paid {
      background: #dcfce7;
      color: #166534;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .badge-demo {
      background: #fef3c7;
      color: #92400e;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .empty-box {
      text-align: center;
      color: #94a3b8;
      padding: 30px 10px;
    }

    .contact-box {
      color: #475569;
      line-height: 1.7;
    }

    @media (max-width: 991px) {
      .sidebar {
        position: static;
        width: 100%;
        height: auto;
      }

      .main {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <div class="brand-logo">JC</div>
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">Student Panel</p>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
      <div>
        <p class="student-name"><?php echo htmlspecialchars($studentName); ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <a href="#dashboard" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-house"></i></span>
      <span>Dashboard</span>
    </a>

    <a href="student_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-book"></i></span>
      <span>My Classes</span>
    </a>

    <a href="#contact" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-comments"></i></span>
      <span>Contact Admin</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
      <span>Logout</span>
    </a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <div class="topbar-user">
      <div class="small-avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
      <span><?php echo htmlspecialchars($studentName); ?></span>
    </div>
    <div class="text-muted fw-semibold">Student Dashboard</div>
  </div>

  <section id="dashboard" class="hero">
    <div>
      <h2>Hello, <?php echo htmlspecialchars($studentName); ?></h2>
      <p>Welcome to your learning dashboard</p>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="panel-card">
        <div class="soft-stat soft-green">
          <div>Total Classes</div>
          <div class="big-stat"><?php echo $totalClasses; ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="panel-card">
        <div class="soft-stat soft-blue">
          <div>Upcoming Classes</div>
          <div class="big-stat"><?php echo $upcomingClasses; ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="panel-card">
        <div class="soft-stat soft-purple">
          <div>Latest Class Date</div>
          <div class="big-stat" style="font-size: 1.1rem;"><?php echo htmlspecialchars($latestClassDate); ?></div>
        </div>
      </div>
    </div>
  </div>

  <section id="classes" class="panel-card">
    <div class="panel-title">My Classes</div>

    <?php if (!empty($classes)): ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Teacher</th>
              <th>Date</th>
              <th>Time</th>
              <th>Type</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($classes as $class): ?>
              <tr>
                <td><?php echo $class["id"]; ?></td>
                <td><?php echo htmlspecialchars($class["teacher_name"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_date"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
                <td>
                  <span class="<?php echo strtolower($class["type"]) === "paid" ? "badge-paid" : "badge-demo"; ?>">
                    <?php echo htmlspecialchars($class["type"]); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($class["details"]); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-box">No classes assigned yet.</div>
    <?php endif; ?>
  </section>

  <section id="contact" class="panel-card">
    <div class="panel-title">Contact Admin</div>
    <div class="contact-box">
      For payment and scheduling questions, please contact the admin on WhatsApp.
      <br><br>
      <a href="#" class="btn btn-success">Contact on WhatsApp</a>
    </div>
  </section>
</div>

</body>
</html>