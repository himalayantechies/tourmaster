<?php
get_header();

	$settings = array(
		'pagination' => 'page',
		'tour-style' => tourmaster_get_option('general', 'search-page-tour-style', 'full'),
		'column-size' => tourmaster_get_option('general', 'search-page-column-size', '20'),
		'thumbnail-size' => tourmaster_get_option('general', 'search-page-thumbnail-size', 'full'),
		'tour-info' => tourmaster_get_option('general', 'search-page-tour-info', array()),
		'excerpt' => tourmaster_get_option('general', 'search-page-with-excerpt', 'specify-number'),
		'excerpt-number' => tourmaster_get_option('general', 'search-page-excerpt-number', '55'),
		'tour-rating' => tourmaster_get_option('general', 'search-page-tour-rating', 'enable'),
		'custom-pagination' => true
	);
	$settings['paged'] = (get_query_var('paged'))? get_query_var('paged') : get_query_var('page');
	$settings['paged'] = empty($settings['paged'])? 1: $settings['paged'];

	// archive query
	global $wp_query;
	$settings['query'] = $wp_query;

	// start the content
	echo '<div class="tourmaster-template-wrapper" >';
	echo '<div class="tourmaster-container" >';
	
	// sidebar content
	$sidebar_type = tourmaster_get_option('general', 'search-sidebar', 'none');
	echo '<div class="' . tourmaster_get_sidebar_wrap_class($sidebar_type) . '" >';
	echo '<div class="' . tourmaster_get_sidebar_class(array('sidebar-type'=>$sidebar_type, 'section'=>'center')) . '" >';
	echo '<div class="tourmaster-page-content" >';
	
	echo tourmaster_pb_element_tour::get_content($settings);

	echo '</div>'; // tourmaster-page-content
	echo '</div>'; // tourmaster-get-sidebar-class

	// sidebar left
	if( $sidebar_type == 'left' || $sidebar_type == 'both' ){
		$sidebar_left = tourmaster_get_option('general', 'search-sidebar-left');
		echo tourmaster_get_sidebar($sidebar_type, 'left', $sidebar_left);
	}

	// sidebar right
	if( $sidebar_type == 'right' || $sidebar_type == 'both' ){
		$sidebar_right = tourmaster_get_option('general', 'search-sidebar-right');
		echo tourmaster_get_sidebar($sidebar_type, 'right', $sidebar_right);
	}

	echo '</div>'; // tourmaster-get-sidebar-wrap-class	

	echo '</div>'; // tourmaster-container
	echo '</div>'; // tourmaster-template-wrapper

get_footer(); 

?>