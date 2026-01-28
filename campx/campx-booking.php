<?php
/**
 * Plugin Name:       CampX Booking
 * Plugin URI:        https://upsidedown-webdesign.ch/
 * Description:       Modernes Buchungssystem für Campingplätze mit Ressourcen, Kapazitäten, Kalender, E-Mail-Templates, ICS und Anti-Überbuchung per DB-Tabellen.
 * Version:           1.4.3
 * Author:            Upsidedown Webdesign (Damian Trötschler)
 * Author URI:        https://upsidedown-webdesign.ch/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       campx
 * Domain Path:       /languages
 *
 * @package CampXBooking
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CAMPX_VERSION', '1.4.2' );
define( 'CAMPX_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAMPX_URL', plugin_dir_url( __FILE__ ) );

require_once CAMPX_PATH . 'includes/class-campx-plugin.php';
require_once CAMPX_PATH . 'includes/class-campx-db.php';
require_once CAMPX_PATH . 'includes/class-campx-cpt.php';
require_once CAMPX_PATH . 'includes/class-campx-admin.php';
require_once CAMPX_PATH . 'includes/class-campx-availability.php';
require_once CAMPX_PATH . 'includes/class-campx-rest.php';
require_once CAMPX_PATH . 'includes/class-campx-ics.php';
require_once CAMPX_PATH . 'includes/class-campx-frontend.php';

add_action( 'plugins_loaded', function(){
    load_plugin_textdomain('campx', false, dirname( plugin_basename(__FILE__) ) . '/languages');
    \CampX\Plugin::init();
});

register_activation_hook( __FILE__, function(){
    \CampX\DB::install();
    \CampX\CPT::register();
    \CampX\Plugin::seed_samples();
    flush_rewrite_rules();
});
register_deactivation_hook( __FILE__, function(){ flush_rewrite_rules(); });
