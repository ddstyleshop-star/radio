<?php
/*
Plugin Name: WP Live Radio
Description: 24/7 live radio engine
Version: 1.1
Author: Denis & Antigravity
*/

if (!defined('ABSPATH')) exit;

define('RADIO_PATH', WP_CONTENT_DIR . '/uploads/songs/');
define('RADIO_URL', content_url('/uploads/songs/'));

require_once plugin_dir_path(__FILE__) . 'radio-engine.php';

/*
========================
PLAYLIST HELPER
========================
*/
function wp_radio_playlist() {
    $list = [];
    if (!is_dir(RADIO_PATH)) {
        return $list;
    }
    
    foreach (scandir(RADIO_PATH) as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp3') {
            $list[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'url'  => RADIO_URL . $file,
                'file' => RADIO_PATH . $file
            ];
        }
    }
    
    return array_values($list);
}

/*
========================
INIT VALUES
========================
*/
function wp_radio_start() {
    if (get_option('radio_track') === false) {
        update_option('radio_track', 0);
        update_option('radio_started', time());
        update_option('radio_version', time());
    }
}
add_action('init', 'wp_radio_start');

/*
========================
AJAX STATUS
========================
*/
function radio_status() {
    $list = wp_radio_playlist();
    if (empty($list)) {
        wp_send_json_error();
    }
    
    $track = intval(get_option('radio_track', 0));
    if (!isset($list[$track])) {
        $track = 0;
    }
    
    $start = intval(get_option('radio_started', time()));
    
    wp_send_json([
        'name'     => $list[$track]['name'],
        'url'      => $list[$track]['url'],
        'position' => time() - $start,
        'version'  => get_option('radio_version', 1)
    ]);
}
add_action('wp_ajax_radio_status', 'radio_status');
add_action('wp_ajax_nopriv_radio_status', 'radio_status');

/*
========================
ADMIN PANEL
========================
*/
add_action('admin_menu', function() {
    add_menu_page(
        'Live Radio',
        'Live Radio',
        'manage_options',
        'live-radio',
        'radio_admin',
        'dashicons-format-audio'
    );
});

function radio_admin() {
    $list = wp_radio_playlist();
    $current = intval(get_option('radio_track', 0));
    ?>
    <div class="wrap">
        <h1>🔴 Live Radio Studio</h1>
        <h2>Сейчас в эфире: <strong><?php echo isset($list[$current]) ? esc_html($list[$current]['name']) : 'Эфир пуст'; ?></strong></h2>
        
        <table class="widefat striped" style="margin-top: 20px; max-width: 600px;">
            <thead>
                <tr>
                    <th>Название песни</th>
                    <th style="width: 120px;">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="2">Загрузите MP3 файлы в папку: <code>wp-content/uploads/songs/</code></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($list as $id => $song): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($song['name']); ?></strong>
                            </td>
                            <td>
                                <button class="button <?php echo $id === $current ? 'button-primary' : ''; ?> radio-start" data-id="<?php echo $id; ?>">
                                    <?php echo $id === $current ? '⚡ В эфире' : '▶ В эфир'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    document.querySelectorAll('.radio-start').forEach(btn => {
        btn.onclick = function() {
            const trackId = this.dataset.id;
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=radio_set_track&track=' + trackId
            })
            .then(r => r.text())
            .then(() => location.reload());
        }
    });
    </script>
    <?php
}

add_action('wp_ajax_radio_set_track', function() {
    if (!current_user_can('manage_options')) {
        wp_die();
    }
    
    $id = intval($_POST['track']);
    update_option('radio_track', $id);
    update_option('radio_started', time());
    update_option('radio_version', microtime(true));
    
    echo 'OK';
    wp_die();
});

/*
========================
SHORTCODE
========================
*/
function live_radio() {
    ?>
    <div class="live-radio-card">
        <div class="live-radio-header">
            <span class="live-radio-indicator" id="radio-indicator"></span>
            <span>прямой эфир</span>
        </div>
        
        <div class="radio-visualizer" id="radio-visualizer">
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
        </div>

        <div class="live-radio-title" id="radio-track-title">Подключение...</div>

        <button class="live-radio-play-btn" id="radio-play-btn">▶ Слушать</button>

        <div class="live-radio-volume-container">
            <span class="volume-icon" id="volume-icon">🔊</span>
            <input class="live-radio-volume" id="radio-volume-slider" type="range" min="0" max="1" step="0.01" value="0.8">
        </div>

        <audio id="radio-audio-a" preload="auto"></audio>
        <audio id="radio-audio-b" preload="auto"></audio>
    </div>

    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

    .live-radio-card {
        background: rgba(18, 18, 24, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #ffffff;
        padding: 30px;
        border-radius: 24px;
        max-width: 380px;
        margin: 20px auto;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
        text-align: center;
    }
    
    .live-radio-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 20px;
        color: rgba(255, 255, 255, 0.6);
    }
    
    .live-radio-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #555;
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }
    
    .live-radio-indicator.active {
        background: #FF2E93;
        box-shadow: 0 0 12px #FF2E93;
        animation: pulse-dot 1.5s infinite alternate;
    }
    
    @keyframes pulse-dot {
        0% { opacity: 0.6; }
        100% { opacity: 1; }
    }
    
    .radio-visualizer {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 4px;
        height: 30px;
        width: 50px;
        margin: 15px auto;
    }
    
    .radio-visualizer .bar {
        width: 4px;
        background: #555;
        height: 5px;
        border-radius: 2px;
        transition: height 0.2s ease, background-color 0.3s ease;
    }
    
    .radio-visualizer.playing .bar {
        animation: equalize 1.2s infinite ease-in-out alternate;
    }
    
    .radio-visualizer.playing .bar:nth-child(1) { animation-duration: 0.8s; background: #FF2E93; }
    .radio-visualizer.playing .bar:nth-child(2) { animation-duration: 1.1s; background: #FF8A00; }
    .radio-visualizer.playing .bar:nth-child(3) { animation-duration: 0.9s; background: #00F0FF; }
    .radio-visualizer.playing .bar:nth-child(4) { animation-duration: 1.2s; background: #7000FF; }
    .radio-visualizer.playing .bar:nth-child(5) { animation-duration: 0.7s; background: #00FF66; }
    
    @keyframes equalize {
        0% { height: 5px; }
        100% { height: 30px; }
    }
    
    .live-radio-title {
        font-size: 18px;
        font-weight: 600;
        margin: 10px 0;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .live-radio-play-btn {
        background: linear-gradient(135deg, #FF2E93 0%, #FF8A00 100%);
        border: none;
        color: white;
        font-size: 15px;
        font-weight: 650;
        padding: 14px 44px;
        border-radius: 50px;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(255, 46, 147, 0.3);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        margin-top: 15px;
    }
    
    .live-radio-play-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(255, 46, 147, 0.5);
        filter: brightness(1.1);
    }
    
    .live-radio-play-btn:active {
        transform: translateY(1px);
    }
    
    .live-radio-play-btn.active {
        background: linear-gradient(135deg, #00F0FF 0%, #7000FF 100%);
        box-shadow: 0 8px 20px rgba(0, 240, 255, 0.3);
    }
    
    .live-radio-volume-container {
        margin-top: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
    }
    
    .volume-icon {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.5);
        user-select: none;
    }
    
    .live-radio-volume {
        -webkit-appearance: none;
        appearance: none;
        width: 150px;
        height: 6px;
        border-radius: 3px;
        background: rgba(255, 255, 255, 0.15);
        outline: none;
    }
    
    .live-radio-volume::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #ffffff;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        transition: transform 0.1s ease;
    }
    
    .live-radio-volume::-webkit-slider-thumb:hover {
        transform: scale(1.25);
    }
    </style>

    <script>
    (function() {
        const audioA = document.getElementById('radio-audio-a');
        const audioB = document.getElementById('radio-audio-b');
        const playBtn = document.getElementById('radio-play-btn');
        const titleEl = document.getElementById('radio-track-title');
        const indicatorEl = document.getElementById('radio-indicator');
        const visualizerEl = document.getElementById('radio-visualizer');
        const volumeSlider = document.getElementById('radio-volume-slider');
        const volumeIcon = document.getElementById('volume-icon');

        let activeAudio = audioA;
        let currentVersion = null;
        let userVolume = parseFloat(volumeSlider.value);

        function updateVolumeIcon(vol) {
            if (vol === 0 || activeAudio.muted) {
                volumeIcon.innerText = '🔇';
            } else if (vol < 0.4) {
                volumeIcon.innerText = '🔈';
            } else if (vol < 0.7) {
                volumeIcon.innerText = '🔉';
            } else {
                volumeIcon.innerText = '🔊';
            }
        }

        function checkSoundPlaying() {
            const isSoundActive = activeAudio && 
                                 !activeAudio.paused && 
                                 !activeAudio.muted && 
                                 activeAudio.volume > 0;
            
            if (isSoundActive) {
                playBtn.innerText = '🔴 Эфир';
                playBtn.classList.add('active');
                indicatorEl.classList.add('active');
                visualizerEl.classList.add('playing');
            } else {
                playBtn.innerText = '▶ Слушать';
                playBtn.classList.remove('active');
                indicatorEl.classList.remove('active');
                visualizerEl.classList.remove('playing');
            }
            updateVolumeIcon(userVolume);
        }

        // Attach listeners to both players
        [audioA, audioB].forEach(audio => {
            ['play', 'playing', 'timeupdate', 'pause', 'volumechange'].forEach(evt => {
                audio.addEventListener(evt, checkSoundPlaying);
            });
        });

        // Crossfade Logic
        function crossfadeTo(newUrl, newPosition, newVersion) {
            currentVersion = newVersion;
            
            const oldAudio = activeAudio;
            const newAudio = (activeAudio === audioA) ? audioB : audioA;

            // Reset and prepare new audio
            newAudio.src = newUrl;
            newAudio.volume = 0;
            newAudio.muted = oldAudio.muted; // Maintain current mute state
            
            newAudio.onloadedmetadata = function() {
                newAudio.currentTime = newPosition;
                newAudio.play().then(() => {
                    activeAudio = newAudio;
                    
                    const fadeDuration = 1500;
                    const steps = 15;
                    const stepTime = fadeDuration / steps;
                    let currentStep = 0;
                    const oldStartVol = oldAudio.volume;

                    const fadeInterval = setInterval(() => {
                        currentStep++;
                        let ratio = currentStep / steps;
                        
                        oldAudio.volume = Math.max(0, oldStartVol * (1 - ratio));
                        newAudio.volume = userVolume * ratio;
                        
                        if (currentStep >= steps) {
                            clearInterval(fadeInterval);
                            oldAudio.pause();
                            oldAudio.src = '';
                        }
                    }, stepTime);
                }).catch(err => {
                    console.warn("Crossfade play blocked:", err);
                    activeAudio = newAudio;
                    newAudio.volume = userVolume;
                });
            };
        }

        // Connection Logic
        function radioConnect(forcePlay = false) {
            fetch("<?php echo admin_url('admin-ajax.php');?>?action=radio_status")
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.url) return;
                    
                    titleEl.innerText = "🎵 " + data.name;

                    if (currentVersion === null) {
                        currentVersion = data.version;
                        activeAudio.src = data.url;
                        activeAudio.volume = userVolume;
                        activeAudio.muted = !forcePlay; // Mute on load unless interactively triggered
                        
                        activeAudio.onloadedmetadata = function() {
                            activeAudio.currentTime = data.position;
                            
                            // Attempt unmuted play first
                            activeAudio.muted = false;
                            activeAudio.play().catch(e => {
                                console.log("Unmuted autoplay blocked. Trying muted autoplay...");
                                activeAudio.muted = true;
                                activeAudio.play().catch(e2 => {
                                    console.log("All autoplay blocked. Waiting for user interaction.");
                                });
                            });
                        };
                    } else if (data.version !== currentVersion) {
                        crossfadeTo(data.url, data.position, data.version);
                    } else {
                        // Sync timeline drift if playing
                        if (!activeAudio.paused && !activeAudio.muted) {
                            let diff = Math.abs(activeAudio.currentTime - data.position);
                            if (diff > 3) {
                                activeAudio.currentTime = data.position;
                            }
                        }
                    }
                })
                .catch(err => console.error("Radio status error:", err));
        }

        // Button click handler
        playBtn.onclick = function() {
            if (activeAudio.paused || activeAudio.muted) {
                // Unmute / Play and sync instantly
                activeAudio.muted = false;
                activeAudio.volume = userVolume;
                
                fetch("<?php echo admin_url('admin-ajax.php');?>?action=radio_status")
                    .then(r => r.json())
                    .then(data => {
                        titleEl.innerText = "🎵 " + data.name;
                        currentVersion = data.version;
                        
                        if (activeAudio.src === data.url) {
                            activeAudio.currentTime = data.position;
                            activeAudio.play();
                        } else {
                            activeAudio.src = data.url;
                            activeAudio.onloadedmetadata = function() {
                                activeAudio.currentTime = data.position;
                                activeAudio.play();
                            };
                        }
                    });
            } else {
                // Mute: instantly silence without pausing live track
                activeAudio.muted = true;
            }
        };

        // Volume Slider Handler
        volumeSlider.oninput = function() {
            userVolume = parseFloat(this.value);
            if (activeAudio) {
                activeAudio.volume = userVolume;
                if (userVolume > 0 && activeAudio.muted) {
                    activeAudio.muted = false;
                }
            }
            updateVolumeIcon(userVolume);
        };

        // Initial autoplay connection
        radioConnect(false);

        // Regular sync status check
        setInterval(function() {
            radioConnect(false);
        }, 5000);
    })();
    </script>
    <?php
}
add_shortcode('live_radio', 'live_radio');