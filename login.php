<?php
session_start();

$loginSuccess = isset($_GET['success']) && isset($_SESSION['role']);

if (isset($_SESSION['role']) && !$loginSuccess) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php"); exit();
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: teacher_dashboard.php"); exit();
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php"); exit();
    }
}

$redirectUrl = '';
if ($loginSuccess) {
    if ($_SESSION['role'] === 'admin')   $redirectUrl = 'admin_dashboard.php';
    elseif ($_SESSION['role'] === 'teacher') $redirectUrl = 'teacher_dashboard.php';
    elseif ($_SESSION['role'] === 'student') $redirectUrl = 'student_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | JuniorCode Academy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #2563eb;
      --primary-dark: #1e40af;
      --secondary:    #0ea5e9;
      --dark:         #0f172a;
      --muted:        #64748b;
      --border:       #e2e8f0;
      --soft:         #eff6ff;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      color: var(--dark);
    }

    /* ── Layout ── */
    .login-layout {
      display: flex;
      min-height: 100vh;
    }

    /* ═══════════════════════════════
       LEFT PANEL
    ═══════════════════════════════ */
    .left-panel {
      width: 42%;
      flex-shrink: 0;
      position: relative;
      overflow: hidden;
      background: linear-gradient(150deg, #0d1f4e 0%, #1e3a8a 45%, #0369a1 100%);
      display: flex;
      align-items: center;
      padding: 60px 48px;
    }

    /* Blurred orbs */
    .lp-orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(65px);
      pointer-events: none;
      opacity: 0.22;
      animation: orbDrift 12s ease-in-out infinite;
    }
    .lp-orb-1 { width: 320px; height: 320px; background: #60a5fa; top: -100px; right: -80px; animation-duration: 14s; }
    .lp-orb-2 { width: 240px; height: 240px; background: #38bdf8; bottom: 40px;  left:  -60px; animation-duration: 11s; animation-delay: 4s; animation-direction: reverse; }
    .lp-orb-3 { width: 180px; height: 180px; background: #818cf8; top: 50%;     left:  35%;   animation-duration: 9s;  animation-delay: 2s; }

    @keyframes orbDrift {
      0%,100% { transform: translate(0,0); }
      33%      { transform: translate(22px,-28px); }
      66%      { transform: translate(-16px,18px); }
    }

    /* Floating code particles */
    .particle {
      position: absolute;
      font-family: 'Courier New', monospace;
      font-weight: 900;
      color: rgba(255,255,255,0.12);
      pointer-events: none;
      user-select: none;
      bottom: -60px;
      left: var(--x);
      font-size: var(--sz, 1.2rem);
      animation: riseUp var(--dur, 9s) ease-in var(--delay, 0s) infinite;
    }

    @keyframes riseUp {
      0%   { transform: translateY(0)      rotate(0deg);   opacity: 0; }
      8%   { opacity: 1; }
      92%  { opacity: 0.7; }
      100% { transform: translateY(-115vh) rotate(20deg);  opacity: 0; }
    }

    /* Left content */
    .left-content {
      position: relative;
      z-index: 2;
      width: 100%;
    }

    .lp-brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 999px;
      padding: 10px 18px;
      margin-bottom: 40px;
      text-decoration: none;
      transition: background 0.25s;
    }
    .lp-brand:hover { background: rgba(255,255,255,0.16); }

    .lp-brand-logo {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      object-fit: contain;
      background: rgba(255,255,255,0.15);
    }

    .lp-brand-name {
      font-weight: 800;
      font-size: 0.95rem;
      color: #fff;
      line-height: 1.2;
    }

    .lp-brand-sub {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.65);
      letter-spacing: 1px;
    }

    .lp-headline {
      font-size: clamp(1.8rem, 2.8vw, 2.7rem);
      font-weight: 900;
      line-height: 1.12;
      color: #fff;
      margin-bottom: 18px;
      min-height: 3.5em;
    }

    .tw-word {
      background: linear-gradient(90deg, #7dd3fc, #a5f3fc);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .tw-cursor {
      display: inline-block;
      width: 3px;
      height: 0.8em;
      background: #7dd3fc;
      border-radius: 2px;
      margin-left: 2px;
      vertical-align: middle;
      animation: cursorBlink 0.75s step-end infinite;
    }
    @keyframes cursorBlink { 50% { opacity: 0; } }

    .lp-sub {
      color: rgba(255,255,255,0.75);
      font-size: 0.97rem;
      line-height: 1.8;
      margin-bottom: 32px;
      max-width: 380px;
    }

    .lp-features {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 36px;
    }

    .lp-feat {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.9);
      font-size: 0.93rem;
      opacity: 0;
      transform: translateX(-20px);
      transition: opacity 0.5s ease, transform 0.5s ease;
    }
    .lp-feat.visible { opacity: 1; transform: translateX(0); }

    .lp-feat-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
      flex-shrink: 0;
    }

    .lp-stats {
      display: flex;
      gap: 24px;
      padding-top: 28px;
      border-top: 1px solid rgba(255,255,255,0.15);
    }

    .lp-stat strong {
      display: block;
      font-size: 1.4rem;
      font-weight: 900;
      color: #fff;
    }

    .lp-stat span {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.6);
    }

    /* ═══════════════════════════════
       RIGHT PANEL
    ═══════════════════════════════ */
    .right-panel {
      flex: 1;
      background: #fff;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-y: auto;
    }

    .rp-topbar {
      padding: 24px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .back-link {
      color: var(--muted);
      text-decoration: none;
      font-weight: 700;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: color 0.2s;
    }
    .back-link:hover { color: var(--primary); }

    .form-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 20px 64px 60px;
      max-width: 520px;
      width: 100%;
      margin: 0 auto;
    }

    .form-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--soft);
      color: var(--primary-dark);
      border: 1px solid #bfdbfe;
      border-radius: 999px;
      padding: 7px 14px;
      font-size: 0.82rem;
      font-weight: 800;
      margin-bottom: 20px;
    }

    .form-badge::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #22c55e;
      display: inline-block;
      animation: pulse-dot 1.8s ease-in-out infinite;
    }
    @keyframes pulse-dot {
      0%,100% { transform: scale(1);   opacity: 1; }
      50%      { transform: scale(1.4); opacity: 0.6; }
    }

    .form-title {
      font-size: 2rem;
      font-weight: 900;
      color: var(--dark);
      margin-bottom: 8px;
    }

    .form-sub {
      color: var(--muted);
      line-height: 1.7;
      margin-bottom: 32px;
      font-size: 0.97rem;
    }

    /* ── Floating-label fields ── */
    .field-wrap {
      position: relative;
      margin-bottom: 20px;
    }

    .field-input {
      width: 100%;
      height: 58px;
      border: 2px solid var(--border);
      border-radius: 16px;
      padding: 22px 48px 8px 16px;
      font-size: 1rem;
      color: var(--dark);
      background: #fff;
      outline: none;
      transition: border-color 0.25s, box-shadow 0.25s;
      appearance: none;
    }

    .field-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
    }

    .field-label {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 0.97rem;
      color: #94a3b8;
      font-weight: 600;
      pointer-events: none;
      transition: top 0.2s ease, font-size 0.2s ease, color 0.2s ease;
      background: #fff;
      padding: 0 3px;
    }

    .field-input:focus   ~ .field-label,
    .field-input:not(:placeholder-shown) ~ .field-label {
      top: 10px;
      font-size: 0.72rem;
      color: var(--primary);
      transform: none;
    }

    .field-suffix {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      align-items: center;
      color: #94a3b8;
    }

    .eye-btn {
      border: none;
      background: transparent;
      color: #94a3b8;
      cursor: pointer;
      padding: 4px;
      display: flex;
      align-items: center;
      transition: color 0.2s;
    }
    .eye-btn:hover { color: var(--primary); }

    /* ── Login button ── */
    .login-btn {
      width: 100%;
      height: 54px;
      border: none;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      font-weight: 800;
      font-size: 1rem;
      margin-top: 6px;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: transform 0.25s, box-shadow 0.25s;
      box-shadow: 0 12px 28px rgba(37,99,235,0.22);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .login-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 18px 36px rgba(37,99,235,0.3);
    }

    .login-btn:disabled { opacity: 0.75; cursor: not-allowed; transform: none; }

    .btn-spinner {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255,255,255,0.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .login-btn.loading .btn-spinner { display: block; }
    .login-btn.loading .btn-label   { display: none; }

    /* ── Error alert ── */
    .alert-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: 14px;
      padding: 13px 16px;
      font-weight: 600;
      font-size: 0.93rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-box::before { font-family: "Font Awesome 6 Free"; font-weight: 900; content: "\f071"; }

    /* ── Success alert ── */
    .success-box {
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #15803d;
      border-radius: 14px;
      padding: 13px 16px;
      font-weight: 700;
      font-size: 0.97rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: successPop .4s ease;
    }
    @keyframes successPop {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Helper box ── */
    .helper-box {
      margin-top: 24px;
      padding: 18px 20px;
      border-radius: 18px;
      background: #f8faff;
      border: 1px solid #e0ecff;
    }
    .helper-box h6 { font-weight: 800; margin-bottom: 6px; font-size: 0.92rem; }
    .helper-box p  { color: var(--muted); font-size: 0.88rem; line-height: 1.7; margin: 0; }

    /* ═══════════════════════════════
       RESPONSIVE
    ═══════════════════════════════ */
    @media (max-width: 991px) {
      .login-layout { flex-direction: column; }

      .left-panel {
        width: 100%;
        padding: 40px 28px 36px;
        min-height: auto;
      }

      .lp-headline { font-size: 1.8rem; min-height: auto; }
      .lp-sub      { display: none; }
      .lp-features { display: none; }
      .lp-stats    { padding-top: 20px; }

      .form-area { padding: 20px 28px 48px; }
    }

    @media (max-width: 575px) {
      .rp-topbar { padding: 16px 20px; }
      .form-area { padding: 16px 20px 40px; }
      .form-title { font-size: 1.6rem; }
      .left-panel { padding: 28px 20px; }
    }
  </style>
</head>
<body>

<div class="login-layout">

  <!-- ══ LEFT PANEL ══ -->
  <div class="left-panel">

    <!-- Orbs -->
    <div class="lp-orb lp-orb-1"></div>
    <div class="lp-orb lp-orb-2"></div>
    <div class="lp-orb lp-orb-3"></div>

    <!-- Floating code particles -->
    <div class="particle" style="--x:6%;  --dur:10s; --delay:0s;   --sz:1.5rem">&lt;/&gt;</div>
    <div class="particle" style="--x:20%; --dur:13s; --delay:2.5s; --sz:1.0rem">&#123;&#125;</div>
    <div class="particle" style="--x:34%; --dur:8s;  --delay:5s;   --sz:0.85rem">&#40;&#41;</div>
    <div class="particle" style="--x:50%; --dur:15s; --delay:1s;   --sz:1.3rem">&#35;</div>
    <div class="particle" style="--x:64%; --dur:11s; --delay:4s;   --sz:1.1rem">=&gt;</div>
    <div class="particle" style="--x:78%; --dur:9s;  --delay:7s;   --sz:0.9rem">&#42;&#42;</div>
    <div class="particle" style="--x:88%; --dur:12s; --delay:3s;   --sz:1.4rem">&lt;&gt;</div>
    <div class="particle" style="--x:14%; --dur:14s; --delay:6s;   --sz:0.8rem">&#47;&#47;</div>
    <div class="particle" style="--x:44%; --dur:7s;  --delay:9s;   --sz:1.2rem">&lt;/&gt;</div>
    <div class="particle" style="--x:72%; --dur:16s; --delay:0.8s; --sz:1.0rem">&#123; &#125;</div>

    <!-- Content -->
    <div class="left-content">

      <a href="index.html" class="lp-brand">
        <img src="images/robot2.png.png" alt="JuniorCode Logo" class="lp-brand-logo">
        <div>
          <div class="lp-brand-name">JuniorCode Academy</div>
          <div class="lp-brand-sub">LEARN &bull; BUILD &bull; GROW</div>
        </div>
      </a>

      <h2 class="lp-headline">
        Your gateway to<br>
        <span class="tw-word" id="tw-word"></span><span class="tw-cursor"></span>
      </h2>

      <p class="lp-sub">
        Sign in to access your personalised dashboard, upcoming classes,
        course materials, and progress reports — all in one secure place.
      </p>

      <div class="lp-features" id="lp-features">
        <div class="lp-feat">
          <div class="lp-feat-icon"><i class="fas fa-bullseye"></i></div>
          <span>Age-based learning paths for students aged 6–18</span>
        </div>
        <div class="lp-feat">
          <div class="lp-feat-icon"><i class="fas fa-laptop-code"></i></div>
          <span>Real coding projects, games, and web development</span>
        </div>
        <div class="lp-feat">
          <div class="lp-feat-icon"><i class="fas fa-robot"></i></div>
          <span>Robotics, AI exploration, and future tech skills</span>
        </div>
        <div class="lp-feat">
          <div class="lp-feat-icon"><i class="fas fa-chalkboard-user"></i></div>
          <span>Live mentor-led sessions with instant feedback</span>
        </div>
      </div>

      <div class="lp-stats">
        <div class="lp-stat"><strong>500+</strong><span>Students</span></div>
        <div class="lp-stat"><strong>12+</strong><span>Courses</span></div>
        <div class="lp-stat"><strong>4.9<i class="fas fa-star fa-xs ms-1"></i></strong><span>Rating</span></div>
      </div>

    </div>
  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right-panel">

    <div class="rp-topbar">
      <a href="index.html" class="back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Home
      </a>
    </div>

    <div class="form-area">

      <div class="form-badge">Secure Login</div>

      <h2 class="form-title">Sign in to your account</h2>
      <p class="form-sub">Enter your credentials to access your dashboard and learning materials.</p>

      <?php if ($loginSuccess): ?>
        <div class="success-box" id="successBox">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Login successful! Redirecting you now…
        </div>
        <script>
          setTimeout(() => { window.location.href = "<?= htmlspecialchars($redirectUrl) ?>"; }, 1800);
        </script>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert-box">Invalid username or password. Please try again.</div>
      <?php endif; ?>

      <form action="authenticate.php" method="POST" id="loginForm">

        <!-- Username -->
        <div class="field-wrap">
          <input
            type="text"
            id="username"
            name="username"
            class="field-input"
            placeholder=" "
            autocomplete="username"
            required
          >
          <label for="username" class="field-label">Username</label>
          <div class="field-suffix">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
        </div>

        <!-- Password -->
        <div class="field-wrap">
          <input
            type="password"
            id="password"
            name="password"
            class="field-input"
            placeholder=" "
            autocomplete="current-password"
            required
          >
          <label for="password" class="field-label">Password</label>
          <div class="field-suffix">
            <button type="button" class="eye-btn" id="eyeBtn" onclick="togglePassword()" aria-label="Show password">
              <!-- eye-open -->
              <svg id="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <!-- eye-closed (hidden) -->
              <svg id="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">
          <span class="btn-label">Sign In</span>
          <div class="btn-spinner"></div>
        </button>

      </form>

      <div class="helper-box">
        <h6>Need help signing in?</h6>
        <p>Contact your academy administrator or instructor if you cannot access your account.</p>
      </div>

    </div>
  </div>

</div>

<script>
  /* ── Typewriter ── */
  (function () {
    const words  = ["Coding", "Robotics", "AI", "Web Dev", "Python"];
    const el     = document.getElementById("tw-word");
    let wi = 0, ci = 0, deleting = false;

    function tick() {
      const word = words[wi];
      if (!deleting && ci < word.length) {
        el.textContent = word.slice(0, ++ci);
        setTimeout(tick, 95);
      } else if (!deleting && ci === word.length) {
        setTimeout(() => { deleting = true; tick(); }, 1800);
      } else if (deleting && ci > 0) {
        el.textContent = word.slice(0, --ci);
        setTimeout(tick, 50);
      } else {
        deleting = false;
        wi = (wi + 1) % words.length;
        setTimeout(tick, 300);
      }
    }
    tick();
  })();

  /* ── Staggered feature reveal ── */
  document.querySelectorAll("#lp-features .lp-feat").forEach((el, i) => {
    setTimeout(() => el.classList.add("visible"), 400 + i * 150);
  });

  /* ── Password toggle ── */
  function togglePassword() {
    const input  = document.getElementById("password");
    const open   = document.getElementById("eye-open");
    const closed = document.getElementById("eye-closed");
    const showing = input.type === "text";
    input.type   = showing ? "password" : "text";
    open.style.display   = showing ? ""      : "none";
    closed.style.display = showing ? "none"  : "";
  }

  /* ── Submit loading state ── */
  document.getElementById("loginForm").addEventListener("submit", function () {
    const btn = document.getElementById("loginBtn");
    btn.classList.add("loading");
    btn.disabled = true;
  });
</script>

</body>
</html>
