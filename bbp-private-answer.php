<?php namespace BPA;
/*
Plugin Name: bbPress Private Answer
Plugin URI: https://samelh.com/work-with-me/
Description: bbPress Private Answer
Author: Samuel Elh
Version: 0.1
Author URI: https://samelh.com
Text Domain: bbpress-private-answer
*/

// prevent direct access
defined('ABSPATH') || exit('Direct access not allowed.' . PHP_EOL);

/**
  * Compare PHP versions
  * Making sure this blog is running enough required PHP software for
  * our plugin
  */
 
Class BPAVersionCompare
{
    public $hasRequiredPHP;
    protected $min, $operator;
 
    public function __construct( $minVersion = '5.3', $operator = '>=' )
    {
        $this->min = $minVersion;
        $this->operator = $operator;
 
        if ( version_compare(PHP_VERSION, $this->min, $operator) ) {
            $this->hasRequiredPHP = true;
        } else {
            add_action( "admin_notices", array( $this, "notice" ) );
        }
    }
 
    public function notice()
    {
        printf(
            '<div class="error notice is-dismissible"><p>bbPress Private Answer requires PHP %s %s.</p></div>',
            $this->operator,
            $this->min
        );
    }
}

$VersionCompare = new BPAVersionCompare('5.3');

if ( !isset($VersionCompare->hasRequiredPHP) || !$VersionCompare->hasRequiredPHP ) {
    return; // no min server requirements, stop, no more code below will be executed
}

use \BPA\Includes\Plugin
  , \BPA\Includes\Admin;

class BPA
{

    /** Class instance **/
    protected static $instance = null;

    /** Constants **/
    public $constants;

    /** Get Class instance **/
    public static function instance()
    {
        return null == self::$instance ? new self : self::$instance;
    }

    public function init()
    {
        // define constants
        $this->defineConstants();
    }

    /** define necessary constants **/
    protected function defineConstants()
    {
        $this->constants = array(
            "BPA_FILE" => __FILE__,
            "BPA_DIR" => plugin_dir_path(__FILE__),
            //"BPA_URL" => plugin_dir_url(__FILE__),
            "BPA_VER" => '0.1'
        );

        foreach ( $this->constants as $constant => $def ) {
            if ( !defined( $constant ) ) {
                define( $constant, $def );
            }
        }
    }

    /** autoloader **/
    public static function autoload( $class ) {
        $classFile = $class;
        if ( '\BPA\\' === substr( $classFile, 0, 5 ) ) {
            $classFile = substr( $classFile, 5 );
        }
        else if ( 'BPA\\' === substr( $classFile, 0, 4 ) ) {
            $classFile = substr( $classFile, 4 );
        }

        $classFile = BPA_DIR."{$classFile}.php";
        $classFile = str_replace( '\\', '/', $classFile );

        if ( !class_exists( $class ) && file_exists($classFile) ) {
            return require( $classFile );
        }
    }

    public static function verifyNonce()
    {
        if ( !isset( $_REQUEST['bpa_nonce'] ) ) {
            return;
        }
        return wp_verify_nonce( $_REQUEST['bpa_nonce'], 'bpa_nonce' );
    }
}

// init
$BPA = new \BPA\BPA;
$BPA->init();

// load plugin
$BPA::autoload( '\BPA\Includes\Plugin' );

// init plugin
Plugin::init();

if ( is_admin() ) {
    // load admin
    $BPA::autoload( '\BPA\Includes\Admin' );
	// init admin
	Admin::init();
}