<?php

if ( ! class_exists( 'WMobilePack_Admin' ) ) {
    require_once(PWA_PLUGIN_PATH.'admin/class-admin.php');
}

if ( ! class_exists( 'WMobilePack_Themes_Config' )) {
    require_once(PWA_PLUGIN_PATH.'inc/class-wmp-themes-config.php');
}

if ( ! class_exists( 'WMobilePack_Admin_Init' ) ) {

    /**
     * WMobilePack_Admin_Init class for initializing the admin area for the Wordpress Mobile Pack plugin
     *
     * Displays menu & loads static files for each admin page.
     */
    class WMobilePack_Admin_Init
    {

        /**
         * The menu item's title
         * @var string
         */
        private static $submenu_title = PWA_PLUGIN_NAME;

        /**
         * Submenu pages arrays. Each item has the following properties:
         *
         * - page_title = The page's and menu's title
         * - capability = GET parameter to be sent to the admin.php page
         * - function = The admin function that display the page (from class-admin.php)
         * - enqueue_hook = (optional) The method that adds the Javascript & CSS files required by each page
         *
         * @var array
         */
        private static $submenu_pages = array(
            array(
                'page_title' => "Quick Start",
                'capability' => 'wmp-options',
                'function' => 'whatsnew',
            ),
			array(
                'page_title' => "App Themes",
                'capability' => 'wmp-options-themes',
                'function' => 'themes',
            ),
            array(
                'page_title' => "Look & Feel",
                'capability' => 'wmp-options-theme-settings',
                'function' => 'theme_settings',
            ),
            array(
                'page_title' => "Content",
                'capability' => 'wmp-options-content',
                'function' => 'content',
                'enqueue_hook' => 'wmp_admin_load_content_js'
            ),
            array(
                'page_title' => "Settings",
                'capability' => 'wmp-options-settings',
                'function' => 'settings',
            )
        );


        /**
         * Class constructor
         *
         * Init admin menu and enqueue general Javascript & CSS files
         */
        public function __construct()
        {
            // enqueue css and javascript for the admin area
            add_action('admin_enqueue_scripts', array(&$this, 'wmp_admin_enqueue_scripts'));

            // add admin menu hook
            add_action('admin_menu', array(&$this, 'wmp_admin_menu'));
        }


        /**
         *
         * Build the admin menu and add all admin pages of the plugin
         *
         */
        public function wmp_admin_menu()
        {

            // init admin object
            $WMobilePackAdmin = new WMobilePack_Admin();

            $pages_list = self::$submenu_pages;

			$menu_name = 'wmp-options';

			// check if we need to request updates for the what's new section
			$WMobilePackCookie = new WMobilePack_Cookie();

			// display notify icon if the what's new section was updated
			$display_notify_icon = false;
			if (WMobilePack_Options::get_setting('whats_new_updated') == 1) {
				$display_notify_icon = true;
			}
            
            // add menu and submenu hooks
            add_menu_page(self::$submenu_title, self::$submenu_title, 'manage_options', $menu_name, '', WP_PLUGIN_URL . '/' . PWA_DOMAIN . '/admin/images/menu-icon2' . ($display_notify_icon == true ? '-updates' : '') . '.png');

            foreach ($pages_list as $submenu_item) {

                // add page in the submenu
                $submenu_page = add_submenu_page($menu_name, $submenu_item['page_title'], $submenu_item['page_title'], 'manage_options', $submenu_item['capability'], array(&$WMobilePackAdmin, $submenu_item['function']));

                // enqueue js files for each subpage
                if (isset($submenu_item['enqueue_hook']) && $submenu_item['enqueue_hook'] != '') {
                    add_action('load-' . $submenu_page, array(&$this, $submenu_item['enqueue_hook']));
                }
            }

            if ($menu_name == 'wmp-options'){

                // fake submenu since it is not visible (for editing a category's details)
                $category_page = add_submenu_page( null, 'Content', 'Category Details', 'manage_options', 'wmp-category-details', array( &$WMobilePackAdmin, 'category_content') );
                add_action( 'load-' . $category_page, array( &$this, 'wmp_admin_load_category_js' ) );

                // fake submenu since it is not visible (for editing a page's details)
                $pages_page = add_submenu_page(null, 'Content', 'Page Details', 'manage_options', 'wmp-page-details', array(&$WMobilePackAdmin, 'page_content'));
                add_action('load-' . $pages_page, array(&$this, 'wmp_admin_load_page_js'));
            }
        }


        /**
         *
         * The wmp_admin_enqueue_scripts is used to enqueue scripts and styles for the admin area.
         * The scripts and styles loaded by this method are used on all admin pages.
         *
         */
        public function wmp_admin_enqueue_scripts()
        {
            // enqueue styles
            wp_enqueue_style(WMobilePack_Options::$prefix.'css_general', plugins_url(PWA_DOMAIN.'/admin/css/general-1493993256.css'), array(), PWA_VERSION);

            // enqueue scripts
            $dependencies = array('jquery-core', 'jquery-migrate');

            wp_enqueue_script(WMobilePack_Options::$prefix.'js_validate', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/Lib/jquery.validate.min.js'), $dependencies, '1.11.1');
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_validate_additional', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/Lib/validate-additional-methods.min.js'), $dependencies, '1.11.1');
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_loader', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/Loader.min.js'), $dependencies, PWA_VERSION);
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_ajax_upload', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/AjaxUpload.min.js'), $dependencies, PWA_VERSION);
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_interface', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/JSInterface.min.js'), $dependencies, PWA_VERSION);
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_scrollbar', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/Lib/perfect-scrollbar.min.js'), array(), PWA_VERSION);

            wp_enqueue_script(WMobilePack_Options::$prefix.'js_join_waitlist', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Waitlist/WMP_WAITLIST.min.js'), array(), PWA_VERSION);
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_feedback', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Feedback/WMP_SEND_FEEDBACK.min.js'), array(), PWA_VERSION);

            if (WMobilePack_Options::get_setting('upgrade_notice_updated') == 1){
                wp_enqueue_script(WMobilePack_Options::$prefix.'js_upgrade_notice', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Waitlist/WMP_UPGRADE_NOTICE.min.js'), array(), PWA_VERSION, true);
            }
        }

        /**
         *
         * Load specific javascript files for the admin Look & Feel submenu page
         *
         */
        public function wmp_admin_load_theme_settings_js()
        {

			wp_enqueue_style(WMobilePack_Options::$prefix.'css_select_box_it', plugins_url(PWA_DOMAIN.'/admin/css/jquery.selectBoxIt.css'), array(), '3.8.1');
			wp_enqueue_script(WMobilePack_Options::$prefix.'js_select_box_it', plugins_url(PWA_DOMAIN.'/admin/js/UI.Interface/Lib/jquery.selectBoxIt.min.js'), array('jquery','jquery-ui-core', 'jquery-ui-widget'), '3.8.1');

            wp_enqueue_style('wp-color-picker');
        }


        /**
         *
         * Load specific javascript files for the admin Content submenu page
         *
         */
        public function wmp_admin_load_content_js()
        {
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_content_editcategories', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Content/WMP_EDIT_CATEGORIES.min.js'), array(), PWA_VERSION);
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_content_editpages', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Content/WMP_EDIT_PAGES.min.js'), array(), PWA_VERSION);
            wp_enqueue_script('jquery-ui-sortable');
        }


        /**
         *
         * Load specific javascript files for the admin category details
         *
         */
        public function wmp_admin_load_category_js()
        {
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_content_categorydetails', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Content/WMP_CATEGORY_DETAILS.min.js'), array(), PWA_VERSION);
        }

        /**
         *
         * Load specific javascript files for the admin Content submenu page
         *
         */
        public function wmp_admin_load_page_js()
        {
            wp_enqueue_script(WMobilePack_Options::$prefix.'js_content_pagedetails', plugins_url(PWA_DOMAIN.'/admin/js/UI.Modules/Content/WMP_PAGE_DETAILS.min.js'), array(), PWA_VERSION);
        }
    }
}
