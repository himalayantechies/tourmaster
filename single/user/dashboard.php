<?php
	/* dashboard page content */
	global $current_user;

	///////////////////////
	// my profile section
	///////////////////////
	tourmaster_user_content_block_start(array(
		'title' => esc_html__('My Profile', 'tourmaster'),
		'title-link-text' => esc_html__('Edit Profile', 'tourmaster'),
		'title-link' => tourmaster_get_template_url('user', array('page_type'=>'edit-profile'))
	));

	$profile_list = array(
		'full_name' => esc_html__('Name', 'tourmaster'),
		'gender' => esc_html__('Gender', 'tourmaster'),
		'birth_date' => esc_html__('Birth Date', 'tourmaster'),
		'country' => esc_html__('Country', 'tourmaster'),
		'email' => esc_html__('Email', 'tourmaster'),
		'phone' => esc_html__('Phone', 'tourmaster'),
		'contact_address' => esc_html__('Contact Address', 'tourmaster'),
	);
	echo '<div class="tourmaster-my-profile-wrapper" >';
	echo '<div class="tourmaster-my-profile-avatar" >';
	$avatar = get_the_author_meta('tourmaster-user-avatar', $current_user->data->ID);
	if( !empty($avatar['file_url']) ){
		echo '<img src="' . esc_url($avatar['file_url']) . '" alt="profile-image" />';
	}else{
		echo get_avatar($current_user->data->ID, 85);
	}
	echo '</div>';

	$even_column = true;
	echo '<div class="tourmaster-my-profile-info-wrap clearfix" >';
	foreach( $profile_list as $meta_field => $field_title ){
		$extra_class  = 'tourmaster-my-profile-info-' . $meta_field;
		$extra_class .= ($even_column)? ' tourmaster-even': ' tourmaster-odd';
		

		echo '<div class="tourmaster-my-profile-info ' . esc_attr($extra_class) . ' clearfix" >';
		echo '<span class="tourmaster-head" >' . $field_title . '</span>';
		echo '<span class="tourmaster-tail" >';
		if( $meta_field == 'birth_date' ){
			$user_meta = tourmaster_get_user_meta($current_user->data->ID, $meta_field, '-');
			if( $user_meta == '-' ){
				echo $user_meta;
			}else{
				echo tourmaster_date_format($user_meta);
			}
		}else if( $meta_field == 'gender' ){
			$user_meta = tourmaster_get_user_meta($current_user->data->ID, $meta_field, '-');
			if( $user_meta == 'male' ){
				echo esc_html__('Male', 'tourmaster');
			}else if( $user_meta == 'female' ){
				echo esc_html__('Female', 'tourmaster');
			}
		}else{
			echo tourmaster_get_user_meta($current_user->data->ID, $meta_field, '-');
		}

		echo '</span>';
		echo '</div>';

		$even_column = !$even_column;
	}
	echo '</div>'; // tourmaster-my-profile-info-wrap

	echo '</div>'; // tourmaster-my-profile-wrapper
	tourmaster_user_content_block_end();

	///////////////////////
	// my booking section
	///////////////////////

	// query 
	$conditions = array('user_id' => $current_user->data->ID, 'order_status'=> array('condition'=>'!=', 'value'=>'cancel'));
	$results = tourmaster_get_booking_data($conditions, array('paged'=>1, 'num-fetch'=>5));

	if( !empty($results) ){	
		$statuses = array(
			'all' => esc_html__('All', 'tourmaster'),
			'pending' => esc_html__('Pending', 'tourmaster'),
			'approved' => esc_html__('Approved', 'tourmaster'),
			'receipt-submitted' => esc_html__('Receipt Submitted', 'tourmaster'),
			'online-paid' => esc_html__('Online Paid', 'tourmaster'),
			'deposit-paid' => esc_html__('Deposit Paid', 'tourmaster'),
			'departed' => esc_html__('Departed', 'tourmaster'),
			'rejected' => esc_html__('Rejected', 'tourmaster'),
		);

		tourmaster_user_content_block_start(array(
			'title' => esc_html__('Current Booking', 'tourmaster'),
			'title-link-text' => esc_html__('View All Bookings', 'tourmaster'),
			'title-link' => tourmaster_get_template_url('user', array('page_type'=>'my-booking'))
		));

		echo '<table class="tourmaster-my-booking-table tourmaster-table" >';
		tourmaster_get_table_head(array(
			esc_html__('Tour Name', 'tourmaster'),
			esc_html__('Travel Date', 'tourmaster'),
			esc_html__('Total', 'tourmaster'),
			esc_html__('Payment Status', 'tourmaster'),
		));
		foreach( $results as $result ){

			$single_booking_url = add_query_arg(array(
				'page_type' => 'my-booking',
				'sub_page' => 'single',
				'id' => $result->id,
				'tour_id' => $result->tour_id
			));
			$title = '<a class="tourmaster-my-booking-title" href="' . esc_url($single_booking_url) . '" >' . get_the_title($result->tour_id) . '</a>';

			$status  = '<span class="tourmaster-my-booking-status tourmaster-booking-status tourmaster-status-' . esc_attr($result->order_status) . '" >';
			if( $result->order_status == 'approved' ){
				$status .= '<i class="fa fa-check" ></i>';
			}else if( $result->order_status == 'departed' ){
				$status .= '<i class="fa fa-check-circle-o" ></i>';
			}else if( $result->order_status == 'rejected' ){
				$status .= '<i class="fa fa-remove" ></i>';
			}		
			$status .= $statuses[$result->order_status];
			$status .= '</span>';
			if( in_array($result->order_status, array('pending', 'receipt-submitted', 'rejected')) ){
				$status .= '<a class="tourmaster-my-booking-action fa fa-dollar" href="' . esc_url($single_booking_url) . '" ></a>';
			}
			if( in_array($result->order_status, array('pending', 'receipt-submitted', 'rejected')) ){
				$status .= '<a class="tourmaster-my-booking-action fa fa-remove" href="' . add_query_arg(array('action'=>'remove', 'id'=>$result->id)) . '" ></a>';
			}

			tourmaster_get_table_content(array(
				$title,
				tourmaster_date_format($result->travel_date),
				'<span class="tourmaster-my-booking-price" >' . tourmaster_money_format($result->total_price) . '</span>',
				$status
			));
		}
		echo '</table>';

		tourmaster_user_content_block_end();
	}

	///////////////////////
	// review section
	///////////////////////
	$conditions = array(
		'user_id' => $current_user->data->ID,
		'order_status' => 'departed'
	);
	$results = tourmaster_get_booking_data($conditions, array('paged'=>1, 'num-fetch'=>5, 'with-review' => true));

	if( !empty($results) ){
		tourmaster_user_content_block_start(array(
			'title' => esc_html__('Reviews', 'tourmaster'),
			'title-link-text' => esc_html__('View All Reviews', 'tourmaster'),
			'title-link' => tourmaster_get_template_url('user', array('page_type'=>'reviews'))
		));

		echo '<table class="tourmaster-user-review-table tourmaster-table" >';
		tourmaster_get_table_head(array(
			esc_html__('Tour Name', 'tourmaster'),
			esc_html__('Status', 'tourmaster'),
			esc_html__('Action', 'tourmaster'),
		));		
		foreach( $results as $result ){
			$title = get_the_title($result->tour_id);

			if( $result->review_score == '' ){
				$status  = '<span class="tourmaster-user-review-status tourmaster-status-pending" >';	
				$status .= esc_html__('Pending', 'tourmaster');
				$status .= '</span>';

				$action  = '<span class="tourmaster-user-review-action" data-tmlb="submit-review" >' . esc_html__('Submit Review', 'tourmaster') . '</span>';
				$action .= tourmaster_lightbox_content(array(
					'id' => 'submit-review',
					'title' => esc_html__('Submit Your Review', 'tourmaster'),
					'content' => tourmaster_get_review_form( $result )
				));
			}else{
				$status  = '<span class="tourmaster-user-review-status tourmaster-status-submitted" >';	
				$status .= esc_html__('Submitted', 'tourmaster');
				$status .= '</span>';

				$action  = '<span class="tourmaster-user-review-action" data-tmlb="view-review" >' . esc_html__('View Review', 'tourmaster') . '</span>';		
				$action .= tourmaster_lightbox_content(array(
					'id' => 'view-review',
					'title' => esc_html__('Your Review', 'tourmaster'),
					'content' => tourmaster_get_submitted_review( $result )
				));
			}

			tourmaster_get_table_content(array($title, $status, $action));
		}
		echo '</table>';

		tourmaster_user_content_block_end();
	}

?>