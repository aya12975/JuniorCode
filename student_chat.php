<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";
$today       = date("Y-m-d");

/* ── Load settings from DB ── */
$apiKey     = getAdminSetting($conn, "claude_api_key",    "");
$dailyLimit = (int)getAdminSetting($conn, "chat_daily_limit", "30");
$apiReady   = $apiKey !== "";

/* ── Daily usage so far ── */
$used = 0;
$usageCheck = $conn->query("SHOW TABLES LIKE 'ai_chat_usage'");
if ($usageCheck && $usageCheck->num_rows > 0) {
    $us = $conn->prepare("SELECT message_count FROM ai_chat_usage WHERE student_name = ? AND usage_date = ?");
    if ($us) {
        $us->bind_param("ss", $studentName, $today);
        $us->execute();
        $ur = $us->get_result()->fetch_assoc();
        $used = (int)($ur["message_count"] ?? 0);
    }
}
$remaining = max(0, $dailyLimit - $used);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Tutor | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary:   #3e5077;
  --secondary: #143674;
  --dark:      #0f172a;
  --muted:     #64748b;
  --border:    #edf4ff;
  --shadow:    0 18px 45px rgba(37,99,235,0.08);
}
* { box-sizing: border-box; }
body {
  margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--dark);
  background:
    radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
}

/* ── Sidebar ── */
.sidebar {
  position: fixed; top: 0; left: 0; width: 255px; height: 100vh;
  background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
  display: flex; flex-direction: column; justify-content: space-between;
  z-index: 1000; overflow-y: auto; transition: transform 0.3s ease;
}
body.sidebar-collapsed .sidebar { transform: translateX(-255px); }
.sidebar-top { padding: 20px 16px; }
.brand { display: flex; align-items: center; gap: 12px; padding: 10px 10px 18px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 16px; }
.brand-logo-img { width: 55px; height: 55px; object-fit: contain; flex-shrink: 0; }
.brand-title    { font-size: 1.05rem; font-weight: 900; margin: 0; color: #fff; line-height: 1.2; }
.brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }
.student-box { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 14px; margin-bottom: 18px; }
.student-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; font-weight: bold; font-size: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.student-name { font-weight: 800; margin: 0; color: #fff; }
.student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }
.nav-link-custom { display: flex; align-items: center; gap: 12px; text-decoration: none; color: rgba(255,255,255,0.78); padding: 12px 14px; border-radius: 14px; margin: 4px 0; font-weight: 700; transition: all 0.22s ease; }
.nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }
.nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; box-shadow: 0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width: 32px; height: 32px; border-radius: 10px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }
.sidebar-bottom { padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); }

/* ── Main ── */
.main { margin-left: 255px; padding: 24px; height: 100vh; display: flex; flex-direction: column; transition: margin-left 0.3s ease; }
body.sidebar-collapsed .main { margin-left: 0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:16px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); flex-shrink:0; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Chat shell ── */
.chat-shell {
  flex: 1; display: flex; flex-direction: column; min-height: 0;
  background: #fff; border-radius: 24px; border: 1px solid var(--border);
  box-shadow: var(--shadow); overflow: hidden;
}

/* ── Chat header ── */
.chat-header {
  padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  flex-shrink: 0;
}
.chat-header-left { display: flex; align-items: center; gap: 14px; }
.codi-avatar {
  width: 48px; height: 48px; border-radius: 14px;
  background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.35);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; color: #fff; flex-shrink: 0;
}
.codi-name { font-weight: 900; font-size: 1.05rem; color: #fff; margin: 0; line-height: 1.2; }
.codi-sub  { font-size: 0.78rem; color: rgba(255,255,255,0.75); margin: 2px 0 0; }
.usage-pill {
  background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25);
  color: #fff; border-radius: 999px; padding: 5px 13px; font-size: 0.8rem; font-weight: 800;
}

/* ── Messages area ── */
.chat-messages {
  flex: 1; overflow-y: auto; padding: 20px 22px;
  display: flex; flex-direction: column; gap: 16px;
  scroll-behavior: smooth;
}
.chat-messages::-webkit-scrollbar { width: 5px; }
.chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

/* ── Bubbles ── */
.msg-row { display: flex; align-items: flex-end; gap: 10px; }
.msg-row.user { flex-direction: row-reverse; }
.msg-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
}
.msg-avatar.ai   { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; }
.msg-avatar.user { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-weight: 800; font-size: 0.8rem; }
.bubble {
  max-width: 72%; padding: 12px 16px; border-radius: 18px;
  font-size: 0.91rem; line-height: 1.6;
}
.bubble.ai {
  background: #f8fbff; border: 1px solid #e2eaf8;
  border-bottom-left-radius: 4px; color: var(--dark);
}
.bubble.user {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff; border-bottom-right-radius: 4px;
}
.bubble pre {
  background: #1e293b; color: #e2e8f0; border-radius: 10px;
  padding: 12px 14px; font-size: 0.82rem; overflow-x: auto;
  margin: 8px 0 4px; white-space: pre-wrap; word-break: break-word;
}
.bubble code { background: rgba(99,102,241,0.12); color: #4f46e5; border-radius: 5px; padding: 1px 5px; font-size: 0.85em; }
.bubble pre code { background: none; color: inherit; padding: 0; }
.bubble strong { font-weight: 800; }
.msg-time { font-size: 0.7rem; color: var(--muted); margin-top: 3px; padding: 0 4px; }
.msg-row.user .msg-time { text-align: right; }

/* ── Typing indicator ── */
.typing-indicator { display: none; }
.typing-indicator.show { display: flex; }
.typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; animation: bounce 1.2s infinite; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%,80%,100% { transform: scale(0.7); opacity:0.5; } 40% { transform: scale(1); opacity:1; } }
.typing-bubble { background: #f8fbff; border: 1px solid #e2eaf8; border-bottom-left-radius: 4px; border-radius: 18px; padding: 12px 16px; display: flex; gap: 5px; align-items: center; }

/* ── Suggestions ── */
.suggestions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.suggestion-chip {
  background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
  border-radius: 999px; padding: 6px 14px; font-size: 0.82rem; font-weight: 700;
  cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.suggestion-chip:hover { background: #dbeafe; border-color: #93c5fd; }

/* ── Input area ── */
.chat-input-area {
  padding: 14px 20px; border-top: 1px solid #f1f5f9;
  display: flex; align-items: flex-end; gap: 10px; flex-shrink: 0;
  background: #fff;
}
#chatInput {
  flex: 1; border: 1.5px solid #dbe4f0; border-radius: 14px;
  padding: 11px 14px; font-size: 0.93rem; font-family: inherit;
  resize: none; outline: none; max-height: 120px; overflow-y: auto;
  line-height: 1.5; transition: border-color 0.2s;
}
#chatInput:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(62,80,119,0.1); }
#chatInput:disabled { background: #f8fafc; color: #94a3b8; }
.btn-send {
  width: 44px; height: 44px; border-radius: 12px; border: none; flex-shrink: 0;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff; font-size: 1rem; cursor: pointer; display: flex;
  align-items: center; justify-content: center; transition: opacity 0.2s;
}
.btn-send:hover { opacity: 0.88; }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Limit warning ── */
.limit-banner { background: #fef9c3; border: 1px solid #fde047; border-radius: 10px; padding: 10px 14px; font-size: 0.83rem; font-weight: 700; color: #92400e; margin: 0 22px 12px; text-align: center; }
.limit-banner.danger { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

@media (max-width: 991px) {
  .sidebar { position: static; width: 100%; height: auto; }
  .main    { margin-left: 0; height: auto; min-height: 100vh; }
  .bubble  { max-width: 88%; }
}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
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
    <a href="student_dashboard.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
    <a href="student_classes.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span></a>
    <a href="student_assignments.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span></a>
    <a href="student_chat.php"         class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
    <a href="student_contact.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-comments"></i></span><span>Contact Admin</span></a>
    <a href="student_profile.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
  </div>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
  </div>

  <div class="chat-shell">

    <!-- Header -->
    <div class="chat-header">
      <div class="chat-header-left">
        <div class="codi-avatar"><i class="fas fa-robot"></i></div>
        <div>
          <p class="codi-name">Codi — AI Coding Tutor</p>
          <p class="codi-sub">Powered by Gemini · Always here to help</p>
        </div>
      </div>
      <div class="usage-pill" id="usagePill">
        <i class="fas fa-bolt me-1"></i><?= $remaining ?> / <?= $dailyLimit ?> left today
      </div>
    </div>

    <?php if ($remaining <= 5 && $remaining > 0): ?>
      <div class="limit-banner"><i class="fas fa-triangle-exclamation me-1"></i> Only <?= $remaining ?> message<?= $remaining !== 1 ? 's' : '' ?> left today.</div>
    <?php elseif ($remaining === 0): ?>
      <div class="limit-banner danger"><i class="fas fa-ban me-1"></i> You've used all your messages for today. Come back tomorrow!</div>
    <?php endif; ?>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">

      <!-- Codi greeting -->
      <div class="msg-row ai">
        <div class="msg-avatar ai"><i class="fas fa-robot"></i></div>
        <div>
          <div class="bubble ai">
            <strong><i class="fas fa-hand me-1"></i> Hi <?= htmlspecialchars($studentName) ?>! I'm Codi, your AI coding tutor!</strong><br><br>
            I can help you with Python, Scratch, game development, HTML/CSS, and any other programming questions you have.<br><br>
            What would you like to learn today? You can ask me anything — no question is too simple! <i class="fas fa-rocket"></i>
            <div class="suggestions" id="suggestions">
              <span class="suggestion-chip" onclick="sendSuggestion(this)">How do Python loops work?</span>
              <span class="suggestion-chip" onclick="sendSuggestion(this)">What is a variable?</span>
              <span class="suggestion-chip" onclick="sendSuggestion(this)">How do I make a simple game?</span>
              <span class="suggestion-chip" onclick="sendSuggestion(this)">Explain functions to me</span>
              <span class="suggestion-chip" onclick="sendSuggestion(this)">What is an if statement?</span>
              <span class="suggestion-chip" onclick="sendSuggestion(this)">Help me understand lists</span>
            </div>
          </div>
          <div class="msg-time"><?= date("h:i A") ?></div>
        </div>
      </div>

      <?php if (!$apiReady): ?>
      <div class="msg-row ai">
        <div class="msg-avatar ai"><i class="fas fa-robot"></i></div>
        <div>
          <div class="bubble ai" style="background:#fff7ed;border-color:#fed7aa;">
            <i class="fas fa-gear me-1"></i> <strong>Setup needed:</strong> The AI tutor needs an API key to work.<br>
            Ask the admin to open <strong>AI Tutor Settings</strong> and add the Gemini API key.
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Typing indicator (hidden until needed) -->
      <div class="msg-row ai typing-indicator" id="typingIndicator">
        <div class="msg-avatar ai"><i class="fas fa-robot"></i></div>
        <div class="typing-bubble">
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
        </div>
      </div>

    </div><!-- /chat-messages -->

    <!-- Input -->
    <div class="chat-input-area">
      <textarea id="chatInput" rows="1"
        placeholder="<?= $remaining > 0 ? 'Ask Codi anything about coding...' : 'No messages left for today.' ?>"
        <?= $remaining === 0 || !$apiReady ? 'disabled' : '' ?>></textarea>
      <button class="btn-send" id="sendBtn" onclick="sendMessage()"
        <?= $remaining === 0 || !$apiReady ? 'disabled' : '' ?>>
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>

  </div><!-- /chat-shell -->
</div><!-- /main -->

<script>
const conversationHistory = [];
let remaining = <?= $remaining ?>;
const dailyLimit = <?= $dailyLimit ?>;

/* ── Auto-grow textarea ── */
const input = document.getElementById('chatInput');
input.addEventListener('input', () => {
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 120) + 'px';
});
input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

/* ── Suggestion chips ── */
function sendSuggestion(el) {
  input.value = el.textContent;
  document.getElementById('suggestions').remove();
  sendMessage();
}

/* ── Send a message ── */
async function sendMessage() {
  const text = input.value.trim();
  if (!text || remaining <= 0) return;

  appendMessage('user', text);
  conversationHistory.push({ role: 'user', content: text });
  input.value = '';
  input.style.height = 'auto';

  showTyping(true);
  setBusy(true);

  try {
    const res = await fetch('ai_chat_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, history: conversationHistory.slice(0, -1) }),
    });
    const data = await res.json();

    showTyping(false);

    if (data.error) {
      appendMessage('ai', '<i class="fas fa-triangle-exclamation me-1"></i> ' + (data.message || data.error), true);
      if (data.error === 'daily_limit') { disableInput(); }
    } else {
      appendMessage('ai', data.reply);
      conversationHistory.push({ role: 'assistant', content: data.reply });
      remaining = data.remaining;
      updateUsagePill();
      if (remaining <= 0) disableInput();
    }
  } catch (err) {
    showTyping(false);
    appendMessage('ai', '<i class="fas fa-triangle-exclamation me-1"></i> Connection error. Please check your internet and try again.', true);
  }

  setBusy(false);
}

/* ── Append bubble ── */
function appendMessage(role, text, isError = false) {
  const messages = document.getElementById('chatMessages');
  const indicator = document.getElementById('typingIndicator');

  const row = document.createElement('div');
  row.className = 'msg-row ' + role;

  const avatar = document.createElement('div');
  avatar.className = 'msg-avatar ' + role;
  avatar.innerHTML = role === 'ai'
    ? '<i class="fas fa-robot"></i>'
    : '<?= strtoupper(substr($studentName, 0, 1)) ?>';

  const wrap = document.createElement('div');

  const bubble = document.createElement('div');
  bubble.className = 'bubble ' + role;
  if (isError) bubble.style.cssText = 'background:#fff7ed;border-color:#fed7aa;color:#92400e;';
  bubble.innerHTML = (role === 'ai' || isError) ? renderMarkdown(text) : escapeHtml(text);

  const time = document.createElement('div');
  time.className = 'msg-time';
  time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  wrap.appendChild(bubble);
  wrap.appendChild(time);
  row.appendChild(avatar);
  row.appendChild(wrap);

  messages.insertBefore(row, indicator);
  messages.scrollTop = messages.scrollHeight;
}

/* ── Markdown renderer ── */
function renderMarkdown(text) {
  // Extract code blocks
  const blocks = [];
  text = text.replace(/```[\w]*\n?([\s\S]*?)```/g, (_, code) => {
    blocks.push(escapeHtml(code).trimEnd());
    return '\x00BLOCK' + (blocks.length - 1) + '\x00';
  });
  // Escape remaining HTML
  text = escapeHtml(text);
  // Inline code
  text = text.replace(/`([^`\x00]+?)`/g, (_, c) => '<code>' + escapeHtml(c) + '</code>');
  // Bold / italic
  text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  text = text.replace(/\*(.+?)\*/g,     '<em>$1</em>');
  // Restore code blocks
  text = text.replace(/\x00BLOCK(\d+)\x00/g, (_, i) => '<pre><code>' + blocks[i] + '</code></pre>');
  // Line breaks
  text = text.replace(/\n/g, '<br>');
  return text;
}

function escapeHtml(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── UI helpers ── */
function showTyping(show) {
  document.getElementById('typingIndicator').classList.toggle('show', show);
  document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
}
function setBusy(busy) {
  document.getElementById('sendBtn').disabled  = busy || remaining <= 0;
  document.getElementById('chatInput').disabled = busy || remaining <= 0;
}
function disableInput() {
  document.getElementById('chatInput').disabled = true;
  document.getElementById('sendBtn').disabled   = true;
  document.getElementById('chatInput').placeholder = 'No messages left for today. Come back tomorrow!';
}
function updateUsagePill() {
  document.getElementById('usagePill').innerHTML =
    '<i class="fas fa-bolt me-1"></i>' + remaining + ' / ' + dailyLimit + ' left today';
  if (remaining <= 5) {
    const banner = document.createElement('div');
    banner.className = 'limit-banner' + (remaining === 0 ? ' danger' : '');
    banner.innerHTML = remaining === 0
      ? '<i class="fas fa-ban me-1"></i> You\'ve used all your messages for today. Come back tomorrow!'
      : '<i class="fas fa-triangle-exclamation me-1"></i> Only ' + remaining + ' message' + (remaining !== 1 ? 's' : '') + ' left today.';
    const shell = document.querySelector('.chat-shell');
    const existing = shell.querySelector('.limit-banner');
    if (existing) existing.replaceWith(banner); else shell.insertBefore(banner, document.getElementById('chatMessages'));
  }
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
