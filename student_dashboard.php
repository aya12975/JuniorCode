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
    :root {
      --primary:      #3e5077;
      --secondary:    #143674;
      --dark:         #0f172a;
      --muted:        #64748b;
      --shadow:       0 18px 45px rgba(37,99,235,0.08);
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 255px;
      height: 100vh;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
      overflow-y: auto;
    }

    .sidebar-top {
      padding: 20px 16px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 10px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 16px;
    }

    .brand-logo-img {
      width: 55px;
      height: 55px;
      object-fit: contain;
      border-radius: 12px;
      background: none;
      padding: 4px;
      flex-shrink: 0;
    }

    .brand-title {
      font-size: 1.05rem;
      font-weight: 900;
      margin: 0;
      color: #ffffff;
      line-height: 1.2;
    }

    .brand-subtitle {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.55);
      margin: 3px 0 0;
      letter-spacing: 1px;
    }

    .student-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .student-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .student-name {
      font-weight: 800;
      margin: 0;
      color: #ffffff;
    }

    .student-role {
      margin: 0;
      color: rgba(255,255,255,0.55);
      font-size: 0.85rem;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: rgba(255,255,255,0.78);
      padding: 12px 14px;
      border-radius: 14px;
      margin: 4px 0;
      font-weight: 700;
      transition: all 0.22s ease;
    }

    .nav-link-custom:hover {
      background: rgba(255,255,255,0.09);
      color: #ffffff;
    }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #ffffff;
    }

    .nav-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
    }

    .sidebar-bottom {
      padding: 16px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    .main {
      margin-left: 255px;
      padding: 26px;
    }

    .topbar {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px;
      padding: 18px 22px;
      margin-bottom: 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow);
    }

    .topbar h1 {
      color: white;
      margin: 0;
      font-weight: 900;
      font-size: 1.4rem;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.8);
      font-size: 0.9rem;
    }

    .topbar-user {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: bold;
      color: white;
    }

    .small-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    .hero {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      border-radius: 22px;
      padding: 22px 24px;
      margin-bottom: 22px;
      box-shadow: 0 12px 30px rgba(30, 50, 100, 0.18);
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
      border: 1px solid #edf4ff;
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
      <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
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
    <div>
      <h1>Student Dashboard</h1>
      <p>Welcome back, <?php echo htmlspecialchars($studentName); ?></p>
    </div>
    <div class="topbar-user">
      <div class="small-avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
      <span><?php echo htmlspecialchars($studentName); ?></span>
    </div>
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