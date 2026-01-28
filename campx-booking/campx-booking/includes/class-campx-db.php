<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class DB {
    public static function install(){
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $bookings = $wpdb->prefix . 'campx_bookings';
        $occ = $wpdb->prefix . 'campx_occupancy';

        $sql1 = "CREATE TABLE IF NOT EXISTS $bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            resource_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            units INT NOT NULL DEFAULT 1,
            persons INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'requested',
            customer_name VARCHAR(190) NULL,
            customer_email VARCHAR(190) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY resource_id (resource_id),
            KEY booking_id (booking_id),
            KEY status (status),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset;";

        $sql2 = "CREATE TABLE IF NOT EXISTS $occ (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            resource_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            booking_id BIGINT UNSIGNED NOT NULL,
            units INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'requested',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq (resource_id, date, booking_id),
            KEY resource_date (resource_id, date),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
    }

    public static function nights( $start, $end ){
        $out = [];
        try { $s = new \DateTime($start); $e = new \DateTime($end); } catch(\Exception $ex){ return $out; }
        while( $s < $e ){ $out[] = $s->format('Y-m-d'); $s->modify('+1 day'); }
        return $out;
    }

    public static function reserve_occupancy( $resource_id, $booking_id, $start, $end, $units, $status='requested' ){
        global $wpdb;
        $occ = $wpdb->prefix . 'campx_occupancy';
        foreach( self::nights($start,$end) as $d ){
            $res = $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO $occ (resource_id,date,booking_id,units,status) VALUES (%d,%s,%d,%d,%s)",
                $resource_id, $d, $booking_id, max(1,(int)$units), $status
            ));
            if ( $res === false ) return false;
        }
        return true;
    }

    public static function free_occupancy( $booking_id ){
        global $wpdb;
        $occ = $wpdb->prefix . 'campx_occupancy';
        $wpdb->delete( $occ, [ 'booking_id' => (int) $booking_id ], [ '%d' ] );
    }

    public static function update_occupancy_status( $booking_id, $status ){
        if ( in_array($status, ['declined','expired'], true) ){
            self::free_occupancy( $booking_id );
            return;
        }
        global $wpdb;
        $occ = $wpdb->prefix . 'campx_occupancy';
        $wpdb->update( $occ, [ 'status' => $status ], [ 'booking_id' => (int)$booking_id ], [ '%s' ], [ '%d' ] );
    }

    public static function booked_units_on( $resource_id, $date ){
        global $wpdb;
        $occ = $wpdb->prefix . 'campx_occupancy';
        $sum = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(units),0) FROM $occ WHERE resource_id=%d AND date=%s AND status IN ('requested','accepted')",
            $resource_id, $date
        ));
        return $sum;
    }
}
