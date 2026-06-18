@extends('emails.layouts.base')

@section('email-title', 'Reset Your Password — STRIDE')
@section('header-title', 'Password Reset Request')
@section('header-subtitle', 'PSHS Service Requests Management Information System')
@section('header-gradient', 'linear-gradient(90deg,#4f46e5,#6366f1)')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">
    You are receiving this email because we received a password reset request for your account.
    Click the button below to choose a new password. This link will expire in
    <strong>{{ $expiry }} minutes</strong>.
</p>
@endsection

@section('actions')
<a href="{{ $resetUrl }}" class="btn btn-primary">Reset Password</a>
@endsection

@section('fallback-links')
<p>{{ $resetUrl }}</p>
@endsection

@section('footer-note')
If you did not request a password reset, no action is required — your password will remain unchanged.
This link expires in {{ $expiry }} minutes.
@endsection
