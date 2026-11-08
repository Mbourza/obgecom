<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="shopify-api-key" content="65ca1eee04a0891563664e09781d570b">
<title>Obgecom — Automatisez vos commandes. Livrez plus. Perdez moins.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<style>
:root{
  /* === BRAND (sampled exactly from logo) === */
  --violet:#6631E1; --violet-dk:#4F22B8; --violet-lt:#EDE5FB; --violet-pale:#F6F2FD;
  --navy:#2A1669; --navy-soft:#3D2980;
  --paper:#FFFFFF; --paper2:#FAF8FE; --paper3:#F1ECFA;
  --ink:#16101F; --ink-soft:#4A4358;
  --line:#E4DBF7; --line-dk:#CDBBF2;
  --mute:#8A82A0;
  --clay:#D6483B; --clay-lt:#FBE7E4; --clay-dk:#A6362B;
  --carrier:#1F6F6B; --carrier-lt:#E2F2F0;
  --font-display:'Fraunces',serif;
  --font-body:'Inter',sans-serif;
  --font-mono:'IBM Plex Mono',monospace;
  --nav-h:76px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--paper);color:var(--ink);overflow-x:hidden;line-height:1.6;}
::-webkit-scrollbar{width:5px;}
::-webkit-scrollbar-track{background:var(--paper3);}
::-webkit-scrollbar-thumb{background:var(--violet);border-radius:3px;}
@media (prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;}}
a{color:inherit;}

/* subtle ambient grain kept minimal on white ground */
.grain{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.02;mix-blend-mode:multiply;
  background-image:radial-gradient(circle at 20% 30%,#000 0,transparent 1px),radial-gradient(circle at 70% 60%,#000 0,transparent 1px);
  background-size:4px 4px,6px 6px;}

/* === NAV === */
nav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);display:flex;align-items:center;justify-content:space-between;padding:0 48px;z-index:100;transition:background .3s,border-color .3s;border-bottom:1px solid transparent;}
nav.scrolled{background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom-color:var(--line);}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;height:38px;}
.nav-logo img{height:34px;width:auto;display:block;}
.nav-links{display:flex;align-items:center;gap:36px;list-style:none;}
.nav-links a{color:var(--ink-soft);text-decoration:none;font-size:14px;font-weight:500;transition:color .2s;position:relative;}
.nav-links a:not(.nav-cta):not(.btn-login-header)::after{content:'';position:absolute;left:0;bottom:-6px;width:0;height:2px;background:var(--violet);transition:width .25s;border-radius:2px;}
.nav-links a:not(.nav-cta):not(.btn-login-header):hover::after{width:100%;}
.nav-links a:hover{color:var(--ink);}
.nav-cta{background:var(--violet);color:#fff!important;padding:10px 22px;border-radius:8px;font-weight:600!important;transition:all .25s!important;letter-spacing:.01em;}
.nav-cta:hover{background:var(--violet-dk)!important;transform:translateY(-1px);box-shadow:0 8px 20px rgba(102,49,225,.3);}
.btn-login-header{border:1.5px solid var(--line-dk);color:var(--ink)!important;padding:9px 18px;border-radius:8px;font-weight:600!important;transition:all .25s;}
.btn-login-header:hover{background:var(--violet-pale);border-color:var(--violet);}

.hamburger{display:none;flex-direction:column;justify-content:space-between;width:26px;height:18px;cursor:pointer;z-index:101;}
.hamburger span{display:block;height:2px;width:100%;background:var(--ink);transition:all .3s ease;}
.hamburger.active span:nth-child(1){transform:translateY(8px) rotate(45deg);}
.hamburger.active span:nth-child(2){opacity:0;}
.hamburger.active span:nth-child(3){transform:translateY(-8px) rotate(-45deg);}

.mobile-menu{position:fixed;top:0;right:-100%;width:78%;max-width:320px;height:100vh;background:#fff;z-index:99;padding:100px 28px 40px;transition:right .4s cubic-bezier(0.2,0.9,0.4,1.1);box-shadow:-8px 0 30px rgba(102,49,225,.12);border-left:1px solid var(--line);display:flex;flex-direction:column;gap:6px;}
.mobile-menu.open{right:0;}
.mobile-menu a{color:var(--ink-soft);text-decoration:none;font-size:18px;font-weight:500;padding:14px 0;border-bottom:1px solid var(--line);transition:all .2s;display:block;}
.mobile-menu a:hover{color:var(--violet);padding-left:8px;}
.mobile-cta{background:var(--violet);color:#fff!important;text-align:center;border-radius:8px;padding:14px!important;margin-top:16px;font-weight:700!important;border-bottom:none!important;}
.mobile-login{border:1.5px solid var(--line-dk);border-radius:8px;text-align:center;margin-top:10px;}

/* === HERO === */
.hero{min-height:100vh;display:flex;align-items:center;position:relative;overflow:hidden;padding:calc(var(--nav-h) + 60px) 48px 80px;background:var(--paper);}
.hero-bg{position:absolute;inset:0;overflow:hidden;}
.hero-bg::before{content:'';position:absolute;top:-25%;right:-12%;width:760px;height:760px;border-radius:50%;background:radial-gradient(circle,rgba(102,49,225,.10) 0%,transparent 70%);}
.hero-bg::after{content:'';position:absolute;bottom:-20%;left:-12%;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(42,22,105,.06) 0%,transparent 70%);}
.hero-inner{position:relative;z-index:2;max-width:1240px;margin:0 auto;width:100%;display:grid;grid-template-columns:1.05fr .95fr;gap:64px;align-items:center;}
.hero-badge{display:inline-flex;align-items:center;gap:9px;border:1.5px solid var(--line-dk);border-radius:100px;padding:6px 16px 6px 12px;font-size:12.5px;color:var(--violet-dk);font-weight:600;margin-bottom:30px;font-family:var(--font-mono);opacity:0;animation:fadeUp .6s ease forwards .1s;background:var(--violet-pale);}
.hero-badge-dot{width:7px;height:7px;border-radius:50%;background:var(--navy);animation:pulse 2s ease-in-out infinite;}
h1.hero-title{font-family:var(--font-display);font-size:clamp(38px,5vw,62px);font-weight:600;line-height:1.06;letter-spacing:-1.5px;margin-bottom:26px;opacity:0;animation:fadeUp .6s ease forwards .2s;color:var(--ink);}
h1.hero-title .accent{color:var(--violet);font-style:italic;font-weight:500;}
.hero-sub{font-size:18px;color:var(--ink-soft);line-height:1.7;max-width:500px;margin-bottom:38px;opacity:0;animation:fadeUp .6s ease forwards .3s;}
.hero-actions{display:flex;gap:14px;align-items:center;flex-wrap:wrap;opacity:0;animation:fadeUp .6s ease forwards .4s;}
.btn-primary{display:inline-flex;align-items:center;gap:10px;background:var(--violet);color:#fff;padding:16px 30px;border-radius:10px;font-weight:600;font-size:15.5px;text-decoration:none;border:none;cursor:pointer;transition:all .25s;font-family:var(--font-body);}
.btn-primary:hover{background:var(--violet-dk);transform:translateY(-2px);box-shadow:0 14px 32px rgba(102,49,225,.35);}
.btn-secondary{display:inline-flex;align-items:center;gap:9px;color:var(--ink);padding:16px 26px;border-radius:10px;font-weight:500;font-size:14.5px;text-decoration:none;border:1.5px solid var(--line-dk);background:transparent;transition:all .25s;font-family:var(--font-body);}
.btn-secondary:hover{background:var(--violet-pale);border-color:var(--violet);}
.hero-trust{display:flex;align-items:center;gap:18px;margin-top:42px;opacity:0;animation:fadeUp .6s ease forwards .5s;padding-top:24px;border-top:1px dashed var(--line-dk);}
.trust-avatars{display:flex;}
.trust-avatars span{width:30px;height:30px;border-radius:50%;border:2px solid #fff;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;margin-left:-8px;font-family:var(--font-mono);}
.trust-avatars span:first-child{margin-left:0;}
.hero-trust p{font-size:13px;color:var(--mute);}
.scroll-hint{position:absolute;bottom:28px;left:50%;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;gap:6px;opacity:0;animation:fadeUp .6s ease forwards 1.2s;cursor:pointer;}
.scroll-hint span{font-size:10.5px;color:var(--mute);text-transform:uppercase;letter-spacing:.12em;font-family:var(--font-mono);}
.scroll-line{width:1px;height:36px;background:linear-gradient(var(--violet),transparent);animation:scrollAnim 2s ease-in-out infinite;}
@keyframes scrollAnim{0%,100%{transform:scaleY(1);opacity:1;}50%{transform:scaleY(.5);opacity:.5;}}
@keyframes fadeUp{from{opacity:0;transform:translateY(22px);}to{opacity:1;transform:translateY(0);}}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.4);}}

/* === HERO MANIFEST (signature element, re-skinned in brand violet/navy) === */
.hero-manifest{position:relative;opacity:0;animation:fadeLeft .7s ease forwards .4s;}
@keyframes fadeLeft{from{opacity:0;transform:translateX(26px);}to{opacity:1;transform:translateX(0);}}
.manifest-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 30px 70px rgba(42,22,105,.16);position:relative;overflow:hidden;}
.manifest-card::before{content:'';position:absolute;left:0;right:0;top:0;height:4px;background:linear-gradient(90deg,var(--violet),var(--navy));}
.manifest-head{padding:24px 26px 16px;border-bottom:1px dashed var(--line-dk);display:flex;justify-content:space-between;align-items:flex-start;margin-top:4px;}
.manifest-head h5{font-family:var(--font-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);margin-bottom:6px;}
.manifest-head strong{font-family:var(--font-display);font-size:20px;font-weight:600;color:var(--ink);}
.manifest-id{text-align:right;font-family:var(--font-mono);font-size:11px;color:var(--mute);}
.manifest-id b{display:block;font-size:15px;color:var(--violet-dk);font-weight:700;}
.manifest-body{padding:18px 26px;}
.manifest-row{display:grid;grid-template-columns:auto 1fr auto auto;gap:14px;align-items:center;padding:13px 0;border-bottom:1px solid var(--paper3);font-size:13px;}
.manifest-row:last-child{border-bottom:none;}
.mr-id{font-family:var(--font-mono);color:var(--mute);font-size:12px;}
.mr-name{color:var(--ink);font-weight:500;}
.mr-city{font-family:var(--font-mono);font-size:11px;color:var(--mute);}
.mr-status{font-family:var(--font-mono);font-size:10.5px;font-weight:600;padding:4px 10px;border-radius:100px;letter-spacing:.02em;white-space:nowrap;}
.mr-status.confirmed{background:var(--violet-lt);color:var(--violet-dk);}
.mr-status.pending{background:var(--clay-lt);color:var(--clay-dk);}
.mr-status.dispatched{background:var(--carrier-lt);color:var(--carrier);}
.manifest-row.stamping .mr-status{animation:stampIn .4s ease;}
@keyframes stampIn{0%{transform:scale(1.8) rotate(-8deg);opacity:0;}60%{transform:scale(.95) rotate(2deg);opacity:1;}100%{transform:scale(1) rotate(0);}}
.manifest-foot{padding:18px 26px 22px;display:flex;justify-content:space-between;align-items:center;border-top:1px dashed var(--line-dk);}
.manifest-stat{text-align:left;}
.manifest-stat .num{font-family:var(--font-display);font-size:26px;font-weight:600;color:var(--violet);line-height:1;}
.manifest-stat .lbl{font-size:11px;color:var(--mute);margin-top:2px;}
.stamp-mark{font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--navy);border:2px solid var(--navy);border-radius:6px;padding:6px 12px;transform:rotate(-6deg);letter-spacing:.05em;opacity:0;}
.stamp-mark.show{animation:stampSlam .5s cubic-bezier(.2,1.4,.4,1) forwards;}
@keyframes stampSlam{0%{opacity:0;transform:scale(2.2) rotate(-6deg);}55%{opacity:1;transform:scale(.9) rotate(-6deg);}100%{opacity:1;transform:scale(1) rotate(-6deg);}}
.hero-float-1{position:absolute;top:-18px;right:-20px;background:var(--navy);color:#fff;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 12px 28px rgba(42,22,105,.3);animation:floatCard 4s ease-in-out infinite;z-index:3;}
.hero-float-2{position:absolute;bottom:-16px;left:-26px;background:#fff;border:1px solid var(--line-dk);border-radius:10px;padding:10px 16px;box-shadow:0 12px 28px rgba(42,22,105,.12);animation:floatCard 5s ease-in-out infinite 1s;z-index:3;}
@keyframes floatCard{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
.float-icon{font-size:18px;}
.float-text strong{display:block;font-size:12.5px;font-weight:600;font-family:var(--font-mono);}
.float-text span{font-size:11px;opacity:.75;}

/* === STATS BAR === */
.stats-bar{background:var(--navy);padding:36px 48px;}
.stats-inner{max-width:1240px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);}
.stat-item{text-align:left;padding:0 28px;border-right:1px solid rgba(255,255,255,.14);}
.stat-item:first-child{padding-left:0;}
.stat-item:last-child{border-right:none;}
.stat-number{font-family:var(--font-display);font-size:40px;font-weight:600;letter-spacing:-1px;line-height:1;color:#fff;}
.stat-number .stat-sign{color:#B8A3F0;}
.stat-label{font-size:12.5px;color:#B0A4D6;margin-top:8px;line-height:1.4;font-family:var(--font-mono);}

/* === SECTION COMMONS === */
.section{padding:120px 48px;position:relative;}
.section-inner{max-width:1240px;margin:0 auto;}
.section-tag{display:inline-flex;align-items:center;gap:9px;font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--violet-dk);margin-bottom:20px;font-family:var(--font-mono);}
.section-tag::before{content:'';display:block;width:18px;height:2px;background:var(--violet);border-radius:2px;}
h2.section-title{font-family:var(--font-display);font-size:clamp(28px,4vw,46px);font-weight:600;letter-spacing:-1px;line-height:1.1;margin-bottom:20px;color:var(--ink);}
.section-sub{font-size:17px;color:var(--ink-soft);line-height:1.7;max-width:600px;}
.section-header{margin-bottom:68px;}
.section-header.center{text-align:center;}
.section-header.center .section-sub{margin:0 auto;}
.section-header.center .section-tag{justify-content:center;}

/* === PROBLEM === */
.problem-section{background:var(--paper2);border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
.problem-grid{display:grid;grid-template-columns:1fr 1fr;gap:72px;align-items:center;}
.problem-cards{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.problem-card{background:#fff;border:1px solid var(--line);border-left:3px solid var(--clay);border-radius:10px;padding:22px;transition:all .2s;}
.problem-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(42,22,105,.08);}
.problem-card-icon{font-size:24px;margin-bottom:12px;display:block;}
.problem-card h3{font-family:var(--font-display);font-size:15px;font-weight:600;color:var(--ink);margin-bottom:7px;}
.problem-card p{font-size:13px;color:var(--ink-soft);line-height:1.6;}
.problem-stat{background:var(--clay-lt);border:1px solid var(--clay);border-radius:10px;padding:24px;margin-top:16px;display:flex;align-items:center;gap:18px;}
.problem-stat-num{font-family:var(--font-display);font-size:48px;font-weight:700;color:var(--clay-dk);letter-spacing:-2px;line-height:1;white-space:nowrap;}
.problem-stat p{font-size:13.5px;color:var(--ink-soft);line-height:1.55;}
.problem-stat p strong{color:var(--ink);}

.loss-calc{margin-top:20px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px 22px;}
.loss-calc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.loss-calc-head span{font-family:var(--font-mono);font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--mute);}
.loss-calc-row{display:flex;align-items:center;gap:14px;}
.loss-calc-row input[type="range"]{flex:1;accent-color:var(--clay);height:4px;}
.loss-calc-val{font-family:var(--font-mono);font-weight:600;color:var(--ink);min-width:64px;text-align:right;font-size:14px;}
.loss-calc-result{margin-top:14px;padding-top:14px;border-top:1px dashed var(--line-dk);font-size:13.5px;color:var(--ink-soft);}
.loss-calc-result b{font-family:var(--font-mono);color:var(--clay-dk);font-size:17px;font-weight:700;}

/* === SERVICES === */
.services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--line);border-radius:16px;overflow:hidden;}
.service-card{background:#fff;padding:38px 30px;position:relative;border-right:1px solid var(--line);transition:background .25s;}
.service-card:last-child{border-right:none;}
.service-card:hover{background:var(--violet-pale);}
.service-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:22px;border:1px solid var(--line-dk);}
.s1 .service-icon{background:var(--violet-lt);}
.s2 .service-icon{background:var(--carrier-lt);}
.s3 .service-icon{background:var(--clay-lt);}
.service-num{position:absolute;top:30px;right:28px;font-family:var(--font-mono);font-size:13px;font-weight:600;color:var(--mute);letter-spacing:.05em;}
.service-card h3{font-family:var(--font-display);font-size:19px;font-weight:600;margin-bottom:12px;letter-spacing:-.3px;color:var(--ink);}
.service-card p{font-size:14px;color:var(--ink-soft);line-height:1.7;margin-bottom:22px;}
.service-features{list-style:none;display:flex;flex-direction:column;gap:9px;margin-bottom:24px;}
.service-features li{font-size:13px;color:var(--ink-soft);display:flex;align-items:flex-start;gap:9px;line-height:1.45;}
.s1 .service-features li::before{content:'✓';color:var(--violet);font-weight:700;flex-shrink:0;}
.s2 .service-features li::before{content:'✓';color:var(--carrier);font-weight:700;flex-shrink:0;}
.s3 .service-features li::before{content:'✓';color:var(--clay);font-weight:700;flex-shrink:0;}
.service-kpi{padding-top:18px;border-top:1px dashed var(--line-dk);display:flex;align-items:baseline;gap:10px;}
.service-kpi-val{font-family:var(--font-display);font-size:26px;font-weight:700;line-height:1;}
.s1 .service-kpi-val{color:var(--violet);}
.s2 .service-kpi-val{color:var(--carrier);}
.s3 .service-kpi-val{color:var(--clay);}
.service-kpi-label{font-size:11.5px;color:var(--mute);line-height:1.3;font-family:var(--font-mono);}

/* === HOW IT WORKS === */
.how-section{background:var(--paper2);border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
.how-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:0;position:relative;}
.how-steps::before{content:'';position:absolute;top:27px;left:calc(12.5% + 18px);right:calc(12.5% + 18px);height:2px;background:linear-gradient(90deg,var(--violet),var(--navy));opacity:.25;}
.how-step{padding:0 22px;text-align:left;}
.how-step-num{width:54px;height:54px;border-radius:50%;background:#fff;border:2px solid var(--violet);display:flex;align-items:center;justify-content:center;margin-bottom:22px;font-family:var(--font-mono);font-size:16px;font-weight:700;color:var(--violet-dk);position:relative;z-index:2;box-shadow:0 0 0 6px var(--violet-pale);}
.how-step:nth-child(4) .how-step-num{border-color:var(--navy);color:var(--navy);box-shadow:0 0 0 6px var(--paper3);}
.how-step-icon{font-size:24px;display:block;margin-bottom:14px;}
.how-step h4{font-family:var(--font-display);font-size:16px;font-weight:600;margin-bottom:9px;color:var(--ink);}
.how-step p{font-size:13px;color:var(--ink-soft);line-height:1.6;}
.integrations-strip{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:64px;}
.integ-badge{background:#fff;border:1px solid var(--line-dk);border-radius:8px;padding:9px 16px;font-size:12.5px;color:var(--ink-soft);white-space:nowrap;transition:all .2s;font-family:var(--font-mono);}
.integ-badge:hover{background:var(--violet);color:#fff;border-color:var(--violet);}

/* === FEATURES === */
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:16px;overflow:hidden;}
.feature-item{background:#fff;padding:30px 26px;transition:all .2s;}
.feature-item:hover{background:var(--violet-pale);}
.feature-icon{width:38px;height:38px;border-radius:10px;background:var(--paper3);border:1px solid var(--line-dk);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:16px;}
.feature-item h4{font-family:var(--font-display);font-size:15px;font-weight:600;margin-bottom:8px;color:var(--ink);}
.feature-item p{font-size:13px;color:var(--ink-soft);line-height:1.6;}

/* === PRICING === */
.pricing-section{background:var(--paper2);border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
.payg-container{max-width:880px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:20px;padding:0;box-shadow:0 20px 60px rgba(42,22,105,.12);overflow:hidden;}
.payg-container::before{content:'';display:block;height:4px;background:linear-gradient(90deg,var(--violet),var(--navy));}
.payg-inner{padding:44px 44px 40px;text-align:center;}
.payg-container .pricing-name{font-size:11.5px;letter-spacing:.12em;color:var(--violet-dk);font-weight:700;text-transform:uppercase;margin-bottom:10px;font-family:var(--font-mono);}
.payg-container .payg-badge{display:inline-block;background:var(--violet-lt);border:1px solid var(--violet);color:var(--violet-dk);font-size:11.5px;font-weight:700;padding:4px 16px;border-radius:100px;margin-bottom:22px;font-family:var(--font-mono);}
.payg-container h3{font-family:var(--font-display);font-size:27px;font-weight:600;margin-bottom:8px;color:var(--ink);}
.payg-container .payg-sub{color:var(--ink-soft);font-size:15px;margin-bottom:34px;}
.payg-calc{display:grid;grid-template-columns:1fr 1fr;gap:24px;text-align:left;margin-bottom:28px;background:var(--paper2);border-radius:14px;padding:24px;border:1px solid var(--line);}
.payg-calc label{display:flex;flex-direction:column;gap:6px;font-size:13.5px;font-weight:600;color:var(--ink-soft);font-family:var(--font-mono);}
.payg-calc label span{font-weight:400;font-size:11.5px;color:var(--mute);font-family:var(--font-body);}
.payg-calc input,.payg-calc select{background:#fff;border:1px solid var(--line-dk);border-radius:8px;padding:10px 12px;color:var(--ink);font-size:15px;font-weight:500;font-family:var(--font-mono);width:100%;transition:border-color .2s;}
.payg-calc input:focus,.payg-calc select:focus{outline:none;border-color:var(--violet);}
.payg-result{display:flex;justify-content:space-between;align-items:center;padding:22px 26px;background:var(--violet-lt);border:1px solid var(--violet);border-radius:14px;margin-bottom:30px;flex-wrap:wrap;gap:12px;}
.payg-result .total-label{font-size:14px;color:var(--violet-dk);font-weight:600;font-family:var(--font-mono);text-transform:uppercase;letter-spacing:.04em;}
.payg-result .total-price{font-family:var(--font-display);font-size:32px;font-weight:700;color:var(--violet-dk);}
.payg-result .total-price small{font-size:15px;font-weight:500;color:var(--violet-dk);opacity:.7;margin-left:4px;}
.payg-container .pricing-btn{display:inline-block;padding:15px 46px;border-radius:10px;background:var(--violet);color:#fff;font-weight:600;font-size:15.5px;text-decoration:none;transition:all .25s;border:none;cursor:pointer;font-family:var(--font-body);}
.payg-container .pricing-btn:hover{background:var(--violet-dk);transform:translateY(-2px);box-shadow:0 14px 32px rgba(102,49,225,.35);}
.payg-note{margin-top:22px;font-size:12.5px;color:var(--mute);font-family:var(--font-mono);}
.payg-note strong{color:var(--ink);}

/* === TESTIMONIALS === */
.testimonials-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
.testimonial-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:30px;transition:all .25s;position:relative;}
.testimonial-card:hover{box-shadow:0 16px 36px rgba(42,22,105,.12);transform:translateY(-3px);}
.testimonial-stars{color:var(--clay);font-size:13px;margin-bottom:16px;letter-spacing:2px;}
.testimonial-quote{font-family:var(--font-display);font-size:17px;color:var(--ink);line-height:1.55;margin-bottom:22px;font-weight:400;font-style:italic;}
.testimonial-author{display:flex;align-items:center;gap:12px;}
.testimonial-avatar{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;font-family:var(--font-mono);background:var(--navy);}
.testimonial-name{font-family:var(--font-body);font-size:14px;font-weight:700;color:var(--ink);}
.testimonial-role{font-size:12px;color:var(--mute);margin-top:2px;}
.testimonial-result{margin-top:18px;padding:10px 14px;border-radius:8px;font-size:12.5px;font-weight:600;font-family:var(--font-mono);}

/* === CTA === */
.cta-section{background:var(--violet);position:relative;overflow:hidden;text-align:center;}
.cta-section::before{content:'';position:absolute;top:-50%;right:-10%;width:500px;height:500px;border-radius:50%;background:rgba(255,255,255,.08);}
.cta-section::after{content:'';position:absolute;bottom:-50%;left:-10%;width:400px;height:400px;border-radius:50%;background:rgba(42,22,105,.18);}
.cta-inner{position:relative;z-index:2;max-width:680px;margin:0 auto;}
.cta-inner h2{font-family:var(--font-display);font-size:clamp(32px,5vw,50px);font-weight:600;letter-spacing:-1.5px;line-height:1.08;margin-bottom:20px;color:#fff;}
.cta-inner p{font-size:17px;color:rgba(255,255,255,.82);margin-bottom:38px;line-height:1.65;}
.cta-actions{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}
.btn-white{display:inline-flex;align-items:center;gap:8px;background:#fff;color:var(--violet-dk);padding:16px 32px;border-radius:10px;font-weight:700;font-size:15.5px;text-decoration:none;transition:all .25s;font-family:var(--font-body);}
.btn-white:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(0,0,0,.18);}
.btn-ghost{display:inline-flex;align-items:center;gap:8px;background:transparent;color:#fff;padding:16px 26px;border-radius:10px;font-weight:500;font-size:14.5px;text-decoration:none;border:1.5px solid rgba(255,255,255,.4);transition:all .25s;font-family:var(--font-body);}
.btn-ghost:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.6);}
.cta-trust{display:flex;align-items:center;justify-content:center;gap:22px;margin-top:34px;flex-wrap:wrap;}
.cta-trust-item{display:flex;align-items:center;gap:7px;font-size:12.5px;color:rgba(255,255,255,.78);font-family:var(--font-mono);}
.cta-trust-item::before{content:'✓';font-weight:700;color:#fff;}

/* === FOOTER === */
footer{background:#fff;border-top:1px solid var(--line);padding:64px 48px 40px;}
.footer-inner{max-width:1240px;margin:0 auto;}
.footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:60px;margin-bottom:52px;}
.footer-brand img{height:30px;margin-bottom:14px;}
.footer-brand span{font-size:13.5px;color:var(--ink-soft);line-height:1.7;display:block;margin-bottom:18px;}
.footer-col-title{font-family:var(--font-mono);font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:18px;color:var(--ink);}
.footer-links{list-style:none;display:flex;flex-direction:column;gap:11px;}
.footer-links a{font-size:13.5px;color:var(--ink-soft);text-decoration:none;transition:color .2s;}
.footer-links a:hover{color:var(--violet);}
.footer-bottom{display:flex;justify-content:space-between;align-items:center;padding-top:26px;border-top:1px solid var(--line);font-size:12.5px;color:var(--mute);flex-wrap:wrap;gap:16px;font-family:var(--font-mono);}
.footer-legal{display:flex;gap:22px;}
.footer-legal a{color:var(--mute);text-decoration:none;transition:color .2s;}
.footer-legal a:hover{color:var(--violet);}
.footer-payment{max-width:1240px;margin:36px auto 28px;padding:22px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:28px;flex-wrap:wrap;}
.payment-section{display:flex;align-items:center;gap:22px;flex-wrap:wrap;}
.payment-label{font-size:11px;font-weight:700;color:var(--violet-dk);text-transform:uppercase;letter-spacing:.1em;font-family:var(--font-mono);}
.payment-icons{display:flex;align-items:center;gap:18px;}
.payment-icon{display:inline-flex;align-items:center;justify-content:center;transition:all .25s;opacity:.6;font-size:26px;color:var(--ink-soft);text-decoration:none;}
.payment-icon i{font-size:30px;}
.payment-icon:hover{opacity:1;transform:translateY(-2px);color:var(--violet);}
.secure-badge{display:flex;align-items:center;gap:8px;padding:7px 16px;background:var(--violet-lt);border:1px solid var(--violet);border-radius:100px;font-size:11.5px;font-weight:600;color:var(--violet-dk);font-family:var(--font-mono);}
.secure-badge i{font-size:.85rem;}

/* === REVEAL === */
.reveal{opacity:0;transform:translateY(26px);transition:opacity .7s ease,transform .7s ease;}
.reveal.visible{opacity:1;transform:translateY(0);}
.reveal-delay-1{transition-delay:.08s;}
.reveal-delay-2{transition-delay:.16s;}
.reveal-delay-3{transition-delay:.24s;}
.reveal-delay-4{transition-delay:.32s;}

/* === RESPONSIVE === */
@media(max-width:1024px){
  nav{padding:0 24px;}
  .nav-links{display:none;}
  .hamburger{display:flex;}
  .hero-inner{grid-template-columns:1fr;gap:48px;}
  .stats-inner{grid-template-columns:repeat(2,1fr);gap:24px;row-gap:28px;}
  .stat-item:nth-child(2n){border-right:none;}
  .problem-grid,.services-grid,.features-grid{grid-template-columns:1fr;}
  .services-grid{border:none;border-radius:0;}
  .service-card{border:1px solid var(--line);border-radius:16px;margin-bottom:14px;}
  .features-grid{background:transparent;border:none;gap:14px;border-radius:0;}
  .feature-item{border:1px solid var(--line);border-radius:16px;}
  .how-steps{grid-template-columns:1fr 1fr;row-gap:36px;}
  .how-steps::before{display:none;}
  .testimonials-grid,.footer-top{grid-template-columns:1fr 1fr;}
  .payg-calc{grid-template-columns:1fr;}
  .section{padding:80px 24px;}
  .stats-bar{padding:32px 24px;}
  .hero{padding:calc(var(--nav-h) + 40px) 24px 60px;}
  .payg-inner{padding:36px 24px;}
}
@media(max-width:640px){
  .testimonials-grid,.footer-top{grid-template-columns:1fr;}
  .problem-cards{grid-template-columns:1fr;}
  .hero-float-1,.hero-float-2{display:none;}
  h1.hero-title{font-size:32px;}
  .stat-number{font-size:30px;}
  .problem-stat{flex-direction:column;align-items:flex-start;}
  .payg-result{flex-direction:column;align-items:stretch;text-align:center;}
  .payg-result .total-price{font-size:28px;}
  .manifest-row{grid-template-columns:1fr auto;gap:6px;}
  .mr-id,.mr-city{display:none;}
}
</style>
</head>
<body>
<div class="grain"></div>

<nav id="navbar">
  <a href="./" class="nav-logo"><img src="./assets/img/logo.png" alt="Obgecom logo"></a>
  <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
  <ul class="nav-links">
    <li><a href="#services">Services</a></li>
    <li><a href="#fonctionnalites">Fonctionnalités</a></li>
    <li><a href="#tarifs">Tarifs</a></li>
    <li><a href="#temoignages">Témoignages</a></li>
    <li><a href="#demo" class="nav-cta">Démo gratuite</a></li>
    <li><a href="./lg" class="btn-login-header"><i class="fas fa-key" style="margin-right:6px;"></i>Connexion</a></li>
  </ul>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <a href="#services">Services</a>
  <a href="#fonctionnalites">Fonctionnalités</a>
  <a href="#tarifs">Tarifs</a>
  <a href="#temoignages">Témoignages</a>
  <a href="#demo" class="mobile-cta">Démo gratuite</a>
  <a href="./lg" class="mobile-login"><i class="fas fa-key"></i> Connexion</a>
</div>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-bg"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-badge"><span class="hero-badge-dot"></span>PLATEFORME LIVE · PILOTE 1 MOIS OFFERT</div>
      <h1 class="hero-title">Automatisez vos commandes.<br><span class="accent">Livrez plus.</span> Perdez moins.</h1>
      <p class="hero-sub">Obgecom confirme chaque commande avant expédition via WhatsApp et appel téléphonique, saisit les données automatiquement et les envoie directement à votre transporteur. Zéro retour évitable. Zéro saisie manuelle.</p>
      <div class="hero-actions">
        <a href="mailto:contact@obgecom.com" class="btn-primary">Démarrer gratuitement <span>→</span></a>
        <a href="#services" class="btn-secondary"><span>▶</span> Voir comment ça marche</a>
      </div>
      <div class="hero-trust">
        <div class="trust-avatars"><span>KB</span><span>SL</span><span>MR</span><span>FA</span></div>
        <p>Rejoignez les marchands qui ont réduit leurs retours de 60%</p>
      </div>
    </div>

    <div class="hero-manifest">
      <div class="hero-float-1"><span class="float-icon">✅</span><div class="float-text"><strong>COMMANDE CONFIRMÉE</strong><span>Il y a 12 secondes</span></div></div>

      <div class="manifest-card">
        <div class="manifest-head">
          <div><h5>Manifeste d'expédition</h5><strong>Obgecom Dispatch</strong></div>
          <div class="manifest-id">Lot du jour<b id="manifestDate">26/06</b></div>
        </div>
        <div class="manifest-body" id="manifestBody">
          <div class="manifest-row" data-row>
            <span class="mr-id">#4821</span>
            <span class="mr-name">Karim B.</span>
            <span class="mr-city">Casablanca</span>
            <span class="mr-status pending">En appel</span>
          </div>
          <div class="manifest-row" data-row>
            <span class="mr-id">#4820</span>
            <span class="mr-name">Sara L.</span>
            <span class="mr-city">Rabat</span>
            <span class="mr-status confirmed">Confirmée</span>
          </div>
          <div class="manifest-row" data-row>
            <span class="mr-id">#4819</span>
            <span class="mr-name">Youssef M.</span>
            <span class="mr-city">Fès</span>
            <span class="mr-status dispatched">Expédiée</span>
          </div>
          <div class="manifest-row" data-row>
            <span class="mr-id">#4818</span>
            <span class="mr-name">Imane T.</span>
            <span class="mr-city">Marrakech</span>
            <span class="mr-status pending">WhatsApp envoyé</span>
          </div>
        </div>
        <div class="manifest-foot">
          <div class="manifest-stat"><div class="num" id="manifestPct">94%</div><div class="lbl">taux de confirmation</div></div>
          <div class="stamp-mark" id="stampMark">CONFIRMÉ ✓</div>
        </div>
      </div>

      <div class="hero-float-2"><div class="float-text"><strong style="color:var(--violet-dk)">+15 620 MAD économisés</strong><span style="color:var(--mute)">Ce mois sur les retours évités</span></div></div>
    </div>
  </div>
  <div class="scroll-hint" onclick="document.getElementById('probleme').scrollIntoView({behavior:'smooth'})"><span>Découvrir</span><div class="scroll-line"></div></div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-inner">
    <div class="stat-item"><div class="stat-number">85<span class="stat-sign" style="font-size:.6em">%</span></div><div class="stat-label">du e-commerce MENA<br>en Cash on Delivery</div></div>
    <div class="stat-item"><div class="stat-number"><span class="stat-sign">−</span>60<span class="stat-sign" style="font-size:.6em">%</span></div><div class="stat-label">de retours évitables<br>dès le 1er mois</div></div>
    <div class="stat-item"><div class="stat-number">48<span class="stat-sign" style="font-size:.5em">h</span></div><div class="stat-label">intégration boutique<br>et transporteur</div></div>
    <div class="stat-item"><div class="stat-number">24<span class="stat-sign" style="font-size:.5em">/7</span></div><div class="stat-label">automatisation<br>sans interruption</div></div>
  </div>
</div>

<!-- PROBLEM -->
<section class="section problem-section" id="probleme">
  <div class="section-inner">
    <div class="problem-grid">
      <div>
        <div class="section-tag reveal">Le problème</div>
        <h2 class="section-title reveal reveal-delay-1">Votre activité perd de l'argent chaque jour sans le savoir.</h2>
        <p class="section-sub reveal reveal-delay-2">Chaque commande non confirmée, chaque adresse erronée, chaque appel manuel est une perte directe sur votre marge.</p>
        <div class="problem-stat reveal reveal-delay-3"><div class="problem-stat-num">35%</div><p>des commandes COD au Maroc ne sont pas livrées à la première tentative. Sur 5 000 colis à 25 MAD de coût retour, c'est <strong>43 750 MAD perdus</strong> chaque mois.</p></div>

        <div class="loss-calc reveal reveal-delay-4">
          <div class="loss-calc-head"><span>Calculez votre perte mensuelle</span></div>
          <div class="loss-calc-row">
            <span style="font-family:var(--font-mono);font-size:12px;color:var(--mute);min-width:90px">Vos colis/mois</span>
            <input type="range" id="lossOrders" min="100" max="10000" step="100" value="5000">
            <span class="loss-calc-val" id="lossOrdersVal">5 000</span>
          </div>
          <div class="loss-calc-result">Coût retour estimé : <b id="lossResult">43 750 MAD</b> / mois en colis non livrés au 1er passage.</div>
        </div>
      </div>
      <div class="problem-cards reveal reveal-delay-1">
        <div class="problem-card"><span class="problem-card-icon">📍</span><h3>Adresses erronées</h3><p>15 à 25% des commandes contiennent une adresse incomplète.</p></div>
        <div class="problem-card"><span class="problem-card-icon">📞</span><h3>Appels manuels</h3><p>Vos agents passent des heures à appeler pour des confirmations manuelles.</p></div>
        <div class="problem-card"><span class="problem-card-icon">📋</span><h3>Saisie manuelle</h3><p>Copier-coller depuis WhatsApp vers l'extranet. Source d'erreurs.</p></div>
        <div class="problem-card"><span class="problem-card-icon">🔄</span><h3>Retours non maîtrisés</h3><p>Aucune visibilité sur les raisons des retours.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICES -->
<section class="section" id="services">
  <div class="section-inner">
    <div class="section-header center">
      <div class="section-tag reveal">La solution</div>
      <h2 class="section-title reveal reveal-delay-1">3 services. 1 plateforme.<br>0 intervention manuelle.</h2>
      <p class="section-sub reveal reveal-delay-2">Obgecom automatise chaque étape du post-commande — de la confirmation client jusqu'à la transmission au transporteur.</p>
    </div>
    <div class="services-grid">
      <div class="service-card s1 reveal reveal-delay-1">
        <div class="service-icon">✅</div><div class="service-num">01 / 03</div>
        <h3>Confirmation automatique</h3>
        <p>Dès qu'une commande est passée, Obgecom contacte le client par WhatsApp ou SMS.</p>
        <ul class="service-features"><li>Message WhatsApp avec boutons Confirmer / Annuler</li><li>Relance automatique après 2h sans réponse</li><li>Appel téléphonique agent après 4h</li></ul>
        <div class="service-kpi"><span class="service-kpi-val">−25%</span><span class="service-kpi-label">livraisons<br>à vide</span></div>
      </div>
      <div class="service-card s2 reveal reveal-delay-2">
        <div class="service-icon">📋</div><div class="service-num">02 / 03</div>
        <h3>Saisie et structuration auto</h3>
        <p>Toutes les données de commande sont extraites, validées et formatées automatiquement.</p>
        <ul class="service-features"><li>Extraction automatique depuis la boutique</li><li>Validation adresse, téléphone, wilaya</li><li>Formatage dans le format exact du transporteur</li></ul>
        <div class="service-kpi"><span class="service-kpi-val">0</span><span class="service-kpi-label">erreur de<br>saisie</span></div>
      </div>
      <div class="service-card s3 reveal reveal-delay-3">
        <div class="service-icon">🚀</div><div class="service-num">03 / 03</div>
        <h3>Envoi automatique carrier</h3>
        <p>Les commandes confirmées sont transmises directement dans le système du transporteur via API.</p>
        <ul class="service-features"><li>Intégration Amana, CTM, J&amp;T, Aramex et plus</li><li>Génération automatique du bon de livraison</li><li>Mise à jour du statut de suivi en temps réel</li></ul>
        <div class="service-kpi"><span class="service-kpi-val">24/7</span><span class="service-kpi-label">dispatch<br>automatique</span></div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section how-section">
  <div class="section-inner">
    <div class="section-header center">
      <div class="section-tag reveal">Comment ça marche</div>
      <h2 class="section-title reveal reveal-delay-1">De la commande à la livraison<br>en 60 secondes chrono</h2>
    </div>
    <div class="how-steps">
      <div class="how-step reveal reveal-delay-1"><div class="how-step-num">01</div><span class="how-step-icon">🛒</span><h4>Commande reçue</h4><p>Votre boutique envoie la commande à Obgecom en temps réel.</p></div>
      <div class="how-step reveal reveal-delay-2"><div class="how-step-num">02</div><span class="how-step-icon">📱</span><h4>Client confirmé</h4><p>WhatsApp en moins de 60 secondes. Sans réponse, un agent l'appelle.</p></div>
      <div class="how-step reveal reveal-delay-3"><div class="how-step-num">03</div><span class="how-step-icon">⚙️</span><h4>Données structurées</h4><p>Les données sont validées, formatées et enrichies automatiquement.</p></div>
      <div class="how-step reveal reveal-delay-4"><div class="how-step-num">04</div><span class="how-step-icon">🚚</span><h4>Transporteur notifié</h4><p>La commande est envoyée dans votre système carrier, suivi activé.</p></div>
    </div>
    <div class="integrations-strip reveal">
      <span class="integ-badge">🛒 Shopify</span><span class="integ-badge">🇲🇦 Youcan</span><span class="integ-badge">🌐 WooCommerce</span><span class="integ-badge">🔌 API Custom</span><span class="integ-badge">📦 Amana</span><span class="integ-badge">🚚 CTM</span><span class="integ-badge">⚡ J&amp;T Express</span><span class="integ-badge">✈️ Aramex</span>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section" id="fonctionnalites">
  <div class="section-inner">
    <div class="section-header center">
      <div class="section-tag reveal">Fonctionnalités</div>
      <h2 class="section-title reveal reveal-delay-1">Tout ce dont vous avez besoin,<br>dans une seule interface.</h2>
    </div>
    <div class="features-grid">
      <div class="feature-item reveal reveal-delay-1"><div class="feature-icon">🏪</div><h4>Multi-boutiques</h4><p>Gérez toutes vos boutiques depuis un seul tableau de bord centralisé.</p></div>
      <div class="feature-item reveal reveal-delay-2"><div class="feature-icon">📦</div><h4>Gestion des stocks</h4><p>Multi-entrepôts, alertes stock bas, synchronisation en temps réel.</p></div>
      <div class="feature-item reveal reveal-delay-3"><div class="feature-icon">👥</div><h4>Centre de confirmation</h4><p>Gérez vos agents, dashboard de performance temps réel.</p></div>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="section pricing-section" id="tarifs">
  <div class="section-inner">
    <div class="section-header center">
      <div class="section-tag reveal">Tarifs</div>
      <h2 class="section-title reveal reveal-delay-1">Payez en fonction de votre volume.</h2>
      <p class="section-sub reveal reveal-delay-2">Sélectionnez vos commandes mensuelles et obtenez un tarif personnalisé immédiatement.</p>
    </div>
    <div class="payg-container reveal">
      <div class="payg-inner">
        <div class="pricing-name">Tarification Flex</div>
        <div class="payg-badge">⚡ Pas d'engagement</div>
        <h3>Calculez votre forfait</h3>
        <p class="payg-sub">Ajustez le volume pour voir le tarif correspondant.</p>

        <div class="payg-calc">
          <label>Commandes / mois<span>Estimez votre volume mensuel</span>
            <input type="number" id="orderVolume" value="300" min="0" step="10">
          </label>
          <label>Période<span>Choisissez votre cycle de facturation</span>
            <select id="billingPeriod">
              <option value="monthly">Mensuel</option>
              <option value="quarterly">Trimestriel ( -5% )</option>
              <option value="yearly">Annuel ( -15% )</option>
            </select>
          </label>
        </div>

        <div class="payg-result">
          <span class="total-label">Estimation du prix</span>
          <span class="total-price" id="totalPrice">300 <small>MAD</small></span>
        </div>

        <a href="./signup?plan=flex" class="pricing-btn">Commencer avec 1 mois offert →</a>
        <div class="payg-note"><strong>Pilote 1 mois offert</strong> · Sans engagement · Aucune carte bancaire requise</div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="section" id="temoignages">
  <div class="section-inner">
    <div class="section-header center">
      <div class="section-tag reveal">Témoignages</div>
      <h2 class="section-title reveal reveal-delay-1">Ce que nos clients disent vraiment.</h2>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card reveal reveal-delay-1">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-quote">"En 48h tout était opérationnel. Mes commandes arrivent confirmées, propres, directement chez mon transporteur."</p>
        <div class="testimonial-author"><div class="testimonial-avatar">KB</div><div><div class="testimonial-name">Karim B.</div><div class="testimonial-role">E-commerçant mode · Casablanca</div></div></div>
        <div class="testimonial-result" style="background:var(--violet-lt);border:1px solid var(--violet);color:var(--violet-dk)">📉 −58% de retours dès le premier mois</div>
      </div>
      <div class="testimonial-card reveal reveal-delay-2">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-quote">"Avant, je passais 3 heures par jour à saisir des commandes. Aujourd'hui Obgecom fait tout seul."</p>
        <div class="testimonial-author"><div class="testimonial-avatar">SL</div><div><div class="testimonial-name">Sara L.</div><div class="testimonial-role">Dropshippeuse · Marrakech</div></div></div>
        <div class="testimonial-result" style="background:var(--carrier-lt);border:1px solid var(--carrier);color:var(--carrier)">⏱ 3h/jour récupérées sur la saisie manuelle</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section cta-section" id="demo">
  <div class="section-inner">
    <div class="cta-inner">
      <h2 class="reveal">Prêt à automatiser votre post-commande ?</h2>
      <p class="reveal reveal-delay-1">Démarrez avec 1 mois gratuit. Notre équipe intègre votre boutique et configure vos transporteurs en 48h.</p>
      <div class="cta-actions reveal reveal-delay-2">
        <a href="mailto:contact@obgecom.com" class="btn-white">Démarrer gratuitement <span>→</span></a>
        <a href="mailto:contact@obgecom.com" class="btn-ghost">Demander une démo</a>
      </div>
      <div class="cta-trust reveal reveal-delay-3">
        <span class="cta-trust-item">1 mois gratuit</span><span class="cta-trust-item">Sans engagement</span><span class="cta-trust-item">Migration incluse</span><span class="cta-trust-item">Intégration 48h</span>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <img src="./assets/img/logo.png" alt="Obgecom logo">
        <span>La plateforme d'automatisation post-commande pour les e-commerçants et dropshippers du Maroc et de la région MENA.</span>
        <a href="mailto:contact@obgecom.com" style="color:var(--violet-dk);font-size:13px;text-decoration:none">contact@obgecom.com</a>
      </div>
      <div><div class="footer-col-title">Produit</div><ul class="footer-links"><li><a href="#services">Services core</a></li><li><a href="#fonctionnalites">Fonctionnalités</a></li><li><a href="#tarifs">Tarifs</a></li></ul></div>
      <div><div class="footer-col-title">Intégrations</div><ul class="footer-links"><li><a href="#">Shopify</a></li><li><a href="#">Youcan</a></li><li><a href="#">WooCommerce</a></li></ul></div>
      <div><div class="footer-col-title">Entreprise</div><ul class="footer-links"><li><a href="#">À propos</a></li><li><a href="mailto:contact@obgecom.com">Contact</a></li><li><a href="./lg">Connexion</a></li></ul></div>
    </div>

    <div class="footer-payment">
      <div class="payment-section">
        <span class="payment-label">Paiement sécurisé</span>
        <div class="payment-icons">
          <a href="https://www.mastercard.com" target="_blank" rel="noopener noreferrer" class="payment-icon"><i class="fab fa-cc-mastercard"></i></a>
          <a href="https://www.visa.com" target="_blank" rel="noopener noreferrer" class="payment-icon"><i class="fab fa-cc-visa"></i></a>
          <a href="http://www.cmi.co.ma" target="_blank" rel="noopener noreferrer" class="payment-icon"><img src="./assets/img/cmicard.png" alt="CMI" style="height:26px;width:auto;"></a>
        </div>
      </div>
      <div class="secure-badge"><i class="fas fa-shield-alt"></i><span>100% Sécurisé</span></div>
    </div>

    <div class="footer-bottom">
      <div>© 2026 Obgecom · Casablanca, Maroc · Développé par <a href="https://brz.ma" target="_blank" style="text-decoration:none;color:var(--violet-dk)">BRZ</a> · Tous droits réservés</div>
      <div class="footer-legal"><a href="#">Confidentialité</a><a href="#">Conditions d'utilisation</a></div>
    </div>
  </div>
</footer>

<script>
const navbar=document.getElementById('navbar');
const hamburger=document.getElementById('hamburger');
const mobileMenu=document.getElementById('mobileMenu');
window.addEventListener('scroll',()=>navbar.classList.toggle('scrolled',window.scrollY>20));

hamburger.addEventListener('click',()=>{
  hamburger.classList.toggle('active');
  mobileMenu.classList.toggle('open');
  document.body.style.overflow=mobileMenu.classList.contains('open')?'hidden':'';
});
document.querySelectorAll('.mobile-menu a, .mobile-menu .mobile-cta, .mobile-menu .mobile-login').forEach(link=>{
  link.addEventListener('click',()=>{
    hamburger.classList.remove('active');
    mobileMenu.classList.remove('open');
    document.body.style.overflow='';
  });
});

const observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('visible');}),{threshold:.1,rootMargin:'0px 0px -40px 0px'});
document.querySelectorAll('.reveal').forEach(el=>observer.observe(el));

document.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener('click',function(e){const t=document.querySelector(this.getAttribute('href'));if(t){e.preventDefault();window.scrollTo({top:t.getBoundingClientRect().top+window.pageYOffset-80,behavior:'smooth'});}}));

// --- Set manifest date to today ---
(function(){
  const d=new Date();
  const dd=String(d.getDate()).padStart(2,'0');
  const mm=String(d.getMonth()+1).padStart(2,'0');
  document.getElementById('manifestDate').textContent=dd+'/'+mm;
})();

// --- Hero manifest: live stamping cycle ---
(function(){
  const cities=['Casablanca','Rabat','Fès','Marrakech','Tanger','Agadir','Meknès','Oujda'];
  const names=['Karim B.','Sara L.','Youssef M.','Imane T.','Reda K.','Nadia F.','Hamza E.','Salma R.','Othmane Z.','Lina B.'];
  const rows=Array.from(document.querySelectorAll('#manifestBody [data-row]'));
  const pctEl=document.getElementById('manifestPct');
  const stampEl=document.getElementById('stampMark');
  let orderCounter=4822;
  let pct=94;

  function randomOf(arr){return arr[Math.floor(Math.random()*arr.length)];}

  function cycleStatuses(){
    // pick a pending/in-progress row and "confirm" it
    const target=rows.find(r=>{
      const st=r.querySelector('.mr-status');
      return st.classList.contains('pending');
    }) || rows[0];

    const st=target.querySelector('.mr-status');
    st.textContent='Confirmée';
    st.className='mr-status confirmed';
    target.classList.add('stamping');
    setTimeout(()=>target.classList.remove('stamping'),450);

    // show the stamp mark briefly
    stampEl.classList.remove('show');
    void stampEl.offsetWidth;
    stampEl.classList.add('show');

    // a moment later, replace that row with a brand-new pending order to keep the manifest alive
    setTimeout(()=>{
      target.querySelector('.mr-id').textContent='#'+(orderCounter++);
      target.querySelector('.mr-name').textContent=randomOf(names);
      target.querySelector('.mr-city').textContent=randomOf(cities);
      const st2=target.querySelector('.mr-status');
      const states=[['En appel','pending'],['WhatsApp envoyé','pending'],['Expédiée','dispatched']];
      const [label,cls]=randomOf(states);
      st2.textContent=label;
      st2.className='mr-status '+cls;
    },2200);

    pct=Math.min(98,pct+ (Math.random()>.5?1:0));
    pctEl.textContent=pct+'%';
  }

  setInterval(cycleStatuses,3200);
})();

// --- PAYG Calculator ---
function calculatePrice(){
  const volume=parseInt(document.getElementById('orderVolume').value)||0;
  const period=document.getElementById('billingPeriod').value;
  let pricePerOrder=10;
  if(volume>500&&volume<=1000){pricePerOrder=10;}
  else if(volume>1000&&volume<=2000){pricePerOrder=9;}
  else if(volume>2000&&volume<=5000){pricePerOrder=8;}
  else if(volume>5000){pricePerOrder=7;}
  let basePrice=Math.round(volume*pricePerOrder);
  if(basePrice<300)basePrice=300;
  let total=basePrice;
  if(period==='quarterly'){total=Math.round(total*3*0.95);}
  else if(period==='yearly'){total=Math.round(total*12*0.85);}
  document.getElementById('totalPrice').innerHTML=total.toLocaleString('fr-FR')+' <small>MAD</small>';
}
document.getElementById('orderVolume').addEventListener('input',calculatePrice);
document.getElementById('billingPeriod').addEventListener('change',calculatePrice);
calculatePrice();

// --- Loss calculator (problem section) ---
function calculateLoss(){
  const orders=parseInt(document.getElementById('lossOrders').value)||0;
  document.getElementById('lossOrdersVal').textContent=orders.toLocaleString('fr-FR');
  const loss=Math.round(orders*0.35*25);
  document.getElementById('lossResult').textContent=loss.toLocaleString('fr-FR')+' MAD';
}
document.getElementById('lossOrders').addEventListener('input',calculateLoss);
calculateLoss();
</script>
</body>
</html>