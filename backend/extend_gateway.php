<?php
// Public page — the shortener's "final destination" should point here.
// It's the SAME URL for every user (existing users extending, AND new
// users generating their first token); the browser tells us who's who
// via whichever key it saved to localStorage before leaving for the
// shortener: 'appguard_extend_token' (existing token) or
// 'appguard_signup_sig' (brand-new signup).
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AppGuard — Verifying…</title>
<style>
  body{font-family:system-ui,sans-serif;background:#10141C;color:#ECEFF4;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;box-sizing:border-box;}
  .box{background:#171D28;border:1px solid #2A3240;border-radius:12px;padding:32px;max-width:380px;width:100%;}
  h1{font-size:18px;margin:0 0 10px;}
  p{font-size:13.5px;color:#9AA5B8;line-height:1.6;margin:0 0 14px;}
  .ok{color:#4FA8A0;}
  .err{color:#E0625B;}
  a{color:#C9A24B;}
  .spinner{width:28px;height:28px;border:3px solid #2A3240;border-top-color:#C9A24B;border-radius:50%;margin:0 auto 16px;animation:spin 0.8s linear infinite;}
  @keyframes spin{to{transform:rotate(360deg);}}
  .tokenBox{background:#1B2230;border:1px dashed #C9A24B;border-radius:8px;padding:12px;font-family:monospace;font-size:15px;letter-spacing:0.5px;margin:14px 0;word-break:break-all;}
  button{width:100%;background:#C9A24B;color:#14100a;border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;}
</style>
</head>
<body>
  <div class="box" id="box">
    <div class="spinner"></div>
    <h1>Verifying…</h1>
    <p>Hang on while we confirm this.</p>
  </div>

  <script>
    const API_BASE = location.protocol + '//' + location.host + location.pathname.replace(/\/[^\/]*$/, '');
    const box = document.getElementById('box');
    const loginPage = '../extend.html'; // extend_gateway.php lives in backend/, extend.html is one level up at htdocs root

    function render(html) { box.innerHTML = html; }
    function renderMsg(title, body, ok) {
      render('<h1 class="' + (ok ? 'ok' : 'err') + '">' + title + '</h1><p>' + body + '</p>');
    }

    const extendToken = localStorage.getItem('appguard_extend_token');
    const signupSig = localStorage.getItem('appguard_signup_sig');

    if (extendToken) {
      fetch(API_BASE + '/extend_finalize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: extendToken })
      })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            localStorage.removeItem('appguard_extend_token');
            renderMsg('Extension applied ✓', 'Your access is now valid until <strong>' + data.nice_expiry + '</strong>. You can close this page.', true);
          } else {
            renderMsg('Couldn\'t extend', (data.error || 'Something went wrong.') + ' <a href="' + loginPage + '">Try again</a>.', false);
          }
        })
        .catch(() => renderMsg('Couldn\'t extend', 'Network error — please <a href="' + loginPage + '">try again</a>.', false));
    } else if (signupSig) {
      fetch(API_BASE + '/signup_finalize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sig: signupSig })
      })
        .then(r => r.json())
        .then(data => {
          localStorage.removeItem('appguard_signup_sig');
          if (data.ok) {
            render(
              '<h1 class="ok">Your token is ready ✓</h1>' +
              '<p>Save this now — it will not be shown again. Valid until <strong>' + data.nice_expiry + '</strong>.</p>' +
              '<div class="tokenBox" id="tokenText">' + data.token + '</div>' +
              '<button id="copyBtn">Copy token</button>'
            );
            document.getElementById('copyBtn').onclick = () => {
              navigator.clipboard.writeText(data.token).then(() => {
                document.getElementById('copyBtn').textContent = 'Copied ✓';
              });
            };
          } else {
            renderMsg('Couldn\'t generate token', (data.error || 'Something went wrong.') + ' <a href="' + loginPage + '">Try again</a>.', false);
          }
        })
        .catch(() => renderMsg('Couldn\'t generate token', 'Network error — please <a href="' + loginPage + '">try again</a>.', false));
    } else {
      renderMsg(
        'Can\'t find your session',
        'Please open this page in the same browser where you started, or <a href="' + loginPage + '">go back</a> to try again.',
        false
      );
    }
  </script>
</body>
</html>
