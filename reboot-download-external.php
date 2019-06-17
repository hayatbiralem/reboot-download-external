<?php

/**
 * Plugin Name: Reboot Download External
 * Description: A plugin for downloading external images from the post content.
 * Version:     0.0.1
 * Author:      Reboot
 * Author URI:  https://reboot.com.tr
 * Text Domain: reboot-download-external
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

if (!class_exists('REBOOT_DOWNLOAD_EXTERNAL')) {

    define('REBOOT_DOWNLOAD_EXTERNAL_VERSION', '4.0.0');

    define('REBOOT_DOWNLOAD_EXTERNAL_PATH', plugin_dir_path(__FILE__));
    define('REBOOT_DOWNLOAD_EXTERNAL_URL', plugin_dir_url(__FILE__));

    define('REBOOT_ASSETS_VERSION', REBOOT_DOWNLOAD_EXTERNAL_VERSION);
    define('REBOOT_ASSETS_PATH', REBOOT_DOWNLOAD_EXTERNAL_PATH . 'assets/');
    define('REBOOT_ASSETS_URL', REBOOT_DOWNLOAD_EXTERNAL_URL . 'assets/');

    define('REBOOT_NONCE_KEY', '1c1d1bdd7e6c24057ef271c2bd5e3d6c'); // You can use md5_file( __FILE__ ) for new cool nonce key ;)
    define('REBOOT_DOWNLOAD_EXTERNAL_TEXT_DOMAIN', 'reboot-download-external');

    define('REBOOT_DOWNLOAD_EXTERNAL_TEMPLATE_PATH', trailingslashit(get_template_directory()));
    define('REBOOT_DOWNLOAD_EXTERNAL_TEMPLATE_URL', trailingslashit(get_template_directory_uri()));

    define('REBOOT_DOWNLOAD_EXTERNAL_CHILD_PATH', trailingslashit(get_stylesheet_directory()));
    define('REBOOT_DOWNLOAD_EXTERNAL_CHILD_URL', trailingslashit(get_stylesheet_directory_uri()));

    define('REBOOT_DOWNLOAD_EXTERNAL_IS_CHILD', REBOOT_DOWNLOAD_EXTERNAL_TEMPLATE_PATH != REBOOT_DOWNLOAD_EXTERNAL_CHILD_PATH ? true : false);

    class REBOOT_DOWNLOAD_EXTERNAL
    {
        function __construct()
        {
            $post_types = apply_filters('reboot_download_external_post_types', ['post', 'page']);

            foreach ($post_types as $post_type) {
                add_filter( "bulk_actions-edit-{$post_type}", [$this, 'register_bulk_actions'] );
                add_filter( "handle_bulk_actions-edit-{$post_type}", [$this, 'bulk_action_handler'], 10, 3 );
            }

            add_action( 'admin_notices', [$this, 'bulk_action_admin_notice'] );
        }

        function register_bulk_actions($bulk_actions) {
            $bulk_actions['reboot_download_external'] = __( 'Download External', REBOOT_DOWNLOAD_EXTERNAL_TEXT_DOMAIN);
            return $bulk_actions;
        }

        function bulk_action_handler( $redirect_to, $do_action, $post_ids ) {
            if ( $do_action !== 'reboot_download_external' ) {
                return $redirect_to;
            }

            $success = [];
            $error = [];
            foreach ( $post_ids as $post_id ) {
                $p = get_post($post_id);
                if($p) {
                    $images = self::parse_images($p->post_content);
                    if(!empty($images)) {

                        $replace = [];

                        foreach ($images as $image) {
                            if(isset($image['atts']['src'])) {
                                $new_image = self::download_image($image['atts']['src']);
                                if($new_image) {
                                    $replace[$image[0]] = $new_image;
                                } else {
                                    $error[$post_id] = sprintf( __('Image not downloaded: %s'), esc_attr($image['atts']['src']) );
                                }
                            } else {
                                $error[$post_id] = sprintf( __('Src attr not found: %s'), esc_attr($image['match']) );
                            }
                        }

                        $updated_id = wp_update_post([
                            'ID' => $post_id,
                            'post_content' => $p->post_content . '___deneme',
                        ]);

                        if($updated_id && !is_wp_error($updated_id)) {
                            $success[] = $post_id;
                        } else {
                            $error[$post_id] = sprintf( __('Post not updated: %s'), $post_id );
                        }
                    } else {
                        $error[$post_id] = sprintf( __('No images found: %s'), $post_id );
                    }
                } else {
                    $error[$post_id] = sprintf( __('Post not fetched: %s'), $post_id );
                }
            }

            if(!empty($success)) {
                $redirect_to = add_query_arg( 'reboot_download_external_success', count( $success ), $redirect_to );
            }

            if(!empty($error)) {
                $redirect_to = add_query_arg( 'reboot_download_external_error', implode('|', $error), $redirect_to );
            }

            return $redirect_to;
        }

        function bulk_action_admin_notice() {
            if ( ! empty( $_REQUEST['reboot_download_external_error'] ) ) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    str_replace('|', '<br>', $_REQUEST['reboot_download_external_error'])
                );
            }

            if ( ! empty( $_REQUEST['reboot_download_external_success'] ) ) {
                $updated_count = intval( $_REQUEST['reboot_download_external_success'] );
                printf( '<div class="notice notice-success is-dismissible"><p>' .
                    _n( 'Updated %s post.',
                        'Updated %s posts.',
                        $updated_count,
                        REBOOT_DOWNLOAD_EXTERNAL_TEXT_DOMAIN
                    ) . '</p></div>', $updated_count );
            }
        }

        static function parse_images($str) {
            $re = '/<img ([^>]*)\/?>/ms';
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

            $images = [];
            if(!empty($matches)) {
                foreach ($matches as $match) {
                    $images[] = [
                        'match' => $match,
                        'atts' => self::parse_atts($match[1]),
                    ];
                }
            }

            return $images;
        }

        static function parse_atts($str) {
            $re = '/([^\=\s]*)\="([^\"]*)"/m';
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

            $atts = [];
            if(!empty($matches)) {
                foreach ($matches as $match) {
                    $atts[$match[1]] = $match[2];
                }
            }

            return $atts;
        }

        static function download_image($src) {

        }
    }

    // new REBOOT_DOWNLOAD_EXTERNAL;

}