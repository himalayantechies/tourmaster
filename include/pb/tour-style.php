<?php
	/*	
	*	Goodlayers Blog Item Style
	*/
	
	if( !class_exists('tourmaster_tour_style') ){
		class tourmaster_tour_style{

			// get the content of the tour item
			function get_content( $args ){

				$ret = apply_filters('tourmaster_tour_style_content', '', $args, $this);
				if( !empty($ret) ) return $ret;

				switch( $args['tour-style'] ){
					case 'modern':
					case 'modern-no-space': 
						return $this->tour_modern( $args ); 
						break;
					case 'grid':
					case 'grid-no-space': 
						return $this->tour_grid( $args ); 
						break;	
					case 'medium': 
						return $this->tour_medium( $args ); 
						break;
					case 'full': 
						return $this->tour_full( $args ); 
						break;
					case 'widget': 
						return $this->tour_widget( $args ); 
						break;
				}
				
			}

			// get blog excerpt
			function get_excerpt( $excerpt_length, $excerpt_more = ' [&hellip;]' ) {

				$post = get_post();
				if( empty($post) || post_password_required() ){ return ''; }
			
				$excerpt = $post->post_excerpt;
				if( empty($excerpt) ){
					$excerpt = get_the_content('');
					$excerpt = strip_shortcodes($excerpt);
					
					$excerpt = apply_filters('the_content', $excerpt);
					$excerpt = str_replace(']]>', ']]&gt;', $excerpt);
				}
				
				$excerpt_more = apply_filters('excerpt_more', $excerpt_more);
				$excerpt = wp_trim_words($excerpt, $excerpt_length, $excerpt_more);

				$excerpt = apply_filters('wp_trim_excerpt', $excerpt, $post->post_excerpt);		
				$excerpt = apply_filters('get_the_excerpt', $excerpt);
				
				return $excerpt;
			}

			function tour_excerpt( $args ){

				$ret = '';

				if( $args['excerpt'] == 'specify-number' ){
					if( !empty($args['excerpt-number']) ){
						$ret = '<div class="tourmaster-tour-content" >' . $this->get_excerpt($args['excerpt-number']) . '</div>';
					}
				}else if( $args['excerpt'] != 'none' ){
					$ret = '<div class="tourmaster-tour-content" >' . tourmaster_content_filter(get_the_content(), true) . '</div>';
				}	

				return $ret;
			}			

			// get the portfolio title
			function tour_title( $args ){

				$ret  = '<h3 class="tourmaster-tour-title gdlr-core-skin-title" ' . tourmaster_esc_style(array(
					'font-size' => empty($args['tour-title-font-size'])? '': $args['tour-title-font-size'],
					'font-weight' => empty($args['tour-title-font-weight'])? '': $args['tour-title-font-weight'],
					'letter-spacing' => empty($args['tour-title-letter-spacing'])? '': $args['tour-title-letter-spacing'],
					'text-transform' => (empty($args['tour-title-text-transform']) || $args['tour-title-text-transform'] == 'uppercase')? '': $args['portfolio-title-text-transform'],
					'margin-bottom' => empty($args['tour-title-bottom-margin'])? '': $args['tour-title-bottom-margin']
				)) . ' >';
				$ret .= '<a href="' . get_permalink() . '" >' . get_the_title() . '</a>';
				$ret .= '</h3>';


				return $ret;
			}

			// get tour thumbnail
			function get_thumbnail( $args, $has_content = true ){
				
				$ret = '';
				
				$feature_image = get_post_thumbnail_id();
				if( !empty($feature_image) ){
					$ret .= '<div class="tourmaster-tour-thumbnail tourmaster-media-image" >';
					$ret .= '<a href="' . get_permalink() . '" >';
					$ret .= tourmaster_get_image($feature_image, $args['thumbnail-size']);
					$ret .= '</a>';
					$ret .= $this->get_tour_ribbon( $args );
					$ret .= '</div>';

				}

				return $ret;
			}

			// get tour ribbon
			function get_tour_ribbon( $args = array() ){
				$ret = '';
				$post_meta = tourmaster_get_post_meta(get_the_ID(), 'tourmaster-tour-option');

				if( (empty($args['enable-ribbon']) || $args['enable-ribbon'] == 'enable') && !empty($post_meta['promo-text']) ){
					$ret  = '<div class="tourmaster-thumbnail-ribbon gdlr-core-outer-frame-element" ' . tourmaster_esc_style(array(
						'color' => empty($post_meta['promo-text-ribbon-text-color'])? '': $post_meta['promo-text-ribbon-text-color'],
						'background-color' => empty($post_meta['promo-text-ribbon-background'])? '': $post_meta['promo-text-ribbon-background'],
					)) .' >';
					$ret .= '<div class="tourmaster-thumbnail-ribbon-cornor" ' . tourmaster_esc_style(array(
						'border-right-color' => empty($post_meta['promo-text-ribbon-background'])? '': array($post_meta['promo-text-ribbon-background'], 0.5),
					)) .' ></div>';
					$ret .= $post_meta['promo-text'];
					$ret .= '</div>';
				}

				return $ret;
			}

			// tour rating
			function get_rating( $style = 'widget' ){

				$rating = get_post_meta(get_the_ID(), 'tourmaster-tour-rating', true);
				if( empty($rating) ){ return ''; }
				
				
				if( !empty($rating['reviewer']) ){
					$ret  = '<div class="tourmaster-tour-rating" >';
					$score = intval($rating['score']) / intval($rating['reviewer']);

					if( $style == 'plain' ){
						$ret .= '<span class="tourmaster-tour-rating-text" >';
						$ret .= $rating['reviewer'] . ' ';
						$ret .= (intval($rating['reviewer']) > 1)? esc_html__('Reviews', 'tourmaster'): esc_html__('Review', 'tourmaster');
						$ret .= '</span>';
					}

					$ret .= tourmaster_get_rating($score);

					if( $style == 'widget' ){
						$ret .= '<span class="tourmaster-tour-rating-text" >(';
						$ret .= $rating['reviewer'] . ' ';
						$ret .= (intval($rating['reviewer']) > 1)? esc_html__('Reviews', 'tourmaster'): esc_html__('Review', 'tourmaster');
						$ret .= ')</span>';
					}
					$ret .= '</div>';
				}else{
					$ret  = '<div class="tourmaster-tour-rating tourmaster-tour-rating-empty" >0</div>';
				}

				return $ret;

			}

			// tour price
			function get_price( $settings = array() ){	

				$ret = '';
				$post_meta = tourmaster_get_post_meta(get_the_ID(), 'tourmaster-tour-option');
				$extra_class = empty($post_meta['tour-price-discount-text'])? '': 'tourmaster-discount';

				if( !empty($post_meta['tour-price-text']) || !empty($post_meta['tour-price-discount-text']) ){
					$ret  .= '<div class="tourmaster-tour-price-wrap ' . esc_attr($extra_class) . '" >';
					if( !empty($post_meta['tour-price-text']) ){
						$ret .= '<span class="tourmaster-tour-price" >';
						$ret .= '<span class="tourmaster-head">' . esc_html__('From', 'tourmaster') . '</span>';
						$ret .= '<span class="tourmaster-tail">' . tourmaster_money_format($post_meta['tour-price-text'], 0) . '</span>';
						$ret .= '</span>';
					}

					if( !empty($post_meta['tour-price-discount-text']) ){
						$ret .= '<span class="tourmaster-tour-discount-price" >';
						$ret .= tourmaster_money_format($post_meta['tour-price-discount-text'], 0);
						$ret .= '</span>';
					}

					if( !empty($settings['with-info']) ){
						$ret .= '<span class="fa fa-info-circle tourmaster-tour-price-info" data-rel="tipsy" title="';
						$ret .= esc_html__('The initial price based on 1 adult with the lowest price in low season', 'tourmaster');
						$ret .= '" >';
						$ret .= '</span>';
					}
					$ret .= '</div>';
				}
				

				return $ret;

			}

			// tour info
			function get_info( $options = array(), $args = array() ){

				$ret = '';
				$post_meta = tourmaster_get_post_meta(get_the_ID(), 'tourmaster-tour-option');

				foreach( $options as $type ){
					switch( $type ){
						case 'custom-excerpt': 
							if( !empty($post_meta['custom-excerpt']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-custom-excerpt ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= tourmaster_content_filter($post_meta['custom-excerpt']);
								$ret .= '</div>';
							} 
							break; 

						case 'duration-text': 
							if( !empty($post_meta['duration-text']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-duration-text ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="icon_clock_alt" ></i>';
								$ret .= tourmaster_text_filter($post_meta['duration-text']);
								$ret .= '</div>';
							} 
							break;

						case 'availability': 
							if( !empty($post_meta['date-range']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-availability ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="fa fa-calendar" ></i>';
								$ret .= esc_html__('Availability :', 'tourmaster') . ' ';
								$ret .= tourmaster_text_filter($post_meta['date-range']);
								$ret .= '</div>';
							} 
							break;

						case 'departure-location': 
							if( !empty($post_meta['departure-location']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-departure-location ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="flaticon-takeoff-the-plane" ></i>';
								$ret .= tourmaster_text_filter($post_meta['departure-location']);
								$ret .= '</div>';
							} 
							break;

						case 'return-location':
							if( !empty($post_meta['return-location']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-return-location ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="flaticon-plane-landing" ></i>';
								$ret .= tourmaster_text_filter($post_meta['return-location']);
								$ret .= '</div>';
							} 
							break; 

						case 'minimum-age': 
							if( !empty($post_meta['minimum-age']) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-minimum-age ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="fa fa-user" ></i>';
								$ret .= esc_html__('Min Age :', 'tourmaster') . ' ';
								$ret .= tourmaster_text_filter($post_meta['minimum-age']);
								$ret .= '</div>';
							} 
							break; 

						case 'maximum-people':
							$maximum_people = get_post_meta(get_the_ID(), 'tourmaster-max-people', true);
							if( !empty($maximum_people) ){
								$ret .= '<div class="tourmaster-tour-info tourmaster-tour-info-maximum-people ' . (empty($args['info-class'])? '': esc_attr($args['info-class'])) . '" >';
								$ret .= '<i class="fa fa-users" ></i>';
								$ret .= esc_html__('Max People :', 'tourmaster') . ' ';
								$ret .= tourmaster_text_filter($maximum_people);
								$ret .= '</div>';
							} 
							break; 
					}
				}

				if( empty($args['no-wrapper']) ){
					$ret = '<div class="tourmaster-tour-info-wrap clearfix" >' . $ret . '</div>';
				}

				return $ret;
			}

			// tour widget
			function tour_widget( $args ){

				$ret  = '<div class="tourmaster-item-list tourmaster-tour-widget tourmaster-item-pdlr" >';
				$ret .= '<div class="tourmaster-tour-widget-inner clearfix" >';

				$args['enable-ribbon'] = 'disable';
				$args['thumbnail-size'] = 'thumbnail';
				$ret .= $this->get_thumbnail($args);

				$ret .= '<div class="tourmaster-tour-content-wrap" >';
				$ret .= $this->tour_title($args);

				$args['enable-ribbon'] = 'enable';
				$ribbon = $this->get_tour_ribbon($args);
				$ret .= '<div class="tourmaster-tour-content-info clearfix ' . (empty($ribbon)? '': 'tourmaster-with-ribbon') . '" >';
				$ret .= $ribbon;

				$ret .= $this->get_price();
				$ret .= '</div>'; // tourmaster-tour-content-info 
				$ret .= '</div>'; // tourmaster-tour-content-wrap 

				$ret .= '</div>'; // tourmaster-tour-widget-inner
				$ret .= '</div>'; // tourmaster-tour-widget
				
				return $ret;
			} 

			// tour full
			function tour_full( $args ){

				$extra_class = ( !empty($args['with-frame']) && $args['with-frame'] == 'enable' )? 'tourmaster-tour-frame': '';

				$ret  = '<div class="tourmaster-item-list tourmaster-tour-full tourmaster-item-pdlr clearfix ' . esc_attr($extra_class) . '" >';
				$ret .= $this->get_thumbnail($args);

				$ret .= '<div class="tourmaster-tour-content-wrap clearfix ' . (empty($extra_class)? '': 'gdlr-core-skin-e-background') . '" >';
				$ret .= '<div class="tourmaster-content-left" >';
				$ret .= $this->tour_title($args);

				// tour info
				if( !empty($args['tour-info']) ){
					$ret .= $this->get_info($args['tour-info']);
				}

				// excerpt
				$ret .= $this->tour_excerpt($args);
				$ret .= '</div>'; // tourmaster-content-left

				$ret .= '<div class="tourmaster-content-right tourmaster-center-tour-content" >';
				
				// price
				$ret .= $this->get_price();

				// rating
				if( !empty($args['tour-rating']) && $args['tour-rating'] == 'enable' ){
					$ret .= $this->get_rating();
				} 

				$ret .= '<a class="tourmaster-tour-view-more" href="' . get_permalink() . '" >' . esc_html__('View Details', 'tourmaster') . '</a>';
				$ret .= '</div>'; // tourmaster-tour-content-right
				$ret .= '</div>'; // tourmaster-tour-content-wrap 

				$ret .= '</div>'; // tourmaster-tour-full
				
				return $ret;
			} 

			// tour medium
			function tour_medium( $args ){

				$extra_class = ( !empty($args['with-frame']) && $args['with-frame'] == 'enable' )? 'tourmaster-tour-frame gdlr-core-skin-e-background': '';

				$ret  = '<div class="tourmaster-item-list tourmaster-tour-medium tourmaster-item-mglr clearfix ' . esc_attr($extra_class) . '" >';
				$ret .= '<div class="tourmaster-tour-medium-inner" >';
				$ret .= $this->get_thumbnail($args);

				$ret .= '<div class="tourmaster-tour-content-wrap clearfix" >';
				$ret .= '<div class="tourmaster-content-left" >';
				$ret .= $this->tour_title($args);

				// tour info
				if( !empty($args['tour-info']) ){
					$ret .= $this->get_info($args['tour-info']);
				}

				// excerpt
				$ret .= $this->tour_excerpt($args);
				$ret .= '</div>'; // tourmaster-content-left

				$ret .= '<div class="tourmaster-content-right tourmaster-center-tour-content" >';
				// price
				$ret .= $this->get_price();

				// rating
				if( !empty($args['tour-rating']) && $args['tour-rating'] == 'enable' ){
					$ret .= $this->get_rating();
				} 

				$ret .= '<a class="tourmaster-tour-view-more" href="' . get_permalink() . '" >' . esc_html__('View Details', 'tourmaster') . '</a>';
				$ret .= '</div>'; // tourmaster-tour-content-right
				$ret .= '</div>'; // tourmaster-tour-content-wrap 

				$ret .= '</div>'; // tourmaster-tour-medium-inner
				$ret .= '</div>'; // tourmaster-tour-medium
				
				return $ret;
			} 
			
			// tour modern
			function tour_modern( $args ){
				
				$args['enable-ribbon'] = 'disable';
				$thumbnail = $this->get_thumbnail($args, false);
				$args['enable-ribbon'] = 'enable';
				$extra_class = empty($thumbnail)? 'tourmaster-without-thumbnail': 'tourmaster-with-thumbnail';

				// info
				$tour_info = '';
				if( !empty($args['tour-info']) ){
					$tour_info = $this->get_info($args['tour-info']);
				}
				if( !empty($tour_info) ){
					$extra_class .= ' tourmaster-with-info';
				}else{
					$extra_class .= ' tourmaster-without-info';
				}

				$ret  = '<div class="tourmaster-tour-modern ' . esc_attr($extra_class) . '" >';
				$ret .= $this->get_tour_ribbon($args);
				$ret .= '<div class="tourmaster-tour-modern-inner" >';
				$ret .= $thumbnail;
				$ret .= '<div class="tourmaster-tour-content-wrap" >';
				$ret .= $this->tour_title($args);

				// price
				$ret .= $this->get_price();

				$ret .= $tour_info;

				$ret .= '</div>'; // tourmaster-tour-content
				$ret .= '</div>'; // tourmaster-tour-modern-inner
				$ret .= '</div>'; // tourmaster-tour-modern
				
				return $ret;
			} 
			
			// tour grid
			function tour_grid( $args ){
				
				$extra_class  = ( !empty($args['with-frame']) && $args['with-frame'] == 'enable' )? 'tourmaster-tour-frame': '';

				$args['price-position'] = empty($args['price-position'])? 'right-title': $args['price-position'];
				$extra_class .= ' tourmaster-price-' . $args['price-position'];

				$ret  = '<div class="tourmaster-tour-grid ' . esc_attr($extra_class) . '" >';
				$ret .= $this->get_thumbnail($args);

				$ret .= '<div class="tourmaster-tour-content-wrap ' . (empty($extra_class)? '': 'gdlr-core-skin-e-background') . '" >';
				$ret .= $this->tour_title($args);

				// price
				if( $args['price-position'] != 'bottom-bar' ){
					$ret .= $this->get_price();
				}

				// info
				if( !empty($args['tour-info']) ){
					$ret .= $this->get_info($args['tour-info']);
				}

				// excerpt
				$ret .= $this->tour_excerpt($args);

				// rating
				if( !empty($args['tour-rating']) && $args['tour-rating'] == 'enable' ){
					$ret .= $this->get_rating();
				} 
				$ret .= '</div>'; // tourmaster-tour-content-wrap

				// price
				if( $args['price-position'] == 'bottom-bar' ){
					$post_meta = tourmaster_get_post_meta(get_the_ID(), 'tourmaster-tour-option');

					if( !empty($post_meta['tour-price-text']) ){
						$ret .= '<div class="tourmaster-tour-price-bottom-wrap clearfix ' . (empty($post_meta['tour-price-discount-text'])? '': 'tourmaster-with-discount') . '" >';
						$ret .= '<span class="tourmaster-tour-price-head" >' . esc_html__('From', 'tourmaster') . '</span>';
						$ret .= '<span class="tourmaster-tour-price-content" >';
						$ret .= '<span class="tourmaster-tour-price">' . tourmaster_money_format($post_meta['tour-price-text'], 0) . '</span>';
						if( !empty($post_meta['tour-price-discount-text']) ){
							$ret .= '<span class="tourmaster-tour-discount-price" >';
							$ret .= tourmaster_money_format($post_meta['tour-price-discount-text'], 0);
							$ret .= '</span>';
						}
						$ret .= '</span>'; // tourmaster-tour-price-content
						$ret .= '</div>'; // tourmaster-tour-price-bottom-wrap
					}
				}

				$ret .= '</div>'; // tourmaster-tour-grid
				
				return $ret;
			} 				
			
		} // tourmaster_tour_style
	} // class_exists
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	