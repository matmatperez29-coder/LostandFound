<?php
// ============================================
// iAcademy Lost & Found — Login Handler
// auth/login.php
// ============================================

session_start();

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, first_name, last_name, email, password_hash, role, is_active
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been deactivated. Contact the administrator.';
        } else {
            // Log activity
            $pdo->prepare(
                "INSERT INTO activity_log (actor_id, action, target_type, target_id, ip_address)
                 VALUES (?, 'user.login', 'user', ?, ?)"
            )->execute([$user['id'], $user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

            // Set session
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];

            // Redirect by role
            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../user/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iAcademy — Lost & Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    /* ── CSS Variables ── */
    :root {
      --navy-deep:   #05063a;
      --navy-mid:    #0a0e6b;
      --navy-bright: #1a24c8;
      --blue-glow:   #2b5ce6;
      --blue-accent: #4d82ff;
      --white:       #ffffff;
      --white-soft:  rgba(255,255,255,0.85);
      --white-dim:   rgba(255,255,255,0.45);
      --white-ghost: rgba(255,255,255,0.08);
      --white-line:  rgba(255,255,255,0.12);
      --error:       #ff6b6b;
      --success:     #6bffb8;

      --font-display: 'Cormorant Garamond', serif;
      --font-body:    'DM Sans', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-family: var(--font-body);
      background: var(--navy-deep);
      color: var(--white);
      overflow: hidden;
    }

    /* ── Animated Background ── */
    .bg-canvas {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 80%, rgba(26,36,200,0.55) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 20%, rgba(43,92,230,0.35) 0%, transparent 55%),
        radial-gradient(ellipse 100% 80% at 50% 50%, var(--navy-mid) 0%, var(--navy-deep) 70%);
    }

    .bg-grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 60px 60px;
      mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
    }

    /* Floating orbs */
    .orb {
      position: fixed; border-radius: 50%; filter: blur(80px);
      animation: drift 18s ease-in-out infinite alternate;
      z-index: 0; pointer-events: none;
    }
    .orb-1 {
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(43,92,230,0.25), transparent 70%);
      top: -150px; left: -100px;
      animation-duration: 20s;
    }
    .orb-2 {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(26,36,200,0.2), transparent 70%);
      bottom: -100px; right: -50px;
      animation-duration: 15s; animation-delay: -7s;
    }
    .orb-3 {
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(77,130,255,0.15), transparent 70%);
      top: 40%; left: 60%;
      animation-duration: 22s; animation-delay: -3s;
    }

    @keyframes drift {
      from { transform: translate(0, 0) scale(1); }
      to   { transform: translate(40px, 30px) scale(1.1); }
    }

    /* ── Layout ── */
    .page {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      height: 100vh;
    }

    /* ── Left Panel ── */
    .panel-left {
      display: flex; flex-direction: column;
      justify-content: center; align-items: flex-start;
      padding: 60px 70px;
      border-right: 1px solid var(--white-line);
      position: relative; overflow: hidden;
    }

    .panel-left::after {
      content: '';
      position: absolute; top: 0; right: 0;
      width: 1px; height: 100%;
      background: linear-gradient(to bottom, transparent, var(--blue-accent), transparent);
      opacity: 0.6;
    }

    .brand-tag {
      font-family: var(--font-body);
      font-size: 11px; font-weight: 500;
      letter-spacing: 0.3em; text-transform: uppercase;
      color: var(--blue-accent);
      margin-bottom: 48px;
      opacity: 0;
      animation: fadeUp 0.8s 0.2s ease forwards;
    }

    /* iAcademy Hexagon Logo (SVG recreation) */
    .logo-wrap {
      margin-bottom: 48px;
      opacity: 0;
      animation: fadeUp 0.8s 0.4s ease forwards;
    }

    .logo-hex {
      width: 120px; height: auto;
    }

    .hero-heading {
      font-family: var(--font-display);
      font-size: clamp(48px, 5vw, 72px);
      font-weight: 300;
      line-height: 1.05;
      letter-spacing: -0.01em;
      color: var(--white);
      margin-bottom: 20px;
      opacity: 0;
      animation: fadeUp 0.8s 0.6s ease forwards;
    }

    .hero-heading em {
      font-style: italic;
      color: var(--blue-accent);
    }

    .hero-sub {
      font-size: 14px; font-weight: 300;
      color: var(--white-dim);
      line-height: 1.7;
      max-width: 320px;
      opacity: 0;
      animation: fadeUp 0.8s 0.8s ease forwards;
    }

    .decorative-line {
      width: 60px; height: 1px;
      background: linear-gradient(to right, var(--blue-accent), transparent);
      margin: 36px 0;
      opacity: 0;
      animation: fadeUp 0.8s 1.0s ease forwards;
    }

    .stats-row {
      display: flex; gap: 40px;
      opacity: 0;
      animation: fadeUp 0.8s 1.1s ease forwards;
    }

    .stat-item { display: flex; flex-direction: column; gap: 4px; }
    .stat-num {
      font-family: var(--font-display);
      font-size: 32px; font-weight: 600;
      color: var(--white);
    }
    .stat-label {
      font-size: 11px; font-weight: 300;
      color: var(--white-dim);
      letter-spacing: 0.1em; text-transform: uppercase;
    }

    /* ── Right Panel / Form ── */
    .panel-right {
      display: flex; flex-direction: column;
      justify-content: center; align-items: center;
      padding: 60px 70px;
    }

    .form-card {
      width: 100%; max-width: 400px;
    }

    .form-header {
      margin-bottom: 40px;
      opacity: 0;
      animation: fadeUp 0.8s 0.5s ease forwards;
    }

    .form-eyebrow {
      font-size: 11px; font-weight: 500;
      letter-spacing: 0.25em; text-transform: uppercase;
      color: var(--blue-accent); margin-bottom: 10px;
    }

    .form-title {
      font-family: var(--font-display);
      font-size: 36px; font-weight: 300;
      color: var(--white);
    }

    /* Alert */
    .alert {
      padding: 12px 16px;
      border-radius: 6px;
      font-size: 13px; font-weight: 400;
      margin-bottom: 24px;
      display: none;
      align-items: center; gap: 10px;
      animation: fadeUp 0.3s ease forwards;
    }
    .alert.show { display: flex; }
    .alert-error {
      background: rgba(255,107,107,0.1);
      border: 1px solid rgba(255,107,107,0.3);
      color: var(--error);
    }
    .alert-success {
      background: rgba(107,255,184,0.1);
      border: 1px solid rgba(107,255,184,0.3);
      color: var(--success);
    }

    /* ── Form Fields ── */
    .field-group {
      display: flex; flex-direction: column; gap: 20px;
      margin-bottom: 32px;
      opacity: 0;
      animation: fadeUp 0.8s 0.7s ease forwards;
    }

    .field {
      display: flex; flex-direction: column; gap: 8px;
      position: relative;
    }

    .field label {
      font-size: 11px; font-weight: 500;
      letter-spacing: 0.15em; text-transform: uppercase;
      color: var(--white-dim);
    }

    .field input {
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      border-radius: 8px;
      padding: 14px 18px;
      font-family: var(--font-body);
      font-size: 14px; font-weight: 400;
      color: var(--white);
      outline: none;
      transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
      width: 100%;
    }

    .field input::placeholder { color: rgba(255,255,255,0.2); }

    .field input:focus {
      border-color: var(--blue-accent);
      background: rgba(77,130,255,0.08);
      box-shadow: 0 0 0 3px rgba(77,130,255,0.12);
    }

    .field input.error-field {
      border-color: var(--error);
      box-shadow: 0 0 0 3px rgba(255,107,107,0.1);
    }

    /* Password toggle */
    .field-pw { position: relative; }
    .field-pw input { padding-right: 50px; }
    .pw-toggle {
      position: absolute; right: 14px; bottom: 14px;
      background: none; border: none; cursor: pointer;
      color: var(--white-dim); padding: 0;
      transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--white); }
    .pw-toggle svg { display: block; }

    /* Forgot link */
    .field-footer {
      display: flex; justify-content: flex-end;
      margin-top: -10px;
    }
    .forgot-link {
      font-size: 12px; color: var(--blue-accent);
      text-decoration: none; font-weight: 400;
      transition: opacity 0.2s;
    }
    .forgot-link:hover { opacity: 0.7; }

    /* ── Submit Button ── */
    .btn-submit {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, var(--navy-bright), var(--blue-glow));
      border: 1px solid rgba(77,130,255,0.3);
      border-radius: 8px;
      font-family: var(--font-body);
      font-size: 13px; font-weight: 500;
      letter-spacing: 0.12em; text-transform: uppercase;
      color: var(--white);
      cursor: pointer;
      position: relative; overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
      opacity: 0;
      animation: fadeUp 0.8s 0.9s ease forwards;
    }

    .btn-submit::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
      opacity: 0; transition: opacity 0.3s;
    }

    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 30px rgba(43,92,230,0.4);
    }
    .btn-submit:hover::before { opacity: 1; }
    .btn-submit:active { transform: translateY(0); }

    .btn-submit.loading {
      pointer-events: none; opacity: 0.7;
    }

    .btn-inner { display: flex; align-items: center; justify-content: center; gap: 8px; }

    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }

    .btn-submit.loading .spinner { display: block; }
    .btn-submit.loading .btn-text { opacity: 0.6; }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Footer note ── */
    .form-note {
      text-align: center;
      margin-top: 28px;
      font-size: 12px; color: var(--white-dim);
      opacity: 0;
      animation: fadeUp 0.8s 1.1s ease forwards;
    }

    /* ── Animations ── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── PHP Error Banner ── */
    <?php if ($error): ?>
    .php-alert { display: flex !important; }
    <?php endif; ?>

    /* ── Responsive ── */
    @media (max-width: 768px) {
      html, body { overflow: auto; }
      .page { grid-template-columns: 1fr; height: auto; min-height: 100vh; }
      .panel-left { display: none; }
      .panel-right { padding: 50px 30px; min-height: 100vh; }
    }
  </style>
</head>
<body>

  <div class="bg-canvas"></div>
  <div class="bg-grid"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <div class="page">

    <!-- ── LEFT PANEL ── -->
    <div class="panel-left">
      <div class="brand-tag">iAcademy · Est. 2002</div>

      <!-- Hexagon Logo SVG recreation -->
      <div class="logo-wrap">
        <svg class="logo-hex" viewBox="0 0 120 138" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Hexagon outline -->
          <path d="M60 4L112 32V88L60 116L8 88V32L60 4Z"
                fill="rgba(255,255,255,0.06)"
                stroke="rgba(255,255,255,0.25)" stroke-width="1.5"/>
          <!-- "i" dot -->
          <rect x="54" y="38" width="12" height="10" rx="2" fill="white" opacity="0.9"/>
          <!-- "i" stem + "A" merged mark -->
          <path d="M60 54 L60 88" stroke="white" stroke-width="5" stroke-linecap="round"/>
          <path d="M42 88 L60 54 L78 88" stroke="white" stroke-width="5"
                stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          <!-- Crossbar of A -->
          <path d="M50 76 L70 76" stroke="white" stroke-width="4" stroke-linecap="round"/>
        </svg>
      </div>

      <h1 class="hero-heading">Lost &<br><em>Found</em><br>System</h1>
      <p class="hero-sub">A centralized platform for reporting, tracking, and recovering lost items within the iAcademy campus.</p>
      <div class="decorative-line"></div>
      <div class="stats-row">
        <div class="stat-item">
          <span class="stat-num">Computing</span>
          <span class="stat-label">College</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">Business</span>
          <span class="stat-label">College</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">Design</span>
          <span class="stat-label">College</span>
        </div>
      </div>
    </div>

    <!-- ── RIGHT PANEL (FORM) ── -->
    <div class="panel-right">
      <div class="form-card">

        <div class="form-header">
          <p class="form-eyebrow">Secure Access</p>
          <h2 class="form-title">Sign in to<br>your account</h2>
        </div>

        <!-- PHP Error Message -->
        <?php if ($error): ?>
        <div class="alert alert-error show php-alert">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
            <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- JS Alert -->
        <div class="alert alert-error" id="js-alert">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
            <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span id="js-alert-msg"></span>
        </div>

        <form method="POST" action="" id="login-form" novalidate>

          <div class="field-group">

            <!-- Email -->
            <div class="field">
              <label for="email">Email Address</label>
              <input
                type="email" id="email" name="email"
                placeholder="you@iacademy.edu.ph"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                autocomplete="email"
              />
            </div>

            <!-- Password -->
            <div class="field">
              <label for="password">Password</label>
              <div class="field-pw">
                <input
                  type="password" id="password" name="password"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                />
                <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password">
                  <svg id="eye-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
              <div class="field-footer">
                <a href="#" class="forgot-link">Forgot password?</a>
              </div>
            </div>

          </div><!-- /field-group -->

          <button type="submit" class="btn-submit" id="btn-submit">
            <span class="btn-inner">
              <span class="spinner" id="spinner"></span>
              <span class="btn-text">Sign In</span>
            </span>
          </button>

        </form>

        <p class="form-note">
          Don't have an account? <a href="register.php" style="color:var(--blue-accent);text-decoration:none;font-weight:500;">Sign up here</a>
        </p>

      </div>
    </div>

  </div><!-- /page -->

  <script>
    // ── Password toggle ──
    const pwInput  = document.getElementById('password');
    const pwToggle = document.getElementById('pw-toggle');
    const eyeIcon  = document.getElementById('eye-icon');

    const EYE_OPEN = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    const EYE_CLOSED = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

    pwToggle.addEventListener('click', () => {
      const isText = pwInput.type === 'text';
      pwInput.type = isText ? 'password' : 'text';
      eyeIcon.innerHTML = isText ? EYE_OPEN : EYE_CLOSED;
    });

    // ── Client-side validation ──
    const form     = document.getElementById('login-form');
    const jsAlert  = document.getElementById('js-alert');
    const jsMsg    = document.getElementById('js-alert-msg');
    const btnSubmit = document.getElementById('btn-submit');
    const emailInput = document.getElementById('email');

    function showAlert(msg) {
      jsMsg.textContent = msg;
      jsAlert.classList.add('show');
    }
    function clearAlert() {
      jsAlert.classList.remove('show');
      emailInput.classList.remove('error-field');
      pwInput.classList.remove('error-field');
    }

    form.addEventListener('submit', (e) => {
      clearAlert();
      const email = emailInput.value.trim();
      const pass  = pwInput.value.trim();
      const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!email || !pass) {
        e.preventDefault();
        showAlert('Please fill in all fields.');
        if (!email) emailInput.classList.add('error-field');
        if (!pass)  pwInput.classList.add('error-field');
        return;
      }
      if (!emailRe.test(email)) {
        e.preventDefault();
        showAlert('Enter a valid email address.');
        emailInput.classList.add('error-field');
        return;
      }

      // Loading state
      btnSubmit.classList.add('loading');
      document.getElementById('spinner').style.display = 'block';
    });

    // Clear error on input
    [emailInput, pwInput].forEach(el => {
      el.addEventListener('input', () => {
        el.classList.remove('error-field');
        clearAlert();
      });
    });
  </script>
</body>
</html>