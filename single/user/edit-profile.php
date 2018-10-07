<?php
	$profile_fields = tourmaster_get_profile_fields();

	echo '<div class="tourmaster-user-content-inner tourmaster-user-content-inner-edit-profile" >';
	tourmaster_get_user_breadcrumb();

	// update data
	if( isset($_POST['tourmaster-edit-profile']) ){
		$verify = tourmaster_validate_profile_field($profile_fields);

		if( is_wp_error($verify) ){
			$error_messages = '';
			foreach( $verify->get_error_messages() as $messages ){
				$error_messages .= empty($error_messages)? '': '<br />';
				$error_messages .= $messages;
			}
			tourmaster_user_update_notification($error_messages, false);
		}else{
			tourmaster_update_profile_field($profile_fields);
			tourmaster_user_update_notification(esc_html__('Your profile has been successfully changed.', 'tourmaster'));
		}
	}

	// edit profile page content
	echo '<form class="tourmaster-edit-profile-wrap tourmaster-form-field" method="POST" >';

	echo '<div class="tourmaster-edit-profile-avatar" >';
	echo get_avatar($current_user->data->ID, 85);
	echo '<a class="tourmaster-button" href="https://gravatar.com" target="_blank" >' . esc_html__('Change Profile Picture', 'tourmaster') . '</a>';
	echo '</div>';

	foreach( $profile_fields as $slug => $profile_field ){
		$profile_field['slug'] = $slug;
		tourmaster_get_form_field($profile_field, 'profile');
	}

	echo '<input type="submit" class="tourmaster-edit-profile-submit tourmaster-button" value="' . esc_html__('Update Profile', 'tourmaster') . '" />';
	echo '<input type="hidden" name="tourmaster-edit-profile" value="1" />';
	echo '</form>'; // tourmaster-edit-profile-wrap

	echo '</div>'; // tourmaster-user-content-inner
?>