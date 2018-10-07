<?php
	/*	
	*	Goodlayers Item For Page Builder
	*/

	add_action('plugins_loaded', 'tourmaster_add_pb_element_tour_search');
	if( !function_exists('tourmaster_add_pb_element_tour_search') ){
		function tourmaster_add_pb_element_tour_search(){

			if( class_exists('gdlr_core_page_builder_element') ){
				gdlr_core_page_builder_element::add_element('tour_search', 'tourmaster_pb_element_tour_search'); 
			}
			
		}
	}
	
	if( !class_exists('tourmaster_pb_element_tour_search') ){
		class tourmaster_pb_element_tour_search{
			
			// get the element settings
			static function get_settings(){
				return array(
					'icon' => 'fa-plane',
					'title' => esc_html__('Tour Search', 'tourmaster')
				);
			}
			
			// return the element options
			static function get_options(){
				return apply_filters('tourmaster_tour_item_options', array(		
					'general' => array(
						'title' => esc_html__('General', 'tourmaster'),
						'options' => array(
							'title' => array(
								'title' => esc_html__('Title', 'tourmaster'),
								'type' => 'text',
							),
							'fields' => array(
								'title' => esc_html__('Select Fields', 'tourmaster'),
								'type' => 'multi-combobox',
								'options' => tourmaster_get_tour_search_fields(),
								'description' => esc_html__('You can use Ctrl/Command button to select multiple items or remove the selected item. Leave this field blank to select all items in the list.', 'tourmaster'),
							),
							'style' => array(
								'title' => esc_html__('Style', 'tourmaster'),
								'type' => 'combobox',
								'options' => array(
									'column' => esc_html__('Column', 'tourmaster'),
									'half' => esc_html__('Half', 'tourmaster'),
									'full' => esc_html__('Full', 'tourmaster'),
								)
							),
							'enable-rating-field' => array(
								'title' => esc_html__('Enable Rating', 'tourmaster'),
								'type' => 'checkbox',
								'default' => 'disable',
								'condition' => array( 'style' => 'full' )
							),
							'filters' => array(
								'title' => esc_html__('Select Filter', 'tourmaster'),
								'type' => 'multi-combobox',
								'options' => tourmaster_get_tour_search_fields('custom'),
								'condition' => array( 'style' => 'full' ),
								'description' => esc_html__('You can use Ctrl/Command button to select multiple items or remove the selected item.', 'tourmaster'),
							),
							'with-frame' => array(
								'title' => esc_html__('Item Frame', 'tourmaster'),
								'type' => 'combobox',
								'options' => array(
									'disable' => esc_html__('Disable', 'tourmaster'),
									'enable' => esc_html__('Color Background', 'tourmaster'),
									'image' => esc_html__('Image Background', 'tourmaster'),
								),
								'default' => 'enable'
							),
							'frame-background-color' => array(
								'title' => esc_html__('Frame Background Color', 'tourmaster'),
								'type' => 'colorpicker',
								'condition' => array( 'with-frame' => 'enable' )
							),
							'frame-background-image' => array(
								'title' => esc_html__('Frame Background Image', 'tourmaster'),
								'type' => 'upload',
								'condition' => array( 'with-frame' => 'image' )
							)
						)
					),			
					'spacing' => array(
						'title' => esc_html('Spacing', 'tourmaster'),
						'options' => array(
							'padding-bottom' => array(
								'title' => esc_html__('Padding Bottom ( Item )', 'tourmaster'),
								'type' => 'text',
								'data-input-type' => 'pixel',
								'default' => '30px'
							)
						)
					),
				));
			}

			// get the preview for page builder
			static function get_preview( $settings = array() ){
				$content  = self::get_content($settings);
				return $content;
			}			

			// get the content from settings
			static function get_content( $settings = array() ){
				
				// default variable
				$settings = empty($settings)? array(): $settings;

				$settings['style'] = empty($settings['style'])? 'column': $settings['style'];
				$extra_class = 'tourmaster-style-' . $settings['style'];
				
				// set the field variable
				$fields = empty($settings['fields'])? array(): $settings['fields'];
				if( !is_array($fields) ){
					$fields = array_map('trim', explode(',', $fields));
				}
				$extra_class .= ' tourmaster-column-count-' . (sizeof($fields) + 1);
				$extra_class .= empty($settings['no-pdlr'])? ' tourmaster-item-pdlr': '';

				$ret  = '<div class="tourmaster-tour-search-item clearfix ' . esc_attr($extra_class) . '" ';
				if( !empty($settings['padding-bottom']) && $settings['padding-bottom'] != '30px' ){
					$ret .= tourmaster_esc_style(array('padding-bottom'=>$settings['padding-bottom']));
				}
				if( !empty($settings['id']) ){
					$ret .= ' id="' . esc_attr($settings['id']) . '" ';
				}
				$ret .= ' >';

				$with_frame = (empty($settings['with-frame']) || $settings['with-frame'] != 'disable')? true: false;
				$ret .= '<div class="tourmaster-tour-search-wrap ' . ($with_frame? 'tourmaster-with-frame': '') . '" ';
				if( empty($settings['with-frame']) || $settings['with-frame'] == 'enable' ){
					if( !empty($settings['frame-background-color']) ){
						$ret .= tourmaster_esc_style(array('background-color'=>$settings['frame-background-color']));
					}
				}else if( $settings['with-frame'] == 'image' ){
					if( !empty($settings['frame-background-image']) ){
						$ret .= tourmaster_esc_style(array('background-image'=>$settings['frame-background-image']));
					}
				}
				$ret .=' >';

				// for tour search page
				if( isset($_GET['tour-search']) ){
					global $tourmaster_found_posts;

					$ret .= '<div class="tourmaster-tour-search-item-head" >';
					$ret .= '<h3 class="tourmaster-tour-search-item-head-title">';
					$ret .= '<i class="fa fa-search" ></i>';
					$ret .= esc_html__('Search Results', 'tourmaster');
					$ret .= '</h3>';

					$ret .= '<div class="tourmaster-tour-search-item-head-caption" >';
					$ret .= sprintf(esc_html__('%d Results Found', 'tourmaster'), $tourmaster_found_posts);
					$ret .= '</div>';
					$ret .= '</div>';

					$ret .= '<div class="tourmaster-tour-search-item-divier" ></div>';
				}


				if( !empty($settings['title']) ){
					$ret .= '<h3 class="tourmaster-tour-search-title" >' . tourmaster_text_filter($settings['title']) . '</h3>';
				} 

				$form_tag = is_admin()? 'div': 'form';
				$action_url = tourmaster_get_template_url('search');
				$ret .= '<' . $form_tag . ' class="tourmaster-form-field tourmaster-with-border" action="' . esc_url($action_url) . '" method="GET" >';
				
				// keywords
				if( empty($fields) || in_array('keywords', $fields) ){
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-keywords" >';
					$ret .= '<label>' . esc_html__('Keywords', 'tourmaster') . '</label>';
					$ret .= '<div class="tourmaster-tour-search-field-inner" >';
					$ret .= '<input name="tour-search" type="text" value="' . (empty($_GET['tour-search'])? '': esc_attr($_GET['tour-search'])) . '" />';
					$ret .= '</div>';
					$ret .= '</div>';
				}else{
					$ret .= '<input name="tour-search" type="hidden" value="" />';
				}
				
				// tour_category
				if( empty($fields) || in_array('tour_category', $fields) ){
					$tour_category = array_merge(
						array('' => esc_html__('Any', 'tourmaster')),
						tourmaster_get_term_list('tour_category')
					);
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-tour-category" >';
					$ret .= '<label>' . esc_html__('Category', 'tourmaster') . '</label>';
					$ret .= self::get_combobox('tour_category', $tour_category);
					$ret .= '</div>';				
				}

				// tour_tax
				$tax_fields = array( 'tour_tag' => esc_html__('Tag', 'tourmaster') );
				$tax_fields = $tax_fields + tourmaster_get_custom_tax_list();
				foreach( $tax_fields as $tax_field => $tax_title ){
					if( empty($fields) || in_array($tax_field, $fields) ){
						$location = array_merge(
							array('' => esc_html__('Any', 'tourmaster')),
							tourmaster_get_term_list($tax_field)
						);
						$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-tax" >';
						$ret .= '<label>' . $tax_title . '</label>';
						$ret .= self::get_combobox($tax_field, $location);
						$ret .= '</div>';				
					}
				}

				// tour duration
				if( empty($fields) || in_array('duration', $fields) ){
					$duration = array(
						'' => esc_html__('Any', 'tourmaster'), 
						'1' => esc_html__('1 Day Tour', 'tourmaster'), 
						'2' => esc_html__('2-4 Days Tour', 'tourmaster'), 
						'5' => esc_html__('5-7 Days Tour', 'tourmaster'), 
						'7' => esc_html__('7+ Days Tour', 'tourmaster'), 
					);
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-duration" >';
					$ret .= '<label>' . esc_html__('Duration', 'tourmaster') . '</label>';
					$ret .= self::get_combobox('duration', $duration);
					$ret .= '</div>';
				}

				// date
				if( empty($fields) || in_array('date', $fields) ){
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-date" >';
					$ret .= '<label>' . esc_html__('Date', 'tourmaster') . '</label>';
					$ret .= '<div class="tourmaster-datepicker-wrap" >';
					$ret .= '<input class="tourmaster-datepicker" type="text"  ';
					$ret .= 'placeholder="' . esc_html__('Any', 'tourmaster') . '" ';
					$ret .= 'data-date-format="' . esc_attr(tourmaster_get_option('general', 'datepicker-date-format', 'd M yy')) . '" />';
					$ret .= '<input class="tourmaster-datepicker-alt" name="date" type="hidden" />';
					$ret .= '</div>';
					$ret .= '</div>';
				}

				// min-price
				if( empty($fields) || in_array('min-price', $fields) ){
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-min-price" >';
					$ret .= '<label>' . esc_html__('Min Price', 'tourmaster') . '</label>';
					$ret .= '<input name="min-price" type="text" />';
					$ret .= '</div>';
				}

				// max-price
				if( empty($fields) || in_array('max-price', $fields) ){
					$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-max-price" >';
					$ret .= '<label>' . esc_html__('Max Price', 'tourmaster') . '</label>';
					$ret .= '<input name="max-price" type="text" />';
					$ret .= '</div>';
				}

				$query_var = parse_url($action_url, PHP_URL_QUERY);
				parse_str($query_var, $query_args);
				if( !empty($query_args) ){
					foreach( $query_args as $query_slug => $query_value ){
						$ret .= '<input type="hidden" name="' . esc_attr($query_slug) . '" value="' . esc_attr($query_value) . '" />';
					} 				
				} 

				if( $settings['style'] == 'full' ){

					// enable rating
					if( !empty($settings['enable-rating-field']) && $settings['enable-rating-field'] == 'enable' ){
						$rating = empty($_GET['rating'])? 0: intval($_GET['rating']);

						$ret .= '<div class="tourmaster-tour-search-field tourmaster-tour-search-field-rating clearfix" >';
						$ret .= '<label>' . esc_html__('Rating', 'tourmaster') . '</label>';

						for( $i = 0; $i <= 10; $i += 2 ){
							if( $i <= 10 ){
								$ret .= '<span class="tourmaster-rating-select" data-rating-score="' . esc_attr($i) . '" ></span>';
							}
							if( $i + 1 <= 10 ){
								$ret .= '<i class="tourmaster-rating-select fa ';
								if( $i + 1 > $rating ){
									$ret .= 'fa-star-o';
								}else if( $i + 1 == $rating ){
									$ret .= 'fa-star-half-empty';
								}else if( $i + 1 < $rating ){
									$ret .= 'fa-star';
								}
								$ret .= '" data-rating-score="' . esc_attr($i + 1) . '" ></i>';
							}
						}

						$ret .= '<input type="hidden" name="rating" value="0" />';
						$ret .= '<span class="tourmaster-tail" >' . esc_html__('or more', 'tourmaster') . '</span>';
						$ret .= '</div>';
					}

					// type filter
					if( !empty($settings['filters']) ){
						$ret .= '<div class="tourmaster-tour-search-item-divier" ></div>';

						$ret .= '<div class="tourmaster-tour-search-type-filter" >';
						$ret .= '<h3 class="tourmaster-type-filter-title">';
						$ret .= esc_html__('Type Filter', 'tourmaster');
						$ret .= '<i class="fa fa-plus-circle" ></i>';
						$ret .= '</h3>';
						
						$ret .= '<div class="tourmaster-type-filter-item-wrap" >';
						foreach( $settings['filters'] as $filter ){
							$taxonomy = get_taxonomy($filter);
							$term_list = tourmaster_get_term_list($filter);
							$filter_val = empty($_GET[$filter])? array(): $_GET[$filter]; 

							$ret .= '<div class="tourmaster-type-filter-item" >';
							$ret .= '<h5 class="tourmaster-type-filter-item-title" >' . $taxonomy->label . '</h5>';
							foreach( $term_list as $term_slug => $term ){
								$ret .= '<label class="tourmaster-type-filter-term" >';
								$ret .= '<input type="checkbox" name="' . esc_attr($filter) . '[]" value="' . esc_attr($term_slug) . '" ';
								$ret .= in_array($term_slug, $filter_val)? 'checked': '';
								$ret .= ' />';
								$ret .= '<span class="tourmaster-type-filter-display" >';
								$ret .= '<i class="fa fa-check-circle-o" ></i>';
								$ret .= '<span class="tourmaster-head" >' . $term . '</span>';
								$ret .= '</span>';
								$ret .= '</label>';
							}
							$ret .= '</div>'; // tourmaster-type-filter-item
						}
						$ret .= '</div>'; // tourmaster-type-filter-item-wrap
						$ret .= '</div>'; // tourmaster-tour-search-type-filter
					}
				}

				$ret .= '<input class="tourmaster-tour-search-submit" type="submit" value="' . esc_html__('Search', 'tourmaster') . '" />';
				$ret .= '</' . $form_tag . '>';

				$ret .= '</div>'; // tourmaster-tour-search-wrap
				$ret .= '</div>'; // tourmaster-tour-search-item
				
				return $ret;
			}		

			// combobox
			static function get_combobox( $name, $options, $value = '' ){
				
				$ret  = '<div class="tourmaster-combobox-wrap" >';
				$ret .= '<select name="' . esc_attr($name) . '" >';
				foreach( $options as $option_slug => $option_title ){
					$ret .= '<option value="' . esc_attr($option_slug) . '" ';
					$ret .= (!empty($_GET[$name]) && $_GET[$name] == $option_slug)? ' selected': '';
					$ret .= ' >' . esc_html($option_title) . '</option>';
				}
				$ret .= '</select>';
				$ret .= '</div>';

				return $ret;
			}	
			
		} // tourmaster_pb_element_tour
	} // class_exists	

	add_shortcode('tourmaster_tour_search', 'tourmaster_tour_search_shortcode');
	if( !function_exists('tourmaster_tour_search_shortcode') ){
		function tourmaster_tour_search_shortcode($atts){
			$atts = wp_parse_args($atts, array());
			
			$ret  = '<div class="tourmaster-tour-search-shortcode clearfix tourmaster-item-rvpdlr" >';
			$ret .= tourmaster_pb_element_tour_search::get_content($atts);
			$ret .= '</div>';

			return $ret;
		}
	}