<?php
/**
 * Email OTP HTML Template
 *
 * @package Ashna_OTP
 * @var string $heading
 * @var string $body_text
 * @var string $code
 * @var string $site_name
 * @var int    $expiry_seconds
 * @var string $primary_color
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $heading ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6;padding:36px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);">
					<tr>
						<td style="background-color:<?php echo esc_attr( $primary_color ); ?>;padding:24px;text-align:center;color:#ffffff;">
							<h1 style="margin:0;font-size:20px;font-weight:bold;"><?php echo esc_html( $site_name ); ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding:32px 28px;color:#1f2937;">
							<h2 style="margin:0 0 14px 0;font-size:18px;color:#111827;"><?php echo esc_html( $heading ); ?></h2>
							<p style="margin:0 0 24px 0;font-size:14px;line-height:1.8;color:#4b5563;">
								<?php echo esc_html( $body_text ); ?>
							</p>
							<div style="background-color:#f8fafc;border:2px dashed <?php echo esc_attr( $primary_color ); ?>;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px 0;">
								<span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;color:<?php echo esc_attr( $primary_color ); ?>;direction:ltr;">
									<?php echo esc_html( $code ); ?>
								</span>
							</div>
							<p style="margin:0;font-size:12px;color:#6b7280;line-height:1.7;">
								این کد تا <strong><?php echo esc_html( (string) $expiry_seconds ); ?> ثانیه</strong> معتبر است. در صورتی که این درخواست از طرف شما نبوده است، لطفاً این ایمیل را نادیده بگیرید.
							</p>
						</td>
					</tr>
					<tr>
						<td style="background-color:#f9fafb;padding:16px 28px;text-align:center;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;">
							ارسال شده به صورت خودکار از <?php echo esc_html( $site_name ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
