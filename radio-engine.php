<?php
if (!defined('ABSPATH')) exit;

/*
========================
CRON INTERVAL
========================
*/
add_filter('cron_schedules', function($schedules) {
    $schedules['radio_minute'] = [
        'interval' => 10,
        'display'  => 'Radio every 10 seconds'
    ];
    return $schedules;
});

/*
========================
START CRON
========================
*/
add_action('init', function() {
    if (!wp_next_scheduled('radio_engine_tick')) {
        wp_schedule_event(time(), 'radio_minute', 'radio_engine_tick');
    }
});

/*
========================
ENGINE
========================
*/
add_action('radio_engine_tick', 'radio_engine');

function radio_engine() {
    $list = wp_radio_playlist();
    if (empty($list)) {
        return;
    }

    $current = intval(get_option('radio_track', 0));
    if (!isset($list[$current])) {
        $current = 0;
    }

    $started = intval(get_option('radio_started', time()));
    $file    = $list[$current]['file'];
    $duration = radio_get_duration($file);

    if (time() - $started >= $duration) {
        $new = $current;
        if (count($list) > 1) {
            while ($new == $current) {
                $new = rand(0, count($list) - 1);
            }
        }

        update_option('radio_track', $new);
        update_option('radio_started', time());
        update_option('radio_version', microtime(true));
    }
}

/*
========================
MP3 DURATION
========================
*/
function radio_get_duration($file) {
    if (!file_exists($file)) {
        return 180;
    }

    require_once(ABSPATH . WPINC . '/ID3/getid3.php');
    
    try {
        $getID3 = new getID3();
        $data = $getID3->analyze($file);
        if (isset($data['playtime_seconds'])) {
            return intval($data['playtime_seconds']);
        }
    } catch (Exception $e) {
        // Fallback duration
    }

    return 180;
}