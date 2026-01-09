<?php
/** @var string $siteName */
/** @var string $magicLink */
/** @var string $contentHtml */
$siteName = $siteName ?? 'Flint';
$magicLink = $magicLink ?? '#';
$contentHtml = $contentHtml ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flint Magic Link</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;font-family:Arial,sans-serif;color:#111827;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;">
                            <?= $contentHtml ?>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 32px 24px 32px;">
                            <a href="<?= htmlspecialchars($magicLink, ENT_QUOTES) ?>" style="display:inline-block;background-color:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;font-size:14px;">
                                Open link
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px 32px;font-size:12px;color:#6b7280;">
                            If the button does not work, copy and paste this link into your browser:<br>
                            <a href="<?= htmlspecialchars($magicLink, ENT_QUOTES) ?>" style="color:#4f46e5;word-break:break-all;">
                                <?= htmlspecialchars($magicLink, ENT_QUOTES) ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb;padding:16px 32px;font-size:12px;color:#6b7280;text-align:center;">
                            Sent by <strong style="color:#111827;">Flint</strong> · <?= htmlspecialchars($siteName, ENT_QUOTES) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
