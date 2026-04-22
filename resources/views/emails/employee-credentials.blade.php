<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Account Credentials</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f6f8;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #1a2b3c;
    }
    .wrapper {
      max-width: 560px;
      margin: 40px auto;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .header {
      background: #102746;
      padding: 32px 40px;
      text-align: center;
    }
    .header h1 {
      margin: 0 0 4px;
      color: #ffffff;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }
    .header p {
      margin: 0;
      color: #e57c2a;
      font-size: 13px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .body {
      padding: 36px 40px;
    }
    .greeting {
      font-size: 16px;
      margin: 0 0 20px;
      color: #1a2b3c;
    }
    .intro {
      font-size: 14px;
      line-height: 1.6;
      color: #4a5568;
      margin: 0 0 28px;
    }
    .credentials-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-left: 4px solid #e57c2a;
      border-radius: 8px;
      padding: 20px 24px;
      margin-bottom: 28px;
    }
    .credentials-box h2 {
      margin: 0 0 16px;
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #64748b;
    }
    .credential-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #e2e8f0;
    }
    .credential-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .credential-label {
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
      min-width: 120px;
    }
    .credential-value {
      font-size: 14px;
      font-weight: 700;
      color: #102746;
      font-family: 'Courier New', monospace;
      background: #edf2f7;
      padding: 4px 10px;
      border-radius: 4px;
      letter-spacing: 0.5px;
    }
    .notice {
      background: #fff7ed;
      border: 1px solid #fed7aa;
      border-radius: 8px;
      padding: 16px 20px;
      margin-bottom: 28px;
    }
    .notice p {
      margin: 0;
      font-size: 13px;
      line-height: 1.6;
      color: #92400e;
    }
    .notice strong {
      color: #78350f;
    }
    .cta {
      text-align: center;
      margin-bottom: 28px;
    }
    .cta a {
      display: inline-block;
      background: #e57c2a;
      color: #ffffff;
      text-decoration: none;
      padding: 13px 32px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .footer-note {
      font-size: 13px;
      line-height: 1.6;
      color: #64748b;
      margin: 0 0 8px;
    }
    .footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 20px 40px;
      text-align: center;
    }
    .footer p {
      margin: 0;
      font-size: 12px;
      color: #94a3b8;
      line-height: 1.6;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>LiamKai Fish Trading</h1>
      <p>System Account Notification</p>
    </div>

    <div class="body">
      <p class="greeting">Hello, <strong>{{ $employeeName }}</strong></p>

      <p class="intro">
        An administrator has created a system account for you on the
        <strong>LiamKai Fish Trading System</strong>. You can use the credentials
        below to log in. Please change your password immediately after your first login.
      </p>

      <div class="credentials-box">
        <h2>Your Login Credentials</h2>
        <div class="credential-row">
          <span class="credential-label">Username</span>
          <span class="credential-value">{{ $username }}</span>
        </div>
        <div class="credential-row">
          <span class="credential-label">Temporary Password</span>
          <span class="credential-value">{{ $temporaryPassword }}</span>
        </div>
      </div>

      <div class="notice">
        <p>
          <strong>⚠ Important:</strong> This is a temporary password. You will be required to
          set a new password the first time you log in. Do not share these credentials with anyone.
        </p>
      </div>

      <div class="cta">
        <a href="{{ $loginUrl }}">Log In to Your Account</a>
      </div>

      <p class="footer-note">
        If the button above doesn't work, copy and paste this link into your browser:<br />
        <a href="{{ $loginUrl }}" style="color:#e57c2a;word-break:break-all;">{{ $loginUrl }}</a>
      </p>

      <p class="footer-note">
        If you did not expect this email or believe it was sent in error, please contact your administrator immediately.
      </p>
    </div>

    <div class="footer">
      <p>
        &copy; {{ date('Y') }} LiamKai Fish Trading System &bull;
        This is an automated message, please do not reply.
      </p>
    </div>
  </div>
</body>
</html>
