<?php

/**
 * Feature: Auto WebP Conversion (free).
 *
 * Converts uploaded JPEG/PNG images to WebP on `wp_handle_upload` — before the
 * attachment is created — so the WebP becomes the actual media-library item (and
 * all generated thumbnail sizes are WebP too). Uses GD's imagewebp(), falling back
 * to Imagick. Optional: keep the original, cap width, and a per-upload confirmation
 * in the media uploader.
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
        return 'Auto WebP Conversion';
    }
    public function description()
    {
        return 'Convert uploaded JPEG/PNG images to WebP before they enter the media library (smaller, faster images).';
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
            array('key' => 'convert_jpeg', 'type' => 'toggle', 'label' => 'Convert JPEG', 'default' => 1),
            array('key' => 'convert_png', 'type' => 'toggle', 'label' => 'Convert PNG (keeps transparency)', 'default' => 1),
            array('key' => 'max_width', 'type' => 'number', 'label' => 'Max width (px, 0 = no resize)', 'default' => 0, 'help' => 'Large uploads are scaled down before conversion.'),
            array('key' => 'keep_original', 'type' => 'toggle', 'label' => 'Keep the original file', 'default' => 0),
            array('key' => 'confirm_on_upload', 'type' => 'toggle', 'label' => 'Ask before converting on each upload', 'default' => 0, 'help' => 'Shows a confirmation in the media uploader so you can choose per upload.'),
        );
    }

    public function boot()
    {
        add_filter('wp_handle_upload', array($this, 'maybe_convert'), 10, 2);

        if ($this->get_setting('confirm_on_upload', 0)) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_confirm'));
        }
    }

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

    /**
     * @param array  $upload  ['file' => path, 'url' => url, 'type' => mime]
     * @param string $context 'upload' | 'sideload'
     */
    public function maybe_convert($upload, $context = 'upload')
    {
        if (empty($upload['file']) || empty($upload['type'])) {
            return $upload;
        }
        $type = $upload['type'];
        $do = ($type === 'image/jpeg' && $this->get_setting('convert_jpeg', 1))
            || ($type === 'image/png' && $this->get_setting('convert_png', 1));
        if (!$do) {
            return $upload;
        }
        // Per-upload opt-out (when the confirmation dialog is enabled).
        if ($this->get_setting('confirm_on_upload', 0)
            && isset($_REQUEST['wpa_webp_choice']) && $_REQUEST['wpa_webp_choice'] === 'no') {
            return $upload;
        }

        $engine = $this->webp_supported();
        if (!$engine) {
            return $upload;
        }

        $quality   = max(1, min(100, (int) $this->get_setting('quality', 80)));
        $max_width = max(0, (int) $this->get_setting('max_width', 0));
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['file']);
        if (!$webp_path || $webp_path === $upload['file']) {
            return $upload;
        }

        $ok = ($engine === 'imagick')
            ? $this->convert_imagick($upload['file'], $webp_path, $quality, $max_width)
            : $this->convert_gd($upload['file'], $type, $webp_path, $quality, $max_width);

        if (!$ok || !file_exists($webp_path)) {
            return $upload;
        }

        if (!$this->get_setting('keep_original', 0)) {
            @unlink($upload['file']);
        }

        $upload['file'] = $webp_path;
        $upload['url']  = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['url']);
        $upload['type'] = 'image/webp';
        return $upload;
    }

    private function convert_gd($src, $type, $dest, $quality, $max_width)
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

    private function convert_imagick($src, $dest, $quality, $max_width)
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

    public function enqueue_confirm()
    {
        if (!wp_script_is('wp-plupload', 'registered') && !wp_script_is('wp-plupload', 'enqueued')) {
            return;
        }
        $js = <<<JS
(function(){
  if (!window.wp || !wp.Uploader || wp.Uploader.__wpaWebpBound) return;
  wp.Uploader.__wpaWebpBound = true;
  var orig = wp.Uploader.prototype.init;
  wp.Uploader.prototype.init = function () {
    if (orig) orig.apply(this, arguments);
    var up = this.uploader;
    if (!up || !up.bind) return;
    up.bind('FilesAdded', function (uploader, files) {
      var hasImage = false;
      for (var i = 0; i < files.length; i++) { if (/\.(jpe?g|png)$/i.test(files[i].name)) hasImage = true; }
      if (!hasImage) return;
      var convert = window.confirm('Convert the uploaded image(s) to WebP?');
      uploader.settings.multipart_params = uploader.settings.multipart_params || {};
      uploader.settings.multipart_params.wpa_webp_choice = convert ? 'yes' : 'no';
    });
  };
})();
JS;
        wp_add_inline_script('wp-plupload', $js);
    }
}
