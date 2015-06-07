<?php
/*
 * Settings Page for "Ank Simplified GA" Plugin
 * Lets keep admin area code here
 */

/* No direct access */
if (!defined('ABSPATH')) exit;
if (!defined('ASGA_BASE_FILE')) wp_die('What ?');

class ASGA_Admin_Class
{

    protected static $instance = null;
    /*store plugin option page slug,so that we can change it with ease */
    private $plugin_slug = 'asga_options_page';
    /*store database option field name to avoid confusion */
    private $option_name = 'asga_options';

    function __construct()
    {
        // If instance is null, create it. Prevent creating multiple instances of this class
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }
        /*to save default options upon activation*/
        register_activation_hook(plugin_basename(ASGA_BASE_FILE), array($this, 'add_default_options'));

        /*for register setting*/
        add_action('admin_init', array($this, 'ASGA_admin_init'));

        /*settings link on plugin listing page*/
        add_filter('plugin_action_links_' . plugin_basename(ASGA_BASE_FILE), array($this, 'add_plugin_actions_links'), 10, 2);

        /* Add settings link under admin->settings menu */
        add_action('admin_menu', array($this, 'add_to_settings_menu'));

        /* Show warning if debug mode is on  */
        add_action('admin_notices', array($this, 'show_admin_notice'));

    }

    /**
     * Function to instantiate our class and make it a singleton
     */
    public static function get_instance()
    {

        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /*
     * Save default settings upon plugin activation
     */
    function add_default_options()
    {
        //if options already exists then return early
        if (get_option($this->option_name)) return;

        update_option($this->option_name, $this->getDefaultOptions());
    }

    /*Register our settings, using WP settings API*/
    function ASGA_admin_init()
    {
        register_setting('asga_plugin_options', $this->option_name, array($this, 'ASGA_validate_options'));
    }


    /**
     * Adds a 'Settings' link for this plugin on plugin listing page
     *
     * @param $links
     * @param $file
     * @return array  Links array
     */
    function add_plugin_actions_links($links, $file)
    {
        if (current_user_can('manage_options')) {
            $build_url = add_query_arg('page', $this->plugin_slug, 'options-general.php');
            array_unshift(
                $links,
                sprintf('<a href="%s">%s</a>', $build_url, __('Settings'))
            );
        }

        return $links;
    }

    /*
     * Adds link to Plugin Option page + do related stuff
     */
    function add_to_settings_menu()
    {
        $page_hook_suffix = add_submenu_page('options-general.php', 'Ank Simplified GA', 'Ank Simplified GA', 'manage_options', $this->plugin_slug, array($this, 'ASGA_Options_Page'));
        /*add help stuff via tab*/
        add_action("load-$page_hook_suffix", array($this, 'add_help_menu_tab'));
        /*we can load additional css/js to our option page here */

    }

    /**
     * Return all roles plus superAdmin if multi-site is enabled
     * @return array
     */
    private function getAllRoles()
    {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        $role_list = $wp_roles->get_names();
        //append a custom role if multi-site is enabled
        if (is_multisite()) {
            $role_list['networkAdmin'] = 'Network Admin';
        }

        return $role_list;
    }

    /**
     * Return default options for this plugin
     * @return array
     */
    private function getDefaultOptions()
    {
        $defaults = array(
            'plugin_ver' => ASGA_PLUGIN_VER,
            'ga_id' => '',
            'js_location' => 1, //default to header
            'js_priority' => 10,
            'log_404' => 0,
            'log_search' => 0,
            'ua_enabled' => 1, //UA is enabled by default
            'displayfeatures' => 0,
            'ga_ela' => 0,
            'anonymise_ip' => 0,
            'ga_domain' => '',
            'debug_mode' => 0
        );
        //store roles as well
        foreach ($this->getAllRoles() as $role => $role_info) {
            $defaults = array_merge($defaults, array('ignore_role_' . $role => 0));
        }
        return $defaults;
    }


    /**
     * Callback Function to handle and validate the form data
     *
     * @param array $in - POST array
     * @returns array - Validated array
     */
    function ASGA_validate_options($in)
    {

        $out = array();
        //always store plugin version to db
        $out['plugin_ver'] = ASGA_PLUGIN_VER;

        // Get the actual tracking ID
        if (!preg_match('|^UA-\d{4,}-\d+$|', (string)$in['ga_id'])) {
            $out['ga_id'] = '';
            //warn user that the entered id is not valid
            add_settings_error($this->option_name, 'ga_id', 'Your GA tracking ID seems invalid. Please check.');
        } else {
            $out['ga_id'] = esc_attr($in['ga_id']);
        }

        $out['js_location'] = absint($in['js_location']);
        $out['js_priority'] = ($in['js_priority'] == '') ? 10 : absint($in['js_priority']);

        $out['ga_domain'] = esc_attr($in['ga_domain']);

        $checkbox_items = array('ua_enabled', 'anonymise_ip', 'displayfeatures', 'ga_ela', 'log_404', 'log_search','debug_mode');
         //add rolls to checkbox_items array
        foreach ($this->getAllRoles() as $role => $role_info) {
            $checkbox_items[] = 'ignore_role_' . $role;
        }

        foreach ($checkbox_items as $checkbox_item) {
            if (isset($in[$checkbox_item]) && '1' == $in[$checkbox_item])
                $out[$checkbox_item] = 1;
            else
                $out[$checkbox_item] = 0;
        }

        return $out;
    }

    /**
     * Function will print our option page form
     */
     function ASGA_Options_Page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h2><i class="dashicons dashicons-chart-bar" style="vertical-align: middle"></i> Ank Simplified Google Analytics <small>(v<?php echo ASGA_PLUGIN_VER; ?>)</small> </h2>
            <form action="<?php echo admin_url('options.php') ?>" method="post" id="asga_form">
                <?php
                $options = $this->getSafeOptions();
                //wp inbuilt nonce field , etc
                settings_fields('asga_plugin_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Google Analytics tracking ID :</th>
                        <td><input type="text" placeholder="UA-XXXXXXXX-X" name="asga_options[ga_id]" value="<?php echo esc_attr($options['ga_id']); ?>" required="" aria-required="true"> <br>
                            <p class="description">Paste your Google Analytics <a target="_blank" href="https://support.google.com/analytics/answer/1032385">tracking ID</a> (e.g. "UA-XXXXXXXX-X")</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Universal GA :</th>
                        <td><label><input type="checkbox" name="asga_options[ua_enabled]" value="1" <?php checked($options['ua_enabled'], 1) ?>>Enable Universal Google Analytics</label>
                            <p class="description">Un-check this, If you are using Classic GA . OR <a href="https://support.google.com/analytics/answer/3450662" target="_blank">upgrade</a>. </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Set domain :</th>
                        <td><input type="text" placeholder="auto" name="asga_options[ga_domain]" value="<?php echo esc_attr($options['ga_domain']); ?>">
                            <?php
                            //print sub-domain url on screen , when multi-site is enabled
                            if(!is_main_site()){
                                printf('<p class="description">%s</p>',get_blogaddress_by_id(get_current_blog_id())) ;
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Display Advertising :</th>
                        <td><label><input type="checkbox" name="asga_options[displayfeatures]" value="1" <?php checked($options['displayfeatures'], 1) ?>>Check to enable <a target="_blank" href="https://support.google.com/analytics/answer/3450482">Read More</a></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enhanced Link Attribution :</th>
                        <td><label><input type="checkbox" name="asga_options[ga_ela]" value="1" <?php checked($options['ga_ela'], 1) ?>>Check to Enable <a target="_blank" href="https://support.google.com/analytics/answer/2558867">Read more</a> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Anonymize IP's :</th>
                        <td><label><input type="checkbox" name="asga_options[anonymise_ip]" value="1" <?php checked($options['anonymise_ip'], 1) ?>>Anonymize IP <a href="https://support.google.com/analytics/answer/2763052" target="_blank">Read more</a></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Code Location :</th>
                        <td>
                            <label><input type="radio" name="asga_options[js_location]" value="1" <?php checked($options['js_location'], 1) ?>>&ensp;Place in document header</label><br>
                            <label><input type="radio" name="asga_options[js_location]" value="2" <?php checked($options['js_location'], 2) ?>>&ensp;Place in document footer</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Action Priority :</th>
                        <td><input type="number" min="0" max="999" placeholder="10" name="asga_options[js_priority]" value="<?php echo esc_attr($options['js_priority']); ?>">
                            <p class="description">0 means highest priority, default is 10</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Disable Tracking when :</th>
                        <td>
                            <?php
                            foreach ($this->getAllRoles() as $id => $label) {
                                echo '<label>';
                                echo '<input type="checkbox" name="asga_options[ignore_role_' . $id . ']" value="1" ' . checked($options['ignore_role_' . $id], 1, 0) . '/>';
                                echo '&ensp;' . esc_attr($label) . ' is logged in';
                                echo '</label><br />';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Event Tracking :</th>
                        <td>
                            <?php
                            $events = array(
                                'log_404' => 'Log 404 errors as events',
                                'log_search' => 'Log searched items'
                            );
                            //loop through each event item
                            foreach ($events as $event => $label) {
                                echo '<label>';
                                echo '<input type="checkbox" name="asga_options[' . $event . ']" value="1" ' . checked($options[$event], 1, 0) . '/>';
                                echo '&ensp;' . $label . '</label><br>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Troubleshoot :</th>
                        <td><label><input type="checkbox" name="asga_options[debug_mode]" value="1" <?php checked($options['debug_mode'], 1) ?>>Enable Debugging mode for admins <a target="_blank" href="https://developers.google.com/analytics/resources/articles/gaTrackingTroubleshooting#gaDebug">Read more</a> </label>

                            <p class="description">This should only be used temporarily or during development, don't forget to disable it in production.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Options') ?>
            </form>
            <p>Developed by- <a target="_blank" href="http://ank91.github.io/">Ankur Kumar</a> |
                Fork on <a href="https://github.com/ank91/ank-simplified-ga" target="_blank">GitHub</a></p>
        </div> <!-- .wrap-->
        <?php
        //debugging information shows options saved in database
        if (isset($_GET['debug']) || WP_DEBUG == true) {
            echo '<hr><p><h5>Showing Debugging Info:</h5>';
            var_dump($options);
            echo '</p><hr>';
        }?>
    <?php
    }

    /**
     * Function will add help tab to our option page
     * @require wp v3.3+
     */
    function add_help_menu_tab()
    {
        /*get current screen object*/
        $curr_screen = get_current_screen();

        $curr_screen->add_help_tab(
            array(
                'id' => 'asga-overview',
                'title' => 'Basic',
                'content' => '<p><strong>Do you have a Google Analytics Account ?</strong><br>' .
                    'In order to use this plugin you need to have a Google Analytics Account. Create an account <a target="_blank" href="http://www.google.com/analytics">here</a>. It is FREE. <br>' .
                    '<strong>How do i find my Google Analytics ID ?</strong><br>' .
                    'Please check out this <a target="_blank" href="https://support.google.com/analytics/answer/1032385?hl=en">link</a><br>' .
                    '<strong>How do i view my stats ?</strong><br>' .
                    'Login to Google Analytics Account with your G-Mail ID to view stats.' .
                    '</p>'

            )
        );

        $curr_screen->add_help_tab(
            array(
                'id' => 'asga-troubleshoot',
                'title' => 'Troubleshoot',
                'content' => '<p><strong>Things to remember</strong><br>' .
                    '<ul>
                <li>If you are using a cache/performance plugin, you need to flush/delete your site cache after saving settings here.</li>
                <li>It can take up to 24-48 hours after adding the tracking code before any analytical data appears in your Google Analytics account. </li>
                </ul>
                </p>'

            )
        );
        $curr_screen->add_help_tab(
            array(
                'id' => 'asga-more-info',
                'title' => 'More',
                'content' => '<p><strong>Need more information ?</strong><br>' .
                    'A brief FAQ is available to solve your common issues, ' .
                    'click <a href="https://wordpress.org/plugins/ank-simplified-ga/faq/" target="_blank">here</a> for more.<br>' .
                    'Support is only available on WordPress Forums, click <a href="http://wordpress.org/support/plugin/ank-simplified-ga" target="_blank">here</a> to ask anything about this plugin.<br>' .
                    'You can also browse the source code of this  plugin on <a href="https://github.com/ank91/ank-simplified-ga" target="_blank">GitHub</a>. ' .
                    '</p>'

            )
        );

        /*add a help sidebar with links */
        $curr_screen->set_help_sidebar(
            '<p><strong>Quick Links</strong></p>' .
            '<p><a href="https://wordpress.org/plugins/ank-simplified-ga/faq/" target="_blank">Plugin FAQ</a></p>' .
            '<p><a href="https://github.com/ank91/ank-simplified-ga" target="_blank">Plugin Home</a></p>'
        );
    }

    /**
     * Show a warning notice on all screens if debug mode is on
     */
    function show_admin_notice()
    {   //notice will be shown to admin user only
        if ($this->check_admin_notice()) {
             ?>
            <div id="asga_message" class="notice notice-warning is-dismissible">
                <p>Google Analytics debug mode is on. Don't leave this option enabled in production.
                    <?php
                    //don't show settings link when user is on our options page
                    if (strpos(get_current_screen()->id, $this->plugin_slug) === false) { ?>
                        <a title="Go to settings page" href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug); ?>">Settings</a>
                    <?php } ?>
                </p>
            </div>
        <?php
        }
    }

    /**
     * Check if to show admin notice or not
     * @return bool
     */
    private function check_admin_notice()
    {
        $options = $this->getSafeOptions();
        //id ga id is not set return early
        if (empty($options['ga_id'])) return false;
        //if current user is not admin then return early
        if (!current_user_can('manage_options')) return false;
        //if debug mode is off return early
        if ($options['debug_mode'] == 0) return false;
        //else return true
        return true;

    }

    /**
     * Get fail safe options
     * @return array
     */
    private function getSafeOptions()
    {
        //get fresh options from db
        $options = get_option($this->option_name);
        //if options not exists in db then init with defaults , also always append default options to existing options
        $options = empty($options) ? $this->getDefaultOptions() : $options + $this->getDefaultOptions();
        return $options;

    }

} //end class
