<?php
	/*
	Plugin Name: Tour Master
	Plugin URI: 
	Description: Tour management system plugin
	Version: 3.0.6
	Author: Goodlayers
	Author URI: http://www.goodlayers.com
	License: 
	*/

	// define necessary variable for the site.
	define('TOURMASTER_URL', plugins_url('', __FILE__));
	define('TOURMASTER_LOCAL', dirname(__FILE__));
	define('TOURMASTER_AJAX_URL', admin_url('admin-ajax.php'));

	include_once(TOURMASTER_LOCAL . '/framework/framework.php');

	include_once(TOURMASTER_LOCAL . '/include/plugin-init.php');
	include_once(TOURMASTER_LOCAL . '/include/plugin-option.php');
	include_once(TOURMASTER_LOCAL . '/include/tour-option.php');
	include_once(TOURMASTER_LOCAL . '/include/tour-filter.php');
	include_once(TOURMASTER_LOCAL . '/include/tour-coupon.php');
	include_once(TOURMASTER_LOCAL . '/include/tour-service.php');
	include_once(TOURMASTER_LOCAL . '/include/order.php');

	include_once(TOURMASTER_LOCAL . '/include/tour-util.php');
	include_once(TOURMASTER_LOCAL . '/include/payment-util.php');
	include_once(TOURMASTER_LOCAL . '/include/user-util.php');
	include_once(TOURMASTER_LOCAL . '/include/review-util.php');
	include_once(TOURMASTER_LOCAL . '/include/table-util.php');
	include_once(TOURMASTER_LOCAL . '/include/mail-util.php');
	include_once(TOURMASTER_LOCAL . '/include/shortcodes-list.php');
	include_once(TOURMASTER_LOCAL . '/include/shortcodes.php');
	include_once(TOURMASTER_LOCAL . '/include/utility.php');

	include_once(TOURMASTER_LOCAL . '/include/template-settings.php');
	include_once(TOURMASTER_LOCAL . '/include/paypal.php');
	include_once(TOURMASTER_LOCAL . '/include/hblpay.php'); //HBL Pay file included
	
	include_once(TOURMASTER_LOCAL . '/include/pb/tour-style.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/tour-item.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/pb-element-content-navigation.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/pb-element-tour.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/pb-element-tour-review.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/pb-element-tour-search.php');
	include_once(TOURMASTER_LOCAL . '/include/pb/pb-element-tour-category.php');

	include_once(TOURMASTER_LOCAL . '/include/widget/tour-widget.php');
	include_once(TOURMASTER_LOCAL . '/include/widget/tour-category-widget.php');
	include_once(TOURMASTER_LOCAL . '/include/widget/tour-search-widget.php');

	///////////// payment ///////////////
	include_once(TOURMASTER_LOCAL . '/include/stripe/stripe.php');
	include_once(TOURMASTER_LOCAL . '/include/paymill/paymill.php');
	include_once(TOURMASTER_LOCAL . '/include/authorize/authorize.php');
	
	// add activation hook
	register_activation_hook(__FILE__, 'tourmaster_plugin_activation');
	register_deactivation_hook(__FILE__, 'tourmaster_plugin_deactivation');

	// load text domain for localization
	add_action('plugins_loaded', 'tourmaster_load_textdomain');
	if( !function_exists('tourmaster_load_textdomain') ){
		function tourmaster_load_textdomain() {
		  	load_plugin_textdomain('tourmaster', false, plugin_basename(dirname(__FILE__)) . '/languages'); 
		}
	}	
	
	// enqueue necessay style/script
	if( !is_admin() ){ 
		add_action('wp_enqueue_scripts', 'tourmaster_enqueue_script'); 
	}else{
		// for front end
		add_action('gdlr_core_front_script', 'tourmaster_enqueue_script');
	}
	if( !function_exists('tourmaster_enqueue_script') ){
		function tourmaster_enqueue_script(){
			tourmaster_enqueue_icon();

			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-datepicker');
			wp_enqueue_script('jquery-effects-core');

			wp_enqueue_style('tourmaster-style', TOURMASTER_URL . '/tourmaster.css');
			if( is_rtl() ){
				wp_enqueue_style('tourmaster-style-rtl', TOURMASTER_URL . '/tourmaster-rtl.css');
			}

			wp_enqueue_style('tourmaster-custom-style', tourmaster_get_style_custom());
			
			wp_enqueue_script('tourmaster-script', TOURMASTER_URL . '/tourmaster.js', array('jquery'), false, true);

			// for localization of the datepicker
			// ref : https://gist.github.com/clubduece/4053820
			global $wp_locale;
			$aryArgs = array(
				'closeText'         => esc_html__('Done', 'tourmaster'),
				'currentText'       => esc_html__('Today', 'tourmaster'),
				'monthNames'        => tourmaster_strip_array_indices($wp_locale->month),
				'monthNamesShort'   => tourmaster_strip_array_indices($wp_locale->month_abbrev),
				'dayNames'          => tourmaster_strip_array_indices($wp_locale->weekday),
				'dayNamesShort'     => tourmaster_strip_array_indices($wp_locale->weekday_abbrev),
				'dayNamesMin'       => tourmaster_strip_array_indices($wp_locale->weekday_initial),
				'firstDay'          => get_option('start_of_week')
			);
			wp_localize_script( 'tourmaster-script', 'TMi18n', $aryArgs );
		}
	}
	if( !function_exists('tourmaster_strip_array_indices') ){
		function tourmaster_strip_array_indices( $arrayToStrip ){
			$newArray = array();
			foreach( $arrayToStrip as $objArrayItem){
				$newArray[] =  $objArrayItem;
			}

			return $newArray;
		}
	}	

	// add tourmaster to body class
	add_filter('body_class', 'tourmaster_body_class');
	if( !function_exists('tourmaster_body_class') ){
		function tourmaster_body_class( $classes ){
			$classes[] = 'tourmaster-body';

			return $classes;
		}
	}

	// add_action('init', 'tourmaster_list_category_thumbnail');
	if( !function_exists('tourmaster_list_category_thumbnail') ){
		function tourmaster_list_category_thumbnail(){
			$categories = get_categories(array(
				'taxonomy'=>'tour_category', 
				'hide_empty'=>0,
				'number'=>999
			));

			$tour_tax = array();
			foreach( $categories as $category ){
				$term_meta = get_term_meta($category->term_id, 'thumbnail', true);
				$tour_tax[$category->slug] = $term_meta;
			}

			print_r(json_encode($tour_tax));

		}
	}