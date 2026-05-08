<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

$selectedYear = (int)($_GET["year"] ?? date("Y"));

/* ── All earnings for the year grouped by month + type ── */
$stmt = $conn->prepare("
    SELECT
        MONTH(te.lesson_date)                             AS month_num,
        COALESCE(NULLIF(TRIM(c.type), ''), 'Other')       AS class_type,
        COUNT(*)                                           AS session_count,
        SUM(te.amount)                                     AS total_amount
    FROM teacher_earnings te
    LEFT JOIN classes c ON te.class_id = c.id
    WHERE (te.teacher_id = ? OR (te.teacher_id IS NULL AND LOWER(te.teacher_name) = LOWER(?)))
      AND te.lesson_date IS NOT NULL
      AND YEAR(te.lesson_date) = ?
    GROUP BY month_num, class_type
    ORDER BY month_num ASC, class_type ASC
");

/* Build a 1–12 indexed array */
$byMonth = [];
for ($m = 1; $m <= 12; $m++) $byMonth[$m] = ["total" => 0, "sessions" => 0, "types" => []];

if ($stmt) {
    $stmt->bind_param("isi", $teacherId, $teacherName, $selectedYear);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($row = $rows->fetch_assoc()) {
        $m   = (int)$row["month_num"];
        $amt = (float)$row["total_amount"];
        $cnt = (int)$row["session_count"];
        $byMonth[$m]["types"][$row["class_type"]] = ["count" => $cnt, "amount" => $amt];
        $byMonth[$m]["total"]    += $amt;
        $byMonth[$m]["sessions"] += $cnt;
    }
    $stmt->close();
}

/* Available years */
$years = [];
$yr = $conn->query("
    SELECT DISTINCT YEAR(lesson_date) AS yr FROM teacher_earnings
    WHERE lesson_date IS NOT NULL
      AND (teacher_id = $teacherId OR LOWER(teacher_name) = LOWER('" . $conn->real_escape_string($teacherName) . "'))
    ORDER BY yr DESC
");
if ($yr) while ($r = $yr->fetch_assoc()) $years[] = (int)$r["yr"];
if (!in_array($selectedYear, $years)) $years[] = $selectedYear;
rsort($years);

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Earnings | Teacher</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
    * { box-sizing: border-box; }
    body {
      margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--dark);
      background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
                  radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
    }

    /* Sidebar */
    .sidebar { position:fixed;top:0;left:0;width:260px;height:100vh;background:linear-gradient(180deg,#0f172a 0%,#172554 100%);display:flex;flex-direction:column;justify-content:space-between;z-index:1000;overflow-y:auto;transition:transform 0.3s ease; }
    body.sidebar-collapsed .sidebar { transform: translateX(-260px); }
    .sidebar-top { padding:20px 16px; }
    .brand { display:flex;align-items:center;gap:12px;padding:10px 10px 18px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:16px; }
    .brand-logo-img { width:55px;height:55px;object-fit:contain;border-radius:0;background:none;padding:0;flex-shrink:0; }
    .brand-title { font-size:1.05rem;font-weight:900;margin:0;color:#fff;line-height:1.2; }
    .brand-subtitle { font-size:0.75rem;color:rgba(255,255,255,0.55);margin:3px 0 0;letter-spacing:1px; }
    .teacher-box { display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);border-radius:16px;padding:14px;margin-bottom:18px; }
    .teacher-avatar { width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;font-weight:bold;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;overflow:hidden; }
    .teacher-avatar img { width:100%;height:100%;object-fit:cover; }
    .teacher-name { font-weight:800;margin:0;color:#fff; }
    .teacher-role { margin:0;color:rgba(255,255,255,0.55);font-size:0.85rem; }
    .nav-link-custom { display:flex;align-items:center;gap:12px;text-decoration:none;color:rgba(255,255,255,0.78);padding:12px 14px;border-radius:14px;margin:4px 0;font-weight:700;transition:all 0.22s; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.09);color:#fff; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff; }
    .nav-icon { width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .sidebar-bottom { padding:16px;border-top:1px solid rgba(255,255,255,0.1); }

    /* Main */
    .main { margin-left:260px;padding:26px;min-height:100vh;transition:margin-left 0.3s ease; }
    body.sidebar-collapsed .main { margin-left: 0; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .hero { background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;border-radius:22px;padding:18px 22px;margin-bottom:24px;box-shadow:0 12px 28px rgba(37,99,235,0.3);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px; }
    .hero h2 { margin:0;font-size:1.5rem;font-weight:900; }
    .hero p { margin:4px 0 0;color:rgba(255,255,255,0.8); }
    .hero-badge { background:rgba(255,255,255,0.15);color:white;border-radius:999px;padding:10px 16px;font-weight:800;white-space:nowrap; }

    /* Year tabs */
    .year-bar { display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap; }
    .year-btn { padding:7px 18px;border-radius:999px;font-weight:800;font-size:0.88rem;border:2px solid #dbeafe;background:white;color:var(--primary);text-decoration:none;transition:all 0.2s; }
    .year-btn:hover { border-color:var(--primary); }
    .year-btn.active { background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;border-color:transparent; }

    /* Month navigator */
    .month-nav {
      display:flex;align-items:center;justify-content:center;gap:16px;
      background:white;border:1px solid #edf4ff;
      border-radius:22px;padding:18px 24px;margin-bottom:24px;
      box-shadow:var(--shadow);
      position:relative;overflow:hidden;
    }
    .month-nav::before {
      content:'';display:block;height:5px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      position:absolute;top:0;left:0;right:0;border-radius:22px 22px 0 0;
    }
    .arrow-btn {
      width:44px;height:44px;border-radius:50%;border:2px solid #dbeafe;
      background:white;color:var(--primary);font-size:1.1rem;
      display:flex;align-items:center;justify-content:center;
      cursor:pointer;transition:all 0.2s;flex-shrink:0;
    }
    .arrow-btn:hover:not(:disabled) { background:var(--primary);color:white;border-color:var(--primary); }
    .arrow-btn:disabled { opacity:0.3;cursor:default; }
    .month-label { font-size:1.4rem;font-weight:900;color:var(--primary);min-width:200px;text-align:center; }

    /* Stats */
    .stats-row { display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px; }
    .stat-box { background:white;border:1px solid #edf4ff;border-radius:22px;padding:20px;box-shadow:var(--shadow);text-align:center;position:relative;overflow:hidden; }
    .stat-box::before { content:'';display:block;height:5px;background:linear-gradient(135deg,var(--primary),var(--secondary));position:absolute;top:0;left:0;right:0;border-radius:22px 22px 0 0; }
    .stat-label { font-size:0.8rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:0.5px; }
    .stat-value { font-size:1.8rem;font-weight:900;color:var(--primary);margin-top:6px; }

    /* Earnings table card */
    .earn-card { background:white;border:1px solid #edf4ff;border-radius:22px;padding:22px;box-shadow:var(--shadow);position:relative;overflow:hidden; }
    .earn-card::before { content:'';display:block;height:5px;background:linear-gradient(135deg,var(--primary),var(--secondary));position:absolute;top:0;left:0;right:0;border-radius:22px 22px 0 0; }
    .earn-card-title { font-size:1.05rem;font-weight:900;color:var(--primary);margin-bottom:16px; }
    .type-table { width:100%;border-collapse:collapse; }
    .type-table th { background:#f8fbff;color:var(--muted);font-size:0.8rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;padding:10px 14px;border-bottom:1px solid #edf4ff;text-align:left; }
    .type-table td { padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:0.92rem;vertical-align:middle; }
    .type-table tr:last-child td { border-bottom:none; }
    .type-pill { display:inline-block;padding:5px 14px;border-radius:999px;font-size:0.8rem;font-weight:800; }
    .amount-val { font-weight:900;color:#065f46;font-size:1rem; }
    .sessions-val { color:var(--muted);font-weight:700; }
    .total-row td { font-weight:900;border-top:2px solid #edf4ff;background:#f8fbff; }

    .empty-month { text-align:center;padding:48px 20px;color:var(--muted); }
    .empty-month i { font-size:2.2rem;color:#bfdbfe;margin-bottom:12px;display:block; }
    .empty-month p { font-weight:700;margin:0; }

    @media (max-width:991px) { .sidebar{position:static;width:100%;height:auto;} .main{margin-left:0;} .stats-row{grid-template-columns:1fr 1fr;} }
    @media (max-width:576px) { .stats-row{grid-template-columns:1fr;} .month-label{font-size:1.1rem;min-width:150px;} }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">TEACHER PORTAL</p>
      </div>
    </div>
    <div class="teacher-box">
      <div class="teacher-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($teacherName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="teacher-name"><?= htmlspecialchars($teacherName) ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>
    <a href="teacher_dashboard.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
    <a href="teacher_classes.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>My Classes</span></a>
    <a href="teacher_schedule.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>My Schedule</span></a>
    <a href="teacher_monthly_earnings.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>My Earnings</span></a>
    <a href="teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-user-graduate"></i></span><span>My Students</span></a>
    <a href="teacher_courses.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
    <a href="teacher_profile.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
  </div>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</div>

<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="hero">
    <div>
      <h2><i class="fas fa-dollar-sign"></i> My Earnings</h2>
      <p>Navigate through each month to see your income</p>
    </div>
    <div class="hero-badge"><?= htmlspecialchars($teacherName) ?></div>
  </div>

  <!-- Year selector -->
  <?php if (count($years) > 1): ?>
  <div class="year-bar">
    <span style="font-weight:800;color:var(--muted);font-size:0.88rem">Year:</span>
    <?php foreach ($years as $yr): ?>
      <a href="?year=<?= $yr ?>" class="year-btn <?= $yr===$selectedYear?'active':'' ?>"><?= $yr ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Month navigator -->
  <div class="month-nav">
    <button class="arrow-btn" id="btn-prev" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
    <div class="month-label" id="month-label"></div>
    <button class="arrow-btn" id="btn-next" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-label">Total Earnings</div>
      <div class="stat-value" id="stat-total">$0.00</div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Sessions</div>
      <div class="stat-value" id="stat-sessions">0</div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Avg per Session</div>
      <div class="stat-value" id="stat-avg">$0.00</div>
    </div>
  </div>

  <!-- Earnings breakdown -->
  <div class="earn-card">
    <div class="earn-card-title" id="earn-title">Earnings Breakdown</div>
    <div id="earn-body"></div>
  </div>

</div>

<script>
const YEAR  = <?= $selectedYear ?>;
const NAMES = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

/* Data from PHP: index 1–12 */
const data = <?= json_encode(array_map(fn($m) => [
    'total'    => $m['total'],
    'sessions' => $m['sessions'],
    'types'    => $m['types']
], $byMonth)) ?>;

/* Type badge colours */
function badgeStyle(type) {
  const t = type.toLowerCase();
  if (t === 'paid')               return 'background:#dcfce7;color:#166534';
  if (t.includes('demo'))         return 'background:#fef3c7;color:#92400e';
  if (t === 'half pay')           return 'background:#ede9fe;color:#6d28d9';
  if (t === 'no pay')             return 'background:#fee2e2;color:#991b1b';
  return 'background:#e0e7ff;color:#3730a3';
}

function fmt(n) {
  return '$' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

let currentMonth = new Date().getMonth() + 1; // 1–12

function render() {
  const d = data[currentMonth];

  /* Label */
  document.getElementById('month-label').textContent = NAMES[currentMonth] + ' ' + YEAR;

  /* Arrows */
  document.getElementById('btn-prev').disabled = currentMonth === 1;
  document.getElementById('btn-next').disabled = currentMonth === 12;

  /* Stats */
  document.getElementById('stat-total').textContent    = fmt(d.total);
  document.getElementById('stat-sessions').textContent = d.sessions;
  document.getElementById('stat-avg').textContent      = d.sessions > 0 ? fmt(d.total / d.sessions) : '$0.00';

  /* Breakdown */
  const title = document.getElementById('earn-title');
  const body  = document.getElementById('earn-body');
  title.textContent = NAMES[currentMonth] + ' — Earnings by Class Type';

  const types = Object.entries(d.types);
  if (types.length === 0) {
    body.innerHTML = `<div class="empty-month"><i class="fas fa-wallet"></i><p>No earnings recorded for ${NAMES[currentMonth]} ${YEAR}</p></div>`;
    return;
  }

  let rows = types.map(([type, info]) => `
    <tr>
      <td><span class="type-pill" style="${badgeStyle(type)}">${type}</span></td>
      <td class="sessions-val">${info.count} session${info.count !== 1 ? 's' : ''}</td>
      <td class="amount-val">${fmt(info.amount)}</td>
    </tr>`).join('');

  rows += `<tr class="total-row">
    <td><strong>Total</strong></td>
    <td class="sessions-val"><strong>${d.sessions} session${d.sessions !== 1 ? 's' : ''}</strong></td>
    <td class="amount-val">${fmt(d.total)}</td>
  </tr>`;

  body.innerHTML = `
    <table class="type-table">
      <thead><tr><th>Class Type</th><th>Sessions</th><th>Amount</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
}

function changeMonth(dir) {
  const next = currentMonth + dir;
  if (next < 1 || next > 12) return;
  currentMonth = next;
  render();
}

render();
</script>
</body>
</html>
