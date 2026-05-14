<?php
/*
 * mailer.php — lightweight SMTP mailer (no libraries needed)
 * Supports Gmail (port 587 STARTTLS) and any standard SMTP server.
 */

class Mailer {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromName;
    private        $sock = null;
    private array  $log  = [];

    public function __construct(string $host, int $port, string $user, string $pass, string $fromName = 'JuniorCode') {
        $this->host     = $host;
        $this->port     = $port;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->fromName = $fromName;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
        $this->log = [];
        try {
            $this->connect();
            $this->ehlo();
            if ($this->port === 587) $this->startTls();
            $this->auth();
            $this->sendMessage($toEmail, $toName, $subject, $htmlBody);
            $this->quit();
            return true;
        } catch (Exception $e) {
            $this->log[] = 'ERROR: ' . $e->getMessage();
            if ($this->sock) { @fclose($this->sock); $this->sock = null; }
            return false;
        }
    }

    public function lastError(): string { return implode("\n", $this->log); }

    // ── Private SMTP steps ────────────────────────────────────────────────────

    private function connect(): void {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);
        $prefix     = $this->port === 465 ? 'ssl' : 'tcp';
        $this->sock = stream_socket_client(
            "{$prefix}://{$this->host}:{$this->port}",
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$this->sock) throw new Exception("Cannot connect to {$this->host}:{$this->port} — $errstr ($errno)");
        stream_set_timeout($this->sock, 30);
        $this->expect(220);
    }

    private function ehlo(): void {
        $this->cmd('EHLO localhost');
        $this->expect(250);
    }

    private function startTls(): void {
        $this->cmd('STARTTLS');
        $this->expect(220);
        stream_context_set_option($this->sock, ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);
        if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('TLS negotiation failed');
            }
        }
        $this->ehlo();
    }

    private function auth(): void {
        $this->cmd('AUTH LOGIN');
        $this->expect(334);
        $this->cmd(base64_encode($this->user));
        $this->expect(334);
        $this->cmd(base64_encode($this->pass));
        $this->expect(235);
    }

    private function sendMessage(string $toEmail, string $toName, string $subject, string $html): void {
        $this->cmd("MAIL FROM:<{$this->user}>");
        $this->expect(250);
        $this->cmd("RCPT TO:<{$toEmail}>");
        $this->expect(250);
        $this->cmd('DATA');
        $this->expect(354);

        $msgId   = '<' . uniqid('jc', true) . '@juniorcode>';
        $encoded = chunk_split(base64_encode($html));

        $message  = "Date: " . date('r') . "\r\n";
        $message .= "Message-ID: {$msgId}\r\n";
        $message .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->user}>\r\n";
        $message .= "To: =?UTF-8?B?" . base64_encode($toName ?: $toEmail) . "?= <{$toEmail}>\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= $encoded;
        $message .= "\r\n.\r\n";

        fwrite($this->sock, $message);
        $this->expect(250);
    }

    private function quit(): void {
        $this->cmd('QUIT');
        @fclose($this->sock);
        $this->sock = null;
    }

    private function cmd(string $c): void {
        fwrite($this->sock, $c . "\r\n");
    }

    private function expect(int $code): string {
        $response = '';
        while (true) {
            $line = fgets($this->sock, 512);
            if ($line === false) throw new Exception("Connection lost (expected $code)");
            $this->log[] = '<  ' . rtrim($line);
            $response   .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $actual = (int)substr($line, 0, 3);
                if ($actual !== $code) throw new Exception("Expected $code, got $actual: " . rtrim($line));
                break;
            }
        }
        return $response;
    }
}

// ── Class booking notification ────────────────────────────────────────────────
function buildClassNotificationEmail(
    string $teacherName, string $date, string $time,
    string $student, string $type, string $details, string $zoomLink
): string {
    $dateLabel = date('l, d F Y', strtotime($date));
    $timeLabel = date('h:i A', strtotime($time));
    $zoomRow   = $zoomLink
        ? "<tr><td style='padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:120px;'>Zoom Link</td>
           <td style='padding:10px 16px;border-bottom:1px solid #f1f5f9;'><a href='" . htmlspecialchars($zoomLink) . "' style='color:#3b82f6;font-weight:800;'>Join Meeting</a></td></tr>"
        : "";
    $detailsRow = $details
        ? "<tr><td style='padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;width:120px;'>Notes</td>
           <td style='padding:10px 16px;'>" . htmlspecialchars($details) . "</td></tr>"
        : "";

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">NEW CLASS SCHEDULED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="width:64px;height:64px;border-radius:50%;background:#eff6ff;border:2px solid #bfdbfe;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;">📅</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">You have a new class, {$teacherName}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">A class has been scheduled for you by the admin.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:120px;">Date</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$dateLabel}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Time</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$timeLabel}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Student</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$student}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Type</td>
              <td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">{$type}</span>
              </td></tr>
          {$zoomRow}
          {$detailsRow}
        </table>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.<br>Please log in to view your full schedule.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Quiz assignment notification ──────────────────────────────────────────────
function buildQuizAssignmentEmail(
    string $studentName, string $assignedBy, string $quizTitle,
    string $topic, string $difficulty, string $section, string $quizUrl
): string {
    $diffColor = $difficulty === 'Advanced' ? '#dc2626' : ($difficulty === 'Intermediate' ? '#d97706' : '#16a34a');
    $diffBg    = $difficulty === 'Advanced' ? '#fee2e2' : ($difficulty === 'Intermediate' ? '#fef3c7' : '#dcfce7');

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">NEW QUIZ ASSIGNED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">📝</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">You have a new quiz, {$studentName}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;"><strong>{$assignedBy}</strong> has assigned you a quiz. Give it your best shot!</p>

        <div style="background:#f8fbff;border:1px solid #dbeafe;border-radius:16px;padding:20px 24px;margin-bottom:22px;">
          <div style="font-size:18px;font-weight:900;color:#0f172a;margin-bottom:8px;">{$quizTitle}</div>
          <div style="font-size:14px;color:#64748b;font-weight:700;margin-bottom:12px;">Topic: {$topic}</div>
          <div style="display:inline-block;">
            <span style="background:{$diffBg};color:{$diffColor};border-radius:999px;padding:4px 14px;font-size:12px;font-weight:800;">{$difficulty}</span>
            &nbsp;
            <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:4px 14px;font-size:12px;font-weight:800;">{$section}</span>
          </div>
        </div>

        <div style="text-align:center;margin-bottom:22px;">
          <a href="{$quizUrl}" style="background:linear-gradient(135deg,#3e5077,#143674);color:#fff;text-decoration:none;padding:14px 32px;border-radius:14px;font-weight:900;font-size:15px;display:inline-block;">
            Start Quiz →
          </a>
        </div>

        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;font-size:13px;color:#9a3412;font-weight:700;text-align:center;">
          ⏱ Complete the quiz in one sitting — you can only submit once.
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.<br>Log in to your student portal to see all your quizzes.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Student class notification ────────────────────────────────────────────────
function buildStudentClassNotificationEmail(
    string $studentName, string $teacherName, string $date, string $time,
    string $type, string $zoomLink
): string {
    $dateLabel = date('l, d F Y', strtotime($date));
    $timeLabel = date('h:i A', strtotime($time));
    $zoomRow   = $zoomLink
        ? "<tr><td style='padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:120px;'>Zoom Link</td>
           <td style='padding:10px 16px;border-bottom:1px solid #f1f5f9;'><a href='" . htmlspecialchars($zoomLink) . "' style='color:#3b82f6;font-weight:800;'>Join Meeting</a></td></tr>"
        : "";

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">CLASS CONFIRMED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">🎓</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">Your class is confirmed, {$studentName}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">A new class has been scheduled for you. See the details below.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:120px;">Date</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$dateLabel}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Time</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$timeLabel}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Teacher</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$teacherName}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Type</td>
              <td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">{$type}</span>
              </td></tr>
          {$zoomRow}
        </table>

        <div style="margin-top:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:14px;color:#166534;font-weight:700;text-align:center;">
          We look forward to seeing you in class! 🌟
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.<br>Log in to your student portal to view all your classes.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Welcome / account-created notification ────────────────────────────────────
function buildWelcomeEmail(
    string $name, string $role, string $username, string $password, string $loginUrl
): string {
    $roleLabel  = ucfirst($role);
    $roleColor  = $role === 'teacher' ? '#854d0e' : '#6b21a8';
    $roleBg     = $role === 'teacher' ? '#fef9c3' : '#f3e8ff';
    $icon       = $role === 'teacher' ? '👩‍🏫' : '🎓';
    $portalNote = $role === 'teacher'
        ? 'Log in to view your classes, students, and assignments.'
        : 'Log in to view your classes, quizzes, and learning materials.';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">WELCOME TO THE PLATFORM</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">{$icon}</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">Welcome, {$name}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">Your JuniorCode account has been created. Here are your login credentials.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;margin-bottom:22px;">
          <tr>
            <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:110px;">Role</td>
            <td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;">
              <span style="background:{$roleBg};color:{$roleColor};border-radius:999px;padding:3px 14px;font-size:12px;font-weight:800;">{$roleLabel}</span>
            </td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Username</td>
            <td style="padding:12px 16px;font-weight:800;color:#0f172a;font-family:monospace;font-size:15px;border-bottom:1px solid #f1f5f9;">{$username}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;">Password</td>
            <td style="padding:12px 16px;font-weight:800;color:#0f172a;font-family:monospace;font-size:15px;">{$password}</td>
          </tr>
        </table>

        <div style="text-align:center;margin-bottom:22px;">
          <a href="{$loginUrl}" style="background:linear-gradient(135deg,#3e5077,#143674);color:#fff;text-decoration:none;padding:14px 32px;border-radius:14px;font-weight:900;font-size:15px;display:inline-block;">
            Log In Now →
          </a>
        </div>

        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;font-size:13px;color:#9a3412;font-weight:700;text-align:center;">
          🔒 Keep your credentials safe. Change your password after your first login.
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">{$portalNote}<br>This is an automated message from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── New student assigned notification (to teacher) ───────────────────────────
function buildNewStudentEmail(string $teacherName, string $studentName, string $type, string $details): string {
    $detailsRow = $details
        ? "<tr><td style='padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;width:120px;'>Notes</td>
           <td style='padding:10px 16px;'>" . htmlspecialchars($details) . "</td></tr>"
        : "";

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">NEW STUDENT ASSIGNED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">👨‍🎓</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">You have a new student, {$teacherName}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">The admin has assigned a new student to you. Here are the details.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;width:120px;">Student</td>
              <td style="padding:10px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$studentName}</td></tr>
          <tr><td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Class Type</td>
              <td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">{$type}</span>
              </td></tr>
          {$detailsRow}
        </table>

        <div style="margin-top:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:14px;color:#166534;font-weight:700;text-align:center;">
          Log in to your teacher portal to view your updated student list. 🎉
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Session milestone notification ────────────────────────────────────────────
function buildSessionMilestoneEmail(string $studentName, int $sessionCount): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">SESSION MILESTONE</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;text-align:center;">
        <div style="font-size:52px;margin-bottom:14px;">🎉</div>
        <p style="margin:0 0 8px;font-size:22px;font-weight:900;color:#0f172a;">Congratulations, {$studentName}!</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:15px;">You have completed <strong style="color:#7c3aed;">{$sessionCount} sessions</strong> with JuniorCode.</p>

        <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #ddd6fe;border-radius:16px;padding:22px 28px;margin-bottom:24px;">
          <div style="font-size:42px;font-weight:900;color:#7c3aed;line-height:1;">{$sessionCount}</div>
          <div style="font-size:13px;color:#6d28d9;font-weight:700;margin-top:4px;letter-spacing:1px;">SESSIONS COMPLETED</div>
        </div>

        <p style="margin:0 0 20px;color:#334155;font-size:14px;line-height:1.6;">
          You've reached your <strong>{$sessionCount}-session milestone</strong>!<br>
          To keep learning and growing, please <strong>renew your registration</strong> with the admin.
        </p>

        <div style="background:#fefce8;border:1px solid #fde047;border-radius:12px;padding:14px 18px;font-size:14px;color:#854d0e;font-weight:700;">
          Contact us to renew and continue your coding journey! 🚀
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Class updated / rescheduled notification ──────────────────────────────────
function buildClassUpdatedEmail(
    string $recipientName,
    string $teacherName,
    string $studentName,
    string $newDate,
    string $newTime,
    string $classType,
    string $changesSummary
): string {
    $formattedDate = date("l, d F Y", strtotime($newDate));
    $formattedTime = date("g:i A",    strtotime($newTime));
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">CLASS UPDATED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">📅</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">Hi {$recipientName},</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">A class has been updated by the admin. Here are the new details.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;width:120px;border-bottom:1px solid #f1f5f9;">Teacher</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$teacherName}</td>
          </tr>
          <tr>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Student</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$studentName}</td>
          </tr>
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">New Date</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$formattedDate}</td>
          </tr>
          <tr>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">New Time</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$formattedTime}</td>
          </tr>
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;">Type</td>
            <td style="padding:11px 16px;">
              <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">{$classType}</span>
            </td>
          </tr>
        </table>

        <div style="margin-top:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;font-size:14px;color:#92400e;font-weight:700;">
          <strong>What changed:</strong> {$changesSummary}
        </div>

        <div style="margin-top:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 18px;font-size:14px;color:#1e40af;font-weight:700;text-align:center;">
          Please update your schedule accordingly.
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Class cancelled notification ─────────────────────────────────────────────
function buildClassCancelledEmail(
    string $recipientName,
    string $teacherName,
    string $studentName,
    string $classDate,
    string $classTime,
    string $classType
): string {
    $formattedDate = date("l, d F Y", strtotime($classDate));
    $formattedTime = date("g:i A", strtotime($classTime));
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#7f1d1d,#991b1b);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">CLASS CANCELLED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">❌</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">Hi {$recipientName},</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">A class has been cancelled by the admin. Here are the details.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;width:120px;border-bottom:1px solid #f1f5f9;">Teacher</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$teacherName}</td>
          </tr>
          <tr>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Student</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$studentName}</td>
          </tr>
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Date</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$formattedDate}</td>
          </tr>
          <tr>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;border-bottom:1px solid #f1f5f9;">Time</td>
            <td style="padding:11px 16px;font-weight:800;color:#0f172a;border-bottom:1px solid #f1f5f9;">{$formattedTime}</td>
          </tr>
          <tr style="background:#f8fbff;">
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:#64748b;">Type</td>
            <td style="padding:11px 16px;">
              <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">{$classType}</span>
            </td>
          </tr>
        </table>

        <div style="margin-top:20px;background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:14px 18px;font-size:14px;color:#9f1239;font-weight:700;text-align:center;">
          This class has been permanently cancelled. Please contact the admin for more information.
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Student removed notification ─────────────────────────────────────────────
function buildStudentRemovedEmail(string $teacherName, string $studentName): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">STUDENT REMOVED</div>
      </td></tr>

      <tr><td style="background:#fff;padding:28px 32px;">
        <div style="font-size:40px;text-align:center;margin-bottom:18px;">📋</div>
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;text-align:center;">Hi {$teacherName},</p>
        <p style="margin:0 0 24px;color:#64748b;font-size:14px;text-align:center;">The admin has removed a student from your list.</p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <tr><td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;width:120px;">Student</td>
              <td style="padding:12px 16px;font-weight:800;color:#0f172a;">{$studentName}</td></tr>
          <tr><td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;">Status</td>
              <td style="padding:12px 16px;">
                <span style="background:#fee2e2;color:#991b1b;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;">Removed</span>
              </td></tr>
        </table>

        <div style="margin-top:20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;font-size:14px;color:#92400e;font-weight:700;text-align:center;">
          This student's class records remain intact in the system.
        </div>
      </td></tr>

      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from JuniorCode.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Daily schedule reminder ───────────────────────────────────────────────────
function buildReminderEmail(string $teacherName, string $date, int $count, array $classes): string {
    $rows = '';
    foreach ($classes as $c) {
        $time    = date('h:i A', strtotime($c['class_time']));
        $student = htmlspecialchars($c['student_name']);
        $type    = htmlspecialchars(ucfirst($c['type'] ?? 'Class'));
        $zoom    = !empty($c['zoom_link'])
            ? '<br><a href="' . htmlspecialchars($c['zoom_link']) . '" style="color:#3b82f6;font-size:12px;">Join Zoom</a>'
            : '';
        $rows .= "
        <tr>
          <td style='padding:12px 16px;font-weight:700;color:#0f172a;border-bottom:1px solid #f1f5f9;white-space:nowrap;'>{$time}</td>
          <td style='padding:12px 16px;font-weight:700;color:#0f172a;border-bottom:1px solid #f1f5f9;'>{$student}{$zoom}</td>
          <td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'>
            <span style='background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:800;'>{$type}</span>
          </td>
        </tr>";
    }

    $plural = $count === 1 ? 'class' : 'classes';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">JuniorCode</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;letter-spacing:2px;">TEACHER SCHEDULE</div>
      </td></tr>

      <!-- Body -->
      <tr><td style="background:#fff;padding:28px 32px;">
        <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0f172a;">Good morning, {$teacherName}! 👋</p>
        <p style="margin:0 0 22px;color:#64748b;font-size:14px;">Here is your class schedule for today &mdash; <strong style="color:#0f172a;">{$date}</strong></p>

        <!-- Schedule table -->
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
          <thead>
            <tr style="background:#f8fbff;">
              <th style="padding:11px 16px;text-align:left;font-size:12px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #e2e8f0;">Time</th>
              <th style="padding:11px 16px;text-align:left;font-size:12px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #e2e8f0;">Student</th>
              <th style="padding:11px 16px;text-align:left;font-size:12px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #e2e8f0;">Type</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
        </table>

        <div style="margin-top:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:14px;color:#166534;font-weight:700;">
          You have <strong>{$count} {$plural}</strong> scheduled for today. Have a great day! 🎉
        </div>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#f8fbff;border-radius:0 0 18px 18px;padding:18px 32px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated reminder from JuniorCode.<br>You are receiving this because you have classes scheduled today.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
