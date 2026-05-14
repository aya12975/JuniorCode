<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

require_once "admin_prefs.php";
$whatsapp = getAdminSetting($conn, 'whatsapp', '');
$wa       = preg_replace('/\D/', '', $whatsapp);
$waUrl    = $wa ? "https://wa.me/$wa" : "#";
$waDisplay = $wa ? '+' . $wa : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact Admin | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:   #3e5077;
      --secondary: #143674;
      --dark:      #0f172a;
      --muted:     #64748b;
      --border:    #edf4ff;
      --wa-green:  #25D366;
      --wa-dark:   #128C7E;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(37,99,235,0.07), transparent 22%),
        radial-gradient(circle at bottom right, rgba(37,211,102,0.07), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
    }

    /* ── Sidebar ── */
    .sidebar {
      position: fixed; top: 0; left: 0;
      width: 255px; height: 100vh;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      display: flex; flex-direction: column; justify-content: space-between;
      z-index: 1000; overflow-y: auto; transition: transform 0.3s ease;
    }
    body.sidebar-collapsed .sidebar { transform: translateX(-255px); }

    .sidebar-top { padding: 20px 16px; }

    .brand {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 10px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 16px;
    }

    .brand-logo-img {
      width: 55px; height: 55px; object-fit: contain;
      border-radius: 0; background: none; padding: 0; flex-shrink: 0;
    }

    .brand-title    { font-size: 1.05rem; font-weight: 900; margin: 0; color: #fff; line-height: 1.2; }
    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }

    .student-box {
      display: flex; align-items: center; gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px; padding: 14px; margin-bottom: 18px;
    }

    .student-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; font-weight: bold;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0; overflow: hidden;
    }
    .student-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .student-name { font-weight: 800; margin: 0; color: #fff; }
    .student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none; color: rgba(255,255,255,0.78);
      padding: 12px 14px; border-radius: 14px; margin: 4px 0;
      font-weight: 700; transition: all 0.22s ease;
    }

    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }

    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }
    .sidebar-bottom { padding: 16px; }

    /* ── Main ── */
    .main { margin-left: 255px; padding: 28px; min-height: 100vh; transition: margin-left 0.3s ease; }
    body.sidebar-collapsed .main { margin-left: 0; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px; padding: 22px 26px; margin-bottom: 28px;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 14px; color: #fff;
      box-shadow: 0 12px 28px rgba(37,99,235,0.3);
    }

    .topbar h1 { font-size: 1.7rem; font-weight: 900; }
    .topbar p  { margin: 4px 0 0; opacity: 0.88; font-size: 0.97rem; }

    .topbar-date {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px; padding: 10px 18px;
      font-weight: 700; font-size: 0.9rem;
    }

    /* ── Layout ── */
    .contact-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      max-width: 900px;
      margin: 0 auto;
    }

    /* ── Hero card ── */
    .hero-card {
      background: linear-gradient(145deg, var(--wa-dark), var(--wa-green));
      border-radius: 26px; padding: 40px 32px;
      color: #fff; text-align: center;
      box-shadow: 0 20px 50px rgba(37,211,102,0.35);
      display: flex; flex-direction: column; align-items: center; gap: 20px;
      position: relative; overflow: hidden;
    }

    .hero-card::before {
      content: '';
      position: absolute; top: -40px; right: -40px;
      width: 160px; height: 160px; border-radius: 50%;
      background: rgba(255,255,255,0.08);
    }

    .hero-card::after {
      content: '';
      position: absolute; bottom: -30px; left: -30px;
      width: 110px; height: 110px; border-radius: 50%;
      background: rgba(255,255,255,0.06);
    }

    .wa-icon-wrap {
      width: 90px; height: 90px; border-radius: 28px;
      background: rgba(255,255,255,0.18);
      border: 2px solid rgba(255,255,255,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 2.6rem;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .hero-title { font-size: 1.5rem; font-weight: 900; line-height: 1.3; }
    .hero-sub   { font-size: 0.95rem; opacity: 0.88; line-height: 1.6; }

    .phone-pill {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 999px; padding: 10px 22px;
      font-size: 1.1rem; font-weight: 900; letter-spacing: 1px;
      display: inline-flex; align-items: center; gap: 8px;
    }

    .btn-wa-main {
      display: flex; align-items: center; justify-content: center; gap: 10px;
      background: #fff; color: var(--wa-dark); font-weight: 900; font-size: 1.05rem;
      border-radius: 16px; padding: 15px 28px; text-decoration: none;
      transition: all 0.22s ease; width: 100%;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }
    .btn-wa-main:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(0,0,0,0.2); color: var(--wa-dark); }
    .btn-wa-main i { font-size: 1.3rem; color: var(--wa-green); }

    /* ── Info card ── */
    .info-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 26px; padding: 32px;
      box-shadow: 0 18px 45px rgba(37,99,235,0.07);
      display: flex; flex-direction: column; gap: 18px;
      position: relative; overflow: hidden;
    }

    .info-card::before {
      content: ''; height: 5px;
      background: linear-gradient(135deg, var(--wa-dark), var(--wa-green));
      position: absolute; top: 0; left: 0; right: 0;
      border-radius: 26px 26px 0 0;
    }

    .info-section-title {
      font-size: 1.05rem; font-weight: 900; color: var(--dark);
      padding-bottom: 12px;
      border-bottom: 1px solid #edf4ff;
    }

    .topic-chip {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 16px; border-radius: 14px;
      background: #f8fafc; border: 1px solid #e2e8f0;
      cursor: pointer; transition: all 0.2s; text-decoration: none;
      color: var(--dark);
    }

    .topic-chip:hover {
      background: #f0fdf4; border-color: #86efac; color: var(--wa-dark);
      transform: translateX(4px);
    }

    .topic-chip-icon {
      width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
      background: #edf4ff;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.95rem; color: var(--primary);
      transition: background 0.2s;
    }

    .topic-chip:hover .topic-chip-icon { background: #dcfce7; color: var(--wa-dark); }

    .topic-chip-text { font-weight: 700; font-size: 0.92rem; }
    .topic-chip-sub  { font-size: 0.78rem; color: var(--muted); margin-top: 1px; }

    .topic-chip-arrow { margin-left: auto; color: #cbd5e1; font-size: 0.8rem; transition: color 0.2s; }
    .topic-chip:hover .topic-chip-arrow { color: var(--wa-green); }

    .hours-row {
      display: flex; align-items: center; gap: 10px;
      background: #fffbeb; border: 1px solid #fde68a;
      border-radius: 12px; padding: 12px 16px;
      font-size: 0.88rem; color: #92400e; font-weight: 700;
    }

    .no-contact {
      text-align: center; padding: 40px 20px;
      color: var(--muted); font-size: 0.95rem;
      background: #f8fafc; border-radius: 16px;
      border: 1px dashed #cbd5e1;
      grid-column: 1 / -1;
    }

    @media (max-width: 1100px) {
      .contact-layout { grid-template-columns: 1fr; max-width: 540px; }
    }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main { margin-left: 0; padding: 16px; }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">STUDENT PANEL</p>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <a href="student_dashboard.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
    </a>
    <a href="student_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
    </a>
    <a href="student_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
    </a>
    <a href="student_assignments.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span>
    </a>
    <a href="student_quizzes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
        <a href="student_certificates.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="student_chat.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
    </a>
    <a href="student_contact.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-comments"></i></span><span>Contact Admin</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</div>

<!-- ── MAIN ── -->
<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1><i class="fas fa-headset me-2"></i>Contact Admin</h1>
      <p>We're here to help — reach out anytime</p>
    </div>
    <div class="topbar-date"><?= date("l, d F Y") ?></div>
  </div>

  <div class="contact-layout">

    <?php if ($wa): ?>

    <!-- Hero WhatsApp card -->
    <div class="hero-card">
      <div class="wa-icon-wrap"><i class="fab fa-whatsapp"></i></div>
      <div>
        <div class="hero-title">Chat with us on WhatsApp</div>
        <div class="hero-sub" style="margin-top:8px;">
          Quick replies for all your questions about classes, schedules, and payments.
        </div>
      </div>
      <div class="phone-pill">
        <i class="fas fa-phone"></i>
        <?= htmlspecialchars($waDisplay) ?>
      </div>
      <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="btn-wa-main">
        <i class="fab fa-whatsapp"></i> Open WhatsApp Chat
      </a>
    </div>

    <!-- Quick topics card -->
    <div class="info-card">
      <div class="info-section-title"><i class="fas fa-bolt me-2" style="color:var(--wa-green);"></i>What can we help you with?</div>

      <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="topic-chip">
        <div class="topic-chip-icon"><i class="fas fa-calendar-days"></i></div>
        <div>
          <div class="topic-chip-text">Class Scheduling</div>
          <div class="topic-chip-sub">Reschedule, cancel, or add a new class</div>
        </div>
        <i class="fas fa-chevron-right topic-chip-arrow"></i>
      </a>

      <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="topic-chip">
        <div class="topic-chip-icon"><i class="fas fa-credit-card"></i></div>
        <div>
          <div class="topic-chip-text">Payments & Fees</div>
          <div class="topic-chip-sub">Questions about invoices or payments</div>
        </div>
        <i class="fas fa-chevron-right topic-chip-arrow"></i>
      </a>

      <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="topic-chip">
        <div class="topic-chip-icon"><i class="fas fa-video"></i></div>
        <div>
          <div class="topic-chip-text">Zoom Link Issues</div>
          <div class="topic-chip-sub">Can't join a class or link not working</div>
        </div>
        <i class="fas fa-chevron-right topic-chip-arrow"></i>
      </a>

      <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="topic-chip">
        <div class="topic-chip-icon"><i class="fas fa-circle-question"></i></div>
        <div>
          <div class="topic-chip-text">Other Questions</div>
          <div class="topic-chip-sub">Anything else we can help with</div>
        </div>
        <i class="fas fa-chevron-right topic-chip-arrow"></i>
      </a>

      <div class="hours-row">
        <i class="fas fa-clock"></i>
        Available Monday – Saturday, 8 AM – 8 PM
      </div>
    </div>

    <?php else: ?>
    <div class="no-contact">
      <i class="fas fa-circle-info" style="font-size:2rem;margin-bottom:12px;display:block;color:#94a3b8;"></i>
      <strong>No contact information set up yet.</strong><br>Please check back later.
    </div>
    <?php endif; ?>

  </div>
</div>
<script src="logout-modal.js"></script>
</body>
</html>

