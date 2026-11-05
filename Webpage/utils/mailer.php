<?php
/**
 * iTech Seed Grand Challenge 2027
 * SMTP Socket Mailer & Email Dispatcher for Mentors
 * Supports direct Gmail TLS delivery and local logging fallback
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

class LaunchpadMailer
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
        $logDir = $this->config['log_path'];
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
    }

    /**
     * Dispatch single-use mentor approval invitation email.
     */
    public function sendMentorApprovalRequest(
        array $mentor,
        array $team,
        string $approvalToken,
        string $leadName,
        string $projectTitle
    ): array {
        $baseUrl = ($this->config['get_base_url'])();
        $approvalUrl = "{$baseUrl}/mentor_approval.php?token=" . urlencode($approvalToken);

        $mentorTypeLabel = match ($mentor['mentor_type'] ?? '') {
            'faculty'            => 'Faculty Mentor',
            'additional_faculty' => 'Additional Faculty Mentor',
            'industry'           => 'Industry / Expert Mentor',
            default              => 'Project Mentor'
        };

        $subject = "Action Required: Mentor Consent Request for Team \"{$team['team_name']}\" | iTech Seed Grand Challenge 2027";

        $htmlBody = $this->renderMentorEmailHtml(
            mentorName: $mentor['name'],
            mentorType: $mentorTypeLabel,
            teamName: $team['team_name'],
            appNo: $team['application_no'] ?? 'TBD',
            leadName: $leadName,
            projectTitle: $projectTitle,
            approvalUrl: $approvalUrl
        );

        $result = $this->dispatchEmail(
            toEmail: $mentor['email'],
            toName: $mentor['name'],
            subject: $subject,
            htmlBody: $htmlBody
        );

        $result['approval_url'] = $approvalUrl;
        $result['mentor_name']  = $mentor['name'];
        $result['mentor_type']  = $mentorTypeLabel;
        $result['to_email']     = $mentor['email'];

        return $result;
    }

    /**
     * Dispatch notification email to Team Lead when member & mentor accounts are created.
     */
    public function sendTeamAccountsCreatedNotification(
        string $leadEmail,
        string $leadName,
        string $teamName,
        string $appNo,
        array $members,
        array $mentors
    ): array {
        $baseUrl = ($this->config['get_base_url'])();
        $loginUrl = "{$baseUrl}/login.php";

        $subject = "Accounts Created for Team \"{$teamName}\" - Important Password Information | iTech Seed Grand Challenge 2027";

        $memberRowsHtml = '';
        foreach ($members as $m) {
            if (!empty($m['is_lead'])) continue; // exclude lead
            $mName = htmlspecialchars($m['full_name'] ?? '');
            $mEmail = htmlspecialchars($m['college_email'] ?? '');
            $mPhone = htmlspecialchars($m['phone_number'] ?? '');
            $mOrder = htmlspecialchars((string)($m['member_order'] ?? 'Member'));
            $memberRowsHtml .= "
                <tr>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9;'>Member {$mOrder}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9; font-weight:600;'>{$mName}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9;'>{$mEmail}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9; font-family:monospace; font-weight:700; color:#1b5e20;'>{$mPhone}</td>
                </tr>
            ";
        }

        $mentorRowsHtml = '';
        foreach ($mentors as $men) {
            $menName = htmlspecialchars($men['name'] ?? '');
            $menRole = htmlspecialchars(ucwords(str_replace('_', ' ', $men['mentor_type'] ?? 'Mentor')));
            $menEmail = htmlspecialchars($men['email'] ?? '');
            $menPhone = htmlspecialchars($men['phone'] ?? '');
            $mentorRowsHtml .= "
                <tr>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9;'>{$menRole}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9; font-weight:600;'>{$menName}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9;'>{$menEmail}</td>
                    <td style='padding:8px 12px; border:1px solid #d4e5d9; font-family:monospace; font-weight:700; color:#1b5e20;'>{$menPhone}</td>
                </tr>
            ";
        }

        $safeLead = htmlspecialchars($leadName);
        $safeTeam = htmlspecialchars($teamName);
        $safeApp  = htmlspecialchars($appNo);

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Accounts Created</title>
  <style>
    body { margin: 0; padding: 0; background-color: #f3f7f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e3a29; }
    .email-wrapper { max-width: 620px; margin: 24px auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(18, 53, 36, 0.08); border: 1px solid #d4e5d9; }
    .email-header { background: linear-gradient(135deg, #0e3824 0%, #175436 100%); padding: 30px; text-align: left; color: #ffffff; }
    .header-badge { display: inline-block; background: rgba(86, 219, 142, 0.2); border: 1px solid #56db8e; color: #a4f2c5; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; margin-bottom: 12px; }
    .header-title { font-size: 20px; font-weight: 800; margin: 0 0 6px 0; color: #ffffff; }
    .header-sub { font-size: 13px; color: #c4e4d0; margin: 0; }
    .email-body { padding: 30px; }
    .alert-banner { background: #eef8f1; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 8px; margin-bottom: 22px; font-size: 13.5px; color: #14532d; line-height: 1.5; font-weight: 500; }
    .table-title { font-size: 14px; font-weight: 700; color: #0e3824; margin: 18px 0 8px 0; }
    .cred-table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 18px; }
    .cred-table th { background: #f0f7f2; color: #1b4d2e; padding: 8px 12px; text-align: left; border: 1px solid #d4e5d9; font-weight: 700; }
    .btn-container { text-align: center; margin: 26px 0; }
    .btn-login { display: inline-block; background: #1b5e20; color: #ffffff !important; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 30px; border-radius: 30px; }
    .security-notice { background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 14px 16px; font-size: 12px; color: #713f12; line-height: 1.5; }
    .email-footer { background: #edf4ef; padding: 18px 30px; text-align: center; font-size: 11.5px; color: #587963; border-top: 1px solid #dbe8df; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-header">
      <div class="header-badge">PSG iTech Launchpad</div>
      <h1 class="header-title">Accounts Provisioned for Team "{$safeTeam}"</h1>
      <p class="header-sub">Application Ref: {$safeApp} · iTech Seed Grand Challenge 2027</p>
    </div>

    <div class="email-body">
      <p style="font-size:15px; font-weight:700; color:#0e3824; margin-top:0;">Dear {$safeLead},</p>
      
      <div class="alert-banner">
        <strong>Important Account Notification:</strong><br>
        Accounts have been created for other members and mentors with their email and their phone number being the password. Ask them to change the password.
      </div>

      <p style="font-size:13px; color:#2e4d3a; line-height:1.5;">
        You have successfully saved <strong>Step 1 (Team &amp; Account Details)</strong> for <strong>{$safeTeam}</strong>. Below is the list of login accounts provisioned for your team members and mentors:
      </p>

      <div class="table-title">Student Team Members:</div>
      <table class="cred-table">
        <thead>
          <tr>
            <th>Role</th>
            <th>Name</th>
            <th>Login Email</th>
            <th>Initial Password</th>
          </tr>
        </thead>
        <tbody>
          {$memberRowsHtml}
        </tbody>
      </table>

      <div class="table-title">Project Mentors:</div>
      <table class="cred-table">
        <thead>
          <tr>
            <th>Role</th>
            <th>Name</th>
            <th>Login Email</th>
            <th>Initial Password</th>
          </tr>
        </thead>
        <tbody>
          {$mentorRowsHtml}
        </tbody>
      </table>

      <div class="security-notice">
        <strong>Mandatory First-Time Password Change:</strong><br>
        When any member or mentor logs in for the first time using their temporary password (their phone number), the system will require them to set their own secure password before proceeding. Note that for security reasons, the portal will reject any attempt to reuse their mobile number as the new password.
      </div>

      <div class="btn-container">
        <a href="{$loginUrl}" target="_blank" class="btn-login">Go to Launchpad Portal Login &rarr;</a>
      </div>
    </div>

    <div class="email-footer">
      <p style="margin:0 0 4px 0;">PSG Institute of Technology and Applied Research · Innozone &amp; Startupzone</p>
      <p style="margin:0;">Need assistance? Contact <a href="mailto:innozone@psgitech.ac.in" style="color:#1b5e20;">innozone@psgitech.ac.in</a>.</p>
    </div>
  </div>
</body>
</html>
HTML;

        return $this->dispatchEmail(
            toEmail: $leadEmail,
            toName: $leadName,
            subject: $subject,
            htmlBody: $htmlBody
        );
    }

    /**
     * Dispatches email via Gmail SMTP or fallback logger.
     */
    public function dispatchEmail(string $toEmail, string $toName, string $subject, string $htmlBody): array
    {
        $smtpUser = trim((string)($this->config['smtp_username'] ?? ''));
        $smtpPass = str_replace(' ', '', trim((string)($this->config['smtp_password'] ?? '')));
        $smtpHost = $this->config['smtp_host'] ?? 'smtp.gmail.com';
        $smtpPort = (int)($this->config['smtp_port'] ?? 587);

        // If credentials are configured, attempt real Gmail SMTP socket transfer
        if (!empty($smtpUser) && !empty($smtpPass) && $this->config['smtp_enabled']) {
            $smtpResult = $this->sendViaSocketSmtp(
                host: $smtpHost,
                port: $smtpPort,
                user: $smtpUser,
                pass: $smtpPass,
                toEmail: $toEmail,
                toName: $toName,
                subject: $subject,
                htmlBody: $htmlBody
            );

            if ($smtpResult['success']) {
                $this->logEmail(toEmail: $toEmail, subject: $subject, htmlBody: $htmlBody, status: 'SENT_VIA_GMAIL_SMTP');
                return [
                    'success' => true,
                    'mode'    => 'smtp_gmail',
                    'message' => "Approval email successfully sent to {$toEmail} from {$smtpUser} via Gmail SMTP."
                ];
            } else {
                error_log("LaunchpadMailer SMTP error: " . ($smtpResult['error'] ?? 'Unknown error'));
                $this->logEmail(toEmail: $toEmail, subject: $subject, htmlBody: $htmlBody, status: 'SMTP_FAILED: ' . ($smtpResult['error'] ?? ''));
                return [
                    'success' => false,
                    'mode'    => 'smtp_failed',
                    'error'   => "Failed to deliver email to {$toEmail} via Gmail SMTP: " . ($smtpResult['error'] ?? 'Unknown authentication or delivery error')
                ];
            }
        }

        // Fallback: Try PHP native mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->config['from_name']} <{$this->config['from_email']}>\r\n";
        $headers .= "Reply-To: {$this->config['reply_to']}\r\n";

        $mailSent = @mail($toEmail, $subject, $htmlBody, $headers);

        if ($mailSent) {
            $this->logEmail(toEmail: $toEmail, subject: $subject, htmlBody: $htmlBody, status: 'SENT_VIA_PHP_MAIL');
            return [
                'success' => true,
                'mode'    => 'php_mail',
                'message' => "Approval link dispatched to {$toEmail} via mail system."
            ];
        }

        // Neither SMTP credentials nor PHP mail() succeeded
        $this->logEmail(toEmail: $toEmail, subject: $subject, htmlBody: $htmlBody, status: 'LOGGED_OFFLINE_FOR_DEV');
        return [
            'success' => false,
            'mode'    => 'offline_logged',
            'error'   => "Email could not be delivered to {$toEmail}. Please configure the 16-character Google App Password in Webpage/config/mail.php for {$this->config['from_email']}."
        ];
    }

    /**
     * Pure PHP socket SMTP client with STARTTLS support.
     */
    private function sendViaSocketSmtp(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): array {
        $timeout = 10;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['success' => false, 'error' => "Cannot connect to {$host}:{$port} ({$errstr})"];
        }

        stream_set_timeout($socket, $timeout);

        $read = function () use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $response;
        };

        $write = function (string $cmd) use ($socket, $read): string {
            fputs($socket, $cmd . "\r\n");
            return $read();
        };

        $init = $read();
        if (substr($init, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP greeting failed: {$init}"];
        }

        $write("EHLO " . gethostname());
        $starttls = $write("STARTTLS");
        if (substr($starttls, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => "STARTTLS failed: {$starttls}"];
        }

        // Enable cryptographic TLS stream
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => "Crypto negotiation failed."];
        }

        $write("EHLO " . gethostname());
        $auth = $write("AUTH LOGIN");
        if (substr($auth, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'error' => "AUTH LOGIN not accepted: {$auth}"];
        }

        $write(base64_encode($user));
        $passResp = $write(base64_encode($pass));
        if (substr($passResp, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP Authentication failed: {$passResp}"];
        }

        $mailFromResp = $write("MAIL FROM:<{$this->config['from_email']}>");
        if (substr($mailFromResp, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM rejected: {$mailFromResp}"];
        }

        $rcptResp = $write("RCPT TO:<{$toEmail}>");
        if (substr($rcptResp, 0, 3) !== '250' && substr($rcptResp, 0, 3) !== '251') {
            fclose($socket);
            return ['success' => false, 'error' => "Recipient rejected: {$rcptResp}"];
        }

        $dataStart = $write("DATA");
        if (substr($dataStart, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'error' => "DATA not accepted: {$dataStart}"];
        }

        $mime  = "From: =?UTF-8?B?" . base64_encode($this->config['from_name']) . "?= <{$this->config['from_email']}>\r\n";
        $mime .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
        $mime .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $mime .= "MIME-Version: 1.0\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: 8bit\r\n";
        $mime .= "Date: " . date('r') . "\r\n";
        $mime .= "\r\n";
        $mime .= $htmlBody;
        $mime .= "\r\n.\r\n";

        fputs($socket, $mime);
        $dataResp = $read();

        $write("QUIT");
        fclose($socket);

        if (substr($dataResp, 0, 3) === '250') {
            return ['success' => true];
        }
        return ['success' => false, 'error' => "Data transfer failed: {$dataResp}"];
    }

    /**
     * Audit log recording to local disk.
     */
    private function logEmail(string $toEmail, string $subject, string $htmlBody, string $status): void
    {
        $logFile = $this->config['log_path'] . '/mentor_approvals.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "======================================================================\n"
                  . "TIMESTAMP: {$timestamp} | STATUS: {$status}\n"
                  . "RECIPIENT: {$toEmail}\n"
                  . "SUBJECT:   {$subject}\n"
                  . "----------------------------------------------------------------------\n"
                  . strip_tags($htmlBody) . "\n"
                  . "======================================================================\n\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Renders responsive HTML email body template for mentors.
     */
    private function renderMentorEmailHtml(
        string $mentorName,
        string $mentorType,
        string $teamName,
        string $appNo,
        string $leadName,
        string $projectTitle,
        string $approvalUrl
    ): string {
        $year = date('Y');
        $safeTitle = htmlspecialchars($projectTitle ?: 'Proof-of-Concept Proposal');
        $safeLead  = htmlspecialchars($leadName);
        $safeTeam  = htmlspecialchars($teamName);
        $safeApp   = htmlspecialchars($appNo);
        $safeName  = htmlspecialchars($mentorName);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mentor Consent Request</title>
  <style>
    body { margin: 0; padding: 0; background-color: #f3f7f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e3a29; }
    .email-wrapper { max-width: 620px; margin: 24px auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(18, 53, 36, 0.08); border: 1px solid #d4e5d9; }
    .email-header { background: linear-gradient(135deg, #0e3824 0%, #175436 100%); padding: 32px 30px; text-align: left; color: #ffffff; }
    .header-badge { display: inline-block; background: rgba(86, 219, 142, 0.2); border: 1px solid #56db8e; color: #a4f2c5; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; margin-bottom: 12px; }
    .header-title { font-size: 22px; font-weight: 800; margin: 0 0 6px 0; color: #ffffff; letter-spacing: -0.02em; }
    .header-sub { font-size: 13px; color: #c4e4d0; margin: 0; }
    .email-body { padding: 32px 30px; }
    .salutation { font-size: 16px; font-weight: 700; color: #0e3824; margin-bottom: 14px; }
    .lead-text { font-size: 14px; line-height: 1.6; color: #2e4d3a; margin-bottom: 22px; }
    .card-proposal { background: #f8faf8; border: 1px solid #dbe8df; border-left: 4px solid #2e7d32; border-radius: 8px; padding: 18px 20px; margin-bottom: 24px; }
    .card-row { display: flex; margin-bottom: 8px; font-size: 13px; }
    .card-row:last-child { margin-bottom: 0; }
    .card-label { font-weight: 700; color: #4b6b55; width: 130px; flex-shrink: 0; }
    .card-value { color: #11281b; font-weight: 600; }
    .btn-container { text-align: center; margin: 28px 0; }
    .btn-approve { display: inline-block; background: #1b5e20; color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 34px; border-radius: 30px; box-shadow: 0 4px 14px rgba(27, 94, 32, 0.3); }
    .instructions-box { background: #eef6f0; border-radius: 8px; padding: 16px 20px; font-size: 12.5px; line-height: 1.55; color: #224c30; margin-bottom: 24px; }
    .instructions-box strong { color: #0d381e; }
    .fallback-url { font-size: 11px; color: #688572; word-break: break-all; margin-top: 20px; padding-top: 16px; border-top: 1px dashed #d1e2d7; }
    .email-footer { background: #edf4ef; padding: 20px 30px; text-align: center; font-size: 11.5px; color: #587963; border-top: 1px solid #dbe8df; }
    .email-footer a { color: #1b5e20; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-header">
      <div class="header-badge">PSG iTech Launchpad</div>
      <h1 class="header-title">iTech Seed Grand Challenge 2027</h1>
      <p class="header-sub">Innozone &amp; Startupzone · Pre-Incubation &amp; PoC Grant Initiative</p>
    </div>

    <div class="email-body">
      <div class="salutation">Dear {$safeName},</div>
      
      <p class="lead-text">
        You have been nominated as the <strong>{$mentorType}</strong> by student team <strong>{$safeTeam}</strong> for their grant proposal under the <em>iTech Seed Grand Challenge 2027</em>.
      </p>

      <div class="card-proposal">
        <div class="card-row">
          <div class="card-label">Application Ref:</div>
          <div class="card-value">{$safeApp}</div>
        </div>
        <div class="card-row">
          <div class="card-label">Team Name:</div>
          <div class="card-value">{$safeTeam}</div>
        </div>
        <div class="card-row">
          <div class="card-label">Team Lead:</div>
          <div class="card-value">{$safeLead}</div>
        </div>
        <div class="card-row">
          <div class="card-label">Project Title:</div>
          <div class="card-value">{$safeTitle}</div>
        </div>
        <div class="card-row">
          <div class="card-label">Your Role:</div>
          <div class="card-value" style="color: #1b5e20;">{$mentorType}</div>
        </div>
      </div>

      <div class="instructions-box">
        <strong>Review &amp; Online Endorsement:</strong><br>
        In accordance with challenge regulations, every proposal requires official endorsement by the designated Faculty Mentor and Industry Mentor. Please click the button below to review their full technical proposal, itemized budget, deliverables, and record your digital consent.
      </div>

      <div class="btn-container">
        <a href="{$approvalUrl}" target="_blank" class="btn-approve">
          Review Proposal &amp; Provide Mentor Consent &rarr;
        </a>
      </div>

      <p style="font-size: 13px; color: #3b5b47; line-height: 1.5;">
        You will be presented with a full read-only dossier of the team's problem statement, proposed technology, milestone roadmap, and budget. You can either <strong>Approve</strong> with your e-signature or <strong>Decline</strong> with suggestions for revision.
      </p>

      <div class="fallback-url">
        If the button above does not work, copy and paste this secure link directly into your browser:<br>
        <a href="{$approvalUrl}" style="color: #1b5e20;">{$approvalUrl}</a>
      </div>
    </div>

    <div class="email-footer">
      <p style="margin: 0 0 6px 0;">PSG Institute of Technology and Applied Research · Neelambur, Coimbatore - 641062</p>
      <p style="margin: 0;">Innozone &amp; Startupzone Launchpad · This is an automated notification. For inquiries, email <a href="mailto:innozone@psgitech.ac.in">innozone@psgitech.ac.in</a>.</p>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
