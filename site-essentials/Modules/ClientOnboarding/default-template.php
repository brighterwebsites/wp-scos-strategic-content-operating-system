<?php
/**
 * Default HTML email template for client onboarding.
 *
 * Edit this file to change the default for ALL sites. Per-site customisation
 * is via the textarea on Agency → Onboarding (option: se_onboarding_html_template).
 *
 * Tokens available:
 *   {site_name} {site_url} {site_logo_url}
 *   {user_first_name} {user_login} {user_email} {user_display_name}
 *   {password_set_link} {password_link_expiry_days}
 *   {support_page_url} {login_url}
 *   {agency_name} {agency_email} {agency_phone} {agency_url} {agency_logo_url}
 *   {current_year}
 *
 * Inline CSS only — email clients ignore most external/embedded styles.
 *
 * v1.0 | 2026-05-22
 *
 * @package    SiteEssentials
 * @subpackage Modules\ClientOnboarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{site_name} — Welcome</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f4f4f7;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);">

        <!-- Header -->
        <tr>
          <td style="padding:32px 32px 16px 32px;border-bottom:1px solid #e5e7eb;">
            <p style="margin:0;font-size:13px;color:#6b7280;letter-spacing:0.5px;text-transform:uppercase;">{agency_name} · Website Support</p>
            <h1 style="margin:8px 0 0 0;font-size:24px;font-weight:600;color:#111827;line-height:1.3;">Welcome to {site_name}</h1>
          </td>
        </tr>

        <!-- Greeting -->
        <tr>
          <td style="padding:24px 32px 8px 32px;">
            <p style="margin:0 0 12px 0;font-size:16px;line-height:1.55;">Hi {user_first_name},</p>
            <p style="margin:0 0 12px 0;font-size:16px;line-height:1.55;">Your WordPress account on <a href="{site_url}" style="color:#4f46e5;text-decoration:none;">{site_name}</a> is ready. To get started, set your password using the secure link below.</p>
          </td>
        </tr>

        <!-- Password CTA -->
        <tr>
          <td style="padding:8px 32px 24px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#eef2ff;border-radius:8px;">
              <tr>
                <td style="padding:20px 24px;">
                  <p style="margin:0 0 4px 0;font-size:13px;color:#4338ca;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Step 1 — Set your password</p>
                  <p style="margin:0 0 14px 0;font-size:14px;line-height:1.5;color:#374151;">Your username is <strong>{user_login}</strong>. Click below to set a password you'll remember. The link expires in {password_link_expiry_days} days for security.</p>
                  <a href="{password_set_link}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;font-size:15px;">Set your password</a>
                  <p style="margin:14px 0 0 0;font-size:12px;color:#6b7280;line-height:1.5;">Or copy this link: <span style="word-break:break-all;color:#4338ca;">{password_set_link}</span></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- AI Support card -->
        <tr>
          <td style="padding:0 32px 8px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
              <tr>
                <td style="padding:18px 22px;">
                  <p style="margin:0 0 4px 0;font-size:13px;color:#4338ca;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Your custom website AI assistant</p>
                  <p style="margin:0 0 10px 0;font-size:15px;font-weight:600;color:#111827;">{site_name} Website Support AI Chat</p>
                  <p style="margin:0 0 12px 0;font-size:14px;line-height:1.55;color:#4b5563;">A custom GPT trained on your website configuration. Use it for guided self-help and to know when to ask us for further assistance. Best for business owners and site admins.</p>
                  <a href="https://chatgpt.com/" style="color:#4f46e5;text-decoration:none;font-weight:600;font-size:14px;">Open your support AI →</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Guides -->
        <tr>
          <td style="padding:16px 32px 8px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
              <tr>
                <td style="padding:18px 22px;">
                  <p style="margin:0 0 4px 0;font-size:13px;color:#4338ca;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Quick start guides</p>
                  <p style="margin:0 0 10px 0;font-size:14px;line-height:1.55;color:#4b5563;">How to edit pages and update content with Breakdance Builder, plus everything else you'll need day-to-day.</p>
                  <a href="{support_page_url}" style="color:#4f46e5;text-decoration:none;font-weight:600;font-size:14px;">Open your Support hub →</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Reports (edit these per-site) -->
        <tr>
          <td style="padding:16px 32px 8px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
              <tr>
                <td style="padding:18px 22px;">
                  <p style="margin:0 0 4px 0;font-size:13px;color:#4338ca;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Your reports</p>
                  <p style="margin:0 0 12px 0;font-size:14px;line-height:1.55;color:#4b5563;">We've prepared baseline reports on the health, content, and performance of your site. These are updated periodically — your Support hub will always have the latest.</p>
                  <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.8;color:#1f2937;">
                    <li><a href="{site_url}/wp-content/ai-knowledge/agency-reports/agency-reports-index.html" style="color:#4f46e5;text-decoration:none;">Website Status Reports — site health, content &amp; performance</a></li>
                    <li><a href="{site_url}/wp-content/ai-knowledge/agency-reports/" style="color:#4f46e5;text-decoration:none;">Go-live health check &amp; authority-building reports</a></li>
                  </ul>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Sign-off -->
        <tr>
          <td style="padding:24px 32px 8px 32px;">
            <p style="margin:0 0 12px 0;font-size:15px;line-height:1.55;">Once you've set your password, log in at <a href="{login_url}" style="color:#4f46e5;text-decoration:none;">{login_url}</a> — you'll land straight on your Support hub.</p>
            <p style="margin:0 0 8px 0;font-size:15px;line-height:1.55;">Reach out any time if you need a hand.</p>
            <p style="margin:0;font-size:15px;line-height:1.55;">— {agency_name}</p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:24px 32px 32px 32px;border-top:1px solid #e5e7eb;background:#fafafa;">
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">
              {agency_name}<br>
              <a href="mailto:{agency_email}" style="color:#6b7280;text-decoration:none;">{agency_email}</a> &middot;
              <a href="tel:{agency_phone}" style="color:#6b7280;text-decoration:none;">{agency_phone}</a> &middot;
              <a href="{agency_url}" style="color:#6b7280;text-decoration:none;">{agency_url}</a>
            </p>
            <p style="margin:8px 0 0 0;font-size:11px;color:#9ca3af;">This message was sent to {user_email} as part of website onboarding for {site_name}. &copy; {current_year} {agency_name}.</p>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
