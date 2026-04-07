<?php
// ============================================
// iAcademy Lost & Found — Admin Dashboard
// admin/dashboard.php
// ============================================

require_once __DIR__ . '/../config/auth.php';
requireLogin('admin');

require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

// ── Stats ──
$stats = [];

$stats['total_items']    = $pdo->query("SELECT COUNT(*) FROM items WHERE is_archived = 0")->fetchColumn();
$stats['lost_items']     = $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_archived = 0")->fetchColumn();
$stats['found_items']    = $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_archived = 0")->fetchColumn();
$stats['claimed_items']  = $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_archived = 0")->fetchColumn();
$stats['pending_claims'] = $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'")->fetchColumn();
$stats['total_users']    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// ── Recent Items ──
$recentItems = $pdo->query(
    "SELECT i.*, c.name AS category_name, CONCAT(u.first_name,' ',u.last_name) AS reporter_name
     FROM items i
     JOIN categories c ON i.category_id = c.id
     JOIN users u ON i.reported_by = u.id
     WHERE i.is_archived = 0
     ORDER BY i.created_at DESC LIMIT 8"
)->fetchAll();

// ── Pending Claims ──
$pendingClaims = $pdo->query(
    "SELECT cl.*, i.item_name, i.reference_no,
            CONCAT(u.first_name,' ',u.last_name) AS claimant_name,
            u.email AS claimant_email
     FROM claims cl
     JOIN items i ON cl.item_id = i.id
     JOIN users u ON cl.claimant_id = u.id
     WHERE cl.status = 'pending'
     ORDER BY cl.created_at DESC LIMIT 10"
)->fetchAll();

// ── Recent Activity ──
$recentActivity = $pdo->query(
    "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS actor_name
     FROM activity_log al
     LEFT JOIN users u ON al.actor_id = u.id
     ORDER BY al.created_at DESC LIMIT 12"
)->fetchAll();

// ── Categories Summary ──
$categorySummary = $pdo->query(
    "SELECT c.name, c.icon,
            SUM(CASE WHEN i.status = 'lost'   THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN i.status = 'found'  THEN 1 ELSE 0 END) AS found_count,
            COUNT(i.id) AS total
     FROM categories c
     LEFT JOIN items i ON c.id = i.category_id AND i.is_archived = 0
     GROUP BY c.id, c.name, c.icon
     ORDER BY total DESC"
)->fetchAll();

$admin = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — iAcademy Lost & Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

  <style>
    /* ══════════════════════════════════════════
       CSS Variables — matching auth pages
    ══════════════════════════════════════════ */
    :root {
      --navy-deep:    #05063a;
      --navy-mid:     #0a0e6b;
      --navy-bright:  #1a24c8;
      --blue-glow:    #2b5ce6;
      --blue-accent:  #4d82ff;
      --white:        #ffffff;
      --white-soft:   rgba(255,255,255,0.85);
      --white-dim:    rgba(255,255,255,0.45);
      --white-ghost:  rgba(255,255,255,0.06);
      --white-line:   rgba(255,255,255,0.10);
      --error:        #ff6b6b;
      --success:      #6bffb8;
      --warning:      #ffd166;
      --info:         #74c0fc;

      --sidebar-w:    260px;
      --header-h:     64px;

      --font-display: 'Cormorant Garamond', serif;
      --font-body:    'DM Sans', sans-serif;

      --radius-sm:    6px;
      --radius-md:    10px;
      --radius-lg:    14px;

      --transition:   0.22s ease;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-family: var(--font-body);
      background: var(--navy-deep);
      color: var(--white);
      font-size: 14px;
      line-height: 1.5;
    }

    /* ══════════════════════════════════════════
       Scrollbar
    ══════════════════════════════════════════ */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.22); }

    /* ══════════════════════════════════════════
       Background
    ══════════════════════════════════════════ */
    .bg-canvas {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 80% 60% at 20% 80%, rgba(26,36,200,0.45) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 20%, rgba(43,92,230,0.25) 0%, transparent 55%),
        radial-gradient(ellipse 100% 80% at 50% 50%, var(--navy-mid) 0%, var(--navy-deep) 70%);
    }

    .bg-grid {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
      background-size: 50px 50px;
      mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
    }

    /* ══════════════════════════════════════════
       Layout Shell
    ══════════════════════════════════════════ */
    .shell {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: var(--sidebar-w) 1fr;
      grid-template-rows: var(--header-h) 1fr;
      height: 100vh;
      overflow: hidden;
    }

    /* ══════════════════════════════════════════
       Sidebar
    ══════════════════════════════════════════ */
    .sidebar {
      grid-row: 1 / -1;
      background: rgba(5,6,58,0.7);
      backdrop-filter: blur(20px);
      border-right: 1px solid var(--white-line);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Logo area */
    .sidebar-brand {
      padding: 20px 24px 18px;
      border-bottom: 1px solid var(--white-line);
      display: flex;
      align-items: center;
      gap: 14px;
      flex-shrink: 0;
    }

    /* Real iAcademy logo — white SVG recreation matching the uploaded image */
    .brand-logo {
      width: 44px; height: 44px; flex-shrink: 0;
    }

    .brand-text { min-width: 0; }

    .brand-name {
      font-family: var(--font-display);
      font-size: 17px; font-weight: 600;
      letter-spacing: 0.05em;
      color: var(--white);
      line-height: 1;
    }

    .brand-sub {
      font-size: 10px; font-weight: 400;
      color: var(--white-dim);
      letter-spacing: 0.15em;
      text-transform: uppercase;
      margin-top: 3px;
    }

    /* Nav */
    .sidebar-nav {
      flex: 1;
      padding: 20px 12px;
      overflow-y: auto;
    }

    .nav-section-label {
      font-size: 9px; font-weight: 600;
      letter-spacing: 0.25em; text-transform: uppercase;
      color: rgba(255,255,255,0.25);
      padding: 0 12px;
      margin: 18px 0 8px;
    }

    .nav-section-label:first-child { margin-top: 0; }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: var(--radius-md);
      text-decoration: none;
      color: var(--white-dim);
      font-size: 13.5px; font-weight: 400;
      transition: all var(--transition);
      position: relative;
      cursor: pointer;
    }

    .nav-link:hover {
      background: var(--white-ghost);
      color: var(--white);
    }

    .nav-link.active {
      background: rgba(77,130,255,0.15);
      color: var(--blue-accent);
      font-weight: 500;
    }

    .nav-link.active::before {
      content: '';
      position: absolute; left: 0; top: 50%;
      transform: translateY(-50%);
      width: 3px; height: 60%;
      background: var(--blue-accent);
      border-radius: 0 2px 2px 0;
    }

    .nav-icon {
      width: 18px; height: 18px;
      flex-shrink: 0; opacity: 0.7;
    }
    .nav-link.active .nav-icon,
    .nav-link:hover .nav-icon { opacity: 1; }

    .nav-badge {
      margin-left: auto;
      background: var(--blue-glow);
      color: white;
      font-size: 10px; font-weight: 600;
      padding: 2px 7px;
      border-radius: 20px;
      min-width: 20px; text-align: center;
    }

    .nav-badge.warn { background: var(--warning); color: #333; }

    /* Sidebar footer */
    .sidebar-footer {
      padding: 16px 12px;
      border-top: 1px solid var(--white-line);
      flex-shrink: 0;
    }

    .admin-card {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 14px;
      background: var(--white-ghost);
      border-radius: var(--radius-md);
    }

    .admin-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: linear-gradient(135deg, var(--navy-bright), var(--blue-glow));
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 600; flex-shrink: 0;
      color: white;
    }

    .admin-info { min-width: 0; flex: 1; }
    .admin-name { font-size: 13px; font-weight: 500; color: var(--white); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .admin-role { font-size: 10px; color: var(--blue-accent); text-transform: uppercase; letter-spacing: 0.1em; }

    .logout-btn {
      background: none; border: none; cursor: pointer;
      color: var(--white-dim); padding: 4px;
      border-radius: 4px;
      transition: color var(--transition);
    }
    .logout-btn:hover { color: var(--error); }

    /* ══════════════════════════════════════════
       Header
    ══════════════════════════════════════════ */
    .topbar {
      grid-column: 2;
      display: flex; align-items: center; gap: 16px;
      padding: 0 32px;
      background: rgba(5,6,58,0.5);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--white-line);
    }

    .page-title {
      flex: 1;
    }
    .page-title h1 {
      font-family: var(--font-display);
      font-size: 22px; font-weight: 400;
      color: var(--white);
      line-height: 1;
    }
    .page-title p {
      font-size: 11px; color: var(--white-dim);
      margin-top: 3px;
    }

    .topbar-actions { display: flex; align-items: center; gap: 12px; }

    .icon-btn {
      width: 36px; height: 36px; border-radius: var(--radius-sm);
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; color: var(--white-dim);
      transition: all var(--transition);
      position: relative; text-decoration: none;
    }
    .icon-btn:hover { background: rgba(77,130,255,0.12); color: var(--white); border-color: rgba(77,130,255,0.3); }

    .notif-dot {
      position: absolute; top: 6px; right: 6px;
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--error);
      border: 1.5px solid var(--navy-deep);
    }

    .date-chip {
      font-size: 11px; color: var(--white-dim);
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      padding: 6px 14px; border-radius: 20px;
    }

    /* ══════════════════════════════════════════
       Main Content
    ══════════════════════════════════════════ */
    .main {
      grid-column: 2;
      overflow-y: auto;
      padding: 28px 32px 40px;
    }

    /* ══════════════════════════════════════════
       Stat Cards
    ══════════════════════════════════════════ */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      border-radius: var(--radius-lg);
      padding: 22px 24px;
      display: flex;
      align-items: flex-start;
      gap: 16px;
      position: relative;
      overflow: hidden;
      transition: transform var(--transition), border-color var(--transition);
      animation: fadeUp 0.6s ease forwards;
      opacity: 0;
    }

    .stat-card:hover { transform: translateY(-2px); border-color: rgba(77,130,255,0.3); }

    .stat-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--blue-accent), transparent);
      opacity: 0.4;
    }

    .stat-icon-wrap {
      width: 44px; height: 44px; border-radius: var(--radius-md);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .si-blue   { background: rgba(77,130,255,0.15); color: var(--blue-accent); }
    .si-red    { background: rgba(255,107,107,0.15); color: var(--error); }
    .si-green  { background: rgba(107,255,184,0.15); color: var(--success); }
    .si-yellow { background: rgba(255,209,102,0.15); color: var(--warning); }
    .si-info   { background: rgba(116,192,252,0.15); color: var(--info); }
    .si-purple { background: rgba(180,130,255,0.15); color: #b482ff; }

    .stat-content { flex: 1; min-width: 0; }

    .stat-number {
      font-family: var(--font-display);
      font-size: 38px; font-weight: 600;
      line-height: 1; color: var(--white);
    }

    .stat-label {
      font-size: 11px; font-weight: 400;
      color: var(--white-dim);
      text-transform: uppercase; letter-spacing: 0.1em;
      margin-top: 4px;
    }

    .stat-delta {
      font-size: 11px; color: var(--white-dim);
      margin-top: 8px;
    }
    .stat-delta.up   { color: var(--success); }
    .stat-delta.warn { color: var(--warning); }

    /* Stagger animations */
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.10s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.20s; }
    .stat-card:nth-child(5) { animation-delay: 0.25s; }
    .stat-card:nth-child(6) { animation-delay: 0.30s; }

    /* ══════════════════════════════════════════
       Two-column content grid
    ══════════════════════════════════════════ */
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 20px;
      margin-bottom: 20px;
    }

    .content-grid-3 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    /* ══════════════════════════════════════════
       Panel
    ══════════════════════════════════════════ */
    .panel {
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      border-radius: var(--radius-lg);
      overflow: hidden;
      animation: fadeUp 0.6s 0.2s ease forwards;
      opacity: 0;
    }

    .panel-head {
      display: flex; align-items: center; gap: 12px;
      padding: 18px 22px;
      border-bottom: 1px solid var(--white-line);
    }

    .panel-head h2 {
      font-family: var(--font-display);
      font-size: 18px; font-weight: 400;
      color: var(--white); flex: 1;
    }

    .panel-head-badge {
      font-size: 11px; font-weight: 500;
      padding: 3px 10px; border-radius: 20px;
      background: rgba(77,130,255,0.15);
      color: var(--blue-accent);
    }

    .panel-head-badge.warn { background: rgba(255,209,102,0.15); color: var(--warning); }

    .view-all {
      font-size: 12px; color: var(--blue-accent);
      text-decoration: none; font-weight: 500;
      transition: opacity var(--transition);
    }
    .view-all:hover { opacity: 0.7; }

    /* ══════════════════════════════════════════
       Table
    ══════════════════════════════════════════ */
    .table-wrap { overflow-x: auto; }

    table {
      width: 100%; border-collapse: collapse;
    }

    thead th {
      font-size: 10px; font-weight: 600;
      letter-spacing: 0.15em; text-transform: uppercase;
      color: var(--white-dim);
      padding: 12px 20px;
      text-align: left;
      border-bottom: 1px solid var(--white-line);
      white-space: nowrap;
    }

    tbody td {
      padding: 13px 20px;
      font-size: 13px;
      color: var(--white-soft);
      border-bottom: 1px solid rgba(255,255,255,0.04);
      vertical-align: middle;
    }

    tbody tr:last-child td { border-bottom: none; }

    tbody tr {
      transition: background var(--transition);
    }
    tbody tr:hover { background: rgba(255,255,255,0.025); }

    .ref-no {
      font-family: 'Courier New', monospace;
      font-size: 11px; color: var(--blue-accent);
      font-weight: 600;
    }

    /* Status badge */
    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 500; white-space: nowrap;
    }
    .badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }

    .badge-lost     { background: rgba(255,107,107,0.12); color: #ff8888; }
    .badge-lost::before { background: #ff6b6b; }

    .badge-found    { background: rgba(116,192,252,0.12); color: #90d0ff; }
    .badge-found::before { background: var(--info); }

    .badge-claimed  { background: rgba(107,255,184,0.12); color: #7dffc0; }
    .badge-claimed::before { background: var(--success); }

    .badge-turned_in { background: rgba(180,130,255,0.12); color: #c89fff; }
    .badge-turned_in::before { background: #b482ff; }

    .badge-disposed { background: rgba(255,255,255,0.06); color: var(--white-dim); }
    .badge-disposed::before { background: rgba(255,255,255,0.3); }

    .badge-pending  { background: rgba(255,209,102,0.12); color: #ffe08a; }
    .badge-pending::before { background: var(--warning); }

    .badge-approved { background: rgba(107,255,184,0.12); color: #7dffc0; }
    .badge-approved::before { background: var(--success); }

    .badge-rejected { background: rgba(255,107,107,0.12); color: #ff8888; }
    .badge-rejected::before { background: var(--error); }

    /* Action buttons in table */
    .tbl-actions { display: flex; gap: 8px; }

    .btn-tbl {
      padding: 5px 12px; border-radius: var(--radius-sm);
      font-size: 11px; font-weight: 500;
      cursor: pointer; border: none;
      transition: all var(--transition);
      text-decoration: none; display: inline-block;
    }

    .btn-approve { background: rgba(107,255,184,0.15); color: var(--success); }
    .btn-approve:hover { background: rgba(107,255,184,0.3); }

    .btn-reject { background: rgba(255,107,107,0.12); color: var(--error); }
    .btn-reject:hover { background: rgba(255,107,107,0.25); }

    .btn-view { background: rgba(77,130,255,0.12); color: var(--blue-accent); }
    .btn-view:hover { background: rgba(77,130,255,0.25); }

    /* Empty state */
    .empty-state {
      padding: 48px 20px;
      text-align: center;
      color: var(--white-dim);
    }
    .empty-state svg { opacity: 0.25; margin-bottom: 12px; }
    .empty-state p { font-size: 13px; }

    /* ══════════════════════════════════════════
       Activity Feed
    ══════════════════════════════════════════ */
    .activity-feed { padding: 8px 0; }

    .activity-item {
      display: flex; gap: 14px;
      padding: 12px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.04);
      transition: background var(--transition);
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-item:hover { background: rgba(255,255,255,0.02); }

    .activity-dot {
      width: 8px; height: 8px; border-radius: 50%;
      margin-top: 6px; flex-shrink: 0;
    }
    .dot-login    { background: var(--info); }
    .dot-logout   { background: var(--white-dim); }
    .dot-register { background: var(--success); }
    .dot-claim    { background: var(--warning); }
    .dot-default  { background: var(--blue-accent); }

    .activity-content { flex: 1; min-width: 0; }
    .activity-action { font-size: 12.5px; color: var(--white-soft); }
    .activity-action strong { color: var(--white); font-weight: 500; }
    .activity-time { font-size: 10.5px; color: var(--white-dim); margin-top: 2px; }

    /* ══════════════════════════════════════════
       Category list
    ══════════════════════════════════════════ */
    .cat-list { padding: 6px 0; }

    .cat-row {
      display: flex; align-items: center; gap: 14px;
      padding: 12px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .cat-row:last-child { border-bottom: none; }

    .cat-icon {
      width: 32px; height: 32px; border-radius: var(--radius-sm);
      background: rgba(77,130,255,0.12);
      color: var(--blue-accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; flex-shrink: 0;
    }

    .cat-name { flex: 1; font-size: 13px; color: var(--white-soft); }

    .cat-pills { display: flex; gap: 6px; }

    .cat-pill {
      font-size: 10px; padding: 2px 8px;
      border-radius: 20px; font-weight: 500;
    }
    .pill-lost  { background: rgba(255,107,107,0.12); color: #ff8888; }
    .pill-found { background: rgba(116,192,252,0.12); color: #90d0ff; }

    /* ══════════════════════════════════════════
       Quick Actions bar
    ══════════════════════════════════════════ */
    .quick-actions {
      display: flex; gap: 12px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .qa-btn {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 10px 20px;
      border-radius: var(--radius-md);
      font-family: var(--font-body);
      font-size: 12.5px; font-weight: 500;
      cursor: pointer; border: none;
      text-decoration: none;
      letter-spacing: 0.04em;
      transition: all var(--transition);
      animation: fadeUp 0.6s 0.1s ease forwards;
      opacity: 0;
    }

    .qa-primary {
      background: linear-gradient(135deg, var(--navy-bright), var(--blue-glow));
      color: white;
      border: 1px solid rgba(77,130,255,0.3);
    }
    .qa-primary:hover { box-shadow: 0 6px 24px rgba(43,92,230,0.4); transform: translateY(-1px); }

    .qa-ghost {
      background: var(--white-ghost);
      color: var(--white-soft);
      border: 1px solid var(--white-line);
    }
    .qa-ghost:hover { background: rgba(77,130,255,0.1); color: var(--white); border-color: rgba(77,130,255,0.3); }

    /* ══════════════════════════════════════════
       Modal
    ══════════════════════════════════════════ */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 100;
      background: rgba(5,6,58,0.85);
      backdrop-filter: blur(8px);
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.25s ease;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }

    .modal {
      background: #0c0e52;
      border: 1px solid var(--white-line);
      border-radius: var(--radius-lg);
      width: 100%; max-width: 520px;
      overflow: hidden;
      transform: translateY(16px);
      transition: transform 0.25s ease;
    }
    .modal-overlay.open .modal { transform: translateY(0); }

    .modal-head {
      display: flex; align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid var(--white-line);
      gap: 12px;
    }
    .modal-head h3 { flex: 1; font-family: var(--font-display); font-size: 20px; font-weight: 400; }
    .modal-close {
      background: none; border: none; cursor: pointer;
      color: var(--white-dim); padding: 4px;
      transition: color var(--transition);
    }
    .modal-close:hover { color: var(--error); }

    .modal-body { padding: 24px; }

    .modal-field { margin-bottom: 18px; }
    .modal-field label {
      display: block; font-size: 10.5px; font-weight: 500;
      letter-spacing: 0.15em; text-transform: uppercase;
      color: var(--white-dim); margin-bottom: 8px;
    }
    .modal-field input,
    .modal-field select,
    .modal-field textarea {
      width: 100%;
      background: var(--white-ghost);
      border: 1px solid var(--white-line);
      border-radius: var(--radius-sm);
      padding: 11px 14px;
      font-family: var(--font-body);
      font-size: 13px; color: var(--white);
      outline: none;
      transition: border-color var(--transition), box-shadow var(--transition);
    }
    .modal-field input:focus,
    .modal-field select:focus,
    .modal-field textarea:focus {
      border-color: var(--blue-accent);
      box-shadow: 0 0 0 3px rgba(77,130,255,0.12);
    }
    .modal-field select option { background: #0c0e52; color: white; }
    .modal-field textarea { resize: vertical; min-height: 80px; }

    .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    .modal-foot {
      display: flex; gap: 10px; justify-content: flex-end;
      padding: 16px 24px;
      border-top: 1px solid var(--white-line);
    }

    .btn-modal-cancel {
      padding: 9px 20px; border-radius: var(--radius-sm);
      background: var(--white-ghost); border: 1px solid var(--white-line);
      color: var(--white-dim); font-family: var(--font-body);
      font-size: 12.5px; cursor: pointer;
      transition: all var(--transition);
    }
    .btn-modal-cancel:hover { color: var(--white); background: rgba(255,255,255,0.1); }

    .btn-modal-submit {
      padding: 9px 24px; border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--navy-bright), var(--blue-glow));
      border: 1px solid rgba(77,130,255,0.3);
      color: white; font-family: var(--font-body);
      font-size: 12.5px; font-weight: 500; cursor: pointer;
      transition: all var(--transition);
    }
    .btn-modal-submit:hover { box-shadow: 0 4px 18px rgba(43,92,230,0.4); }

    /* ══════════════════════════════════════════
       Review modal specifics
    ══════════════════════════════════════════ */
    .review-info {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--white-line);
      border-radius: var(--radius-md);
      padding: 16px;
      margin-bottom: 18px;
    }
    .review-info-row {
      display: flex; gap: 8px;
      font-size: 12.5px;
      margin-bottom: 8px;
    }
    .review-info-row:last-child { margin-bottom: 0; }
    .review-info-row .lbl { color: var(--white-dim); min-width: 120px; }
    .review-info-row .val { color: var(--white); }

    .review-actions { display: flex; gap: 10px; }
    .btn-full-approve {
      flex: 1; padding: 11px;
      background: rgba(107,255,184,0.15);
      border: 1px solid rgba(107,255,184,0.3);
      border-radius: var(--radius-sm);
      color: var(--success); font-family: var(--font-body);
      font-size: 13px; font-weight: 500; cursor: pointer;
      transition: all var(--transition);
    }
    .btn-full-approve:hover { background: rgba(107,255,184,0.25); }
    .btn-full-reject {
      flex: 1; padding: 11px;
      background: rgba(255,107,107,0.12);
      border: 1px solid rgba(255,107,107,0.3);
      border-radius: var(--radius-sm);
      color: var(--error); font-family: var(--font-body);
      font-size: 13px; font-weight: 500; cursor: pointer;
      transition: all var(--transition);
    }
    .btn-full-reject:hover { background: rgba(255,107,107,0.22); }

    /* ══════════════════════════════════════════
       Animations
    ══════════════════════════════════════════ */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ══════════════════════════════════════════
       Toast
    ══════════════════════════════════════════ */
    .toast-container {
      position: fixed; bottom: 28px; right: 28px;
      z-index: 200; display: flex; flex-direction: column; gap: 10px;
    }
    .toast {
      padding: 12px 20px;
      border-radius: var(--radius-md);
      font-size: 13px; font-weight: 400;
      display: flex; align-items: center; gap: 10px;
      min-width: 260px;
      animation: slideIn 0.3s ease forwards;
      cursor: pointer;
    }
    .toast-success { background: rgba(107,255,184,0.15); border: 1px solid rgba(107,255,184,0.3); color: var(--success); }
    .toast-error   { background: rgba(255,107,107,0.15); border: 1px solid rgba(255,107,107,0.3); color: var(--error); }
    @keyframes slideIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }

    /* ══════════════════════════════════════════
       Responsive
    ══════════════════════════════════════════ */
    @media (max-width: 1100px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .content-grid { grid-template-columns: 1fr; }
      .content-grid-3 { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
      :root { --sidebar-w: 0px; }
      .sidebar { display: none; }
      .shell { grid-template-columns: 1fr; }
      .topbar { grid-column: 1; }
      .main { grid-column: 1; padding: 20px 18px 32px; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="bg-grid"></div>

<!-- ══ TOAST CONTAINER ══ -->
<div class="toast-container" id="toastContainer"></div>

<!-- ══ SHELL ══ -->
<div class="shell">

  <!-- ════════════ SIDEBAR ════════════ -->
  <aside class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
      <!-- Real iAcademy logo (SVG replica matching uploaded image) -->
      <svg class="brand-logo" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Hexagon background shape -->
        <path d="M40 4 L72 22 L72 58 L40 76 L8 58 L8 22 Z"
              fill="rgba(255,255,255,0.08)"
              stroke="rgba(255,255,255,0.35)" stroke-width="1.5"/>
        <!-- Inner hexagon inset -->
        <path d="M40 12 L64 26 L64 54 L40 68 L16 54 L16 26 Z"
              fill="white" opacity="0.92"/>
        <!-- iA mark — dark on white -->
        <!-- "i" dot -->
        <rect x="35" y="24" width="10" height="7" rx="1.5" fill="#05063a"/>
        <!-- Vertical stem of i/A combined -->
        <path d="M40 36 L40 56" stroke="#05063a" stroke-width="4.5" stroke-linecap="round"/>
        <!-- A legs -->
        <path d="M27 56 L40 36 L53 56" stroke="#05063a" stroke-width="4.5"
              stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <!-- A crossbar -->
        <path d="M33 49 L47 49" stroke="#05063a" stroke-width="3.5" stroke-linecap="round"/>
      </svg>

      <div class="brand-text">
        <div class="brand-name">iACADEMY</div>
        <div class="brand-sub">Lost &amp; Found · Admin</div>
      </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>

      <a href="dashboard.php" class="nav-link active">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Dashboard
      </a>

      <a href="notifications.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        Notifications
        <?php if ($stats['pending_claims'] > 0): ?>
        <span class="nav-badge warn"><?= $stats['pending_claims'] ?></span>
        <?php endif; ?>
      </a>

      <div class="nav-section-label">Items</div>

      <a href="items.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
        All Items
        <span class="nav-badge"><?= $stats['total_items'] ?></span>
      </a>

      <a href="items.php?status=lost" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        Lost Items
        <span class="nav-badge warn"><?= $stats['lost_items'] ?></span>
      </a>

      <a href="items.php?status=found" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        Found Items
      </a>

      <a href="post-item.php" class="nav-link" onclick="openPostModal(); return false;">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Post New Item
      </a>

      <div class="nav-section-label">Claims</div>

      <a href="claims.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        Verification Requests
        <?php if ($stats['pending_claims'] > 0): ?>
        <span class="nav-badge warn"><?= $stats['pending_claims'] ?></span>
        <?php endif; ?>
      </a>

      <a href="meetups.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Meet-Up Requests
      </a>

      <div class="nav-section-label">Management</div>

      <a href="users.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Users
        <span class="nav-badge"><?= $stats['total_users'] ?></span>
      </a>

      <a href="activity-log.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        Activity Log
      </a>

      <a href="archive.php" class="nav-link">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>
          <line x1="10" y1="12" x2="14" y2="12"/>
        </svg>
        Archive
      </a>
    </nav>

    <!-- User footer -->
    <div class="sidebar-footer">
      <div class="admin-card">
        <div class="admin-avatar">
          <?= strtoupper(substr($admin['first_name'],0,1) . substr($admin['last_name'],0,1)) ?>
        </div>
        <div class="admin-info">
          <div class="admin-name"><?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?></div>
          <div class="admin-role">Administrator</div>
        </div>
        <a href="../auth/logout.php" class="logout-btn" title="Logout">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- ════════════ TOPBAR ════════════ -->
  <header class="topbar">
    <div class="page-title">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars($admin['first_name']) ?> — <?= date('l, F j, Y') ?></p>
    </div>
    <div class="topbar-actions">
      <div class="date-chip"><?= date('h:i A') ?></div>
      <a href="notifications.php" class="icon-btn" title="Notifications">
        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($stats['pending_claims'] > 0): ?>
        <span class="notif-dot"></span>
        <?php endif; ?>
      </a>
      <a href="settings.php" class="icon-btn" title="Settings">
        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
      </a>
    </div>
  </header>

  <!-- ════════════ MAIN ════════════ -->
  <main class="main">

    <!-- Quick Actions -->
    <div class="quick-actions">
      <button class="qa-btn qa-primary" onclick="openPostModal()">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Post Found Item
      </button>
      <a href="claims.php" class="qa-btn qa-ghost">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
        </svg>
        Review Claims
        <?php if ($stats['pending_claims'] > 0): ?>
        <span style="background:rgba(255,209,102,0.2);color:var(--warning);padding:2px 8px;border-radius:20px;font-size:10px;">
          <?= $stats['pending_claims'] ?> pending
        </span>
        <?php endif; ?>
      </a>
      <a href="meetups.php" class="qa-btn qa-ghost">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Meet-Up Requests
      </a>
      <a href="users.php" class="qa-btn qa-ghost">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Manage Users
      </a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap si-blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['total_items'] ?></div>
          <div class="stat-label">Total Items</div>
          <div class="stat-delta">Active in system</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap si-red">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['lost_items'] ?></div>
          <div class="stat-label">Lost Items</div>
          <div class="stat-delta warn">Awaiting match</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap si-info">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['found_items'] ?></div>
          <div class="stat-label">Found Items</div>
          <div class="stat-delta">Turned in / posted</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap si-green">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['claimed_items'] ?></div>
          <div class="stat-label">Claimed</div>
          <div class="stat-delta up">Successfully returned</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap si-yellow">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['pending_claims'] ?></div>
          <div class="stat-label">Pending Claims</div>
          <div class="stat-delta warn">Needs review</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap si-purple">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number"><?= $stats['total_users'] ?></div>
          <div class="stat-label">Registered Users</div>
          <div class="stat-delta">Students &amp; staff</div>
        </div>
      </div>
    </div>

    <!-- Main two-col grid -->
    <div class="content-grid">

      <!-- Recent Items Table -->
      <div class="panel">
        <div class="panel-head">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color:var(--blue-accent)">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
          <h2>Recent Items</h2>
          <span class="panel-head-badge"><?= count($recentItems) ?> latest</span>
          <a href="items.php" class="view-all">View all →</a>
        </div>
        <div class="table-wrap">
          <?php if (empty($recentItems)): ?>
          <div class="empty-state">
            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8"/></svg>
            <p>No items posted yet.</p>
          </div>
          <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Ref No.</th>
                <th>Item</th>
                <th>Category</th>
                <th>Status</th>
                <th>Reported</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentItems as $item): ?>
              <tr>
                <td><span class="ref-no"><?= htmlspecialchars($item['reference_no']) ?></span></td>
                <td style="font-weight:500;color:var(--white);"><?= htmlspecialchars($item['item_name']) ?></td>
                <td style="color:var(--white-dim);"><?= htmlspecialchars($item['category_name']) ?></td>
                <td>
                  <span class="badge badge-<?= $item['status'] ?>">
                    <?= ucfirst(str_replace('_',' ', $item['status'])) ?>
                  </span>
                </td>
                <td style="color:var(--white-dim);"><?= date('M d', strtotime($item['date_reported'])) ?></td>
                <td>
                  <div class="tbl-actions">
                    <a href="item-detail.php?id=<?= $item['id'] ?>" class="btn-tbl btn-view">View</a>
                    <a href="item-edit.php?id=<?= $item['id'] ?>" class="btn-tbl btn-tbl" style="background:var(--white-ghost);color:var(--white-dim);">Edit</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right column: Activity + Categories -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Pending Claims -->
        <div class="panel">
          <div class="panel-head">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color:var(--warning)">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            <h2>Pending Claims</h2>
            <?php if ($stats['pending_claims'] > 0): ?>
            <span class="panel-head-badge warn"><?= $stats['pending_claims'] ?> pending</span>
            <?php endif; ?>
            <a href="claims.php" class="view-all">Review all →</a>
          </div>

          <?php if (empty($pendingClaims)): ?>
          <div class="empty-state">
            <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p>No pending claims — all clear!</p>
          </div>
          <?php else: ?>
          <?php foreach (array_slice($pendingClaims, 0, 4) as $claim): ?>
          <div class="activity-item" style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <div class="activity-dot dot-claim" style="margin-top:4px;"></div>
            <div class="activity-content" style="flex:1;">
              <div class="activity-action">
                <strong><?= htmlspecialchars($claim['claimant_name']) ?></strong>
                claims <em style="color:var(--blue-accent);"><?= htmlspecialchars($claim['item_name']) ?></em>
              </div>
              <div class="activity-time"><?= htmlspecialchars($claim['reference_no']) ?> · <?= date('M d, g:i A', strtotime($claim['created_at'])) ?></div>
            </div>
            <div class="tbl-actions" style="align-self:center;">
              <button class="btn-tbl btn-approve"
                onclick="openReviewModal(<?= $claim['id'] ?>, '<?= htmlspecialchars(addslashes($claim['item_name'])) ?>', '<?= htmlspecialchars(addslashes($claim['claimant_name'])) ?>', '<?= htmlspecialchars(addslashes($claim['claimant_email'])) ?>')">
                Review
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Category Summary -->
        <div class="panel">
          <div class="panel-head">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color:var(--blue-accent)">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
              <line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            <h2>By Category</h2>
          </div>
          <div class="cat-list">
            <?php foreach ($categorySummary as $cat): ?>
            <?php if ($cat['total'] == 0) continue; ?>
            <div class="cat-row">
              <div class="cat-icon">
                <?php
                // Simple icon mapping from category name
                $icons = ['Electronics'=>'💻','ID / Cards'=>'🪪','Clothing'=>'👕',
                          'Books / Notes'=>'📚','Bags'=>'🎒','Keys'=>'🔑',
                          'Accessories'=>'⌚','Others'=>'📦'];
                echo $icons[$cat['name']] ?? '📦';
                ?>
              </div>
              <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
              <div class="cat-pills">
                <?php if ($cat['lost_count'] > 0): ?>
                <span class="cat-pill pill-lost"><?= $cat['lost_count'] ?> lost</span>
                <?php endif; ?>
                <?php if ($cat['found_count'] > 0): ?>
                <span class="cat-pill pill-found"><?= $cat['found_count'] ?> found</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (array_sum(array_column($categorySummary,'total')) === 0): ?>
            <div class="empty-state"><p>No items to categorize yet.</p></div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div><!-- /content-grid -->

    <!-- Activity Log -->
    <div class="panel" style="animation-delay:0.35s;">
      <div class="panel-head">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color:var(--blue-accent)">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        <h2>Recent Activity</h2>
        <a href="activity-log.php" class="view-all">Full log →</a>
      </div>
      <div class="activity-feed" style="columns:2;gap:0;">
        <?php if (empty($recentActivity)): ?>
        <div class="empty-state"><p>No activity recorded yet.</p></div>
        <?php else: ?>
        <?php foreach ($recentActivity as $log): ?>
        <?php
          $dotClass = 'dot-default';
          if (str_contains($log['action'],'login'))    $dotClass = 'dot-login';
          if (str_contains($log['action'],'logout'))   $dotClass = 'dot-logout';
          if (str_contains($log['action'],'register')) $dotClass = 'dot-register';
          if (str_contains($log['action'],'claim'))    $dotClass = 'dot-claim';
          $actionLabel = str_replace(['user.','item.','claim.'], '', $log['action']);
          $actionLabel = ucwords(str_replace('.', ' ', $actionLabel));
        ?>
        <div class="activity-item">
          <div class="activity-dot <?= $dotClass ?>"></div>
          <div class="activity-content">
            <div class="activity-action">
              <strong><?= htmlspecialchars($log['actor_name'] ?? 'System') ?></strong>
              — <?= htmlspecialchars($actionLabel) ?>
            </div>
            <div class="activity-time">
              <?= date('M d, g:i A', strtotime($log['created_at'])) ?>
              <?php if ($log['ip_address']): ?>· <span style="opacity:.5;"><?= htmlspecialchars($log['ip_address']) ?></span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main><!-- /main -->

</div><!-- /shell -->


<!-- ══════════════════════════════════════════
     MODAL: Post New Item
══════════════════════════════════════════ -->
<div class="modal-overlay" id="postModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Post Found Item</h3>
      <button class="modal-close" onclick="closePostModal()">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form method="POST" action="item-create.php" enctype="multipart/form-data">
      <div class="modal-body">

        <div class="modal-row">
          <div class="modal-field">
            <label>Item Name *</label>
            <input type="text" name="item_name" placeholder="e.g. Black Laptop" required>
          </div>
          <div class="modal-field">
            <label>Category *</label>
            <select name="category_id" required>
              <option value="">Select…</option>
              <?php foreach ($pdo->query("SELECT id, name FROM categories ORDER BY name") as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="modal-field">
          <label>Description</label>
          <textarea name="description" placeholder="Brand, color, unique identifiers, contents…"></textarea>
        </div>

        <div class="modal-row">
          <div class="modal-field">
            <label>Date Found *</label>
            <input type="date" name="date_found" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="modal-field">
            <label>Status</label>
            <select name="status">
              <option value="found">Found — Turned In</option>
              <option value="lost">Lost — Reported</option>
              <option value="turned_in">Turned In to Office</option>
            </select>
          </div>
        </div>

        <div class="modal-field">
          <label>Location Found</label>
          <input type="text" name="location_found" placeholder="e.g. Library, Room 301, Canteen">
        </div>

        <!-- Member-visible (limited) vs full admin -->
        <div class="modal-field">
          <label>Item Image</label>
          <input type="file" name="item_image" accept="image/*"
                 style="padding:8px;font-size:12px;color:var(--white-dim);">
        </div>

      </div>
      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closePostModal()">Cancel</button>
        <button type="submit" class="btn-modal-submit">Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: Review Claim
══════════════════════════════════════════ -->
<div class="modal-overlay" id="reviewModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Review Claim</h3>
      <button class="modal-close" onclick="closeReviewModal()">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="review-info" id="reviewInfo"></div>

      <form method="POST" action="claim-review.php" id="reviewForm">
        <input type="hidden" name="claim_id" id="reviewClaimId">

        <div class="modal-field">
          <label>Admin Note (optional)</label>
          <textarea name="admin_note" placeholder="Add a note for the claimant…"></textarea>
        </div>

        <div class="review-actions">
          <button type="submit" name="decision" value="approved" class="btn-full-approve">
            ✓ Approve Claim
          </button>
          <button type="submit" name="decision" value="rejected" class="btn-full-reject">
            ✗ Reject Claim
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
  // ══ Modal helpers ══
  function openPostModal() {
    document.getElementById('postModal').classList.add('open');
  }
  function closePostModal() {
    document.getElementById('postModal').classList.remove('open');
  }

  function openReviewModal(claimId, itemName, claimantName, claimantEmail) {
    document.getElementById('reviewClaimId').value = claimId;
    document.getElementById('reviewInfo').innerHTML = `
      <div class="review-info-row"><span class="lbl">Item</span><span class="val">${itemName}</span></div>
      <div class="review-info-row"><span class="lbl">Claimant</span><span class="val">${claimantName}</span></div>
      <div class="review-info-row"><span class="lbl">Email</span><span class="val">${claimantEmail}</span></div>
      <div class="review-info-row"><span class="lbl">Claim ID</span><span class="val" style="font-family:monospace;color:var(--blue-accent);">#${claimId}</span></div>
    `;
    document.getElementById('reviewModal').classList.add('open');
  }
  function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('open');
  }

  // Close on backdrop click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // ══ Toast notifications ══
  function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        ${type === 'success'
          ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
          : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>'}
      </svg>
      ${message}
    `;
    toast.onclick = () => toast.remove();
    container.appendChild(toast);
    setTimeout(() => toast.style.opacity = '0', 3500);
    setTimeout(() => toast.remove(), 3800);
  }

  // ══ PHP flash message as toast ══
  <?php if (isset($_GET['success'])): ?>
  showToast(<?= json_encode(htmlspecialchars($_GET['success'])) ?>);
  <?php elseif (isset($_GET['error'])): ?>
  showToast(<?= json_encode(htmlspecialchars($_GET['error'])) ?>, 'error');
  <?php endif; ?>

  // ══ Keyboard shortcut: N = new item, Esc = close modals ══
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
    if (e.key === 'n' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
      openPostModal();
    }
  });
</script>

</body>
</html>
