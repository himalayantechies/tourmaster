<?php
	/*	
	*	Tourmaster Plugin
	*	---------------------------------------------------------------------
	*	for tour post type
	*	---------------------------------------------------------------------
	*/

	// booking bar ajax action
	add_action('wp_ajax_tourmaster_tour_booking', 'tourmaster_ajax_tour_booking');
	add_action('wp_ajax_nopriv_tourmaster_tour_booking', 'tourmaster_ajax_tour_booking');
	if( !function_exists('tourmaster_ajax_tour_booking') ){
		function tourmaster_ajax_tour_booking(){

			$data = empty($_POST['data'])? array(): tourmaster_process_post_data($_POST['data']);

			$ret = array(
				'content' => tourmaster_get_tour_booking_fields(array(
					'tour-id' => empty($data['tour-id'])? '': $data['tour-id'],
					'tour-date' => empty($data['tour-date'])? '': $data['tour-date'],
					'step' => empty($data['step'])? '': $data['step'],
					'package' => empty($data['package'])? '': $data['package'],
				)),
			);

			die(json_encode($ret));
		} // tourmaster_ajax_tour_booking
	}	

	// check the max amount 
	add_action('wp_ajax_tourmaster_tour_booking_amount_check', 'tourmaster_tour_booking_amount_check');
	add_action('wp_ajax_nopriv_tourmaster_tour_booking_amount_check', 'tourmaster_tour_booking_amount_check');
	if( !function_exists('tourmaster_tour_booking_amount_check') ){
		function tourmaster_tour_booking_amount_check(){

			$ret = array();
			if( !empty($_POST['tour_id']) && !empty($_POST['traveller']) && !empty($_POST['tour_date']) ){

				$tour_option = tourmaster_get_post_meta($_POST['tour_id'], 'tourmaster-tour-option');
				$date_price = tourmaster_get_tour_date_price($tour_option, $_POST['tour_id'], $_POST['tour_date']);
				
				$is_old_data = empty($date_price['package'])? true: false;
				$date_price = tourmaster_get_tour_date_price_package($date_price, array(
					'package' => empty($_POST['package'])? '': tourmaster_process_post_data($_POST['package'])
				));

				// check if tour is still available for booking
				if( !empty($date_price['start-time']) ){
					$start_time = $date_price['start-time'];
				}else if( !empty($tour_option['start-time']) ){
					$start_time = $tour_option['start-time'];
				}else{
					$start_time = '24:00';
				}
				$offset = empty($tour_option['last-minute-booking'])? '': $tour_option['last-minute-booking'];
				$booking_time = strtotime($_POST['tour_date']) + tourmaster_time_offset($start_time, $offset);
				$current_time = strtotime(current_time('Y-m-d H:i'));
				if( $current_time > $booking_time ){
					die(json_encode(array(
						'status' => 'failed',
						'message' => esc_html__('Sorry, the tour is now off for booking on the date/time you selected. Please select another date.', 'tourmaster')
					)));
				}

				// check people amount
				if( $_POST['traveller'] == 'group' ){

					$current_amount = tourmaster_get_booking_data(array(
						'tour_id' => $_POST['tour_id'], 
						'travel_date' => $_POST['tour_date'],
						'package_group_slug' => empty($date_price['group-slug'])? '': $date_price['group-slug'],
						'order_status' => array(
							'condition' => '!=',
							'value' => 'cancel'
						)
					), array(), 'COUNT(*)');

					if( empty($date_price['max-group']) || $date_price['max-group'] > $current_amount ){
						die(json_encode(array(
							'status' => 'success'
						)));
					}else{
						die(json_encode(array(
							'status' => 'failed',
							'message' => esc_html__('Sorry, this tour is now full. Please select another date', 'tourmaster')
						)));
					}					

				}else{

					// check if max people per room exceed limit
					if( $tour_option['tour-type'] == 'multiple' && $date_price['pricing-room-base'] == 'enable' && !empty($date_price['max-people-per-room']) ){
						if( $_POST['max_traveller_per_room'] > $date_price['max-people-per-room'] ){
							die(json_encode(array(
								'status' => 'failed',
								'message' => sprintf(esc_html__('* You can select maximum %d persons per each room.', 'tourmaster'), $date_price['max-people-per-room'])
							)));
						}
					}

					// check if max people exceed booking amount
					if( $is_old_data ){
						$max_people = get_post_meta($_POST['tour_id'], 'tourmaster-max-people', true);
					}else{
						$max_people = empty($date_price['max-people'])? '': $date_price['max-people'];
					}
					$query = tourmaster_get_booking_data(array(
						'tour_id' => $_POST['tour_id'], 
						'travel_date' => $_POST['tour_date'],
						'package_group_slug' => empty($date_price['group-slug'])? '': $date_price['group-slug'],
						'order_status' => array(
							'condition' => '!=',
							'value' => 'cancel'
						)
					), array('single' => true), 'SUM(traveller_amount) AS traveller_amount, SUM(male_amount) AS male_amount, SUM(female_amount) AS female_amount');

					if( !empty($max_people) && $query->traveller_amount + $_POST['traveller'] > $max_people ){
						die(json_encode(array(
							'status' => 'failed',
							'message' => esc_html__('Sorry, this tour is now full. Please try to select another date', 'tourmaster')
						)));
					}else{
						if( !empty($date_price['minimum-people-per-booking']) ){
							$min_people = $date_price['minimum-people-per-booking'];
						}else{
							$min_people = get_post_meta($_POST['tour_id'], 'tourmaster-min-people-per-booking', true);
						}
						$max_people = get_post_meta($_POST['tour_id'], 'tourmaster-max-people-per-booking', true);
						$require_adult = tourmaster_get_option('general', 'require-adult-to-book-tour', 'disable');

						if( !empty($min_people) && $min_people > $_POST['traveller'] ){
							die(json_encode(array(
								'status' => 'failed',
								'date' => $date_price,
								'message' => sprintf(esc_html__('At least %d people is required to book this tour', 'tourmaster'), $min_people)
							)));
						}else if( !empty($max_people) && $max_people < $_POST['traveller'] ){
							die(json_encode(array(
								'status' => 'failed',
								'message' => sprintf(esc_html__('You can book at most %d people per booking', 'tourmaster'), $max_people)
							)));
						}else if( $require_adult == 'enable' && empty($_POST['adult_amount']) ){
							die(json_encode(array(
								'status' => 'failed',
								'message' => esc_html__('At least 1 adult is required to book the tour', 'tourmaster')
							)));
						}


						// check for same gender package
						if( !empty($date_price['same-gender']) && $date_price['same-gender'] == 'enable' ){
							$male_amount = $query->male_amount;
							$female_amount = $query->female_amount;
							
							if( !empty($male_amount) && empty($female_amount) ){
								if( !empty($_POST['female_amount']) ){
									die(json_encode(array(
										'status' => 'failed',
										'message' => esc_html__('This package is only available for male', 'tourmaster')
									)));
								}
							}else if( empty($male_amount) && !empty($female_amount) ){
								if( !empty($_POST['male_amount']) ){
									die(json_encode(array(
										'status' => 'failed',
										'message' => esc_html__('This package is only available for female', 'tourmaster')
									)));
								}
							}
						}

						die(json_encode(array(
							'status' => 'success'
						)));
	
					}
				}
			}else{
				die(json_encode(array(
					'status' => 'failed',
					'message' => esc_html__('An error occurs, please refresh the page to try again.', 'tourmaster')
				)));
			}

		} // tourmaster_ajax_tour_booking
	}


	if( !function_exists('tourmaster_get_tour_booking_fields') ){
		function tourmaster_get_tour_booking_fields( $settings = array(), $value = array() ){

			if( !empty($value['package']) ){
				$settings['package'] = $value['package'];
			}

			$ret = '';
			$tour_option = tourmaster_get_post_meta($settings['tour-id'], 'tourmaster-tour-option');
			$date_price = tourmaster_get_tour_date_price($tour_option, $settings['tour-id'], $settings['tour-date']);

			if( empty($date_price) ){
				return false;
			}

			// available number for old data
			$remaining_seat = tourmaster_get_option('general', 'show-remaining-available-number', 'disable');
			if( $remaining_seat == 'enable' && empty($date_price['package']) ){
				$max_people = get_post_meta($settings['tour-id'], 'tourmaster-max-people', true);

				if( !empty($max_people) ){ 
					$current_amount = tourmaster_get_booking_data(array(
						'tour_id' => $settings['tour-id'], 
						'travel_date' => $settings['tour-date'],
						'package_group_slug' => '',
						'order_status' => array(
							'condition' => '!=',
							'value' => 'cancel'
						)
					), array(), 'SUM(traveller_amount)');

					$ret .= '<div class="tourmaster-tour-booking-available" data-step="2" >';
					$ret .= sprintf(esc_html__('Available: %d seats', 'tourmaster'), ($max_people - $current_amount));
					$ret .= '</div>';
				}
			}

			// select package here
			if( !empty($date_price['package']) && $settings['step'] == 1 ){
				if( sizeof($date_price['package']) > 1 ){

					if( empty($settings['package']) ){
						$select_package_text = empty($date_price['select-package-text'])? esc_html__('Select a package', 'tourmaster'): $date_price['select-package-text'];
					}else{
						$select_package_text = $settings['package'];
					}

					$ret .= '<div class="tourmaster-tour-booking-package" data-step="2" >';
					$ret .= '<div class="tourmaster-tour-booking-next-sign" ><span></span></div>';
					$ret .= '<i class="icon_check" ></i>';
					$ret .= '<div class="tourmaster-combobox-list-wrap" >';
					$ret .= '<div class="tourmaster-combobox-list-display" ><span>' . $select_package_text . '</span></div>';
					$ret .= '<input type="hidden" name="package" value="' . esc_attr(empty($settings['package'])? '': $settings['package']) . '" />';
					$ret .= '<ul>';
					foreach($date_price['package'] as $package){
						$package['title'] = empty($package['title'])? '': $package['title'];

						$ret .= '<li data-value="' . esc_attr($package['title']) . '" class="';
						$ret .= (!empty($settings['package']) && $settings['package'] == $package['title'])? 'tourmaster-active': '';
						$ret .= '" >';
						if( !empty($package['title']) ){
							$ret .= '<span class="tourmaster-combobox-list-title" >' . $package['title'] . '</span>';	
						} 
						if( !empty($package['caption']) ){
							$ret .= '<span class="tourmaster-combobox-list-caption" >' . $package['caption'] . '</span>';	
						} 
						if( !empty($package['start-time']) ){
							$ret .= '<span class="tourmaster-combobox-list-time" >';
							$ret .= esc_html__('Start Time:', 'tourmaster') . ' ';
							$ret .= $package['start-time'];
							$ret .= '</span>';	
						}

						// show available seat
						if( $remaining_seat == 'enable' ){
							if( $date_price['pricing-method'] == 'group' ){
								if( !empty($package['max-group']) ){
									$current_amount = tourmaster_get_booking_data(array(
										'tour_id' => $settings['tour-id'], 
										'travel_date' => $settings['tour-date'],
										'package_group_slug' => empty($package['group-slug'])? '': $package['group-slug'],
										'order_status' => array(
											'condition' => '!=',
											'value' => 'cancel'
										)
									), array(), 'COUNT(*)');

									$ret .= '<span class="tourmaster-combobox-list-avail" >';
									$ret .= sprintf(esc_html__('Available: %d groups', 'tourmaster'), ($package['max-group'] - $current_amount));
									$ret .= '</span>';
								}
							}else{
								if( !empty($package['max-people']) ){
									$current_amount = tourmaster_get_booking_data(array(
										'tour_id' => $settings['tour-id'], 
										'travel_date' => $settings['tour-date'],
										'package_group_slug' => empty($package['group-slug'])? '': $package['group-slug'],
										'order_status' => array(
											'condition' => '!=',
											'value' => 'cancel'
										)
									), array(), 'SUM(traveller_amount)');

									$ret .= '<span class="tourmaster-combobox-list-avail" >';
									$ret .= sprintf(esc_html__('Available: %d seats', 'tourmaster'), ($package['max-people'] - $current_amount));
									$ret .= '</span>';
								}
							}
						}

						$ret .= '</li>';
					}
					$ret .= '</ul>';
					$ret .= '</div>';
					$ret .= '</div>';

					// if come from ajax
					if( empty($value['package']) ){
						return $ret;
					}

				// showing availalbe number when there're only 1 package
				}else{

					$package = $date_price['package'][0];

					if( $remaining_seat == 'enable' ){
						if( $date_price['pricing-method'] == 'group' ){
							if( !empty($package['max-group']) ){
								$current_amount = tourmaster_get_booking_data(array(
									'tour_id' => $settings['tour-id'], 
									'travel_date' => $settings['tour-date'],
									'package_group_slug' => empty($package['group-slug'])? '': $package['group-slug'],
									'order_status' => array(
										'condition' => '!=',
										'value' => 'cancel'
									)
								), array(), 'COUNT(*)');
								
								$ret .= '<div class="tourmaster-tour-booking-available" data-step="2" >';
								$ret .= sprintf(esc_html__('Available: %d groups', 'tourmaster'), ($package['max-group'] - $current_amount));
								$ret .= '</div>';
							}
						}else{
							if( !empty($package['max-people']) ){
								$current_amount = tourmaster_get_booking_data(array(
									'tour_id' => $settings['tour-id'], 
									'travel_date' => $settings['tour-date'],
									'package_group_slug' => empty($package['group-slug'])? '': $package['group-slug'],
									'order_status' => array(
										'condition' => '!=',
										'value' => 'cancel'
									)
								), array(), 'SUM(traveller_amount)');

								$ret .= '<div class="tourmaster-tour-booking-available" data-step="2" >';
								$ret .= sprintf(esc_html__('Available: %d seats', 'tourmaster'), ($package['max-people'] - $current_amount));
								$ret .= '</div>';
							}
						}
					}
				}
			}

			$date_price = tourmaster_get_tour_date_price_package($date_price, array(
				'package' => empty($settings['package'])? '': $settings['package']
			));

			// group price
			if( $date_price['pricing-method'] == 'group' ){

				$ret .= '<div class="tourmaster-tour-booking-group clearfix" data-step="4" >';
				$ret .= '<input type="hidden" name="group" value="1" />';
				$ret .= '</div>';

			// no room based			
			}else if( $tour_option['tour-type'] == 'single' || $date_price['pricing-room-base'] == 'disable' ){
				
				$max_people_per_booking = get_post_meta($settings['tour-id'], 'tourmaster-max-people-per-booking', true);

				// fixed price
				if( $date_price['pricing-method'] == 'fixed' ){
					$ret .= '<div class="tourmaster-tour-booking-people clearfix" data-step="4" >';
					$ret .= '<div class="tourmaster-tour-booking-next-sign" ><span></span></div>';
					$ret .= '<i class="fa fa-users" ></i>';
					$ret .= '<div class="tourmaster-tour-booking-people-input" >';
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-people',
						'default' => empty($value['tour-people'])? '': $value['tour-people'],
						'placeholder' => esc_html__('Number Of People', 'tourmaster'),
						'max-num' => $max_people_per_booking
					));
					$ret .= '</div>';
					$ret .= '</div>';

				// variable price	
				}else{
					$ret .= '<div class="tourmaster-tour-booking-people tourmaster-variable clearfix" data-step="4" >';
					$ret .= '<div class="tourmaster-tour-booking-next-sign" ><span></span></div>';
					$ret .= '<i class="fa fa-users" ></i>';
					$ret .= '<div class="tourmaster-tour-booking-people-input tourmaster-variable clearfix" >';
					if( !empty($date_price['adult-price']) || (isset($date_price['adult-price']) && $date_price['adult-price'] === '0') ){
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-adult',
							'default' => empty($value['tour-adult'])? '': $value['tour-adult'],
							'placeholder' => esc_html__('Adult', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					if( !empty($date_price['male-price']) || (isset($date_price['male-price']) && $date_price['male-price'] === '0') ){
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-male',
							'default' => empty($value['tour-male'])? '': $value['tour-male'],
							'placeholder' => esc_html__('Male', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					if( !empty($date_price['female-price']) || (isset($date_price['female-price']) && $date_price['female-price'] === '0') ){
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-female',
							'default' => empty($value['tour-female'])? '': $value['tour-female'],
							'placeholder' => esc_html__('Female', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					if( !empty($date_price['children-price']) || (isset($date_price['children-price']) && $date_price['children-price'] === '0') ){
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-children',
							'default' => empty($value['tour-children'])? '': $value['tour-children'],
							'placeholder' => esc_html__('Child', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					if( !empty($date_price['student-price']) || (isset($date_price['student-price']) && $date_price['student-price'] === '0') ){	
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-student',
							'default' => empty($value['tour-student'])? '': $value['tour-student'],
							'placeholder' => esc_html__('Student', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					if( !empty($date_price['infant-price']) || (isset($date_price['infant-price']) && $date_price['infant-price'] === '0') ){
						$ret .= tourmaster_get_tour_booking_combobox(array(
							'name' => 'tour-infant',
							'default' => empty($value['tour-infant'])? '': $value['tour-infant'],
							'placeholder' => esc_html__('Infant', 'tourmaster'),
							'max-num' => $max_people_per_booking
						));
					}
					$ret .= '</div>';
					$ret .= '</div>';
				}

			// room based	
			}else{

				$tour_room = empty($value['tour-room'])? 1: $value['tour-room'];
				$max_room = empty($date_price['max-room'])? tourmaster_get_option('general', 'max-dropdown-room-amount', 10): $date_price['max-room'];
				$max_people_per_booking = get_post_meta($settings['tour-id'], 'tourmaster-max-people-per-booking', true);

				$ret .= '<div class="tourmaster-tour-booking-room clearfix" data-step="3" >';
				$ret .= '<div class="tourmaster-tour-booking-next-sign" ><span></span></div>';
				$ret .= '<i class="fa fa-bed" ></i>';
				$ret .= '<div class="tourmaster-tour-booking-room-input" >';
				$ret .= tourmaster_get_tour_booking_combobox(array(
					'name' => 'tour-room',
					'placeholder' => esc_html__('Number Of Rooms', 'tourmaster'),
					'default' => $tour_room,
					'max-num' => $max_room
				));
				$ret .= '</div>'; // tourmaster-tour-booking-room-input
				$ret .= '</div>'; // tourmaster-tour-booking-room

				
				// fixed price
				if( $date_price['pricing-method'] == 'fixed' ){
					$ret .= '<div class="tourmaster-tour-booking-people-container" data-step="999" >';
					for( $i = 0; $i < $tour_room; $i++ ){
						$ret .= tourmaster_get_tour_booking_room_amount_template('fixed', $date_price, array(
							'tour-people' => empty($value['tour-people'][$i])? '': $value['tour-people'][$i]
						), $max_people_per_booking);
					}
					$ret .= '</div>';

					$ret .= '<div class="tourmaster-tour-booking-room-template" data-step="999" >';
					$ret .= tourmaster_get_tour_booking_room_amount_template('fixed', $date_price, array(), $max_people_per_booking);
					$ret .= '</div>';  // tourmaster-tour-room-template

				// variable price	
				}else{
					$ret .= '<div class="tourmaster-tour-booking-people-container" data-step="999" >';
					for( $i = 0; $i < $tour_room; $i++ ){
						$ret .= tourmaster_get_tour_booking_room_amount_template('variable', $date_price, array(
							'tour-adult' => empty($value['tour-adult'][$i])? '': $value['tour-adult'][$i],
							'tour-male' => empty($value['tour-male'][$i])? '': $value['tour-male'][$i],
							'tour-female' => empty($value['tour-male'][$i])? '': $value['tour-female'][$i],
							'tour-children' => empty($value['tour-children'][$i])? '': $value['tour-children'][$i],
							'tour-student' => empty($value['tour-student'][$i])? '': $value['tour-student'][$i],
							'tour-infant' => empty($value['tour-infant'][$i])? '': $value['tour-infant'][$i],
						), $max_people_per_booking);
					}
					$ret .= '</div>';

					$ret .= '<div class="tourmaster-tour-booking-room-template" data-step="999" >';
					$ret .= tourmaster_get_tour_booking_room_amount_template('variable', $date_price, array(), $max_people_per_booking);
					$ret .= '</div>'; // tourmaster-tour-room-template
				}
			}

			$ret .= '<div class="tourmaster-tour-booking-submit" data-step="5" >';
			$ret .= '<div class="tourmaster-tour-booking-next-sign" ><span></span></div>';
			ob_start();
?>
<svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
	 viewBox="0 0 512.007 512.007" style="enable-background:new 0 0 512.007 512.007;" xml:space="preserve">
		<path d="M397.413,199.303c-2.944-4.576-8-7.296-13.408-7.296h-112v-176c0-7.552-5.28-14.08-12.672-15.648
			c-7.52-1.6-14.88,2.272-17.952,9.152l-128,288c-2.208,4.928-1.728,10.688,1.216,15.2c2.944,4.544,8,7.296,13.408,7.296h112v176
			c0,7.552,5.28,14.08,12.672,15.648c1.12,0.224,2.24,0.352,3.328,0.352c6.208,0,12-3.616,14.624-9.504l128-288
			C400.805,209.543,400.389,203.847,397.413,199.303z" fill="<?php echo tourmaster_get_option('color', 'tourmaster-theme-color-light', '#4692e7'); ?>" />
</svg>
<?php
			$ret .= ob_get_contents();
			ob_end_clean();
			$ret .= '<i class="fa fa-check-circle" ></i>';
			$ret .= '<div class="tourmaster-tour-booking-submit-input" >';
			$ret .= '<input class="tourmaster-button" type="submit" value="' . esc_html__('Proceed Booking', 'tourmaster') . '" ';
			$ret .= is_user_logged_in()? ' />': 'data-ask-login="proceed-without-login" />';
			$ret .= '<div class="tourmaster-tour-booking-submit-error" >' . esc_html__('* Please select all required fields to proceed to the next step.', 'tourmaster') . '</div>';
			$ret .= '</div>';
			$ret .= '</div>';

			return $ret;
		} // tourmaster_get_tour_booking_fields
	}
	if( !function_exists('tourmaster_get_tour_booking_room_amount_template') ){
		function tourmaster_get_tour_booking_room_amount_template( $type, $date_price, $value = array(), $max_num = 0 ){

			$ret  = '<div class="tourmaster-tour-booking-people tourmaster-variable clearfix" ';
			if( !empty($value) ){
				$ret .= ' data-step="4" ';
			}
			$ret .= ' >';
			$ret .= '<span class="tourmaster-tour-booking-room-text" >';
			$ret .= esc_html__('Room', 'tourmaster');
			$ret .= ' <span>1</span> :';
			$ret .= '</span>';
			if( $type == 'fixed' ){
				$ret .= '<div class="tourmaster-tour-booking-people-input" >';
				$ret .= tourmaster_get_tour_booking_combobox(array(
					'name' => 'tour-people[]',
					'placeholder' => esc_html__('Number Of People', 'tourmaster'),
					'default' => empty($value['tour-people'])? '': $value['tour-people'],
					'max-num' => $max_num
				));
				$ret .= '</div>';

			}else if( $type == 'variable' ){

				$ret .= '<div class="tourmaster-tour-booking-people-input tourmaster-variable clearfix" >';
				if( !empty($date_price['adult-price']) || (isset($date_price['adult-price']) && $date_price['adult-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-adult[]',
						'placeholder' => esc_html__('Adult', 'tourmaster'),
						'default' => empty($value['tour-adult'])? '': $value['tour-adult'],
						'max-num' => $max_num
					));
				}
				if( !empty($date_price['male-price']) || (isset($date_price['male-price']) && $date_price['male-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-male[]',
						'placeholder' => esc_html__('Male', 'tourmaster'),
						'default' => empty($value['tour-male'])? '': $value['tour-male'],
						'max-num' => $max_num
					));
				}
				if( !empty($date_price['female-price']) || (isset($date_price['female-price']) && $date_price['female-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-female[]',
						'placeholder' => esc_html__('Female', 'tourmaster'),
						'default' => empty($value['tour-female'])? '': $value['tour-female'],
						'max-num' => $max_num
					));
				}
				if( !empty($date_price['children-price']) || (isset($date_price['children-price']) && $date_price['children-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-children[]',
						'placeholder' => esc_html__('Child', 'tourmaster'),
						'default' => empty($value['tour-children'])? '': $value['tour-children'],
						'max-num' => $max_num
					));
				}
				if( !empty($date_price['student-price']) || (isset($date_price['student-price']) && $date_price['student-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-student[]',
						'placeholder' => esc_html__('Student', 'tourmaster'),
						'default' => empty($value['tour-student'])? '': $value['tour-student'],
						'max-num' => $max_num
					));
				}
				if( !empty($date_price['infant-price']) || (isset($date_price['infant-price']) && $date_price['infant-price'] === '0') ){
					$ret .= tourmaster_get_tour_booking_combobox(array(
						'name' => 'tour-infant[]',
						'placeholder' => esc_html__('Infant', 'tourmaster'),
						'default' => empty($value['tour-infant'])? '': $value['tour-infant'],
						'max-num' => $max_num
					));
				}
				$ret .= '</div>';
			}
			$ret .= '</div>';

			return $ret;
		}
	}
	if( !function_exists('tourmaster_get_tour_booking_combobox') ){
		function tourmaster_get_tour_booking_combobox( $settings ){

			$ret  = '<div class="tourmaster-combobox-wrap" >';
			$ret .= '<select name="' . esc_attr($settings['name']) . '" >';
			if( $settings['placeholder'] ){
				$ret .= '<option value="" >' . esc_attr($settings['placeholder']) . '</option>';
			}

			if( empty($settings['max-num']) ){
				$max_num = tourmaster_get_option('general', 'max-dropdown-people-amount', 5);
			}else{
				$max_num = $settings['max-num'];
			}
			
			for( $i = 1; $i <= $max_num; $i++ ){
				$ret .= '<option value="' . esc_attr($i) . '" ' . ((!empty($settings['default']) && $settings['default'] == $i)? 'selected': '') . ' >' . $i . '</option>';
			}
			$ret .= '</select>';
			$ret .= '</div>';

			return $ret;

		}
	}

	// get date price settings of specific tour date
	if( !function_exists('tourmaster_get_tour_date_price') ){
		function tourmaster_get_tour_date_price($tour_option, $tour_id, $tour_date ){
			if( !empty($tour_option['date-price']) ){
				foreach( $tour_option['date-price'] as $settings ){
					$dates = tourmaster_get_tour_dates($settings, $tour_option['tour-timing-method']);
					if( in_array($tour_date, $dates) ){
						return $settings;
					}
				}
			}

			return array();
		}
	}
	if( !function_exists('tourmaster_get_tour_date_price_package') ){
		function tourmaster_get_tour_date_price_package($date_price, $booking_detail){

			if( !empty($date_price['package']) ){
				foreach( $date_price['package'] as $slug => $package ){
					if( sizeof($date_price['package']) == 1 || empty($booking_detail['package']) || $booking_detail['package'] == $package['title'] ){

						$package_settings = array( 'start-time', 'group-slug', 'person-price', 'adult-price', 'children-price', 'student-price', 'infant-price', 'male-price', 'female-price', 'same-gender', 'max-people',
							'initial-price', 'single-discount', 'additional-person', 'additional-adult', 'additional-children', 'additional-student', 'additional-infant', 'additional-male', 'additional-female', 'minimum-people-per-booking', 'max-room', 'max-people-per-room',
							'group-price', 'max-group', 'max-group-people'
						);
						foreach( $package_settings as $package_slug ){
							if( isset($package[$package_slug]) ){
								$date_price[$package_slug] = $package[$package_slug];
							}
						}

						unset($date_price['package']);
						break;
					}
				}
			}

			return $date_price;
		}
	}

	// get tour date from option
	// timing : single/recurring
	if( !function_exists('tourmaster_get_tour_dates') ){	
		function tourmaster_get_tour_dates( $settings = array(), $timing = 'single' ){
			
			$dates = array();

			// single date
			if( $timing == 'single' ){
				if( !empty($settings['date'])){
					$dates[] = $settings['date'];
				}

			// recurring date
			}else{
				if( !empty($settings['year']) && !empty($settings['month']) && !empty($settings['day']) ){
					foreach( $settings['year'] as $year ){
						foreach( $settings['month'] as $month ){
							foreach( $settings['day'] as $day ){

								$timestamp = strtotime("{$year}-{$month}-1");

								// if day matched the selected day
								if( $day == strtolower(date('l', $timestamp)) ){
								 	$dates[] = date('Y-m-d', $timestamp);
								}

								$timestamp = strtotime("next {$day}", $timestamp);
								while( date('n', $timestamp) == $month ){
									$dates[] = date('Y-m-d', $timestamp);
									$timestamp = strtotime("next {$day}", $timestamp);
								}
							}
						}
					}

				} // not empty date month year

				// include extra date
				if( !empty($settings['extra-date']) ){
					$extra_dates = array();
					$extra_dates = explode(',', $settings['extra-date']);
					$extra_dates = array_map('trim', $extra_dates);
					
					if( !empty($extra_dates) ){
						foreach( $extra_dates as $date ){
							// ref : http://stackoverflow.com/questions/22061723/regex-date-validation-for-yyyy-mm-dd
							if( preg_match('/^\d{4}\-(0?[1-9]|1[012])\-(0?[1-9]|[12][0-9]|3[01])$/', $date) ){
								if( !in_array($date, $dates) ){
									$dates[] = $date;
								}
							}
						}

						sort($dates);
					}
					// check if it's valid date
				}

				// exclude extra date
				if( !empty($settings['exclude-extra-date']) ){
					$extra_dates = array();
					$extra_dates = explode(',', $settings['exclude-extra-date']);
					$extra_dates = array_map('trim', $extra_dates);
					
					$dates = array_diff($dates, $extra_dates);
				}
			}

			return $dates;
		} // tourmaster_get_tour_dates
	}	

	// filter date 
	// time_offset is 60 * 60 * 24 = 86400
	if( !function_exists('tourmaster_filter_tour_date') ){
		function tourmaster_filter_tour_date( $dates, $month = '', $time_offset = 86400 ){
			
			if( !empty($month) ){
				$tmp = strtotime(current_time('Y-m-1'));
				$end_time = strtotime('+ ' . (intval($month) + 1) . ' month', $tmp);
			}

			$current_time = strtotime(current_time('Y-m-d H:i'));
			foreach( $dates as $key => $date ){

				$date_time = strtotime($date);

				// if the date is already pass
				if( $current_time > $date_time + $time_offset ){
					unset($dates[$key]);
				}

				// if exceed the available time
				if( !empty($end_time) && $end_time < $date_time ){
					unset($dates[$key]);
				}
			}

			return $dates;
		}
	}	

	if( !function_exists('tourmaster_get_tour_people_amount') ){
		function tourmaster_get_tour_people_amount( $tour_option, $date_price, $booking_detail, $type = 'sum' ){
			
			$amount = 0;
			$male_amount = 0;
			$female_amount = 0;

			if( empty($date_price) ){
				$date_price = tourmaster_get_tour_date_price($tour_option, $booking_detail['tour-id'], $booking_detail['tour-date']);
				$date_price = tourmaster_get_tour_date_price_package($date_price, $booking_detail);
			}

			// no room based
			if( $tour_option['tour-type'] == 'single' || $date_price['pricing-room-base'] == 'disable' ){
				
				// fixed price
				if( $date_price['pricing-method'] == 'fixed' ){
					$amount += empty($booking_detail['tour-people'])? 0: intval($booking_detail['tour-people']);
				
				// variable price
				}else{
					$amount += empty($booking_detail['tour-adult'])? 0: intval($booking_detail['tour-adult']);
					$amount += empty($booking_detail['tour-male'])? 0: intval($booking_detail['tour-male']);
					$amount += empty($booking_detail['tour-female'])? 0: intval($booking_detail['tour-female']);
					$amount += empty($booking_detail['tour-children'])? 0: intval($booking_detail['tour-children']);
					$amount += empty($booking_detail['tour-student'])? 0: intval($booking_detail['tour-student']);
					$amount += empty($booking_detail['tour-infant'])? 0: intval($booking_detail['tour-infant']);
					
					$male_amount += empty($booking_detail['tour-male'])? 0: intval($booking_detail['tour-male']);
					$female_amount += empty($booking_detail['tour-female'])? 0: intval($booking_detail['tour-female']);
				}

			// room based	
			}else{

				// fixed price
				for( $i = 0; $i < $booking_detail['tour-room']; $i++ ){
					if( $date_price['pricing-method'] == 'fixed' ){
						$amount += empty($booking_detail['tour-people'][$i])? 0: intval($booking_detail['tour-people'][$i]);
					
					// variable price
					}else{
						$amount += empty($booking_detail['tour-adult'][$i])? 0: intval($booking_detail['tour-adult'][$i]);
						$amount += empty($booking_detail['tour-male'][$i])? 0: intval($booking_detail['tour-male'][$i]);
						$amount += empty($booking_detail['tour-female'][$i])? 0: intval($booking_detail['tour-female'][$i]);
						$amount += empty($booking_detail['tour-children'][$i])? 0: intval($booking_detail['tour-children'][$i]);
						$amount += empty($booking_detail['tour-student'][$i])? 0: intval($booking_detail['tour-student'][$i]);
						$amount += empty($booking_detail['tour-infant'][$i])? 0: intval($booking_detail['tour-infant'][$i]);
						
						$male_amount += empty($booking_detail['tour-male'][$i])? 0: intval($booking_detail['tour-male'][$i]);
						$female_amount += empty($booking_detail['tour-female'][$i])? 0: intval($booking_detail['tour-female'][$i]);
					}
				}
			}

			if( $type == 'sum' ){
				return $amount;
			}else if( $type == 'all' ){
				return array(
					'male' => $male_amount,
					'female' => $female_amount,
					'sum' => $amount
				);
			}
			return $amount;

		}
	}

	if( !function_exists('tourmaster_get_tour_price') ){
		function tourmaster_get_tour_price( $tour_option, $date_price, $booking_detail ){

			if( empty($date_price) ){
				$date_price = tourmaster_get_tour_date_price($tour_option, $booking_detail['tour-id'], $booking_detail['tour-date']);
				$date_price = tourmaster_get_tour_date_price_package($date_price, $booking_detail);
			}

			$total_price = 0;
			$traveller_amount = 0;
			$room_amount = 0;
			$price_breakdown = array();

			// group price
			if( $date_price['pricing-method'] == 'group' ){

				$price_breakdown['group-price'] = $date_price['group-price'];
				$total_price += $price_breakdown['group-price'];
				
				if( !empty($booking_detail['traveller_first_name']) ){
					for( $i = 0; $i < sizeof($booking_detail['traveller_first_name']); $i++ ){
						if( !empty($booking_detail['traveller_first_name'][$i]) || !empty($booking_detail['traveller_last_name'][$i]) ){
							$traveller_amount++;
						}
					}
				}

			// no room based
			}else if( $tour_option['tour-type'] == 'single' || $date_price['pricing-room-base'] == 'disable' ){

				// fixed price
				if( $date_price['pricing-method'] == 'fixed' ){
					$price_breakdown['traveller-base-price'] = $date_price['person-price'];
					$price_breakdown['traveller-amount'] = $booking_detail['tour-people'];
					$total_price += $price_breakdown['traveller-amount'] * $price_breakdown['traveller-base-price'];

					$traveller_amount += $price_breakdown['traveller-amount'];
					$room_amount += $price_breakdown['traveller-amount'];

				// variable price
				}else{
					$types = array('adult', 'male', 'female', 'children', 'student', 'infant');
					foreach( $types as $type ){
						if( !empty($booking_detail['tour-' . $type]) ){
							$price_breakdown[$type . '-base-price'] = $date_price[$type . '-price'];
							$price_breakdown[$type . '-amount'] = $booking_detail['tour-' . $type];
							$total_price += $price_breakdown[$type . '-amount'] * $price_breakdown[$type . '-base-price'];
							
							$traveller_amount += $price_breakdown[$type . '-amount'];
							$room_amount += $price_breakdown[$type . '-amount'];
						}
					}
				}

			// room based	
			}else{
				
				$price_breakdown['room'] = array();
				
				// fixed price
				if( $date_price['pricing-method'] == 'fixed' ){
					$price_breakdown['traveller-amount'] = 0;
					for( $i = 0; $i < $booking_detail['tour-room']; $i++ ){
						$room = array();
						$room['base-price'] = $date_price['initial-price'];
						$room['traveller-amount'] = $booking_detail['tour-people'][$i];
						if( $room['traveller-amount'] == 1 && !empty($date_price['single-discount']) ){
							$room['base-price'] = $room['base-price'] - $date_price['single-discount'];
						}else if( $room['traveller-amount'] > 2 ){
							$room['additional-traveller-price'] = $date_price['additional-person'];
							$room['additional-traveller-amount'] = $room['traveller-amount'] - 2;
							$total_price += $room['additional-traveller-price'] * $room['additional-traveller-amount'];
						}
						$total_price += $room['base-price'];

						$price_breakdown['room'][] = $room;
						$price_breakdown['traveller-amount'] += $room['traveller-amount'];

						

						$room_amount ++;
					}
					$price_breakdown['traveller-base-price'] = $date_price['person-price'];
					$total_price += $price_breakdown['traveller-base-price'] * $price_breakdown['traveller-amount'];

					$traveller_amount += $price_breakdown['traveller-amount'];

				// variable price
				}else{

					$types = array('adult', 'male', 'female', 'children', 'student', 'infant');

					for( $i = 0; $i < $booking_detail['tour-room']; $i++ ){
						$room = array();
						$room['base-price'] = $date_price['initial-price'];

						$room_base_count = 2;
						foreach( $types as $type ){
							if( !empty($booking_detail['tour-' . $type][$i]) ){
								$room[$type . '-amount'] = $booking_detail['tour-' . $type][$i];

								// calculate additional person / room
								if( $booking_detail['tour-' . $type][$i] >= $room_base_count ){
									$additional_person = $booking_detail['tour-' . $type][$i] - $room_base_count;
									$room_base_count = 0;
								}else{
									$additional_person = 0;
									$room_base_count = $room_base_count - $booking_detail['tour-' . $type][$i];
								}
								if( $additional_person > 0 ){
									$room['additional-' . $type . '-price'] = $date_price['additional-' . $type];
									$room['additional-' . $type . '-amount'] = $additional_person;
									$total_price += $room['additional-' . $type . '-price'] * $additional_person;
								}
								$price_breakdown[$type . '-amount'] = (empty($price_breakdown[$type . '-amount'])? 0: $price_breakdown[$type . '-amount']) + $booking_detail['tour-' . $type][$i];
							}
						}

						if( $room_base_count == 1 && !empty($date_price['single-discount']) ){
							$room['base-price'] = $room['base-price'] - $date_price['single-discount'];
						}
						$total_price += $room['base-price'];
						$price_breakdown['room'][] = $room;

						$room_amount ++;
					}

					// calculate total base price
					foreach( $types as $type ){
						if( !empty($price_breakdown[$type . '-amount']) ){
							$price_breakdown[$type . '-base-price'] = $date_price[$type . '-price'];
							$total_price += $price_breakdown[$type . '-base-price'] * $price_breakdown[$type . '-amount'];
						
							$traveller_amount += $price_breakdown[$type . '-amount'];
						}	
					}
				}
			
			}

			// additional service
			if( !empty($booking_detail['service']) && $booking_detail['service-amount'] ){
				$services = tourmaster_process_service_data($booking_detail['service'], $booking_detail['service-amount']);
				if( !empty($services) ){
					$price_breakdown['additional-service'] = array();
					foreach( $services as $service_id => $service_amount ){
						$service_option = get_post_meta($service_id, 'tourmaster-service-option', true);
						$service_summary = array( 'per' => $service_option['per'] );
						switch( $service_option['per'] ){
							case 'person': 
								$service_summary['amount'] = $traveller_amount;
								break; 
							case 'room': 
								$service_summary['amount'] = $room_amount;
								break; 
							case 'group': 
								$service_summary['amount'] = '1';
								break; 
							case 'unit': 
								$service_summary['amount'] = $service_amount;
								break;
							default: 
								break;
						}
						$service_summary['price-one'] = floatval($service_option['price']);
						$service_summary['price'] = floatval($service_summary['amount']) * $service_summary['price-one'];


						$price_breakdown['additional-service'][$service_id] = $service_summary;
						$total_price += $service_summary['price'];
					}
				}
			}

			$price_breakdown['sub-total-price'] = $total_price;

			// group discount
			if( !empty($tour_option['group-discount']) ){
				$gd_traveller = 0;
				$gd_rate = '';
				$gd_amount = 0;

				foreach( $tour_option['group-discount'] as $gd ){
					if( $traveller_amount >= $gd['traveller-number'] && $gd['traveller-number'] >= $gd_traveller ){
						$gd_traveller = $gd['traveller-number'];

						if( strpos($gd['discount'], '%') !== false ){
							$gd_rate = $gd['discount'];
							$gd_amount = ($total_price * floatval(str_replace('%', '', $gd['discount']))) / 100;
						}else{
							$gd_rate = floatval($gd['discount']);
							$gd_amount = $gd_rate;
						}
					}
				}

				if( $gd_amount > 0 ){
					$total_price -= $gd_amount;

					$price_breakdown['group-discount-traveller'] = $traveller_amount; // $gd_traveller;
					$price_breakdown['group-discount-rate'] = $gd_rate;
					$price_breakdown['group-discounted-price'] = $total_price;
				}
			}


			// coupon
			if( !empty($booking_detail['coupon-code']) ){
				$coupon_validate = tourmaster_validate_coupon_code($booking_detail['coupon-code'], $booking_detail['tour-id']);
				if( !empty($coupon_validate['data']) ){
					$coupon_data = $coupon_validate['data'];

					$price_breakdown['coupon-code'] = $booking_detail['coupon-code'];
					if( $coupon_data['coupon-discount-type'] == 'percent' ){
						$price_breakdown['coupon-text'] = $coupon_data['coupon-discount-amount'] . '%';
						$price_breakdown['coupon-amount'] = (floatval($coupon_data['coupon-discount-amount']) * $total_price) / 100;
					}else if( $coupon_data['coupon-discount-type'] == 'amount' ){
						$price_breakdown['coupon-amount'] = $coupon_data['coupon-discount-amount'];
					}

					if( $price_breakdown['coupon-amount'] > $total_price ){
						$total_price = 0;
					}else{
						$total_price = $total_price - $price_breakdown['coupon-amount'];
					}
				}
			}
			
			// tax
			$tax_rate = tourmaster_get_option('general', 'tax-rate', 0);
			if( !empty($tax_rate) ){
				$price_breakdown['tax-rate'] = $tax_rate;
				$price_breakdown['tax-due'] = ($total_price * $tax_rate) / 100;
				$total_price += $price_breakdown['tax-due'];
			}

			$ret = array();

			// deposit price
			if( !empty($booking_detail['payment-type']) && $booking_detail['payment-type'] == 'partial' ){
				if( empty($tour_option['deposit-booking']) || $tour_option['deposit-booking'] == 'default' ){
					$deposit_rate = tourmaster_get_option('payment', 'deposit-payment-amount', '0');
				}else{
					$deposit_rate = empty($tour_option['deposit-amount'])? 0: $tour_option['deposit-amount'];
				}
				$deposit_price = ($total_price * intval($deposit_rate)) / 100;

				$ret['deposit-rate'] = $deposit_rate;
				$ret['deposit-price'] = $deposit_price;
			}

			// check service rate
			// only for displaying, will not be stored until paypal payment is made 
			if( !empty($booking_detail['payment_method']) ){
				if( $booking_detail['payment_method'] == 'paypal' ){
					$service_fee = tourmaster_get_option('payment', 'paypal-service-fee', '');
					if( !empty($service_fee) ){
						if( !empty($ret['deposit-price']) ){
							$ret['deposit-price-raw'] = $ret['deposit-price'];
							$ret['deposit-paypal-service-rate'] = $service_fee;
							$ret['deposit-paypal-service-fee'] = $ret['deposit-price'] * (floatval($service_fee) / 100);	
							$ret['deposit-price'] += $ret['deposit-paypal-service-fee'];
						}else{
							$price_breakdown['paypal-service-rate'] = $service_fee;
							$price_breakdown['paypal-service-fee'] = $total_price * (floatval($service_fee) / 100);	
							$total_price += $price_breakdown['paypal-service-fee'];
						}
					}
				}else if( in_array($booking_detail['payment_method'], array('stripe', 'authorize', 'paymill')) ){
					$service_fee = tourmaster_get_option('payment', 'credit-card-service-fee', '');
					if( !empty($service_fee) ){
						if( !empty($ret['deposit-price']) ){
							$ret['deposit-price-raw'] = $ret['deposit-price'];
							$ret['deposit-credit-card-service-rate'] = $service_fee;
							$ret['deposit-credit-card-service-fee'] = $ret['deposit-price'] * (floatval($service_fee) / 100);	
							$ret['deposit-price'] += $ret['deposit-credit-card-service-fee'];
						}else{
							$price_breakdown['credit-card-service-rate'] = $service_fee;
							$price_breakdown['credit-card-service-fee'] = $total_price * (floatval($service_fee) / 100);	
							$total_price += $price_breakdown['credit-card-service-fee'];
						}
					}
				}
			}

			$ret['total-price'] = $total_price;
			$ret['price-breakdown'] = $price_breakdown;

			return $ret;

		} // tourmaster_get_tour_price
	}
	add_action('wp_ajax_tourmaster_update_head_price', 'tourmaster_update_head_price');
	add_action('wp_ajax_nopriv_tourmaster_update_head_price', 'tourmaster_update_head_price');
	if( !function_exists('tourmaster_update_head_price') ){
		function tourmaster_update_head_price(){

			$data = empty($_POST['data'])? array(): tourmaster_process_post_data($_POST['data']);
			$data['package'] = empty($data['package'])? '': $data['package'];

			$tour_option = tourmaster_get_post_meta($data['tour-id'], 'tourmaster-tour-option');
			$date_price = tourmaster_get_tour_date_price($tour_option, $data['tour-id'], $data['tour-date']);
			$date_price = tourmaster_get_tour_date_price_package($date_price, $data);
				
			$tour_price = tourmaster_get_tour_price($tour_option, $date_price, $data);


			$ret = array();
			if( !empty($tour_price['total-price']) ){
				$ret['price'] = tourmaster_money_format($tour_price['total-price']);
			}

			die(json_encode($ret));
		}
	}

	if( !function_exists('tourmaster_get_tour_price_breakdown') ){
		function tourmaster_get_tour_price_breakdown( $price_breakdown ){
			$types = array(
				'traveller' => esc_html__('Traveller', 'tourmaster'),
				'adult' => esc_html__('Adult', 'tourmaster'),
				'male' => esc_html__('Male', 'tourmaster'),
				'female' => esc_html__('Female', 'tourmaster'),
				'children' => esc_html__('Child', 'tourmaster'),
				'student' => esc_html__('Student', 'tourmaster'),
				'infant' => esc_html__('Infant', 'tourmaster'),
			);

			$ret  = '<div class="tourmaster-price-breakdown" >';
			$ret .= '<div class="tourmaster-price-breakdown-base-price-wrap" >';

			// group price
			if( !empty($price_breakdown['group-price']) ){
				$ret .= '<div class="tourmaster-price-breakdown-group-price" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Group Price :', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >' . tourmaster_money_format($price_breakdown['group-price']) . '</span>';
				$ret .= '</div>';
			}

			foreach( $types as $type => $type_title ){
				if( !empty($price_breakdown[$type . '-amount']) ){
					$ret .= '<div class="tourmaster-price-breakdown-base-price" >';
					$ret .= '<span class="tourmaster-head" >' . $type_title . ' ' . esc_html__('Base Price', 'tourmaster') . '</span>';
					$ret .= '<span class="tourmaster-tail" >';
					$ret .= '<span class="tourmaster-price-detail" >' . $price_breakdown[$type . '-amount'] . ' x ' . tourmaster_money_format($price_breakdown[$type . '-base-price'], -2) . '</span>';
					$ret .= '<span class="tourmaster-price" >' . tourmaster_money_format($price_breakdown[$type . '-amount'] * $price_breakdown[$type . '-base-price']) . '</span>';
					$ret .= '</span>';
					$ret .= '</div>'; // tourmaster-price-breakdown-base-price
				}
			}
			$ret .= '</div>';


			if( !empty($price_breakdown['room']) ){
				$count = 1;
				foreach( $price_breakdown['room'] as $room ){
					$ret .= '<div class="tourmaster-price-breakdown-room" >';
					$ret .= '<div class="tourmaster-price-breakdown-room-head" >';
					$ret .= '<span class="tourmaster-head" >' . esc_html__('Room', 'tourmaster') . ' ' . $count . ' :</span>';
					$ret .= '<span class="tourmaster-tail" >';
					foreach( $types as $type => $type_title ){
						if( !empty($room[$type . '-amount']) ){
							$ret .= $room[$type . '-amount'] . ' ' . $type_title . ' ';
						}
					}
					$ret .= '</span>';
					$ret .= '</div>';

					$ret .= '<div class="tourmaster-price-breakdown-room-price" >';
					$ret .= '<span class="tourmaster-head" >' . esc_html__('Room Base Price :') . '</span>';
					$ret .= '<span class="tourmaster-tail tourmaster-right" >' . tourmaster_money_format($room['base-price']) . '</span>';
					$ret .= '</div>';

					foreach( $types as $type => $type_title ){
						if( !empty($room['additional-' . $type . '-amount']) ){
							$ret .= '<div class="tourmaster-price-breakdown-room-price" >';
							$ret .= '<span class="tourmaster-head" >' . esc_html__('Additional') . ' ' . $type_title . ' :</span>';
							$ret .= '<span class="tourmaster-tail" >';
							$ret .= '<span class="tourmaster-price-detail" >' . $room['additional-' . $type . '-amount'] . ' x ' . tourmaster_money_format($room['additional-' . $type . '-price'], -2) . '</span>';
							$ret .= '<span class="tourmaster-price" >' .  tourmaster_money_format($room['additional-' . $type . '-price'] * $room['additional-' . $type . '-amount']) . '</span>';
							$ret .= '</span>';
							$ret .= '</div>';
						}
					}
					$ret .= '</div>';
					$count++;
				}
			}

			// additional service
			if( !empty($price_breakdown['additional-service']) ){
				$ret .= '<div class="tourmaster-price-breakdown-additional-service" >';
				$ret .= '<h3 class="tourmaster-price-breakdown-additional-service-title" >' . esc_html__('Additional Services', 'tourmaster') . '</h3>';
				foreach( $price_breakdown['additional-service'] as $service_id => $service_option ){
					$ret .= '<div class="tourmaster-price-breakdown-additional-service-item clearfix" >';
					$ret .= '<span class="tourmaster-head" >';
					$ret .= get_the_title($service_id);
					$ret .= ' (' . $service_option['amount'] . ' x ' . tourmaster_money_format($service_option['price-one'], -2) . ') ';
					$ret .= '</span>';
					$ret .= '<span class="tourmaster-tail tourmaster-right" >';
					$ret .= tourmaster_money_format($service_option['price']);
					$ret .= '</span>';
					$ret .= '</div>';
				}
				$ret .= '</div>';
			}

			// sub total
			$ret .= '<div class="tourmaster-price-breakdown-summary" >';
			$ret .= '<div class="tourmaster-price-breakdown-sub-total " >';
			$ret .= '<span class="tourmaster-head" >' . esc_html__('Sub Total Price', 'tourmaster') . '</span>';
			$ret .= '<span class="tourmaster-tail tourmaster-right" >';
			$ret .= tourmaster_money_format($price_breakdown['sub-total-price']);
			$ret .= '</span>';
			$ret .= '</div>';

			if( !empty($price_breakdown['group-discount-traveller']) && !empty($price_breakdown['group-discounted-price']) ){
				$ret .= '<div class="tourmaster-price-breakdown-group-discount" >';
				$ret .= '<div class="tourmaster-price-breakdown-group-discount-amount" >';
				$ret .= '<span class="tourmaster-head" >' . sprintf(esc_html__('Group Discount (%d people)', 'tourmaster'), $price_breakdown['group-discount-traveller']) . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				if( strpos($price_breakdown['group-discount-rate'], '%') !== false ){
					$ret .= $price_breakdown['group-discount-rate'];
				}else{
					$ret .= tourmaster_money_format($price_breakdown['group-discount-rate']);
				}
				$ret .= '</span>';
				$ret .= '</div>';

				$ret .= '<div class="tourmaster-price-breakdown-group-discounted-price" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Discounted Price', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >' . tourmaster_money_format($price_breakdown['group-discounted-price']) . '</span>';
				$ret .= '</div>';
				$ret .= '</div>';
			}

			if( !empty($price_breakdown['coupon-amount']) ){
				$ret .= '<div class="tourmaster-price-breakdown-coupon-code" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Coupon Code :', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail" > ';
				$ret .= '<span class="tourmaster-coupon-code" >' . (empty($price_breakdown['coupon-code'])? '-': $price_breakdown['coupon-code']) . '</span>';
				if( !empty($price_breakdown['coupon-text'])){
					$ret .= '<span class="tourmaster-coupon-text" >' . $price_breakdown['coupon-text'] . '</span>';
				}
				$ret .= '</span>';
				$ret .= '</div>';

				$ret .= '<div class="tourmaster-price-breakdown-coupon-amount" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Discount Price', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >- ';
				$ret .= tourmaster_money_format($price_breakdown['coupon-amount']);
				$ret .= '</span>';
				$ret .= '</div>';
			}

			// tax rate
			if( !empty($price_breakdown['tax-rate']) ){
				$ret .= '<div class="tourmaster-price-breakdown-tax-rate" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Tax Rate', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= $price_breakdown['tax-rate'] . '%';
				$ret .= '</span>';
				$ret .= '</div>';
			}

			// tax due
			if( !empty($price_breakdown['tax-due']) ){
				$ret .= '<div class="tourmaster-price-breakdown-tax-due" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Tax Due', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['tax-due']);
				$ret .= '</span>';
				$ret .= '</div>';
			}

			// paypal service fee
			if( !empty($price_breakdown['paypal-service-rate']) && !empty($price_breakdown['paypal-service-fee']) ){
				$ret .= '<div class="tourmaster-price-breakdown-service-fee" >';
				$ret .= '<span class="tourmaster-head" >' . sprintf(esc_html__('Paypal Service Fee (%s%%)', 'tourmaster'), $price_breakdown['paypal-service-rate']) . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['paypal-service-fee']);
				$ret .= '</span>';
				$ret .= '</div>';

			// credit card service fee
			}else if( !empty($price_breakdown['credit-card-service-rate']) && !empty($price_breakdown['credit-card-service-fee']) ){
				$ret .= '<div class="tourmaster-price-breakdown-service-fee" >';
				$ret .= '<span class="tourmaster-head" >' . sprintf(esc_html__('Credit Card Service Fee (%s%%)', 'tourmaster'), $price_breakdown['credit-card-service-rate']) . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['credit-card-service-fee']);
				$ret .= '</span>';
				$ret .= '</div>';
			}

			$ret .= '</div>'; // tourmaster-price-breakdown-summary
			$ret .= '<div class="clear"></div>';
			$ret .= '</div>'; // tourmaster-price-breakdown

			return $ret;
		} // tourmaster_get_tour_price_breakdown
	}	
	if( !function_exists('tourmaster_get_tour_invoice_price') ){
		function tourmaster_get_tour_invoice_price( $tour_id, $price_breakdown ){
			$types = array(
				'traveller' => esc_html__('Traveller', 'tourmaster'),
				'adult' => esc_html__('Adult', 'tourmaster'),
				'male' => esc_html__('Male', 'tourmaster'),
				'female' => esc_html__('Female', 'tourmaster'),
				'children' => esc_html__('Child', 'tourmaster'),
				'student' => esc_html__('Student', 'tourmaster'),
				'infant' => esc_html__('Infant', 'tourmaster'),
			);

			$ret  = '<div class="tourmaster-invoice-price clearfix" >';

			// item name
			$ret .= '<div class="tourmaster-invoice-price-item clearfix" >';
			$ret .= '<span class="tourmaster-head" >';
			$ret .= '<span class="tourmaster-head-title" >' . get_the_title($tour_id) . '</span>';
			if( !empty($price_breakdown['group-price']) ){

			}else{
				$ret .= '<span class="tourmaster-head-caption" >- ';
				$comma = false;
				foreach( $types as $type_slug => $type ){
					if( !empty($price_breakdown[$type_slug . '-amount']) ){
						$ret .= empty($comma)? '': ', ';
						$ret .= $price_breakdown[$type_slug . '-amount'] . ' ' . $type;
						$comma = true;
					}
				}
				$ret .= '</span>';
			}
			$ret .= '</span>';
			$ret .= '<span class="tourmaster-tail tourmaster-right" >';
			// subtract service out
			$sub_total_price = $price_breakdown['sub-total-price'];
			if( !empty($price_breakdown['additional-service']) ){
				foreach( $price_breakdown['additional-service'] as $service_id => $service_option ){
					if( !empty($service_option['price']) ){
						$sub_total_price -= $service_option['price'];
					}
				}
			}
			$ret .= tourmaster_money_format($sub_total_price);
			$ret .= '</span>';
			$ret .= '</div>';	

			// additional service
			if( !empty($price_breakdown['additional-service']) ){
				$ret .= '<div class="tourmaster-invoice-price-item tourmaster-large clearfix" >';
				$ret .= '<h3 class="tourmaster-invoice-price-additional-service-title" >' . esc_html__('Additional Services', 'tourmaster') . '</h3>';
				foreach( $price_breakdown['additional-service'] as $service_id => $service_option ){
					$ret .= '<span class="tourmaster-head" >';
					$ret .= get_the_title($service_id);
					$ret .= ' (' . $service_option['amount'] . ' x ' . tourmaster_money_format($service_option['price-one'], -2) . ') ';
					$ret .= '</span>';
					$ret .= '<span class="tourmaster-tail tourmaster-right" >';
					$ret .= tourmaster_money_format($service_option['price']);
					$ret .= '</span>';
				}
				$ret .= '</div>';
			}		

			// sub total
			$ret .= '<div class="tourmaster-invoice-price-sub-total clearfix" >';
			$ret .= '<span class="tourmaster-head" >' . esc_html__('Sub Total', 'tourmaster') . '</span>';
			$ret .= '<span class="tourmaster-tail tourmaster-right" >';
			$ret .= tourmaster_money_format($price_breakdown['sub-total-price']);
			$ret .= '</span>';
			$ret .= '</div>';

			// discounted price
			if( !empty($price_breakdown['group-discounted-price']) ){
				$ret .= '<div class="tourmaster-invoice-price-last" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Group Discounted Price', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >' . tourmaster_money_format($price_breakdown['group-discounted-price']) . '</span>';
				$ret .= '</div>';
			}

			// tax due
			if( !empty($price_breakdown['tax-due']) ){
				$ret .= '<div class="tourmaster-invoice-price-tax clearfix" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Tax', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['tax-due']);
				$ret .= '</span>';
				$ret .= '</div>';
			}

			// paypal service fee
			if( !empty($price_breakdown['paypal-service-rate']) && !empty($price_breakdown['paypal-service-fee']) ){
				$ret .= '<div class="tourmaster-invoice-price-last" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Paypal Service Fee', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['paypal-service-fee']);
				$ret .= '</span>';
				$ret .= '</div>';

			// credit card service fee
			}else if( !empty($price_breakdown['credit-card-service-rate']) && !empty($price_breakdown['credit-card-service-fee']) ){
				$ret .= '<div class="tourmaster-invoice-price-last" >';
				$ret .= '<span class="tourmaster-head" >' . esc_html__('Credit Card Service Fee', 'tourmaster') . '</span>';
				$ret .= '<span class="tourmaster-tail tourmaster-right" >';
				$ret .= tourmaster_money_format($price_breakdown['credit-card-service-fee']);
				$ret .= '</span>';
				$ret .= '</div>';
			}

			$ret .= '</div>'; // tourmaster-invoice-price

			return $ret;
		} // tourmaster_get_tour_invoice_price
	}
	if( !function_exists('tourmaster_get_tour_invoice_price_email') ){
		function tourmaster_get_tour_invoice_price_email( $tour_id, $price_breakdown ){
			$types = array(
				'traveller' => esc_html__('Traveller', 'tourmaster'),
				'adult' => esc_html__('Adult', 'tourmaster'),
				'male' => esc_html__('Male', 'tourmaster'),
				'female' => esc_html__('Female', 'tourmaster'),
				'children' => esc_html__('Child', 'tourmaster'),
				'student' => esc_html__('Student', 'tourmaster'),
				'infant' => esc_html__('Infant', 'tourmaster'),
			);

			$ret  = '<div>'; // tourmaster-invoice-price clearfix

			// item name
			$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-item
			$ret .= '<span style="width: 80%; float: left; color: #7b7b7b;" >'; // tourmaster-head
			$ret .= '<span style="display: block; font-size: 15px; margin-bottom: 2px;" >' . get_the_title($tour_id) . '</span>'; // tourmaster-head-title
			if( !empty($price_breakdown['group-price']) ){

			}else{
				$ret .= '<span class="display: block; font-size: 13px;" >- '; // tourmaster-head-caption
				$comma = false;
				foreach( $types as $type_slug => $type ){
					if( !empty($price_breakdown[$type_slug . '-amount']) ){
						$ret .= empty($comma)? '': ', ';
						$ret .= $price_breakdown[$type_slug . '-amount'] . ' ' . $type;
						$comma = true;
					}
				}
				$ret .= '</span>';
			}
			$ret .= '</span>';
			$ret .= '<span style="color: #1e1e1e; font-size: 16px;" >'; // tourmaster-tail
			// subtract service out
			$sub_total_price = $price_breakdown['sub-total-price'];
			if( !empty($price_breakdown['additional-service']) ){
				foreach( $price_breakdown['additional-service'] as $service_id => $service_option ){
					if( !empty($service_option['price']) ){
						$sub_total_price -= $service_option['price'];
					}
				}
			}
			$ret .= tourmaster_money_format($sub_total_price);
			$ret .= '</span>';
			$ret .= '<div style="clear: both;" ></div>';
			$ret .= '</div>';	

			// additional service
			if( !empty($price_breakdown['additional-service']) ){
				$ret .= '<div style="padding: 30px 25px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-item tourmaster-large clearfix
				$ret .= '<h3 style="font-size: 15px; margin-bottom: 12px; font-weight: bold;" >' . esc_html__('Additional Services', 'tourmaster') . '</h3>'; // tourmaster-invoice-price-additional-service-title
				foreach( $price_breakdown['additional-service'] as $service_id => $service_option ){
					$ret .= '<span style="width: 80%; float: left; color: #7b7b7b;" >'; // tourmaster-head
					$ret .= get_the_title($service_id);
					$ret .= ' (' . $service_option['amount'] . ' x ' . tourmaster_money_format($service_option['price-one'], -2) . ') ';
					$ret .= '</span>';
					$ret .= '<span style="color: #1e1e1e; font-size: 16px;" >'; // tourmaster-tail
					$ret .= tourmaster_money_format($service_option['price']);
					$ret .= '</span>';
				}
				$ret .= '<div style="clear: both;" ></div>';
				$ret .= '</div>';
			}			

			// sub total
			$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-sub-total
			$ret .= '<span style="color: #7b7b7b; float: left; margin-left: 55%; width: 25%; font-size: 15px;" >' . esc_html__('Sub Total', 'tourmaster') . '</span>'; // tourmaster-head
			$ret .= '<span style="color: #1e1e1e; display: block; overflow: hidden; font-size: 16px;" >'; // tourmaster-tail
			$ret .= tourmaster_money_format($price_breakdown['sub-total-price']);
			$ret .= '</span>';
			$ret .= '<div style="clear: both;" ></div>';
			$ret .= '</div>';

			// discounted price
			if( !empty($price_breakdown['group-discounted-price']) ){
				$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1" >'; // tourmaster-invoice-price-last
				$ret .= '<span style="color: #7b7b7b; float: left; margin-left: 55%; width: 25%; font-size: 15px;" >' . esc_html__('Group Discounted Price', 'tourmaster') . '</span>'; // tourmaster-head
				$ret .= '<span style="color: #1e1e1e; display: block; overflow: hidden; font-size: 16px;" >' . tourmaster_money_format($price_breakdown['group-discounted-price']) . '</span>'; // tourmaster-tail
				$ret .= '<div style="clear: both;" ></div>';
				$ret .= '</div>';
			}

			// tax due
			if( !empty($price_breakdown['tax-due']) ){
				$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-tax
				$ret .= '<span style="color: #7b7b7b; float: left; margin-left: 55%; width: 25%; font-size: 15px;" >' . esc_html__('Tax', 'tourmaster') . '</span>'; // tourmaster-head
				$ret .= '<span style="color: #1e1e1e; display: block; overflow: hidden; font-size: 16px;" >'; // tourmaster-tail
				$ret .= tourmaster_money_format($price_breakdown['tax-due']);
				$ret .= '</span>';
				$ret .= '<div style="clear: both;" ></div>';
				$ret .= '</div>';
			}

			// paypal service fee
			if( !empty($price_breakdown['paypal-service-rate']) && !empty($price_breakdown['paypal-service-fee']) ){
				$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-last
				$ret .= '<span style="color: #7b7b7b; float: left; margin-left: 55%; width: 25%; font-size: 15px;" >' . esc_html__('Paypal Service Fee', 'tourmaster') . '</span>'; // tourmaster-head
				$ret .= '<span style="color: #1e1e1e; display: block; overflow: hidden; font-size: 16px;" >'; // tourmaster-tail
				$ret .= tourmaster_money_format($price_breakdown['paypal-service-fee']);
				$ret .= '</span>';
				$ret .= '<div style="clear: both;" ></div>';
				$ret .= '</div>';

			// credit card service fee
			}else if( !empty($price_breakdown['credit-card-service-rate']) && !empty($price_breakdown['credit-card-service-fee']) ){
				$ret .= '<div style="padding: 18px 25px; border-bottom-width: 1px; border-bottom-style: solid; border-color: #e1e1e1;" >'; // tourmaster-invoice-price-last
				$ret .= '<span style="color: #7b7b7b; float: left; margin-left: 55%; width: 25%; font-size: 15px;" >' . esc_html__('Credit Card Service Fee', 'tourmaster') . '</span>'; // tourmaster-head
				$ret .= '<span style="color: #1e1e1e; display: block; overflow: hidden; font-size: 16px;" >'; // tourmaster-tail
				$ret .= tourmaster_money_format($price_breakdown['credit-card-service-fee']);
				$ret .= '</span>';
				$ret .= '<div style="clear: both;" ></div>';
				$ret .= '</div>';
			}

			$ret .= '<div style="clear: both;" ></div>';
			$ret .= '</div>'; // tourmaster-invoice-price

			return $ret;
		} // tourmaster_get_tour_invoice_price
	}

	// enquiry form
	if( !function_exists('tourmaster_get_enquiry_form') ){
		function tourmaster_get_enquiry_form(){

			$custom_fields = tourmaster_get_option('general', 'enquiry-form-fields', '');
			if( empty($custom_fields) ){
				$enquiry_fields = array(
					'full-name' => array(
						'title' => esc_html__('Full Name', 'tourmaster'),
						'type' => 'text',
						'required' => true
					),
					'email-address' => array(
						'title' => esc_html__('Email Address', 'tourmaster'),
						'type' => 'text',
						'required' => true
					),
					'your-enquiry' => array(
						'title' => esc_html__('Your Enquiry', 'tourmaster'),
						'type' => 'textarea',
						'required' => true
					),
				);
			}else{
				$enquiry_fields = tourmaster_read_custom_fields($custom_fields);
			}

			$ret  = '<form class="tourmaster-enquiry-form tourmaster-form-field tourmaster-with-border clearfix" ';
			$ret .= ' id="tourmaster-enquiry-form" ';
			$ret .= ' data-ajax-url="' . esc_url(TOURMASTER_AJAX_URL) . '" '; 
			$ret .= ' data-action="tourmaster_send_enquiry_form" ';
			$ret .= ' data-validate-error="' . esc_attr(esc_html__('Please fill all required fields.', 'tourmaster')) . '" ';
			$ret .= ' >';
			foreach( $enquiry_fields as $slug => $enquiry_field ){
				$enquiry_field['echo'] = false;
				$enquiry_field['slug'] = $slug;
				
				$ret .= tourmaster_get_form_field($enquiry_field, 'enquiry');
			}
			$ret .= '<div class="tourmaster-enquiry-form-message" ></div>';
			$ret .= '<input type="hidden" name="tour-id" value="' . get_the_ID() . '" />';
			$ret .= '<input type="submit" class="tourmaster-button" value="' . esc_html__('Submit Enquiry', 'tourmaster') . '" />';
			$ret .= '</form>';

			return $ret;
		}
	}
	add_action('wp_ajax_tourmaster_send_enquiry_form', 'tourmaster_ajax_send_enquiry_form');
	add_action('wp_ajax_nopriv_tourmaster_send_enquiry_form', 'tourmaster_ajax_send_enquiry_form');
	if( !function_exists('tourmaster_ajax_send_enquiry_form') ){
		function tourmaster_ajax_send_enquiry_form(){

			$data = tourmaster_process_post_data($_POST['data']);

			if( !empty($data['email-address']) && is_email($data['email-address']) ){

				// send an email to admin
				$admin_mail_title = tourmaster_get_option('general', 'admin-enquiry-mail-title','');
				$admin_mail_content = tourmaster_get_option('general', 'admin-enquiry-mail-content','');
				$admin_mail_content = tourmaster_set_enquiry_data($admin_mail_content, $data);
				if( !empty($admin_mail_title) && !empty($admin_mail_content) ){
					$admin_mail_address = tourmaster_get_option('general', 'admin-email-address');

					tourmaster_mail(array(
						'recipient' => $admin_mail_address,
						'reply-to' => $data['email-address'],
						'title' => $admin_mail_title,
						'message' => tourmaster_mail_content($admin_mail_content)
					));
				}

				// send an email to customer
				$mail_title = tourmaster_get_option('general', 'enquiry-mail-title','');
				$mail_content = tourmaster_get_option('general', 'enquiry-mail-content','');
				$mail_content = tourmaster_set_enquiry_data($mail_content, $data);
				if( !empty($mail_title) && !empty($mail_content) ){
					tourmaster_mail(array(
						'recipient' => $data['email-address'],
						'title' => $mail_title,
						'message' => tourmaster_mail_content($mail_content)
					));
				}

				$ret = array(
					'status' => 'success',
					'message' => esc_html__('Your enquiry has been sent. Thank you!', 'tourmaster')
				);
			}else{
				$ret = array(
					'status' => 'failed',
					'message' => esc_html__('Invalid Email Address', 'tourmaster')
				);
			}

			die(json_encode($ret));
		}
	}
	if( !function_exists('tourmaster_set_enquiry_data') ){
		function tourmaster_set_enquiry_data( $content, $data ){
			foreach( $data as $slug => $value ){
				$content = str_replace('{' . $slug . '}', $value, $content);
			}

			if( !empty($data['tour-id']) ){
				$tour_title = '<a href="' . esc_url(get_permalink($data['tour-id'])) . '" >' . get_the_title($data['tour-id']) . '</a>';
				$content = str_replace('{tour-name}', $tour_title, $content);
			}
			return $content;
		}
	}