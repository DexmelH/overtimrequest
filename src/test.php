<?php

require  '../vendor/autoload.php';
$config = require __DIR__ . '/config.php';
$htmlPath = __DIR__ . '/usr/template/request_email.html';
$htmlTemplate = file_get_contents($htmlPath);

use App\Service\Mailer;

$mailer = new Mailer($config["mail"]);

$toEmail = "medrano_c-kdt@global.kawasaki.com";
$toName = "Dexmel";
$subject = "Test";
// $bodyHtml = $htmlTemplate;

function buildUnlockRequestSubmittedEmailBody(array $data): string
{
    $employeeName = htmlspecialchars((string)($data['employeeName'] ?? '—'));
    $requesterName = htmlspecialchars((string)($data['requesterName'] ?? '—'));
    $requestedMonthLabel = htmlspecialchars((string)($data['requestedMonthLabel'] ?? '—'));
    $dateRequestedLabel = htmlspecialchars((string)($data['dateRequestedLabel'] ?? '—'));
    $approvalUrl = htmlspecialchars((string)($data['approvalUrl'] ?? '#'));
    $approverName = htmlspecialchars((string)($data['approverName'] ?? 'Approver'));
 
    $backgroundImg = './test/bg.png';
 
    $pendingIcon = '<img src="./test/pending.png" width="20" height="20" alt=""  style="display:block; width:20px; height:20px; border:0;">';
 
    $personIcon = '<img src="./test/user.png" width="20" height="20" alt=""  style="display:block; width:20px; height:20px; border:0;">';
 
    $calendarIcon = '<img src="./test/calendar.png" width="20" height="20" alt=""    style="display:block; width:20px; height:20px; border:0;">';
 
    $clockIcon = '<img src="./test/clock.png" width="20" height="20" alt=""  style="display:block; width:20px; height:20px; border:0;">';
 
    $infoIcon = '<img src="./test/info.png" width="20" height="20" alt=""    style="display:block; width:20px; height:20px; border:0;">';
 
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report Access Request Submitted</title>
</head>
<body style="margin:0; padding:0; background:#f3f5fb; font-family:Segoe UI,Arial, Helvetica, sans-serif; color:#25324b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eeedf8; margin:0; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="840" cellpadding="0" cellspacing="0" border="0" style="width:840px; max-width:840px; background:#f7f6fd; border:1px solid #d9ddf1; border-radius:22px; overflow:hidden;">
                   
                    <tr>
                        <td style="padding:24px 28px 22px 28px; background-color:#4F78D9; background-image:url('{$backgroundImg}');background-repeat:no-repeat; background-position:right center; background-size:cover;" >
                            <div style="font-size:15px; color:#EAF1FF; margin-bottom:10px;">Web JMR System</div>
                            <div style="font-size:28px; line-height:1.25; font-weight:500; color:#FFFFFF; margin-bottom:10px;">
                                Daily Report Access Request Submitted
                            </div>
                            <div style="font-size:15px; line-height:1.6; color:#EAF1FF;">
                                A temporary Daily Report access request is awaiting your review
                            </div>
                        </td>
                       
                    </tr>
 
                    <tr>
                        <td style="padding:30px 36px 30px 36px;">
                            <div style="font-size:18px; line-height:1.4; font-weight:700; color:#25324b; margin-bottom:20px;">
                                Dear {$approverName},
                            </div>
 
                            <div style="font-size:16px; line-height:1.7; color:#3D4A63; margin-bottom:18px;">
                                A temporary Daily Report access request has been submitted and is awaiting your action.
                            </div>
 
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #d9ddf1; border-radius:16px; overflow:hidden; background:#FFFFFF;">
                                <tr>
                                    <td style="padding:18px 20px; background:#F1F6FF; border-bottom:1px solid #E2E8F5;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="vertical-align:middle;">{$pendingIcon}</td>
                                                <td style="width:10px;"></td>
                                                <td style="vertical-align:middle; font-size:16px; font-weight:700; color:#355FD1; letter-spacing:0.2px;">
                                                    PENDING REVIEW
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
 
                                <tr>
                                    <td style="padding:0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="vertical-align:middle;">{$personIcon}</td>
                                                            <td style="width:12px;"></td>
                                                            <td style="vertical-align:middle; font-size:15px; font-weight:700; color:#2F3C56;">Employee:</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4; font-size:15px; color:#2F3C56;">
                                                    {$employeeName}
                                                </td>
                                            </tr>
 
                                            <tr>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="vertical-align:middle;">{$personIcon}</td>
                                                            <td style="width:12px;"></td>
                                                            <td style="vertical-align:middle; font-size:15px; font-weight:700; color:#2F3C56;">Requested By:</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4; font-size:15px; color:#2F3C56;">
                                                    {$requesterName}
                                                </td>
                                            </tr>
 
                                            <tr>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="vertical-align:middle;">{$calendarIcon}</td>
                                                            <td style="width:12px;"></td>
                                                            <td style="vertical-align:middle; font-size:15px; font-weight:700; color:#2F3C56;">Requested Month:</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="width:50%; padding:14px 18px; border-bottom:1px solid #E4E8F4; font-size:15px; color:#2F3C56;">
                                                    {$requestedMonthLabel}
                                                </td>
                                            </tr>
 
                                            <tr>
                                                <td style="width:50%; padding:14px 18px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="vertical-align:middle;">{$clockIcon}</td>
                                                            <td style="width:12px;"></td>
                                                            <td style="vertical-align:middle; font-size:15px; font-weight:700; color:#2F3C56;">Requested On:</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="width:50%; padding:14px 18px; font-size:15px; color:#2F3C56;">
                                                    {$dateRequestedLabel}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
 
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px; background:#EFF3FF; border:1px solid #D8E1F5; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="vertical-align:middle;">{$infoIcon}</td>
                                                <td style="width:12px;"></td>
                                                <td style="vertical-align:middle; font-size:14px; line-height:1.7; color:#4B5C82;">
                                                    Please review this request through the <span style="color:#355FD1; font-weight:500;">Temporary Access Approval</span> page.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
 
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px auto 0 auto;">
                                <tr>
                                    <td align="center" bgcolor="#3F6FE4" style="border-radius:10px;line-height:1.7; padding:14px 28px;">
                                        <a href="{$approvalUrl}" target="_blank" style="display:inline-block; min-width:260px; text-align:center;  font-size:16px; font-weight:600; color:#FFFFFF; text-decoration:none;">
                                            Review Access Request
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
 
                    <tr>
                        <td style="padding:0 36px 30px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f8ff; border-top:1px solid #e1e9f5; border-radius:0 0 14px 14px;">
                                <tr>
                                    <td align="center" style="padding:18px 20px; font-size:13px; color:#6a7a94;">
                                        This is an automated notification from the Web JMR System.
                                    </td>
                                     
                                </tr>
                            </table>
                        </td>  
                       
                    </tr>
 
                </table>
               
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
 
$bodyHtml = buildUnlockRequestSubmittedEmailBody([
    'employeeName' => 'John Doe',
    'requesterName' => 'Jane Smith',
    'requestedMonthLabel' => 'September 2024',
    'dateRequestedLabel' => 'September 15, 2024',
    'approvalUrl' => 'https://example.com/approval/123',
    'approverName' => 'Manager'
]);

$mailer->send($toEmail, $toName, $subject, $bodyHtml);