@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','IT Job Request — Target Date Approval')
@section('header-subtitle','STRIDE IT Services')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">The assigned MIS personnel proposed a target completion date for <strong>{{ $jobRequest->itjr_no }}</strong>. MIS cannot act on the request until you decide.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">ITJR No.</td><td class="val"><strong style="font-family:monospace;">{{ $jobRequest->itjr_no }}</strong></td></tr>
    <tr><td class="lbl">Title</td><td class="val">{{ $jobRequest->title }}</td></tr>
    <tr><td class="lbl">Submitted By</td><td class="val">{{ $jobRequest->user?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Assigned To</td><td class="val">{{ $jobRequest->assignedTo?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Proposed Date</td><td class="val"><strong>{{ $jobRequest->expected_completion_date ? \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') : '—' }}</strong></td></tr>
</table>

@if($jobRequest->mis_assessment)
<div class="callout callout-blue" style="margin-top:12px;">
    <div class="callout-title">MIS Assessment</div>
    {{ $jobRequest->mis_assessment }}
</div>
@endif

<div class="callout callout-amber">
    <div class="callout-title">Action Required</div>
    <strong>Approve</strong> asks for your signature PIN. <strong>Reject</strong> asks for a reason. Links expire in 7 days.
</div>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>
<a class="btn btn-red" href="{{ $rejectUrl }}">Reject</a>
@endsection

@section('fallback-links')
<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
<p>Reject: <a href="{{ $rejectUrl }}">{{ $rejectUrl }}</a></p>
@endsection

@section('footer-note')
If you are not the KID Chief/OCD, ignore this email. Do not forward it: anyone with the link can open the confirmation page.
@endsection
