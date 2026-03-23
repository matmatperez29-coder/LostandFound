<?php
// ============================================
// iAcademy Lost & Found — Register Handler
// auth/register.php
// ============================================

session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = trim($_POST['password']   ?? '');
    $confirm    = trim($_POST['confirm']    ?? '');

    // Validation
    if (!$first_name || !$last_name || !$email || !$password || !$confirm) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = getPDO();

        // Check duplicate email
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            // Check duplicate student ID (if provided)
            if ($student_id) {
                $chk2 = $pdo->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
                $chk2->execute([$student_id]);
                if ($chk2->fetch()) {
                    $error = 'That Student ID is already registered.';
                }
            }

            if (!$error) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (first_name, last_name, student_id, email, password_hash, role)
                     VALUES (?, ?, ?, ?, ?, "user")'
                );
                $stmt->execute([
                    $first_name, $last_name,
                    $student_id ?: null,
                    $email, $hash
                ]);

                $new_id = $pdo->lastInsertId();

                // Log
                $pdo->prepare(
                    "INSERT INTO activity_log (actor_id, action, target_type, target_id, ip_address)
                     VALUES (?, 'user.register', 'user', ?, ?)"
                )->execute([$new_id, $new_id, $_SERVER['REMOTE_ADDR'] ?? null]);

                $success = 'Account created! You can now sign in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iAcademy — Create Account</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
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

    html { scroll-behavior: smooth; }

    body {
      font-family: var(--font-body);
      background: var(--navy-deep);
      color: var(--white);
      min-height: 100vh;
    }

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

    .orb {
      position: fixed; border-radius: 50%; filter: blur(80px);
      animation: drift 18s ease-in-out infinite alternate;
      z-index: 0; pointer-events: none;
    }
    .orb-1 { width:500px; height:500px; background:radial-gradient(circle,rgba(43,92,230,.25),transparent 70%); top:-150px; left:-100px; animation-duration:20s; }
    .orb-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(26,36,200,.2),transparent 70%); bottom:-100px; right:-50px; animation-duration:15s; animation-delay:-7s; }
    .orb-3 { width:300px; height:300px; background:radial-gradient(circle,rgba(77,130,255,.15),transparent 70%); top:40%; left:60%; animation-duration:22s; animation-delay:-3s; }

    @keyframes drift {
      from { transform:translate(0,0) scale(1); }
      to   { transform:translate(40px,30px) scale(1.1); }
    }

    /* ── Layout ── */
    .page {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
    }

    /* ── Left Panel ── */
    .panel-left {
      display: flex; flex-direction: column;
      justify-content: center; align-items: flex-start;
      padding: 60px 70px;
      border-right: 1px solid var(--white-line);
      position: sticky; top: 0; height: 100vh;
    }

    .panel-left::after {
      content:''; position:absolute; top:0; right:0;
      width:1px; height:100%;
      background:linear-gradient(to bottom,transparent,var(--blue-accent),transparent);
      opacity:.6;
    }

    .brand-tag {
      font-size:11px; font-weight:500; letter-spacing:.3em; text-transform:uppercase;
      color:var(--blue-accent); margin-bottom:48px;
      opacity:0; animation:fadeUp .8s .2s ease forwards;
    }

    .logo-wrap { margin-bottom:48px; opacity:0; animation:fadeUp .8s .4s ease forwards; }
    .logo-hex  { width:120px; height:auto; }

    .hero-heading {
      font-family:var(--font-display); font-size:clamp(48px,5vw,72px);
      font-weight:300; line-height:1.05; color:var(--white); margin-bottom:20px;
      opacity:0; animation:fadeUp .8s .6s ease forwards;
    }
    .hero-heading em { font-style:italic; color:var(--blue-accent); }

    .hero-sub {
      font-size:14px; font-weight:300; color:var(--white-dim); line-height:1.7; max-width:320px;
      opacity:0; animation:fadeUp .8s .8s ease forwards;
    }

    .decorative-line {
      width:60px; height:1px;
      background:linear-gradient(to right,var(--blue-accent),transparent);
      margin:36px 0; opacity:0; animation:fadeUp .8s 1s ease forwards;
    }

    .steps { display:flex; flex-direction:column; gap:18px; opacity:0; animation:fadeUp .8s 1.1s ease forwards; }

    .step { display:flex; align-items:center; gap:14px; }
    .step-num {
      width:28px; height:28px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      background:rgba(77,130,255,.15); border:1px solid rgba(77,130,255,.3);
      font-size:11px; font-weight:500; color:var(--blue-accent);
    }
    .step-text { font-size:13px; color:var(--white-dim); font-weight:300; }

    /* ── Right Panel ── */
    .panel-right {
      display:flex; flex-direction:column;
      justify-content:flex-start; align-items:center;
      padding:60px 70px;
      overflow-y:auto;
    }

    .form-card { width:100%; max-width:420px; padding:20px 0 60px; }

    .form-header { margin-bottom:36px; opacity:0; animation:fadeUp .8s .5s ease forwards; }
    .form-eyebrow {
      font-size:11px; font-weight:500; letter-spacing:.25em; text-transform:uppercase;
      color:var(--blue-accent); margin-bottom:10px;
    }
    .form-title { font-family:var(--font-display); font-size:36px; font-weight:300; }

    /* Alert */
    .alert {
      padding:12px 16px; border-radius:6px; font-size:13px; margin-bottom:24px;
      display:none; align-items:center; gap:10px;
    }
    .alert.show { display:flex; }
    .alert-error   { background:rgba(255,107,107,.1); border:1px solid rgba(255,107,107,.3); color:var(--error); }
    .alert-success { background:rgba(107,255,184,.1); border:1px solid rgba(107,255,184,.3); color:var(--success); }

    /* Fields */
    .field-group { display:flex; flex-direction:column; gap:18px; margin-bottom:28px; opacity:0; animation:fadeUp .8s .7s ease forwards; }
    .row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .field { display:flex; flex-direction:column; gap:8px; }
    .field label { font-size:11px; font-weight:500; letter-spacing:.15em; text-transform:uppercase; color:var(--white-dim); }

    .field input {
      background:var(--white-ghost); border:1px solid var(--white-line); border-radius:8px;
      padding:13px 16px; font-family:var(--font-body); font-size:14px; color:var(--white);
      outline:none; transition:border-color .25s,background .25s,box-shadow .25s; width:100%;
    }
    .field input::placeholder { color:rgba(255,255,255,.2); }
    .field input:focus {
      border-color:var(--blue-accent); background:rgba(77,130,255,.08);
      box-shadow:0 0 0 3px rgba(77,130,255,.12);
    }
    .field input.error-field { border-color:var(--error); box-shadow:0 0 0 3px rgba(255,107,107,.1); }

    .field-hint { font-size:11px; color:var(--white-dim); margin-top:2px; }

    /* Password wrap */
    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:48px; }
    .pw-toggle {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; color:var(--white-dim);
      transition:color .2s; padding:0;
    }
    .pw-toggle:hover { color:var(--white); }

    /* Strength bar */
    .strength-bar { display:flex; gap:4px; margin-top:6px; }
    .strength-seg { height:3px; flex:1; border-radius:2px; background:var(--white-ghost); transition:background .3s; }
    .strength-label { font-size:11px; color:var(--white-dim); margin-top:4px; }

    /* Submit */
    .btn-submit {
      width:100%; padding:15px;
      background:linear-gradient(135deg,var(--navy-bright),var(--blue-glow));
      border:1px solid rgba(77,130,255,.3); border-radius:8px;
      font-family:var(--font-body); font-size:13px; font-weight:500;
      letter-spacing:.12em; text-transform:uppercase; color:var(--white);
      cursor:pointer; position:relative; overflow:hidden;
      transition:transform .2s,box-shadow .2s;
      opacity:0; animation:fadeUp .8s .9s ease forwards;
    }
    .btn-submit::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,.1),transparent); opacity:0; transition:opacity .3s; }
    .btn-submit:hover { transform:translateY(-1px); box-shadow:0 8px 30px rgba(43,92,230,.4); }
    .btn-submit:hover::before { opacity:1; }
    .btn-submit.loading { pointer-events:none; opacity:.7; }
    .btn-inner { display:flex; align-items:center; justify-content:center; gap:8px; }
    .spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; display:none; }
    .btn-submit.loading .spinner { display:block; }

    @keyframes spin { to { transform:rotate(360deg); } }

    .form-note {
      text-align:center; margin-top:24px; font-size:13px; color:var(--white-dim);
      opacity:0; animation:fadeUp .8s 1.1s ease forwards;
    }
    .form-note a { color:var(--blue-accent); text-decoration:none; transition:opacity .2s; }
    .form-note a:hover { opacity:.7; }

    @keyframes fadeUp {
      from { opacity:0; transform:translateY(18px); }
      to   { opacity:1; transform:translateY(0); }
    }

    @media (max-width:768px) {
      .page { grid-template-columns:1fr; }
      .panel-left { display:none; }
      .panel-right { padding:40px 24px; }
      .row-2 { grid-template-columns:1fr; }
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

  <!-- LEFT PANEL -->
  <div class="panel-left">
    <div class="brand-tag">iAcademy · Est. 2002</div>

    <div class="logo-wrap">
      <svg class="logo-hex" viewBox="0 0 120 138" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M60 4L112 32V88L60 116L8 88V32L60 4Z" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.25)" stroke-width="1.5"/>
        <rect x="54" y="38" width="12" height="10" rx="2" fill="white" opacity="0.9"/>
        <path d="M60 54 L60 88" stroke="white" stroke-width="5" stroke-linecap="round"/>
        <path d="M42 88 L60 54 L78 88" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M50 76 L70 76" stroke="white" stroke-width="4" stroke-linecap="round"/>
      </svg>
    </div>

    <h1 class="hero-heading">Create<br>your <em>account</em></h1>
    <p class="hero-sub">Join the iAcademy Lost & Found portal to report missing items and track claims across campus.</p>
    <div class="decorative-line"></div>

    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-text">Register with your student email</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-text">Report lost or found items instantly</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text">Track claim status in real time</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="panel-right">
    <div class="form-card">

      <div class="form-header">
        <p class="form-eyebrow">New Account</p>
        <h2 class="form-title">Sign up<br>for free</h2>
      </div>

      <!-- PHP alerts -->
      <?php if ($error): ?>
      <div class="alert alert-error show">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success show">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke-width="2" stroke-linecap="round"/><polyline points="22 4 12 14.01 9 11.01" stroke-width="2" stroke-linecap="round"/></svg>
        <?= htmlspecialchars($success) ?>
        <a href="login.php" style="margin-left:auto;color:var(--success);font-weight:500;">Sign in →</a>
      </div>
      <?php endif; ?>

      <!-- JS alert -->
      <div class="alert alert-error" id="js-alert">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"/></svg>
        <span id="js-msg"></span>
      </div>

      <form method="POST" action="" id="reg-form" novalidate>

        <div class="field-group">

          <!-- Name row -->
          <div class="row-2">
            <div class="field">
              <label for="first_name">First Name <span style="color:var(--error)">*</span></label>
              <input type="text" id="first_name" name="first_name"
                     placeholder="Juan" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" />
            </div>
            <div class="field">
              <label for="last_name">Last Name <span style="color:var(--error)">*</span></label>
              <input type="text" id="last_name" name="last_name"
                     placeholder="Dela Cruz" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" />
            </div>
          </div>

          <!-- Student ID -->
          <div class="field">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id"
                   placeholder="e.g. 2021-00123" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" />
            <span class="field-hint">Optional — leave blank if you're staff</span>
          </div>

          <!-- Email -->
          <div class="field">
            <label for="email">Email Address <span style="color:var(--error)">*</span></label>
            <input type="email" id="email" name="email"
                   placeholder="you@iacademy.edu.ph" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
          </div>

          <!-- Password -->
          <div class="field">
            <label for="password">Password <span style="color:var(--error)">*</span></label>
            <div class="pw-wrap">
              <input type="password" id="password" name="password" placeholder="Min. 8 characters" />
              <button type="button" class="pw-toggle" id="pw1-toggle" aria-label="Toggle">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" id="eye1">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <!-- Strength bar -->
            <div class="strength-bar" id="strength-bar">
              <div class="strength-seg" id="s1"></div>
              <div class="strength-seg" id="s2"></div>
              <div class="strength-seg" id="s3"></div>
              <div class="strength-seg" id="s4"></div>
            </div>
            <div class="strength-label" id="strength-label"></div>
          </div>

          <!-- Confirm Password -->
          <div class="field">
            <label for="confirm">Confirm Password <span style="color:var(--error)">*</span></label>
            <div class="pw-wrap">
              <input type="password" id="confirm" name="confirm" placeholder="Re-enter password" />
              <button type="button" class="pw-toggle" id="pw2-toggle" aria-label="Toggle">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" id="eye2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>

        </div><!-- /field-group -->

        <button type="submit" class="btn-submit" id="btn-submit">
          <span class="btn-inner">
            <span class="spinner" id="spinner"></span>
            <span class="btn-text">Create Account</span>
          </span>
        </button>

      </form>

      <p class="form-note">Already have an account? <a href="login.php">Sign in here</a></p>

    </div>
  </div>

</div>

<script>
  // ── PW toggles ──
  const EYE_OPEN   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const EYE_CLOSED = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

  function setupToggle(inputId, btnId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    document.getElementById(btnId).addEventListener('click', () => {
      inp.type = inp.type === 'text' ? 'password' : 'text';
      ico.innerHTML = inp.type === 'text' ? EYE_CLOSED : EYE_OPEN;
    });
  }
  setupToggle('password','pw1-toggle','eye1');
  setupToggle('confirm', 'pw2-toggle','eye2');

  // ── Password strength ──
  const pwInput = document.getElementById('password');
  const segs    = [document.getElementById('s1'),document.getElementById('s2'),document.getElementById('s3'),document.getElementById('s4')];
  const lbl     = document.getElementById('strength-label');
  const levels  = [
    { color:'#ff6b6b', label:'Weak' },
    { color:'#ffa94d', label:'Fair' },
    { color:'#74c0fc', label:'Good' },
    { color:'#6bffb8', label:'Strong' },
  ];

  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    segs.forEach((s, i) => {
      s.style.background = i < score ? levels[score - 1].color : 'rgba(255,255,255,0.08)';
    });
    lbl.textContent = v.length ? levels[score - 1]?.label ?? '' : '';
    lbl.style.color = v.length ? levels[score - 1]?.color : 'var(--white-dim)';
  });

  // ── Validation ──
  const form    = document.getElementById('reg-form');
  const jsAlert = document.getElementById('js-alert');
  const jsMsg   = document.getElementById('js-msg');

  function showAlert(msg) { jsMsg.textContent = msg; jsAlert.classList.add('show'); }
  function clearAlert()   { jsAlert.classList.remove('show'); }

  form.addEventListener('submit', e => {
    clearAlert();
    const fn  = document.getElementById('first_name').value.trim();
    const ln  = document.getElementById('last_name').value.trim();
    const em  = document.getElementById('email').value.trim();
    const pw  = pwInput.value;
    const cf  = document.getElementById('confirm').value;
    const re  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!fn || !ln || !em || !pw || !cf) { e.preventDefault(); showAlert('Please fill in all required fields.'); return; }
    if (!re.test(em))                    { e.preventDefault(); showAlert('Enter a valid email address.'); return; }
    if (pw.length < 8)                   { e.preventDefault(); showAlert('Password must be at least 8 characters.'); return; }
    if (pw !== cf)                       { e.preventDefault(); showAlert('Passwords do not match.'); return; }

    document.getElementById('btn-submit').classList.add('loading');
    document.getElementById('spinner').style.display = 'block';
  });

  // Clear on type
  document.querySelectorAll('input').forEach(el => el.addEventListener('input', () => {
    el.classList.remove('error-field'); clearAlert();
  }));
</script>
</body>
</html>
