<?php
/**
 * Plugin class.
 *
 * @package   Credit_Tracker
 * @author    Labs64 <info@labs64.com>
 * @license   GPL-2.0+
 * @link      http://www.labs64.com
 * @copyright 2013 Labs64
 */

class Credit_Tracker
{

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @var     string
     */
    const VERSION = '1.0.0';

    /**
     * Unique identifier for your plugin.
     *
     * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
     * match the Text Domain file header in the main plugin file.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'credit-tracker';

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    /**
     * Slug of the plugin screen.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_screen_hook_suffix = null;

    /**
     * Initialize the plugin by setting localization, filters, and administration functions.
     *
     * @since     1.0.0
     */
    private function __construct()
    {
        require_once(plugin_dir_path(__FILE__) . 'options-credit-tracker.php');

        // Load plugin text domain
        add_action('init', array($this, 'load_plugin_textdomain'));

        // Activate plugin when new blog is added
        add_action('wpmu_new_blog', array($this, 'activate_new_site'));

        // Add the options page and menu item.
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // Add an action link pointing to the options page.
        $plugin_basename = plugin_basename(plugin_dir_path(__FILE__) . 'credit-tracker.php');
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_action_links'));

        // Load admin style sheet and JavaScript.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Load public-facing style sheet and JavaScript.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Define custom functionality. Read more about actions and filters: http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
        add_filter('attachment_fields_to_edit', array($this, 'get_attachment_fields'));
        add_filter('attachment_fields_to_save', array($this, 'save_attachment_fields'));
    }

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance()
    {
        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Fired when the plugin is activated.
     *
     * @since    1.0.0
     *
     * @param    boolean $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
     */
    public static function activate($network_wide)
    {
        if (function_exists('is_multisite') && is_multisite()) {
            if ($network_wide) {
                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {
                    switch_to_blog($blog_id);
                    self::single_activate();
                }
                restore_current_blog();
            } else {
                self::single_activate();
            }
        } else {
            self::single_activate();
        }
    }

    /**
     * Fired when the plugin is deactivated.
     *
     * @since    1.0.0
     *
     * @param    boolean $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
     */
    public static function deactivate($network_wide)
    {
        if (function_exists('is_multisite') && is_multisite()) {
            if ($network_wide) {
                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {
                    switch_to_blog($blog_id);
                    self::single_deactivate();
                }
                restore_current_blog();
            } else {
                self::single_deactivate();
            }
        } else {
            self::single_deactivate();
        }
    }

    /**
     * Fired when a new site is activated with a WPMU environment.
     *
     * @since    1.0.0
     *
     * @param    int $blog_id ID of the new blog.
     */
    public function activate_new_site($blog_id)
    {
        if (1 !== did_action('wpmu_new_blog')) {
            return;
        }

        switch_to_blog($blog_id);
        self::single_activate();
        restore_current_blog();
    }

    /**
     * Get all blog ids of blogs in the current network that are:
     * - not archived
     * - not spam
     * - not deleted
     *
     * @since    1.0.0
     *
     * @return    array|false    The blog ids, false if no matches.
     */
    private static function get_blog_ids()
    {
        global $wpdb;

        // get an array of blog ids
        $sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
        return $wpdb->get_col($sql);
    }

    /**
     * Fired for each blog when the plugin is activated.
     *
     * @since    1.0.0
     */
    private static function single_activate()
    {
        // TODO: Define activation functionality here
    }

    /**
     * Fired for each blog when the plugin is deactivated.
     *
     * @since    1.0.0
     */
    private static function single_deactivate()
    {
        // TODO: Define deactivation functionality here
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        $domain = $this->plugin_slug;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, trailingslashit(WP_LANG_DIR) . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, FALSE, basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * Register and enqueue admin-specific style sheet.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_styles()
    {
        if (!isset($this->plugin_screen_hook_suffix)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen->id == $this->plugin_screen_hook_suffix) {
            wp_enqueue_style($this->plugin_slug . '-admin-styles', plugins_url('css/admin.css', __FILE__), array(), self::VERSION);
        }

    }

    /**
     * Register and enqueue admin-specific JavaScript.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_scripts()
    {
        if (!isset($this->plugin_screen_hook_suffix)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen->id == $this->plugin_screen_hook_suffix) {
            wp_enqueue_script($this->plugin_slug . '-admin-script', plugins_url('js/admin.js', __FILE__), array('jquery'), self::VERSION);
        }

    }

    /**
     * Register and enqueue public-facing style sheet.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_slug . '-plugin-styles', plugins_url('css/public.css', __FILE__), array(), self::VERSION);
    }

    /**
     * Register and enqueues public-facing JavaScript files.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_slug . '-plugin-script', plugins_url('js/public.js', __FILE__), array('jquery'), self::VERSION);
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu()
    {
        $this->plugin_screen_hook_suffix = add_options_page(
            __('Credit Tracker', $this->plugin_slug),
            __('Credit Tracker', $this->plugin_slug),
            'manage_options',
            $this->plugin_slug,
            'credit_tracker_options_page'
        );

    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links($links)
    {
        return array_merge(
            array(
                'settings' => '<a href="' . admin_url('options-general.php?page=credit-tracker') . '">' . __('Settings', $this->plugin_slug) . '</a>'
            ),
            $links
        );
    }

    public function get_attachment_fields($form_fields, $post)
    {
        $options = credit_tracker_get_plugin_options();

        if (!$options['author']['hide']) {
            $lbAuthor = $options['author']['lbl'];
            if (empty($lbAuthor)) {
                $lbAuthor = __('Author', $this->plugin_slug);
            }

            $author = get_post_meta($post->ID, '_credit_tracker_author', true);
            $form_fields['credit_tracker_author']['tr'] = '<tr><td colspan="2" style="width:800px;"><p>
                <label for="credit_tracker_author"><strong>' . $lbAuthor . '</strong></label><br>
                <input type="text" value="' . $author . '" id="attachments-' . $post->ID . '-credit_tracker_author" name="attachments[' . $post->ID . '][credit_tracker_author]"  class="widefat" />
                </p></td></tr>';
        }

        if (!$options['publisher']['hide']) {
            $lbPublisher = $options['publisher']['lbl'];
            if (empty($lbPublisher)) {
                $lbPublisher = __('Publisher', $this->plugin_slug);
            }

            $publisher = get_post_meta($post->ID, '_credit_tracker_publisher', true);
            $form_fields['credit_tracker_publisher']['tr'] = '<tr><td colspan="2" style="width:800px;"><p>
            <label for="credit_tracker_publisher"><strong>' . $lbPublisher . '</strong></label><br>
            <input type="text" value="' . $publisher . '" id="attachments-' . $post->ID . '-credit_tracker_publisher" name="attachments[' . $post->ID . '][credit_tracker_publisher]"  class="widefat" />
            </p></td></tr>';
        }

        if (!$options['ident_nr']['hide']) {
            $lbIdentNr = $options['ident_nr']['lbl'];
            if (empty($lbIdentNr)) {
                $lbIdentNr = __('Ident-Nr.', $this->plugin_slug);
            }

            $ident_nr = get_post_meta($post->ID, '_credit_tracker_ident_nr', true);
            $form_fields['credit_tracker_ident_nr']['tr'] = '<tr><td colspan="2" style="width:800px;"><p>
                <label for="credit_tracker_ident_nr"><strong>' . $lbIdentNr . '</strong></label><br>
                <input type="text" value="' . $ident_nr . '" id="attachments-' . $post->ID . '-credit_tracker_ident_nr" name="attachments[' . $post->ID . '][credit_tracker_ident_nr]"  class="widefat" />
                </p></td></tr>';
        }

        if (!$options['license']['hide']) {
            $lbLicense = $options['license']['lbl'];
            if (empty($lbLicense)) {
                $lbLicense = __('License', $this->plugin_slug);
            }

            $set = get_post_meta($post->ID, '_credit_tracker_license', true);
            $form_fields['credit_tracker_license']['tr'] = '<tr><td colspan="2" style="width:800px;"><p>
                <label for="credit_tracker_license"><strong>' . $lbLicense . '</strong></label><br>
                <input type="text" value="' . $set . '" id="attachments-' . $post->ID . '-credit_tracker_license" name="attachments[' . $post->ID . '][credit_tracker_license]"  class="widefat" />
                </p></td></tr>';
        }

        return $form_fields;
    }

    public function save_attachment_fields($post, $attachment)
    {
        if (isset($attachment['credit_tracker_author'])) {
            update_post_meta($post['ID'], '_credit_tracker_author', $attachment['credit_tracker_author']);
        }
        if (isset($attachment['credit_tracker_publisher'])) {
            update_post_meta($post['ID'], '_credit_tracker_publisher', $attachment['credit_tracker_publisher']);
        }
        if (isset($attachment['credit_tracker_ident_nr'])) {
            update_post_meta($post['ID'], '_credit_tracker_ident_nr', $attachment['credit_tracker_ident_nr']);
        }
        if (isset($attachment['credit_tracker_license'])) {
            update_post_meta($post['ID'], '_credit_tracker_license', $attachment['credit_tracker_license']);
        }
        return $post;
    }

}
