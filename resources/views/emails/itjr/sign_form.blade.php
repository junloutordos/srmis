<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.1);max-width:520px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#1447C0,#2563eb);padding:24px;color:#fff}
        .card-header h1{font-size:18px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}
        .detail .lbl{color:#64748b;font-weight:600}
        label{display:block;font-size:13px;font-weight:600;color:#374151;margin:16px 0 6px}
        input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:16px;letter-spacing:.2em;outline:none}
        input:focus{border-color:#1447C0;box-shadow:0 0 0 3px rgba(20,71,192,.15)}
        button{width:100%;margin-top:14px;background:#1447C0;color:#fff;border:none;padding:12px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
        .err{color:#dc2626;font-size:13px;margin-top:6px}
        .warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:12px;border-radius:8px;font-size:14px}
        .footer{padding:14px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header"><h1>{{ $heading }}</h1><p>PSHSS Helpdesk — digital signature required</p></div>
    <div class="card-body">
        <div class="detail"><span class="lbl">ITJR No.</span><span style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Title</span><span>{{ $jobRequest->title }}</span></div>
        <div class="detail"><span class="lbl">Submitted By</span><span>{{ $jobRequest->user?->name ?? '—' }}</span></div>
        @if($jobRequest->expected_completion_date)
        <div class="detail"><span class="lbl">Target Date</span><span>{{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}</span></div>
        @endif
        @if($canSign)
        <form method="POST" action="{{ $postAction }}">
            @csrf
            <label for="pin">Enter your signature PIN to approve and sign</label>
            <input type="password" name="pin" id="pin" inputmode="numeric" autocomplete="off" required autofocus>
            @error('pin')<div class="err">{{ $message }}</div>@enderror
            <button type="submit">{{ $button }}</button>
        </form>
        @else
        <div class="warn" style="margin-top:16px;">You haven't set up your digital signature and PIN yet. Sign in to STRIDE, set them under Profile → Signature, then use this link again.</div>
        @endif
    </div>
    <div class="footer">PSHSS Helpdesk</div>
</div>
</body>
</html>
