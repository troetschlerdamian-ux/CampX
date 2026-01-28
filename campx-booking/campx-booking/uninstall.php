<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$opts = get_option('campx_settings', []);
$wipe = is_array($opts) && ! empty($opts['wipe_on_uninstall']);

delete_option('campx_settings');
delete_option('campx_email_templates');

if ( ! $wipe ) return;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}campx_occupancy" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}campx_bookings" );

$posts = get_posts([ 'post_type'=>['campx_booking','campx_resource'], 'numberposts'=>-1, 'post_status'=>'any' ]);
foreach($posts as $p){ wp_delete_post($p->ID, true); }
