@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','IT Job Request — Awaiting Dispatch')
@section('header-subtitle','STRIDE IT Services')

@section('content')
@php
    $priorityClass = match(strtolower($jobRequest->priority ?? 'normal')) {
        'urgent' => 'priority-urgent',
        'high'   => 'priority-high',
        'low'    => 'priority-low',
        default  => 'priority-normal',
    };
@endphp

<p class="greeting">Hello <strong>{{ $dispatcher->name }}</strong>,</p>
<p class="lead">IT Job Request <strong>{{ $jobRequest->itjr_no }}</strong> is ready to be dispatched to an MIS personnel.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">ITJR No.</td><td class="val"><strong style="font-family:monospace;">{{ $jobRequest->itjr_no }}</strong></td></tr>
    <tr><td class="lbl">Title</td><td class="val">{{ $jobRequest->title }}</td></tr>
    <tr><td class="lbl">Submitted By</td><td class="val">{{ $jobRequest->user?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Category</td><td class="val">{{ $jobRequest->category }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge {{ $priorityClass }}">{{ ucfirst($jobRequest->priority ?? 'Normal') }}</span></td></tr>
    @if(!empty($approverRole))
    <tr><td class="lbl">Approved By</td><td class="val">{{ \App\Services\RoleLabelService::apply($approverRole) }}</td></tr>
    @endif
</table>

@if($jobRequest->description)
<div class="callout callout-blue" style="margin-top:12px;">
    <div class="callout-title">Issue Description</div>
    {{ $jobRequest->description }}
</div>
@endif

<div class="callout callout-amber">
    <div class="callout-title">Action Required</div>
    Click <strong>Dispatch</strong> to choose the MIS personnel and add your triage note. This link expires in 3 weekdays; after that, use the Dispatch Queue in STRIDE.
</div>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ $dispatchUrl }}">Dispatch</a>
<a class="btn" href="{{ route('jobrequests.dispatch') }}">Open Dispatch Queue</a>
@endsection

@section('fallback-links')
<p>Dispatch: <a href="{{ $dispatchUrl }}">{{ $dispatchUrl }}</a></p>
@endsection

@section('footer-note')
If you are not an ITJR Dispatcher, you may ignore this email. Please do not forward it: anyone with this link can dispatch the request.
@endsection
