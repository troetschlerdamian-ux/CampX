<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class PDF {
    public static function generate_booking_pdf($booking_id, $is_admin_copy = false){
        $booking = get_post($booking_id);
        if ( ! $booking || $booking->post_type !== 'campx_booking' ) {
            return '';
        }
        $resource_id = (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $resource = get_post($resource_id);
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        $end = get_post_meta($booking_id,'_campx_end_date',true);
        $units = (int) get_post_meta($booking_id,'_campx_units',true);
        $persons = (int) get_post_meta($booking_id,'_campx_persons',true);
        $name = get_post_meta($booking_id,'_campx_customer_name',true);
        $email = get_post_meta($booking_id,'_campx_customer_email',true);
        $phone = get_post_meta($booking_id,'_campx_customer_phone',true);
        $status = get_post_meta($booking_id,'_campx_status',true) ?: 'requested';
        $price_per_night = (float) get_post_meta($resource_id,'_campx_price_per_night',true);
        $currency = \CampX\Plugin::get_settings()['currency'] ?? 'CHF';
        $nights = count(\CampX\Availability::date_range_nights($start, $end));
        $total = $price_per_night > 0 ? $price_per_night * $units * $nights : 0;

        $pdf_settings = self::get_pdf_settings();
        $context = [
            'booking_id' => $booking_id,
            'resource_name' => $resource ? $resource->post_title : '',
            'start' => $start,
            'end' => $end,
            'units' => $units,
            'persons' => $persons,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'status' => ucfirst($status),
            'nights' => $nights,
            'price_per_night' => self::format_price($price_per_night, $currency),
            'total' => self::format_price($total, $currency),
            'currency' => $currency,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'settings' => $pdf_settings,
            'is_admin_copy' => (bool) $is_admin_copy,
        ];
        $context['header_html'] = self::format_pdf_html($pdf_settings['header_text'] ?? '', $context);
        $context['body_html'] = self::format_pdf_html($pdf_settings['body_html'] ?? '', $context);
        $context['footer_html'] = self::format_pdf_html($pdf_settings['footer_text'] ?? '', $context);

        $pdf = self::build_pdf($context);
        if ( ! $pdf ) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $folder = trailingslashit($upload_dir['basedir']) . 'campx';
        if ( ! file_exists($folder) ) {
            wp_mkdir_p($folder);
        }
        $filename = sprintf('campx-booking-%d-%s.pdf', $booking_id, $is_admin_copy ? 'admin' : 'customer');
        $path = trailingslashit($folder) . $filename;
        file_put_contents($path, $pdf);
        return $path;
    }

    protected static function build_pdf(array $context){
        if ( class_exists('\Dompdf\\Dompdf') ) {
            $html = self::render_pdf_template($context);
            if ( $html !== '' ) {
                $pdf = self::build_pdf_with_dompdf($html);
                if ( $pdf !== '' ) {
                    return $pdf;
                }
            }
        }
        $lines = self::legacy_lines_from_context($context);
        return self::build_legacy_pdf($lines);
    }

    protected static function build_pdf_with_dompdf($html){
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }

    protected static function render_pdf_template(array $context){
        $settings = $context['settings'] ?? [];
        if ( isset($settings['template_html']) ) {
            $template_html = trim((string) $settings['template_html']);
            if ( $template_html !== '' ) {
                return self::format_pdf_template_html($template_html, $context);
            }
        }
        $template = trailingslashit(CAMPX_PATH) . 'templates/pdf/booking.php';
        if ( ! file_exists($template) ) {
            return '';
        }
        $data = $context;
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    protected static function legacy_lines_from_context(array $context){
        $settings = $context['settings'] ?? [];
        $primary = $settings['primary'] ?? [0, 0, 0];
        $accent = $settings['accent'] ?? [0, 0, 0];
        $notice = $settings['notice'] ?? [0, 0, 0];
        $brand = $settings['brand'] ?? '';
        return [
            ['text' => $brand, 'size' => 20, 'color' => $primary, 'max_chars' => 52],
            ['text' => __('Buchungsbestätigung','campx'), 'size' => 14, 'color' => $accent, 'max_chars' => 70],
            ['text' => ''],
            ['text' => __('Buchung','campx') . ' #' . ($context['booking_id'] ?? ''), 'size' => 12, 'color' => $primary],
            ['text' => __('Ressource','campx') . ': ' . ($context['resource_name'] ?? ''), 'max_chars' => 90],
            ['text' => __('Anreise','campx') . ': ' . ($context['start'] ?? '')],
            ['text' => __('Abreise','campx') . ': ' . ($context['end'] ?? '')],
            ['text' => __('Nächte','campx') . ': ' . ($context['nights'] ?? '')],
            ['text' => __('Einheiten','campx') . ': ' . ($context['units'] ?? '')],
            ['text' => __('Personen','campx') . ': ' . ($context['persons'] ?? '')],
            ['text' => ''],
            ['text' => __('Kunde','campx') . ': ' . ($context['customer_name'] ?? ''), 'max_chars' => 90],
            ['text' => __('E-Mail','campx') . ': ' . ($context['customer_email'] ?? ''), 'max_chars' => 90],
            ['text' => __('Telefon','campx') . ': ' . ($context['customer_phone'] ?? ''), 'max_chars' => 90],
            ['text' => ''],
            ['text' => __('Preis pro Einheit/Nacht','campx') . ': ' . ($context['price_per_night'] ?? '')],
            ['text' => __('Gesamtbetrag','campx') . ': ' . ($context['total'] ?? ''), 'size' => 13, 'color' => $primary],
            ['text' => ''],
            ['text' => __('Status','campx') . ': ' . ($context['status'] ?? '')],
            ['text' => __('NICHT BEZAHLT – Zahlung vor Ort','campx'), 'color' => $notice, 'size' => 14, 'max_chars' => 85],
        ];
    }

    protected static function build_legacy_pdf(array $lines){
        $content_lines = [];
        $content_lines[] = 'BT';
        $font_size = 12;
        $leading = self::leading_for_size($font_size);
        $content_lines[] = '/F1 ' . $font_size . ' Tf';
        $content_lines[] = $leading . ' TL';
        $content_lines[] = '50 790 Td';
        foreach ($lines as $line) {
            $text = $line['text'] ?? '';
            $color = $line['color'] ?? [0, 0, 0];
            $size = (int) ($line['size'] ?? $font_size);
            $max_chars = (int) ($line['max_chars'] ?? 0);
            if ( $size !== $font_size && $size > 0 ) {
                $font_size = $size;
                $leading = self::leading_for_size($font_size);
                $content_lines[] = '/F1 ' . $font_size . ' Tf';
                $content_lines[] = $leading . ' TL';
            }
            $content_lines[] = sprintf('%.3f %.3f %.3f rg', $color[0], $color[1], $color[2]);
            if ( $text === '' ) {
                $content_lines[] = 'T*';
                continue;
            }
            $wrapped_lines = self::wrap_text($text, $max_chars);
            foreach ($wrapped_lines as $wrapped_line) {
                $content_lines[] = '(' . self::escape_pdf_text($wrapped_line) . ') Tj';
                $content_lines[] = 'T*';
            }
        }
        $content_lines[] = 'ET';
        $content = implode("\n", $content_lines);

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = '5 0 obj << /Length ' . strlen($content) . ' >> stream' . "\n" . $content . "\n" . 'endstream endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj . "\n";
        }
        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . count($offsets) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size " . count($offsets) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";
        return $pdf;
    }

    protected static function escape_pdf_text($text){
        $text = (string) $text;
        if ( function_exists('iconv') ) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ( $converted !== false ) {
                $text = $converted;
            }
        }
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = str_replace(["\r", "\n"], ' ', $text);
        return $text;
    }

    protected static function format_price($amount, $currency){
        $amount = number_format((float) $amount, 2, '.', "'");
        return $amount . ' ' . $currency;
    }

    protected static function leading_for_size($size){
        return (int) ceil($size * 1.4);
    }

    protected static function wrap_text($text, $max_chars = 0){
        $text = trim((string) $text);
        if ( $text === '' ) {
            return [''];
        }
        if ( $max_chars <= 0 ) {
            return [$text];
        }
        $wrapped = wordwrap($text, $max_chars, "\n", true);
        return explode("\n", $wrapped);
    }

    protected static function get_pdf_settings(){
        $settings = \CampX\Plugin::get_settings();
        $brand = trim((string) ($settings['pdf_brand_name'] ?? ''));
        if ( $brand === '' ) {
            $brand = get_bloginfo('name');
        }
        $primary_hex = self::normalize_hex_color($settings['pdf_primary'] ?? '', $settings['primary'] ?? '#5b7fff');
        $accent_hex = self::normalize_hex_color($settings['pdf_accent'] ?? '', $settings['accent'] ?? '#00c2a8');
        $notice_hex = self::normalize_hex_color($settings['pdf_notice'] ?? '', $settings['danger'] ?? '#e74c3c');
        $logo_id = (int) ($settings['pdf_logo_id'] ?? 0);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
        $logo_max_width = max(0, (int) ($settings['pdf_logo_max_width'] ?? 200));
        $logo_align = (string) ($settings['pdf_logo_align'] ?? 'left');
        if ( ! in_array($logo_align, ['left', 'center', 'right'], true) ) {
            $logo_align = 'left';
        }
        return [
            'brand' => $brand,
            'primary' => self::hex_to_rgb($primary_hex, $settings['primary'] ?? '#5b7fff'),
            'accent' => self::hex_to_rgb($accent_hex, $settings['accent'] ?? '#00c2a8'),
            'notice' => self::hex_to_rgb($notice_hex, $settings['danger'] ?? '#e74c3c'),
            'primary_hex' => $primary_hex,
            'accent_hex' => $accent_hex,
            'notice_hex' => $notice_hex,
            'header_text' => (string) ($settings['pdf_header_text'] ?? ''),
            'body_html' => (string) ($settings['pdf_body_html'] ?? ''),
            'template_html' => (string) ($settings['pdf_template_html'] ?? ''),
            'footer_text' => (string) ($settings['pdf_footer_text'] ?? ''),
            'logo_id' => $logo_id,
            'logo_url' => $logo_url,
            'logo_max_width' => $logo_max_width,
            'logo_align' => $logo_align,
        ];
    }

    protected static function format_pdf_html($html, array $context){
        $html = trim((string) $html);
        if ( $html === '' ) {
            return '';
        }
        $html = self::apply_placeholders($html, $context);
        $html = wp_kses_post($html);
        return wpautop($html);
    }

    protected static function format_pdf_template_html($html, array $context){
        $html = trim((string) $html);
        if ( $html === '' ) {
            return '';
        }
        $html = self::apply_placeholders($html, $context);
        $allowed = wp_kses_allowed_html('post');
        $allowed['style'] = [];
        $allowed['html'] = [];
        $allowed['head'] = [];
        $allowed['body'] = [];
        $allowed['meta'] = ['charset' => true];
        return wp_kses($html, $allowed);
    }

    protected static function apply_placeholders($text, array $context){
        $map = self::placeholder_map($context);
        foreach ( $map as $key => $value ) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    protected static function placeholder_map(array $context){
        return [
            'booking_id' => esc_html((string) ($context['booking_id'] ?? '')),
            'resource_name' => esc_html((string) ($context['resource_name'] ?? '')),
            'start' => esc_html((string) ($context['start'] ?? '')),
            'end' => esc_html((string) ($context['end'] ?? '')),
            'units' => esc_html((string) ($context['units'] ?? '')),
            'persons' => esc_html((string) ($context['persons'] ?? '')),
            'customer_name' => esc_html((string) ($context['customer_name'] ?? '')),
            'customer_email' => esc_html((string) ($context['customer_email'] ?? '')),
            'customer_phone' => esc_html((string) ($context['customer_phone'] ?? '')),
            'status' => esc_html((string) ($context['status'] ?? '')),
            'nights' => esc_html((string) ($context['nights'] ?? '')),
            'price_per_night' => esc_html((string) ($context['price_per_night'] ?? '')),
            'total' => esc_html((string) ($context['total'] ?? '')),
            'currency' => esc_html((string) ($context['currency'] ?? '')),
            'site_name' => esc_html((string) ($context['site_name'] ?? '')),
            'site_url' => esc_url((string) ($context['site_url'] ?? '')),
            'logo_url' => esc_url((string) ($context['settings']['logo_url'] ?? '')),
            'logo_align' => esc_html((string) ($context['settings']['logo_align'] ?? '')),
            'logo_max_width' => esc_html((string) ($context['settings']['logo_max_width'] ?? '')),
        ];
    }

    protected static function normalize_hex_color($hex, $fallback_hex){
        $hex = trim((string) $hex);
        if ( $hex === '' ) {
            $hex = $fallback_hex;
        }
        $hex = ltrim($hex, '#');
        if ( strlen($hex) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen($hex) !== 6 || preg_match('/[^0-9a-fA-F]/', $hex) ) {
            $hex = ltrim((string) $fallback_hex, '#');
        }
        return '#' . strtolower($hex);
    }

    protected static function hex_to_rgb($hex, $fallback_hex){
        $hex = trim((string) $hex);
        if ( $hex === '' ) {
            $hex = $fallback_hex;
        }
        $hex = ltrim($hex, '#');
        if ( strlen($hex) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if ( strlen($hex) !== 6 || preg_match('/[^0-9a-fA-F]/', $hex) ) {
            $hex = ltrim($fallback_hex, '#');
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        return [round($r, 3), round($g, 3), round($b, 3)];
    }
}
