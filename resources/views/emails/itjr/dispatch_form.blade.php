<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch — IT Job Request</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.1);max-width:520px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#1447c0,#3b82f6);padding:24px;color:#fff}
        .card-header h1{font-size:18px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px;margin-bottom:4px}
        .detail .lbl{color:#64748b;font-weight:600}
        .detail .val{color:#0f172a;text-align:right}
        label{display:block;font-size:13px;font-weight:600;color:#374151;margin:16px 0 6px}
        select,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit;outline:none;background:#fff}
        textarea{resize:vertical;min-height:100px}
        select:focus,textarea:focus{border-color:#1447c0;box-shadow:0 0 0 3px rgba(20,71,192,.15)}
        .err{color:#dc2626;font-size:13px;margin-top:4px}
        button{width:100%;margin-top:16px;background:#1447c0;color:#fff;border:none;padding:12px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
        button:hover{background:#0f3a9e}
        .footer{padding:14px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Dispatch IT Job Request</h1>
        <p>PSHSS Helpdesk — ITJR Dispatcher</p>
    </div>
    <div class="card-body">
        <p style="color:#475569;font-size:14px;margin:0 0 16px;">Choose the MIS personnel who will handle this request and add a short triage note.</p>
        <div class="detail"><span class="lbl">ITJR No.</span><span class="val" style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Title</span><span class="val">{{ $jobRequest->title }}</span></div>
        <div class="detail"><span class="lbl">Category</span><span class="val">{{ $jobRequest->category }}</span></div>
        <div class="detail"><span class="lbl">Priority</span><span class="val">{{ ucfirst($jobRequest->priority ?? 'Normal') }}</span></div>
        <div class="detail"><span class="lbl">Submitted By</span><span class="val">{{ $jobRequest->user?->name ?? '—' }}</span></div>
        <form method="POST" action="{{ $postAction }}">
            @csrf
            <label for="assignedto">Assign to MIS personnel <span style="color:#ef4444">*</span></label>
            <select name="assignedto" id="assignedto" required>
                <option value="" disabled @selected(! old('assignedto', $suggestedId))>Select…</option>
                @foreach($misPersonnel as $mis)
                    <option value="{{ $mis->id }}" @selected((int) old('assignedto', $suggestedId) === $mis->id)>{{ $mis->name }}{{ $mis->id === $suggestedId ? ' (suggested)' : '' }}</option>
                @endforeach
            </select>
            @error('assignedto')<div class="err">{{ $message }}</div>@enderror

            <label for="notes">Triage note <span style="color:#ef4444">*</span></label>
            <textarea name="notes" id="notes" rows="4" required placeholder="e.g. Validated with the requester — routine hardware issue.">{{ old('notes') }}</textarea>
            @error('notes')<div class="err">{{ $message }}</div>@enderror

            <button type="submit">Dispatch to MIS</button>
        </form>
    </div>
    <div class="footer">PSHSS Helpdesk</div>
</div>
</body>
</html>
