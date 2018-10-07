<?php
	echo '<div class="tourmaster-user-content-inner tourmaster-user-content-inner-my-booking-single" >';
	tourmaster_get_user_breadcrumb();

	if( !empty($_GET['error_code']) && $_GET['error_code'] == 'cannot_upload_file' ){ 
		echo '<div class="tourmaster-notification-box tourmaster-failure" >';
		echo esc_html__('Cannot upload a media file, please try uploading it again.', 'tourmaster');
		echo '</div>';
	}

	// booking table block
	tourmaster_user_content_block_start();

	global $current_user;
	$result = tourmaster_get_booking_data(array(
		'id' => $_GET['id'],
		'user_id' => $current_user->data->ID,
		'order_status' => array(
			'condition' => '!=',
			'value' => 'cancel'
		)
	), array('single' => true));

	$contact_fields = tourmaster_get_payment_contact_form_fields();
	$contact_detail = empty($result->contact_info)? array(): json_decode($result->contact_info, true);
	$billing_detail = empty($result->billing_info)? array(): json_decode($result->billing_info, true);
	$booking_detail = empty($result->booking_detail)? array(): json_decode($result->booking_detail, true);

	// sidebar
	echo '<div class="tourmaster-my-booking-single-content-wrap" >';
	echo '<div class="tourmaster-my-booking-single-sidebar" >';
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
	echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Order Status', 'tourmaster') . '</h3>';
	echo '<div class="tourmaster-booking-status tourmaster-status-' . esc_attr($result->order_status) . '" >' . $statuses[$result->order_status] . '</div>';
	
	echo '<h3 class="tourmaster-my-booking-single-sub-title">' . esc_html__('Bank Payment Receipt', 'tourmaster') . '</h3>';
	if( in_array($result->order_status, array('pending', 'rejected')) ){
		
		echo '<a data-tmlb="payment-receipt" class="tourmaster-my-booking-single-receipt-button tourmaster-button" >' . esc_html__('Submit Payment Receipt', 'tourmaster') . '</a>';
		echo tourmaster_lightbox_content(array(
			'id' => 'payment-receipt',
			'title' => esc_html__('Submit Bank Payment Receipt', 'tourmaster'),
			'content' => tourmaster_lb_payment_receipt($result->id)
		));
		echo '<a href="';
		echo esc_url(add_query_arg(array('tid'=>$result->id, 'step'=>3), tourmaster_get_template_url('payment')));
		echo '" class="tourmaster-my-booking-single-payment-button tourmaster-button" >' . esc_html__('Make an Online Payment', 'tourmaster') . '</a>';
	
	}else if( !empty($result->payment_info) ){

		// print payment info
		$payment_info = json_decode($result->payment_info, true);

		if( !empty($payment_info['file_url']) ){
			echo '<div class="tourmaster-my-booking-single-payment-receipt" >';
			echo '<a href="' . esc_url($payment_info['file_url']) . '" target="_blank" >';
			echo '<img src="' . esc_url($payment_info['file_url']) . '" alt="receipt" />';
			echo '</a>';
			echo '</div>';			
		}

		if( !empty($payment_info['submission_date']) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Submission Date', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $payment_info['submission_date'] . '</span>';
			echo '</div>';			
		}else if( !empty($result->payment_date) && $result->payment_date != '0000-00-00 00:00:00' ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Payment Date', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $result->payment_date . '</span>';
			echo '</div>';				
		} 
		
		if( !empty($payment_info['payment_method']) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Payment Method', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $payment_info['payment_method'] . '</span>';
			echo '</div>';			
		}
		
		// deposit price
		if( !empty($payment_info['deposit-rate']) && !empty($payment_info['deposit-price']) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Deposit Rate', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $payment_info['deposit-rate'] . '%</span>';
			echo '</div>';			

			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Deposit Price', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . tourmaster_money_format($payment_info['deposit-price']) . '</span>';
			echo '</div>';			
		}
		
		if( !empty($payment_info['transaction_id']) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Transaction ID', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $payment_info['transaction_id'] . '</span>';
			echo '</div>';			
		}
		
		if( $result->order_status == 'deposit-paid' ){
			$pricing_info = json_decode($result->pricing_info, true);
			
			if( !empty($pricing_info['deposit-price']) && !empty($pricing_info['deposit-paypal-amount']) && 
				tourmaster_compare_price($pricing_info['deposit-paypal-amount'], $payment_info['amount']) ){
				
				echo '<div class="tourmaster-my-booking-single-field clearfix" >';
				echo '<span class="tourmaster-head">' . sprintf(esc_html__('Deposit Amount', 'tourmaster'), $pricing_info['deposit-paypal-service-rate']) . ' :</span> ';
				echo '<span class="tourmaster-tail">' . tourmaster_money_format($pricing_info['deposit-price']) . '</span>';
				echo '</div>';

				echo '<div class="tourmaster-my-booking-single-field clearfix" >';
				echo '<span class="tourmaster-head">' . sprintf(esc_html__('Paypal Fee (%d%%)', 'tourmaster'), $pricing_info['deposit-paypal-service-rate']) . ' :</span> ';
				echo '<span class="tourmaster-tail">' . tourmaster_money_format($pricing_info['deposit-paypal-service-fee']) . '</span>';
				echo '</div>';
			}		
		}

		if( !empty($payment_info['amount']) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . esc_html__('Paid Amount', 'tourmaster') . ' :</span> ';
			echo '<span class="tourmaster-tail">' . tourmaster_money_format($payment_info['amount']) . '</span>';
			echo '</div>';			
		}

	}
	echo '</div>'; // tourmaster-my-booking-single-sidebar

	// content
	echo '<div class="tourmaster-my-booking-single-content" >';
	echo '<div class="tourmaster-item-rvpdlr clearfix" >';
	echo '<div class="tourmaster-my-booking-single-order-summary-column tourmaster-column-20 tourmaster-item-pdlr" >';
	echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Order Summary', 'tourmaster') . '</h3>';

	echo '<div class="tourmaster-my-booking-single-field clearfix" >';
	echo '<span class="tourmaster-head">' . esc_html__('Order Number', 'tourmaster') . ' :</span> ';
	echo '<span class="tourmaster-tail">#' . $result->id . '</span>';
	echo '</div>';

	echo '<div class="tourmaster-my-booking-single-field clearfix" >';
	echo '<span class="tourmaster-head">' . esc_html__('Booking Date', 'tourmaster') . ' :</span> ';
	echo '<span class="tourmaster-tail">' . tourmaster_date_format($result->booking_date) . '</span>';
	echo '</div>';

	echo '<div class="tourmaster-my-booking-single-field clearfix" >';
	echo '<span class="tourmaster-head">' . esc_html__('Tour', 'tourmaster') . ' :</span> ';
	echo '<span class="tourmaster-tail"><a href="' . get_permalink($result->tour_id) . '" target="_blank" >' . get_the_title($result->tour_id) . '</a></span>';
	echo '</div>';

	echo '<div class="tourmaster-my-booking-single-field clearfix" >';
	echo '<span class="tourmaster-head">' . esc_html__('Travel Date', 'tourmaster') . ' :</span> ';
	echo '<span class="tourmaster-tail">' . tourmaster_date_format($result->travel_date) . '</span>';
	echo '</div>';

	if( !empty($booking_detail['package']) ){
		$tour_option = tourmaster_get_post_meta($result->tour_id, 'tourmaster-tour-option');
		$date_price = tourmaster_get_tour_date_price($tour_option, $result->tour_id, $result->travel_date);

		echo '<div class="tourmaster-my-booking-single-field clearfix" >';
		echo '<span class="tourmaster-head">' . esc_html__('Package', 'tourmaster') . ' :</span> ';
		echo '<span class="tourmaster-tail">' . $booking_detail['package'];
		foreach($date_price['package'] as $package){
			if( $package['title'] == $booking_detail['package'] ){
				echo '<span class="tourmaster-my-booking-package-detail" >';
				echo '<span>' . $package['caption'] . '</span>';
				echo '<span>' . esc_html__('Start Time: ', 'tourmaster') . $package['start-time'] . '</span>';
				echo '</span>';
			}
		}
		echo '</span>';
		echo '</div>';
	}

	if( !empty($contact_detail['additional_notes']) ){
		echo '<div class="tourmaster-my-booking-single-field tourmaster-additional-note clearfix" >';
		echo '<span class="tourmaster-head">' . esc_html__('Customer\'s Note', 'tourmaster') . ' :</span> ';
		echo '<span class="tourmaster-tail">' . $contact_detail['additional_notes'] . '</span>';
		echo '</div>';
	}
	echo '</div>'; // tourmaster-my-booking-single-order-summary-column

	echo '<div class="tourmaster-my-booking-single-contact-detail-column tourmaster-column-20 tourmaster-item-pdlr" >';
	echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Contact Detail', 'tourmaster') . '</h3>';
	foreach( $contact_fields as $field_slug => $contact_field ){
		if( !empty($contact_detail[$field_slug]) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . $contact_field['title'] . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $contact_detail[$field_slug] . '</span>';
			echo '</div>';
		}
	}
	echo '</div>'; // tourmaster-my-booking-single-contact-detail-column

	echo '<div class="tourmaster-my-booking-single-billing-detail-column tourmaster-column-20 tourmaster-item-pdlr" >';
	echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Billing Detail', 'tourmaster') . '</h3>';
	foreach( $contact_fields as $field_slug => $contact_field ){
		if( !empty($billing_detail[$field_slug]) ){
			echo '<div class="tourmaster-my-booking-single-field clearfix" >';
			echo '<span class="tourmaster-head">' . $contact_field['title'] . ' :</span> ';
			echo '<span class="tourmaster-tail">' . $billing_detail[$field_slug] . '</span>';
			echo '</div>';
		}
	}
	echo '</div>'; // tourmaster-my-booking-single-billing-detail-column
	echo '</div>'; // tourmaster-item-rvpdl

	// traveller info
	if( !empty($result->traveller_info) ){
		$title_types = tourmaster_payment_traveller_title();
		$traveller_info = json_decode($result->traveller_info, true);

		if( !empty($traveller_info) ){
			echo '<div class="tourmaster-my-booking-single-traveller-info" >';
			echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Traveller Info', 'tourmaster') . '</h3>';
			for( $i=0; $i<sizeof($traveller_info['first_name']); $i++ ){
				if( !empty($traveller_info['first_name'][$i]) || !empty($traveller_info['last_name'][$i]) ){
					echo '<div class="tourmaster-my-booking-single-field clearfix" >';
					echo '<span class="tourmaster-head">' . esc_html__('Traveller', 'tourmaster') . ' ' . ($i+1) . ' :</span> ';
					echo '<span class="tourmaster-tail">';
					if( !empty($traveller_info['title'][$i]) ){
						if( !empty($title_types[$traveller_info['title'][$i]]) ){
							echo $title_types[$traveller_info['title'][$i]] . ' ';
						}
					}
					echo $traveller_info['first_name'][$i] . ' ' . $traveller_info['last_name'][$i];
					if( !empty($traveller_info['passport'][$i]) ){
						echo '<br>' . esc_html__('Passport ID :', 'tourmaster') . ' ' . $traveller_info['passport'][$i];
					}
					echo '</span>';
					echo '</div>';				
				}
			}
			echo '</div>'; // tourmaster-my-booking-single-traveller-info
		}
	}

	// price breakdown
	if( !empty($result->pricing_info) ){
		$pricing_info = json_decode($result->pricing_info, true);
		echo '<div class="tourmaster-my-booking-single-price-breakdown" >';
		echo '<h3 class="tourmaster-my-booking-single-title">' . esc_html__('Price Breakdown', 'tourmaster') . '</h3>';
		echo tourmaster_get_tour_price_breakdown($pricing_info['price-breakdown']);

		echo '<div class="tourmaster-my-booking-single-total-price clearfix" >';
		echo '<div class="tourmaster-my-booking-single-field clearfix" >';
		echo '<span class="tourmaster-head">' . esc_html__('Total', 'tourmaster') . '</span> ';
		echo '<span class="tourmaster-tail">' . tourmaster_money_format($result->total_price) . '</span>';
		echo '</div>';
		echo '</div>';
		echo '</div>'; // tourmaster-my-booking-single-traveller-info
	}

	echo '</div>'; // tourmaster-my-booking-single-content
	echo '</div>'; // tourmaster-my-booking-single-content-wrap

	tourmaster_user_content_block_end();

	echo '</div>'; // tourmaster-user-content-inner