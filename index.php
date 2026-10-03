<?php
declare(strict_types=1);
session_start();

/* ============================================================
   HoodFi — Liquidity Protocol on Robinhood Chain (Mainnet)
   Single-file app. No SQL — settings live in settings.json,
   logo lives as logo.<ext> next to this file.
   ============================================================ */
const ADMIN_PASSWORD = 'Mithai@555';
const SETTINGS_FILE  = __DIR__ . '/settings.json';

$DEFAULTS = [
    'site_name'      => 'HoodFi',
    'tg_url'         => '',
    'x_url'          => '',
    'theme'          => 1,
    'logo'           => '',
    'contract_4663'  => '0x0E1d1dD4bE9e4335Bc8419D4874c27eac129DCc1',
    'contract_46630' => '',
];

function hf_load(array $d): array {
    if (is_file(SETTINGS_FILE)) {
        $j = json_decode((string)file_get_contents(SETTINGS_FILE), true);
        if (is_array($j)) { return array_merge($d, $j); }
    }
    return $d;
}
function hf_save(array $s): void {
    file_put_contents(SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
function hf_social(string $u, string $base): string {
    $u = trim($u);
    if ($u === '') { return ''; }
    if ($u[0] === '@') { $u = $base . substr($u, 1); }
    elseif (!preg_match('#^https?://#i', $u)) { $u = 'https://' . $u; }
    return filter_var($u, FILTER_VALIDATE_URL) ? substr($u, 0, 200) : '';
}

$action     = (string)($_GET['action'] ?? '');
$loginError = '';

if ($action === 'logout') {
    $_SESSION = [];
    if (session_id() !== '') { session_destroy(); }
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($action === 'admin' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pw = (string)($_POST['admin_password'] ?? '');
    if (hash_equals(ADMIN_PASSWORD, $pw)) {
        $_SESSION['hoodfi_admin'] = true;
        header('Location: ?action=admin');
        exit;
    }
    $loginError = 'Incorrect password. Please try again.';
}

$isAdmin = !empty($_SESSION['hoodfi_admin']);

/* ---- Admin API: save settings (name / socials / theme) ---- */
if ($action === 'save_settings') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo '{"ok":false,"error":"forbidden"}'; exit; }
    $b = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $S = hf_load($DEFAULTS);
    if (isset($b['site_name'])) {
        $n = trim(mb_substr((string)$b['site_name'], 0, 40));
        $S['site_name'] = $n !== '' ? $n : 'HoodFi';
    }
    if (isset($b['tg_url'])) { $S['tg_url'] = hf_social((string)$b['tg_url'], 'https://t.me/'); }
    if (isset($b['x_url']))  { $S['x_url']  = hf_social((string)$b['x_url'],  'https://x.com/'); }
    if (isset($b['theme']))  { $t = (int)$b['theme']; $S['theme'] = in_array($t, [1, 2, 3], true) ? $t : 1; }
    foreach (['contract_4663', 'contract_46630'] as $ck) {
        if (isset($b[$ck])) {
            $ca = trim((string)$b[$ck]);
            if ($ca === '' || preg_match('/^0x[a-fA-F0-9]{40}$/', $ca)) { $S[$ck] = $ca; }
        }
    }
    hf_save($S);
    echo json_encode(['ok' => true, 'settings' => $S]);
    exit;
}

/* ---- Admin API: logo upload ---- */
if ($action === 'upload_logo') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo '{"ok":false,"error":"forbidden"}'; exit; }
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'no file received']); exit;
    }
    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'max 2 MB allowed']); exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
    $map  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/gif' => 'gif'];
    if (!isset($map[$mime])) {
        echo json_encode(['ok' => false, 'error' => 'PNG / JPG / WebP / SVG / GIF only']); exit;
    }
    foreach (glob(__DIR__ . '/logo.*') ?: [] as $old) {
        if (preg_match('/\.(png|jpe?g|webp|svg|gif)$/', $old)) { @unlink($old); }
    }
    $fname = 'logo.' . $map[$mime];
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/' . $fname)) {
        echo json_encode(['ok' => false, 'error' => 'upload failed — folder writable?']); exit;
    }
    $S = hf_load($DEFAULTS);
    $S['logo'] = $fname . '?v=' . time();
    hf_save($S);
    echo json_encode(['ok' => true, 'settings' => $S]);
    exit;
}

$S = hf_load($DEFAULTS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodFi — Liquidity Protocol on Robinhood Chain</title>
<meta name="description" content="Deposit ETH, receive LP shares, withdraw anytime. A transparent liquidity pool on Robinhood Chain.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.4/dist/ethers.umd.min.js"></script>
<style>
:root{
  --bg:#ffffff;
  --ink:#0b0b0c;
  --ink-2:#3f4247;
  --ink-3:#8b8c92;
  --line:#e6e7ec;
  --line-soft:#f0f1f5;
  --accent:#b3c0fe;            /* periwinkle */
  --accent-deep:#4c5ef2;
  --accent-ink:#2b3bd4;
  --mint:#daf8f1;
  --mint-deep:#0f9d7c;
  --red:#dc4446;
  --amber:#b97907;
  --card:#ffffff;
  --glass:rgba(255,255,255,.72);
  --radius:18px;
  --font-display:"Space Grotesk",Inter,system-ui,sans-serif;
  --font-body:Inter,system-ui,-apple-system,sans-serif;
  --font-mono:"JetBrains Mono",ui-monospace,SFMono-Regular,monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:var(--font-body);
  background:var(--bg);
  color:var(--ink);
  -webkit-font-smoothing:antialiased;
  min-height:100vh;
}
::selection{background:var(--accent);color:var(--ink)}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
input,select{font-family:inherit}
.mono{font-family:var(--font-mono)}
.container{max-width:1180px;margin:0 auto;padding:0 28px}

/* ---------- Nav ---------- */
.nav{
  position:sticky;top:0;z-index:60;
  background:var(--glass);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid var(--line);
}
.nav-inner{display:flex;align-items:center;gap:26px;height:68px}
.brand{display:flex;align-items:center;gap:11px;font-family:var(--font-display);font-weight:700;font-size:19px;letter-spacing:-.02em}
.brand svg{display:block}
.brand small{font-family:var(--font-mono);font-weight:500;font-size:9.5px;letter-spacing:.14em;color:var(--ink-3);border:1px solid var(--line);border-radius:99px;padding:3px 8px;margin-left:2px}
.tabs{display:flex;gap:4px;margin-left:8px}
.tab-btn{
  border:0;background:transparent;font-size:13.5px;font-weight:500;color:var(--ink-2);
  padding:8px 14px;border-radius:99px;transition:background .2s,color .2s;
}
.tab-btn:hover{background:var(--line-soft)}
.tab-btn.active{background:var(--ink);color:#fff}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.net-pill{
  display:flex;align-items:center;gap:7px;font-family:var(--font-mono);font-size:11px;font-weight:500;
  border:1px solid var(--line);border-radius:99px;padding:7px 12px;color:var(--ink-2);background:#fff;
}
.net-dot{width:7px;height:7px;border-radius:50%;background:#c4c6cc}
.net-dot.on{background:var(--mint-deep);box-shadow:0 0 0 3px rgba(15,157,124,.15)}
.btn{
  border:0;border-radius:99px;font-weight:600;font-size:13.5px;
  padding:10px 20px;transition:transform .18s,box-shadow .25s,background .2s,opacity .2s;
}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-dark{background:var(--ink);color:#fff}
.btn-dark:hover:not(:disabled){box-shadow:0 6px 22px rgba(11,11,12,.22)}
.btn-accent{background:var(--accent-deep);color:#fff}
.btn-accent:hover:not(:disabled){box-shadow:0 6px 22px rgba(76,94,242,.32)}
.btn-ghost{background:#fff;border:1px solid var(--line);color:var(--ink)}
.btn-ghost:hover:not(:disabled){border-color:var(--ink)}
.btn-block{width:100%}

/* ---------- Hero ---------- */
.hero{position:relative;overflow:hidden;border-bottom:1px solid var(--line)}
#shader{position:absolute;inset:0;width:100%;height:100%;display:block;pointer-events:none}
.hero-inner{position:relative;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;padding:88px 0 76px;align-items:center}
.eyebrow{
  font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.22em;color:var(--accent-ink);
  display:inline-flex;align-items:center;gap:8px;margin-bottom:22px;
}
.eyebrow::before{content:"";width:22px;height:1.5px;background:var(--accent-deep)}
h1{
  font-family:var(--font-display);font-weight:700;font-size:clamp(40px,4.6vw,62px);
  line-height:1.02;letter-spacing:-.035em;margin-bottom:20px;
}
h1 .hl{
  background:linear-gradient(100deg,var(--accent) 0%,#c9e9f5 55%,var(--mint) 100%);
  border-radius:.18em;padding:0 .14em;box-decoration-break:clone;-webkit-box-decoration-break:clone;
}
.lede{font-size:16.5px;line-height:1.65;color:var(--ink-2);max-width:46ch;margin-bottom:32px}
.hero-ctas{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.micro{font-family:var(--font-mono);font-size:11px;color:var(--ink-3);margin-top:18px;letter-spacing:.02em}
.micro b{color:var(--ink-2);font-weight:600}

/* ---------- Live dashboard card (hero right) ---------- */
.dash{
  background:var(--card);border:1px solid var(--line);border-radius:22px;
  box-shadow:0 24px 60px -30px rgba(43,59,212,.25),0 2px 6px rgba(11,11,12,.04);
  overflow:hidden;
}
.dash-head{
  display:flex;align-items:center;gap:10px;padding:16px 22px;border-bottom:1px solid var(--line-soft);
  font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.18em;color:var(--ink-3);
}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--mint-deep);animation:pulse 1.8s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(15,157,124,.35)}55%{box-shadow:0 0 0 7px rgba(15,157,124,0)}}
.dash-head .spacer{margin-left:auto;color:var(--ink-3);letter-spacing:.05em;font-weight:500}
.dash-grid{display:grid;grid-template-columns:1fr 1fr}
.stat{padding:22px;border-bottom:1px solid var(--line-soft)}
.stat:nth-child(odd){border-right:1px solid var(--line-soft)}
.stat:nth-child(3),.stat:nth-child(4){border-bottom:0}
.stat-label{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.16em;color:var(--ink-3);margin-bottom:10px}
.stat-value{font-family:var(--font-display);font-weight:700;font-size:26px;letter-spacing:-.02em;display:flex;align-items:baseline;gap:6px}
.stat-unit{font-family:var(--font-mono);font-size:11px;font-weight:500;color:var(--ink-3)}
.slot{position:relative;display:inline-flex;flex-direction:column;height:1.08em;overflow:hidden}
.slot-line{display:block;height:1.08em;line-height:1.08em;transition:transform .45s cubic-bezier(.22,1,.36,1)}
.stat-sub{font-size:12px;color:var(--ink-3);margin-top:8px}
.dash-foot{
  border-top:1px solid var(--line-soft);padding:13px 22px;display:flex;align-items:center;gap:8px;
  font-family:var(--font-mono);font-size:10.5px;color:var(--ink-3);
}
.dash-foot a{color:var(--accent-ink);border-bottom:1px solid var(--accent)}

/* ---------- Sections / panels ---------- */
main{min-height:60vh}
.panel{display:none;padding:64px 0 90px}
.panel.active{display:block;animation:panelIn .4s cubic-bezier(.22,1,.36,1)}
@keyframes panelIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.section-head{margin-bottom:34px}
.section-kicker{font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.22em;color:var(--accent-ink);margin-bottom:12px}
.section-title{font-family:var(--font-display);font-weight:700;font-size:clamp(26px,3vw,36px);letter-spacing:-.03em}
.section-sub{color:var(--ink-2);margin-top:10px;max-width:60ch;line-height:1.6;font-size:15px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:22px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.card{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:26px;
  transition:box-shadow .25s,border-color .25s;
}
.card:hover{border-color:#d8d9e2;box-shadow:0 14px 40px -24px rgba(11,11,12,.18)}
.card-label{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.16em;color:var(--ink-3);margin-bottom:14px}
.card-big{font-family:var(--font-display);font-weight:700;font-size:30px;letter-spacing:-.02em}
.card-sub{font-size:13px;color:var(--ink-3);margin-top:8px;line-height:1.5}

.field{margin-bottom:18px}
.field label{display:block;font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.14em;color:var(--ink-3);margin-bottom:8px}
.input-wrap{position:relative}
.input-wrap input{
  width:100%;border:1px solid var(--line);border-radius:14px;padding:15px 64px 15px 16px;
  font-family:var(--font-mono);font-size:16px;font-weight:500;color:var(--ink);background:#fff;
  transition:border-color .2s,box-shadow .2s;outline:none;
}
.input-wrap input:focus{border-color:var(--accent-deep);box-shadow:0 0 0 4px rgba(76,94,242,.12)}
.input-unit{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  font-family:var(--font-mono);font-size:11px;font-weight:600;color:var(--ink-3);
  background:var(--line-soft);border-radius:8px;padding:5px 9px;
}
.hint-row{display:flex;justify-content:space-between;font-size:12px;color:var(--ink-3);margin-top:8px}
.hint-row button{background:none;border:0;color:var(--accent-ink);font-weight:600;font-size:12px;text-decoration:underline;text-underline-offset:3px}
.quote-line{
  display:flex;justify-content:space-between;align-items:center;gap:12px;
  font-size:13px;color:var(--ink-2);padding:11px 0;border-top:1px dashed var(--line);
}
.quote-line .mono{font-size:12.5px;font-weight:600;color:var(--ink)}
.note{
  border:1px solid var(--line);border-left:3px solid var(--accent-deep);border-radius:12px;
  padding:14px 16px;font-size:13px;color:var(--ink-2);line-height:1.6;background:linear-gradient(180deg,#fbfcff,#fff);
}
.note.warn{border-left-color:var(--amber);background:linear-gradient(180deg,#fffdf7,#fff)}
.divider{height:1px;background:var(--line);margin:22px 0}

/* status chips */
.chip{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.08em;border-radius:99px;padding:5px 11px}
.chip.ok{background:#e8f8f1;color:var(--mint-deep)}
.chip.off{background:#fdeeee;color:var(--red)}
.chip.idle{background:var(--line-soft);color:var(--ink-3)}

/* table */
.table{width:100%;border-collapse:collapse;font-size:13px}
.table th{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.14em;color:var(--ink-3);text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
.table td{padding:12px;border-bottom:1px solid var(--line-soft);color:var(--ink-2)}
.table tr:last-child td{border-bottom:0}
.table .mono{font-size:12px}
.badge{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.06em;border-radius:6px;padding:3px 8px}
.badge.in{background:#e8f8f1;color:var(--mint-deep)}
.badge.out{background:#fdeeee;color:var(--red)}
.link{color:var(--accent-ink);border-bottom:1px solid var(--accent)}
.empty{color:var(--ink-3);font-size:13px;padding:26px 12px;text-align:center}

/* guide steps */
.steps{display:grid;gap:0}
.step{display:grid;grid-template-columns:44px 1fr;gap:18px;padding:22px 0;border-bottom:1px solid var(--line-soft)}
.step:last-child{border-bottom:0}
.step-num{
  width:44px;height:44px;border-radius:14px;background:var(--ink);color:#fff;
  display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-weight:600;font-size:14px;
}
.step h3{font-family:var(--font-display);font-size:17px;font-weight:600;margin-bottom:7px;letter-spacing:-.01em}
.step p{font-size:14px;color:var(--ink-2);line-height:1.65}
.step code{font-family:var(--font-mono);font-size:12px;background:var(--line-soft);border-radius:6px;padding:2px 7px;color:var(--accent-ink)}

/* ---------- Admin ---------- */
.admin-hero{padding:64px 0 90px}
.login-card{max-width:420px;margin:8vh auto 0}
.login-card .card{padding:34px}
.admin-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
.admin-grid .card{padding:20px}
.admin-grid .card-big{font-size:22px}
.admin-cols{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;align-items:start}
.kv{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px solid var(--line-soft);font-size:13px}
.kv:last-child{border-bottom:0}
.kv .k{color:var(--ink-3);font-family:var(--font-mono);font-size:11px;letter-spacing:.1em}
.kv .v{color:var(--ink);font-weight:500;text-align:right;word-break:break-all}

/* toast */
#toast{
  position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);z-index:100;
  background:var(--ink);color:#fff;border-radius:14px;padding:14px 20px;font-size:13.5px;
  opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;max-width:min(560px,90vw);
  box-shadow:0 18px 50px -12px rgba(11,11,12,.4);
}
#toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
#toast a{color:var(--accent);text-decoration:underline;text-underline-offset:3px}
#toast.err{background:#7c1d1d}

/* footer */
footer{border-top:1px solid var(--line);padding:34px 0 44px;margin-top:20px}
.foot-inner{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.foot-inner .mono{font-size:11px;color:var(--ink-3)}
.foot-links{margin-left:auto;display:flex;gap:20px;font-size:13px;color:var(--ink-2)}
.foot-links a{border-bottom:1px solid transparent;transition:border-color .2s}
.foot-links a:hover{border-color:var(--ink)}

/* reveal on scroll */
.reveal{opacity:0;transform:translateY(14px);transition:opacity .6s cubic-bezier(.22,1,.36,1),transform .6s cubic-bezier(.22,1,.36,1)}
.reveal.in{opacity:1;transform:none}

/* theme picker (admin) */
.theme-picker{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.theme-opt{border:1px solid var(--line);border-radius:14px;padding:12px;cursor:pointer;display:block;transition:border-color .2s,box-shadow .2s}
.theme-opt input{display:none}
.theme-opt b{display:block;font-family:var(--font-display);font-size:14px;margin:9px 0 2px;letter-spacing:-.01em}
.theme-opt i{font-style:normal;font-size:11px;color:var(--ink-3)}
.theme-opt.sel{border-color:var(--accent-deep);box-shadow:0 0 0 3px rgba(76,94,242,.15)}
.theme-prev{display:block;height:56px;border-radius:9px;border:1px solid var(--line)}
.p1{background:linear-gradient(120deg,#ffffff 55%,#b3c0fe)}
.p2{background:linear-gradient(120deg,#0a0a0c 55%,#00d9a3)}
.p3{background:linear-gradient(120deg,#e5e7eb 55%,#fd630c)}

/* ============================================================
   THEME 2 — MIDNIGHT TERMINAL (dark, mint, centered hero)
   ============================================================ */
body.theme-2{
  --bg:#0a0a0c;--ink:#f4f4f5;--ink-2:#b6b7bd;--ink-3:#71717a;
  --line:#232328;--line-soft:#1a1a1f;--card:#121215;--glass:rgba(10,10,12,.8);
  --accent:#7cf5d0;--accent-deep:#00d9a3;--accent-ink:#34e3b0;
  --mint:#123c33;--mint-deep:#34e3b0;--red:#ff7a7a;--amber:#f5b942;--radius:12px;
}
body.theme-2 .brand svg rect{fill:#f4f4f5}
body.theme-2 .brand svg path{fill:#00d9a3}
body.theme-2 .brand svg circle{fill:#0a0a0c}
body.theme-2 .btn-dark{background:#f4f4f5;color:#0a0a0c}
body.theme-2 .btn-dark:hover:not(:disabled){box-shadow:0 6px 22px rgba(244,244,245,.18)}
body.theme-2 .btn-accent{color:#04120d}
body.theme-2 .btn-accent:hover:not(:disabled){box-shadow:0 6px 22px rgba(0,217,163,.35)}
body.theme-2 .btn-ghost{background:transparent;border-color:var(--line);color:var(--ink)}
body.theme-2 .btn-ghost:hover:not(:disabled){border-color:var(--accent-deep)}
body.theme-2 .tab-btn{color:var(--ink-2)}
body.theme-2 .tab-btn:hover{background:var(--line-soft)}
body.theme-2 .tab-btn.active{background:var(--accent-deep);color:#04120d}
body.theme-2 .net-pill{background:var(--card)}
body.theme-2 #shader{display:none}
body.theme-2 .hero{background:radial-gradient(60% 80% at 70% 0%,rgba(0,217,163,.13),transparent 60%),var(--bg)}
body.theme-2 .hero-inner{grid-template-columns:1fr;text-align:center;gap:46px;padding:80px 0 72px}
body.theme-2 .lede{margin:0 auto 32px}
body.theme-2 .hero-ctas{justify-content:center}
body.theme-2 .dash{max-width:700px;margin:0 auto;text-align:left;box-shadow:0 24px 60px -30px rgba(0,217,163,.28)}
body.theme-2 h1 .hl{background:linear-gradient(100deg,rgba(0,217,163,.32),rgba(124,245,208,.16));color:var(--accent)}
body.theme-2 .live-dot{background:var(--accent-deep)}
body.theme-2 .chip.idle{background:var(--line-soft);color:var(--ink-3)}
body.theme-2 .chip.ok{background:#0c2f27;color:var(--accent)}
body.theme-2 .chip.off{background:#3a1414;color:var(--red)}
body.theme-2 .input-wrap input{background:#0d0d10;color:var(--ink)}
body.theme-2 .input-unit{background:var(--line-soft);color:var(--ink-2)}
body.theme-2 .note{background:var(--card)}
body.theme-2 .note.warn{background:linear-gradient(180deg,#171307,var(--card))}
body.theme-2 .badge.in{background:#0c2f27;color:var(--accent)}
body.theme-2 .badge.out{background:#3a1414;color:var(--red)}
body.theme-2 .card:hover{box-shadow:0 14px 40px -24px rgba(0,0,0,.65)}
body.theme-2 .step-num{background:var(--accent-deep);color:#04120d}
body.theme-2 #toast{background:#f4f4f5;color:#0a0a0c}
body.theme-2 #toast.err{background:#5c1a1a;color:#fff}
body.theme-2 .theme-opt.sel{box-shadow:0 0 0 3px rgba(0,217,163,.2)}
body.theme-2 ::selection{background:#00d9a3;color:#04120d}

/* ============================================================
   THEME 3 — PRESS (brutalist light, black rules, orange)
   ============================================================ */
body.theme-3{
  --bg:#e5e7eb;--ink:#000000;--ink-2:#333335;--ink-3:#5f6063;
  --line:#000000;--line-soft:#c6c8cd;--card:#ffffff;--glass:rgba(229,231,235,.88);
  --accent:#fd630c;--accent-deep:#fd630c;--accent-ink:#d14e00;
  --mint:#c9f2e4;--mint-deep:#0a7a5c;--radius:0px;
}
body.theme-3 .nav{border-bottom:1.5px solid #000}
body.theme-3 .brand svg rect{rx:0}
body.theme-3 .tab-btn{border-radius:0}
body.theme-3 .tab-btn:hover{background:#d3d5da}
body.theme-3 .tab-btn.active{background:#000;color:#fff}
body.theme-3 .net-pill{border-radius:0;border:1.5px solid #000;background:#fff}
body.theme-3 .btn{border-radius:0}
body.theme-3 .btn-dark{background:#000;color:#fff}
body.theme-3 .btn-dark:hover:not(:disabled){box-shadow:4px 4px 0 #fd630c}
body.theme-3 .btn-accent{background:#fd630c;color:#000}
body.theme-3 .btn-accent:hover:not(:disabled){box-shadow:4px 4px 0 #000}
body.theme-3 .btn-ghost{background:#fff;border:1.5px solid #000;color:#000}
body.theme-3 .btn-ghost:hover:not(:disabled){box-shadow:4px 4px 0 #000}
body.theme-3 #shader{display:none}
body.theme-3 .hero{border-bottom:1.5px solid #000;background:var(--bg)}
body.theme-3 .hero-inner{grid-template-columns:1fr;gap:42px;padding:76px 0 64px}
body.theme-3 h1{text-transform:uppercase;font-size:clamp(42px,6.4vw,84px)}
body.theme-3 h1 .hl{background:#fd630c;color:#000;border-radius:0;padding:0 .12em}
body.theme-3 .lede{max-width:62ch}
body.theme-3 .eyebrow{color:#000}
body.theme-3 .eyebrow::before{background:#fd630c;height:3px;width:30px}
body.theme-3 .dash{border:1.5px solid #000;border-radius:0;box-shadow:none}
body.theme-3 .stat{border-bottom-color:#000}
body.theme-3 .stat:nth-child(odd){border-right-color:#000}
body.theme-3 .dash-head{border-bottom-color:#000}
body.theme-3 .dash-foot{border-top-color:#000}
body.theme-3 .card{border:1.5px solid #000;border-radius:0;box-shadow:none}
body.theme-3 .card:hover{border-color:#000;box-shadow:6px 6px 0 #000}
body.theme-3 .input-wrap input{border:1.5px solid #000;border-radius:0}
body.theme-3 .input-wrap input:focus{border-color:#000;box-shadow:4px 4px 0 #fd630c}
body.theme-3 .input-unit{border-radius:0;border:1px solid #000;background:#fff;color:#000}
body.theme-3 .note{border:1.5px solid #000;border-left:6px solid #fd630c;border-radius:0;background:#fff}
body.theme-3 .note.warn{border-left-color:#000}
body.theme-3 .step-num{border-radius:0;background:#000}
body.theme-3 .step{border-bottom-color:#000}
body.theme-3 .chip{border-radius:0}
body.theme-3 .badge{border-radius:0}
body.theme-3 .table th{border-bottom:1.5px solid #000}
body.theme-3 footer{border-top:1.5px solid #000}
body.theme-3 .divider{background:#000}
body.theme-3 .theme-opt{border-radius:0}
body.theme-3 .theme-prev{border-radius:0}
body.theme-3 .theme-opt.sel{border-color:#fd630c;box-shadow:3px 3px 0 #fd630c}
body.theme-3 ::selection{background:#fd630c;color:#000}

@media(max-width:960px){
  .hero-inner{grid-template-columns:1fr;padding:60px 0}
  .grid-2,.grid-3,.admin-grid,.admin-cols{grid-template-columns:1fr}
  .tabs{display:none}
  .nav-inner{gap:14px}
}
</style>
</head>
<body>

<script>
//__APP_STATE_START__
window.APP = {
  action: <?= json_encode($action, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
  loginError: <?= json_encode($loginError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
//__APP_STATE_END__
</script>

<script>
//__SITE_SETTINGS_START__
window.SITE_SETTINGS = <?= json_encode($S, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
//__SITE_SETTINGS_END__
</script>

<nav class="nav">
  <div class="container nav-inner">
    <a class="brand" href="?">
      <svg width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true">
        <rect x="1" y="1" width="24" height="24" rx="7" fill="#0b0b0c"/>
        <path d="M13 6.5c3.2 3.8 5 6.7 5 9a5 5 0 1 1-10 0c0-2.3 1.8-5.2 5-9Z" fill="#b3c0fe"/>
        <circle cx="13" cy="15.5" r="2" fill="#daf8f1"/>
      </svg>
      HoodFi <small>ROBINHOOD CHAIN</small>
    </a>
    <div class="tabs" id="tabs">
      <button class="tab-btn active" data-tab="overview">Overview</button>
      <button class="tab-btn" data-tab="pool">Pool</button>
      <button class="tab-btn" data-tab="wallet">Wallet</button>
      <button class="tab-btn" data-tab="guide">Guide</button>
    </div>
    <div class="nav-right">
      <div class="net-pill"><span class="net-dot" id="netDot"></span><span id="netLabel">Not connected</span></div>
      <button class="btn btn-dark" id="connectBtn">Connect Wallet</button>
    </div>
  </div>
</nav>

<!-- ============================ PUBLIC SITE ============================ -->
<div id="publicView">

  <section class="hero">
    <canvas id="shader"></canvas>
    <div class="container hero-inner">
      <div>
        <div class="eyebrow">LIQUIDITY PROTOCOL · CHAIN ID 4663 · MAINNET</div>
        <h1>Liquidity on the chain <span class="hl">built for markets.</span></h1>
        <p class="lede">
          Deposit ETH into a transparent, non-custodial pool on Robinhood Chain.
          You receive LP shares that track your exact share of the pool — withdraw
          your ETH back at any time. No database, no middleman: every number on
          this page is read directly from the blockchain.
        </p>
        <div class="hero-ctas">
          <button class="btn btn-accent" id="heroConnect">Connect Wallet</button>
          <button class="btn btn-ghost" data-goto="pool">Add Liquidity</button>
          <button class="btn btn-ghost" data-goto="guide">Deploy Guide</button>
        </div>
        <div class="micro">NETWORK <b>Robinhood Chain (Mainnet)</b> &nbsp;·&nbsp; GAS TOKEN <b>ETH</b> &nbsp;·&nbsp; CUSTODY <b>none — contract only</b></div>
      </div>

      <div class="dash reveal in">
        <div class="dash-head"><span class="live-dot"></span> LIVE POOL OBSERVATION <span class="spacer" id="dashBlock">block —</span></div>
        <div class="dash-grid">
          <div class="stat">
            <div class="stat-label">TOTAL VALUE LOCKED</div>
            <div class="stat-value"><span class="slot" id="statTvl">—</span><span class="stat-unit">ETH</span></div>
            <div class="stat-sub">ETH held by the pool contract</div>
          </div>
          <div class="stat">
            <div class="stat-label">LP SHARES ISSUED</div>
            <div class="stat-value"><span class="slot" id="statShares">—</span><span class="stat-unit">HF-LP</span></div>
            <div class="stat-sub">total claims on the pool</div>
          </div>
          <div class="stat">
            <div class="stat-label">YOUR POSITION</div>
            <div class="stat-value"><span class="slot" id="statMine">—</span><span class="stat-unit">ETH</span></div>
            <div class="stat-sub" id="statMinePct">connect wallet to view</div>
          </div>
          <div class="stat">
            <div class="stat-label">POOL STATUS</div>
            <div class="stat-value" style="font-size:16px;align-items:center"><span class="chip idle" id="statStatus">READING…</span></div>
            <div class="stat-sub" id="statContract">contract not configured</div>
          </div>
        </div>
        <div class="dash-foot">
          <span id="footChain">Robinhood Chain (Mainnet)</span> ·
          <a href="#" id="footExplorer" target="_blank" rel="noopener">view contract on explorer</a>
        </div>
      </div>
    </div>
  </section>

  <main class="container">

    <!-- ============ OVERVIEW ============ -->
    <section class="panel active" id="panel-overview">
      <div class="section-head">
        <div class="section-kicker">HOW IT WORKS</div>
        <div class="section-title">One pool. Three moves. Zero trust required.</div>
      </div>
      <div class="grid-3">
        <div class="card reveal">
          <div class="card-label">01 — DEPOSIT</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Send ETH, get shares</h3>
          <p class="card-sub">Call <span class="mono" style="font-size:12px;color:var(--accent-ink)">deposit()</span> with any amount of ETH. The contract mints LP shares proportional to your stake — the first deposit is 1:1.</p>
        </div>
        <div class="card reveal">
          <div class="card-label">02 — EARN / HOLD</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Shares track the pool</h3>
          <p class="card-sub">Your shares always represent the same percentage of total liquidity. The pool's balance and your position are readable on-chain by anyone, any time.</p>
        </div>
        <div class="card reveal">
          <div class="card-label">03 — WITHDRAW</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Burn shares, ETH back</h3>
          <p class="card-sub">Call <span class="mono" style="font-size:12px;color:var(--accent-ink)">withdraw(shares)</span> or <span class="mono" style="font-size:12px;color:var(--accent-ink)">withdrawAll()</span>. The contract burns your shares and returns your ETH in the same transaction.</p>
        </div>
      </div>
      <div class="divider"></div>
      <div class="grid-2">
        <div class="note">
          <b>Why no SQL?</b> Balances, shares and history all live inside the smart contract on Robinhood Chain.
          This site is a window into that contract — even the admin panel reads everything from the chain itself.
          If this website disappeared tomorrow, your funds would still be in the contract, withdrawable from any other interface.
        </div>
        <div class="note warn">
          <b>Honest by design.</b> The contract owner can only pause new deposits or hand over ownership —
          the owner <u>cannot</u> move or freeze user funds. Verify the source on the explorer before depositing anything.
          Start on <b>testnet</b> with free faucet ETH.
        </div>
      </div>
    </section>

    <!-- ============ POOL ============ -->
    <section class="panel" id="panel-pool">
      <div class="section-head">
        <div class="section-kicker">POOL</div>
        <div class="section-title">Add or remove liquidity</div>
        <p class="section-sub">Transactions are signed in your wallet and executed by the smart contract on Robinhood Chain. This site never sees your keys.</p>
      </div>
      <div id="noContractNote" class="note warn" style="display:none;margin-bottom:22px">
        The pool contract address is not configured for this network yet. Deploy
        <span class="mono" style="font-size:12px">LiquidityPool.sol</span> (see the <b>Guide</b> tab), then paste the
        address into <span class="mono" style="font-size:12px">CONFIG.CONTRACTS</span> at the top of this file's script.
        Balance and network features still work.
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-label">ADD LIQUIDITY</div>
          <div class="field">
            <label>AMOUNT (ETH)</label>
            <div class="input-wrap">
              <input id="depAmount" type="text" inputmode="decimal" placeholder="0.0" autocomplete="off">
              <span class="input-unit">ETH</span>
            </div>
            <div class="hint-row">
              <span>Wallet balance: <span class="mono" id="depBalance">—</span></span>
              <button id="depMax" type="button">MAX</button>
            </div>
          </div>
          <div class="quote-line"><span>You will receive</span><span class="mono" id="depQuote">—</span></div>
          <div class="quote-line"><span>Share of pool after deposit</span><span class="mono" id="depSharePct">—</span></div>
          <div style="height:18px"></div>
          <button class="btn btn-accent btn-block" id="depBtn">Deposit ETH</button>
        </div>
        <div class="card">
          <div class="card-label">REMOVE LIQUIDITY</div>
          <div class="field">
            <label>LP SHARES TO BURN</label>
            <div class="input-wrap">
              <input id="wdShares" type="text" inputmode="decimal" placeholder="0.0" autocomplete="off">
              <span class="input-unit">HF-LP</span>
            </div>
            <div class="hint-row">
              <span>Your shares: <span class="mono" id="wdBalance">—</span></span>
              <button id="wdMax" type="button">MAX</button>
            </div>
          </div>
          <div class="quote-line"><span>You will receive</span><span class="mono" id="wdQuote">—</span></div>
          <div class="quote-line"><span>Remaining position</span><span class="mono" id="wdRemain">—</span></div>
          <div style="height:18px"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <button class="btn btn-dark" id="wdBtn">Withdraw</button>
            <button class="btn btn-ghost" id="wdAllBtn">Withdraw All</button>
          </div>
        </div>
      </div>
      <div style="height:22px"></div>
      <div class="card">
        <div class="card-label">YOUR RECENT POOL ACTIVITY</div>
        <table class="table">
          <thead><tr><th>TYPE</th><th>ETH</th><th>SHARES</th><th>TX</th></tr></thead>
          <tbody id="myEvents"><tr><td colspan="4" class="empty">Connect your wallet to load your on-chain activity.</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ============ WALLET ============ -->
    <section class="panel" id="panel-wallet">
      <div class="section-head">
        <div class="section-kicker">WALLET</div>
        <div class="section-title">Connection &amp; balances</div>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-label">CONNECTED ACCOUNT</div>
          <div id="walletBox">
            <div style="padding:26px 0;text-align:center;color:var(--ink-3);font-size:14px">
              No wallet connected.<br><br>
              <button class="btn btn-dark" id="walletConnect">Connect Wallet</button>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-label">NETWORK</div>
          <div class="kv"><span class="k">CHAIN</span><span class="v" id="nwName">—</span></div>
          <div class="kv"><span class="k">CHAIN ID</span><span class="v mono" id="nwId">—</span></div>
          <div class="kv"><span class="k">RPC</span><span class="v mono" id="nwRpc" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">EXPLORER</span><span class="v"><a class="link" id="nwExplorer" target="_blank" rel="noopener" href="#">open</a></span></div>
          <div style="height:14px"></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-ghost" id="switchTestnet">Use Testnet</button>
            <button class="btn btn-ghost" id="switchMainnet">Use Mainnet</button>
            <a class="btn btn-ghost" id="faucetBtn" target="_blank" rel="noopener" href="https://faucet.testnet.chain.robinhood.com">Get test ETH</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ GUIDE ============ -->
    <section class="panel" id="panel-guide">
      <div class="section-head">
        <div class="section-kicker">DEPLOY GUIDE</div>
        <div class="section-title">Live in five steps</div>
        <p class="section-sub">Everything runs on Robinhood Chain. The contract is a single file with zero imports — deploy it from Remix in minutes.</p>
      </div>
      <div class="card">
        <div class="steps">
          <div class="step">
            <div class="step-num">1</div>
            <div><h3>Add Robinhood Chain to your wallet</h3>
            <p>This site defaults to <b>mainnet</b> — Chain ID <code>4663</code>, RPC <code>https://rpc.mainnet.chain.robinhood.com</code>.
            The wallet adds it automatically on connect. For practice first, press <b>Use Testnet</b> in the Wallet tab
            (Chain ID <code>46630</code>).</p></div>
          </div>
          <div class="step">
            <div class="step-num">2</div>
            <div><h3>Get ETH for gas</h3>
            <p>Mainnet par deployment aur transactions ke liye real ETH chahiye — <a class="link" target="_blank" rel="noopener" href="https://docs.robinhood.com/chain">Robinhood Chain docs</a> se bridge karein.
            Sirf test ke liye: testnet par <a class="link" target="_blank" rel="noopener" href="https://faucet.testnet.chain.robinhood.com">faucet</a> se free ETH mil jata hai.</p></div>
          </div>
          <div class="step">
            <div class="step-num">3</div>
            <div><h3>Deploy the contract</h3>
            <p>Open <a class="link" target="_blank" rel="noopener" href="https://remix.ethereum.org">remix.ethereum.org</a>, create
            <code>LiquidityPool.sol</code>, paste the contract file shipped with this site, compile with Solidity
            <code>0.8.20+</code>, then in the Deploy tab choose <b>Injected Provider — MetaMask</b> and press <b>Deploy</b>.</p></div>
          </div>
          <div class="step">
            <div class="step-num">4</div>
            <div><h3>Contract address set karein</h3>
            <p>Deployed address <b>Admin Panel → Site Settings</b> me paste karein (mainnet ya testnet field) — Save karte hi
            poori site naye contract par live ho jati hai, file edit karne ki zaroorat nahin. Chahein to
            <code>CONFIG.CONTRACTS</code> me hardcoded bhi kar sakte hain — admin panel wala address hamesha override karta hai.</p></div>
          </div>
          <div class="step">
            <div class="step-num">5</div>
            <div><h3>Admin panel &amp; branding</h3>
            <p>Visit <code>yoursite.com/?action=admin</code> and log in. Wahan se <b>site name</b>, <b>logo upload</b>,
            <b>Telegram / X handles</b> aur <b>3 me se koi bhi theme</b> choose karein — sab settings.json me save hoti hain,
            koi database nahin. Panel TVL, shares, depositors aur latest events seedha chain se parhta hai.</p></div>
          </div>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- ============================ ADMIN ============================ -->
<div id="adminView" style="display:none">
  <main class="container admin-hero">

    <div id="adminLogin" class="login-card" style="display:none">
      <div class="card">
        <div class="card-label">ADMIN ACCESS</div>
        <h2 style="font-family:var(--font-display);font-size:24px;letter-spacing:-.02em;margin-bottom:8px">Sign in</h2>
        <p class="card-sub" style="margin-bottom:22px">Restricted area — pool administration.</p>
        <form method="post" action="?action=admin" id="adminLoginForm">
          <div class="field">
            <label>PASSWORD</label>
            <div class="input-wrap" style="display:block">
              <input type="password" name="admin_password" id="adminPass" placeholder="••••••••" style="padding-right:16px" required autofocus>
            </div>
          </div>
          <div class="note warn" id="loginErr" style="display:none;margin-bottom:16px"></div>
          <button class="btn btn-dark btn-block" type="submit">Unlock Panel</button>
        </form>
        <p style="margin-top:16px;text-align:center"><a class="link" href="?" style="font-size:13px">← back to site</a></p>
      </div>
    </div>

    <div id="adminPanel" style="display:none">
      <div class="section-head" style="display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap">
        <div>
          <div class="section-kicker">ADMIN PANEL</div>
          <div class="section-title">Pool control room</div>
          <p class="section-sub">All figures below are read live from the smart contract on Robinhood Chain. No server database involved.</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:10px">
          <button class="btn btn-ghost" id="adminRefresh">Refresh</button>
          <a class="btn btn-dark" href="?action=logout" id="adminLogout">Log out</a>
        </div>
      </div>

      <div class="card" style="margin-bottom:22px">
        <div class="card-label">SITE SETTINGS — name, logo, socials, theme</div>
        <div class="grid-2" style="gap:18px">
          <div class="field">
            <label>SITE NAME</label>
            <div class="input-wrap"><input id="setName" placeholder="HoodFi" style="padding-right:16px"></div>
          </div>
          <div class="field">
            <label>LOGO (PNG/JPG/SVG/WebP · max 2 MB)</label>
            <div style="display:flex;gap:12px;align-items:center">
              <input type="file" id="setLogo" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" style="font-size:12px;color:var(--ink-2)">
              <img id="logoPreview" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:contain;border:1px solid var(--line);display:none">
            </div>
          </div>
          <div class="field">
            <label>CONTRACT ADDRESS — MAINNET (4663)</label>
            <div class="input-wrap"><input id="setCaMain" placeholder="0x…" style="padding-right:16px;font-size:13px"></div>
          </div>
          <div class="field">
            <label>CONTRACT ADDRESS — TESTNET (46630) · optional</label>
            <div class="input-wrap"><input id="setCaTest" placeholder="0x…" style="padding-right:16px;font-size:13px"></div>
          </div>
          <div class="field">
            <label>TELEGRAM (handle ya URL)</label>
            <div class="input-wrap"><input id="setTg" placeholder="@yourchannel" style="padding-right:16px"></div>
          </div>
          <div class="field">
            <label>X / TWITTER (handle ya URL)</label>
            <div class="input-wrap"><input id="setX" placeholder="@yourhandle" style="padding-right:16px"></div>
          </div>
        </div>
        <div class="field">
          <label>SITE THEME — click to preview instantly</label>
          <div class="theme-picker">
            <label class="theme-opt" data-theme="1"><input type="radio" name="themePick" value="1"><span class="theme-prev p1"></span><b>Aurora</b><i>Light · periwinkle · soft glass</i></label>
            <label class="theme-opt" data-theme="2"><input type="radio" name="themePick" value="2"><span class="theme-prev p2"></span><b>Midnight</b><i>Dark terminal · mint accent</i></label>
            <label class="theme-opt" data-theme="3"><input type="radio" name="themePick" value="3"><span class="theme-prev p3"></span><b>Press</b><i>Brutalist light · black &amp; orange</i></label>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:14px">
          <button class="btn btn-accent" id="saveSettings">Save Settings</button>
          <span class="mono" id="settingsMsg" style="font-size:11px;color:var(--mint-deep)"></span>
        </div>
      </div>

      <div class="admin-grid">
        <div class="card"><div class="card-label">TOTAL VALUE LOCKED</div><div class="card-big"><span class="slot" id="admTvl">—</span> <span class="stat-unit">ETH</span></div></div>
        <div class="card"><div class="card-label">LP SHARES</div><div class="card-big"><span class="slot" id="admShares">—</span></div></div>
        <div class="card"><div class="card-label">UNIQUE DEPOSITORS</div><div class="card-big"><span class="slot" id="admUsers">—</span></div></div>
        <div class="card"><div class="card-label">DEPOSITS / WITHDRAWS</div><div class="card-big"><span class="slot" id="admOps">—</span></div></div>
      </div>

      <div class="admin-cols">
        <div class="card">
          <div class="card-label">LATEST POOL EVENTS (ON-CHAIN)</div>
          <table class="table">
            <thead><tr><th>EVENT</th><th>USER</th><th>ETH</th><th>TX</th></tr></thead>
            <tbody id="admEvents"><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
          </table>
        </div>
        <div class="card">
          <div class="card-label">CONTRACT &amp; CHAIN</div>
          <div class="kv"><span class="k">NETWORK</span><span class="v" id="admNet">—</span></div>
          <div class="kv"><span class="k">CONTRACT</span><span class="v mono" id="admContract" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">OWNER</span><span class="v mono" id="admOwner" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">STATUS</span><span class="v" id="admPaused">—</span></div>
          <div class="kv"><span class="k">LATEST BLOCK</span><span class="v mono" id="admBlock">—</span></div>
          <div class="kv"><span class="k">RPC LATENCY</span><span class="v mono" id="admLatency">—</span></div>
          <div style="height:14px"></div>
          <div class="note" style="font-size:12.5px">
            Owner powers are limited to pause/unpause and ownership transfer — user ETH can never be moved by the owner.
            To pause the pool, connect the owner wallet in Remix and call <span class="mono" style="font-size:11.5px">pause()</span>.
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<footer>
  <div class="container foot-inner">
    <span class="mono"><span class="js-sitename" data-upper>HOODFI</span> — LIQUIDITY PROTOCOL</span>
    <span class="mono" id="footStatus">reading chain…</span>
    <div class="foot-links">
      <a href="#" id="tgLink" target="_blank" rel="noopener" style="display:none">Telegram</a>
      <a href="#" id="xLink" target="_blank" rel="noopener" style="display:none">X</a>
      <a href="https://docs.robinhood.com/chain" target="_blank" rel="noopener">Chain docs</a>
      <a href="https://faucet.testnet.chain.robinhood.com" target="_blank" rel="noopener">Faucet</a>
      <a href="?action=admin">Admin</a>
    </div>
  </div>
</footer>

<div id="toast"></div>

<script>
/* ============================================================
   CONFIG — after deploying LiquidityPool.sol, paste addresses here
   ============================================================ */
const CONFIG = {
  DEFAULT_CHAIN: 4663,
  CHAINS: {
    46630: { name:"Robinhood Chain Testnet", short:"Testnet", hex:"0xB626",
             rpc:"https://rpc.testnet.chain.robinhood.com",
             explorer:"https://explorer.testnet.chain.robinhood.com",
             faucet:"https://faucet.testnet.chain.robinhood.com" },
    4663:  { name:"Robinhood Chain", short:"Mainnet", hex:"0x1237",
             rpc:"https://rpc.mainnet.chain.robinhood.com",
             explorer:"https://robinhoodchain.blockscout.com" }
  },
  CONTRACTS: {
    46630: "0x0000000000000000000000000000000000000000",  // ← testnet contract address yahan paste karein
    4663:  "0x0E1d1dD4bE9e4335Bc8419D4874c27eac129DCc1"   // mainnet contract (admin panel se bhi change ho sakta hai)
  },
  REFRESH_MS: 15000
};

const POOL_ABI = [
  "function deposit() payable",
  "function withdraw(uint256 shares)",
  "function withdrawAll()",
  "function totalLiquidity() view returns (uint256)",
  "function totalShares() view returns (uint256)",
  "function sharesOf(address) view returns (uint256)",
  "function valueOf(address) view returns (uint256)",
  "function previewDeposit(uint256 amount) view returns (uint256)",
  "function getPoolInfo(address user) view returns (uint256 liquidity, uint256 shares, uint256 myShares, uint256 myValue, bool isPaused, address contractOwner)",
  "function owner() view returns (address)",
  "function paused() view returns (bool)",
  "event Deposited(address indexed user, uint256 amount, uint256 sharesMinted)",
  "event Withdrawn(address indexed user, uint256 amount, uint256 sharesBurned)"
];

const ZERO = "0x0000000000000000000000000000000000000000";
const $ = (id) => document.getElementById(id);

/* ---------------- state ---------------- */
let browserProvider = null;   // wallet provider (ethers.BrowserProvider)
let signer = null;
let account = null;
let viewChain = CONFIG.DEFAULT_CHAIN;   // chain the UI is reading
let refreshTimer = null;

const iface = () => new ethers.Interface(POOL_ABI);
const chainCfg = (id) => CONFIG.CHAINS[id];
/* admin panel se set kiya hua address (settings.json) hardcoded default par override karta hai */
const contractAddr = (id) => {
  const s = window.SITE_SETTINGS || {};
  const v = id === 4663 ? (s.contract_4663 || "") : (s.contract_46630 || "");
  return (v && v.toLowerCase() !== ZERO) ? v : (CONFIG.CONTRACTS[id] || ZERO);
};
const hasContract = (id) => contractAddr(id) !== ZERO;
const readProvider = (id) => new ethers.JsonRpcProvider(chainCfg(id).rpc, id, { staticNetwork: true });

/* ---------------- helpers ---------------- */
function toast(msg, isErr = false, ms = 4200) {
  const t = $("toast");
  t.innerHTML = msg;
  t.className = "show" + (isErr ? " err" : "");
  clearTimeout(t._h);
  t._h = setTimeout(() => t.className = "", ms);
}
function fmtEth(wei, dp = 5) {
  try {
    const n = parseFloat(ethers.formatEther(wei));
    if (n === 0) return "0";
    if (n < 0.00001) return "<0.00001";
    return n.toLocaleString("en-US", { maximumFractionDigits: dp });
  } catch { return "0"; }
}
const short = (a) => a ? a.slice(0, 6) + "…" + a.slice(-4) : "—";
const txLink = (hash, chainId) => `<a class="link" target="_blank" rel="noopener" href="${chainCfg(chainId).explorer}/tx/${hash}">${short(hash)}</a>`;
const addrLink = (a, chainId) => `<a class="link" target="_blank" rel="noopener" href="${chainCfg(chainId).explorer}/address/${a}">${short(a)}</a>`;

/* slot-machine number animation */
function slotSet(el, val) {
  if (!el || el.dataset.v === val) return;
  el.dataset.v = val;
  const old = [...el.children];
  const line = document.createElement("span");
  line.className = "slot-line";
  line.textContent = val;
  el.appendChild(line);
  requestAnimationFrame(() => [...el.children].forEach(c => c.style.transform = "translateY(-100%)"));
  setTimeout(() => old.forEach(l => l.remove()), 500);
}

/* ---------------- WebGL pastel shader (hero) ---------------- */
(function shaderBg() {
  const cv = $("shader");
  const gl = cv.getContext("webgl", { alpha: true, antialias: false });
  if (!gl) { cv.style.background = "linear-gradient(120deg,#eef0ff, #f7fbff 55%, #eafaf6)"; return; }
  const vs = "attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}";
  const fs = `precision highp float;uniform float t;uniform vec2 r;
    vec2 pcg2d(vec2 p){p=vec2(dot(p,vec2(127.1,311.7)),dot(p,vec2(269.5,183.3)));return fract(sin(p)*43758.5453);}
    float noise(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);
      float a=pcg2d(i).x,b=pcg2d(i+vec2(1,0)).x,c=pcg2d(i+vec2(0,1)).x,d=pcg2d(i+vec2(1,1)).x;
      return mix(mix(a,b,f.x),mix(c,d,f.x),f.y);}
    float fbm(vec2 p){float v=0.,a=.5;for(int i=0;i<4;i++){v+=a*noise(p);p*=2.03;a*=.5;}return v;}
    void main(){
      vec2 uv=gl_FragCoord.xy/r; uv.x*=r.x/r.y;
      float n=fbm(uv*1.6+vec2(t*.05,t*.03));
      float m=fbm(uv*2.2-vec2(t*.04,t*.02)+n);
      vec3 c0=vec3(.702,.753,.996); vec3 c1=vec3(.929,.941,1.); vec3 c2=vec3(.855,.969,1.);
      vec3 col=mix(c1,c0,smoothstep(.25,.75,n));
      col=mix(col,c2,smoothstep(.45,.85,m)*.7);
      float fade=smoothstep(1.05,.25,gl_FragCoord.y/r.y*.9);
      gl_FragColor=vec4(col,fade*.85);
    }`;
  function sh(type, src) { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
  const pr = gl.createProgram();
  gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs));
  gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs));
  gl.linkProgram(pr); gl.useProgram(pr);
  const buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
  const loc = gl.getAttribLocation(pr, "p");
  gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  const uT = gl.getUniformLocation(pr, "t"), uR = gl.getUniformLocation(pr, "r");
  function frame(ms) {
    const w = cv.clientWidth, h = cv.clientHeight;
    if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; gl.viewport(0, 0, w, h); }
    gl.uniform1f(uT, ms / 1000); gl.uniform2f(uR, w, h);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
})();

/* ---------------- tabs ---------------- */
document.querySelectorAll(".tab-btn").forEach(b => b.addEventListener("click", () => showTab(b.dataset.tab)));
document.querySelectorAll("[data-goto]").forEach(b => b.addEventListener("click", () => { showTab(b.dataset.goto); window.scrollTo({ top: 0, behavior: "smooth" }); }));
function showTab(name) {
  document.querySelectorAll(".tab-btn").forEach(b => b.classList.toggle("active", b.dataset.tab === name));
  document.querySelectorAll(".panel").forEach(p => p.classList.toggle("active", p.id === "panel-" + name));
}

/* ---------------- view router (public vs admin) ---------------- */
(function route() {
  if (APP.action === "admin") {
    $("publicView").style.display = "none";
    document.querySelector(".nav .tabs").style.display = "none";
    $("adminView").style.display = "block";
    if (APP.isAdmin) { $("adminPanel").style.display = "block"; initAdmin(); }
    else {
      $("adminLogin").style.display = "block";
      if (APP.loginError) { const e = $("loginErr"); e.textContent = APP.loginError; e.style.display = "block"; }
    }
  }
})();

/* static preview: client-side admin gate (PHP host par server-side auth chalti hai) */
if (window.STATIC_DEMO) {
  $("adminLoginForm").addEventListener("submit", (ev) => {
    ev.preventDefault();
    if ($("adminPass").value === window.STATIC_ADMIN_PASSWORD) {
      sessionStorage.setItem("hoodfi_admin", "1");
      location.href = "?action=admin";
    } else {
      const e = $("loginErr"); e.textContent = "Incorrect password. Please try again."; e.style.display = "block";
    }
  });
}

/* ---------------- wallet ---------------- */
async function connectWallet() {
  if (!window.ethereum) {
    toast("Wallet nahin mila. Mobile par: site ko <b>MetaMask app ke andar wale browser</b> me kholein. Desktop par: <a href='https://metamask.io' target='_blank' rel='noopener'>MetaMask</a> ya Robinhood Wallet extension install karein.", true, 8000);
    return;
  }
  try {
    browserProvider = new ethers.BrowserProvider(window.ethereum);
    const accounts = await browserProvider.send("eth_requestAccounts", []);
    account = accounts[0];
    const net = await browserProvider.getNetwork();
    let cid = Number(net.chainId);
    if (!chainCfg(cid)) {
      await switchNetwork(CONFIG.DEFAULT_CHAIN);
      cid = CONFIG.DEFAULT_CHAIN;
    }
    viewChain = cid;
    onConnected();
  } catch (e) {
    toast("Connection rejected: " + (e.shortMessage || e.message), true);
  }
}

async function switchNetwork(chainId) {
  const c = chainCfg(chainId);
  if (!window.ethereum) { toast("Wallet not found.", true); return; }
  try {
    await window.ethereum.request({ method: "wallet_switchEthereumChain", params: [{ chainId: c.hex }] });
  } catch (e) {
    if (e.code === 4902) {
      await window.ethereum.request({
        method: "wallet_addEthereumChain",
        params: [{
          chainId: c.hex, chainName: c.name,
          rpcUrls: [c.rpc],
          nativeCurrency: { name: "Ether", symbol: "ETH", decimals: 18 },
          blockExplorerUrls: [c.explorer]
        }]
      });
    } else { toast("Network switch failed: " + (e.shortMessage || e.message), true); return; }
  }
  viewChain = chainId;
  if (account) onConnected(); else refreshPublic();
}

function onConnected() {
  $("connectBtn").textContent = short(account);
  $("heroConnect").textContent = short(account) + " connected";
  $("netDot").classList.add("on");
  $("netLabel").textContent = chainCfg(viewChain).name;
  renderWalletBox();
  refreshPublic();
  if (APP.isAdmin && APP.action === "admin") refreshAdmin();
}

if (window.ethereum) {
  window.ethereum.on("accountsChanged", (accs) => {
    account = accs[0] || null;
    if (account) onConnected(); else location.reload();
  });
  window.ethereum.on("chainChanged", (hexId) => {
    const cid = parseInt(hexId, 16);
    if (chainCfg(cid)) { viewChain = cid; onConnected(); } else location.reload();
  });
}

/* ---------------- public reads ---------------- */
async function refreshPublic() {
  const c = chainCfg(viewChain);
  $("footChain").textContent = c.name;
  $("footExplorer").href = c.explorer + (hasContract(viewChain) ? "/address/" + contractAddr(viewChain) : "");
  $("nwName").textContent = c.name;
  $("nwId").textContent = viewChain;
  $("nwRpc").textContent = c.rpc.replace("https://", "");
  $("nwExplorer").href = c.explorer;
  $("noContractNote").style.display = hasContract(viewChain) ? "none" : "block";

  const rp = readProvider(viewChain);
  rp.getBlockNumber().then(n => { $("dashBlock").textContent = "block " + n.toLocaleString(); $("footStatus").textContent = c.short + " · block " + n.toLocaleString(); }).catch(() => {});

  if (account) {
    try { $("depBalance").textContent = fmtEth(await rp.getBalance(account)) + " ETH"; } catch { }
  }

  if (!hasContract(viewChain)) {
    slotSet($("statTvl"), "0"); slotSet($("statShares"), "0"); slotSet($("statMine"), "0");
    const st = $("statStatus"); st.textContent = "NOT DEPLOYED"; st.className = "chip idle";
    $("statContract").textContent = "paste address in CONFIG to go live";
    return;
  }

  try {
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const info = await pool.getPoolInfo(account || ZERO);
    slotSet($("statTvl"), fmtEth(info.liquidity));
    slotSet($("statShares"), fmtEth(info.shares, 2));
    const st = $("statStatus");
    st.textContent = info.isPaused ? "PAUSED" : "ACTIVE";
    st.className = "chip " + (info.isPaused ? "off" : "ok");
    $("statContract").textContent = short(contractAddr(viewChain));

    if (account) {
      slotSet($("statMine"), fmtEth(info.myValue));
      const pct = info.shares > 0n ? (Number(info.myShares * 10000n / info.shares) / 100).toFixed(2) : "0";
      $("statMinePct").textContent = fmtEth(info.myShares, 2) + " HF-LP · " + pct + "% of pool";
      $("wdBalance").textContent = fmtEth(info.myShares, 4) + " HF-LP";
    } else {
      $("statMinePct").textContent = "connect wallet to view";
    }
  } catch (e) {
    console.warn(e);
    const st = $("statStatus"); st.textContent = "READ ERROR"; st.className = "chip off";
  }
}

/* quotes */
$("depAmount").addEventListener("input", async () => {
  const v = $("depAmount").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0 || !hasContract(viewChain)) { $("depQuote").textContent = "—"; $("depSharePct").textContent = "—"; return; }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const wei = ethers.parseEther(v);
    const shares = await pool.previewDeposit(wei);
    const total = await pool.totalShares();
    $("depQuote").textContent = fmtEth(shares, 4) + " HF-LP";
    const after = total + shares;
    $("depSharePct").textContent = after > 0n ? (Number(shares * 10000n / after) / 100).toFixed(2) + "%" : "—";
  } catch { $("depQuote").textContent = "—"; }
});

$("wdShares").addEventListener("input", async () => {
  const v = $("wdShares").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0 || !hasContract(viewChain)) { $("wdQuote").textContent = "—"; $("wdRemain").textContent = "—"; return; }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const shares = ethers.parseEther(v);
    const total = await pool.totalShares();
    const liq = await pool.totalLiquidity();
    const out = total > 0n ? shares * liq / total : 0n;
    $("wdQuote").textContent = fmtEth(out) + " ETH";
    const mine = account ? await pool.sharesOf(account) : 0n;
    const remShares = mine > shares ? mine - shares : 0n;
    const remVal = total > 0n ? remShares * liq / total : 0n;
    $("wdRemain").textContent = fmtEth(remVal) + " ETH";
  } catch { $("wdQuote").textContent = "—"; }
});

$("depMax").addEventListener("click", async () => {
  if (!account) return;
  try {
    const bal = await readProvider(viewChain).getBalance(account);
    const gas = ethers.parseEther("0.0002");
    const usable = bal > gas ? bal - gas : 0n;
    $("depAmount").value = ethers.formatEther(usable);
    $("depAmount").dispatchEvent(new Event("input"));
  } catch { }
});
$("wdMax").addEventListener("click", async () => {
  if (!account || !hasContract(viewChain)) return;
  try {
    const s = await new ethers.Contract(contractAddr(viewChain), POOL_ABI, readProvider(viewChain)).sharesOf(account);
    $("wdShares").value = ethers.formatEther(s);
    $("wdShares").dispatchEvent(new Event("input"));
  } catch { }
});

/* ---------------- transactions ---------------- */
async function withSigner() {
  if (!account) { await connectWallet(); if (!account) return null; }
  const net = await browserProvider.getNetwork();
  if (Number(net.chainId) !== viewChain) { await switchNetwork(viewChain); }
  signer = await browserProvider.getSigner();
  return signer;
}

$("depBtn").addEventListener("click", async () => {
  if (!account) { toast("Pehle upar <b>Connect Wallet</b> karein — phir deposit chalega.", true); return; }
  if (!hasContract(viewChain)) { toast("Is network par pool contract set nahin. Wallet tab se <b>Robinhood Chain Mainnet (4663)</b> select karein.", true); return; }
  const v = $("depAmount").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0) { toast("Enter a valid ETH amount.", true); return; }
  const s = await withSigner(); if (!s) return;
  try {
    $("depBtn").disabled = true; $("depBtn").textContent = "Confirm in wallet…";
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, s);
    const tx = await pool.deposit({ value: ethers.parseEther(v) });
    $("depBtn").textContent = "Depositing…";
    toast("Transaction sent — " + txLink(tx.hash, viewChain));
    await tx.wait();
    toast("Deposit confirmed. " + txLink(tx.hash, viewChain));
    $("depAmount").value = "";
    refreshPublic(); loadMyEvents();
  } catch (e) {
    toast("Deposit failed: " + (e.shortMessage || e.message), true);
  } finally {
    $("depBtn").disabled = false; $("depBtn").textContent = "Deposit ETH";
  }
});

async function doWithdraw(sharesWei) {
  if (!account) { toast("Pehle <b>Connect Wallet</b> karein.", true); return; }
  if (!hasContract(viewChain)) { toast("Is network par pool contract set nahin. Mainnet (4663) select karein.", true); return; }
  const s = await withSigner(); if (!s) return;
  try {
    $("wdBtn").disabled = true; $("wdAllBtn").disabled = true;
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, s);
    const tx = sharesWei === null ? await pool.withdrawAll() : await pool.withdraw(sharesWei);
    toast("Transaction sent — " + txLink(tx.hash, viewChain));
    await tx.wait();
    toast("Withdrawal confirmed. " + txLink(tx.hash, viewChain));
    $("wdShares").value = "";
    refreshPublic(); loadMyEvents();
  } catch (e) {
    toast("Withdraw failed: " + (e.shortMessage || e.message), true);
  } finally {
    $("wdBtn").disabled = false; $("wdAllBtn").disabled = false;
  }
}
$("wdBtn").addEventListener("click", () => {
  const v = $("wdShares").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0) { toast("Enter shares to burn.", true); return; }
  doWithdraw(ethers.parseEther(v));
});
$("wdAllBtn").addEventListener("click", () => doWithdraw(null));

/* ---------------- user activity (own events) ---------------- */
async function loadMyEvents() {
  const tb = $("myEvents");
  if (!account || !hasContract(viewChain)) { tb.innerHTML = '<tr><td colspan="4" class="empty">Connect your wallet to load your on-chain activity.</td></tr>'; return; }
  tb.innerHTML = '<tr><td colspan="4" class="empty">Reading chain…</td></tr>';
  const evs = await fetchPoolEvents(viewChain, account);
  if (!evs.length) { tb.innerHTML = '<tr><td colspan="4" class="empty">No deposits or withdrawals yet for this wallet.</td></tr>'; return; }
  tb.innerHTML = evs.map(e => `<tr>
    <td><span class="badge ${e.type === "DEPOSIT" ? "in" : "out"}">${e.type}</span></td>
    <td class="mono">${fmtEth(e.amount)}</td>
    <td class="mono">${fmtEth(e.shares, 3)}</td>
    <td>${txLink(e.tx, viewChain)}</td></tr>`).join("");
}

/* event fetching: Blockscout API first, RPC fallback */
async function fetchPoolEvents(chainId, onlyUser = null) {
  const c = chainCfg(chainId);
  const addr = contractAddr(chainId);
  const t0 = ethers.id("Deposited(address,uint256,uint256)");
  const t1 = ethers.id("Withdrawn(address,uint256,uint256)");
  let logs = [];
  try {
    const url = `${c.explorer}/api?module=logs&action=getLogs&fromBlock=0&toBlock=latest&address=${addr}&topic0_1_opr=or&topic0=${t0}&topic1=${t1}`;
    const r = await fetch(url);
    const j = await r.json();
    if (j.status === "1" && Array.isArray(j.result)) logs = j.result;
    else throw new Error("api");
  } catch {
    try {
      const rp = readProvider(chainId);
      const latest = await rp.getBlockNumber();
      const step = 90000;
      for (let to = latest; to > 0 && logs.length < 60; to -= step) {
        const from = Math.max(0, to - step);
        logs.push(...await rp.getLogs({ address: addr, topics: [[t0, t1]], fromBlock: from, toBlock: to }));
        if (from === 0) break;
      }
    } catch { }
  }
  const itf = iface();
  const out = [];
  for (const l of logs) {
    try {
      const p = itf.parseLog({ topics: l.topics, data: l.data });
      const user = p.args.user || p.args[0];
      if (onlyUser && user.toLowerCase() !== onlyUser.toLowerCase()) continue;
      out.push({
        type: p.name === "Deposited" ? "DEPOSIT" : "WITHDRAW",
        user,
        amount: p.args.amount ?? p.args[1],
        shares: p.name === "Deposited" ? (p.args.sharesMinted ?? p.args[2]) : (p.args.sharesBurned ?? p.args[2]),
        tx: l.transactionHash,
        block: Number(l.blockNumber)
      });
    } catch { }
  }
  return out.sort((a, b) => b.block - a.block).slice(0, 25);
}

/* ---------------- wallet tab render ---------------- */
function renderWalletBox() {
  const box = $("walletBox");
  if (!account) return;
  box.innerHTML = `
    <div class="kv"><span class="k">ADDRESS</span><span class="v mono" style="font-size:12px">${addrLink(account, viewChain)}</span></div>
    <div class="kv"><span class="k">ETH BALANCE</span><span class="v mono" id="wbBal">reading…</span></div>
    <div class="kv"><span class="k">POOL SHARES</span><span class="v mono" id="wbShares">—</span></div>
    <div class="kv"><span class="k">POOL VALUE</span><span class="v mono" id="wbValue">—</span></div>
    <div style="height:16px"></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-ghost" onclick="navigator.clipboard.writeText('${account}');toast('Address copied.')">Copy address</button>
      <a class="btn btn-ghost" target="_blank" rel="noopener" href="${chainCfg(viewChain).explorer}/address/${account}">View on explorer</a>
      <button class="btn btn-ghost" onclick="location.reload()">Disconnect</button>
    </div>`;
  readProvider(viewChain).getBalance(account).then(b => { const el = $("wbBal"); if (el) el.textContent = fmtEth(b) + " ETH"; }).catch(() => {});
  if (hasContract(viewChain)) {
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, readProvider(viewChain));
    pool.sharesOf(account).then(s => { const el = $("wbShares"); if (el) el.textContent = fmtEth(s, 4) + " HF-LP"; }).catch(() => {});
    pool.valueOf(account).then(v => { const el = $("wbValue"); if (el) el.textContent = fmtEth(v) + " ETH"; }).catch(() => {});
  }
}

/* ---------------- site settings (name / logo / socials / theme) ---------------- */
let DEFAULT_BRAND_LOGO = null;
function applySiteSettings(s) {
  if (!s) return;
  const name = (s.site_name || "HoodFi").trim() || "HoodFi";
  document.title = name + " — Liquidity Protocol on Robinhood Chain";
  document.querySelectorAll(".js-sitename").forEach(el => {
    el.textContent = el.hasAttribute("data-upper") ? name.toUpperCase() : name;
  });
  const wrap = $("brandLogoWrap");
  if (wrap) {
    if (DEFAULT_BRAND_LOGO === null) DEFAULT_BRAND_LOGO = wrap.innerHTML;
    wrap.innerHTML = s.logo
      ? `<img src="${s.logo}" alt="${name}" style="width:26px;height:26px;object-fit:contain;border-radius:7px;display:block">`
      : DEFAULT_BRAND_LOGO;
  }
  const tg = $("tgLink"), x = $("xLink");
  if (tg) { if (s.tg_url) { tg.href = s.tg_url; tg.style.display = ""; } else tg.style.display = "none"; }
  if (x)  { if (s.x_url)  { x.href  = s.x_url;  x.style.display  = ""; } else x.style.display  = "none"; }
  document.body.classList.remove("theme-1", "theme-2", "theme-3");
  document.body.classList.add("theme-" + (s.theme || 1));
}

function markTheme(t) {
  document.querySelectorAll(".theme-opt").forEach(o => o.classList.toggle("sel", +o.dataset.theme === t));
  const r = document.querySelector(`input[name="themePick"][value="${t}"]`);
  if (r) r.checked = true;
}
function currentSettings() {
  return {
    site_name: ($("setName").value.trim() || "HoodFi"),
    tg_url: $("setTg").value.trim(),
    x_url: $("setX").value.trim(),
    contract_4663: $("setCaMain").value.trim(),
    contract_46630: $("setCaTest").value.trim(),
    theme: +(document.querySelector('input[name="themePick"]:checked')?.value || 1),
    logo: (SITE_SETTINGS && SITE_SETTINGS.logo) || ""
  };
}
async function saveSettings() {
  const s = currentSettings();
  $("settingsMsg").style.color = "var(--mint-deep)";
  if (window.STATIC_DEMO) {
    localStorage.setItem("hoodfi_settings", JSON.stringify(s));
    window.SITE_SETTINGS = s; applySiteSettings(s);
    $("settingsMsg").textContent = "Saved ✓ (demo preview: browser storage)";
    return;
  }
  try {
    const r = await fetch("?action=save_settings", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(s) });
    const j = await r.json();
    if (j.ok) {
      window.SITE_SETTINGS = j.settings; applySiteSettings(j.settings);
      $("settingsMsg").textContent = "Saved ✓ — live for all visitors";
      refreshPublic(); if (APP.action === "admin") refreshAdmin();
    }
    else { $("settingsMsg").style.color = "var(--red)"; $("settingsMsg").textContent = "Save failed: " + (j.error || ""); }
  } catch { $("settingsMsg").style.color = "var(--red)"; $("settingsMsg").textContent = "Save failed."; }
}
async function uploadLogo() {
  const f = $("setLogo").files[0];
  if (!f) return;
  if (window.STATIC_DEMO) {
    const rd = new FileReader();
    rd.onload = () => {
      const s = { ...currentSettings(), logo: rd.result };
      localStorage.setItem("hoodfi_settings", JSON.stringify(s));
      window.SITE_SETTINGS = s; applySiteSettings(s);
      const p = $("logoPreview"); p.src = rd.result; p.style.display = "block";
      toast("Logo updated (demo preview).");
    };
    rd.readAsDataURL(f);
    return;
  }
  const fd = new FormData();
  fd.append("logo", f);
  try {
    const r = await fetch("?action=upload_logo", { method: "POST", body: fd });
    const j = await r.json();
    if (j.ok) {
      window.SITE_SETTINGS = j.settings; applySiteSettings(j.settings);
      const p = $("logoPreview"); p.src = j.settings.logo; p.style.display = "block";
      toast("Logo updated — live for all visitors.");
    } else toast("Logo upload failed: " + (j.error || ""), true);
  } catch { toast("Logo upload failed.", true); }
}
function initSettingsForm() {
  const s = SITE_SETTINGS || {};
  $("setName").value = s.site_name || "";
  $("setTg").value = s.tg_url || "";
  $("setX").value = s.x_url || "";
  $("setCaMain").value = s.contract_4663 || "";
  $("setCaTest").value = s.contract_46630 || "";
  markTheme(+(s.theme || 1));
  if (s.logo) { const p = $("logoPreview"); p.src = s.logo; p.style.display = "block"; }
  document.querySelectorAll(".theme-opt").forEach(o => o.addEventListener("click", () => {
    markTheme(+o.dataset.theme);
    applySiteSettings({ ...currentSettings(), theme: +o.dataset.theme });  // instant preview
  }));
  $("setLogo").addEventListener("change", uploadLogo);
  $("saveSettings").addEventListener("click", saveSettings);
}

/* ---------------- admin ---------------- */
async function initAdmin() { initSettingsForm(); await refreshAdmin(); }

async function refreshAdmin() {
  const c = chainCfg(viewChain);
  $("admNet").textContent = c.name + " (chain " + viewChain + ")";
  const addr = contractAddr(viewChain);
  $("admContract").innerHTML = hasContract(viewChain)
    ? `<a class="link" target="_blank" rel="noopener" href="${c.explorer}/address/${addr}">${addr}</a>`
    : "not configured";

  const t0 = performance.now();
  try {
    const rp = readProvider(viewChain);
    $("admBlock").textContent = (await rp.getBlockNumber()).toLocaleString();
    $("admLatency").textContent = Math.round(performance.now() - t0) + " ms";
  } catch { $("admLatency").textContent = "RPC error"; }

  if (!hasContract(viewChain)) {
    slotSet($("admTvl"), "0"); slotSet($("admShares"), "0"); slotSet($("admUsers"), "0"); slotSet($("admOps"), "0");
    $("admOwner").textContent = "—";
    $("admPaused").innerHTML = '<span class="chip idle">NOT DEPLOYED</span>';
    $("admEvents").innerHTML = '<tr><td colspan="4" class="empty">Deploy LiquidityPool.sol and paste its address in CONFIG to see live data.</td></tr>';
    return;
  }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(addr, POOL_ABI, rp);
    const info = await pool.getPoolInfo(ZERO);
    slotSet($("admTvl"), fmtEth(info.liquidity));
    slotSet($("admShares"), fmtEth(info.shares, 2));
    $("admOwner").innerHTML = `<a class="link" target="_blank" rel="noopener" href="${c.explorer}/address/${info.contractOwner}">${short(info.contractOwner)}</a>`;
    $("admPaused").innerHTML = info.isPaused ? '<span class="chip off">PAUSED</span>' : '<span class="chip ok">ACTIVE</span>';

    const evs = await fetchPoolEvents(viewChain);
    const users = new Set(evs.map(e => e.user.toLowerCase()));
    slotSet($("admUsers"), String(users.size));
    slotSet($("admOps"), evs.filter(e => e.type === "DEPOSIT").length + " / " + evs.filter(e => e.type === "WITHDRAW").length);
    $("admEvents").innerHTML = evs.length ? evs.slice(0, 12).map(e => `<tr>
      <td><span class="badge ${e.type === "DEPOSIT" ? "in" : "out"}">${e.type}</span></td>
      <td class="mono">${addrLink(e.user, viewChain)}</td>
      <td class="mono">${fmtEth(e.amount)}</td>
      <td>${txLink(e.tx, viewChain)}</td></tr>`).join("")
      : '<tr><td colspan="4" class="empty">No events yet — the pool is waiting for its first deposit.</td></tr>';
  } catch (e) {
    console.warn(e);
    toast("Admin read failed: " + (e.shortMessage || e.message), true);
  }
}
$("adminRefresh") && $("adminRefresh").addEventListener("click", refreshAdmin);

/* ---------------- wire up ---------------- */
$("connectBtn").addEventListener("click", connectWallet);
$("heroConnect").addEventListener("click", connectWallet);
$("walletConnect") && $("walletConnect").addEventListener("click", connectWallet);
$("switchTestnet").addEventListener("click", () => switchNetwork(46630));
$("switchMainnet").addEventListener("click", () => switchNetwork(4663));

/* reveal on scroll */
const io = new IntersectionObserver(es => es.forEach(e => e.isIntersecting && e.target.classList.add("in")), { threshold: .12 });
document.querySelectorAll(".reveal").forEach(el => io.observe(el));

/* boot */
applySiteSettings(window.SITE_SETTINGS);
refreshPublic();
refreshTimer = setInterval(() => {
  refreshPublic();
  if (APP.isAdmin && APP.action === "admin") refreshAdmin();
}, CONFIG.REFRESH_MS);
</script>
</body>
</html>
