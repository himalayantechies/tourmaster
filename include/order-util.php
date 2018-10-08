<?php
	/*	
	*	Ordering Page
	*/

	if( !function_exists('tourmaster_order_edit_text') ){
		function tourmaster_order_edit_text($tmlb = ''){
			return '<a class="tourmaster-order-edit-text" href="#" data-tmlb="' . esc_attr($tmlb) . '" >' . esc_html__('Edit', 'tourmaster') . '<i class="fa fa-edit" ></i></a>';
		}
	}

	if( !function_exists('tourmaster_order_edit_form') ){
		function tourmaster_order_new_form( $booking_detail ){

			$ret  = '';
			$tour_id = empty($booking_detail['tour-id'])? '': $booking_detail['tour-id'];
			$ret .= tourmaster_get_form_field(array(
				'title' => esc_html__('Select Tour :', 'tourmaster'),
				'echo' => false,
				'slug' => 'tour_id',
				'type' => 'combobox',
				'options' => tourmaster_get_post_list('tour', true)
			), 'order-edit', $tour_id);
			if( empty($tour_id) ) return $ret;

			$tour_date = empty($booking_detail['tour-date'])? '': $booking_detail['tour-date'];
			$available_dates = get_post_meta($tour_id, 'tourmaster-tour-date-avail', true);
			if( empty($available_dates) ){
				$ret .= esc_html__('There\'re no tour available.', 'tourmaster');
			}else{
				$available_dates = explode(',', $available_dates);
				$ret .= '<div class="tourmaster-tour-booking-date clearfix" data-step="1" >';
				$ret .= '<i class="fa fa-calendar" ></i>';

				$ret .= '<div class="tourmaster-tour-booking-date-input" >';
				$ret .= '<div class="tourmaster-combobox-wrap tourmaster-tour-date-combobox" >';
				$ret .= '<select name="tour-date" >';
				foreach( $available_dates as $available_date ){
					$ret .= '<option value="' . esc_attr($available_date) . '" ' . ($tour_date == $available_date? 'selected': '') . ' >';
					$ret .= tourmaster_date_format($available_date);
					$ret .= '</option>';
				}
				$ret .= '</select>';
				$ret .= '</div>';
				$ret .= '</div>';

				$ret .= '</div>'; // tourmaster-tour-booking-date
			}
			if( empty($tour_date) ) return $ret;

			$ret .= tourmaster_get_tour_booking_fields($booking_detail, $booking_detail);
			// $ret .= gdlr_core_debug_object($booking_detail);

			return $ret;
		}
	}

	if( !function_exists('tourmaster_order_edit_form') ){
		function tourmaster_order_edit_form($tid, $type = '', $result){
			$ret  = '<form class="tourmaster-order-edit-form tourmaster-type-' . esc_attr($type) . '" action="" method="post" data-ajax-url="' . esc_attr(TOURMASTER_AJAX_URL) . '" >';

			if( $type == 'new_order' ){
				
				$booking_detail = empty($result)? array(): json_decode($result->booking_detail, true);
				$ret .= tourmaster_order_new_form($booking_detail);

			}else if( $type == 'additional_notes' ){

				$contact_detail = empty($result->contact_info)? array(): json_decode($result->contact_info, true);
				$value = empty($contact_detail['additional_notes'])? '': $contact_detail['additional_notes'];
				$ret .= tourmaster_get_form_field(array(
					'title' => esc_html__('Additional Notes :', 'tourmaster'),
					'echo' => false,
					'slug' => 'additional_notes',
					'type' => 'textarea'
				), 'order-edit', $value);

			}else if( $type == 'contact_details' || $type == 'billing_details' ){

				if( $type == 'contact_details' ){
					$values = empty($result->contact_info)? array(): json_decode($result->contact_info, true);
				}else if( $type == 'billing_details' ){
					$values = empty($result->billing_info)? array(): json_decode($result->billing_info, true);
				}

				$form_fields = tourmaster_get_payment_contact_form_fields();
				foreach( $form_fields as $field_slug => $field ){
					$value = empty($values[$field_slug])? '': $values[$field_slug];
					$ret .= tourmaster_get_form_field(array(
						'title' => $field['title'],
						'echo' => false,
						'slug' => $field_slug,
						'type' => $field['type'],
						'options' => empty($field['options'])? array(): $field['options'],
						'required' => empty($field['required'])? false: true,
					), 'order-edit', $value);
				}

			}else if( $type == 'price' ){

				$people_types = array(
					'traveller' => esc_html__('Traveller', 'tourmaster'),
					'adult' => esc_html__('Adult', 'tourmaster'),
					'male' => esc_html__('Male', 'tourmaster'),
					'female' => esc_html__('Female', 'tourmaster'),
					'children' => esc_html__('Children', 'tourmaster'),
					'student' => esc_html__('Student', 'tourmaster'),
					'infant' => esc_html__('Infant', 'tourmaster'),
				);
				$pricing_info = json_decode($result->pricing_info, true);

				// base price
				foreach( $people_types as $people_slug => $people_type ){
					if( isset($pricing_info['price-breakdown'][$people_slug . '-base-price']) ){
						$ret .= tourmaster_get_form_field(array(
							'title' => sprintf(esc_html__('%s Base Price', 'tourmaster'), $people_type),
							'echo' => false,
							'slug' => $people_slug . '-base-price',
							'type' => 'price-edit',
							'description' => esc_html__('Price per person (Fill only number).', 'tourmaster')
						), 'order-edit', $pricing_info['price-breakdown'][$people_slug . '-base-price']);					
					}
				}

				// group price
				if( isset($pricing_info['price-breakdown']['group-price']) ){
					$ret .= tourmaster_get_form_field(array(
						'title' => esc_html__('Group Price', 'tourmaster'),
						'echo' => false,
						'slug' => 'group-price',
						'type' => 'price-edit',
						'description' => esc_html__('Fill only number.', 'tourmaster')
					), 'order-edit', $pricing_info['price-breakdown']['group-price']);					
				}

				// room
				$count = 0;
				if( !empty($pricing_info['price-breakdown']['room']) ){
					foreach( $pricing_info['price-breakdown']['room'] as $room ){
						$ret .= '<h3 class="tourmaster-order-edit-title" >' . sprintf(esc_html__('Room %s', 'tourmaster'), $count + 1) . '</h3>';
					
						$ret .= tourmaster_get_form_field(array(
							'title' => esc_html__('Room Base Price', 'tourmaster'),
							'echo' => false,
							'slug' => 'room-base-price' . $count,
							'type' => 'price-edit',
							'description' => esc_html__('Price per person (Fill only number).', 'tourmaster')
						), 'order-edit', $room['base-price']);

						foreach( $people_types as $people_slug => $people_type ){
							if( isset($room['additional-' . $people_slug . '-price']) ){
								$ret .= tourmaster_get_form_field(array(
									'title' => sprintf(esc_html__('Additional %s Price', 'tourmaster'), $people_type),
									'echo' => false,
									'slug' => 'additional-' . $people_slug . '-price' . $count,
									'type' => 'price-edit',
									'description' => esc_html__('Price per person (Fill only number).', 'tourmaster')
								), 'order-edit', $room['additional-' . $people_slug . '-price']);
							}
						}

						$count++;
					}
				}

				// additional service
				if( !empty($pricing_info['price-breakdown']['additional-service']) ){
					$pers = array(
						'person' => esc_html__('Person', 'tourmaster'),
						'group' => esc_html__('Group', 'tourmaster'),
						'room' => esc_html__('Room', 'tourmaster'),
						'unit' => esc_html__('Unit', 'tourmaster'),
					);
					$ret .= '<h3 class="tourmaster-order-edit-title" >' . esc_html__('Additional Service', 'tourmaster'). '</h3>';

					foreach( $pricing_info['price-breakdown']['additional-service'] as $service_id => $service ){
						$ret .= tourmaster_get_form_field(array(
							'title' => get_the_title($service_id),
							'echo' => false,
							'slug' => 'additional-service',
							'type' => 'price-edit',
							'data-type' => 'array',
							'description' => sprintf(esc_html__('Price per %s (Fill only number).', 'tourmaster'), $pers[$service['per']])
						), 'order-edit', $service['price-one']);
					} 
				}

				// group discount
				if( !empty($pricing_info['price-breakdown']['group-discount-traveller']) ){
					$ret .= '<h3 class="tourmaster-order-edit-title" >' . esc_html__('Group Discount', 'tourmaster'). '</h3>';
					$group_discount = '';
					if( !empty($pricing_info['price-breakdown']['group-discount-rate']) ){
						$group_discount = $pricing_info['price-breakdown']['group-discount-rate'];
					}

					$ret .= tourmaster_get_form_field(array(
						'title' => esc_html__('Group Discount', 'tourmaster'),
						'echo' => false,
						'slug' => 'group-discount',
						'type' => 'text'
					), 'order-edit', $group_discount);
				}

				// coupon
				$ret .= '<h3 class="tourmaster-order-edit-title" >' . esc_html__('Discount', 'tourmaster'). '</h3>';
				$coupon_code = empty($pricing_info['price-breakdown']['coupon-code'])? '': $pricing_info['price-breakdown']['coupon-code'];
				$coupon_text = '';
				if( empty($pricing_info['price-breakdown']['coupon-text']) ){
					if( !empty($pricing_info['price-breakdown']['coupon-amount']) ){
						$coupon_text = $pricing_info['price-breakdown']['coupon-amount'];
					}
				}else{
					$coupon_text = $pricing_info['price-breakdown']['coupon-text'];
				}
				$ret .= tourmaster_get_form_field(array(
					'title' => esc_html__('Coupon Code', 'tourmaster'),
					'echo' => false,
					'slug' => 'coupon-code',
					'type' => 'text'
				), 'order-edit', $coupon_code);
				$ret .= tourmaster_get_form_field(array(
					'title' => esc_html__('Coupon Discount Amount', 'tourmaster'),
					'echo' => false,
					'slug' => 'coupon-text',
					'type' => 'price-edit',
					'description' => esc_html__('With % or just number for fixed amount.', 'tourmaster')
				), 'order-edit', $coupon_text);

			} // price

			$ret .= '<div class="tourmaster-order-edit-form-load" >' . esc_html__('Now loading', 'tourmaster') . '</div>';
			$ret .= '<div class="tourmaster-order-edit-form-error" >' . esc_html__('An error occurs, please check console for more information', 'tourmaster') . '</div>';
			$ret .= '<input type="hidden" name="tid" value="' . esc_attr($tid) . '" />';
			$ret .= '<input type="hidden" name="type" value="' . esc_attr($type) . '" />';
			$ret .= '<input type="hidden" name="action" value="tourmaster_order_edit" />';
			
			if( $type != 'new_order' ){
				$ret .= '<input type="submit" class="tourmaster-order-edit-submit" value="' . esc_attr__('Submit', 'tourmaster') . '" />';
			}

			$ret .= '</form>';

			return $ret;
		}
	}

	add_action('wp_ajax_tourmaster_order_edit', 'tourmaster_order_edit');
	if( !function_exists('tourmaster_order_edit') ){
		function tourmaster_order_edit(){
			$data = tourmaster_process_post_data($_POST);
			
			// additional notes
			if( $data['type'] == 'additional_notes' ){
				
				if( !empty($data['additional_notes']) ){
					$result = tourmaster_get_booking_data(array('id' => $data['tid']), array('single' => true));
					$contact_detail = empty($result->contact_info)? array(): json_decode($result->contact_info, true);
					$contact_detail['additional_notes'] = $data['additional_notes'];

					tourmaster_update_booking_data(
						array('contact_info' => json_encode($contact_detail)), 
						array('id' => $data['tid']), 
						array('%s'), 
						array('%d')
					);

					die(json_encode(array('status' => 'success')));
				}else{
					die(json_encode(array('status' => 'failed', 'message' => esc_html__('Please fill the additional notes.', 'tourmaster'))));
				}

			// contact & billing details
			}else if( $data['type'] == 'contact_details' || $data['type'] == 'billing_details' ){

				$result = tourmaster_get_booking_data(array('id' => $data['tid']), array('single' => true));
				if( $data['type'] == 'contact_details' ){
					$updated_field = 'contact_info';
					$values = empty($result->contact_info)? array(): json_decode($result->contact_info, true);
				}else if( $data['type'] == 'billing_details' ){
					$updated_field = 'billing_info';
					$values = empty($result->billing_info)? array(): json_decode($result->billing_info, true);
				}

				$form_fields = tourmaster_get_payment_contact_form_fields();
				
				foreach( $form_fields as $field_slug => $field ){
					if( !empty($data[$field_slug]) ){
						$values[$field_slug] = $data[$field_slug];
					}

					// validate
					if( !empty($field['required']) && empty($data[$field_slug]) ){
						die(json_encode(array('status' => 'failed', 'message' => esc_html__('Please fill all required fields.', 'tourmaster'))));
					}
					if( $field['type'] == 'email' && !empty($data[$field_slug]) ){
						if( !is_email($data[$field_slug]) ){
							die(json_encode(array('status' => 'failed', 'message' => esc_html__('An E-mail is incorrect.', 'tourmaster'))));
						}
					}
				}

				tourmaster_update_booking_data(
					array($updated_field => json_encode($values)), 
					array('id' => $data['tid']), 
					array('%s'), 
					array('%d')
				);

				die(json_encode(array('status' => 'success')));

			// price
			}else if( $data['type'] == 'price' ){

				$result = tourmaster_get_booking_data(array('id' => $data['tid']), array('single' => true));
				$people_types = array(
					'traveller' => esc_html__('Traveller', 'tourmaster'),
					'adult' => esc_html__('Adult', 'tourmaster'),
					'male' => esc_html__('Male', 'tourmaster'),
					'female' => esc_html__('Female', 'tourmaster'),
					'children' => esc_html__('Children', 'tourmaster'),
					'student' => esc_html__('Student', 'tourmaster'),
					'infant' => esc_html__('Infant', 'tourmaster'),
				);
				$pricing_info = json_decode($result->pricing_info, true);
				$price_breakdown = $pricing_info['price-breakdown'];
				$price_breakdown['sub-total-price'] = 0;

				// base price
				foreach( $people_types as $people_slug => $people_type ){
					if( isset($price_breakdown[$people_slug . '-base-price']) && isset($data[$people_slug . '-base-price']) ){
						$price_breakdown[$people_slug . '-base-price'] = $data[$people_slug . '-base-price'];
					}
					if( !empty($price_breakdown[$people_slug . '-base-price']) && !empty($price_breakdown[$people_slug . '-base-price']) ){
						$price_breakdown['sub-total-price'] += $price_breakdown[$people_slug . '-base-price'] * $price_breakdown[$people_slug . '-amount'];
					}
				}

				// group price
				if( isset($price_breakdown['group-price']) && isset($data['group-price']) ){
					$price_breakdown['group-price'] = $data['group-price'];
				}
				if( !empty($price_breakdown['group-price']) ){
					$price_breakdown['sub-total-price'] += $price_breakdown['group-price'];
				}

				// room
				if( !empty($price_breakdown['room']) ){
					$count = 0;
					foreach( $price_breakdown['room'] as $room ){
						
						if( isset($room['base-price']) && isset($data['room-base-price' . $count]) ){
							$room['base-price'] = $data['room-base-price' . $count];
						}
						$price_breakdown['sub-total-price'] += $room['base-price'];

						foreach( $people_types as $people_slug => $people_type ){

							if( isset($room['additional-' . $people_slug . '-price']) && isset($data['additional-' . $people_slug . '-price' . $count]) ){
								$room['additional-' . $people_slug . '-price'] = $data['additional-' . $people_slug . '-price' . $count];
							}
							$price_breakdown['sub-total-price'] += $room['additional-' . $people_slug . '-price'] * $room['additional-' . $people_slug . '-amount'];
						}

						$price_breakdown['room'][$count] = $room;
						$count++;
					}
				}

				// additional service
				if( !empty($price_breakdown['additional-service']) ){
					$count = 0;
					foreach( $price_breakdown['additional-service'] as $service_id => $service ){

						if( isset($data['additional-service'][$count]) ){
							$service['price-one'] = $data['additional-service'][$count];
							$service['price'] = $service['price-one'] * $service['amount'];
						}
						
						if( !empty($service['price']) ){
							$price_breakdown['sub-total-price'] += $service['price'];
						}
						
						$price_breakdown['additional-service'][$service_id] = $service;
						$count++;
					} 
				}

				$pricing_info['total-price'] = $price_breakdown['sub-total-price'];

				// group discount
				if( isset($data['group-discount']) ){
					$price_breakdown['group-discount-rate'] = $data['group-discount'];
					if( strpos($data['group-discount'], '%') === false ){
						$pricing_info['total-price'] -= floatval($data['group-discount']);
					}else{
						$pricing_info['total-price'] = ($pricing_info['total-price'] * (100 - floatval($data['group-discount']))) / 100;
					}

					$price_breakdown['group-discounted-price'] = $pricing_info['total-price'];
				}

				// coupon
				$coupon_code = '';
				if( isset($data['coupon-code']) ){
					$price_breakdown['coupon-code'] = $data['coupon-code'];
					$coupon_code = $data['coupon-code'];
				}
				if( isset($data['coupon-text']) ){
					if( strpos($data['coupon-text'], '%') === false ){
						$price_breakdown['coupon-text'] = '';
						$price_breakdown['coupon-amount'] = floatval($data['coupon-text']);
					}else{
						$price_breakdown['coupon-text'] = $data['coupon-text'];
						$price_breakdown['coupon-amount'] = ($pricing_info['total-price'] * floatval($data['coupon-text'])) / 100;
					}

					$pricing_info['total-price'] -= $price_breakdown['coupon-amount'];
				}

				// tax
				$tax_rate = tourmaster_get_option('general', 'tax-rate', 0);
				if( !empty($tax_rate) ){
					$price_breakdown['tax-rate'] = $tax_rate;
					$price_breakdown['tax-due'] = ($pricing_info['total-price'] * $tax_rate) / 100;
					$pricing_info['total-price'] += $price_breakdown['tax-due'];
				}else{
					unset($price_breakdown['tax-rate']);
					unset($price_breakdown['tax-due']);
				}

				// update the data
				$pricing_info['price-breakdown'] = $price_breakdown;
				// die(json_encode(array('status' => 'failed', 'message' => gdlr_core_debug_object($pricing_info))));
				tourmaster_update_booking_data(
					array(
						'pricing_info' => json_encode($pricing_info), 
						'total_price' => $pricing_info['total-price'], 
						'coupon_code' => $coupon_code
					), 
					array('id' => $data['tid']), 
					array('%s', '%s', '%s'), 
					array('%d')
				);

				die(json_encode(array('status' => 'success')));

			} // end if
			
		}
	}