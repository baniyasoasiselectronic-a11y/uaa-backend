<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Move In / Out — United Arab Agencies</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0D0D0D;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#fff;padding:20px}
.card{width:100%;max-width:380px;background:#171717;border:1px solid rgba(201,169,110,.35);border-radius:16px;padding:32px 28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.6)}
.card img{height:64px;margin-bottom:14px}
h1{font-size:16px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 6px}
p{font-size:13px;color:#a8a29a;margin:0 0 22px;line-height:1.55}
input{width:100%;padding:15px 16px;border-radius:10px;border:1px solid #3a3326;background:#0D0D0D;color:#fff;font-size:18px;text-align:center;letter-spacing:.3em;outline:none}
input:focus{border-color:#C9A96E}
button{width:100%;margin-top:14px;padding:15px;border:0;border-radius:10px;background:linear-gradient(135deg,#E8D4A8,#C9A96E);color:#111;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:13px;cursor:pointer}
.err{margin-top:14px;color:#f87171;font-size:13px}
a{color:#C9A96E}
</style>
</head>
<body>
<div class="card">
  <img src="{{ asset('images/uaa-logo.png') }}" alt="United Arab Agencies">
  <h1>Move In / Out</h1>
  @if($hasPin)
    <p>Enter the inspection PIN to continue.</p>
    <form method="post" action="{{ url('/inspection/pin') }}">
      @csrf
      <input type="password" name="pin" inputmode="numeric" autocomplete="current-password" autofocus required aria-label="PIN">
      <button type="submit">Open</button>
    </form>
    @if($error)<div class="err">{{ $error }}</div>@endif
  @else
    <p>No inspection PIN has been set yet. Ask an administrator to set it in <b>Move In/Out Settings</b>, or <a href="{{ url('/admin/login') }}">sign in to the admin</a>.</p>
  @endif
</div>
</body>
</html>
