<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="OBG ECOM — Plateforme de commerce marocaine. Tableau de bord centralisé pour les vendeurs modernes.">
<title>OBG ECOM · Connexion</title>
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

/* fond à pois subtil */
body::before{
  content:'';
  position:fixed;
  inset:0;
  background-image:radial-gradient(circle,#c8c5bb 1px,transparent 1px);
  background-size:28px 28px;
  opacity:0.35;
  pointer-events:none;
  z-index:0;
}

/* formes décoratives */
.blob{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(60px);}
.blob-a{width:500px;height:500px;top:-180px;left:-180px;background:radial-gradient(circle,rgba(92,69,232,0.07) 0%,transparent 70%);}
.blob-b{width:400px;height:400px;bottom:-120px;right:-100px;background:radial-gradient(circle,rgba(124,106,240,0.06) 0%,transparent 70%);}

/* LAYOUT */
.split{
  position:relative;
  z-index:1;
  display:flex;
  width:100%;
  min-height:100vh;
  flex-direction:column;
}

/* ==================== PARTIE GAUCHE (HERO) ==================== */
.hero{
  padding:2rem 1.75rem 2.5rem;
  background:var(--surface);
  border-bottom:1px solid var(--border);
  position:relative;
  overflow:hidden;
}
.hero::after{
  content:'';
  position:absolute;
  top:-40px;right:-40px;
  width:180px;height:180px;
  border-radius:50%;
  background:var(--accent-light);
  opacity:0.7;
}

.logo{
  display:flex;align-items:center;gap:10px;
  font-family:'Sora',sans-serif;font-size:0.9rem;font-weight:700;
  color:var(--text);letter-spacing:-0.01em;
  position:relative;z-index:1;
}
.logo-mark{
  width:34px;height:34px;border-radius:10px;
  background:var(--accent);
  display:grid;place-items:center;
  font-size:0.6rem;font-weight:800;color:#fff;
  letter-spacing:0.02em;
  flex-shrink:0;
}
.logo-name{display:flex;flex-direction:column;line-height:1.1;}
.logo-name span:first-child{font-size:0.85rem;font-weight:700;color:var(--text);}
.logo-name span:last-child{font-size:0.55rem;font-weight:500;color:var(--text3);letter-spacing:0.08em;text-transform:uppercase;}

.hero-body{margin-top:2rem;position:relative;z-index:1;}
.eyebrow{
  display:inline-flex;align-items:center;gap:6px;
  font-size:0.6rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;
  color:var(--accent);margin-bottom:1rem;
  background:var(--accent-light);padding:4px 10px;border-radius:var(--r-pill);
}
.edot{width:5px;height:5px;border-radius:50%;background:var(--accent);flex-shrink:0;}

.hero-t{
  font-family:'Sora',sans-serif;
  font-size:1.85rem;font-weight:700;
  line-height:1.18;letter-spacing:-0.03em;
  color:var(--text);margin-bottom:0.85rem;
}
.hero-t em{font-style:normal;color:var(--accent);}

.hero-d{font-size:0.82rem;color:var(--text2);line-height:1.6;margin-bottom:1.75rem;max-width:100%;}

.feats{display:flex;flex-direction:column;gap:0.55rem;margin-bottom:1.75rem;}
.fi{
  display:flex;align-items:center;gap:10px;
  font-size:0.78rem;color:var(--text2);
  padding:0.55rem 0.8rem;
  background:var(--surface2);
  border:1px solid var(--border);
  border-radius:var(--r-sm);
  transition:border-color 0.2s,background 0.2s;
}
.fi:hover{border-color:var(--accent-mid);background:#f8f7ff;}
.fchk{
  flex-shrink:0;width:18px;height:18px;border-radius:5px;
  background:var(--accent-light);
  display:grid;place-items:center;
}
.fchk svg{width:9px;height:9px;stroke:var(--accent);fill:none;stroke-width:2.5;}

.hero-ft{
  display:flex;align-items:center;gap:10px;
  padding-top:1.25rem;
  border-top:1px solid var(--border);
}
.avstack{display:flex;}
.av{
  width:26px;height:26px;border-radius:50%;
  border:2px solid var(--surface);
  display:grid;place-items:center;
  font-size:0.5rem;font-weight:700;color:#fff;
  margin-right:-6px;
}
.av:nth-child(1){background:linear-gradient(135deg,#5c45e8,#4338ca);}
.av:nth-child(2){background:linear-gradient(135deg,#7c6af0,#5c45e8);}
.av:nth-child(3){background:linear-gradient(135deg,#4338ca,#7c6af0);}
.avtext{font-size:0.72rem;color:var(--text3);font-weight:500;padding-left:4px;}
.avtext strong{color:var(--text2);font-weight:600;}

/* badges de confiance */
.trust-row{
  display:flex;align-items:center;gap:1rem;
  flex-wrap:wrap;margin-top:0.75rem;
}
.trust-badge{
  display:flex;align-items:center;gap:5px;
  font-size:0.65rem;color:var(--text3);font-weight:500;
  background:var(--surface2);border:1px solid var(--border);
  padding:3px 8px;border-radius:var(--r-pill);
}
.trust-badge svg{width:11px;height:11px;stroke:var(--text3);fill:none;stroke-width:1.75;}

/* ==================== PARTIE DROITE (FORMULAIRE) ==================== */
.fp{
  flex:1;
  display:flex;align-items:center;justify-content:center;
  padding:2rem 1.5rem 2.5rem;
  background:var(--bg);
}
.fb{width:100%;max-width:100%;}

/* back to home link */
.back-home {
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:0.7rem;
  font-weight:600;
  color:var(--text3);
  text-decoration:none;
  margin-bottom:0.25rem;
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

.switch-tabs{
  display:flex;
  border-bottom:1px solid var(--border);
  margin-bottom:1.75rem;
  gap:0;
}
.tab-btn{
  background:none;border:none;
  padding:0.65rem 0;
  font-size:0.82rem;font-weight:600;
  font-family:'DM Sans',sans-serif;
  color:var(--text3);
  cursor:pointer;transition:color 0.2s;
  position:relative;flex:1;text-align:center;
}
.tab-btn.active{color:var(--accent);}
.tab-btn.active::after{
  content:'';position:absolute;
  bottom:-1px;left:0;right:0;height:2px;
  background:var(--accent);border-radius:2px 2px 0 0;
}
.tab-btn:hover:not(.active){color:var(--text2);}

.auth-step{display:none;animation:si .28s cubic-bezier(0.16,1,0.3,1);}
.auth-step.active{display:block;}
@keyframes si{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}

.form-head{margin-bottom:1.5rem;}
.sh2{
  font-family:'Sora',sans-serif;
  font-size:1.45rem;font-weight:700;
  letter-spacing:-0.025em;color:var(--text);margin-bottom:4px;
}
.ss{font-size:0.78rem;color:var(--text3);line-height:1.5;}

.fr{margin-bottom:0.95rem;}
.fr label{
  display:block;font-size:0.62rem;font-weight:700;
  letter-spacing:0.06em;text-transform:uppercase;
  color:var(--text2);margin-bottom:5px;
}
.iw{position:relative;}
.iw svg.ico{
  position:absolute;left:12px;top:50%;transform:translateY(-50%);
  width:14px;height:14px;stroke:var(--text3);fill:none;stroke-width:1.75;pointer-events:none;
}
.inp{
  width:100%;
  padding:11px 12px 11px 38px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--r);
  color:var(--text);
  font-size:0.83rem;font-family:'DM Sans',sans-serif;
  outline:none;transition:all 0.22s;
  appearance:none;-webkit-appearance:none;
}
.inp::placeholder{color:var(--text3);font-size:0.8rem;}
.inp:hover:not(:focus){border-color:var(--border2);}
.inp:focus{
  border-color:var(--accent);
  background:var(--surface);
  box-shadow:0 0 0 3px rgba(92,69,232,0.1);
}
.inp.err{border-color:var(--error);box-shadow:0 0 0 3px rgba(192,57,43,0.08);}

.eye2{
  position:absolute;right:11px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:var(--text3);display:grid;place-items:center;padding:4px;
  transition:color 0.2s;border-radius:4px;
}
.eye2:hover{color:var(--text2);}
.eye2 svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.75;}

.fe{font-size:0.69rem;color:var(--error);margin-top:4px;display:none;}
.fe.on{display:flex;align-items:center;gap:4px;}
.fe::before{content:'';display:inline-block;width:4px;height:4px;border-radius:50%;background:var(--error);flex-shrink:0;}

.forgot-link{text-align:right;margin-top:5px;}
.forgot-link a{color:var(--accent);font-size:0.7rem;text-decoration:none;font-weight:600;}
.forgot-link a:hover{text-decoration:underline;}

.btn{
  width:100%;padding:11px 16px;
  border:none;border-radius:var(--r-pill);
  font-size:0.85rem;font-weight:700;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:7px;
  transition:all 0.22s ease;
  margin-top:0.25rem;
}
.btn-p{
  background:var(--accent);color:#fff;
  box-shadow:0 2px 8px rgba(92,69,232,0.22);
}
.btn-p:hover{background:#4e39d4;box-shadow:0 4px 16px rgba(92,69,232,0.35);transform:translateY(-1px);}
.btn-p:active{transform:translateY(0);box-shadow:0 2px 6px rgba(92,69,232,0.2);}
.btn-p:disabled{opacity:0.52;cursor:not-allowed;transform:none;box-shadow:none;}

.sil{
  text-align:center;margin-top:1.25rem;
  font-size:0.74rem;color:var(--text3);
}
.sil a{color:var(--accent);text-decoration:none;font-weight:600;}
.sil a:hover{text-decoration:underline;}

/* séparateur */
.divider{
  display:flex;align-items:center;gap:10px;
  margin:1.1rem 0;font-size:0.7rem;color:var(--text3);
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* boutons sociaux */
.social-row{display:flex;gap:8px;}
.soc-btn{
  flex:1;padding:9px 8px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--r);
  font-size:0.75rem;font-weight:600;
  font-family:'DM Sans',sans-serif;
  color:var(--text2);cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:all 0.2s;
}
.soc-btn:hover{border-color:var(--border2);background:var(--surface2);}
.soc-btn svg{width:14px;height:14px;flex-shrink:0;}

/* TOAST */
.ntf{
  position:fixed;
  top:16px;left:16px;right:16px;
  z-index:9999;
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

.spin{
  display:inline-block;width:15px;height:15px;
  border:2px solid rgba(255,255,255,0.3);
  border-radius:50%;border-top-color:#fff;
  animation:sp 0.6s linear infinite;
}
@keyframes sp{to{transform:rotate(360deg);}}

/* ==================== RESPONSIVE ==================== */
@media(min-width:640px){
  .split{flex-direction:row;}
  .hero{
    flex:0 0 44%;
    padding:2.5rem;
    border-bottom:none;
    border-right:1px solid var(--border);
    display:flex;flex-direction:column;justify-content:space-between;
  }
  .hero-t{font-size:2.1rem;}
  .hero-d{font-size:0.85rem;}
  .feats{flex-direction:column;}
  .fp{padding:2rem 2.5rem 2rem 3rem;}
  .fb{max-width:400px;}
  .ntf{left:auto;right:20px;top:20px;max-width:340px;transform:translateX(calc(100% + 1.5rem));opacity:1;}
  .ntf.on{transform:translateX(0);}
}
@media(min-width:1024px){
  .hero{flex:0 0 42%;padding:3rem 2.5rem;}
  .hero-t{font-size:2.6rem;}
  .fp{padding:3rem 4rem 3rem 5rem;}
  .fb{max-width:440px;}
  .sh2{font-size:1.65rem;}
}
@media(max-width:380px){
  .hero-t{font-size:1.6rem;}
  .fi{font-size:0.72rem;}
  .inp{padding:10px 10px 10px 36px;font-size:0.8rem;}
  .btn{padding:10px;}
}
</style>
</head>
<body>
<div class="blob blob-a"></div>
<div class="blob blob-b"></div>

<div class="split">
  <!-- ==================== PARTIE GAUCHE ==================== -->
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
        <h1 class="hero-t">Bon retour.<br><em>Développez plus intelligemment.</em></h1>
        <p class="hero-d">Accédez à votre tableau de bord centralisé — commandes, livraisons, analytique et logistique dans un espace épuré.</p>

        <div class="feats">
          <div class="fi">
            <div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>
            Synchronisation automatique multicanal
          </div>
          <div class="fi">
            <div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>
            Suivi multi-transporteurs en temps réel
          </div>
          <div class="fi">
            <div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>
            Analytique prédictive IA
          </div>
          <div class="fi">
            <div class="fchk"><svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg></div>
            Facturation et reporting automatisés
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="hero-ft">
        <div class="avstack">
          <div class="av">YA</div><div class="av">MO</div><div class="av">KH</div>
        </div>
        <div class="avtext"><strong>1 400+</strong> commerçants actifs</div>
      </div>
      <div class="trust-row">
        <div class="trust-badge">
          <svg viewBox="0 0 16 16"><path d="M8 14s-6-4-6-8a6 6 0 0112 0c0 4-6 8-6 8z"/></svg>
          Approuvé Maroc
        </div>
        <div class="trust-badge">
          <svg viewBox="0 0 16 16"><rect x="2" y="11" width="12" height="3" rx="1"/><path d="M5 11V6a3 3 0 016 0v5"/><circle cx="8" cy="8" r="1"/></svg>
          Sécurisé SSL
        </div>
        <div class="trust-badge">
          <svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 2"/></svg>
          Support 24/7
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== PARTIE DROITE (FORMULAIRE) ==================== -->
  <div class="fp">
    <div class="fb">
      
      <!-- Back to Home link -->
      <a href="./" class="back-home">
        <svg viewBox="0 0 16 16"><path d="M2 8l6-6 6 6"/><path d="M4 6v6h2V8h4v4h2V6"/></svg>
        Retour à l'accueil
      </a>

      <div class="switch-tabs">
        <button class="tab-btn active" data-tab="login">Se connecter</button>
        <button class="tab-btn" data-tab="forgot">Réinitialiser mot de passe</button>
      </div>

      <!-- CONNEXION -->
      <div class="auth-step active" id="loginStep">
        <div class="form-head">
          <h2 class="sh2">Accéder au tableau de bord</h2>
          <p class="ss">Utilisez votre email, identifiant ou numéro de téléphone</p>
        </div>

        <div class="fr">
          <label>Email / Identifiant / Téléphone</label>
          <div class="iw">
            <svg class="ico" viewBox="0 0 16 16"><path d="M13 13c0-2.2-2.2-4-5-4S3 10.8 3 13"/><circle cx="8" cy="5" r="3"/></svg>
            <input class="inp" type="text" id="loginUsername" placeholder="commercant@obgecom.ma" autocomplete="username">
          </div>
          <div class="fe" id="loginUsernameError"></div>
        </div>

        <div class="fr">
          <label>Mot de passe</label>
          <div class="iw">
            <svg class="ico" viewBox="0 0 16 16"><rect x="3" y="7" width="10" height="8" rx="1"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>
            <input class="inp" type="password" id="loginPassword" placeholder="••••••••" autocomplete="current-password">
            <button class="eye2" type="button" id="toggleLoginPwd" aria-label="Afficher/masquer le mot de passe">
              <svg id="eyeIcon" viewBox="0 0 16 16"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
            </button>
          </div>
          <div class="fe" id="loginPasswordError"></div>
          <div class="forgot-link"><a href="#" id="switchToForgotInline">Mot de passe oublié ?</a></div>
        </div>

        <button class="btn btn-p" id="loginBtn">
          Se connecter
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
        </button>

        <div class="divider">ou continuer avec</div>
        <div class="social-row">
          <button class="soc-btn" onclick="notif('Connexion Google bientôt disponible','i')">
            <svg viewBox="0 0 16 16" fill="none"><path d="M14.5 8.16c0-.5-.04-1-.13-1.5H8v2.84h3.65a3.12 3.12 0 01-1.35 2.04v1.7h2.19c1.28-1.18 2.01-2.91 2.01-5.08z" fill="#4285F4"/><path d="M8 15c1.83 0 3.36-.61 4.48-1.65l-2.19-1.7c-.61.41-1.38.65-2.29.65-1.76 0-3.25-1.19-3.78-2.79H2v1.75A7 7 0 008 15z" fill="#34A853"/><path d="M4.22 9.51A4.18 4.18 0 014 8c0-.52.09-1.02.22-1.51V4.74H2A7 7 0 001 8c0 1.13.27 2.2.75 3.13l2.47-1.62z" fill="#FBBC05"/><path d="M8 3.71c.99 0 1.88.34 2.58 1.01l1.93-1.93A7 7 0 002 4.74l2.22 1.75C4.75 4.9 6.24 3.71 8 3.71z" fill="#EA4335"/></svg>
            Google
          </button>
          <button class="soc-btn" onclick="notif('Connexion par téléphone bientôt disponible','i')">
            <svg viewBox="0 0 16 16" fill="none" stroke="#374151" stroke-width="1.5"><path d="M11 1H5a1 1 0 00-1 1v12a1 1 0 001 1h6a1 1 0 001-1V2a1 1 0 00-1-1zM8 13h.01"/></svg>
            OTP Téléphone
          </button>
        </div>

        <div class="sil">Pas encore de compte ? <a href="signup">Inscrivez-vous gratuitement.</a></div>
      </div>

      <!-- RÉINITIALISATION MOT DE PASSE -->
      <div class="auth-step" id="forgotStep">
        <div class="form-head">
          <h2 class="sh2">Réinitialiser le mot de passe</h2>
          <p class="ss">Saisissez votre email enregistré, nous vous enverrons un lien de récupération sécurisé</p>
        </div>

        <div class="fr">
          <label>Email enregistré</label>
          <div class="iw">
            <svg class="ico" viewBox="0 0 16 16"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 5l6 5 6-5"/></svg>
            <input class="inp" type="email" id="forgotEmail" placeholder="hello@votreboutique.ma" autocomplete="email">
          </div>
          <div class="fe" id="forgotEmailError"></div>
        </div>

        <button class="btn btn-p" id="forgotBtn">
          Envoyer le lien
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4 4 6-6"/></svg>
        </button>
        <div class="sil"><a href="#" id="backToLogin">← Retour à la connexion</a></div>
      </div>

    </div>
  </div>
</div>

<!-- TOAST NOTIFICATIONS -->
<div class="ntf" id="ntf">
  <div class="ntf-icon" id="ni"></div>
  <span id="nm"></span>
</div>

<script>
// ==================== FONCTIONS UTILITAIRES ====================
let isLoading = false;

function notif(message, type = 'i') {
  const el = document.getElementById('ntf');
  const icons = { s: '✓', e: '✕', i: '!' };
  document.getElementById('ni').innerText = icons[type] || '·';
  document.getElementById('nm').innerText = message;
  el.className = 'ntf ' + type + ' on';
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('on'), 4400);
}

function setLoading(btnId, loading) {
  const btn = document.getElementById(btnId);
  if (!btn) return;
  if (loading) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Veuillez patienter…';
    isLoading = true;
  } else {
    btn.disabled = false;
    if (btnId === 'loginBtn') btn.innerHTML = 'Se connecter <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>';
    else if (btnId === 'forgotBtn') btn.innerHTML = 'Envoyer le lien <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4 4 6-6"/></svg>';
    isLoading = false;
  }
}

function showError(inputId, msg) {
  const input = document.getElementById(inputId);
  const errDiv = document.getElementById(inputId + 'Error');
  if (input) input.classList.add('err');
  if (errDiv) { errDiv.innerText = msg; errDiv.classList.add('on'); }
}
function clearErrors() {
  document.querySelectorAll('.fe').forEach(e => { e.classList.remove('on'); e.innerText = ''; });
  document.querySelectorAll('.inp.err').forEach(i => i.classList.remove('err'));
}
function validateEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }

// ==================== AFFICHAGE/MASQUAGE MOT DE PASSE ====================
const toggleBtn = document.getElementById('toggleLoginPwd');
const pwdInput = document.getElementById('loginPassword');
if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    const show = pwdInput.type === 'password';
    pwdInput.type = show ? 'text' : 'password';
    document.getElementById('eyeIcon').innerHTML = show
      ? '<path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14" stroke-width="1.75"/>'
      : '<path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/>';
  });
}

// ==================== NAVIGATION PAR ONGLETS ====================
const tabBtns = document.querySelectorAll('.tab-btn');
const loginStep = document.getElementById('loginStep');
const forgotStep = document.getElementById('forgotStep');

function switchAuthTab(tab) {
  clearErrors();
  if (tab === 'login') {
    loginStep.classList.add('active');
    forgotStep.classList.remove('active');
  } else {
    loginStep.classList.remove('active');
    forgotStep.classList.add('active');
  }
  tabBtns.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-tab') === tab));
}

tabBtns.forEach(btn => btn.addEventListener('click', () => switchAuthTab(btn.getAttribute('data-tab'))));
document.getElementById('switchToForgotInline')?.addEventListener('click', e => { e.preventDefault(); switchAuthTab('forgot'); });
document.getElementById('backToLogin')?.addEventListener('click', e => { e.preventDefault(); switchAuthTab('login'); });

// ==================== CONNEXION ====================
async function handleLogin() {
  if (isLoading) return;
  clearErrors();
  const username = document.getElementById('loginUsername').value.trim();
  const password = document.getElementById('loginPassword').value;
  let hasErr = false;
  if (!username) { showError('loginUsername', 'L\'identifiant, email ou téléphone est requis'); hasErr = true; }
  if (!password) { showError('loginPassword', 'Le mot de passe est requis'); hasErr = true; }
  if (hasErr) return;
  setLoading('loginBtn', true);
  try {
    const response = await fetch('./dashboard/controllers/handle_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    const data = await response.json();
    if (response.ok && data.success) {
      notif(data.message + ' — redirection…', 's');
      setTimeout(() => { window.location.href = data.redirect; }, 1500);
    } else if (data.account_inactive) {
      notif(data.message, 'i');
      setTimeout(() => { window.location.href = `${data.redirect}?user_id=${data.user_id}&email=${encodeURIComponent(data.email)}`; }, 1500);
    } else {
      notif(data.message || 'Identifiants incorrects. Veuillez réessayer.', 'e');
    }
  } catch (err) {
    notif('Erreur de connexion. Vérifiez votre réseau.', 'e');
  } finally {
    setLoading('loginBtn', false);
  }
}

document.getElementById('loginBtn').addEventListener('click', handleLogin);
['loginPassword', 'loginUsername'].forEach(id => {
  document.getElementById(id)?.addEventListener('keypress', e => { if (e.key === 'Enter') handleLogin(); });
});
['loginUsername', 'loginPassword'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', () => {
    const errDiv = document.getElementById(id + 'Error');
    const input = document.getElementById(id);
    if (errDiv) { errDiv.classList.remove('on'); errDiv.innerText = ''; }
    if (input) input.classList.remove('err');
  });
});

// ==================== RÉINITIALISATION ====================
async function handleForgot() {
  if (isLoading) return;
  clearErrors();
  const email = document.getElementById('forgotEmail').value.trim();
  if (!email) { showError('forgotEmail', 'L\'adresse email est requise'); return; }
  if (!validateEmail(email)) { showError('forgotEmail', 'Entrez une adresse email valide (ex: nom@domaine.com)'); return; }
  setLoading('forgotBtn', true);
  try {
    const resp = await fetch('./dashboard/controllers/forgot_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    });
    const data = await resp.json();
    if (data.success) {
      notif(data.message, 's');
      setTimeout(() => switchAuthTab('login'), 2200);
    } else {
      notif(data.message, 'e');
    }
  } catch (err) {
    notif('Erreur réseau. Veuillez réessayer.', 'e');
  } finally {
    setLoading('forgotBtn', false);
  }
}

document.getElementById('forgotBtn').addEventListener('click', handleForgot);
document.getElementById('forgotEmail')?.addEventListener('keypress', e => { if (e.key === 'Enter') handleForgot(); });
document.getElementById('forgotEmail')?.addEventListener('input', () => {
  const errDiv = document.getElementById('forgotEmailError');
  const input = document.getElementById('forgotEmail');
  if (errDiv) { errDiv.classList.remove('on'); errDiv.innerText = ''; }
  if (input) input.classList.remove('err');
});

// Notifications via paramètres URL
if (window.location.search.includes('verified=true')) notif('Téléphone vérifié — vous pouvez maintenant vous connecter.', 's');
else if (window.location.search.includes('reset=sent')) notif('Lien de réinitialisation envoyé — consultez votre boîte de réception.', 'i');
</script>
</body>
</html>