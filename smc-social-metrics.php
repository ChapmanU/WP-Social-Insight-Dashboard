<?php
/*
Plugin Name: SMC Social Metrics
Plugin URI: 
Description: Retrieve and display social metrics including shares, likes, views, etc. 
Version: 0.1
Author: Ben Cole
Author URI: http://www.bencole.net
*/

global $smc_options;
$smc_options = get_option('socialinsight_settings');

// Include the Inside.Chapman Notification Scripts
include_once('inside-chapman-link.php'); // comment out to disable

// Retrieve the number of views for a post
function smc_get_views($post_id = 0) {
	$current_views = get_post_meta($post_id, "ga_pageviews", true);
	if(!isset($current_views) OR empty($current_views) OR !is_numeric($current_views) ) {
		$current_views = 0;
	}
	return $current_views;
}

// Connect to 3rd party services and sync stats
function smc_do_update($post_id) {

	if ($post_id <= 0) {
		return false;
	}

	global $smc_options; 
	$smc_options = get_option('socialinsight_settings');

	$permalink = get_permalink($post_id);

	// If social is being tracked, pull update
	if ($smc_options['socialinsight_options_enable_social']) {

		// Get JSON data from api.sharedcount.com
		$json = file_get_contents("http://api.sharedcount.com/?url=" . rawurlencode($permalink));
		$shared_count_service_data = json_decode($json, true);

		// Load data into stats array
		$stats = array();
		$stats['socialcount_facebook'] = $shared_count_service_data['Facebook']['total_count'];
		$stats['socialcount_twitter'] = $shared_count_service_data['Twitter'];
		$stats['socialcount_googleplus'] = $shared_count_service_data['GooglePlusOne'];
		$stats['socialcount_linkedin'] = $shared_count_service_data['LinkedIn'];
		$stats['socialcount_pinterest'] = $shared_count_service_data['Pinterest'];
		$stats['socialcount_diggs'] = $shared_count_service_data['Diggs'];
		$stats['socialcount_delicious'] = $shared_count_service_data['Delicious'];
		$stats['socialcount_reddit'] = $shared_count_service_data['Reddit'];
		$stats['socialcount_stumbleupon'] = $shared_count_service_data['StumbleUpon'];

		// There is nothing else in the $stats array YET but we will add more later. We can use the sum for now. 
		$stats['socialcount_TOTAL'] = array_sum($stats);
		update_post_meta($post_id, "socialcount_TOTAL", $stats['socialcount_TOTAL']);

		// Facebook
		if ($stats['socialcount_facebook'] > 0) 
			update_post_meta($post_id, "socialcount_facebook", $stats['socialcount_facebook']);
		// Twitter
		if ($stats['socialcount_twitter'] > 0) 
			update_post_meta($post_id, "socialcount_twitter", $stats['socialcount_twitter']);
		// Google+
		if ($stats['socialcount_googleplus'] > 0) 
			update_post_meta($post_id, "socialcount_googleplus", $stats['socialcount_googleplus']);
		// LinkedIn
		if ($stats['socialcount_linkedin'] > 0) 
			update_post_meta($post_id, "socialcount_linkedin", $stats['socialcount_linkedin']);
		// Pinterest
		if ($stats['socialcount_pinterest'] > 0) 
			update_post_meta($post_id, "socialcount_pinterest", $stats['socialcount_pinterest']);
		// Diggs
		if ($stats['socialcount_diggs'] > 0) 
			update_post_meta($post_id, "socialcount_diggs", $stats['socialcount_diggs']);
		// Delicious
		if ($stats['socialcount_delicious'] > 0) 
			update_post_meta($post_id, "socialcount_delicious", $stats['socialcount_delicious']);
		// Reddit
		if ($stats['socialcount_reddit'] > 0) 
			update_post_meta($post_id, "socialcount_reddit", $stats['socialcount_reddit']);
		// StumbleUpon
		if ($stats['socialcount_stumbleupon'] > 0) 
			update_post_meta($post_id, "socialcount_stumbleupon", $stats['socialcount_stumbleupon']);

	}

	// If analytics are being tracked, pull update
	if ($smc_options['socialinsight_options_enable_analytics']) {
		$smc_ga_token = unserialize(get_site_option('smc_ga_token'));

		if (strlen($smc_ga_token) > 1) {
			require_once ('smc-ga-query.php');

			// Execute GA API query
			$stats['ga_pageviews'] = smc_ga_getPageviewsByURL($permalink, $smc_ga_token);
			if ($stats['ga_pageviews'] > 0) {
				update_post_meta($post_id, "ga_pageviews", $stats['ga_pageviews']);
			}
		}
	}

	update_post_meta($post_id, "socialcount_LAST_UPDATED", time());

	// Custom action hook allows us to extend this function. 
	do_action('smc_social_insight_sync', $post_id, $stats);

	return $stats['socialcount_TOTAL'];
}

add_action( 'smc_update_single_post', 'smc_do_update', 10, 1 );

// Schedule an update on each individual page load
add_action("wp_head", "smc_schedule_update");
function smc_schedule_update($post_id) {
	global $smc_options;
	$ttl = $smc_options['socialinsight_options_ttl_hours'] * 3600;

	global $post;
	if ($post->post_type == 'attachment' || $post->post_status != 'publish') {
		return false;
	}

	if ($post_id <= 0) {
		$post_id = $post->ID;
	}

	if ($post_id <= 0) {
		return false;
	}

	$last_updated = get_post_meta($post_id, "socialcount_LAST_UPDATED", true);
	if ($last_updated < time() - $ttl) {

		// Schedule an update
		wp_schedule_single_event( time(), 'smc_update_single_post', array( $post_id ) );
	} 
}

// Return the social count total
function smc_get_socialcount($post_id = 0, $update = true) {
	// TTL of our local cache
	
	$total_count = 0;

	if (strlen($post_id) <= 0) {
		$post_id = get_the_id();
	}
	
	// Check if we need to schedule an update
	if ($update) {
		smc_schedule_update($post_id);
	}

	$total_count = get_post_meta($post_id, "socialcount_TOTAL", true);

	return $total_count;
}

// Admin menus
if ( is_admin() ){
	
	function smc_setup_menus () {
		global $smc_options; 
		$icon = get_option('siteurl') . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/img/smc-social-metrics-icon.png';
		add_menu_page( 'Social Insight Dashboard', 'Social Insight', $smc_options['socialinsight_options_report_visibility'], 'smc-social-insight', 'smc_social_insight_dashboard', $icon, 30 );
	}
	
	add_action('admin_menu', 'smc_setup_menus');
	
	include_once('smc-settings-setup.php');

	add_action('admin_head', 'admin_header_scripts');
	function admin_header_scripts() {
	    $siteurl = get_option('siteurl');
	    $url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/smc.css?ver=5-24-13';
	    echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
	}


	// BEGIN DASHBOARD

	function smc_social_insight_widget_setup() {
		global $smc_options;
		if (!current_user_can($smc_options['socialinsight_options_report_visibility'])) {
			return false;
		}

	    //wp_add_dashboard_widget( 'social_chapman_widget_dashboard', __( 'Test My Dashboard' ), 'social_chapman_widget_dashboard' );
	    add_meta_box( 'smc-social-insight', 'Popular stories', 'smc_social_insight_widget', 'dashboard', 'normal', 'high' );

	}	

	add_action('wp_dashboard_setup', 'smc_social_insight_widget_setup');


	function smc_social_insight_widget() {

		add_action('admin_head', 'admin_header_scripts');

		require('smc-widget-view.php');

		//Create an instance of our package class...
		$socialInsightTable = new TT_Example_List_Table();
		//Fetch, prepare, sort, and filter our data...
		$socialInsightTable->prepare_items();

		$socialInsightTable->display();
	}

	// END DASHBOARD

	
	function smc_social_insight_dashboard() {
	 	require('smc-dashboard-view.php');
	 	smc_render_dashboard_view();
	}

	function smc_social_insight_settings() {
		require('smc-settings-view.php');
		smc_render_settings_view();
	}

	/* settings link in plugin management screen */
	function smc_social_insight_settings_link($actions, $file) {
	if(false !== strpos($file, 'smc-social-metrics'))
	 $actions['settings'] = '<a href="options-general.php?page=smc_settings">Settings</a>';
	return $actions; 
	}
	add_filter('plugin_action_links', 'smc_social_insight_settings_link', 2, 2);
	

	// register_activation_hook( __FILE__, 'smc_schedule_full_update' );
	// Only works from activation?
	function smc_schedule_full_update() {
		wp_schedule_single_event( time(), 'smc_schedule_full_update_cron' );
	}

	add_action( 'smc_schedule_full_update_cron', 'smc_do_full_update', 10 );
	function smc_do_full_update() {
        // Get posts that have not ever been updated
        $querydata = query_posts(array(
            'order'=>'DESC',
            'orderby'=>'post_date',
            'posts_per_page'=>-1,
            'post_status'   => 'publish',
            'meta_query' => array(
                array(
                 'key' => 'socialcount_LAST_UPDATED',
                 'compare' => 'NOT EXISTS', // works!
                 'value' => '' // This is ignored, but is necessary...
                )
            )
        )); 
        $nextTime = time();
        foreach ($querydata as $querydatum ) {
            wp_schedule_single_event( $nextTime, 'smc_update_single_post', array( $querydatum->ID ) );
            $nextTime = $nextTime + 5;
        }

        // Get posts which HAVE been updated
         $querydata = query_posts(array(
            'order'=>'DESC',
            'orderby'=>'post_date',
            'posts_per_page'=>-1,
            'post_status'   => 'publish',
            'meta_query' => array(
                array(
                 'key' => 'socialcount_LAST_UPDATED',
                 'compare' => '>=', // works!
                 'value' => '0' // This is ignored, but is necessary...
                )
            )
        )); 

         foreach ($querydata as $querydatum ) {
             wp_schedule_single_event( $nextTime, 'smc_update_single_post', array( $querydatum->ID ) );
             $nextTime = $nextTime + 30;
         }
	}

	register_deactivation_hook( __FILE__, 'smc_uninstall' );

	function smc_uninstall() {
		delete_site_option('smc_ga_token');
		delete_option('smc_ga_token');
		delete_option('socialinsight_settings');


	    $crons = _get_cron_array();
	    if ( !empty( $crons ) ) {
		    foreach( $crons as $timestamp => $cron ) {
		        if ( ! empty( $cron['smc_update_single_post'] ) )  {
		            unset( $crons[$timestamp]['smc_update_single_post'] );
		        }
		    }
		    _set_cron_array( $crons );
		}
	}

} // end admin



?>