<?php
/**
 * Status checker for WP Mail SMTP integration.
 */

function guide_check_wp_mail_smtp_mailer() {
	$options = get_option( 'wp_mail_smtp', array() );
	$mailer  = isset( $options['mail']['mailer'] ) ? $options['mail']['mailer'] : '';

	if ( empty( $mailer ) || $mailer === 'php' ) {
		return array( 'status' => 'warning', 'message' => 'Using PHP mail() — no external service' );
	}

	$names = array(
		'sendinblue' => 'Brevo', 'smtp' => 'Custom SMTP', 'gmail' => 'Gmail',
		'outlook' => 'Outlook', 'mailgun' => 'Mailgun', 'sendgrid' => 'SendGrid',
		'postmark' => 'Postmark', 'sparkpost' => 'SparkPost',
	);

	$provider = isset( $names[ $mailer ] ) ? $names[ $mailer ] : $mailer;

	return array( 'status' => 'ok', 'message' => 'Connected via ' . $provider );
}
