<?php

/**
 * Feature: Auto WebP / WebM Conversion (free).
 *
 * Images (JPEG/PNG) are converted to WebP on `wp_handle_upload` via GD/Imagick.
 * Videos (mp4/mov/…) can optionally be converted to WebM via ffmpeg when it is
 * available (size-capped, since transcoding is slow). Everything degrades safely
 * when the required library/binary isn't present.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_WebP extends WP_Arzo_Feature
{
    public function id()
    {
        return 'webp_convert';
    }
    public function title()
    {
        return 'Auto WebP / WebM Conversion';
    }
    public function description()
    {
        return 'Convert uploaded images to WebP (and optionally videos to WebM) before they enter the media library.';
    }
    public function group()
    {
        return 'media';
    }
    public function icon()
    {
        return 'image';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'quality', 'type' => 'number', 'label' => 'WebP quality (1–100)', 'default' => 80),
            array('key' => 'convert_jpeg', 'type' => 'toggle', 'label' => 'Convert JPEG → WebP', 'default' => 1),
            array('key' => 'convert_png', 'type' => 'toggle', 'label' => 'Convert PNG → WebP (keeps transparency)', 'default' => 1),
            array('key' => 'max_width', 'type' => 'number', 'label' => 'Max image width (px, 0 = no resize)', 'default' => 0, 'help' => 'Large uploads are scaled down before conversion.'),
            array('key' => 'convert_video', 'type' => 'toggle', 'label' => 'Convert video → WebM (requires ffmpeg)', 'default' => 0, 'help' => 'Transcoding is slow; large files are skipped and big videos can still time out. Best for short clips.'),
            array('key' => 'video_max_mb', 'type' => 'number', 'label' => 'Max video size to convert (MB)', 'default' => 10),
            array('key' => 'keep_original', 'type' => 'toggle', 'label' => 'Keep the original file', 'default' => 0),
            array('key' => 'confirm_on_upload', 'type' => 'toggle', 'label' => 'Ask before converting on each upload', 'default' => 0, 'help' => 'Shows a confirmation in the media uploader (single & bulk) so you can choose per upload.'),
        );
    }

    public function boot()
    {
        add_filter('wp_handle_upload', array($this, 'maybe_convert'), 10, 2);

        if ($this->get_setting('confirm_on_upload', 0)) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_confirm'));
        }
    }

    /* ------------------------------------------------------------ Convert */

    public function maybe_convert($upload, $context = 'upload')
    {
        if (empty($upload['file']) || empty($upload['type'])) {
            return $upload;
        }
        // Per-upload opt-out (when the confirmation dialog is enabled).
        if ($this->get_setting('confirm_on_upload', 0)
            && isset($_REQUEST['wpa_webp_choice']) && $_REQUEST['wpa_webp_choice'] === 'no') {
            return $upload;
        }

        $type = $upload['type'];

        if ($type === 'image/jpeg' || $type === 'image/png') {
            return $this->convert_image($upload, $type);
        }
        if (strpos($type, 'video/') === 0 && $this->get_setting('convert_video', 0)) {
            return $this->convert_video($upload);
        }
        return $upload;
    }

    private function convert_image($upload, $type)
    {
        $do = ($type === 'image/jpeg' && $this->get_setting('convert_jpeg', 1))
            || ($type === 'image/png' && $this->get_setting('convert_png', 1));
        if (!$do) {
            return $upload;
        }
        $engine = $this->webp_supported();
        if (!$engine) {
            return $upload;
        }

        $quality   = max(1, min(100, (int) $this->get_setting('quality', 80)));
        $max_width = max(0, (int) $this->get_setting('max_width', 0));
        $dest      = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['file']);
        if (!$dest || $dest === $upload['file']) {
            return $upload;
        }

        $ok = ($engine === 'imagick')
            ? $this->img_imagick($upload['file'], $dest, $quality, $max_width)
            : $this->img_gd($upload['file'], $type, $dest, $quality, $max_width);

        if (!$ok || !file_exists($dest)) {
            return $upload;
        }
        return $this->replace($upload, $dest, 'image/webp', '/\.(jpe?g|png)$/i', '.webp');
    }

    private function convert_video($upload)
    {
        $ff = $this->ffmpeg_bin();
        if ($ff === '') {
            return $upload; // ffmpeg/exec unavailable — leave as-is
        }
        $cap = max(1, (int) $this->get_setting('video_max_mb', 10)) * 1024 * 1024;
        if (@filesize($upload['file']) > $cap) {
            return $upload; // too large — skip to avoid timeouts
        }
        $dest = preg_replace('/\.[A-Za-z0-9]+$/', '.webm', $upload['file']);
        if (!$dest || $dest === $upload['file']) {
            return $upload;
        }
        // VP8 + Vorbis with a fast preset; still best for short clips only.
        $cmd = sprintf(
            '%s -y -i %s -c:v libvpx -b:v 1M -c:a libvorbis -deadline realtime -cpu-used 5 %s 2>&1',
            $ff,
            escapeshellarg($upload['file']),
            escapeshellarg($dest)
        );
        @exec($cmd, $out, $code);
        if ($code !== 0 || !file_exists($dest) || filesize($dest) < 1) {
            @unlink($dest);
            return $upload;
        }
        return $this->replace($upload, $dest, 'video/webm', '/\.[A-Za-z0-9]+$/', '.webm');
    }

    private function replace($upload, $dest, $mime, $url_pattern, $url_ext)
    {
        if (!$this->get_setting('keep_original', 0)) {
            @unlink($upload['file']);
        }
        $upload['file'] = $dest;
        $upload['url']  = preg_replace($url_pattern, $url_ext, $upload['url']);
        $upload['type'] = $mime;
        return $upload;
    }

    /* --------------------------------------------------------- Engines */

    private function webp_supported()
    {
        if (function_exists('imagewebp')) {
            return 'gd';
        }
        if (class_exists('Imagick')) {
            $formats = @Imagick::queryFormats('WEBP');
            if (!empty($formats)) {
                return 'imagick';
            }
        }
        return false;
    }

    private function ffmpeg_bin()
    {
        if (!function_exists('exec')) {
            return '';
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return '';
        }
        @exec('ffmpeg -version 2>&1', $o, $code);
        return ($code === 0) ? 'ffmpeg' : '';
    }

    private function img_gd($src, $type, $dest, $quality, $max_width)
    {
        if ($type === 'image/png') {
            $img = @imagecreatefrompng($src);
            if (!$img) {
                return false;
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        } else {
            $img = @imagecreatefromjpeg($src);
            if (!$img) {
                return false;
            }
        }
        if ($max_width > 0 && imagesx($img) > $max_width) {
            $w = imagesx($img);
            $h = imagesy($img);
            $nw = $max_width;
            $nh = (int) round($h * ($max_width / $w));
            $resized = imagecreatetruecolor($nw, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }
        $ok = @imagewebp($img, $dest, $quality);
        imagedestroy($img);
        return $ok;
    }

    private function img_imagick($src, $dest, $quality, $max_width)
    {
        try {
            $im = new Imagick($src);
            if ($max_width > 0 && $im->getImageWidth() > $max_width) {
                $im->resizeImage($max_width, 0, Imagick::FILTER_LANCZOS, 1);
            }
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
            $ok = $im->writeImage($dest);
            $im->clear();
            $im->destroy();
            return (bool) $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* --------------------------------------------------- Upload confirm */

    public function enqueue_confirm()
    {
        if (!wp_script_is('wp-plupload', 'registered') && !wp_script_is('wp-plupload', 'enqueued')) {
            return;
        }
        $msg = $this->get_setting('convert_video', 0)
            ? 'Convert the uploaded media (images → WebP, videos → WebM)?'
            : 'Convert the uploaded image(s) to WebP?';
        $msg = esc_js($msg);
        $js  = <<<JS
(function () {
  function patch() {
    if (!window.wp || !wp.Uploader) return false;
    if (wp.Uploader.__wpaWebpBound) return true;
    wp.Uploader.__wpaWebpBound = true;
    var orig = wp.Uploader.prototype.init;
    wp.Uploader.prototype.init = function () {
      if (orig) orig.apply(this, arguments);
      var up = this.uploader;
      if (!up || !up.bind) return;
      up.bind('FilesAdded', function (uploader, files) {
        var match = false;
        for (var i = 0; i < files.length; i++) {
          if (/\.(jpe?g|png|mp4|mov|m4v|avi|mkv|webm)$/i.test(files[i].name)) match = true;
        }
        if (!match) return;
        var convert = window.confirm('{$msg}');
        uploader.settings.multipart_params = uploader.settings.multipart_params || {};
        uploader.settings.multipart_params.wpa_webp_choice = convert ? 'yes' : 'no';
      });
    };
    return true;
  }
  if (!patch()) {
    var n = 0, t = setInterval(function () { if (patch() || ++n > 50) clearInterval(t); }, 100);
  }
})();
JS;
        wp_add_inline_script('wp-plupload', $js);
    }
}
