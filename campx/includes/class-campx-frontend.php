<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Frontend {
    public static function init(){
        add_action('wp_enqueue_scripts', [__CLASS__,'assets']);
        add_shortcode('campx_booking_form', [__CLASS__,'shortcode_form']);
        add_shortcode('campx_calendar', [__CLASS__,'shortcode_calendar']);
        add_shortcode('campx_catalog', [__CLASS__,'shortcode_catalog']);
    }

    public static function assets(){
        wp_register_style('campx-frontend', CAMPX_URL.'assets/css/frontend.css', [], CAMPX_VERSION);
        wp_register_script('campx-frontend', CAMPX_URL.'assets/js/frontend.js', [], CAMPX_VERSION, true);
        $s = Plugin::get_settings();
        $thankyou = ($p = intval($s['thankyou_page_id'] ?? 0)) ? get_permalink($p) : '';
        wp_localize_script('campx-frontend', 'CampX', [
            'rest' => esc_url_raw( rest_url('campx/v1') ),
            'settings' => ['months'=>intval($s['calendar_months'] ?? 2), 'datepicker'=>true, 'thankyou_url'=>$thankyou]
        ]);
        wp_enqueue_style('campx-frontend');
        if ( function_exists('wp_add_inline_style') ) { wp_add_inline_style('campx-frontend', \CampX\Plugin::color_css_vars() ); }
        wp_enqueue_script('campx-frontend');
    }

    public static function shortcode_form($atts){
        $a = shortcode_atts(['id'=>0], $atts);
        $rid = intval($a['id']);
        ob_start();
        $resource = $rid ? get_post($rid) : null;
        include CAMPX_PATH . 'templates/form.php';
        return ob_get_clean();
    }

    public static function shortcode_calendar($atts){
        $a = shortcode_atts(['id'=>0], $atts);
        $rid = intval($a['id']);
        if ( ! $rid ) return '';
        ob_start();
        include CAMPX_PATH . 'templates/calendar.php';
        return ob_get_clean();
    }

    public static function shortcode_catalog($atts){
        $a = shortcode_atts(['ids'=>''], $atts);
        $ids = array_filter(array_map('absint', explode(',', $a['ids'])));
        if ( empty($ids) ){
            $ids = wp_list_pluck( get_posts(['post_type'=>'campx_resource','numberposts'=>-1]), 'ID' );
        }
        ob_start();
        include CAMPX_PATH . 'templates/catalog.php';
        return ob_get_clean();
    }
}
