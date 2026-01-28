<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class CPT {
    public static function register(){
        register_post_type( 'campx_resource', [
            'labels' => [
                'name' => __('Ressourcen','campx'),
                'singular_name' => __('Ressource','campx'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title','editor','thumbnail','excerpt'],
        ]);

        register_post_type( 'campx_booking', [
            'labels' => [
                'name' => __('Buchungen','campx'),
                'singular_name' => __('Buchung','campx'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
        ]);
    }
}
