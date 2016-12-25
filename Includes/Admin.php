<?php namespace BPA\Includes;

use \BPA\BPA;

/**
  * admin class
  */

class Admin
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
        return add_action( "plugins_loaded", array( self::instance(), "_init" ) );
    }

    /** setup admin **/
    public static function _init()
    {
        global $bpa_admin_bbp_absent;
        // bbpress require notice
        if ( isset( $bpa_admin_bbp_absent ) && (bool) $bpa_admin_bbp_absent ) {
            return add_action( "admin_notices", array( self::instance(), "notice" ) );
        }
        // setup admin menu
        add_action( "admin_menu", array( self::instance(), "menu" ) );
        // target page
        $page = isset( $_GET['page'] ) ? $_GET['page'] : null;
        if ( $page ) {
            $page = str_replace(array(
                '-about'
            ), '', $page);
        }
        if ( 'bbpress-private-answer' === $page ) {
            // add CSS
            add_action( "admin_head", array( self::instance(), "printCSS" ) );
            // manage head
            add_action( "admin_init", array( self::instance(), "header" ) );
            // menu JS toggle current
            add_action( "admin_footer", array( self::instance(), "menuJS" ) );
            // redir issues
            add_action( "admin_init", array( self::instance(), "obStart" ) );
        }
        // add plugins.php meta links
        add_filter( "plugin_action_links_" . plugin_basename(BPA_FILE), array( self::instance(), "pushMeta" ) );
    }

    public static function notice()
    {
        // add new notice
        self::feedback(array(
            'success' => false,
            'message' => __( '<strong>bbPress Private Answer notice</strong>: bbPress (parent) plugin is a requirement. Please activate install and activate bbPress to use this plugin.', 'bbpress-private-answer' )
        ));
        // print
        return self::uiFeedback();
    }

    /** admin menu and settings page **/
    public static function menu()
    {
        // settings page
        add_options_page(
            __('bbPress Custom Plugin','bbpress-private-answer'),
            __('bbP Custom', 'bbpress-private-answer'),
            'manage_options',
            'bbpress-private-answer',
            array( self::instance(), 'adminScreen' )
        );
        // help/about page
        add_submenu_page(
            null,
            'About/Help &lsaquo; bbPress Custom Plugin',
            null,
            'manage_options',
            'bbpress-private-answer-about',
            array(self::instance(), "help")
        );
    }

    public static function feedback( $new_response )
    {
        if ( is_array($new_response) && isset( $new_response['success'] ) ) {
            global $bpa_admin_feedback;
            if ( !is_array($bpa_admin_feedback) ) {
                $bpa_admin_feedback = array();
            }
            $bpa_admin_feedback[] = $new_response;
        }
    }

    public static function uiFeedback()
    {
        global $bpa_admin_feedback, $bpa_admin_feedback_printed;
        if ( !isset( $bpa_admin_feedback_printed ) || !is_array($bpa_admin_feedback_printed) ) {
            $bpa_admin_feedback_printed = array();
        }
        if ( $bpa_admin_feedback && is_array($bpa_admin_feedback) ) {
            foreach ( $bpa_admin_feedback as $i => $res ) {
                if ( empty( $res['message'] ) ) continue;
                // duplicates check
                if ( isset($bpa_admin_feedback_printed[$res['message']]) ) continue;
                $bpa_admin_feedback_printed[$res['message']] = true;
                // print message
                printf(
                    '<div class="%s notice is-dismissible"><p>%s</p></div>',
                    !empty($res['success'])?'updated':'error',
                    $res['message']
                );
            }
        }
    }

    public static function header()
    {
        if ( isset( $_POST['submit'] ) ) {
            if ( !BPA::verifyNonce() ) {
                return self::feedback(array(
                    'success' => false,
                    'message' => __('ERROR: authentication failed.','bbpress-private-answer')
                ));
            }

            if ( isset( $_POST['private_post_label'] ) ) {
                $custom = esc_attr( $_POST['private_post_label'] );
                if ( $custom )
                    update_option( 'se_PrivatePost_field_label', $custom );
                else
                    delete_option( 'se_PrivatePost_field_label' );
            } else {
                delete_option( 'se_PrivatePost_field_label' );
            }

            if ( isset( $_POST['private_post_notice'] ) ) {
                $notice = esc_attr( $_POST['private_post_notice'] );
                if ( $notice )
                    update_option( 'se_PrivatePost_notice', $notice );
                else
                    delete_option( 'se_PrivatePost_notice' );
            } else {
                delete_option( 'se_PrivatePost_notice' );
            }

            if ( isset( $_POST['private_post_uninstall_flush'] ) ) {
                update_option( 'private_post_uninstall_flush', time() );
            } else {
                delete_option( 'private_post_uninstall_flush' );
            }

            return self::feedback(array(
                'success' => true,
                'message' => __('General Settings saved successfully!','bbpress-private-answer')
            ));
        }

    }

    /** admin settings page callback **/
    public static function adminScreen()
    {
        ?>

            <div class="wrap">
    
                <h2><?php _e('bbPress Private Answer &rsaquo; Settings','bbpress-private-answer'); ?></h2>
                
                <?php self::topMenu(); ?>

                <form method="post">
                    
                    <div class="section">

                        <h3><?php _e('Private Topics/Replies','bbpress-private-answer'); ?></h3>

                        <h4><?php _e('Field Label','bbpress-private-answer'); ?></h4>

                        <p>
                            <label><input type="text" name="private_post_label" size="60" value="<?php echo wp_unslash(apply_filters( 'se_PrivatePost_field_label', 'Mark as private' )); ?>" /><br/>
                            <em><?php _e('This label will be placed in topic/reply forms','bbpress-private-answer'); ?></em></label>
                        </p>

                        <h4>"Private Content" Notice</h4>

                        <p>
                            <label><textarea name="private_post_notice" cols="62" rows="5"><?php echo wp_unslash(apply_filters(
                                'se_PrivatePost_notice',
                                __('<p><em>This content is marked private.</em></p>','bbpress-private-answer'),
                                0
                            )); ?></textarea><br/>
                            <em><?php _e('This custom message will be displayed instead of the topic/reply content when the post is private and the viewer has no access to it.','bbpress-private-answer'); ?></em></label>
                        </p>

                        <h4><?php _e('Uninstall','bbpress-private-answer'); ?></h4>

                        <p>
                            <label><input type="checkbox" name="private_post_uninstall_flush" <?php checked((bool) get_option('private_post_uninstall_flush')); ?>> <?php _e('Remove all post data upon next plugin uninstall','bbpress-private-answer'); ?></label>
                        </p>

                    </div>

                    <p></p>

                    <input type="hidden" name="saving_general_settings" value="1" />
                    <?php wp_nonce_field('bpa_nonce','bpa_nonce'); ?>
                    <?php submit_button(); ?>

                </form>
            
            </div>
        <?php
    }

    /** top menu **/
    public static function topMenu()
    {
        // little feedback
        self::uiFeedback();

        if ( empty( $_GET['page'] ) ) return;
        $p = esc_attr($_GET['page']);
        ?>
            <h2 class="nav-tab-wrapper">

                <a class="nav-tab<?php echo"bbpress-private-answer"==$p?" nav-tab-active":"";?>" href="options-general.php?page=bbpress-private-answer">
                    <span><?php _e('Settings','bbpress-private-answer'); ?></span>
                </a>

                <a class="nav-tab<?php echo"bbpress-private-answer-about"==$p?" nav-tab-active":"";?>" href="options-general.php?page=bbpress-private-answer-about">
                    <span><?php _e('About','bbpress-private-answer'); ?></span>
                </a>
                </a>
            </h2>
            <p></p>
        <?php
    }

    /** print CSS for settings page **/
    public static function printCSS()
    {
        print '<style type="text/css">.wrap .section{display:block;background:#fff;padding:.5em 1em 1em;border:1px solid #dcdbdb}.wrap .section.has-error{box-shadow:0 0 2px red;border:0}</style>' . PHP_EOL;
    }

    /** push plugins.php urls **/
    public static function pushMeta( $links )
    {
        return array(
            '<a href="' . esc_url( 'options-general.php?page=bbpress-private-answer' ) . '">' . __( 'Settings', 'bbpress-private-answer' ) . '</a>',
            '<a href="' . esc_url( 'options-general.php?page=bbpress-private-answer-about' ) . '">' . __( 'About', 'bbpress-private-answer' ) . '</a>'
        ) + $links;
    }

    public static function menuJS()
    {
        ?>
        <script type="text/javascript">
            var i=document.querySelector('a[href*="options-general.php?page=bbpress-private-answer"]'), l=null!==i?i.parentElement:null
            null!==i&&i.classList.add('current');
            null!==l&&l.classList.add('current');
        </script>
        <?php
    }

    public static function help()
    {
        ?>
        <div class="wrap">
            <h2>bbPress Private Answer &rsaquo; About</h2>
            
            <?php self::topMenu(); ?>

            <div class="section">
            
                <h3><?php _e('Private Private Answer','bbpress-private-answer'); ?></h3>

                <p><?php _e('Users can mark their topics and replies as private, and only moderators and site admins can be able to access and view these, not excluding the topic/reply post author as well.','bbpress-private-answer'); ?></p>

                <p><?php _e('When a topic/reply is made private, the topic/reply content, excerpt, and feed will be overwritten to the cusotm notice you chose in the General Settings section. When the topic is private, the user who does not have permission to access it, will be redirected to the home page of your site (or a custom redirect using <code>se_PrivatePost_redirect</code> filter).','bbpress-private-answer'); ?></p>

                <p><?php _e('In the admin, admins and moderators can edit topics/replies and toggle on/off making these posts private.','bbpress-private-answer'); ?></p>

                <p><?php _e('In the admin posts list, you\'ll get to see which are made as private.','bbpress-private-answer'); ?></p>            

            </div>

            <p></p>

            <div class="section">
                <p style="font-weight:600"><?php printf( __('Thank you for using <a href="https://wordpress.org/plugins/bbp-private-answer/">bbPress Private Answer ver. %s</a>!', 'bbpress-private-answer'), BPA_VER ); ?></p>
                <li><a href="https://wordpress.org/support/plugin/bbp-private-answer"><?php _e('Support', 'bbpress-private-answer'); ?></li>
                <li><a href="https://samelh.com/contact/"><?php _e('Contact Us', 'bbpress-private-answer'); ?></li>
                <li><a href="https://wordpress.org/support/plugin/bbp-private-answer/reviews/"><?php _e('Rate this plugin', 'bbpress-private-answer'); ?></a></li>
                <li><a href="https://github.com/elhardoum/bbpress-private-answer"><?php _e('Fork on Github', 'bbpress-private-answer'); ?></a></li>
                <p style="font-weight:600"><?php _e('More bbPress plugins by Samuel Elh:', 'bbpress-private-answer'); ?></p>
                <li><a href="https://go.samelh.com/get/bbpress-ultimate/"><?php _e('bbPress Messages', 'bbpress-private-answer'); ?></a>: <?php _e('Bring private messaging feature to your forum and communicate with your community privately!', 'bbpress-private-answer'); ?></li>
                <li><a href="https://go.samelh.com/get/bbpress-ultimate/"><?php _e('bbPress Ultimate', 'bbpress-private-answer'); ?></a>: <?php _e('Add more user info to your forums/profiles, e.g online status, user country, social profiles and more..', 'bbpress-private-answer'); ?></li>
                <li><a href="https://go.samelh.com/get/bbpress-thread-prefixes/"><?php _e('bbPress Thread Prefixes', 'bbpress-private-answer'); ?></a>: <?php _e('Easily generate prefixes for topics and assign groups of prefixes for each forum..', 'bbpress-private-answer'); ?></li>
                <li><a href="https://wordpress.org/plugins/bbp-mentions-email-notifications/"><?php _e('bbPress Mentions Email Notifications', 'bbpress-private-answer'); ?></a>: <?php _e('If anyone mentions a user by @username on the forums, notify this user with a custom email. This feature ships with BuddyPress.', 'bbpress-private-answer'); ?></li>
                <p style="font-weight:600"><?php _e('Subscribe for more!', 'bbpress-private-answer'); ?></p>
                <p><?php _e('We have upcoming bbPress projects that we are very excited to work on. <a href="https://go.samelh.com/newsletter/">Subscribe to our newsletter</a> to get them first!', 'bbpress-private-answer'); ?></p>
                <p style="font-weight:600"><?php _e('Need a custom bbPress plugin? <a href="https://samelh.com/work-with-me/">Hire me!</a>', 'bbpress-private-answer'); ?></p>

            </div>
        </div>
        <?php
    }

    public static function obStart()
    {
        return ob_start();
    }

}