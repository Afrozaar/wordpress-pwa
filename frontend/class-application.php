<?php

if (!class_exists('WMobilePack_Application')) {

    /**
     *
     * WMobilePack_Application
     *
     * Main class for managing frontend apps
     *
     */
    class WMobilePack_Application {


        /**
         * Class constructor
         */
        public function __construct($plugin_dir)
        {
            // Load application only if the PRO plugin is not active
            if (!WMobilePack::is_active_plugin('WordPress Mobile Pack PRO'))
                $this->check_load();

            $this->plugin_dir = $plugin_dir;

			add_action( 'rest_api_init', function() {
				remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
				add_filter( 'rest_pre_serve_request', function( $value ) {
					header( 'Access-Control-Allow-Origin: *' );
					header( 'Access-Control-Allow-Methods: GET' );
					header( 'Access-Control-Allow-Credentials: true' );
					header( 'Access-Control-Expose-Headers: Link', false );
					return $value;
				});
			}, 15 );
        }


        /**
         *
         * Create a cookie management object and return it
         *
         * @return object
         *
         */
        protected function get_cookie_manager()
        {
            return new WMobilePack_Cookie();
        }

        /**
         *
         * Method that checks if we can load the mobile web application theme.
         *
         * The theme is loaded if ALL of the following conditions are met:
         *
         * - the user comes from a supported mobile device and browser
         * - the user has not deactivated the view of the mobile theme by switching to desktop mode
         * - the display mode of the app is set to 'normal' or is set to 'preview' and an admin is logged in
         *
         */
        public function check_load()
        {
            // Set app as visible by default
            $visible_app = true;

            if($_GET["noapp"] || $_REQUEST["noapp"]){
                $visible_app = false;
            }

            if (strpos($_SERVER['REQUEST_URI'], 'about-us') || 
                strpos($_SERVER['REQUEST_URI'], 'contact-us') ||
                strpos($_SERVER['REQUEST_URI'], 'contact') ||
                strpos($_SERVER['REQUEST_URI'], 'win') ||
                strpos($_SERVER['REQUEST_URI'], 'advertise') ||
                strpos($_SERVER['REQUEST_URI'], 'terms') ||
                strpos($_SERVER['REQUEST_URI'], 'cookie-policy') ||
                strpos($_SERVER['REQUEST_URI'], 'privacy-policy'))
            {
                $visible_app = false;
            }

            // Assume the app will not be loaded
            $load_app = false;

            if ($visible_app) {

                // Check if the load app cookie is 1 or the user came from a mobile device
                $cookie_manager = $this->get_cookie_manager();
                // $load_app_cookie = $cookie_manager->get_cookie('load_app');
                $load_app_cookie = null;
                // If the load_app cookie is not set, verify the device
                if ($load_app_cookie === null) {
                    $load_app = $this->check_device();

                } elseif ($load_app_cookie == 1) {

                    // The cookie was already set for the device, so we can load the app
                    $load_app = true;
                }
            } else {
                $themeManager = new ThemeManager(new Theme());
                $theme = $themeManager->getTheme();

                // The user is shown a button to redirect them back to the app
                if($theme->getShowClassicSwitch()) {
                    add_action('wp_enqueue_scripts', function() {
                        wp_enqueue_script('show_classic_switch', $this->plugin_dir . '/frontend/themes/app2/js/classic_switch.js', null, null, false);    
                    });
                }
            }

            // If we need to add the rel=alternate links in the header
            $show_alternate = true;

            // We have a mobile device and the app is visible, so we can load the app
            if ($load_app) {

                // Check if the user deactivated the app display
                $desktop_mode = $this->check_desktop_mode();

                if ($desktop_mode == false) {

					// We're loading the mobile web app, so we don't need the rel=alternate links
					$show_alternate = false;
					$this->load_app();

                } else {

                    // The user returned to desktop, so show him a smart app banner
					add_action('wp_head', array(&$this, 'show_smart_app_banner'));

                    // Add hook in footer to show the switch to mobile link
                    add_action('wp_footer', array(&$this, 'show_mobile_link'));
                }
            }
        }

        public function show_classic_switch() {
            ?>
                <script>
                    window.onload = function() {
                        function onMobileButtonClick() {
                            document.cookie = "classicCookie=false;"
                            location.href = location.href.replace("?noapp=true","");
                        }

                        var mobileButton = document.createElement('div');

                        mobileButton.textContent = "Switch to mobile";
                        mobileButton.onclick = onMobileButtonClick;
                        mobileButton.id = 'classicSwitch';
                        mobileButton.style.position = "fixed";
                        mobileButton.style.backgroundColor = "#218CC6";
                        mobileButton.style.color = "#FFF";
                        mobileButton.style.bottom = "2%";
                        mobileButton.style.right = "2%";
                        mobileButton.style.padding = "7px";
                        mobileButton.style.fontSize = "12px";
                        
                        document.body.insertAdjacentElement('beforeend', mobileButton);
                    }
                </script>
            <?php
        }

        /**
         *
         * Check if the app display is enabled
         *
         * Returns true if display mode is "normal" (enabled for all mobile users) or
         * if display mode is "preview" and an admin is logged in.
         *
         * @return bool
         *
         */
        protected function check_display_mode()
        {

            $display_mode = WMobilePack_Options::get_setting('display_mode');

            if ($display_mode == 'normal')
                return true;

            elseif ($display_mode == 'preview') {

                if (is_user_logged_in() && current_user_can('create_users'))
                    return true;
            }

            return false;
        }


        /**
         *
         * Call the mobile detection method to verify if we have a supported device
         *
         * @return bool
         *
         */
        protected function check_device(){

            if ( ! class_exists( 'WMobilePack_Detect' ) ) {
                require_once(PWA_PLUGIN_PATH.'frontend/class-detect.php');
            }

            $WMobileDetect = new WMobilePack_Detect();
            return $WMobileDetect->detect_device();
        }


        /**
         *
         * Check if the user selected to view the desktop mode or we can display the app.
         *
         * The GET/COOKIE "theme_mode" can have two values: 'desktop' or 'mobile'.
         *
         * - Desktop mode can be activated from the app by selecting to return to desktop view.
         * - Mobile mode can be reactivated from the footer of the website.
         *
         * @return bool
         *
         */
        protected function check_desktop_mode()
        {

            $desktop_mode = false;

            $cookie_manager = $this->get_cookie_manager();
            $param_name = WMobilePack_Cookie::$prefix.'theme_mode';

            if (isset($_GET[$param_name]) && is_string($_GET[$param_name])){

                $theme_mode = $_GET[$param_name];

                if ($theme_mode == "desktop" || $theme_mode == "mobile"){
                    $cookie_manager->set_cookie('theme_mode', $theme_mode, 3600*30*24);
                }

                if ($theme_mode == "desktop")
                    $desktop_mode = true;

            } else {

                $theme_mode_cookie = $cookie_manager->get_cookie('theme_mode');

                if ($theme_mode_cookie){
                    if ($theme_mode_cookie == "desktop")
                        $desktop_mode = true;
                }
            }

            return $desktop_mode;
        }


        /**
         *
         * Method that loads the mobile web application theme.
         *
         * The theme url and theme name from the WP installation are overwritten by the settings below.
         * Set higher than default priority for filters to ensure these are executed after the ones from the free version.
         */
        public function load_app()
        {
            add_filter("stylesheet", array(&$this, "app_theme"), 11);
            add_filter("template", array(&$this, "app_theme"), 11);

            add_filter('theme_root', array( &$this, 'app_theme_root' ), 11);
            add_filter('theme_root_uri', array( &$this, 'app_theme_root' ), 11);
        }


        /**
         * Return the theme name
         */
        public function app_theme()
        {
            return 'app2';
        }


		/**
         * Return path to the mobile themes folder
         */
        public function app_theme_root()
        {
            return PWA_PLUGIN_PATH . 'frontend/themes';
		}

        /**
         * Returns an array with all the application's frontend settings
         *
         * @return array
         */
        public function load_app_settings()
        {

            // load basic settings
            $frontend_options = array(
                'theme',
                'color_scheme',
                'theme_timestamp',
                'font_headlines',
                'font_subtitles',
                'font_paragraphs',
                'google_analytics_id',
                'display_website_link',
                'posts_per_page',
				'enable_facebook',
				'enable_twitter',
				'enable_google',
				'service_worker_installed'
            );

            $settings = array();

            foreach ($frontend_options as $option_name){
                $settings[$option_name] = WMobilePack_Options::get_setting($option_name);

                // backwards compatibility for font settings with versions lower than 2.2
                if (in_array($option_name, array('font_headlines', 'font_subtitles', 'font_paragraphs'))){
                    if (!is_numeric($settings[$option_name])){
                        $settings[$option_name] = 1;
                    }
                }
            }

            // check if custom theme exists and the file size is greater than zero
            if ($settings['theme_timestamp'] != ''){

                $custom_theme_path = PWA_FILES_UPLOADS_DIR.'theme-'.$settings['theme_timestamp'].'.css';

                if (!file_exists($custom_theme_path) || filesize($custom_theme_path) == 0){
                    $settings['theme_timestamp'] = '';
                }
            }

            // theme file doesn't exist, an preset css file will be used instead
            if ($settings['theme_timestamp'] == '' && $settings['font_headlines'] > 3){
                $settings['font_headlines'] = 1;
            }

            // load images
            foreach (array('icon', 'logo', 'cover') as $file_type){

                $file_path = WMobilePack_Options::get_setting($file_type);

                if ($file_path == '' || !file_exists(PWA_FILES_UPLOADS_DIR.$file_path))
                    $settings[$file_type] = '';
                else
                    $settings[$file_type] = WMP_FILES_UPLOADS_URL.$file_path;
            }

            // generate comments token
            if (!class_exists('WMobilePack_Tokens')) {
                require_once(PWA_PLUGIN_PATH . 'inc/class-wmp-tokens.php');
            }

            $settings['comments_token'] = WMobilePack_Tokens::get_token();

			if (!class_exists('WMobilePack_Themes_Config')) {
                require_once(PWA_PLUGIN_PATH . 'inc/class-wmp-themes-config.php');
            }

			$settings['manifest_color'] = WMobilePack_Themes_Config::get_manifest_background($settings['theme'], $settings['color_scheme']);

            return $settings;
        }

		/**
		* Get the language from the locale setting.
		*
		* @param string $locale (eg. 'en_EN')
		* @return string (eg. 'en')
		*/
		public function get_language($locale)
		{
			if (array_key_exists($locale, WMobilePack_Options::$supported_languages)){
				return WMobilePack_Options::$supported_languages[$locale];
			}

			return 'en';
		}


        /**
         * Check if a language file exists in the locales folder
         *
         * @param $locale
         * @return bool|string
         */
        public static function check_language_file($locale)
        {
            $language_file_path = PWA_PLUGIN_PATH.'frontend/locales/'.strip_tags($locale).'.json';

            if (!file_exists($language_file_path)) {
                $language_file_path = PWA_PLUGIN_PATH."frontend/locales/default.json";
            }

            if (file_exists($language_file_path)){
                return $language_file_path;
            }

            return false;
        }
    }
}
