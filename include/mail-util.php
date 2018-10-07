<?php
	/*	
	*	Utility function for uses
	*/

	// array('title', 'sender', 'recipient', 'message')
	if( !function_exists('tourmaster_mail') ){
		function tourmaster_mail( $settings = array() ){

			$sender_name = tourmaster_get_option('general', 'system-email-name', 'WORDPRESS');
			$sender = tourmaster_get_option('general', 'system-email-address');

			if( !empty($sender) ){ 
				$headers  = "From: {$sender_name} <{$sender}>\r\n";
				if( !empty($settings['reply-to']) ){
					$headers .= "Reply-To: {$settings['reply-to']}\r\n";
				}
				$headers .= "MIME-Version: 1.0\r\n";
				$headers .= "Content-Type: text/html; charset=UTF-8\r\n";


				wp_mail($settings['recipient'], $settings['title'], $settings['message'], $headers);
			}

		} // tourmaster_mail
	}

	if( !function_exists('tourmaster_mail_content') ){
		function tourmaster_mail_content( $content = '', $header = true, $footer = true){

			ob_start();

			echo '<html><body>';
			echo '<div class="tourmaster-mail-template" style="line-height: 1.7; background: #f5f5f5; margin: 40px auto 40px; width: 600px; font-size: 14px; font-family: Arial, Helvetica, sans-serif; color: #838383;" >';
			if( !empty($header) ){
				$header_logo = tourmaster_get_option('general', 'mail-header-logo', TOURMASTER_URL . '/images/logo.png');

				echo '<div class="tourmaster-mail-header" style="background: #353d46; padding: 25px 35px;" >';
				echo tourmaster_get_image($header_logo);
				echo '<div style="display: block; clear: both; visibility: hidden; line-height: 0; height: 0; zoom: 1;" ></div>'; // clear
				echo '</div>';
			}

			$content = tourmaster_content_filter($content);

			//apply css to link and p tag
			$pointer = 0;
			while( ($new_pointer = strpos($content, '<a', $pointer)) !== false ){
				$pointer = $new_pointer + 2;

				$style_tag = strpos($content, 'style=', $pointer);
				$close_tag = strpos($content, '>', $pointer);

				if( $style_tag === false || $close_tag < $style_tag ){
					$first_section = substr($content, 0, $pointer);
					$last_section = substr($content, $pointer);
					$content  = $first_section . ' style="color: #4290de; text-decoration: none;" ' . $last_section;
				}
			}
			echo '<div class="tourmaster-mail-content" style="padding: 60px 60px 40px;" >' . $content . '</div>';

			if( !empty($footer) ){
				$footer_left = tourmaster_get_option('general', 'mail-footer-left', '');
				$footer_right = tourmaster_get_option('general', 'mail-footer-right', '');

				echo '<div class="tourmaster-mail-footer" style="background: #ebedef; font-size: 13px; padding: 25px 30px 5px;" >';
				if( !empty($footer_left) ){
					echo '<div class="tourmaster-mail-footer-left" style="float: left; text-align: left;" >' . tourmaster_content_filter($footer_left) . '</div>';
				}
				if( !empty($footer_right) ){
					echo '<div class="tourmaster-mail-footer-right" style="float: right; text-align: right;" >' . tourmaster_content_filter($footer_right) . '</div>';
				}
				echo '<div style="display: block; clear: both; visibility: hidden; line-height: 0; height: 0; zoom: 1;" ></div>'; // clear
				echo '</div>';
			}
			echo '</div>';
			echo '</body></html>';

			$message = ob_get_contents();
			ob_end_clean();

			return $message;

		} // tourmaster_mail_content
	}
	
	if( !function_exists('tourmaster_mail_notification') ){
		function tourmaster_mail_notification( $type, $tid = '', $user_id = '', $settings = array() ){

			if( $type == 'custom' || $type == 'admin-custom' ){
				$option_enable = 'enable';
				$mail_title = empty($settings['title'])? '': $settings['title'];
				$raw_message = empty($settings['message'])? '': $settings['message'];
			}else{
				$option_enable = tourmaster_get_option('general', 'enable-' . $type, 'enable');
				$mail_title = tourmaster_get_option('general', $type . '-title');
				$raw_message = tourmaster_get_option('general', $type);
			}

			if( $option_enable == 'enable' ){

				if( !empty($tid) ){
					$result = tourmaster_get_booking_data(array('id' => $tid), array('single' => true));
					$contact_info = json_decode($result->contact_info, true);
					$payment_info = json_decode($result->payment_info, true);
				}else if( !empty($settings['result']) ){
					$result = $settings['result'];
					$contact_info = json_decode($result->contact_info, true);
					$payment_info = json_decode($result->payment_info, true);
				}

				if( !empty($result) ){

					// customer mail
					$user_email = $contact_info['email'];
					$raw_message = str_replace('{customer-email}', $user_email, $raw_message);

					// tour-name
					$tour_name  = '<h4 class="tourmaster-mail-tour-title" style="font-size: 16px; margin-bottom: 25px; font-weight: 600;" >';
					$tour_name .= '<a href="' . get_permalink($result->tour_id) . '" >';
					$tour_name .= get_the_title($result->tour_id);
					$tour_name .= '</a>';
					$tour_name .= '</h4>';
					$raw_message = str_replace('{tour-name}', $tour_name, $raw_message);

					// customer name
					$customer_name  = '<strong>' . $contact_info['first_name'] . ' ' . $contact_info['last_name'] . '</strong>';
					$raw_message = str_replace('{customer-name}', $customer_name, $raw_message);

					// additional notes
					if( !empty($contact_info['additional_notes']) ){
						$raw_message = str_replace('{customer-note}', $contact_info['additional_notes'], $raw_message);
					}else{
						$raw_message = str_replace('{customer-note}', '', $raw_message);
					}

					// payment method
					if( !empty($payment_info['payment_method']) ){
						$payment_method  = '<div class="tourmaster-mail-payment-info" style="font-weight: 600; margin-bottom: 5px;" >';
						$payment_method .= '<span class="tourmaster-head" >' . esc_html__('Payment Method :', 'tourmaster') . '</span> ';
						$payment_method .= '<span class="payment-method" >';
						if( $payment_info['payment_method'] == 'paypal' ){
							$payment_method .= esc_html__('Paypal', 'tourmaster');
						}else if( $payment_info['payment_method'] == 'receipt' ){
							$payment_method .= esc_html__('Receipt', 'tourmaster');
						}else{
							$payment_method .= esc_html__('Credit Card', 'tourmaster');
						}
						$payment_method .= '</span>';
						$payment_method .= '</div>';
						$raw_message = str_replace('{payment-method}', $payment_method, $raw_message);
					}else{
						$raw_message = str_replace('{payment-method}', '', $raw_message);
					}

					// transaction id
					$transaction_id = '';
					if( !empty($payment_info['transaction-id']) ){
						$transaction_id = $payment_info['transaction-id'];
					}else if( !empty($payment_info['transaction_id']) ){
						$transaction_id = $payment_info['transaction_id'];
					}
					if( !empty($transaction_id) ){
						$ptid  = '<div class="tourmaster-mail-payment-info" style="font-weight: 600; margin-bottom: 5px;" >';
						$ptid .= '<span class="tourmaster-head" >' . esc_html__('Transaction ID :', 'tourmaster') . '</span> ';
						$ptid .= '<span class="payment-method" >' . $transaction_id . '</span>';
						$ptid .= '</div>';
						$raw_message = str_replace('{transaction-id}', $ptid, $raw_message);
					}else{
						$raw_message = str_replace('{transaction-id}', '', $raw_message);
					}

					if( !empty($result->payment_date) ){
						$payment_date  = '<div class="tourmaster-mail-payment-info" style="font-weight: 600; margin-bottom: 5px;" >';
						$payment_date .= '<span class="tourmaster-head" >' . esc_html__('Payment Date :', 'tourmaster') . '</span> ';
						$payment_date .= '<span class="payment-method" >' . tourmaster_date_format($result->payment_date) . '</span>';
						$payment_date .= '</div>';
						$raw_message = str_replace('{payment-date}', $payment_date, $raw_message);
					}else{
						$raw_message = str_replace('{payment-date}', '', $raw_message);
					}

					if( !empty($result->total_price) ){
						$total_price  = '<div class="tourmaster-mail-payment-price" style="font-size: 16px; font-weight: 600; margin: 20px 0px 25px;" >';
						$total_price .= '<span class="tourmaster-head" >' . esc_html__('Total Price :', 'tourmaster') . '</span> ';
						$total_price .= '<span class="payment-method" >' . tourmaster_money_format($result->total_price) . '</span>';
						$total_price .= '</div>';
						$raw_message = str_replace('{total-price}', $total_price, $raw_message);
					}else{
						$raw_message = str_replace('{total-price}', '', $raw_message);
					}

					// order number
					$order_number  = '<div class="tourmaster-mail-order-info" style="font-style: italic; margin-bottom: 5px;" >';
					$order_number .= '<span class="tourmaster-head" >' . esc_html__('Order Number :', 'tourmaster') . '</span> ';
					$order_number .= '<span class="tourmaster-tail" >#' . $result->id . '</span>';
					$order_number .= '</div>';
					$raw_message = str_replace('{order-number}', $order_number, $raw_message);

					// travel date
					$travel_date  = '<div class="tourmaster-mail-order-info" style="font-style: italic; margin-bottom: 5px;" >';
					$travel_date .= '<span class="tourmaster-head" >' . esc_html__('Travel Date :', 'tourmaster') . '</span> ';
					$travel_date .= '<span class="tourmaster-tail" >' . tourmaster_date_format($result->travel_date) . '</span>';
					$travel_date .= '</div>';
					$raw_message = str_replace('{travel-date}', $travel_date, $raw_message);

					// admin transaction url
					$raw_message = str_replace('{admin-transaction-link}', admin_url('admin.php?page=tourmaster_order&single=' . $result->id), $raw_message);
					
					// invoice url
					$user_url = tourmaster_get_template_url('user');
					$invoice_url = add_query_arg(array(
						'page_type' => 'invoices',
						'sub_page' => 'single',
						'id' => $result->id,
						'tour_id' => $result->tour_id
					), $user_url);
					$raw_message = str_replace('{invoice-link}', $invoice_url, $raw_message);				

					// payment url
					$user_url = tourmaster_get_template_url('user');
					$invoice_url = add_query_arg(array(
						'page_type' => 'my-booking',
						'sub_page' => 'single',
						'id' => $result->id,
						'tour_id' => $result->tour_id
					), $user_url);
					$raw_message = str_replace('{payment-link}', $invoice_url, $raw_message);

				}else if( !empty($user_id) ){

					$customer_name  = '<strong>' . tourmaster_get_user_meta($user_id) . '</strong>';
					$raw_message = str_replace('{customer-name}', $customer_name, $raw_message);

					$user_email = tourmaster_get_user_meta($user_id, 'email');
					$raw_message = str_replace('{customer-email}', $user_email, $raw_message);
				}

				// profile page url
				$raw_message = str_replace('{profile-page-link}', tourmaster_get_template_url('user'), $raw_message);
				
				// html
				$raw_message = str_replace('{header}', '<h3 style="font-size: 17px; margin-bottom: 25px; font-weight: 600; margin-top: 0px; color: #515355" >', $raw_message);
				$raw_message = str_replace('{/header}', '</h3>', $raw_message);
				$raw_message = str_replace('{spaces}', '<div class="tourmaster-mail-spaces" style="margin-bottom: 25px;" ></div>', $raw_message);
				$raw_message = str_replace('{divider}', '<div class="tourmaster-mail-divider" style="border-bottom-width: 1px; border-bottom-style: solid; margin-bottom: 30px; margin-top: 30px; border-color: #d7d7d7;" ></div>', $raw_message);

				$message = tourmaster_mail_content($raw_message);
				
				// send the mail
				$mail_settings = array(
					'title' => $mail_title,
					'message' => $message
				);
				
				if( strpos($type, 'admin') === 0 ){
					$mail_settings['recipient'] = tourmaster_get_option('general', 'admin-email-address');
					$mail_settings['reply-to'] = $user_email;
				}else if( !empty($user_email) ){
					$mail_settings['recipient'] = $user_email;
				}

				if( !empty($mail_settings['recipient']) ){
					tourmaster_mail($mail_settings);
				}
			}

		} // tourmaster_mail_notification
	}
	
	// group message
	add_action('wp_ajax_tourmaster_submit_group_message', 'tourmaster_ajax_submit_group_message');
	if( !function_exists('tourmaster_ajax_submit_group_message') ){
		function tourmaster_ajax_submit_group_message(){

			$data = tourmaster_process_post_data($_POST);

			$ret = array('data'=>$data);

			if( empty($data['group-message-date']) ){
				$ret['status'] = 'failed';
				$ret['message'] = esc_html__('Please select the date which you want to retrieve the data.', 'tourmaster');
			}else if( empty($data['group-message-mail-subject']) ){
				$ret['status'] = 'failed';
				$ret['message'] = esc_html__('Please fill in the email title.', 'tourmaster');
			}else if( empty($data['group-message-mail-message']) ){
				$ret['status'] = 'failed';
				$ret['message'] = esc_html__('Please fill in the email message.', 'tourmaster');
			}else{

				// tour id
				$results = tourmaster_get_booking_data(array(
					'tour_id' => $data['post_id'],
					'travel_date' => $data['group-message-date'],
					'order_status' => array('custom' => " IN ('approved', 'online-paid', 'deposit-paid') ")
				));

				if( !empty($results) ){

					if( !empty($data['enable-group-message-admin-copy']) && $data['enable-group-message-admin-copy'] == 'enable' ){
						$admin_copy = true;
					}else{
						$admin_copy = false;
					}
					
					foreach( $results as $result ){
						tourmaster_mail_notification('custom', '', '', array(
							'title' => $data['group-message-mail-subject'],
							'message' => $data['group-message-mail-message'],
							'result' => $result
						));

						if( $admin_copy ){							
							tourmaster_mail_notification('admin-custom', '', '', array(
								'title' => $data['group-message-mail-subject'],
								'message' => $data['group-message-mail-message'],
								'result' => $result
							));

							$admin_copy = false;
						}
					}

					$ret['status'] = 'success';
					$ret['message'] = sprintf(esc_html__('The E-mail has been sent successfully to %d customers.', 'tourmaster'), sizeof($results));
				
				}else{
					$ret['status'] = 'failed';
					$ret['message'] = esc_html__('Sorry, we couldn\'t find any customer on the selected date, please try again with different dates.', 'tourmaster');
				}

			}		

			die(json_encode($ret));

		} // tourmaster_ajax_submit_group_message
	}

	// auto mail
	add_action('tourmaster_schedule_daily', 'tourmaster_daily_mail_reminder');
	if( !function_exists('tourmaster_daily_mail_reminder') ){
		function tourmaster_daily_mail_reminder(){

			global $wpdb;

			$sql  = "SELECT post_id, meta_value FROM {$wpdb->postmeta} ";
		    $sql .= "WHERE meta_key = 'tourmaster-reminder-message' ";
		    $sql .= "AND meta_value = 'enable' ";
		    $results = $wpdb->get_results($sql);

		    foreach( $results as $result ){

		    	$tour_option = tourmaster_get_post_meta($result->post_id, 'tourmaster-tour-option');

		    	$current_date = strtotime(current_time('mysql'));
		    	$days_before_travel = intval($tour_option['reminder-message-days-before-travel']);
		    	$travel_date = date('Y-m-d', ($current_date + ($days_before_travel * 86400)));

		    	$results = tourmaster_get_booking_data(array(
					'tour_id' => $result->post_id,
					'travel_date' => $travel_date,
					'order_status' => array('custom' => " IN ('approved', 'online-paid', 'deposit-paid') ")
				));

				if( !empty($results) ){

					if( !empty($tour_option['enable-reminder-message-admin-copy']) && $tour_option['enable-reminder-message-admin-copy'] == 'enable' ){
						$admin_copy = true;
					}else{
						$admin_copy = false;
					}
					
					foreach( $results as $result ){ 
						tourmaster_mail_notification('custom', '', '', array(
							'title' => $tour_option['reminder-message-mail-subject'],
							'message' => $tour_option['reminder-message-mail-message'],
							'result' => $result
						));

						if( $admin_copy ){							
							tourmaster_mail_notification('admin-custom', '', '', array(
								'title' => $tour_option['reminder-message-mail-subject'],
								'message' => $tour_option['reminder-message-mail-message'],
								'result' => $result
							));

							$admin_copy = false;
						}
					}

				} // if( !empty($results) ){

		    }

		} // tourmaster_mail_reminder
	}