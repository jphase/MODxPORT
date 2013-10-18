<?php
	// Error reporting
	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	// MODx config
	$modx_db_user = 'root';
	$modx_db_pass = '';
	$modx_db_name = 'modx_database';
	$modx_db_host = 'localhost';
	$modx_db_table = 'modx_site_content';

	// WordPress config
	$wp_db_user = 'root';
	$wp_db_pass = '';
	$wp_db_name = 'wordpress_database';
	$wp_db_host = 'localhost';
	$wp_db_table = 'wp_posts';
	$wp_import_type = 'page';

	// Require wp-load and initialize wpdb class
	require_once('wp-load.php');
	$modx = new wpdb($modx_db_user, $modx_db_pass, $modx_db_name, $modx_db_host);
	$wp = new wpdb($wp_db_user, $wp_db_pass, $wp_db_name, $wp_db_host);

	// Get MODx content
	$modx_content = $modx->get_results("SELECT * FROM `$modx_db_table`");

	// Parse results
	if(is_array($modx_content) && count($modx_content)) {
		// Setup some arrays to map IDs and parent IDs
		$ids = $parents = array();
		foreach($modx_content as $c) {
			// Setup our post array for insertion into WP posts table
			$post = array(
				'comment_status' => 'closed',
				'ping_status'    => 'open',
				'post_author'    => 1,
				'post_content'   => $c->content,
				'post_date'      => date('Y-m-d H:i:s', strftime($c->createdon)),
				'post_date_gmt'  => date('Y-m-d H:i:s', strftime($c->createdon)),
				'post_name'      => $c->alias,
				'post_status'    => 'publish',
				'post_title'     => $c->pagetitle,
				'post_type'      => $wp_import_type
			);
			// Insert our post
			$insertion = wp_insert_post($post);
			// Push data onto our ids array
			if($insertion) $ids[] = $insertion;
			// Push data onto our parents array
			if($c->parent && $insertion) $parents[] = array($insertion => $c->parent);
			// Debug message
			echo "Inserted MODx {$c->type} {$c->id} as post ID $insertion<br>";
		}
		// Loop through all our inserted posts and setup our parent/child relationships
		$wp_content = $wp->get_results("SELECT * FROM `$wp_db_table` WHERE `ID` IN (" . implode(',', $ids) . ")");
		if(is_array($wp_content) && count($wp_content)) {
			foreach($wp_content as $w) {
				if(in_array($w->ID, $ids)) {
					// Update the parent
					$wp->query("UPDATE `$wp_db_table` SET `post_parent` = " . $parents[$w->ID] . " WHERE `ID` = " . $w->ID);
					// Debug message
					echo "Updated parent to " . $parents[$w->ID] . " on post ID {$w->ID}<br>";
				}
			}
		}
	}