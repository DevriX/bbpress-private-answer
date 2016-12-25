<?php namespace BPA\Includes;

use \BPA\BPA;

/**
  * plugin class
  */

class Plugin
{
    /** Class instance **/
    protected static $instance = null;

    /** Get Class instance **/
    public static function instance()
    {
        return null == self::$instance ? new self : self::$instance;
    }

    public static function init()
    {
        // i18n
        add_action( "plugins_loaded", array( self::instance(), "loadTextdomain" ) );
        // init
        add_action( "plugins_loaded", array( self::instance(), "_init" ) );
    }

    /** setup **/
    public static function _init()
    {
        // get active plugins list
        $activePlugins = apply_filters('active_plugins',get_option('active_plugins'));
        // require bbPress
        if ( !$activePlugins || !in_array('bbpress/bbpress.php', (array) $activePlugins) ) {
            // return an admin notice
            global $bpa_admin_bbp_absent;
            $bpa_admin_bbp_absent = true;
            // bail
            return;
        }
        // class instance
        $This = self::instance();
        // public hooks
        add_action( "bbp_theme_after_topic_form_content", array( $This, "parseTopicField" ) );
        add_action( "bbp_theme_after_reply_form_content", array( $This, "parseReplyField" ) );
        add_action( "bbp_topic_metabox", array( $This, "parseAdminPostField" ), 11 );
        add_action( "bbp_reply_metabox", array( $This, "parseAdminPostField" ), 11 );
        add_action( "save_post_topic", array( $This, "savePost" ) );
        add_action( "save_post_reply", array( $This, "savePost" ) );
        add_action( "bbp_new_topic", array( $This, "savePost" ) );
        add_action( "bbp_edit_topic", array( $This, "savePost" ) );
        add_action( "bbp_new_reply", array( $This, "savePost" ) );
        add_action( "bbp_edit_reply", array( $This, "savePost" ) );
        add_filter( "bbp_get_topic_title", array( $This, "filterTitle" ), 10, 2 );
        add_action( "wp", array( $This, "redirectWP" ) );
        add_filter( 'bbp_get_topic_excerpt', array( $This, 'filterContent' ), 999, 2 );
        add_filter( 'bbp_get_topic_content', array( $This, 'filterContent' ), 999, 2 );
        add_filter( 'bbp_get_reply_excerpt', array( $This, 'filterContent' ), 999, 2 );
        add_filter( 'bbp_get_reply_content', array( $This, 'filterContent' ), 999, 2 );
        add_filter( 'the_content', array( $This, 'filterContent' ), 999 );
        add_filter( 'the_excerpt', array( $This, 'filterContent' ), 999 );
        add_filter( 'bbp_subscription_mail_message', array( $This, 'filterSubscriptionMail' ), 10, 3 );
        add_filter( 'bbp_topic_subscription_user_ids', array( $This, 'filterSubscriptionUsers' ), 11 );
        add_filter( 'bbp_get_topic_class', array( $This, 'appendPostClass' ), 10, 2 );
        add_filter( 'bbp_get_reply_class', array( $This, 'appendPostClass' ), 10, 2 );
        add_filter( 'se_PrivatePost_field_label', array( $This, 'filterFieldLabel' ) );
        add_filter( 'se_PrivatePost_notice', array( $This, 'filterPrivateContentNotice' ) );
        add_filter( "manage_edit-topic_columns", array( $This, "appendCol" ) );
        add_action( "manage_topic_posts_custom_column", array( $This, "editCol" ), 10, 2 );
        add_filter( "manage_edit-reply_columns", array( $This, "appendCol" ) );
        add_action( "manage_reply_posts_custom_column", array( $This, "editCol" ), 10, 2 );
    }

    public static function loadTextdomain()
    {
        return load_plugin_textdomain('bbpress-private-answer', FALSE, dirname(plugin_basename(Bfr_FILE)).'/languages');
    }

    public static function isPrivate( $post_id )
    {
        $isPrivate = (bool) get_post_meta( $post_id, 'private_answer', 1 );
        return apply_filters( 'se_bbp_is_private', $isPrivate, $post_id );
    }

    public static function parseTopicField()
    {
        $post_id = bbp_get_topic_id();
        self::parseField( $post_id );
    }

    public static function parseReplyField()
    {
        $post_id = bbp_get_reply_id();
        self::parseField( $post_id );
    }

    public static function parseAdminPostField( $post_id )
    {
        self::parseField( $post_id );
    }

    public static function parseField( $post_id=0 )
    {
        ?>
            <p>
                <input type="checkbox" name="mark_private" id="mark_private" <?php checked( self::isPrivate( $post_id ) ); ?>>
                <label for="mark_private"><?php echo apply_filters('se_PrivatePost_field_label',__('Mark as private','bbpress-private-answer')); ?></label>
            </p>
            <?php wp_nonce_field( 'bpa_nonce', 'bpa_nonce' ); ?>
        <?php
    }

    public static function savePost( $post_id )
    {
        if ( $post_id && BPA::verifyNonce() ) {
            if ( isset( $_POST['mark_private'] ) ) {
                return update_post_meta( $post_id, 'private_answer', time() );
            } else {
                return delete_post_meta( $post_id, 'private_answer' );
            }
        }
    }

    public static function filterTitle( $title, $post_id )
    {
        if ( $post_id && self::isPrivate( $post_id ) ) {
            $title = apply_filters(
                'se_PrivatePost_title',
                sprintf( __('[Private] %1$s', 'bbpress-private-answer'), $title ),
                $post_id,
                $title
            );
        }
        return $title;
    }

    public static function canAccess( $post, $user_id = null )
    {

        if ( !isset( $post->ID ) && is_numeric( $post ) && $post ) {
            $post = get_post( $post );
        }

        if ( empty( $post->ID ) ) {
            return true; // no post set
        }

        if ( !self::isPrivate( $post->ID ) ) {
            return true; // not a private post
        }

        if ( $user_id ) {
            $current_user = get_userdata( $user_id );
        } else {
            global $current_user;
        }

        if ( !$current_user->ID ) {
            $canAccess = false;
        }

        else if ( $post->post_author == $current_user->ID ) {
            $canAccess = true; // post author
        }

        else if ( in_array('bbp_keymaster', $current_user->roles) ) {
            $canAccess = true; // keymaster
        }

        else if ( in_array('bbp_moderator', $current_user->roles) ) {
            $canAccess = true; // moderator
        }

        else if ( in_array('administrator', $current_user->roles) ) {
            $canAccess = true; // admin
        }

        else {
            $canAccess = false; // nothing caught
        }

        return apply_filters( 'se_PrivatePost_canAccess', $canAccess, $post );

    }

    public static function redirectWP()
    {
        if ( is_bbpress() ) {
            $topic_id = bbp_get_topic_id();
            $reply_id = bbp_get_reply_id();

            if ( $topic_id ) {
                $post_id = $topic_id;
            } else if ( $reply_id ) {
                $post_id = $reply_id;
            } else {
                return;
            }

            if ( !self::canAccess( $post_id ) ) {
                wp_redirect(apply_filters(
                    'se_PrivatePost_redirect',
                    home_url(),
                    $post_id
                ));
                exit;
            }
        }
    }

    public static function filterSubscriptionMail( $message, $reply_id, $topic_id )
    {
        if ( $reply_id ) {
            $post_id = $reply_id;
        } else if ( $topic_id ) {
            $post_id = $topic_id;
        } else {
            unset( $GLOBALS['bbp_subscription_mail_message_POST_ID'] );
            return $message;
        }
        global $bbp_subscription_mail_message_POST_ID;
        $bbp_subscription_mail_message_POST_ID = $post_id;
        return $message;
    }

    public static function filterSubscriptionUsers( $user_ids )
    {
        global $bbp_subscription_mail_message_POST_ID;

        if ( isset( $bbp_subscription_mail_message_POST_ID ) && is_numeric( $bbp_subscription_mail_message_POST_ID ) ) {
            foreach ( (array) $user_ids as $i=>$user_id ) {
                if ( !self::canAccess( $bbp_subscription_mail_message_POST_ID, $user_id ) ) {
                    unset( $user_ids[$i] );
                }
            }
        }

        return $user_ids;
    }

    public static function appendPostClass( $classes, $post_id )
    {
        if ( !self::canAccess( $post_id ) ) {
            $classes[] = 'bbp-private-content';
        } else if ( self::isPrivate( $post_id ) ) {
            $classes[] = 'bbp-private-has-access';
        }
        return $classes;
    }

    public static function filterContent( $content, $post_id=0 )
    {
        if ( !$post_id && is_bbpress() ) {
            $topic_id = bbp_get_topic_id();
            $reply_id = bbp_get_reply_id();
            if ( $topic_id ) {
                $post_id = $topic_id;
            } else if ( $reply_id ) {
                $post_id = $reply_id;
            } else {
                $post_id = 0;
            }
        }
        if ( !self::canAccess( $post_id ) ) {
            $notice = apply_filters(
                'se_PrivatePost_notice',
                __('<p><em>This content is marked private.</em></p>', 'bbpress-private-answer'),
                $post_id
            );
            // if ( is_feed() ) {
            //    $content = $notice;
            // } else {
                $content = $notice;
            // }
        }
        return $content;
    }

    public static function filterFieldLabel( $label )
    {
        $custom = get_option( 'se_PrivatePost_field_label' );
        if ( $custom && trim( $custom ) ) {
            if ( !is_admin() ) {
                $custom = wp_unslash( $custom );
                $custom = html_entity_decode( $custom );
            }
            return $custom;
        }
        return $label;
    }

    public static function filterPrivateContentNotice( $notice )
    {
        $custom = get_option( 'se_PrivatePost_notice' );
        if ( $custom && trim( $custom ) ) {
            if ( !is_admin() ) {
                $custom = wp_unslash( $custom );
                $custom = html_entity_decode( $custom );
            }
            return $custom;
        }
        return $notice;
    }

    public static function filterRevisions( $rev, $post_id )
    { // not in use, bbp_get_reply_content handles this
        if ( !is_admin() && !self::canAccess( $post_id ) ) {
            $rev = array();
        }
        return $rev;
    }

    public static function appendCol($columns) {
        $columns['private_post'] = __('Private Post', 'bbpress-private-answer');
        return $columns;
    }

    public static function editCol( $column, $post_id ) {
        switch( $column ) {
            case 'private_post' :
                print( self::isPrivate($post_id) ? 'True' : 'False' );
                break;
        }
    }
}