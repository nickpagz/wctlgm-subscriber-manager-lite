<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $invites ) ) {
	$invites = array();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Your Subscription Activation Details</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
	<div style="width: 80%; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
		<h1>Your Subscription Activation Details</h1>
		<p>Thank you for activating your subscription. Below are your private channel invite links:</p>

		<?php foreach ( $invites as $invite ) : ?>
		<div style="margin-bottom: 10px;">
			<span style="font-weight: bold;"><?php echo esc_html( $invite['name'] ); ?>:</span>
			<a href="<?php echo esc_url( $invite['invite_link'] ); ?>" target="_blank" style="color: #0066cc; text-decoration: none;">Join Channel</a>
		</div>
		<?php endforeach; ?>

		<p>If you have any issues or questions, please contact support.</p>
	</div>
</body>
</html>
