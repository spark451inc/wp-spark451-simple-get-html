<?php
/**
Plugin Name: Spark451 - Simple GET HTML
Plugin URI:  https://github.com/spark451inc/wp-spark451-simple-get-html
Description: Powered by Spark451. For all info and changelogs, check: https://github.com/spark451inc/wp-spark451-simple-get-html
Version:     1.0.3
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

      // Named single: [spark451_get_html_item key="bee"]
      if ( $single_only && isset( $atts['key'] ) && $atts['key'] !== '' ) {
        return [ (string) $atts['key'] ];
      }

      // Named list: [spark451_get_html_items keys="bee,honey"]
      if ( ! $single_only && isset( $atts['keys'] ) && $atts['keys'] !== '' ) {
        $raw = (string) $atts['keys'];
        $list = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        return array_values( array_filter( array_map( 'trim', $list ), fn( $s ) => $s !== '' ) );
      }

      // Positional (WP stores bare attrs as numeric); allow commas or spaces
      $positional = [];
      foreach ( $atts as $k => $v ) {
        if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
          $v = (string) $v;
          // Split on commas or whitespace; keep ! or * prefix if present
          $parts = preg_split( '/[\s,]+/', $v, -1, PREG_SPLIT_NO_EMPTY );
          foreach ( $parts as $p ) {
            $p = trim( $p );
            if ( $p !== '' ) {
              $positional[] = $p;
            }
          }
        }
      }

      // Fallback for editors that serialize bare attr to index "0"
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
      $atts = (array) $atts;

      $key = null;
      if ( isset( $atts['key'] ) && $atts['key'] !== '' ) {
        $key = (string) $atts['key'];
      } else {
        $keys = $this->normalize_positional_atts( $atts, /* single_only */ true );
        $key  = $keys[0] ?? null;
      }
      if ( ! $key ) return '';

      $default = isset( $atts['default'] ) ? (string) $atts['default'] : '';
      $fallback_html = isset( $atts['fallback'] ) ? (string) $atts['fallback'] : '';

      $val = $this->get_sanitized_query_value( $key );
      if ( $val === null || $val === '' ) {
        if ( $fallback_html !== '' ) {
          // Allow safe HTML in fallback
          return wp_kses_post( do_shortcode( $fallback_html ) );
        }
        return $default !== '' ? esc_html( $default ) : '';
      }

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
      $content = (string) $content;

      // Collect declared keys (positional or keys="")
      $declared = $this->normalize_positional_atts( $atts, /* single_only */ false );
      if ( empty( $declared ) && empty( $atts['required'] ) ) {
        // Nothing to do
        return $content;
      }

      // Parse required keys from attribute and inline marks (! or *)
      $required_attr = [];
      if ( isset( $atts['required'] ) && $atts['required'] !== '' ) {
        $required_attr = array_values( array_filter(
          array_map( 'trim', preg_split( '/[\s,]+/', (string) $atts['required'] ) ),
          fn( $s ) => $s !== ''
        ));
      }

      $keys = [];
      $required_inline = [];
      foreach ( $declared as $k ) {
        if ( $k[0] === '!' || $k[0] === '*' ) {
          $k = ltrim( $k, '!*' );
          if ( $k !== '' ) {
            $required_inline[] = $k;
            $keys[] = $k;
          }
        } else {
          $keys[] = $k;
        }
      }

      // Unique sets
      $keys = array_values( array_unique( $keys ) );
      $required = array_values( array_unique( array_merge( $required_attr, $required_inline ) ) );

      // Build map of sanitized values for declared keys
      $values = [];
      foreach ( $keys as $k ) {
        $v = $this->get_sanitized_query_value( $k );
        $values[$k] = ($v === null || $v === '') ? null : esc_html( $v );
      }

      // If any required key is fallback/empty → show fallback HTML
      $any_fallback = false;
      foreach ( $required as $rk ) {
        if ( ! array_key_exists( $rk, $values ) || $values[$rk] === null || $values[$rk] === '' ) {
          $any_fallback = true;
          break;
        }
      }

      if ( $any_fallback ) {
        $fallback_html = isset( $atts['fallback'] ) ? (string) $atts['fallback'] : '';
        return $fallback_html !== '' ? wp_kses_post( do_shortcode( $fallback_html ) ) : '';
      }

      // Replace {{key}} tokens (with optional plain-text fallback via |)
      $out = preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_\-]+)(?:\|([^}]*))?\s*\}\}/',
        function ( $m ) use ( $values ) {
          $name     = $m[1];
          $fallback = isset( $m[2] ) ? trim( (string) $m[2] ) : null;

          if ( array_key_exists( $name, $values ) ) {
            $val = $values[$name];
            if ( $val !== null && $val !== '' ) {
              return $val; // already escaped
            }
            // For token-level fallback we keep it simple: treat as plain text
            return $fallback !== null && $fallback !== '' ? esc_html( $fallback ) : '';
          }

          // Unknown token: leave as-is
          return $m[0];
        },
        $content
      );

      // Allow nested shortcodes inside the final content
      return do_shortcode( $out );
    }
  }

  SP451_SGHTML::instance()->init();
endif;