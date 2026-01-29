<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin {
    public static function init(){
        load_plugin_textdomain( 'campx', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
        add_action( 'init', [ '\CampX\CPT', 'register' ] );
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_action( 'init', [ '\CampX\Admin', 'init' ] );
        add_action( 'init', [ '\CampX\Frontend', 'init' ] );
        add_action( 'init', [ '\CampX\Ajax', 'init' ] );
        add_action( 'rest_api_init', [ '\CampX\Rest', 'register_routes' ] );
        add_action( 'init', [ '\CampX\ICS', 'listen' ] );
        add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
    }

    public static function register_query_vars( $vars ) {
        $vars[] = 'campx_ics';
        $vars[] = 'id';
        $vars[] = 'token';
        return $vars;
    }

    public static function add_rewrite_rules(){
        add_rewrite_rule( '^campx\.ics$', 'index.php?campx_ics=all', 'top' );
        add_rewrite_rule( '^campx-([A-Za-z0-9_-]+)\.ics$', 'index.php?campx_ics=all&token=$matches[1]', 'top' );
        add_rewrite_rule( '^campx-resource-([^/]+)\.ics$', 'index.php?campx_ics=resource&id=$matches[1]', 'top' );
        add_rewrite_rule( '^campx-booking-([0-9]+)\.ics$', 'index.php?campx_ics=booking&id=$matches[1]', 'top' );
    }

    public static function get_settings(){
        $defaults = [
            'primary' => '#5b7fff',
            'accent'  => '#00c2a8',
            'success' => '#2ecc71',
            'danger'  => '#e74c3c',
            'bg'      => '#ffffff',
            'admin_email' => get_option('admin_email'),
            'request_expires_hours' => 48,
            'calendar_months' => 2,
            'thankyou_page_id' => 0,
            'wipe_on_uninstall' => 0,
            'ics_token' => '',
        ];
        $opt = get_option('campx_settings', []);
        if ( ! is_array($opt) ) { $opt = []; }
        return wp_parse_args( $opt, $defaults );
    }

    public static function color_css_vars(){
        $s = self::get_settings();
        $keys = ['primary','accent','success','danger','bg'];
        $vars = ':root{';
        foreach($keys as $k){ $vars .= '--campx-'.$k.': '.esc_html($s[$k]).';'; }
        return $vars.'}';
    }

    public static function upload_image_from_plugin( $rel_path ){
        $file = CAMPX_PATH . ltrim($rel_path,'/');
        if ( ! file_exists($file) ) return 0;
        $upload = wp_upload_bits( basename($file), null, file_get_contents($file) );
        if ( $upload['error'] ) return 0;
        $wp_filetype = wp_check_filetype( $upload['file'], null );
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name( basename($file) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];
        $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return $attach_id;
    }

    public static function seed_samples(){
        // Only seed if no resources exist
        $existing = get_posts([ 'post_type'=>'campx_resource', 'posts_per_page'=>1, 'post_status'=>'any' ]);
        if ( $existing ) return;

        $samples = [
            [
                'title'=>'Stellplatz Zelt',
                'excerpt'=>'Grüne Stellplätze mitten in der Natur. Ideal für Zelte bis 6 Personen.',
                'type'=>'parcel','capacity'=>10,'unit_label'=>__('Platz','campx'),
                'min_stay'=>2,'max_per_booking'=>2,'max_persons'=>6,'img'=>'assets/img/tent.png'
            ],
            [
                'title'=>'Doppelzimmer',
                'excerpt'=>'Komfortables Doppelzimmer mit eigenem Bad.',
                'type'=>'room','capacity'=>5,'unit_label'=>__('Zimmer','campx'),
                'min_stay'=>1,'max_per_booking'=>1,'max_persons'=>2,'img'=>'assets/img/double-room.png'
            ],
            [
                'title'=>'Einzelzimmer',
                'excerpt'=>'Praktisches Zimmer für Alleinreisende.',
                'type'=>'room','capacity'=>5,'unit_label'=>__('Zimmer','campx'),
                'min_stay'=>1,'max_per_booking'=>1,'max_persons'=>1,'img'=>'assets/img/single-room.png'
            ],
            [
                'title'=>'Schlafsaal',
                'excerpt'=>'Großer Schlafsaal für Gruppen bis 30 Personen.',
                'type'=>'other','capacity'=>30,'unit_label'=>__('Bett','campx'),
                'min_stay'=>1,'max_per_booking'=>6,'max_persons'=>30,'img'=>'assets/img/dorm.png'
            ],
        ];
        foreach($samples as $s){
            $pid = wp_insert_post([
                'post_type'=>'campx_resource','post_status'=>'publish','post_title'=>$s['title'],'post_excerpt'=>$s['excerpt'],'post_content'=>''
            ]);
            if ( $pid ){
                update_post_meta($pid, '_campx_resource_type', $s['type'] );
                update_post_meta($pid, '_campx_capacity', $s['capacity'] );
                update_post_meta($pid, '_campx_unit_label', $s['unit_label'] );
                update_post_meta($pid, '_campx_min_stay', $s['min_stay'] );
                update_post_meta($pid, '_campx_max_per_booking', $s['max_per_booking'] );
                update_post_meta($pid, '_campx_max_persons', $s['max_persons'] );
                // attach image
                $att_id = \CampX\Plugin::upload_image_from_plugin( $s['img'] );
                if ( $att_id ) { set_post_thumbnail( $pid, $att_id ); }
            }
        }
    }
}
