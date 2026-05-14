<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session completely
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Logged Out — UC CCS SIT Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .popup-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .popup-box {
      background: #fff;
      border-radius: 24px;
      padding: 48px 40px 40px;
      text-align: center;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 32px 80px rgba(0,0,0,0.2);
      animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes popIn {
      from { transform: scale(0.75); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }
    .popup-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #f97316, #ea580c);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 22px;
      box-shadow: 0 8px 24px rgba(249,115,22,0.35);
    }
    .popup-icon svg {
      width: 40px;
      height: 40px;
      stroke: #fff;
      stroke-width: 2.5;
      fill: none;
    }
    .popup-box h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
      color: #1e293b;
      margin-bottom: 8px;
    }
    .popup-box .popup-sub {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 28px;
    }
    .popup-progress {
      height: 5px;
      background: #e2e8f0;
      border-radius: 99px;
      overflow: hidden;
      margin-bottom: 14px;
    }
    .popup-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #f97316, #ea580c);
      border-radius: 99px;
      animation: drain 2.6s linear forwards;
      transform-origin: left;
    }
    @keyframes drain {
      from { transform: scaleX(1); }
      to   { transform: scaleX(0); }
    }
    .popup-redirect-note {
      font-size: 0.78rem;
      color: #94a3b8;
    }
  </style>
</head>
<body>

<div class="popup-overlay">
  <div class="popup-box">
    <div class="popup-icon">
      <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
    </div>
    <h2>Goodbye, Admin!</h2>
    <p class="popup-sub">You have been successfully logged out</p>
    <div class="popup-progress">
      <div class="popup-progress-bar"></div>
    </div>
    <p class="popup-redirect-note">Redirecting to login page...</p>
  </div>
</div>

<script>
  setTimeout(function() {
    window.location.href = 'login.php';
  }, 2600);
</script>

</body>
</html>