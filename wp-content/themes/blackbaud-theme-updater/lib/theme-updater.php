<?php
/**
 * Name: Blackbaud Theme Updater
 * Author: Blackbaud, Inc.
 * Version: 1.0.0
 */
namespace blackbaud;
class ThemeUpdater {



    private $defaults = array(
        "endpoint" => "//api.blackbaud.com/services/wordpress/updater/themes",
        "force_update" => false
    );
    private $settings;
    private $endpoint;
    private $theme_data;
    private $latest_package;



    /**
     * Extend any options and initialize the theme updater.
     */
    public function __construct($options = array()) {

        # Only execute on the dashboard.
        if (! is_admin()) {
            return;
        }

        # Merge settings.
        $this->settings = array_merge($this->defaults, $options);

        # Assign each item of the settings array as object properties.
        $this->make_properties($this->settings);

        # Delete the themes transient every time the page loads.
        # Only force an update for testing.
        if ($this->force_update) {
            set_site_transient('update_themes', null);
        }

        # Construct the appropriate endpoint where the theme API lives.
        $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
        $this->endpoint = $protocol . $this->endpoint;

        # Get the current theme's data, to be used later on.
        $this->theme_data = wp_get_theme();

        # Hook into the theme transient filter so we can check for a possible update to our theme.
        add_filter('pre_set_site_transient_update_themes', array($this, 'check_for_update'));
    }



    /**
     * Checks the WordPress theme transient's theme version against the latest
     * version on the remote server. If there is, we'll update the transient
     * with the new information so that WordPress knows about it.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        if ($this->is_update_available()) {
            $theme_slug = $this->theme_data->get_template();
            $transient->response[$theme_slug] = array(
                'new_version' => $this->latest_package->version,
                'package' => $this->latest_package->package_url,
                'url' => $this->latest_package->description_url
            );
        }
        return $transient;
    }



    /**
     * Returns true or false if the currently installed theme is outdated.
     * It does this by comparing the versions of the current theme and its
     * respective theme on the remote server.
     */
    private function is_update_available() {
        if ($package = $this->fetch_latest_package()) {
            if (version_compare($package->version, $this->theme_data->Version, '>')) {
                return true;
            }
        }
        return false;
    }



    /**
     * Fetches an object representing the latest theme details on the remote server.
     */
    private function fetch_latest_package() {

        # Don't request the package again if it already exists.
        if (isset($this->latest_package)) {
            return $this->latest_package;
        }

        # Request the package details.
        $response = wp_remote_get($this->endpoint . '?theme=' . $this->theme_data->get_template());

        # Is the response in error?
        if (is_wp_error($response)) {
            return false;
        }

        # Retrieve the response's body content and convert it into a usable object.
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body);

        # Is the response empty or unusable?
        if ($this->is_response_error($result)) {
            return false;
        }

        # Set the package URL so WordPress knows where the zip file is located.
        $this->latest_package = $result;
        $this->latest_package->package_url = $this->endpoint . '/' . $this->latest_package->zip_file;

        # We're done! Return the package.
        return $this->latest_package;
    }



    /**
     * Returns true or false depending on some basic requirements for the
     * response object.
     */
    private function is_response_error($response) {
        if ($response === false) {
            return true;
        }
        if (! is_object($response)) {
            return true;
        }
        if (isset($response->error)) {
            return true;
        }
        return false;
    }



    /**
     * Sets the object's properties based on the options array key-value pairs.
     */
    private function make_properties($args = array()) {
        foreach ($args as $key => $val) {
            if (! isset($val)) {
                continue;
            }
            $this->$key = $val;
        }
    }
}
