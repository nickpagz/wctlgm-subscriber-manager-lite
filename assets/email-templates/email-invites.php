<?php

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
	<style>
		body {
			font-family: Arial, sans-serif;
			line-height: 1.6;
			color: #333333;
		}
		.container {
			width: 80%;
			margin: 0 auto;
			padding: 20px;
			background-color: #f9f9f9;
			border: 1px solid #ddd;
			border-radius: 5px;
		}
		.channel-invite {
			margin-bottom: 10px;
		}
		.channel-name {
			font-weight: bold;
		}
		a {
			color: #0066cc;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>Your Subscription Activation Details</h1>
		<p>Thank you for activating your subscription. Below are your private channel invite links:</p>

		<?php foreach ( $invites as $invite ) : ?>
		<div class="channel-invite">
			<span class="channel-name"><?php echo esc_html( $invite['name'] ); ?>:</span>
			<a href="<?php echo esc_url( $invite['invite_link'] ); ?>" target="_blank">Join Channel</a>
		</div>
		<?php endforeach; ?>

		<p>If you have any issues or questions, please contact support.</p>
	</div>
</body>
</html>
