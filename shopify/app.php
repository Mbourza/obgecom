<?php
// shopify/app.php

// Forward webhook calls like /shopify/app.php/dashboard/webhooks/xxx.php to the real file
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$pos  = strpos($uri, 'app.php/');
$path = $_SERVER['PATH_INFO'] ?? ($pos !== false ? substr($uri, $pos + 7) : '');

if ($path !== '') {
    if (preg_match('#^/dashboard/webhooks/([a-z_]+\.php)$#', $path, $m)
        && is_file(__DIR__ . '/../dashboard/webhooks/' . $m[1])) {
        require __DIR__ . '/../dashboard/webhooks/' . $m[1];
        exit;
    }
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OBGecom — App Installed</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --bg: #0F1424;
    --card: #161C31;
    --border: #262E4A;
    --text: #F3F5FA;
    --text-muted: #8B93AC;
    --accent: #34D399;
    --accent-dim: rgba(52, 211, 153, 0.15);
    --pending: #F5B942;
    --pending-dim: rgba(245, 185, 66, 0.15);
  }

  * { box-sizing: border-box; }

  body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:
      radial-gradient(circle at 20% -10%, rgba(52,211,153,0.08), transparent 45%),
      radial-gradient(circle at 100% 10%, rgba(88,101,242,0.10), transparent 40%),
      var(--bg);
    font-family: 'Inter', -apple-system, sans-serif;
    color: var(--text);
    padding: 24px;
  }

  .card{
    width: 100%;
    max-width: 440px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px 36px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
  }

  .badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding: 5px 12px;
    border-radius: 999px;
    background: var(--accent-dim);
    color: var(--accent);
    font-size: 12.5px;
    font-weight: 600;
    letter-spacing: 0.02em;
    margin-bottom: 20px;
  }
  .badge svg{ width:13px; height:13px; }

  h1{
    font-family: 'Sora', sans-serif;
    font-size: 24px;
    font-weight: 700;
    line-height: 1.3;
    margin: 0 0 10px;
    letter-spacing: -0.01em;
  }

  p.sub{
    font-size: 14.5px;
    line-height: 1.6;
    color: var(--text-muted);
    margin: 0 0 30px;
  }
  p.sub strong{ color: var(--text); font-weight: 600; }

  .steps{
    display:flex;
    align-items:flex-start;
    margin-bottom: 32px;
  }

  .step{
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    width: 108px;
  }

  .step-icon{
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
    flex-shrink:0;
  }

  .step-icon.done{
    background: var(--accent);
    color: #0F1424;
  }
  .step-icon.done svg{ width:15px; height:15px; }

  .step-icon.pending{
    background: var(--pending-dim);
    color: var(--pending);
    border: 1.5px solid var(--pending);
    position: relative;
  }
  .step-icon.pending::after{
    content:'';
    position:absolute;
    inset:-4px;
    border-radius:50%;
    border: 1.5px solid var(--pending);
    opacity: 0.6;
    animation: pulse 1.8s ease-out infinite;
  }
  @keyframes pulse{
    0% { transform: scale(0.9); opacity: 0.6; }
    100% { transform: scale(1.5); opacity: 0; }
  }

  .step-label{
    font-size: 12.5px;
    font-weight: 500;
    color: var(--text);
    line-height: 1.35;
  }
  .step-label.faint{ color: var(--text-muted); }

  .connector{
    flex:1;
    height: 2px;
    background: var(--accent);
    margin-top: 15px;
  }

  a.cta{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    width:100%;
    padding: 13px 20px;
    background: var(--text);
    color: #10121C;
    text-decoration:none;
    font-weight: 600;
    font-size: 14.5px;
    border-radius: 10px;
    transition: opacity 0.15s ease, transform 0.15s ease;
  }
  a.cta:hover{ opacity: 0.88; transform: translateY(-1px); }
  a.cta:focus-visible{ outline: 2px solid var(--accent); outline-offset: 2px; }
  a.cta svg{ width:15px; height:15px; }

  .hint{
    text-align:center;
    font-size: 12.5px;
    color: var(--text-muted);
    margin: 14px 0 0;
  }

  @media (prefers-reduced-motion: reduce){
    .step-icon.pending::after{ animation: none; }
  }
</style>
</head>
<body>

  <div class="card">
    <span class="badge">
      <svg viewBox="0 0 20 20" fill="none"><path d="M5 10.5l3.2 3.2L15 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      App installed
    </span>

    <h1>Your Shopify store is connected to OBGecom</h1>
    <p class="sub">One step left. Log in to your <strong>OBGecom dashboard</strong> and connect your store from <strong>Settings</strong> to start syncing.</p>

    <div class="steps">
      <div class="step">
        <div class="step-icon done">
          <svg viewBox="0 0 20 20" fill="none"><path d="M5 10.5l3.2 3.2L15 6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="step-label">App installed</div>
      </div>

      <div class="connector"></div>

      <div class="step">
        <div class="step-icon pending">2</div>
        <div class="step-label faint">Connect store in Settings</div>
      </div>
    </div>

    <a class="cta" href="https://obgecom.com/dashboard/">
      Open OBGecom Dashboard
      <svg viewBox="0 0 20 20" fill="none"><path d="M4 10h11m0 0l-4-4m4 4l-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <p class="hint">After logging in, head to Settings to finish connecting your store.</p>
  </div>

</body>
</html>