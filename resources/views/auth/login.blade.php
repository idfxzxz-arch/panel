@extends('layouts.app', ['title' => 'Login'])

@section('content')
<style>
    body {
        overflow: hidden; /* Hide scrollbars for login page */
    }
    .login-container {
        width: 100%;
        max-width: 440px;
        position: relative;
        z-index: 10;
        /* Container entrance animation */
        animation: form-enter 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    @keyframes form-enter {
        0% { opacity: 0; transform: translateY(40px) scale(0.95); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    
    /* Staggered entrance for form elements */
    .stagger-item {
        opacity: 0;
        transform: translateY(20px);
        animation: stagger-enter 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    }
    .stagger-item:nth-child(1) { animation-delay: 0.1s; }
    .stagger-item:nth-child(2) { animation-delay: 0.2s; }
    .stagger-item:nth-child(3) { animation-delay: 0.3s; }
    .stagger-item:nth-child(4) { animation-delay: 0.4s; }
    .stagger-item:nth-child(5) { animation-delay: 0.5s; }
    .stagger-item:nth-child(6) { animation-delay: 0.6s; }

    @keyframes stagger-enter {
        to { opacity: 1; transform: translateY(0); }
    }
    
    .card-glass-login {
        background: rgba(16, 22, 32, 0.65);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.03) inset;
        border-radius: 16px;
        padding: 40px;
    }
    
    .logo-container {
        text-align: center;
        margin-bottom: 36px;
    }
    
    .logo-mark-login {
        display: inline-grid;
        place-items: center;
        width: 56px;
        height: 56px;
        border: 2px solid var(--red);
        background: linear-gradient(145deg, #57131d, #160a0e);
        transform: rotate(45deg);
        border-radius: 6px;
        margin-bottom: 24px;
        box-shadow: 0 0 24px rgba(240, 56, 71, 0.3);
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        animation: logo-float 6s ease-in-out infinite;
    }
    .logo-mark-login:hover {
        transform: rotate(135deg) scale(1.1);
        box-shadow: 0 0 32px rgba(240, 56, 71, 0.5);
    }
    
    @keyframes logo-float {
        0%, 100% { transform: rotate(45deg) translateY(0); }
        50% { transform: rotate(45deg) translateY(-8px); }
    }
    
    .logo-mark-login span {
        transform: rotate(-45deg);
        color: #ff5261;
        font-size: 24px;
        font-weight: 900;
        transition: transform 0.5s;
    }
    .logo-mark-login:hover span {
        transform: rotate(-135deg);
    }
    
    .login-title {
        font-size: 24px;
        font-weight: 800;
        margin: 0 0 8px;
        background: linear-gradient(to right, #fff, #a8b3c1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.5px;
    }
    
    /* Modern Floating Labels */
    .input-group {
        margin-bottom: 24px;
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        transition: color var(--transition);
        z-index: 2;
    }
    
    .input-with-icon {
        padding: 24px 16px 8px 46px !important;
        background: rgba(9, 12, 18, 0.5) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        height: 56px;
        border-radius: 8px !important;
        color: #fff !important;
        width: 100%;
        transition: all 0.2s ease;
    }
    
    .input-with-icon:focus {
        background: rgba(12, 16, 24, 0.8) !important;
        border-color: var(--red) !important;
        box-shadow: 0 0 0 4px rgba(240, 56, 71, 0.15) !important;
    }
    
    .floating-label {
        position: absolute;
        left: 46px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: 14px;
        pointer-events: none;
        transition: 0.2s ease all;
    }
    
    .input-with-icon:focus ~ .floating-label,
    .input-with-icon:not(:placeholder-shown) ~ .floating-label {
        top: 14px;
        font-size: 10px;
        color: var(--red);
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .input-with-icon:focus ~ .input-icon,
    .input-with-icon:not(:placeholder-shown) ~ .input-icon {
        color: var(--red);
    }
    
    .btn-login {
        width: 100%;
        height: 48px;
        border-radius: 8px;
        font-size: 14px;
        letter-spacing: 0.5px;
        margin-top: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .btn-login::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transform: translateX(-100%);
        transition: transform 0.6s ease;
    }
    .btn-login:hover::after {
        transform: translateX(100%);
    }
    
    .login-footer {
        text-align: center;
        margin-top: 30px;
        padding-top: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    /* Interactive Background Particles (CSS only) */
    .bg-elements {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }
    
    .bg-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.15;
        animation: blob-float 20s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
    }
    .bg-blob-1 {
        top: -10%; left: -10%;
        width: 600px; height: 600px;
        background: var(--red);
        animation-delay: 0s;
    }
    .bg-blob-2 {
        bottom: -20%; right: -10%;
        width: 800px; height: 800px;
        background: var(--blue);
        animation-delay: -5s;
    }
    .bg-blob-3 {
        top: 30%; left: 60%;
        width: 400px; height: 400px;
        background: var(--purple);
        animation-delay: -10s;
    }
    
    @keyframes blob-float {
        0% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(50px, -50px) scale(1.1); }
        66% { transform: translate(-30px, 40px) scale(0.9); }
        100% { transform: translate(0, 0) scale(1); }
    }
    
    .btn-loading .btn-text {
        opacity: 0;
    }
    .btn-loading::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        left: 50%;
        top: 50%;
        margin-left: -10px;
        margin-top: -10px;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Page Transition Overlay */
    .page-transition-overlay {
        position: fixed;
        inset: 0;
        background-color: var(--bg);
        z-index: 99999;
        transform: translateY(100%);
        transition: transform 0.6s cubic-bezier(0.7, 0, 0.3, 1);
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .page-transition-overlay.is-exiting {
        transform: translateY(0);
        pointer-events: all;
    }
    .transition-logo {
        width: 60px;
        height: 60px;
        opacity: 0;
        transform: scale(0.8);
        transition: all 0.4s ease 0.4s;
    }
    .page-transition-overlay.is-exiting .transition-logo {
        opacity: 1;
        transform: scale(1);
    }
</style>

<div class="bg-elements">
    <div class="bg-blob bg-blob-1"></div>
    <div class="bg-blob bg-blob-2"></div>
    <div class="bg-blob bg-blob-3"></div>
</div>

<!-- Full screen transition overlay -->
<div class="page-transition-overlay" id="transitionOverlay">
    <div class="logo-mark-login transition-logo">
        <span>H</span>
    </div>
</div>

<div class="login-container">
    <div class="card-glass-login">
        <div class="logo-container stagger-item">
            <span class="logo-mark-login">
                <span>H</span>
            </span>
            <h1 class="login-title">Harbor Control</h1>
            <p class="muted" style="font-size:13px">Secure infrastructure access</p>
        </div>

        <form method="post" action="{{ route('login.store') }}" id="loginForm">
            @csrf
            
            <div class="input-group stagger-item">
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       placeholder=" " autocomplete="email" class="input-with-icon" id="emailInput">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <label class="floating-label" for="emailInput">Email Address</label>
            </div>
            
            <div class="input-group stagger-item">
                <input type="password" name="password" required
                       placeholder=" " autocomplete="current-password" class="input-with-icon" id="passwordInput">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <label class="floating-label" for="passwordInput">Password</label>
            </div>
            
            <div class="form-group stagger-item" style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
                <input type="checkbox" name="remember" value="1" id="remember"
                       style="width:16px;height:16px;margin:0;accent-color:var(--red);cursor:pointer">
                <label for="remember" style="margin:0;font-weight:500;cursor:pointer;font-size:12px;color:var(--text2);user-select:none">Remember me for 30 days</label>
            </div>
            
            <button type="submit" id="loginBtn" class="btn btn-primary btn-login stagger-item">
                <span class="btn-text" style="display:flex;align-items:center;justify-content:center;gap:8px">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    AUTHORIZE ACCESS
                </span>
            </button>
        </form>

        <div class="login-footer stagger-item">
            <p style="font-size:11px;color:var(--muted2);display:flex;align-items:center;justify-content:center;gap:6px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                End-to-End Encrypted Session
            </p>
        </div>
    </div>
</div>

<script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        // Only intercept if the form is valid (required fields filled)
        if (this.checkValidity()) {
            e.preventDefault(); // Stop normal form submission
            
            // Add loading state to button
            document.getElementById('loginBtn').classList.add('btn-loading');
            
            // Trigger the full screen wipe animation
            const overlay = document.getElementById('transitionOverlay');
            overlay.classList.add('is-exiting');
            
            // Wait for the animation to cover the screen (600ms) before actually submitting
            setTimeout(() => {
                this.submit(); // Programmatically submit bypassing the listener
            }, 700);
        }
    });
</script>
@endsection