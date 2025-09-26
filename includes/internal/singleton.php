<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SP451_SGHTML_Singleton' ) ) :

    class SP451_SGHTML_Singleton {
        protected static $instances = [];

        /**
         * __construct
         */
        protected function __construct() {

        }

        /**
         * Simple singleton setup
         * @return mixed|static
         */
        public static function instance() {

            $c = static::class;

            if ( ! isset( self::$instances[$c] ) ) {
                self::$instances[$c] = new static();
            }

            return self::$instances[$c];
        }
    }

endif;