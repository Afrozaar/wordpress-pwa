<?php if (!class_exists('PtPwa_Options')) {

    /**
     * Overall Option Management class
     *
     * Instantiates all the options and offers a number of utility methods to work with the options
     *
     */
    class PtPwa_Options {

        /* ----------------------------------*/
        /* Properties						 */
        /* ----------------------------------*/

        public static $prefix = 'ptpwa_';
        public static $transient_prefix = 'pt_';

        public static $options = array(

            // content
            'inactive_categories'         => array(),
            'inactive_pages'              => array(),
            'ordered_categories'          => array(),
            'ordered_pages'               => array(), // this option is @deprecated starting from v2.2
            'categories_details'          => array(),

            // administrative
            'joined_waitlists'            => array(),
            'whats_new_updated'           => 0,
            'whats_new_last_updated'      => 0,
            'upgrade_notice_updated'      => 0, // if we should display the upgrade notice
            'upgrade_notice_last_updated' => 0, // upgrade timestamp
        );

        /**
         *
         * The get_setting method is used to read an option value (or options) from the database.
         *
         * If the $option param is an array, the method will return an array with the values,
         * otherwise it will return only the requested option value.
         *
         * @param $option - array / string
         * @return mixed
         */
        public static function get_setting($option) {
            // if the passed param is an array, return an array with all the settings
            if (is_array($option)) {

                $wmp_settings = array();

                foreach ($option as $option_name) {

                    if (get_option(self::$prefix . $option_name) === false) {
                        $wmp_settings[$option_name] = self::$options[$option_name];
                    } else {
                        $wmp_settings[$option_name] = get_option(self::$prefix . $option_name);
                    }
                }

                // return array
                return $wmp_settings;
            } elseif (is_string($option)) { // if option is a string, return the value of the option

                // check if the option is added in the db
                if (get_option(self::$prefix . $option) === false) {
                    $wmp_setting = self::$options[$option];
                } else {
                    $wmp_setting = get_option(self::$prefix . $option);
                }

                return $wmp_setting;
            }
        }

        /**
         *
         * The save_settings method is used to save an option value (or options) in the database.
         *
         * @param $option - array / string
         * @param $option_value - optional, mandatory only when $option is a string
         *
         * @return bool
         *
         */
        public static function save_settings($option, $option_value = '') {
            if (current_user_can('manage_options')) {

                if (is_array($option) && !empty($option)) {

                    // set option not saved variable
                    $option_not_saved = false;

                    foreach ($option as $option_name => $option_loop_value) {
                        if (array_key_exists($option_name, self::$options)) {
                            add_option(self::$prefix . $option_name, $option_loop_value);
                        } else {
                            $option_not_saved = true; // there is at least one option not in the default list
                        }
                    }

                    if ($option_not_saved) {
                        return false;
                    }

                } elseif (is_string($option) && $option_value != '') {
                    if (array_key_exists($option, self::$options)) {
                        return add_option(self::$prefix . $option, $option_value);
                    }
                }
            }
            return false;
        }

        /**
         *
         * The update_settings method is used to update the setting/settings of the plugin in options table in the database.
         *
         * @param $option - array / string
         * @param $option_value - optional, mandatory only when $option is a string
         *
         * @return bool
         *
         */
        public static function update_settings($option, $option_value = NULL) {

            if (current_user_can('manage_options')) {

                if (is_array($option) && !empty($option)) {

                    $option_not_updated = false;

                    foreach ($option as $option_name => $option_loop_value) {

                        // set option not saved variable
                        if (array_key_exists($option_name, self::$options)) {
                            update_option(self::$prefix . $option_name, $option_loop_value);
                        } else {
                            $option_not_updated = true; // there is at least one option not in the default list
                        }
                    }

                    if ($option_not_updated) {
                        return false;
                    }

                } elseif (is_string($option) && $option_value !== NULL) {

                    if (array_key_exists($option, self::$options)) {
                        return update_option(self::$prefix . $option, $option_value);
                    }

                }
            }

            return false;
        }

        /**
         *
         * The delete_settings method is used to delete the setting/settings of the plugin from the options table in the database.
         *
         * @param $option - array / string
         *
         * @return bool
         *
         */
        public static function delete_settings($option) {

            if (current_user_can('manage_options')) {

                if (is_array($option) && !empty($option)) {

                    foreach ($option as $option_name => $option_value) {

                        if (array_key_exists($option_name, self::$options)) {
                            delete_option(self::$prefix . $option_name);
                        }

                    }
                    return true;

                } elseif (is_string($option)) {

                    if (array_key_exists($option, self::$options)) {
                        return delete_option(self::$prefix . $option);
                    }
                }
            }

            return false;
        }

        /**
         *
         */
        public static function cleanPwaFiles() {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/service-worker.js');
            unlink($_SERVER['DOCUMENT_ROOT'] . '/theme.json');
            unlink($_SERVER['DOCUMENT_ROOT'] . '/manifest.json');
        }

        /**
         * Delete all transients and temporary data when the plugin is deactivated
         */
        public static function deactivate() {
        }

        /**
         *
         * Delete all options and transients when the plugin is uninstalled
         *
         */
        public static function uninstall() {

            //Remove single site files
            self::cleanPwaFiles();

            // delete plugin settings
            self::delete_settings(self::$options);

            // remove pages settings
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . self::$prefix . "page_%'");
        }
    }
}
