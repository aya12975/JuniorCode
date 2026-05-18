<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* ── Ensure teacher_id column exists ── */
$colCheck = $conn->query("SHOW COLUMNS FROM classes LIKE 'teacher_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL");
}

/* ── Ensure session notes column exists ── */
$conn->query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS teacher_notes TEXT DEFAULT NULL");

/* ── Create class_feedback table ── */
$conn->query("CREATE TABLE IF NOT EXISTS class_feedback (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    class_id      INT NOT NULL,
    teacher_id    INT DEFAULT NULL,
    teacher_name  VARCHAR(255) DEFAULT NULL,
    student_name  VARCHAR(255) DEFAULT NULL,
    attendance    ENUM('present','absent') DEFAULT 'present',
    course_id     INT DEFAULT NULL,
    course_name   VARCHAR(500) DEFAULT NULL,
    project_id    INT DEFAULT NULL,
    project_name  VARCHAR(500) DEFAULT NULL,
    notes         TEXT DEFAULT NULL,
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

/* ── Courses & projects for feedback dropdown ── */
$coursesForFeedback = [];
$resC = $conn->query("SELECT id, course_name FROM courses WHERE status='active' AND section != 'demo' ORDER BY course_name ASC");
if ($resC) while ($r = $resC->fetch_assoc()) $coursesForFeedback[] = $r;

$projectsForFeedback = [];
$resP = $conn->query("SELECT id, title, course_id FROM course_projects ORDER BY title ASC");
if ($resP) while ($r = $resP->fetch_assoc()) $projectsForFeedback[] = $r;

/* ── Fetch yesterday / today / tomorrow classes for this teacher ── */
$classSessions = [];
$stmt = $conn->prepare("
    SELECT * FROM classes
    WHERE (teacher_id = ? OR LOWER(teacher_name) = LOWER(?))
      AND class_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                         AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ORDER BY class_date ASC, class_time ASC
");
if ($stmt) {
    $stmt->bind_param("is", $teacherId, $teacherName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classSessions[] = $row;
    }
    $stmt->close();
}

/* ── Fetch existing feedback keyed by class_id ── */
$existingFeedback = [];
if (!empty($classSessions)) {
    $ids = array_column($classSessions, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmtF = $conn->prepare("SELECT * FROM class_feedback WHERE class_id IN ($ph)");
    $stmtF->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($row = $resF->fetch_assoc()) $existingFeedback[$row['class_id']] = $row;
    $stmtF->close();
}

/* Session count per student for "Class Nb: X/8" */
$sessionCounts = [];
$scRes = $conn->query("
    SELECT cf.student_name,
           COUNT(cf.id) AS total,
           COALESCE(MAX(o.offset_count),0) AS offset_count
    FROM class_feedback cf
    LEFT JOIN student_session_offsets o ON o.student_name = cf.student_name
    WHERE cf.attendance = 'present'
    GROUP BY cf.student_name
");
if ($scRes) while ($r = $scRes->fetch_assoc()) {
    $used = max(0, (int)$r['total'] - (int)$r['offset_count']);
    $sessionCounts[$r['student_name']] = min(8, $used);
}

/* Last submitted feedback per student for "Last Session" info */
$prevFeedback = [];
$pfRes = $conn->query("
    SELECT f1.student_name, f1.course_name, f1.project_name, f1.notes
    FROM class_feedback f1
    INNER JOIN (SELECT student_name, MAX(id) AS max_id FROM class_feedback GROUP BY student_name) f2
           ON f1.student_name = f2.student_name AND f1.id = f2.max_id
");
if ($pfRes) while ($r = $pfRes->fetch_assoc()) $prevFeedback[$r['student_name']] = $r;

/* All past classes with no feedback (for Pending Feedback tab) */
$pendingFeedbackClasses = [];
$stmtPend = $conn->prepare("
    SELECT c.* FROM classes c
    LEFT JOIN class_feedback cf ON cf.class_id = c.id
    WHERE (c.teacher_id = ? OR LOWER(c.teacher_name) = LOWER(?))
      AND CONCAT(c.class_date, ' ', c.class_time) < NOW()
      AND cf.id IS NULL
    ORDER BY c.class_date DESC, c.class_time DESC
    LIMIT 30
");
if ($stmtPend) {
    $stmtPend->bind_param("is", $teacherId, $teacherName);
    $stmtPend->execute();
    $resPend = $stmtPend->get_result();
    while ($row = $resPend->fetch_assoc()) $pendingFeedbackClasses[] = $row;
    $stmtPend->close();
}

$today          = date("Y-m-d");
$yesterday      = date("Y-m-d", strtotime("-1 day"));
$tomorrow       = date("Y-m-d", strtotime("+1 day"));
$total          = count($classSessions);
$yesterdayCount = 0;
$todayCount     = 0;
$tomorrowCount  = 0;

foreach ($classSessions as $c) {
    $d = $c["class_date"] ?? "";
    if ($d === $yesterday)    $yesterdayCount++;
    elseif ($d === $today)   $todayCount++;
    elseif ($d === $tomorrow) $tomorrowCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Classes | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #3e5077;
      --primary-dark: #152c6b;
      --secondary:    #143674;
      --dark:         #0f172a;
      --muted:        #64748b;
      --border:       #edf4ff;
      --shadow:       0 18px 45px rgba(37,99,235,0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(29,78,216,0.07), transparent 25%),
        radial-gradient(circle at bottom right, rgba(14,165,233,0.07), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #eaf4ff 100%);
    }

    .app-shell { min-height: 100vh; display: flex; }

    /* ── Sidebar ── */
    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff;
      padding: 0;
      position: sticky; top: 0;
      height: 100vh;
      display: flex; flex-direction: column;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
      overflow-y: auto;
    }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; }

    .sidebar-top-area { padding: 0 18px 18px; }

    .brand {
      display: flex; align-items: center; gap: 12px;
      padding: 0 4px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 10px;
    }

    .brand-logo-img {
      width: 55px; height: 55px;
      object-fit: contain; flex-shrink: 0;
      background: none; border-radius: 0;
    }

    .brand-title { font-weight: 900; font-size: 1.1rem; color: #fff; line-height: 1.2; }

    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }

    .nav-title {
      font-size: 0.78rem; text-transform: uppercase;
      letter-spacing: 1.3px; color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px; font-weight: 700;
    }

    .nav-custom { display: flex; flex-direction: column; gap: 4px; }

    .teacher-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .teacher-avatar {
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
      overflow: hidden;
    }
    .teacher-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .teacher-name  { font-weight: 800; margin: 0; color: #ffffff; }
    .teacher-role  { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

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
      box-shadow: 0 8px 20px rgba(30,50,100,0.35);
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

    .nav-link-custom.active .nav-icon {
      background: rgba(255,255,255,0.18);
    }

    .sidebar-bottom { padding: 16px 18px; }

    /* ── Main ── */
    .main {
      flex: 1;
      padding: 28px;
    }

    .hamburger-btn {
      display: flex; flex-direction: column; gap: 5px; cursor: pointer;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      padding: 10px 12px; margin-bottom: 18px; width: fit-content;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: background 0.2s;
    }
    .hamburger-btn:hover { background: #f1f5f9; }
    .hamburger-line { width: 22px; height: 2.5px; background: #334155; border-radius: 2px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 20px;
      padding: 22px 26px;
      margin-bottom: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
      color: #fff;
    }

    .topbar h1 { font-size: 1.7rem; font-weight: 900; margin: 0; }
    .topbar p  { margin: 4px 0 0; opacity: 0.88; font-size: 0.97rem; }

    .topbar-date {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      padding: 10px 18px;
      font-weight: 700;
      font-size: 0.9rem;
    }

    /* ── Tabs ── */
    .tabs {
      margin-top: 18px;
      background: white;
      border: 1px solid #dfe3ec;
      border-radius: 8px;
      padding: 0 22px;
      height: 72px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .tab-list { display: flex; gap: 44px; height: 100%; align-items: center; }
    .tab {
      height: 100%; display: flex; align-items: center;
      position: relative; font-weight: 700; font-size: 15px;
      color: #20345e; cursor: default;
    }
    .tab.active::after {
      content: ""; position: absolute;
      left: 0; right: 0; bottom: 14px;
      height: 3px; background: #173b82; border-radius: 10px;
    }

    select.day-select {
      padding: 10px 32px 10px 14px;
      border: 1px solid #d5dae6; border-radius: 5px;
      background: white; color: #1f2f55;
      font-weight: 600; font-size: 14px; cursor: pointer;
      appearance: auto;
    }

    /* ── Card ── */
    .card {
      margin-top: 22px;
      background: white;
      border: 1px solid #e0e4ef;
      border-radius: 10px;
      box-shadow: 0 3px 9px rgba(0,0,0,.08);
      overflow: hidden;
    }

    .top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 16px 24px 12px;
      gap: 16px;
    }

    .student-block { display: flex; gap: 14px; align-items: flex-start; }

    .avatar {
      width: 58px; height: 58px; border-radius: 6px;
      background: var(--primary); color: white;
      font-weight: 900; font-size: 1.5rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; overflow: hidden;
    }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }

    .student-info h2 { margin: 0; font-size: 18px; color: #24375f; font-weight: 700; }
    .student-info p  { margin: 6px 0 6px; color: #344669; font-size: 13px; font-weight: 500; }

    .badge {
      display: inline-block; font-size: 11px;
      padding: 3px 10px; border-radius: 4px;
      margin-right: 5px; font-weight: 700;
    }
    .badge-section { background: #e7f1ff; color: #2f72bd; }
    .badge-paid    { background: #2d8b62; color: white; }
    .badge-demo    { background: #f59e0b; color: white; }
    .badge-halfpay { background: #7c3aed; color: white; }
    .badge-nopay   { background: #dc2626; color: white; }
    .badge-other   { background: #64748b; color: white; }

    .time-block { text-align: right; color: #1f2f55; flex-shrink: 0; }
    .time-block h2 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
    .time-block h3 { margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #344669; }

    .join-btn {
      background: #1b57a6; color: white; border: none;
      padding: 12px 18px; border-radius: 5px;
      font-size: 15px; font-weight: 700; cursor: pointer;
      text-decoration: none; display: inline-block;
      transition: background 0.2s;
    }
    .join-btn:hover { background: #154d96; color: white; }
    .join-locked {
      display: inline-flex; align-items: center; gap: 6px;
      background: #f1f5f9; color: #94a3b8; border-radius: 5px;
      padding: 11px 14px; font-weight: 600; font-size: 14px;
      border: 1px solid #e2e8f0;
    }
    .no-join {
      display: inline-flex; align-items: center; gap: 6px;
      background: #f8fafc; border: 1px dashed #cbd5e1; color: #94a3b8;
      border-radius: 5px; padding: 11px 14px; font-weight: 600; font-size: 14px;
    }

    /* ── Projects strip ── */
    .projects-strip {
      border-top: 1px solid #edf0f5;
      display: grid; grid-template-columns: 1fr 1fr;
      padding: 12px 24px 16px; gap: 36px;
    }
    .strip-label {
      font-size: 14px; color: #24375f; margin-bottom: 10px; font-weight: 500;
    }
    .strip-label span { font-weight: 700; }

    .action-btn {
      border: 1px solid #dfe5f0; background: #f8fafc; color: #52627e;
      padding: 8px 14px; border-radius: 4px; margin-right: 6px; margin-bottom: 6px;
      font-size: 13px; cursor: pointer; font-weight: 500;
      transition: background 0.15s;
    }
    .action-btn:hover { background: #edf2f8; }

    .btn-feedback {
      border: none; background: #1b57a6; color: white;
      border-radius: 5px; padding: 9px 16px; font-size: 13px;
      font-weight: 700; cursor: pointer; margin-right: 6px; margin-bottom: 6px;
      transition: background 0.2s;
    }
    .btn-feedback:hover { background: #154d96; }
    .btn-feedback-done {
      border: 2px solid #2d8b62; background: #f0fdf4; color: #2d8b62;
      border-radius: 5px; padding: 8px 14px; font-size: 13px;
      font-weight: 700; cursor: pointer; margin-right: 6px; margin-bottom: 6px;
      transition: background 0.2s;
    }
    .btn-feedback-done:hover { background: #dcfce7; }

    /* Notes */
    .notes-label {
      font-size: 12px; font-weight: 700; color: #64748b;
      margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .notes-textarea {
      width: 100%; border: 1.5px solid #e2e8f0; border-radius: 6px;
      padding: 9px 11px; font-size: 0.85rem; color: #334155;
      background: #fff; resize: none; outline: none;
      font-family: inherit; min-height: 62px; box-sizing: border-box;
      transition: border-color 0.2s;
    }
    .notes-textarea:focus { border-color: var(--primary); }
    .notes-textarea::placeholder { color: #94a3b8; }
    .notes-save-row { display: flex; justify-content: flex-end; margin-top: 5px; gap: 7px; align-items: center; }
    .notes-saved-msg { font-size: 0.75rem; color: #16a34a; font-weight: 700; display: none; }
    .notes-save-btn {
      border: none; background: var(--primary); color: #fff;
      border-radius: 4px; padding: 5px 14px; font-size: 0.8rem;
      font-weight: 700; cursor: pointer; transition: background 0.2s;
    }
    .notes-save-btn:hover { background: var(--primary-dark); }
    .notes-save-btn:disabled { background: #94a3b8; cursor: default; }

    /* ── Issues strip ── */
    .issues-strip {
      border-top: 1px solid #edf0f5;
      padding: 12px 24px 16px;
      display: none;
    }
    .card:hover .issues-strip { display: block; }
    .issues-strip p { margin: 0 0 12px; color: #334563; font-size: 14px; font-weight: 600; }
    .issue-btn {
      background: white; border: 2px solid #d35c91; color: #b94c80;
      border-radius: 5px; padding: 10px 16px; margin-right: 8px; margin-bottom: 8px;
      font-weight: 600; font-size: 13px; cursor: pointer; transition: background 0.15s;
    }
    .issue-btn:hover { background: #fdf0f6; }
    .issue-resolved { border-color: #8db6bd; color: #5f8d94; }
    .issue-resolved:hover { background: #f0f8fa; }

    /* ── Pending feedback inline form ── */
    .tab-count {
      display: inline-block; background: #ef4444; color: #fff;
      border-radius: 10px; padding: 1px 7px; font-size: 11px;
      margin-left: 6px; font-weight: 700; vertical-align: middle;
    }
    .pending-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c;
      border-radius: 8px; padding: 6px 12px; font-size: 13px;
      font-weight: 700; flex-shrink: 0;
    }
    .inline-fb {
      border-top: 2px solid #edf0f5;
      padding: 20px 24px 22px;
      background: #f9fafb;
    }
    .ifb-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
      margin-top: 16px;
    }
    .ifb-col { display: flex; flex-direction: column; }
    .ifb-label {
      font-weight: 800; color: #334155; font-size: 0.85rem;
      display: block; margin-bottom: 7px;
    }
    .ifb-select {
      width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px;
      padding: 10px 13px; font-size: 0.9rem; color: #334155;
      background: #fff; outline: none; font-family: inherit;
      box-sizing: border-box; transition: border-color 0.2s;
    }
    .ifb-select:focus { border-color: var(--primary); }
    .ifb-att-row { margin-top: 16px; }
    .ifb-btns { margin-top: 18px; display: flex; gap: 10px; align-items: center; }
    .ifb-saved-msg { font-size: 0.82rem; color: #16a34a; font-weight: 700; display: none; }

    /* ── Empty state ── */
    .empty-state {
      text-align: center; padding: 60px 20px; color: #64748b;
    }
    .empty-state .empty-icon { font-size: 3.5rem; margin-bottom: 14px; }
    .empty-state h5 { font-weight: 800; color: #334155; }
    .empty-state p  { font-size: 0.95rem; max-width: 340px; margin: 0 auto; }

    /* ── Feedback modal ── */
    .fb-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
      z-index: 9000; align-items: center; justify-content: center;
    }
    .fb-overlay.open { display: flex; }
    .fb-box {
      background: #fff; border-radius: 24px; padding: 30px;
      width: 100%; max-width: 500px;
      box-shadow: 0 32px 80px rgba(15,23,42,0.22);
      max-height: 90vh; overflow-y: auto;
    }
    .fb-title { font-size: 1.15rem; font-weight: 900; color: var(--primary); margin: 0 0 18px; }
    .fb-label { font-weight: 800; color: #334155; font-size: 0.88rem; display: block; margin-bottom: 6px; margin-top: 14px; }
    .fb-select, .fb-textarea {
      width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
      padding: 10px 13px; font-size: 0.93rem; color: #334155;
      background: #f8fafc; outline: none; font-family: inherit;
      box-sizing: border-box; transition: border-color 0.2s, background 0.2s;
    }
    .fb-select:focus, .fb-textarea:focus { border-color: var(--primary); background: #fff; }
    .fb-textarea { resize: vertical; min-height: 80px; }
    .attendance-toggle { display: flex; gap: 10px; margin-bottom: 2px; }
    .att-btn {
      flex: 1; padding: 10px; border-radius: 12px; border: 2px solid #e2e8f0;
      background: #f8fafc; font-weight: 800; font-size: 0.9rem; cursor: pointer;
      transition: all 0.18s; text-align: center;
    }
    .att-btn.present.selected { background: #dcfce7; border-color: #16a34a; color: #15803d; }
    .att-btn.absent.selected  { background: #fee2e2; border-color: #dc2626; color: #b91c1c; }
    .att-btn:not(.selected)   { color: #64748b; }
    .fb-btns { display: flex; gap: 10px; margin-top: 18px; }
    .fb-submit {
      flex: 1; padding: 12px; border-radius: 14px; border: none;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: 800; font-size: 0.95rem; cursor: pointer;
      transition: opacity 0.2s;
    }
    .fb-submit:hover { opacity: 0.9; }
    .fb-submit:disabled { background: #94a3b8; cursor: default; }
    .fb-cancel {
      padding: 12px 20px; border-radius: 14px; border: none;
      background: #f1f5f9; color: #334155; font-weight: 800; cursor: pointer;
    }
    .fb-cancel:hover { background: #e2e8f0; }

    /* Responsive */
    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .main { padding: 14px; }
      .projects-strip { grid-template-columns: 1fr; gap: 20px; }
      .top { flex-wrap: wrap; }
    }
    @media (max-width: 575px) {
      .tab-list { gap: 20px; }
      .tab { font-size: 13px; }
      .top { flex-direction: column; }
      .time-block { text-align: left; }
      .issues-strip { padding: 14px 18px 18px; }
      .projects-strip { padding: 14px 18px 18px; }
      .top { padding: 18px 18px 14px; }
    }
  </style>
</head>
<body>

<div class="app-shell">

<aside class="sidebar">
  <div class="sidebar-top-area">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <div class="brand-title">JuniorCode</div>
        <div class="brand-subtitle">TEACHER PORTAL</div>
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
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>

    <div class="nav-title">MAIN</div>
    <div class="nav-custom">
      <a href="teacher_dashboard.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-house"></i></span>
        <span>Dashboard</span>
      </a>
      <a href="teacher_classes.php" class="nav-link-custom active">
        <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span>
        <span>My Classes</span>
      </a>
      <a href="teacher_schedule.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
        <span>My Schedule</span>
      </a>
      <a href="teacher_monthly_earnings.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
        <span>My Earnings</span>
      </a>
      <a href="teacher_students.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
        <span>My Students</span>
      </a>
      <a href="teacher_assignments.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
        <span>Assignments</span>
      </a>
      <a href="teacher_courses.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
        <span>Courses</span>
      </a>
      <a href="teacher_quizzes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-circle-question"></i></span>
        <span>Quizzes</span>
      </a>
    </div>
  </div>

  <div class="sidebar-bottom">
    <a href="teacher_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span>
      <span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h1>My Classes</h1>
      <p>Your scheduled teaching sessions</p>
    </div>
    <div class="topbar-date">
      <?php echo date("l, d F Y"); ?>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab-list">
      <div class="tab active" onclick="switchTab(0)">My Classes</div>
      <div class="tab" onclick="switchTab(1)">Pending Feedback
        <?php if (count($pendingFeedbackClasses) > 0): ?>
          <span class="tab-count"><?= count($pendingFeedbackClasses) ?></span>
        <?php endif; ?>
      </div>
      <div class="tab" onclick="switchTab(2)">Evaluation</div>
    </div>
    <select class="day-select" id="dayFilter" onchange="filterClasses(this.value)">
      <option value="yesterday">Yesterday (<?= $yesterdayCount ?>)</option>
      <option value="today" selected>Today (<?= $todayCount ?>)</option>
      <option value="tomorrow">Tomorrow (<?= $tomorrowCount ?>)</option>
    </select>
  </div>

  <!-- ══ VIEW: MY CLASSES ══ -->
  <div id="view-classes">

  <?php if (empty($classSessions)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-book-open"></i></div>
      <h5>No classes yet</h5>
      <p>Once the admin assigns a class to you it will appear here with all the details and your Zoom link.</p>
    </div>

  <?php else: foreach ($classSessions as $c):
      $cDate    = $c["class_date"] ?? "";
      $cTime    = $c["class_time"] ?? "";
      $cType    = $c["type"] ?? "";
      $cDetails = $c["details"] ?? "";
      $cZoom    = $c["zoom_link"] ?? "";
      $cStudent = $c["student_name"] ?? "";

      if ($cDate === $yesterday)     $when = "yesterday";
      elseif ($cDate === $today)    $when = "today";
      else                          $when = "tomorrow";

      $t = strtolower(trim($cType));
      $typeBadgeClass = match(true) {
          $t === 'paid'            => 'badge-paid',
          str_contains($t,'demo') => 'badge-demo',
          $t === 'half pay'        => 'badge-halfpay',
          $t === 'no pay'          => 'badge-nopay',
          default                  => 'badge-other',
      };

      $classNb = $sessionCounts[$cStudent] ?? 0;
      $pf      = $prevFeedback[$cStudent]  ?? null;
      $fb      = $existingFeedback[$c['id']] ?? null;
      $lastSession = $pf ? implode(' · ', array_filter([$pf['course_name'], $pf['project_name']])) : '';
  ?>

    <div class="card" data-when="<?= $when ?>">

      <!-- Top row -->
      <div class="top">
        <div class="student-block">
          <div class="avatar">
            <?= strtoupper(substr($cStudent, 0, 1)) ?>
          </div>
          <div class="student-info">
            <h2><?= htmlspecialchars($cStudent) ?></h2>
            <p>Class Nb: <?= $classNb ?> / 8</p>
            <span class="badge badge-section">Junior</span>
            <span class="badge <?= $typeBadgeClass ?>"><?= htmlspecialchars($cType) ?></span>
            <?php if (!empty($cDetails)): ?>
              <span class="badge badge-other"><?= htmlspecialchars($cDetails) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="time-block">
          <h2><?= date("h:i A", strtotime($cTime)) ?></h2>
          <h3><?= date("M d, Y", strtotime($cDate)) ?></h3>
          <?php if (!empty($cZoom)): ?>
            <span class="zoom-slot"
              data-url="<?= htmlspecialchars($cZoom) ?>"
              data-date="<?= $cDate ?>"
              data-time="<?= $cTime ?>"></span>
          <?php else: ?>
            <span class="no-join"><i class="fas fa-video-slash"></i> No Zoom link</span>
          <?php endif; ?>
        </div>
      </div><!-- /.top -->

      <!-- Projects / Notes strip -->
      <div class="projects-strip">

        <!-- Left: last session + feedback -->
        <div>
          <div class="strip-label">
            Last Session:
            <span><?= $lastSession ? htmlspecialchars($lastSession) : 'No previous data' ?></span>
          </div>
          <?php if ($fb): ?>
            <button class="btn-feedback-done"
              onclick="openFeedback(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', true)">
              <i class="fas fa-circle-check"></i> Feedback Submitted &middot; Edit
            </button>
          <?php else: ?>
            <button class="btn-feedback"
              onclick="openFeedback(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', false)">
              <i class="fas fa-clipboard-list"></i> Submit Feedback
            </button>
          <?php endif; ?>
          <?php if ($fb && $fb['course_name']): ?>
            <div style="margin-top:8px;font-size:13px;color:#344669;">
              <strong>Feedback:</strong>
              <span style="color:<?= $fb['attendance'] === 'present' ? '#2d8b62' : '#dc2626' ?>; font-weight:700;">
                <?= $fb['attendance'] === 'present' ? 'Present' : 'Absent' ?>
              </span>
              &middot; <?= htmlspecialchars($fb['course_name']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right: notes -->
        <div class="notes-section">
          <div class="notes-label"><i class="fas fa-pen-to-square"></i> Session Notes</div>
          <textarea class="notes-textarea" data-class-id="<?= $c['id'] ?>"
            placeholder="Topics covered, homework, progress notes…"
          ><?= htmlspecialchars($c['teacher_notes'] ?? '') ?></textarea>
          <div class="notes-save-row">
            <span class="notes-saved-msg"><i class="fas fa-check"></i> Saved</span>
            <button class="notes-save-btn" onclick="saveNotes(this)">Save</button>
          </div>
        </div>

      </div><!-- /.projects-strip -->

      <!-- Issues strip -->
      <div class="issues-strip">
        <p>Report Issue:</p>
        <button class="issue-btn" onclick="reportIssue(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', 'Student Not Joined', 'absent')">
          &#9432; Student Not Joined
        </button>
        <button class="issue-btn" onclick="reportIssue(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', 'Student Left Early', 'absent')">
          &#9432; Student Left
        </button>
        <button class="issue-btn" onclick="reportIssue(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', 'Internet Issue', 'present')">
          &#9432; Internet Issue
        </button>
        <button class="issue-btn" onclick="reportIssue(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', 'Mic/Audio Issue', 'present')">
          &#9432; Mic/Audio Issue
        </button>
        <button class="issue-btn" onclick="reportIssue(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', 'Zoom Link Issue', 'present')">
          &#9432; Zoom Link Issue
        </button>
        <button class="issue-btn issue-resolved"
          onclick="openFeedback(<?= $c['id'] ?>, '<?= addslashes($cStudent) ?>', <?= $fb ? 'true' : 'false' ?>)">
          &#10003; Issue Resolved
        </button>
      </div><!-- /.issues-strip -->

    </div><!-- /.card -->

  <?php endforeach; endif; ?>

  </div><!-- /#view-classes -->

  <!-- ══ VIEW: PENDING FEEDBACK ══ -->
  <div id="view-pending" style="display:none;">

    <?php if (empty($pendingFeedbackClasses)): ?>
      <div class="empty-state" style="margin-top:32px;">
        <div class="empty-icon"><i class="fas fa-circle-check"></i></div>
        <h5>All caught up!</h5>
        <p>No pending feedback. All finished classes have been reviewed.</p>
      </div>

    <?php else: foreach ($pendingFeedbackClasses as $pc):
        $pcStudent = $pc["student_name"] ?? "";
        $pcDate    = $pc["class_date"]   ?? "";
        $pcTime    = $pc["class_time"]   ?? "";
        $pcType    = $pc["type"]         ?? "";
        $pt = strtolower(trim($pcType));
        $ptBadge = match(true) {
            $pt === 'paid'            => 'badge-paid',
            str_contains($pt,'demo') => 'badge-demo',
            $pt === 'half pay'        => 'badge-halfpay',
            $pt === 'no pay'          => 'badge-nopay',
            default                   => 'badge-other',
        };
    ?>

      <div class="card" style="margin-top:22px;">
        <!-- Student info row -->
        <div class="top">
          <div class="student-block">
            <div class="avatar"><?= strtoupper(substr($pcStudent, 0, 1)) ?></div>
            <div class="student-info">
              <h2><?= htmlspecialchars($pcStudent) ?></h2>
              <p><?= date("D, d M Y", strtotime($pcDate)) ?> &bull; <?= date("h:i A", strtotime($pcTime)) ?></p>
              <span class="badge <?= $ptBadge ?>"><?= htmlspecialchars($pcType) ?></span>
            </div>
          </div>
          <span class="pending-badge"><i class="fas fa-clock"></i> Awaiting Feedback</span>
        </div>

        <!-- Inline feedback form -->
        <div class="inline-fb">
          <input type="hidden" class="ifb-class-id" value="<?= $pc['id'] ?>">

          <!-- Attendance -->
          <div class="ifb-att-row" style="margin-top:0;">
            <div class="ifb-label">Attendance</div>
            <div class="attendance-toggle">
              <button type="button" class="att-btn present selected"
                onclick="toggleAttInline(this)">
                <i class="fas fa-user-check me-1"></i> Present
              </button>
              <button type="button" class="att-btn absent"
                onclick="toggleAttInline(this)">
                <i class="fas fa-user-xmark me-1"></i> Absent
              </button>
            </div>
          </div>

          <!-- Course + Project -->
          <div class="ifb-grid">
            <div class="ifb-col">
              <label class="ifb-label">Course</label>
              <select class="ifb-select ifb-course" onchange="filterIfbProjects(this)">
                <option value="">— Select course —</option>
                <?php foreach ($coursesForFeedback as $co): ?>
                  <option value="<?= $co['id'] ?>" data-name="<?= htmlspecialchars($co['course_name']) ?>">
                    <?= htmlspecialchars($co['course_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ifb-col">
              <label class="ifb-label">Project</label>
              <select class="ifb-select ifb-project">
                <option value="">— Select project —</option>
                <?php foreach ($projectsForFeedback as $pr): ?>
                  <option value="<?= $pr['id'] ?>"
                    data-course="<?= $pr['course_id'] ?>"
                    data-name="<?= htmlspecialchars($pr['title']) ?>">
                    <?= htmlspecialchars($pr['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="ifb-btns">
            <button class="btn-feedback" onclick="submitInlineFeedback(this)">
              <i class="fas fa-paper-plane me-1"></i> Submit Feedback
            </button>
            <span class="ifb-saved-msg"><i class="fas fa-check"></i> Saved!</span>
          </div>
          <div class="ifb-error" style="color:#ef4444;font-size:0.85rem;margin-top:6px;display:none;"></div>
        </div>
      </div><!-- /.card -->

    <?php endforeach; endif; ?>

  </div><!-- /#view-pending -->

  <!-- ══ VIEW: EVALUATION ══ -->
  <div id="view-eval" style="display:none;">
    <div class="empty-state" style="margin-top:32px;">
      <div class="empty-icon"><i class="fas fa-chart-line"></i></div>
      <h5>Evaluation</h5>
      <p>Student evaluation reports will appear here.</p>
    </div>
  </div><!-- /#view-eval -->

</div><!-- /.main -->
</div><!-- /.app-shell -->

<!-- ══ Feedback Modal ══ -->
<div class="fb-overlay" id="fbOverlay" onclick="if(event.target===this)closeFeedback()">
  <div class="fb-box">
    <div class="fb-title"><i class="fas fa-clipboard-list me-2"></i>Class Feedback</div>
    <input type="hidden" id="fb-class-id">

    <div style="background:#f0f4ff;border-radius:12px;padding:10px 14px;font-size:0.9rem;margin-bottom:4px;">
      <strong style="color:var(--primary);">Student:</strong> <span id="fb-student-name"></span>
    </div>

    <label class="fb-label">Attendance</label>
    <div class="attendance-toggle">
      <button type="button" class="att-btn present selected" id="att-present" onclick="selectAtt('present')">
        <i class="fas fa-user-check me-1"></i> Present
      </button>
      <button type="button" class="att-btn absent" id="att-absent" onclick="selectAtt('absent')">
        <i class="fas fa-user-xmark me-1"></i> Absent
      </button>
    </div>

    <label class="fb-label" for="fb-course">Course</label>
    <select id="fb-course" class="fb-select" onchange="filterProjects()">
      <option value="">— Select course —</option>
      <?php foreach ($coursesForFeedback as $co): ?>
        <option value="<?= $co['id'] ?>" data-name="<?= htmlspecialchars($co['course_name']) ?>">
          <?= htmlspecialchars($co['course_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="fb-label" for="fb-project">Project</label>
    <select id="fb-project" class="fb-select">
      <option value="">— Select project —</option>
      <?php foreach ($projectsForFeedback as $pr): ?>
        <option value="<?= $pr['id'] ?>" data-course="<?= $pr['course_id'] ?>" data-name="<?= htmlspecialchars($pr['title']) ?>">
          <?= htmlspecialchars($pr['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="fb-label" for="fb-notes">Notes</label>
    <textarea id="fb-notes" class="fb-textarea" placeholder="How did the session go? Progress, challenges, homework…"></textarea>

    <div id="fb-error" style="color:#ef4444;font-size:0.85rem;margin-top:8px;display:none;"></div>
    <div class="fb-btns">
      <button class="fb-submit" id="fb-submit-btn" onclick="submitFeedback()">
        <i class="fas fa-paper-plane me-1"></i> Submit Feedback
      </button>
      <button class="fb-cancel" onclick="closeFeedback()">Cancel</button>
    </div>
  </div>
</div>

<script>
/* Tab switching */
function switchTab(idx) {
  document.querySelectorAll('.tab').forEach(function(t, i) {
    t.classList.toggle('active', i === idx);
  });
  ['view-classes','view-pending','view-eval'].forEach(function(id, i) {
    var el = document.getElementById(id);
    if (el) el.style.display = i === idx ? '' : 'none';
  });
  var df = document.getElementById('dayFilter');
  if (df) df.style.display = idx === 0 ? '' : 'none';
}

/* Inline feedback helpers */
function toggleAttInline(btn) {
  var toggle = btn.closest('.attendance-toggle');
  toggle.querySelectorAll('.att-btn').forEach(function(b) { b.classList.remove('selected'); });
  btn.classList.add('selected');
}

function filterIfbProjects(courseSelect) {
  var card = courseSelect.closest('.inline-fb');
  var projSel = card.querySelector('.ifb-project');
  var courseId = courseSelect.value;
  Array.from(projSel.options).forEach(function(o) {
    if (!o.value) { o.style.display = ''; return; }
    o.style.display = (!courseId || o.dataset.course == courseId) ? '' : 'none';
  });
  projSel.value = '';
}

function submitInlineFeedback(btn) {
  var fb       = btn.closest('.inline-fb');
  var classId  = fb.querySelector('.ifb-class-id').value;
  var errEl    = fb.querySelector('.ifb-error');
  var savedMsg = fb.querySelector('.ifb-saved-msg');

  var presentBtn = fb.querySelector('.att-btn.present');
  var attendance = presentBtn.classList.contains('selected') ? 'present' : 'absent';

  var courseEl   = fb.querySelector('.ifb-course');
  var projectEl  = fb.querySelector('.ifb-project');
  var courseId   = courseEl.value;
  var courseName = courseEl.selectedIndex > 0 ? courseEl.options[courseEl.selectedIndex].dataset.name : '';
  var projectId  = projectEl.value;
  var projectName= projectEl.selectedIndex > 0 ? projectEl.options[projectEl.selectedIndex].dataset.name : '';

  errEl.style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

  var body = new FormData();
  body.append('class_id',    classId);
  body.append('attendance',  attendance);
  body.append('course_id',   courseId);
  body.append('course_name', courseName);
  body.append('project_id',  projectId);
  body.append('project_name',projectName);
  body.append('notes',       '');

  fetch('save_class_feedback.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Feedback';
      if (data.ok) {
        savedMsg.style.display = 'inline';
        btn.style.display = 'none';
        setTimeout(function() { location.href = '?tab=pending'; }, 900);
      } else {
        errEl.textContent = data.message || 'Error saving feedback.';
        errEl.style.display = 'block';
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Feedback';
    });
}

/* Zoom slot renderer — unlocks exactly at class time */
function renderZoomSlots() {
  document.querySelectorAll('.zoom-slot').forEach(function(slot) {
    if (slot.dataset.unlocked) return;
    var url = slot.dataset.url;
    if (!url) return;
    var classAt = new Date(slot.dataset.date + 'T' + slot.dataset.time);
    var now     = new Date();
    var diffSec = (classAt - now) / 1000;
    if (diffSec <= 0) {
      slot.dataset.unlocked = '1';
      var a = document.createElement('a');
      a.href = url; a.target = '_blank'; a.rel = 'noopener';
      a.className = 'join-btn';
      a.innerHTML = '<i class="fas fa-video"></i> Join Class &rsaquo;';
      slot.innerHTML = ''; slot.appendChild(a);
    } else {
      var h = classAt.getHours().toString().padStart(2,'0');
      var m = classAt.getMinutes().toString().padStart(2,'0');
      slot.innerHTML = '<span class="join-locked"><i class="fas fa-lock"></i> Starts at ' + h + ':' + m + '</span>';
    }
  });
}
renderZoomSlots();
setInterval(renderZoomSlots, 1000);

/* Day filter */
function filterClasses(filter) {
  var cards   = document.querySelectorAll('.card[data-when]');
  var visible = 0;
  cards.forEach(function(card) {
    var show = card.dataset.when === filter;
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  var empty = document.getElementById('emptyFiltered');
  if (visible === 0) {
    if (!empty) {
      empty = document.createElement('div');
      empty.id = 'emptyFiltered';
      empty.className = 'empty-state';
      empty.innerHTML = '<div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div><h5>No classes for this day</h5><p>No classes scheduled for this period.</p>';
      document.querySelector('.main').appendChild(empty);
    }
    empty.style.display = '';
  } else if (empty) {
    empty.style.display = 'none';
  }
}

/* Run filter on load */
filterClasses('today');

/* Restore tab from URL param (e.g. after inline feedback reload) */
(function() {
  var tab = new URLSearchParams(location.search).get('tab');
  if (tab === 'pending') switchTab(1);
  else if (tab === 'eval') switchTab(2);
})();

/* Save notes */
function saveNotes(btn) {
  var section  = btn.closest('.notes-section');
  var textarea = section.querySelector('.notes-textarea');
  var savedMsg = section.querySelector('.notes-saved-msg');
  var classId  = textarea.dataset.classId;

  btn.disabled = true;
  btn.textContent = 'Saving…';

  var body = new FormData();
  body.append('class_id', classId);
  body.append('notes', textarea.value);

  fetch('save_class_notes.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.textContent = 'Save';
      btn.disabled = false;
      if (data.ok) {
        savedMsg.style.display = 'inline';
        setTimeout(function() { savedMsg.style.display = 'none'; }, 2500);
      }
    })
    .catch(function() {
      btn.textContent = 'Save';
      btn.disabled = false;
    });
}

/* Feedback modal */
const allProjects = <?= json_encode($projectsForFeedback) ?>;
const existingFb  = <?= json_encode($existingFeedback) ?>;
let currentAtt = 'present';

function selectAtt(val) {
  currentAtt = val;
  document.getElementById('att-present').classList.toggle('selected', val === 'present');
  document.getElementById('att-absent').classList.toggle('selected',  val === 'absent');
}

function filterProjects() {
  const courseId = document.getElementById('fb-course').value;
  const sel = document.getElementById('fb-project');
  const cur = sel.value;
  Array.from(sel.options).forEach(o => {
    if (o.value === '') { o.style.display = ''; return; }
    o.style.display = (!courseId || o.dataset.course == courseId) ? '' : 'none';
  });
  if (cur && sel.querySelector(`option[value="${cur}"]`)?.style.display === 'none') sel.value = '';
}

function openFeedback(classId, studentName, isEdit) {
  document.getElementById('fb-class-id').value          = classId;
  document.getElementById('fb-student-name').textContent = studentName;
  document.getElementById('fb-notes').value              = '';
  document.getElementById('fb-course').value             = '';
  document.getElementById('fb-project').value            = '';
  document.getElementById('fb-error').style.display      = 'none';
  selectAtt('present');

  const fb = existingFb[classId];
  if (fb) {
    selectAtt(fb.attendance || 'present');
    if (fb.course_id)  document.getElementById('fb-course').value  = fb.course_id;
    filterProjects();
    if (fb.project_id) document.getElementById('fb-project').value = fb.project_id;
    document.getElementById('fb-notes').value = fb.notes || '';
  } else {
    filterProjects();
  }

  document.getElementById('fbOverlay').classList.add('open');
}

function closeFeedback() {
  document.getElementById('fbOverlay').classList.remove('open');
}

function reportIssue(classId, studentName, issueText, attendance) {
  openFeedback(classId, studentName, false);
  document.getElementById('fb-notes').value = issueText;
  selectAtt(attendance);
}

function submitFeedback() {
  const classId   = document.getElementById('fb-class-id').value;
  const courseEl  = document.getElementById('fb-course');
  const projectEl = document.getElementById('fb-project');
  const notes     = document.getElementById('fb-notes').value.trim();
  const errEl     = document.getElementById('fb-error');

  errEl.style.display = 'none';

  const courseId   = courseEl.value;
  const courseName = courseEl.options[courseEl.selectedIndex]?.dataset.name || '';
  const projectId  = projectEl.value;
  const projectName= projectEl.options[projectEl.selectedIndex]?.dataset.name || '';

  const btn = document.getElementById('fb-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

  const body = new FormData();
  body.append('class_id',    classId);
  body.append('attendance',  currentAtt);
  body.append('course_id',   courseId);
  body.append('course_name', courseName);
  body.append('project_id',  projectId);
  body.append('project_name',projectName);
  body.append('notes',       notes);

  fetch('save_class_feedback.php', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Feedback';
      if (data.ok) {
        closeFeedback();
        location.reload();
      } else {
        errEl.textContent = data.message || 'Error saving feedback.';
        errEl.style.display = 'block';
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Feedback';
      errEl.textContent = 'Request failed. Please try again.';
      errEl.style.display = 'block';
    });
}
</script>

<script src="logout-modal.js"></script>
</body>
</html>
