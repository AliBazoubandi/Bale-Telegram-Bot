<?php
/**
 * index.php — the front door for the domain root.
 *
 * Visitors who open the bare domain see this page instead of a folder listing.
 * The real application lives under /admin.
 *
 * Self-contained: no external fonts, scripts, images or packages.
 */

// ---- Settings ---------------------------------------------------------------
$adminPath = '/admin/';   // where the button leads
$siteName  = '';          // leave empty to show the domain name itself
$noindex   = true;        // true = ask search engines not to list this page
// -----------------------------------------------------------------------------

$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
$host = strtolower(preg_replace('/:\d+$/', '', $host));
$host = preg_replace('/[^a-z0-9.\-]/', '', $host);
$host = preg_replace('/^www\./', '', $host);
if ($host === '') {
    $host = 'localhost';
}
$name  = ($siteName !== '') ? $siteName : $host;
$nonce = base64_encode(random_bytes(16));

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$nonce'; script-src 'nonce-$nonce'; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
if ($noindex) {
    header('X-Robots-Tag: noindex, nofollow');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#070B1A">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23070B1A'/%3E%3Ccircle cx='16' cy='16' r='6' fill='%23FFB08A'/%3E%3Cpath d='M0 22 L9 17 L16 21 L23 16 L32 22 V32 H0Z' fill='%231A1840'/%3E%3C/svg%3E">
<script nonce="<?= $nonce ?>">document.documentElement.className+=' js';</script>
<style nonce="<?= $nonce ?>">
  :root{
    --ink:#070B1A;
    --mist:#EEF0FF;
    --dim:#B4BBE0;
    --faint:#7D86B8;
    --sun:#FFB08A;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  html{background:var(--ink)}
  body{
    background:linear-gradient(#04061A,#1B1445 55%,#070B1A);
    color:var(--mist);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
  }
  #scape{position:fixed;inset:0;width:100%;height:100%;display:block}

  main{
    position:relative;z-index:1;
    min-height:100vh;min-height:100svh;
    display:flex;flex-direction:column;justify-content:space-between;
    padding:clamp(28px,8vh,104px) clamp(24px,6vw,88px) max(28px,env(safe-area-inset-bottom));
  }
  .content{max-width:56rem}
  h1{
    font-family:"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua",Georgia,serif;
    font-weight:400;
    font-size:clamp(2.5rem,8vw,6.5rem);
    line-height:.98;
    letter-spacing:-.035em;
    overflow-wrap:anywhere;
  }
  .lede{
    margin-top:clamp(16px,2.4vh,26px);
    max-width:34ch;
    font-size:1.0625rem;
    line-height:1.6;
    color:var(--dim);
  }
  .cta{
    display:inline-flex;align-items:center;gap:.7rem;
    margin-top:clamp(22px,3.4vh,36px);
    padding:.95rem 1.6rem .95rem 1.25rem;
    border-radius:999px;
    background:var(--mist);color:var(--ink);
    font-size:1rem;font-weight:600;letter-spacing:.005em;
    text-decoration:none;
    transition:background-color .25s ease,transform .15s ease;
  }
  .cta .dot{
    width:.7rem;height:.7rem;border-radius:50%;
    background:radial-gradient(circle at 35% 35%,#FFE9D6,#FF9A78 70%);
  }
  .cta:hover{background:#FFE3D2}
  .cta:active{transform:translateY(1px)}
  .cta:focus-visible{outline:2px solid var(--sun);outline-offset:4px}
  footer{font-size:.8125rem;color:#fff;text-align:center;line-height:1.5}

  .js .content{opacity:0;transform:translateY(10px)}
  .js.on .content{
    opacity:1;transform:none;
    transition:opacity 1.6s ease,transform 1.6s cubic-bezier(.2,.7,.2,1);
  }
  @media (max-height:560px) and (min-width:700px){
    h1{font-size:clamp(2rem,6vw,4rem)}
    .lede{display:none}
  }
  @media (prefers-reduced-motion:reduce){
    .js .content{opacity:1;transform:none;transition:none}
    .cta{transition:none}
  }
</style>
</head>
<body>
<canvas id="scape" aria-hidden="true"></canvas>

<main>
  <div class="content">
    <h1><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="lede">There is nothing to browse at this address. The application lives in the admin area.</p>
    <a class="cta" id="enter" href="<?= htmlspecialchars($adminPath, ENT_QUOTES, 'UTF-8') ?>"><span class="dot" aria-hidden="true"></span>Open admin</a>
  </div>
  <footer>✨ All rights of this website are reserved for the Rahbarian Rabin Business Intelligence Team. ✨</footer>
</main>

<script nonce="<?= $nonce ?>">
(function () {
  var root = document.documentElement;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cv = document.getElementById('scape');
  var ctx = cv.getContext('2d');
  var cta = document.getElementById('enter');

  var W = 0, H = 0, DPR = 1, HOR = 0;
  var LINES = 44, PTS = 120;
  var S = {
    t: 0, intro: reduce ? 1 : 0,
    px: .5, py: .5, tpx: .5, tpy: .5,
    act: 0, tact: 0,
    pass: .5, open: 1, topen: 1,
    flare: 0, tflare: 0
  };

  function clamp(x, a, b) { return x < a ? a : x > b ? b : x; }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function ease(t) { return 1 - Math.pow(1 - t, 3); }
  function sstep(a, b, x) { var t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); }
  function noise(x, z, t) {
    return Math.sin(x * 19 + z * 5.3 + t * .35) * .5 +
           Math.sin(x * 37 - z * 8.9 - t * .27) * .3 +
           Math.sin(x * 71 + z * 17.1 + t * .51) * .12;
  }

  function resize() {
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    W = window.innerWidth; H = window.innerHeight;
    cv.width = Math.round(W * DPR); cv.height = Math.round(H * DPR);
    HOR = H * (W < 700 ? .58 : (H < 560 ? .64 : .52));
    draw();
  }

  function draw() {
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);

    // sky
    var sky = ctx.createLinearGradient(0, 0, 0, HOR + H * .05);
    sky.addColorStop(0, '#04061A');
    sky.addColorStop(.62, '#0F1039');
    sky.addColorStop(1, '#2B1B50');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, W, H);

    // sun, rising through the pass
    var ie = ease(clamp(S.intro, 0, 1));
    var sx = W * S.pass;
    var sy = HOR - H * .035 + (1 - ie) * H * .13;
    var boost = 1 + S.flare * .6;

    var gr = Math.max(W, H) * .8;
    var g = ctx.createRadialGradient(sx, sy, 0, sx, sy, gr);
    g.addColorStop(0,   'rgba(255,170,130,' + Math.min(1, .7 * boost).toFixed(3) + ')');
    g.addColorStop(.1,  'rgba(255,120,140,' + Math.min(1, .4 * boost).toFixed(3) + ')');
    g.addColorStop(.35, 'rgba(110,80,200,.15)');
    g.addColorStop(1,   'rgba(60,40,140,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    var halo = ctx.createRadialGradient(sx, sy, 0, sx, sy, H * .12 * boost);
    halo.addColorStop(0, 'rgba(255,214,180,.55)');
    halo.addColorStop(1, 'rgba(255,170,130,0)');
    ctx.fillStyle = halo;
    ctx.fillRect(sx - H * .2, sy - H * .2, H * .4, H * .4);

    ctx.fillStyle = 'rgb(255,238,216)';
    ctx.beginPath();
    ctx.arc(sx, sy, H * .03 * (1 + S.flare * .35), 0, Math.PI * 2);
    ctx.fill();

    // ridges, back to front
    var span = H * 1.03 - HOR;
    var ptz = clamp((S.py * H - HOR) / span, 0, 1);
    var pz = Math.pow(ptz, 1 / 1.7);
    ctx.lineJoin = 'round';

    for (var i = 0; i < LINES; i++) {
      var z = i / (LINES - 1);
      var base = HOR + span * Math.pow(z, 1.7);
      var amp = H * (.035 + .25 * Math.pow(z, 1.2));
      var li = ease(clamp(S.intro * 1.5 - z * .5, 0, 1));
      var w = (.07 + .16 * z) * S.open;
      var top = new Path2D();

      for (var j = 0; j < PTS; j++) {
        var xn = (j / (PTS - 1)) * 1.1 - .05;
        var d = Math.abs(xn - S.pass);
        var env = sstep(w * .2, w * 1.9 + .12, d);
        var v = noise(xn, z, S.t) / .92 * .5 + .5;
        var hgt = amp * (.05 + .95 * env) * (.35 + .65 * v);
        var dx = xn - S.px, dz = z - pz;
        hgt += Math.exp(-dx * dx / .012 - dz * dz / .04) * amp * .55 * S.act * (.35 + .65 * env);
        var x = xn * W, y = base - hgt * li;
        if (j === 0) top.moveTo(x, y); else top.lineTo(x, y);
      }

      var area = new Path2D(top);
      area.lineTo(W * 1.05, H + 4);
      area.lineTo(-W * .05, H + 4);
      area.closePath();

      ctx.fillStyle = 'rgb(' + Math.round(lerp(26, 6, z)) + ',' + Math.round(lerp(22, 9, z)) + ',' + Math.round(lerp(64, 22, z)) + ')';
      ctx.fill(area);

      var r = Math.round(lerp(255, 176, z)), gg = Math.round(lerp(184, 196, z)), b = Math.round(lerp(150, 255, z));
      if (z < .3) {
        ctx.lineWidth = 5;
        ctx.strokeStyle = 'rgba(' + r + ',' + gg + ',' + b + ',.08)';
        ctx.stroke(top);
      }
      ctx.lineWidth = .9 + z * .9;
      ctx.strokeStyle = 'rgba(' + r + ',' + gg + ',' + b + ',' + lerp(.95, .7, z).toFixed(2) + ')';
      ctx.stroke(top);
    }
  }

  var last = 0;
  function tick(ts) {
    var dt = Math.min(.05, (ts - last) / 1000 || 0);
    last = ts;
    S.t += dt;
    if (S.t > .25 && S.intro < 1) S.intro = Math.min(1, S.intro + dt / 3.2);

    var kp = 1 - Math.exp(-dt * 6);
    var kPass = 1 - Math.exp(-dt * 2.2);
    S.px += (S.tpx - S.px) * kp;
    S.py += (S.tpy - S.py) * kp;
    S.act += (S.tact - S.act) * kp;
    S.pass += (.5 + (S.tpx - .5) * .3 + Math.sin(S.t * .2) * .02 - S.pass) * kPass;
    S.open += (S.topen - S.open) * (1 - Math.exp(-dt * 3));
    S.flare += (S.tflare - S.flare) * (1 - Math.exp(-dt * 5));

    draw();
    requestAnimationFrame(tick);
  }

  window.addEventListener('resize', resize);
  resize();

  setTimeout(function () { root.classList.add('on'); }, reduce ? 0 : 500);

  if (!reduce) {
    window.addEventListener('pointermove', function (e) {
      S.tpx = e.clientX / W; S.tpy = e.clientY / H; S.tact = 1;
    }, { passive: true });
    window.addEventListener('pointerup', function (e) { if (e.pointerType !== 'mouse') S.tact = 0; });
    window.addEventListener('pointercancel', function () { S.tact = 0; });
    root.addEventListener('mouseleave', function () { S.tact = 0; });

    cta.addEventListener('pointerenter', function () { S.topen = 1.45; });
    cta.addEventListener('pointerleave', function () { S.topen = 1; });
    cta.addEventListener('focus', function () { S.topen = 1.45; });
    cta.addEventListener('blur', function () { S.topen = 1; });

    cta.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      S.tflare = 1; S.topen = 2.1;
      setTimeout(function () { window.location.href = cta.href; }, 550);
    });

    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { S.tflare = 0; S.topen = 1; }
    });

    requestAnimationFrame(tick);
  }
})();
</script>
</body>
</html>