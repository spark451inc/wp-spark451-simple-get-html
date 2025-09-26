<?php
/**
Plugin Name: Spark451 - Simple GET HTML
Plugin URI:  https://github.com/spark451inc/wp-spark451-simple-get-html
Description: Powered by Spark451. For all info and changelogs, check: https://github.com/spark451inc/wp-spark451-simple-get-html
Version:     1.0.2
Author:      Spark451.com
Author URI:  https://www.spark451.com/
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: spark451-simple-get-html
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined('SP451_SGHTML_DIR') ) :
  define( 'SP451_SGHTML_DIR', plugin_dir_path( __FILE__ ));
endif;

require_once dirname( __FILE__ ) . '/includes/internal/singleton.php';
require_once dirname( __FILE__ ) . '/includes/internal/template.php';

require SP451_SGHTML_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! class_exists( 'SP451_SGHTML') ) :

  class SP451_SGHTML extends SP451_SGHTML_Singleton {

    public function init() {
      $this->include( 'includes/templates.php' );
      $this->sp451_sghtml_template = new SP451_SGHTML_Template_Loader();


      add_action( 'init', function () {

      } );


      add_shortcode( 'spark451_get_html_item', [ $this, 'render_get_html_item' ] );
      add_shortcode( 'spark451_get_html_items', [ $this, 'render_get_html_items' ] );

      $this->deactivate();

      $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/spark451inc/wp-spark451-simple-get-html',
        __FILE__, //Full path to the main plugin file or functions.php.
        'spark451-simple-get-html'
      );

      $myUpdateChecker->setBranch('master');
    }

    public function deactivate() {
      register_deactivation_hook( __FILE__, function () {

      } );
    }

    public function plugin_path( $filename = '' ) {
      return SP451_SGHTML_DIR . ltrim( $filename, '/' );
    }

    public function include( $filename = '' ) {
      $file_path = $this->plugin_path( $filename );

      if ( file_exists( $file_path ) ) {
        include_once $file_path;
      }
    }

    private function get_sanitized_query_value( $key ) {
      if ( ! is_string( $key ) || $key === '' ) {
        return null;
      }
      if ( ! isset( $_GET[ $key ] ) ) {
        return null;
      }
      $raw = wp_unslash( $_GET[ $key ] );
      if ( is_array( $raw ) ) {
        $raw = implode( ' ', array_map( 'sanitize_text_field', $raw ) );
      }
      $val = sanitize_text_field( $raw );
      return $val !== '' ? $val : null;
    }

    /**
     * Normalize shortcode atts so both positional ([tag foo bar]) and named
     * ([tag keys="foo,bar"] or [tag key="foo"]) work. Returns array of strings.
     */
    private function normalize_positional_atts( $atts, $single_only = false ) {
      $atts = (array) $atts;

      // If named 'key' is used: [spark451_get_html_item key="bee"]
      if ( $single_only && isset( $atts['key'] ) && $atts['key'] !== '' ) {
        return [ (string) $atts['key'] ];
      }

      // If named 'keys' list is used: [spark451_get_html_items keys="bee,honey"]
      if ( ! $single_only && isset( $atts['keys'] ) && $atts['keys'] !== '' ) {
        $list = array_map( 'trim', explode( ',', (string) $atts['keys'] ) );
        return array_values( array_filter( $list, fn( $s ) => $s !== '' ) );
      }

      // Positional: numeric keys 0,1,2... (WP stores bare attrs as numeric)
      $positional = [];
      foreach ( $atts as $k => $v ) {
        if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
          $v = trim( (string) $v );
          if ( $v !== '' ) {
            $positional[] = $v;
          }
        }
      }

      // Fallback: if [tag bee] was given as [tag key="bee"] by editor, catch it
      if ( $single_only && empty( $positional ) && isset( $atts['0'] ) && $atts['0'] !== '' ) {
        $positional[] = (string) $atts['0'];
      }

      return $positional;
    }

    /**
     * [spark451_get_html_item bee]
     * Prints sanitized value of ?bee=... if present, else empty string.
     * Also supports [spark451_get_html_item key="bee"].
     */
    public function render_get_html_item( $atts = [] ) {
      $keys = $this->normalize_positional_atts( $atts, /* single_only */ true );

      // Must have exactly one key
      $key = $keys[0] ?? null;
      if ( ! $key ) {
        return '';
      }

      $val = $this->get_sanitized_query_value( $key );
      if ( $val === null ) {
        return '';
      }

      // Escape for safe HTML output
      return esc_html( $val );
    }

    /**
     * [spark451_get_html_items bee honey left right]...[/spark451_get_html_items]
     * Replaces {{bee}}, {{honey}}, {{left}}, {{right}} inside content
     * with sanitized $_GET values (if present). Missing ones render as empty.
     *
     * Also supports named list: [spark451_get_html_items keys="bee,honey,left,right"].
     */
    public function render_get_html_items( $atts = [], $content = '' ) {
      // Make sure content is a string
      $content = (string) $content;

      $keys = $this->normalize_positional_atts( $atts, /* single_only */ false );
      if ( empty( $keys ) ) {
        // No keys provided: return content unchanged
        return $content;
      }

      // Build replacements only for provided keys
      $replacements = [];
      foreach ( $keys as $key ) {
        $val = $this->get_sanitized_query_value( $key );
        // Insert empty string if not present (so {{key}} disappears)
        $replacements[ $key ] = $val === null ? '' : esc_html( $val );
      }

      // Replace {{key}} tokens (double curly braces) in the content
      // Use a callback to leave unknown tokens untouched (not in $replacements)
      $out = preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/',
        function ( $m ) use ( $replacements ) {
          $name = $m[1];
          return array_key_exists( $name, $replacements ) ? $replacements[ $name ] : $m[0];
        },
        $content
      );

      // Return the user-provided HTML with safe substitutions
      return $out;
    }
  }

  SP451_SGHTML::instance()->init();
endif;