<?php
session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: teacher_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | JuniorCode Academy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary: #38bdf8;
      --accent: #0ea5e9;
      --dark: #0f172a;
      --muted: #64748b;
      --soft: #eff6ff;
      --soft-2: #f8fbff;
      --border: #dbeafe;
      --white: #ffffff;
      --shadow: 0 20px 60px rgba(37, 99, 235, 0.12);
    }

    * {
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 24%),
        radial-gradient(circle at bottom right, rgba(56, 189, 248, 0.12), transparent 24%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 15px;
      color: var(--dark);
    }

    .login-wrapper {
      width: 100%;
      max-width: 1120px;
    }

    .login-card {
      border: none;
      border-radius: 30px;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.94);
      box-shadow: var(--shadow);
      backdrop-filter: blur(10px);
    }

    .login-left {
      min-height: 100%;
      padding: 56px 44px;
      color: white;
      position: relative;
      overflow: hidden;
      background:
        radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 25%),
        radial-gradient(circle at bottom left, rgba(255,255,255,0.10), transparent 25%),
        linear-gradient(145deg, #1e40af 0%, #2563eb 45%, #38bdf8 100%);
    }

    .login-left::before {
      content: "";
      position: absolute;
      width: 260px;
      height: 260px;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      top: -80px;
      right: -60px;
    }

    .login-left::after {
      content: "";
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.07);
      bottom: -70px;
      left: -50px;
    }

    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      border-radius: 999px;
      background: white;
      border: 1px solid #e5e7eb;
      margin-bottom: 28px;
      position: relative;
      z-index: 2;
      backgroung-filter: blur(6px);
    }

    .logo-box {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  background: rgab(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.logo-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.brand-title {
  color: black; /* ✅ black text */
  font-weight: 800;
}

.brand-subtitle {
  color: black; /* soft dark gray (looks professional) */
}
    .login-left h2 {
      font-size: 2.5rem;
      line-height: 1.1;
      font-weight: 900;
      margin-bottom: 18px;
      position: relative;
      z-index: 2;
    }

    .login-left p,
    .left-points,
    .left-note {
      position: relative;
      z-index: 2;
    }

    .login-left p {
      color: rgba(255,255,255,0.95);
      line-height: 1.8;
      font-size: 1rem;
      max-width: 490px;
      margin-bottom: 26px;
    }

    .left-points {
      display: grid;
      gap: 14px;
      margin-bottom: 28px;
    }

    .point-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      color: rgba(255,255,255,0.96);
    }

    .point-icon {
      width: 28px;
      height: 28px;
      border-radius: 10px;
      background: rgba(255,255,255,0.16);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .left-note {
      display: inline-block;
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.16);
      font-size: 0.92rem;
      color: rgba(255,255,255,0.92);
    }

    .login-right {
      padding: 56px 44px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .form-top-badge {
      display: inline-block;
      background: var(--soft);
      color: var(--primary-dark);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 8px 14px;
      font-size: 0.85rem;
      font-weight: 800;
      margin-bottom: 16px;
    }

    .login-right h3 {
      font-size: 2rem;
      font-weight: 900;
      margin-bottom: 8px;
      color: var(--dark);
    }

    .subtext {
      color: var(--muted);
      margin-bottom: 28px;
      line-height: 1.7;
    }

    .form-label {
      font-weight: 800;
      color: var(--dark);
      margin-bottom: 8px;
    }

    .form-control {
      height: 54px;
      border-radius: 16px;
      border: 1px solid #dbe4f0;
      padding: 12px 16px;
      font-size: 0.98rem;
      background: #fff;
      transition: all 0.25s ease;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.14);
    }

    .password-wrap {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      top: 50%;
      right: 14px;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: var(--muted);
      font-weight: 700;
      cursor: pointer;
      padding: 0;
    }

    .btn-main {
      width: 100%;
      border: none;
      border-radius: 16px;
      padding: 14px;
      font-weight: 800;
      font-size: 1rem;
      color: white;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      box-shadow: 0 14px 28px rgba(37, 99, 235, 0.22);
      transition: all 0.25s ease;
    }

    .btn-main:hover {
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 18px 34px rgba(37, 99, 235, 0.28);
    }

    .alert {
      border-radius: 14px;
      font-weight: 600;
    }

    .helper-box {
      margin-top: 22px;
      padding: 16px 18px;
      border-radius: 18px;
      background: var(--soft-2);
      border: 1px solid #e8f0ff;
    }

    .helper-box h6 {
      font-weight: 800;
      margin-bottom: 8px;
      color: var(--dark);
    }

    .helper-box p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
      font-size: 0.95rem;
    }

    .bottom-link {
      text-align: center;
      margin-top: 24px;
    }

    .bottom-link a {
      color: var(--primary-dark);
      text-decoration: none;
      font-weight: 800;
    }

    .bottom-link a:hover {
      text-decoration: underline;
    }

    @media (max-width: 991px) {
      .login-left,
      .login-right {
        padding: 38px 24px;
      }

      .login-left h2 {
        font-size: 2rem;
      }

      .login-right h3 {
        font-size: 1.7rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="card login-card">
      <div class="row g-0">
        <div class="col-lg-6">
          <div class="login-left h-100">
            <div class="brand-badge">
              <div class="logo-box"><img src="images/robot2.png.png" alt="JuniorCode Logo"></div>
              <div>
                <div class="brand-title">JuniorCode Academy</div>
                <div class="brand-subtitle">Learn • Build • Grow</div>
              </div>
            </div>

            <h2>Welcome back to your digital learning space</h2>

            <p>
              Sign in to continue your journey in coding, robotics, and future technology.
              Access your lessons, classes, dashboards, and progress in one secure place.
            </p>

            <div class="left-points">
              <div class="point-item">
                <div class="point-icon">✓</div>
                <div>Access student, teacher, or admin dashboards instantly</div>
              </div>
              <div class="point-item">
                <div class="point-icon">✓</div>
                <div>Track classes, tasks, reports, and learning progress</div>
              </div>
              <div class="point-item">
                <div class="point-icon">✓</div>
                <div>Professional, secure, and easy-to-use platform</div>
              </div>
            </div>

            <div class="left-note">
              Built for modern tech education with a clean and focused experience.
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="login-right">
            <span class="form-top-badge">Secure Login</span>
            <h3>Sign in to your account</h3>
            <p class="subtext">Enter your username and password to access your dashboard.</p>

            <?php if (isset($_GET['error'])): ?>
              <div class="alert alert-danger">
                Invalid username or password.
              </div>
            <?php endif; ?>

            <form action="authenticate.php" method="POST">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input
                  type="text"
                  name="username"
                  class="form-control"
                  placeholder="Enter your username"
                  required
                >
              </div>

              <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="password-wrap">
                  <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                  >
                  <button type="button" class="toggle-password" onclick="togglePassword()">Show</button>
                </div>
              </div>

              <button type="submit" class="btn btn-main">Login</button>
            </form>

            <div class="helper-box">
              <h6>Need help?</h6>
              <p>
                If you cannot access your account, please contact the academy administrator or your instructor for assistance.
              </p>
            </div>

            <div class="bottom-link">
              <a href="index.html">← Back to Home</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const toggleBtn = document.querySelector('.toggle-password');

      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.textContent = 'Hide';
      } else {
        passwordInput.type = 'password';
        toggleBtn.textContent = 'Show';
      }
    }
  </script>
</body>
</html>