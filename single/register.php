<?php
	/**
	 * The template for displaying login page
	 */

nocache_headers();

$default_profile_fields = tourmaster_get_profile_fields();
$profile_fields = array_merge(array(
	'username' => array(
		'title' => esc_html__('Username', 'tourmaster'),
		'type' => 'text',
		'required' => true
	),
	'password' => array(
		'title' => esc_html__('Password', 'tourmaster'),
		'type' => 'password',
		'required' => true
	),
	'confirm-password' => array(
		'title' => esc_html__('Confirm Password', 'tourmaster'),
		'type' => 'password',
		'required' => true
	),
), $default_profile_fields);

if( isset($_POST['security']) ){
	if( wp_verify_nonce($_POST['security'], 'tourmaster-registration') ){
		
		// simple validation
		$verify = tourmaster_validate_profile_field($profile_fields);
		if( is_wp_error($verify) ){
			$error_messages = '';
			foreach( $verify->get_error_messages() as $messages ){
				$error_messages .= empty($error_messages)? '': '<br />';
				$error_messages .= $messages;
			}
		}else{

			// validate the data
			if( username_exists($_POST['username']) ){
				$error_messages = esc_html__('Username already exists, pleae try again with another name.', 'tourmaster');
			}else if( $_POST['password'] != $_POST['confirm-password'] ){
				$error_messages = esc_html__('Password does not match the confirm password.', 'tourmaster');
			}else{
				$user_id = wp_insert_user(array(
					'user_login' => $_POST['username'], 
					'user_pass' => $_POST['password'],
					'user_email' => $_POST['email'],
					'role' => 'subscriber'
				));

				if( is_wp_error($user_id) ){
					$error_messages = $user_id->get_error_message();
				
				// successfully insert the user
				}else{
					tourmaster_mail_notification('registration-complete-mail', null, $user_id);

					tourmaster_update_profile_field($default_profile_fields, $user_id);
?>
<html>
	<body>
		<form method="post" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" id="tourmaster-login-redirect">
		    <input type="hidden" name="log" value="<?php echo esc_attr($_POST['username']); ?>" />
		    <input type="hidden" name="pwd" value="<?php echo esc_attr($_POST['password']); ?>" />
			<input type="hidden" name="rememberme"  value="forever" />
			<input type="hidden" name="redirect_to" value="<?php 
				$redirect_url = '';
				if( empty($_POST['redirect']) ){
					$redirect_url = tourmaster_get_template_url('user');
				}else{
					if( is_numeric($_POST['redirect']) ){
						$redirect_url = get_permalink($_POST['redirect']);
					}else{
						$redirect_url = tourmaster_get_template_url($_POST['redirect']);
						$redirect_url = empty($redirect_url)? $_POST['redirect']: $redirect_url;
					}
				} 
				echo esc_url($redirect_url);
			?>" />
		</form>

		<script type="text/javascript">
			document.getElementById("tourmaster-login-redirect").submit();
		</script>
	</body>
</html>
<?php
					exit();
				}
			}
		}
	}

	$_POST['password'] = '';
	$_POST['confirm-password'] = '';
}

get_header();

	echo '<div class="tourmaster-template-wrapper" >';
	echo '<div class="tourmaster-container" >';
	echo '<div class="tourmaster-page-content tourmaster-item-pdlr" >';

	if( !empty($error_messages) ){
		echo '<div class="tourmaster-notification-box tourmaster-failure" >' . $error_messages . '</div>';
	}

	tourmaster_get_registration_form();

	echo '</div>'; // tourmaster-page-content
	echo '</div>'; // tourmaster-container
	echo '</div>'; // tourmaster-template-wrapper

get_footer(); 

?>