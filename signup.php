<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="OBG ECOM — Créez votre compte. Pay-as-you-go : calculez votre tarif en fonction de vos commandes.">
<title>OBG ECOM · Créer un compte · Tarif flexible</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
	*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
	:root{
		--bg:#fafaf8;
		--surface:#ffffff;
		--surface2:#f4f3f0;
		--border:#e8e6e0;
		--border2:#d4d1c9;
		--text:#1a1814;
		--text2:#5c5a55;
		--text3:#9b9890;
		--accent:#5c45e8;
		--accent2:#7c6af0;
		--accent-light:#eeecfd;
		--accent-mid:#c5bef9;
		--success:#1a7a5e;
		--success-bg:#edf7f3;
		--error:#c0392b;
		--error-bg:#fdf0ef;
		--info:#3a5bbf;
		--info-bg:#eef2fc;
		--r:12px;
		--r-sm:8px;
		--r-lg:16px;
		--r-pill:100px;
	}
	html,body{height:100%;overflow-x:hidden;}
	body{
		background:var(--bg);
		font-family:'DM Sans',system-ui,sans-serif;
		color:var(--text);
		min-height:100vh;
		display:flex;
		align-items:stretch;
	}
	body::before{
		content:'';
		position:fixed;inset:0;
		background-image:radial-gradient(circle,#c8c5bb 1px,transparent 1px);
		background-size:28px 28px;
		opacity:0.35;pointer-events:none;z-index:0;
	}
	.blob{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(60px);}
	.blob-a{width:500px;height:500px;top:-180px;left:-180px;background:radial-gradient(circle,rgba(92,69,232,0.07) 0%,transparent 70%);}
	.blob-b{width:400px;height:400px;bottom:-120px;right:-100px;background:radial-gradient(circle,rgba(124,106,240,0.06) 0%,transparent 70%);}

	.split{position:relative;z-index:1;display:flex;width:100%;min-height:100vh;flex-direction:column;}

	/* LEFT HERO */
	.hero{
		padding:2rem 1.75rem 2.5rem;
		background:var(--surface);
		border-bottom:1px solid var(--border);
		position:relative;overflow:hidden;
	}
	.hero::after{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:var(--accent-light);opacity:0.7;}
	.logo{display:flex;align-items:center;gap:10px;font-family:'Sora',sans-serif;font-size:0.9rem;font-weight:700;color:var(--text);letter-spacing:-0.01em;position:relative;z-index:1;}
	.logo-mark{width:34px;height:34px;border-radius:10px;background:var(--accent);display:grid;place-items:center;font-size:0.6rem;font-weight:800;color:#fff;flex-shrink:0;}
	.logo-name{display:flex;flex-direction:column;line-height:1.1;}
	.logo-name span:first-child{font-size:0.85rem;font-weight:700;color:var(--text);}
	.logo-name span:last-child{font-size:0.55rem;font-weight:500;color:var(--text3);letter-spacing:0.08em;text-transform:uppercase;}
	.hero-body{margin-top:1.75rem;position:relative;z-index:1;}
	.eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:0.6rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--accent);margin-bottom:1rem;background:var(--accent-light);padding:4px 10px;border-radius:var(--r-pill);}
	.edot{width:5px;height:5px;border-radius:50%;background:var(--accent);flex-shrink:0;}
	.hero-t{font-family:'Sora',sans-serif;font-size:1.85rem;font-weight:700;line-height:1.18;letter-spacing:-0.03em;color:var(--text);margin-bottom:0.85rem;}
	.hero-t em{font-style:normal;color:var(--accent);}
	.hero-d{font-size:0.82rem;color:var(--text2);line-height:1.6;margin-bottom:1.75rem;}
	.feats{display:flex;flex-direction:column;gap:0.55rem;margin-bottom:1.75rem;}
	.fi{display:flex;align-items:center;gap:10px;font-size:0.78rem;color:var(--text2);padding:0.55rem 0.8rem;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);transition:border-color 0.2s,background 0.2s;}
	.fi:hover{border-color:var(--accent-mid);background:#f8f7ff;}
	.fchk{flex-shrink:0;width:18px;height:18px;border-radius:5px;background:var(--accent-light);display:grid;place-items:center;}
	.fchk svg{width:9px;height:9px;stroke:var(--accent);fill:none;stroke-width:2.5;}
	.hero-ft{display:flex;align-items:center;gap:10px;padding-top:1.25rem;border-top:1px solid var(--border);}
	.avstack{display:flex;}
	.av{width:26px;height:26px;border-radius:50%;border:2px solid var(--surface);display:grid;place-items:center;font-size:0.5rem;font-weight:700;color:#fff;margin-right:-6px;}
	.av:nth-child(1){background:linear-gradient(135deg,#5c45e8,#4338ca);}
	.av:nth-child(2){background:linear-gradient(135deg,#7c6af0,#5c45e8);}
	.av:nth-child(3){background:linear-gradient(135deg,#4338ca,#7c6af0);}
	.avtext{font-size:0.72rem;color:var(--text3);font-weight:500;padding-left:4px;}
	.avtext strong{color:var(--text2);font-weight:600;}

	/* RIGHT PANEL */
	.fp{flex:1;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1.5rem 2.5rem;background:var(--bg);}
	.fb{width:100%;max-width:100%;}

	.back-home {
		display:inline-flex;
		align-items:center;
		gap:6px;
		font-size:0.7rem;
		font-weight:600;
		color:var(--text3);
		text-decoration:none;
		margin-bottom:0.5rem;
		transition:color 0.2s;
	}
	.back-home:hover {
		color:var(--accent);
	}
	.back-home svg {
		width:14px;
		height:14px;
		stroke:currentColor;
		fill:none;
		stroke-width:2;
	}

	/* STEP BAR */
	.sb{display:flex;align-items:center;margin-bottom:1.75rem;}
	.sn{display:flex;align-items:center;gap:6px;flex-shrink:0;}
	.sc{
		width:28px;height:28px;border-radius:50%;
		border:1.5px solid var(--border2);
		display:grid;place-items:center;
		font-size:0.65rem;font-weight:700;color:var(--text3);
		transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);
		background:var(--surface);
	}
	.sc.on{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 0 0 3px var(--accent-light);}
	.sc.dn{background:var(--success-bg);border-color:#86efac;color:var(--success);}
	.sl{font-size:0.62rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;transition:color 0.2s;}
	.sl.on{color:var(--text);}
	.sln{flex:1;height:1px;background:var(--border);margin:0 8px;position:relative;overflow:hidden;}
	.slf{position:absolute;inset:0;background:var(--accent);transform:scaleX(0);transform-origin:left;transition:transform 0.5s ease;}
	.slf.on{transform:scaleX(1);}
	@media(max-width:450px){.sl{display:none;}.sc{width:32px;height:32px;}.sln{margin:0 4px;}}

	/* STEP SECTIONS */
	.step{display:none;}
	.step.on{display:block;animation:si .28s cubic-bezier(0.16,1,0.3,1);}
	@keyframes si{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
	.sh2{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:700;letter-spacing:-0.025em;color:var(--text);margin-bottom:4px;}
	.ss{font-size:0.78rem;color:var(--text3);line-height:1.5;margin-bottom:1.35rem;}

	/* QUOTA SELECTOR */
	.quota-wrap{
		background:var(--surface);
		border:1px solid var(--border);
		border-radius:var(--r-lg);
		padding:1.5rem 1.25rem;
		margin-bottom:1.25rem;
	}
	.quota-header{
		display:flex;
		justify-content:space-between;
		align-items:center;
		margin-bottom:0.5rem;
		flex-wrap:wrap;
		gap:0.5rem;
	}
	.quota-header .q-label{
		font-size:0.7rem;
		font-weight:700;
		text-transform:uppercase;
		letter-spacing:0.08em;
		color:var(--text3);
	}
	.quota-header .q-value{
		font-family:'Sora',sans-serif;
		font-size:1.1rem;
		font-weight:700;
		color:var(--accent);
		background:var(--accent-light);
		padding:0.15rem 0.75rem;
		border-radius:var(--r-pill);
	}
	.quota-slider{
		width:100%;
		-webkit-appearance:none;
		appearance:none;
		height:4px;
		background:var(--border2);
		border-radius:4px;
		outline:none;
		margin:0.75rem 0 0.25rem;
		transition:background 0.15s;
	}
	.quota-slider::-webkit-slider-thumb{
		-webkit-appearance:none;
		appearance:none;
		width:18px;
		height:18px;
		border-radius:50%;
		background:var(--accent);
		cursor:pointer;
		box-shadow:0 2px 8px rgba(92,69,232,0.25);
		transition:0.1s;
	}
	.quota-slider::-webkit-slider-thumb:hover{transform:scale(1.08);}
	.quota-slider::-moz-range-thumb{
		width:18px;height:18px;border-radius:50%;
		background:var(--accent);
		cursor:pointer;
		border:none;
	}
	.quota-labels{
		display:flex;
		justify-content:space-between;
		font-size:0.58rem;
		color:var(--text3);
		margin-top:0.15rem;
	}
	.quota-meta{
		display:flex;
		justify-content:space-between;
		align-items:center;
		margin-top:0.75rem;
		padding-top:0.75rem;
		border-top:1px solid var(--border);
		flex-wrap:wrap;
		gap:0.5rem;
	}
	.quota-meta .price-tag{
		font-family:'Sora',sans-serif;
		font-size:1.6rem;
		font-weight:700;
		color:var(--accent);
		line-height:1;
	}
	.quota-meta .price-tag small{
		font-size:0.7rem;
		font-weight:500;
		color:var(--text3);
	}
	.quota-meta .meta-info{
		font-size:0.7rem;
		color:var(--text2);
	}
	.quota-meta .meta-info strong{color:var(--text);}
	.quota-meta .saving-badge{
		font-size:0.65rem;
		font-weight:700;
		color:var(--success);
		background:var(--success-bg);
		padding:0.2rem 0.7rem;
		border-radius:var(--r-pill);
	}
	.free-badge{
		display:inline-block;
		font-size:0.6rem;
		font-weight:700;
		color:var(--success);
		background:var(--success-bg);
		padding:0.2rem 0.7rem;
		border-radius:var(--r-pill);
		margin-left:0.5rem;
	}
	.quota-presets{
		display:flex;
		gap:6px;
		flex-wrap:wrap;
		margin-top:0.5rem;
	}
	.qp-btn{
		padding:4px 12px;
		border-radius:var(--r-pill);
		font-size:0.65rem;
		font-weight:600;
		border:1px solid var(--border);
		background:var(--surface);
		color:var(--text2);
		cursor:pointer;
		transition:all 0.18s;
		font-family:'DM Sans',sans-serif;
	}
	.qp-btn:hover{border-color:var(--accent-mid);color:var(--accent);}
	.qp-btn.on{background:var(--accent-light);border-color:var(--accent);color:var(--accent);}
	.qp-btn.free{background:var(--success-bg);border-color:#86efac;color:var(--success);}
	.qp-btn.free.on{background:var(--success-bg);border-color:var(--success);color:var(--success);}

	/* PRICE SUMMARY BOX */
	.pp{
		display:flex;align-items:center;justify-content:space-between;
		background:var(--accent-light);
		border:1px solid var(--accent-mid);
		border-radius:var(--r);
		padding:10px 14px;margin-bottom:1.25rem;
	}
	.ppl .plab{font-size:0.6rem;color:var(--accent2);margin-bottom:2px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;}
	.ppl .pnm2{font-size:0.8rem;font-weight:700;color:var(--text);}
	.ppr2{text-align:right;}
	.pamt{font-family:'Sora',sans-serif;font-size:1.25rem;font-weight:700;color:var(--accent);line-height:1;}
	.pper{font-size:0.6rem;color:var(--text3);margin-top:2px;}
	.psav{font-size:0.6rem;color:var(--success);font-weight:600;}

	/* FORM FIELDS */
	.fr{margin-bottom:0.9rem;}
	.fr label{display:block;font-size:0.62rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text2);margin-bottom:5px;}
	.iw{position:relative;}
	.iw svg.ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--text3);fill:none;stroke-width:1.75;pointer-events:none;}
	.inp{
		width:100%;padding:11px 12px 11px 38px;
		background:var(--surface);border:1px solid var(--border);
		border-radius:var(--r);color:var(--text);
		font-size:0.83rem;font-family:'DM Sans',sans-serif;
		outline:none;transition:all 0.22s;appearance:none;-webkit-appearance:none;
	}
	.inp::placeholder{color:var(--text3);font-size:0.8rem;}
	.inp:hover:not(:focus){border-color:var(--border2);}
	.inp:focus{border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 3px rgba(92,69,232,0.1);}
	.inp.err{border-color:var(--error);box-shadow:0 0 0 3px rgba(192,57,43,0.08);}
	.pfx{position:absolute;left:38px;top:50%;transform:translateY(-50%);font-size:0.83rem;color:var(--text3);pointer-events:none;}
	.inp.pf{padding-left:72px;}
	.eye2{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);display:grid;place-items:center;padding:4px;transition:color 0.2s;border-radius:4px;}
	.eye2:hover{color:var(--text2);}
	.eye2 svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.75;}
	.fe{font-size:0.69rem;color:var(--error);margin-top:4px;display:none;}
	.fe.on{display:flex;align-items:center;gap:4px;}
	.fe.on::before{content:'';display:inline-block;width:4px;height:4px;border-radius:50%;background:var(--error);flex-shrink:0;}

	/* BUTTONS */
	.btn{
		width:100%;padding:11px 16px;border:none;border-radius:var(--r-pill);
		font-size:0.85rem;font-weight:700;font-family:'DM Sans',sans-serif;
		cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
		transition:all 0.22s;
	}
	.btn-p{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(92,69,232,0.22);}
	.btn-p:hover{background:#4e39d4;box-shadow:0 4px 16px rgba(92,69,232,0.35);transform:translateY(-1px);}
	.btn-p:active{transform:translateY(0);}
	.btn-p:disabled{opacity:0.5;cursor:not-allowed;transform:none;box-shadow:none;}
	.btn-g{
		background:var(--surface);border:1px solid var(--border);color:var(--text2);
		width:auto;padding:10px 16px;
	}
	.btn-g:hover{border-color:var(--border2);background:var(--surface2);}
	.brow{display:flex;gap:10px;margin-top:0.5rem;}
	.brow .btn-p{flex:1;}

	.sil{text-align:center;margin-top:1.2rem;font-size:0.74rem;color:var(--text3);}
	.sil a{color:var(--accent);text-decoration:none;font-weight:600;}
	.sil a:hover{text-decoration:underline;}

	/* OTP */
	.otp{display:flex;gap:7px;justify-content:center;margin:1.1rem 0;}
	.od{
		width:44px;height:52px;text-align:center;
		font-size:1.3rem;font-weight:700;
		background:var(--surface);border:1.5px solid var(--border);
		border-radius:var(--r);color:var(--text);
		font-family:'Sora',sans-serif;outline:none;transition:all 0.2s;
	}
	.od:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
	.od.filled{border-color:var(--accent-mid);background:var(--accent-light);}
	.vs{text-align:center;font-size:0.75rem;padding:7px 12px;border-radius:var(--r-sm);margin:4px 0;}
	.vs.ok{color:var(--success);background:var(--success-bg);font-weight:600;}
	.vs.bad{color:var(--error);background:var(--error-bg);font-weight:600;}
	.resrow{text-align:center;font-size:0.72rem;color:var(--text3);margin:6px 0 1rem;}
	.resbtn{background:none;border:none;color:var(--accent);cursor:pointer;font-size:0.72rem;font-weight:600;font-family:'DM Sans',sans-serif;}
	.resbtn:disabled{color:var(--text3);cursor:not-allowed;}

	.pstrip{
		display:flex;align-items:center;gap:9px;
		background:var(--info-bg);border:1px solid #bfcfef;
		border-radius:var(--r);padding:10px 13px;margin-bottom:1.1rem;
		font-size:0.78rem;color:var(--info);font-weight:500;
	}
	.pstrip svg{width:14px;height:14px;stroke:var(--info);fill:none;stroke-width:1.75;flex-shrink:0;}

	.tc{display:flex;align-items:flex-start;gap:9px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r);padding:10px 12px;margin:0.9rem 0;}
	.tc input{width:15px;height:15px;accent-color:var(--accent);margin-top:2px;flex-shrink:0;}
	.tc label{font-size:0.72rem;color:var(--text2);line-height:1.5;cursor:pointer;}
	.tc label span{color:var(--accent);text-decoration:underline;cursor:pointer;}

	/* Method selector */
	.method-selector {
		display:flex;
		gap:10px;
		margin-bottom:1rem;
	}
	.method-btn {
		flex:1;
		padding:8px 12px;
		border:1.5px solid var(--border);
		border-radius:var(--r);
		background:var(--surface);
		cursor:pointer;
		text-align:center;
		font-size:0.7rem;
		font-weight:600;
		font-family:'DM Sans',sans-serif;
		color:var(--text2);
		transition:all 0.2s;
	}
	.method-btn:hover{border-color:var(--accent-mid);}
	.method-btn.active{
		border-color:var(--accent);
		background:var(--accent-light);
		color:var(--accent);
	}
	.method-btn svg{
		width:16px;
		height:16px;
		display:block;
		margin:0 auto 4px;
		stroke:currentColor;
		fill:none;
		stroke-width:1.75;
	}

	/* TOAST */
	.ntf{
		position:fixed;top:16px;left:16px;right:16px;z-index:9999;
		padding:11px 14px;border-radius:var(--r);
		font-size:0.78rem;font-weight:600;
		display:flex;align-items:center;gap:10px;
		transform:translateY(-120px);opacity:0;
		transition:transform 0.38s cubic-bezier(0.34,1.56,0.64,1),opacity 0.25s;
		max-width:calc(100% - 32px);margin:0 auto;
		border-left:3px solid transparent;
		box-shadow:0 4px 20px rgba(0,0,0,0.1);
	}
	.ntf.on{transform:translateY(0);opacity:1;}
	.ntf.s{background:var(--success-bg);border-color:#1a7a5e;color:var(--success);}
	.ntf.e{background:var(--error-bg);border-color:var(--error);color:var(--error);}
	.ntf.i{background:var(--info-bg);border-color:var(--info);color:var(--info);}
	.ntf-icon{width:18px;height:18px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;font-size:10px;font-weight:700;}
	.ntf.s .ntf-icon{background:rgba(26,122,94,0.15);}
	.ntf.e .ntf-icon{background:rgba(192,57,43,0.12);}
	.ntf.i .ntf-icon{background:rgba(58,91,191,0.12);}

	.spin{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,0.3);border-radius:50%;border-top-color:#fff;animation:sp .6s linear infinite;}
	@keyframes sp{to{transform:rotate(360deg);}}

	/* RESPONSIVE */
	@media(min-width:640px){
		.split{flex-direction:row;}
		.hero{flex:0 0 44%;padding:2.5rem;border-bottom:none;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:space-between;}
		.hero-t{font-size:2.1rem;}
		.fp{padding:2rem 2.5rem 2rem 3rem;align-items:center;}
		.fb{max-width:400px;}
		.ntf{left:auto;right:20px;top:20px;max-width:340px;transform:translateX(calc(100% + 1.5rem));opacity:1;}
		.ntf.on{transform:translateX(0);}
	}
	@media(min-width:1024px){
		.hero{flex:0 0 42%;padding:3rem 2.5rem;}
		.hero-t{font-size:2.6rem;}
		.fp{padding:3rem 4rem 3rem 5rem;}
		.fb{max-width:440px;}
		.sh2{font-size:1.55rem;}
		.od{width:50px;height:56px;}
	}
	@media(max-width:380px){
		.hero-t{font-size:1.6rem;}
		.inp{padding:10px 10px 10px 36px;font-size:0.8rem;}
		.btn{padding:10px;}
		.od{width:38px;height:46px;font-size:1.1rem;}
	}
</style>
</head>
<body>
<div class="blob blob-a"></div>
<div class="blob blob-b"></div>

<div class="split">
<!-- LEFT HERO -->
<div class="hero">
	<div>
	<div class="logo">
		<div class="logo-mark">OE</div>
		<div class="logo-name">
		<span>OBG ECOM</span>
		<span>Plateforme Commerce</span>
		</div>
	</div>
	<div class="hero-body">
		<div class="eyebrow"><div class="edot"></div>Hub Commerce Maroc</div>
		<h1 class="hero-t">La solution qui accélère<br><em>vos opérations.</em></h1>
		<p class="hero-d">Synchronisez, confirmez et expédiez des milliers de commandes depuis un tableau de bord conçu pour la vitesse du e‑commerce marocain.</p>
		<div class="feats">
		<div class="fi"><div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>Synchro automatique multicanal</div>
		<div class="fi"><div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>Traitement groupé des commandes</div>
		<div class="fi"><div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>Suivi temps réel multi-transporteurs</div>
		<div class="fi"><div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>Analytique prédictive IA</div>
		</div>
	</div>
	</div>
	<div class="hero-ft">
	<div class="avstack"><div class="av">YA</div><div class="av">MO</div><div class="av">KH</div></div>
	<div class="avtext"><strong>1 400+</strong> commerçants actifs</div>
	</div>
</div>

<!-- RIGHT PANEL -->
<div class="fp">
	<div class="fb">
	
	<!-- Back to Home link -->
	<a href="./" class="back-home">
		<svg viewBox="0 0 16 16"><path d="M2 8l6-6 6 6"/><path d="M4 6v6h2V8h4v4h2V6"/></svg>
		Retour à l'accueil
	</a>

	<!-- STEP BAR -->
	<div class="sb">
		<div class="sn"><div class="sc on" id="sc1">1</div><span class="sl on" id="sl1">Quota</span></div>
		<div class="sln"><div class="slf" id="sf1"></div></div>
		<div class="sn"><div class="sc" id="sc2">2</div><span class="sl" id="sl2">Compte</span></div>
		<div class="sln"><div class="slf" id="sf2"></div></div>
		<div class="sn"><div class="sc" id="sc3">3</div><span class="sl" id="sl3">Vérifier</span></div>
	</div>

	<!-- ÉTAPE 1 : QUOTA -->
	<div class="step on" id="s1">
		<h2 class="sh2">Choisissez votre quota</h2>
		<p class="ss">Ajustez le nombre de commandes que vous souhaitez traiter par mois. <span class="free-badge">🎁 100 commandes offertes</span></p>
		
		<div class="quota-wrap">
			<div class="quota-header">
				<span class="q-label">Commandes / mois</span>
				<span class="q-value" id="orderDisplay">100</span>
			</div>
			<input type="range" class="quota-slider" id="orderSlider" min="0" max="10000" step="10" value="100">
			<div class="quota-labels">
				<span>0 (Gratuit)</span>
				<span>5 000</span>
				<span>10 000+</span>
			</div>
			<div class="quota-presets">
				<button class="qp-btn on" data-q="100">🎁 100</button>
				<button class="qp-btn" data-q="500">500</button>
				<button class="qp-btn" data-q="1000">1 000</button>
				<button class="qp-btn" data-q="2500">2 500</button>
				<button class="qp-btn" data-q="5000">5 000</button>
				<button class="qp-btn" data-q="10000">10 000</button>
			</div>
			<div class="quota-meta">
				<div>
					<div class="price-tag" id="priceDisplay">0 <small>MAD</small></div>
					<div class="meta-info"><strong id="pricePerOrder">0,80</strong> MAD / commande</div>
				</div>
				<div class="saving-badge" id="savingBadge">🎁 Gratuit</div>
			</div>
			<div style="font-size:0.7rem;color:var(--text3);margin-top:0.5rem;padding:0.5rem;background:var(--surface2);border-radius:var(--r-sm);">
				💡 <strong>100 premières commandes offertes</strong> — Les commandes non utilisées sont reportées au mois suivant.
			</div>
		</div>

		<div class="pp">
			<div class="ppl">
				<div class="plab">Votre quota</div>
				<div class="pnm2" id="ppnm">100 commandes — Gratuit</div>
			</div>
			<div class="ppr2">
				<div class="pamt" id="ppamt">0 MAD</div>
				<div class="pper" id="pper">par mois</div>
				<div class="psav" id="psav" style="display:none"></div>
			</div>
		</div>

		<button class="btn btn-p" id="b1">
			Continuer
			<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
		</button>
		<div class="sil">Déjà un compte ? <a href="login">Se connecter</a></div>
	</div>

	<!-- ÉTAPE 2 : DÉTAILS -->
	<div class="step" id="s2">
		<h2 class="sh2">Créez votre compte</h2>
		<p class="ss">Vos informations sont privées et sécurisées.</p>
		
		<!-- Method Selector -->
		<div class="method-selector">
			<button class="method-btn active" data-method="whatsapp" id="methodWhatsApp">
				<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.89.59 3.64 1.6 5.07L2 22l5.04-1.5C8.28 21.41 10.07 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/><path d="M16.77 14.2c-.27-.14-1.58-.78-1.82-.87-.25-.09-.43-.14-.61.14s-.7.87-.86 1.05c-.16.18-.31.2-.58.07-.27-.14-1.16-.42-2.2-1.35-.81-.73-1.36-1.63-1.52-1.91-.16-.28-.02-.43.12-.57.12-.12.27-.31.41-.47.14-.16.19-.27.28-.45.09-.18.05-.34-.02-.47-.07-.14-.62-1.5-.85-2.06-.22-.55-.45-.47-.61-.47h-.53c-.19 0-.49.07-.75.35-.26.28-1.02.99-1.02 2.42s1.04 2.8 1.19 2.99c.14.19 2.05 3.13 4.97 4.13.69.24 1.23.38 1.65.49.69.22 1.32.19 1.82.11.56-.09 1.58-.64 1.8-1.26.22-.62.22-1.15.15-1.26-.07-.11-.25-.18-.52-.32z"/></svg>
				WhatsApp
			</button>
			<button class="method-btn" data-method="sms" id="methodSMS">
				<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
				SMS
			</button>
		</div>
		
		<div class="fr">
		<label>Nom complet</label>
		<div class="iw">
			<svg class="ico" viewBox="0 0 16 16"><path d="M13 13c0-2.2-2.2-4-5-4S3 10.8 3 13"/><circle cx="8" cy="5" r="3"/></svg>
			<input class="inp" type="text" id="iN" placeholder="Youssef El Amrani" autocomplete="name">
		</div>
		<div class="fe" id="eN"></div>
		</div>
		<div class="fr">
		<label>Adresse e-mail</label>
		<div class="iw">
			<svg class="ico" viewBox="0 0 16 16"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 5l6 5 6-5"/></svg>
			<input class="inp" type="email" id="iE" placeholder="vous@exemple.com" autocomplete="email">
		</div>
		<div class="fe" id="eE"></div>
		</div>
		<div class="fr">
		<label>Téléphone — Maroc</label>
		<div class="iw">
			<svg class="ico" viewBox="0 0 16 16"><rect x="4" y="1" width="8" height="14" rx="1.5"/><circle cx="8" cy="12.5" r="0.75" fill="currentColor" stroke="none"/></svg>
			<span class="pfx">+212</span>
			<input class="inp pf" type="tel" id="iP" placeholder="6XX-XXXXXX" maxlength="9" autocomplete="tel">
		</div>
		<div class="fe" id="eP"></div>
		</div>
		<div class="fr">
		<label>Mot de passe</label>
		<div class="iw">
			<svg class="ico" viewBox="0 0 16 16"><rect x="3" y="7" width="10" height="8" rx="1"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>
			<input class="inp" type="password" id="iW" placeholder="8 caractères minimum" autocomplete="new-password">
			<button class="eye2" type="button" onclick="tEye('iW',this)">
			<svg viewBox="0 0 16 16"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
			</button>
		</div>
		<div class="fe" id="eW"></div>
		</div>
		<div class="fr">
		<label>Confirmer le mot de passe</label>
		<div class="iw">
			<svg class="ico" viewBox="0 0 16 16"><path d="M2 8l4 4 8-8"/></svg>
			<input class="inp" type="password" id="iC" placeholder="Répétez le mot de passe" autocomplete="new-password">
			<button class="eye2" type="button" onclick="tEye('iC',this)">
			<svg viewBox="0 0 16 16"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
			</button>
		</div>
		<div class="fe" id="eC"></div>
		</div>
		<div class="brow">
		<button class="btn btn-g" id="b2bk">
			<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8H3M7 4L3 8l4 4"/></svg>
			Retour
		</button>
		<button class="btn btn-p" id="b2">
			Vérifier le téléphone
			<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
		</button>
		</div>
	</div>

	<!-- ÉTAPE 3 : VÉRIFICATION -->
	<div class="step" id="s3">
		<h2 class="sh2">Vérifiez votre téléphone</h2>
		<p class="ss">Saisissez le code à 6 chiffres envoyé à votre numéro.</p>
		<div class="pstrip">
		<svg viewBox="0 0 16 16"><rect x="4" y="1" width="8" height="14" rx="1.5"/><circle cx="8" cy="12.5" r="0.75" fill="currentColor" stroke="none"/></svg>
		Code envoyé à <strong id="dph">+212 6XX-XXXXXX</strong>
		</div>
		<div class="otp" id="otpEl">
		<input class="od" maxlength="1" inputmode="numeric" data-i="0">
		<input class="od" maxlength="1" inputmode="numeric" data-i="1">
		<input class="od" maxlength="1" inputmode="numeric" data-i="2">
		<input class="od" maxlength="1" inputmode="numeric" data-i="3">
		<input class="od" maxlength="1" inputmode="numeric" data-i="4">
		<input class="od" maxlength="1" inputmode="numeric" data-i="5">
		</div>
		<div id="vst"></div>
		<div class="resrow">
		<button class="resbtn" id="resBtn">Renvoyer le code</button>
		<span id="cdt"></span>
		</div>
		<div class="tc">
		<input type="checkbox" id="tcb">
		<label for="tcb">J'accepte les <span onclick="alert('Conditions générales : droit de rétractation de 14 jours, données protégées selon la loi marocaine.')">Conditions d'utilisation</span> et la <span onclick="alert('Vos données personnelles sont traitées avec totale confidentialité.')">Politique de confidentialité</span></label>
		</div>
		<div class="brow">
		<button class="btn btn-g" id="b3bk">
			<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8H3M7 4L3 8l4 4"/></svg>
			Retour
		</button>
		<button class="btn btn-p" id="b3" disabled>
			Valider l'inscription
			<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4 4 6-6"/></svg>
		</button>
		</div>
	</div>
	</div>
</div>
</div>

<div class="ntf" id="ntf">
<div class="ntf-icon" id="ni"></div>
<span id="nm"></span>
</div>

<script>
// ==================== QUOTA CALCULATOR ====================
const PRICE_PER_ORDER = 0.80; // MAD per order
const FREE_QUOTA = 100;

let sel = { orders: 100 };
let verificationMethod = 'whatsapp'; // Default to WhatsApp

// Method selector
document.querySelectorAll('.method-btn').forEach(btn => {
	btn.addEventListener('click', function() {
		document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
		this.classList.add('active');
		verificationMethod = this.dataset.method;
	});
});

function ntf(msg, t = 'i') {
	const el = document.getElementById('ntf');
	const ics = { s: '✓', e: '✕', i: '!' };
	document.getElementById('ni').innerText = ics[t] || '·';
	document.getElementById('nm').innerText = msg;
	el.className = 'ntf ' + t + ' on';
	clearTimeout(el._t);
	el._t = setTimeout(() => el.classList.remove('on'), 4400);
}

function calculateTotal(orders) {
	if (orders <= FREE_QUOTA) {
		return { total: 0, paid: 0, free: orders };
	}
	const paid = orders - FREE_QUOTA;
	const total = paid * PRICE_PER_ORDER;
	return { total: Math.round(total * 100) / 100, paid: paid, free: FREE_QUOTA };
}

function updateQuota() {
	const orders = parseInt(document.getElementById('orderSlider').value);
	sel.orders = orders;
	
	const result = calculateTotal(orders);
	const isFree = orders <= FREE_QUOTA;
	
	document.getElementById('orderDisplay').textContent = orders;
	document.getElementById('pricePerOrder').textContent = PRICE_PER_ORDER.toFixed(2);
	
	if (isFree) {
		document.getElementById('priceDisplay').innerHTML = '0 <small>MAD</small>';
		document.getElementById('savingBadge').textContent = '🎁 Gratuit';
	} else {
		document.getElementById('priceDisplay').innerHTML = result.total + ' <small>MAD</small>';
		document.getElementById('savingBadge').textContent = `⚡ ${result.paid} commandes payantes`;
	}
	
	document.getElementById('ppamt').textContent = (isFree ? '0' : result.total.toFixed(2)) + ' MAD';
	document.getElementById('ppnm').textContent = `${orders} commandes — ${isFree ? 'Gratuit 🎁' : result.paid + ' payantes'}`;
	document.getElementById('pper').textContent = isFree ? 'gratuit' : 'par mois';
	
	// Update preset buttons
	document.querySelectorAll('.qp-btn').forEach(b => {
		b.classList.toggle('on', parseInt(b.dataset.q) === orders);
	});
}

document.getElementById('orderSlider').addEventListener('input', function() {
	updateQuota();
});

document.querySelectorAll('.qp-btn').forEach(btn => {
	btn.addEventListener('click', function() {
		const val = parseInt(this.dataset.q);
		document.getElementById('orderSlider').value = val;
		updateQuota();
	});
});

// Initial update
updateQuota();

// ==================== NAVIGATION ÉTAPES ====================
function goTo(n) {
	document.querySelectorAll('.step').forEach((s, i) => s.classList.toggle('on', i === n - 1));
	for (let i = 1; i <= 3; i++) {
		const sc = document.getElementById('sc' + i);
		const sl = document.getElementById('sl' + i);
		sc.classList.remove('on', 'dn');
		sl.classList.remove('on');
		if (i < n) { sc.classList.add('dn'); sc.textContent = '✓'; } 
		else if (i === n) { sc.classList.add('on'); sc.textContent = i; sl.classList.add('on'); } 
		else sc.textContent = i;
	}
	document.getElementById('sf1').classList.toggle('on', n >= 2);
	document.getElementById('sf2').classList.toggle('on', n >= 3);
	window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('b1').addEventListener('click', () => goTo(2));
document.getElementById('b2bk').addEventListener('click', () => goTo(1));
document.getElementById('b3bk').addEventListener('click', () => { vfied = false; goTo(2); });

// ==================== VALIDATION ====================
const eRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const pRe = /^[567][0-9]{8}$/;

function ve(id, m) {
	const e = document.getElementById(id);
	e.textContent = m;
	e.classList.add('on');
	const inp = document.getElementById(id.replace('e', 'i'));
	if (inp) inp.classList.add('err');
}

function ce() {
	document.querySelectorAll('.fe').forEach(e => { e.classList.remove('on'); e.textContent = ''; });
	document.querySelectorAll('.inp.err').forEach(i => i.classList.remove('err'));
}

window.tEye = (id, btn) => {
	const i = document.getElementById(id);
	i.type = i.type === 'password' ? 'text' : 'password';
	const s = btn.querySelector('svg');
	s.innerHTML = i.type === 'text'
		? '<path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14" stroke-width="1.75"/>'
		: '<path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/>';
};

['iN', 'iE', 'iP', 'iW', 'iC'].forEach(id => {
	document.getElementById(id)?.addEventListener('input', () => {
		const errEl = document.getElementById(id.replace('i', 'e'));
		const inp = document.getElementById(id);
		if (errEl) { errEl.classList.remove('on'); errEl.textContent = ''; }
		if (inp) inp.classList.remove('err');
	});
});

// ==================== STEP 2 -> OTP ====================
let uD = {}, code = null, vfied = false, cdI = null;

document.getElementById('b2').addEventListener('click', async () => {
	ce();
	const n = document.getElementById('iN').value.trim();
	const e = document.getElementById('iE').value.trim();
	const p = document.getElementById('iP').value.trim();
	const w = document.getElementById('iW').value;
	const cc = document.getElementById('iC').value;
	let ok = true;
	
	if (!n) { ve('eN', 'Le nom complet est requis'); ok = false; }
	if (!e || !eRe.test(e)) { ve('eE', 'Veuillez entrer un email valide'); ok = false; }
	if (!p || !pRe.test(p)) { ve('eP', '9 chiffres commençant par 5, 6 ou 7'); ok = false; }
	if (w.length < 8) { ve('eW', '8 caractères minimum requis'); ok = false; }
	if (w !== cc) { ve('eC', 'Les mots de passe ne correspondent pas'); ok = false; }
	if (!ok) return;

	const orders = sel.orders;
	const result = calculateTotal(orders);
	const isFree = orders <= FREE_QUOTA;

	uD = {
		name: n,
		email: e,
		phone: p,
		password: w,
		orders: orders,
		totalAmount: result.total,
		paidOrders: result.paid,
		freeOrders: result.free,
		isFree: isFree,
		selectedPlan: isFree ? 'starter' : 'payg',
		method: verificationMethod
	};

	// Send verification code via WhatsApp or SMS
	const btn = document.getElementById('b2');
	btn.disabled = true;
	const methodLabel = verificationMethod === 'whatsapp' ? 'WhatsApp' : 'SMS';
	btn.innerHTML = `<span class="spin"></span> Envoi via ${methodLabel}...`;

	try {
		const response = await fetch('./dashboard/controllers/send_verification.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ 
				phone: p, 
				method: verificationMethod,
				name: n
			})
		});
		const res = await response.json();

		if (res.success) {
			code = res.verification_code || res.debug_code || '123456';
			document.getElementById('dph').innerHTML = `+212 ${p}`;
			document.querySelectorAll('.od').forEach(i => { i.value = ''; i.classList.remove('filled'); });
			document.getElementById('vst').innerHTML = '';
			vfied = false;
			ubtn();
			ntf(`Code envoyé via ${methodLabel} ! ${res.message}`, 'i');
			
			// Show debug code in console for testing
			if (res.debug_code) {
				console.log('📱 Verification code:', res.debug_code);
			}
			
			goTo(3);
			startCD();
			setTimeout(() => document.querySelector('.od').focus(), 350);
		} else {
			ntf(res.message || 'Erreur d\'envoi du code', 'e');
		}
	} catch (err) {
		ntf('Erreur de connexion', 'e');
		console.error('Error:', err);
	} finally {
		btn.disabled = false;
		btn.innerHTML = 'Vérifier le téléphone <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>';
	}
});

// ==================== OTP ====================
function vOTP() {
	const v = [...document.querySelectorAll('.od')].map(i => i.value).join('');
	if (v.length !== 6) return;
	const vs = document.getElementById('vst');
	if (v === code) {
		vs.innerHTML = '<div class="vs ok">✓ Téléphone vérifié avec succès !</div>';
		vfied = true;
		ntf('Téléphone vérifié !', 's');
	} else {
		vs.innerHTML = '<div class="vs bad">✗ Code incorrect — veuillez réessayer</div>';
		vfied = false;
	}
	ubtn();
}

document.querySelectorAll('.od').forEach((inp, idx) => {
	inp.addEventListener('input', () => {
		inp.value = inp.value.replace(/\D/g, '').slice(-1);
		if (inp.value) { inp.classList.add('filled'); } else { inp.classList.remove('filled'); }
		if (inp.value && idx < 5) document.querySelector(`.od[data-i="${idx + 1}"]`).focus();
		if (idx === 5 && inp.value) vOTP();
		if (!inp.value) { document.getElementById('vst').innerHTML = ''; vfied = false; ubtn(); }
	});
	inp.addEventListener('keydown', e => {
		if (e.key === 'Backspace' && !inp.value && idx > 0) {
			const prev = document.querySelector(`.od[data-i="${idx - 1}"]`);
			prev.focus();
			prev.value = '';
			prev.classList.remove('filled');
		}
	});
	inp.addEventListener('paste', e => {
		const p = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
		if (p.length === 6) {
			e.preventDefault();
			document.querySelectorAll('.od').forEach((d, i) => { d.value = p[i] || ''; d.classList.toggle('filled', !!p[i]); });
			vOTP();
		}
	});
});

function startCD() {
	const btn = document.getElementById('resBtn'), sp = document.getElementById('cdt');
	if (cdI) clearInterval(cdI);
	let t = 60;
	btn.disabled = true;
	sp.innerHTML = ` (${t}s)`;
	cdI = setInterval(() => {
		t--;
		if (t <= 0) { clearInterval(cdI); btn.disabled = false; sp.innerHTML = ''; } 
		else sp.innerHTML = ` (${t}s)`;
	}, 1000);
}

document.getElementById('resBtn').addEventListener('click', async () => {
	ntf('Envoi d\'un nouveau code...', 'i');
	try {
		const response = await fetch('./dashboard/controllers/send_verification.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ 
				phone: uD.phone, 
				method: verificationMethod,
				name: uD.name,
				resend: true 
			})
		});
		const res = await response.json();
		if (res.success) {
			code = res.verification_code || res.debug_code || '123456';
			document.querySelectorAll('.od').forEach(i => { i.value = ''; i.classList.remove('filled'); });
			document.getElementById('vst').innerHTML = '';
			vfied = false;
			ubtn();
			ntf('Nouveau code envoyé !', 'i');
			startCD();
			if (res.debug_code) {
				console.log('📱 New verification code:', res.debug_code);
			}
		} else {
			ntf(res.message || 'Erreur d\'envoi', 'e');
		}
	} catch (err) {
		ntf('Erreur de connexion', 'e');
	}
});

function ubtn() {
	document.getElementById('b3').disabled = !(vfied && document.getElementById('tcb').checked);
}
document.getElementById('tcb').addEventListener('change', ubtn);

// ==================== FINAL SUBMIT ====================
document.getElementById('b3').addEventListener('click', async () => {
	if (!vfied || !document.getElementById('tcb').checked) return;
	
	const btn = document.getElementById('b3');
	btn.disabled = true;
	btn.innerHTML = '<span class="spin"></span> Création du compte...';
	
	try {
		// First, send verification confirmation
		const verifyResp = await fetch('./dashboard/controllers/verify_phone.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ 
				phone: uD.phone, 
				code: code,
				method: verificationMethod
			})
		});
		const verifyRes = await verifyResp.json();
		
		if (!verifyRes.success) {
			throw new Error('Vérification du téléphone échouée');
		}
		
		// Then create user with quota
		const userData = {
			name: uD.name,
			email: uD.email,
			password: uD.password,
			phone: uD.phone,
			orders: uD.orders,
			totalAmount: uD.totalAmount,
			paidOrders: uD.paidOrders,
			freeOrders: uD.freeOrders,
			isFree: uD.isFree,
			selectedPlan: uD.selectedPlan,
			verificationMethod: verificationMethod
		};
		
		const createResp = await fetch('./dashboard/controllers/create_user.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(userData)
		});
		const createRes = await createResp.json();
	
		if (createRes.success) {
			ntf('Compte créé avec succès ! Redirection...', 's');
			
			// If not free, redirect to payment
			if (!uD.isFree && createRes.transaction_id) {
				setTimeout(() => {
					window.location.href = `./payment.php?transaction_id=${createRes.transaction_id}`;
				}, 1500);
			} else {
				// Free plan - login automatically
				setTimeout(() => {
					window.location.href = './dashboard/home.php';
				}, 1500);
			}
		} else {
			throw new Error(createRes.message || 'Erreur lors de la création du compte');
		}
	} catch (err) {
		ntf(err.message || 'Une erreur s\'est produite', 'e');
		btn.disabled = false;
		btn.innerHTML = 'Valider l\'inscription <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4 4 6-6"/></svg>';
		ubtn();
	}
});
</script>
</body>
</html>