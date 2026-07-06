<?php

/**
 * Feature: Content Order.
 *
 * Drag-and-drop ordering of posts, pages, and any custom post type, right in the
 * admin list table. Reordering writes each item's `menu_order`, and enabled post
 * types are then ordered by `menu_order` in both the admin list and on the front
 * end — so your custom order sticks everywhere.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Content_Order extends WP_Arzo_Feature
{
    const NONCE = 'wp_arzo_content_order';

    public function id()
    {
        return 'content_order';
    }
    public function title()
    {
        return 'Content Order (drag & drop)';
    }
    public function description()
    {
        return 'Reorder posts, pages, and custom post types by dragging rows in the admin list. The order applies on the front end too.';
    }
    public function group()
    {
        return 'content';
    }
    public function icon()
    {
        return 'edit';
    }

    /** One toggle per editable post type. */
    public function settings_schema()
    {
        $out = array();
        foreach ($this->orderable_types() as $name => $label) {
            $out[] = array(
                'key'     => 'pt_' . $name,
                'type'    => 'toggle',
                'label'   => 'Enable for: ' . $label,
                'default' => ($name === 'page') ? 1 : 0,
            );
        }
        return $out;
    }

    private function orderable_types()
    {
        $out = array();
        foreach (get_post_types(array('show_ui' => true), 'objects') as $pt) {
            if (in_array($pt->name, array('attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'), true)) {
                continue;
            }
            $out[$pt->name] = isset($pt->labels->name) ? $pt->labels->name : $pt->name;
        }
        return $out;
    }

    private function enabled_types()
    {
        $types = array();
        foreach (array_keys($this->orderable_types()) as $name) {
            if ($this->get_setting('pt_' . $name, $name === 'page' ? 1 : 0)) {
                $types[] = $name;
            }
        }
        return $types;
    }

    public function boot()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('pre_get_posts', array($this, 'order_query'));
        add_action('wp_ajax_wp_arzo_save_order', array($this, 'ajax_save'));
    }

    public function enqueue($hook)
    {
        if ($hook !== 'edit.php') {
            return;
        }
        $screen = get_current_screen();
        $pt = $screen ? $screen->post_type : '';
        if (!$pt || !in_array($pt, $this->enabled_types(), true)) {
            return;
        }
        wp_enqueue_script('jquery-ui-sortable');
        $data = array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE),
            'postType' => $pt,
        );
        $js = 'jQuery(function($){'
            . 'var cfg=' . wp_json_encode($data) . ';'
            . 'var tb=$("#the-list"); if(!tb.length) return;'
            . 'tb.sortable({items:"tr",axis:"y",cursor:"move",opacity:0.7,'
            . 'helper:function(e,ui){ui.children().each(function(){$(this).width($(this).width());});return ui;},'
            . 'update:function(){'
            . 'var ids=tb.find("tr").map(function(){var m=this.id&&this.id.match(/post-(\\d+)/);return m?m[1]:null;}).get();'
            . '$.post(cfg.ajaxUrl,{action:"wp_arzo_save_order",nonce:cfg.nonce,post_type:cfg.postType,order:ids});'
            . '}});'
            . 'tb.find("tr").css("cursor","move");'
            . '});';
        wp_add_inline_script('jquery-ui-sortable', $js);
    }

    /** Order enabled post types by menu_order (admin list + front), unless explicitly sorted. */
    public function order_query($query)
    {
        if (!$query->is_main_query() || $query->get('orderby')) {
            return;
        }
        $pt = $query->get('post_type');
        if (!$pt && !is_admin() && function_exists('is_post_type_archive') && is_post_type_archive()) {
            $obj = get_queried_object();
            $pt = ($obj && isset($obj->name)) ? $obj->name : '';
        }
        if (is_array($pt) || !$pt || !in_array($pt, $this->enabled_types(), true)) {
            return;
        }
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }

    public function ajax_save()
    {
        if (!current_user_can('edit_others_posts') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $pt = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
        if (!in_array($pt, $this->enabled_types(), true)) {
            wp_send_json_error(array('message' => 'Ordering not enabled for this type'), 400);
        }
        $order = isset($_POST['order']) ? array_map('intval', (array) $_POST['order']) : array();
        $i = 0;
        foreach ($order as $id) {
            if ($id > 0) {
                wp_update_post(array('ID' => $id, 'menu_order' => $i));
                $i++;
            }
        }
        wp_send_json_success(array('updated' => $i));
    }
}
