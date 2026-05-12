<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"];
$userName = $_SESSION["username"] ?? "User";
$quizId   = (int)($_GET["id"] ?? 0);

if ($quizId <= 0) {
    echo "<p style='padding:40px;font-family:Arial;'>Invalid quiz link.</p>";
    exit();
}

// Load quiz
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if (!$quiz) {
    echo "<p style='padding:40px;font-family:Arial;'>Quiz not found.</p>";
    exit();
}

// Load questions
$qStmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
$qStmt->bind_param("i", $quizId);
$qStmt->execute();
$questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($questions)) {
    echo "<p style='padding:40px;font-family:Arial;'>This quiz has no questions.</p>";
    exit();
}

// Back link based on role
$backLink = $role === "admin" ? "admin_quiz_generator.php" : ($role === "teacher" ? "teacher_quizzes.php" : "student_quizzes.php");

// Check if student already completed this quiz
$conn->query("CREATE TABLE IF NOT EXISTS quiz_results (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id          INT          NOT NULL,
    student_username VARCHAR(255) NOT NULL,
    score            INT          NOT NULL DEFAULT 0,
    total            INT          NOT NULL DEFAULT 0,
    answers          TEXT         NOT NULL DEFAULT '{}',
    completed_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_result (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$alreadyDone = null;
if ($role === 'student') {
    $doneStmt = $conn->prepare("SELECT score, total, completed_at FROM quiz_results WHERE quiz_id = ? AND student_username = ?");
    if ($doneStmt) {
        $doneStmt->bind_param("is", $quizId, $userName);
        $doneStmt->execute();
        $doneRes = $doneStmt->get_result();
        if ($doneRes && $doneRes->num_rows > 0) {
            $alreadyDone = $doneRes->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($quiz["title"]) ?> | JuniorCode Quiz</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; }
* { box-sizing:border-box; }
body {
  margin:0; font-family:Arial,Helvetica,sans-serif; color:var(--dark);
  background: radial-gradient(circle at top left,rgba(29,78,216,0.07),transparent 25%),
              radial-gradient(circle at bottom right,rgba(14,165,233,0.07),transparent 25%),
              linear-gradient(180deg,#f8fbff 0%,#eaf4ff 100%);
  min-height:100vh;
}

.quiz-wrap { max-width:760px; margin:0 auto; padding:28px 18px 60px; }

.quiz-hero {
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:white; border-radius:22px; padding:22px 24px; margin-bottom:26px;
  box-shadow:0 12px 28px rgba(37,99,235,0.3);
}
.quiz-hero h1 { margin:0 0 6px; font-size:1.6rem; font-weight:900; }
.quiz-hero p  { margin:0; color:rgba(255,255,255,0.82); font-size:0.93rem; }
.quiz-meta-chips { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
.quiz-chip { background:rgba(255,255,255,0.18); border-radius:999px; padding:5px 14px; font-size:0.82rem; font-weight:700; }

.back-link { display:inline-flex; align-items:center; gap:7px; color:var(--primary); font-weight:700; text-decoration:none; font-size:0.9rem; margin-bottom:18px; }
.back-link:hover { color:var(--secondary); }

/* Progress bar */
.progress-row { display:flex; align-items:center; gap:12px; margin-bottom:22px; }
.progress-bar-wrap { flex:1; background:#e2e8f0; border-radius:999px; height:8px; overflow:hidden; }
.progress-bar-fill { height:100%; background:linear-gradient(90deg,var(--primary),var(--secondary)); border-radius:999px; transition:width 0.4s; }
.progress-label { font-size:0.85rem; font-weight:800; color:var(--muted); white-space:nowrap; }

/* Question card */
.q-card { background:white; border:1px solid #edf4ff; border-radius:22px; padding:24px; margin-bottom:18px; box-shadow:0 8px 24px rgba(37,99,235,0.07); display:none; }
.q-card.active { display:block; }
.q-num-label { font-size:0.78rem; font-weight:800; color:var(--muted); margin-bottom:8px; }
.q-text { font-size:1.08rem; font-weight:800; color:#0f172a; line-height:1.6; margin-bottom:20px; }

/* Choice buttons */
.choice-list { display:flex; flex-direction:column; gap:10px; }
.choice-btn {
  display:flex; align-items:center; gap:12px;
  padding:14px 18px; border-radius:14px;
  border:2px solid #e2e8f0; background:#f8fbff;
  cursor:pointer; text-align:left; font-size:0.93rem;
  font-weight:700; color:#0f172a; transition:all 0.18s;
  width:100%;
}
.choice-btn:hover:not(:disabled) { border-color:var(--primary); background:#eff6ff; }
.choice-btn.selected  { border-color:#3b82f6; background:#eff6ff; }
.choice-btn.correct   { border-color:#22c55e; background:#f0fdf4; color:#166534; }
.choice-btn.wrong     { border-color:#ef4444; background:#fef2f2; color:#991b1b; }
.choice-btn:disabled  { cursor:default; }
.choice-key { width:32px; height:32px; border-radius:8px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:0.9rem; flex-shrink:0; }
.choice-btn.correct .choice-key  { background:#22c55e; color:white; }
.choice-btn.wrong    .choice-key  { background:#ef4444; color:white; }
.choice-btn.selected .choice-key  { background:var(--primary); color:white; }

/* Explanation box */
.explain-box { margin-top:16px; background:#f0f7ff; border-left:4px solid #3b82f6; border-radius:12px; padding:13px 16px; font-size:0.88rem; color:#1e40af; display:none; }
.explain-box.visible { display:block; }

/* Timer */
.timer-box { display:flex; align-items:center; gap:7px; background:white; border:2px solid #dbeafe; border-radius:12px; padding:7px 16px; font-size:1rem; font-weight:900; color:var(--primary); white-space:nowrap; min-width:90px; justify-content:center; transition:all 0.3s; }
.timer-box.warn   { border-color:#f59e0b; color:#b45309; background:#fffbeb; }
.timer-box.danger { border-color:#ef4444; color:#dc2626; background:#fef2f2; animation:pulse-timer 0.55s infinite alternate; }
@keyframes pulse-timer { from { transform:scale(1); } to { transform:scale(1.07); } }

/* Navigation */
.nav-row { display:flex; justify-content:space-between; align-items:center; margin-top:20px; flex-wrap:wrap; gap:10px; }
.btn-nav { padding:12px 22px; border-radius:14px; font-weight:800; font-size:0.95rem; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
.btn-prev { background:#e2e8f0; color:#334155; }
.btn-prev:hover { background:#cbd5e1; }
.btn-next { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.btn-next:hover { opacity:0.9; }
.btn-next:disabled { opacity:0.5; cursor:not-allowed; }

/* Results screen */
.results-card { background:white; border:1px solid #edf4ff; border-radius:22px; padding:36px 24px; text-align:center; box-shadow:0 8px 24px rgba(37,99,235,0.07); display:none; }
.results-card.visible { display:block; }
.score-circle { width:120px; height:120px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; flex-direction:column; align-items:center; justify-content:center; margin:0 auto 20px; font-size:2rem; font-weight:900; }
.score-circle span { font-size:0.75rem; font-weight:700; opacity:0.85; }
.results-title { font-size:1.5rem; font-weight:900; color:#0f172a; margin-bottom:8px; }
.results-sub   { color:var(--muted); font-size:0.95rem; margin-bottom:24px; }
.result-row { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:14px; margin-bottom:8px; text-align:left; }
.result-row.result-correct { background:#f0fdf4; border:1px solid #bbf7d0; }
.result-row.result-wrong   { background:#fef2f2; border:1px solid #fecaca; }
.result-icon { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.85rem; }
.result-correct .result-icon { background:#22c55e; color:white; }
.result-wrong   .result-icon { background:#ef4444; color:white; }
.result-q-text { font-size:0.88rem; font-weight:700; color:#0f172a; flex:1; }
.result-ans { font-size:0.8rem; color:var(--muted); margin-top:2px; }

/* Already-done banner */
.done-card { background:white; border:1px solid #bbf7d0; border-radius:22px; padding:36px 24px; text-align:center; box-shadow:0 8px 24px rgba(37,99,235,0.07); }
.done-icon { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#22c55e,#16a34a); color:white; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:2rem; }
.done-title { font-size:1.4rem; font-weight:900; color:#166534; margin-bottom:8px; }
.done-sub { color:var(--muted); font-size:0.95rem; margin-bottom:22px; }
.done-score { display:inline-block; background:#f0fdf4; border:2px solid #bbf7d0; border-radius:14px; padding:12px 28px; font-size:1.5rem; font-weight:900; color:#16a34a; margin-bottom:24px; }
</style>
</head>
<body>
<div class="quiz-wrap">

  <a href="<?= htmlspecialchars($backLink) ?>" class="back-link">
    <i class="fas fa-arrow-left"></i> Back
  </a>

  <div class="quiz-hero">
    <h1><?= htmlspecialchars($quiz["title"]) ?></h1>
    <p>Topic: <?= htmlspecialchars($quiz["topic"]) ?></p>
    <div class="quiz-meta-chips">
      <span class="quiz-chip"><i class="fas fa-layer-group me-1"></i><?= htmlspecialchars(ucfirst($quiz["section"])) ?></span>
      <span class="quiz-chip"><i class="fas fa-signal me-1"></i><?= htmlspecialchars(ucfirst($quiz["difficulty"])) ?></span>
      <span class="quiz-chip"><i class="fas fa-circle-question me-1"></i><?= count($questions) ?> questions</span>
    </div>
  </div>

  <?php if ($alreadyDone): ?>
  <!-- Already completed -->
  <div class="done-card">
    <div class="done-icon"><i class="fas fa-circle-check"></i></div>
    <div class="done-title">Quiz Already Completed!</div>
    <div class="done-sub">You already submitted this quiz on <?= date("d M Y", strtotime($alreadyDone["completed_at"])) ?>.</div>
    <div class="done-score"><?= (int)$alreadyDone["score"] ?> / <?= (int)$alreadyDone["total"] ?> correct &nbsp;·&nbsp; <?= $alreadyDone["total"] > 0 ? round(($alreadyDone["score"] / $alreadyDone["total"]) * 100) : 0 ?>%</div>
    <br>
    <a href="<?= htmlspecialchars($backLink) ?>" class="btn-nav btn-next" style="text-decoration:none;">
      <i class="fas fa-arrow-left"></i> Back to Quizzes
    </a>
  </div>
  <?php else: ?>

  <!-- Progress -->
  <div class="progress-row" id="progress-row">
    <div class="progress-bar-wrap">
      <div class="progress-bar-fill" id="prog-fill" style="width:0%"></div>
    </div>
    <div class="progress-label" id="prog-label">0 / <?= count($questions) ?></div>
    <div class="timer-box" id="quiz-timer"><i class="fas fa-clock"></i> <span id="timer-text">—</span></div>
  </div>

  <!-- Question cards -->
  <?php foreach ($questions as $i => $q): ?>
  <div class="q-card <?= $i === 0 ? 'active' : '' ?>" id="q-<?= $i ?>">
    <div class="q-num-label">Question <?= $i + 1 ?> of <?= count($questions) ?></div>
    <div class="q-text"><?= htmlspecialchars($q["question"]) ?></div>
    <div class="choice-list">
      <?php foreach (['A'=>$q["choice_a"],'B'=>$q["choice_b"],'C'=>$q["choice_c"],'D'=>$q["choice_d"]] as $letter => $text): ?>
      <button class="choice-btn" onclick="selectChoice(<?= $i ?>, '<?= $letter ?>', this)"
              data-letter="<?= $letter ?>" data-correct="<?= $q["correct_answer"] ?>">
        <span class="choice-key"><?= $letter ?></span>
        <span><?= htmlspecialchars($text) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($q["explanation"])): ?>
    <div class="explain-box" id="explain-<?= $i ?>">
      <i class="fas fa-lightbulb me-1" style="color:#f59e0b;"></i>
      <?= htmlspecialchars($q["explanation"]) ?>
    </div>
    <?php endif; ?>
    <div class="nav-row">
      <?php if ($i > 0): ?>
      <button class="btn-nav btn-prev" onclick="goTo(<?= $i - 1 ?>)"><i class="fas fa-arrow-left"></i> Previous</button>
      <?php else: ?>
      <span></span>
      <?php endif; ?>
      <?php if ($i < count($questions) - 1): ?>
      <button class="btn-nav btn-next" id="next-<?= $i ?>" onclick="goTo(<?= $i + 1 ?>)" disabled>
        Next <i class="fas fa-arrow-right"></i>
      </button>
      <?php else: ?>
      <button class="btn-nav btn-next" id="next-<?= $i ?>" onclick="showResults()" disabled>
        See Results <i class="fas fa-flag-checkered"></i>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Results -->
  <div class="results-card" id="results-card">
    <div class="score-circle" id="score-circle">
      <div id="score-num">0</div>
      <span>/ <?= count($questions) ?></span>
    </div>
    <div class="results-title" id="results-title">Quiz Complete!</div>
    <div class="results-sub"  id="results-sub"></div>
    <div id="results-list" style="text-align:left;margin-bottom:24px;"></div>
    <button class="btn-nav btn-next" id="try-again-btn" onclick="restartQuiz()">
      <i class="fas fa-rotate-left"></i> Try Again
    </button>
    <a href="<?= htmlspecialchars($backLink) ?>" class="btn-nav btn-prev" style="margin-left:10px;text-decoration:none;">
      <i class="fas fa-house"></i> Back
    </a>
    <div id="save-status" style="margin-top:14px;font-size:0.88rem;font-weight:700;display:none;"></div>
  </div>

  <?php endif; ?>

</div>

<?php if (!$alreadyDone): ?>
<script>
const totalQ    = <?= count($questions) ?>;
const QUIZ_ID   = <?= $quizId ?>;
const IS_STUDENT = <?= $role === 'student' ? 'true' : 'false' ?>;
const answers   = {};
const correct   = {};

/* ── Timer ── */
const TOTAL_SECONDS = <?= (int)($quiz["time_limit"] ?? 0) ?>;
const HAS_TIMER = TOTAL_SECONDS > 0;
let timeLeft = TOTAL_SECONDS;
let timerInterval = null;
if (!HAS_TIMER) document.getElementById('quiz-timer').style.display = 'none';

function startTimer() {
  const el  = document.getElementById('quiz-timer');
  const txt = document.getElementById('timer-text');
  function tick() {
    const m = Math.floor(timeLeft / 60);
    const s = timeLeft % 60;
    txt.textContent = m + ':' + String(s).padStart(2, '0');
    el.classList.remove('warn', 'danger');
    if (timeLeft <= 10) el.classList.add('danger');
    else if (timeLeft <= 30) el.classList.add('warn');
    if (timeLeft <= 0) { clearInterval(timerInterval); timerInterval = null; showResults(); return; }
    timeLeft--;
  }
  tick();
  timerInterval = setInterval(tick, 1000);
}

function stopTimer() {
  if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}

// Populate correct answers from DOM
document.querySelectorAll('.choice-btn').forEach(btn => {
  const qIdx = parseInt(btn.closest('.q-card').id.replace('q-',''));
  correct[qIdx] = btn.dataset.correct;
});

function selectChoice(qIdx, letter, btn) {
  if (answers[qIdx]) return; // already answered

  answers[qIdx] = letter;
  const card  = document.getElementById('q-' + qIdx);
  const btns  = card.querySelectorAll('.choice-btn');

  btns.forEach(b => {
    b.disabled = true;
    if (b.dataset.letter === correct[qIdx]) b.classList.add('correct');
    else if (b.dataset.letter === letter)   b.classList.add('wrong');
  });

  const explain = document.getElementById('explain-' + qIdx);
  if (explain) explain.classList.add('visible');

  document.getElementById('next-' + qIdx).disabled = false;
  updateProgress();
}

function goTo(idx) {
  document.querySelectorAll('.q-card').forEach(c => c.classList.remove('active'));
  document.getElementById('q-' + idx).classList.add('active');
  updateProgress();
}

function updateProgress() {
  const answered = Object.keys(answers).length;
  const pct = Math.round((answered / totalQ) * 100);
  document.getElementById('prog-fill').style.width  = pct + '%';
  document.getElementById('prog-label').textContent = answered + ' / ' + totalQ;
}

function showResults() {
  stopTimer();
  document.querySelectorAll('.q-card').forEach(c => c.style.display = 'none');
  document.getElementById('progress-row').style.display = 'none';

  let score = 0;
  for (let i = 0; i < totalQ; i++) {
    if (answers[i] === correct[i]) score++;
  }

  document.getElementById('score-num').textContent = score;
  const pct = Math.round((score / totalQ) * 100);
  const title = pct === 100 ? 'Perfect Score! 🎉' : pct >= 70 ? 'Great Job! 🌟' : pct >= 50 ? 'Good Effort! 💪' : 'Keep Practicing! 📚';
  document.getElementById('results-title').textContent = title;
  document.getElementById('results-sub').textContent   = 'You got ' + score + ' out of ' + totalQ + ' correct (' + pct + '%)';

  const cards = document.querySelectorAll('.q-card');
  const list  = document.getElementById('results-list');
  list.innerHTML = '';
  cards.forEach((card, i) => {
    const isCorrect = answers[i] === correct[i];
    const qText = card.querySelector('.q-text').textContent.trim();
    const chosen = answers[i] ? card.querySelector('[data-letter="' + answers[i] + '"]').querySelector('span:last-child').textContent.trim() : 'Not answered';
    const correctText = card.querySelector('[data-letter="' + correct[i] + '"]').querySelector('span:last-child').textContent.trim();

    const row = document.createElement('div');
    row.className = 'result-row ' + (isCorrect ? 'result-correct' : 'result-wrong');
    row.innerHTML = `
      <div class="result-icon"><i class="fas fa-${isCorrect ? 'check' : 'xmark'}"></i></div>
      <div>
        <div class="result-q-text">${escHtml(qText)}</div>
        <div class="result-ans">${isCorrect ? '✓ Correct' : 'Your answer: ' + escHtml(chosen) + ' · Correct: ' + escHtml(correctText)}</div>
      </div>
    `;
    list.appendChild(row);
  });

  document.getElementById('results-card').classList.add('visible');

  if (IS_STUDENT) saveResult(score, totalQ);
}

async function saveResult(score, total) {
  const statusEl = document.getElementById('save-status');
  try {
    const res  = await fetch('save_quiz_result.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ quiz_id: QUIZ_ID, score, total, answers }),
    });
    const data = await res.json();
    if (data.success) {
      const tryAgainBtn = document.getElementById('try-again-btn');
      if (tryAgainBtn) tryAgainBtn.style.display = 'none';
      statusEl.textContent = 'Your result has been saved. Your teacher can now see your score.';
      statusEl.style.color = '#16a34a';
      statusEl.style.display = 'block';
    }
  } catch(e) {}
}

function restartQuiz() {
  Object.keys(answers).forEach(k => delete answers[k]);
  document.getElementById('results-card').classList.remove('visible');
  document.getElementById('progress-row').style.display = '';

  document.querySelectorAll('.q-card').forEach((card, i) => {
    card.style.display = '';
    card.classList.toggle('active', i === 0);
    card.querySelectorAll('.choice-btn').forEach(btn => {
      btn.disabled = false;
      btn.classList.remove('correct','wrong','selected');
    });
    const explain = card.querySelector('.explain-box');
    if (explain) explain.classList.remove('visible');
    const nextBtn = document.getElementById('next-' + i);
    if (nextBtn) nextBtn.disabled = true;
  });

  timeLeft = TOTAL_SECONDS;
  document.getElementById('quiz-timer').classList.remove('warn', 'danger');
  if (HAS_TIMER) startTimer();
  updateProgress();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

updateProgress();
if (HAS_TIMER) startTimer();
</script>
<?php endif; ?>
</body>
</html>
