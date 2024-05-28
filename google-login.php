<?php

/**
 * Plugin Name: Google Login for WooCommerce
 * Description: Allows users to log in to WooCommerce using their Google account.
 * Version: 1.0
 * Author: Monir Saikat
 */

defined('WP_DEBUG') or define('WP_DEBUG', true);

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

class Google_Login_Plugin
{
    private $client;

    public function __construct()
    {
        add_action('init', array($this, 'start_session'));
        add_action('wp_logout', array($this, 'end_session'));
        add_action('woocommerce_login_form', array($this, 'add_google_login_button'));
        add_action('wp_loaded', array($this, 'handle_google_callback'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));

        $this->client = new Google_Client();

        $googleClientId     = get_option('google_login_options')['google_client_id'];
        $googleClientSecret = get_option('google_login_options')['google_client_secret'];

        $this->client->setClientId($googleClientId);
        $this->client->setClientSecret($googleClientSecret);

        $this->client->setRedirectUri(home_url('/google-callback'));
        $this->client->addScope('email');
        $this->client->addScope('profile');
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('google-login-custom-style', plugin_dir_url(__FILE__) . 'css/google-login-styles.css');
    }

    public function start_session()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function end_session()
    {
        session_destroy();
    }

    public function add_google_login_button()
    {
        $authUrl = $this->client->createAuthUrl();
        echo '<a href="' . esc_url($authUrl) . '" class="button google-login-button">Login with Google</a>';
    }

    public function handle_google_callback()
    {
        if (isset($_GET['code'])) {
            $token = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
            $this->client->setAccessToken($token);

            $oauth2 = new Google_Service_Oauth2($this->client);
            $userInfo = $oauth2->userinfo->get();

            $this->login_or_register_user($userInfo);

            wp_redirect(home_url());
            exit();
        }
    }

    private function login_or_register_user($userInfo)
    {
        $email = $userInfo->email;
        $user = get_user_by('email', $email);

        if ($user) {
            wp_set_auth_cookie($user->ID);
        } else {
            $user_id = wp_create_user($userInfo->email, wp_generate_password(), $userInfo->email);
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $userInfo->name,
                'first_name' => $userInfo->givenName,
                'last_name' => $userInfo->familyName,
            ));

            wp_set_auth_cookie($user_id);
        }
    }

    public function add_plugin_page()
    {
        add_options_page(
            'Social Login Settings',
            'Social Login',
            'manage_options',
            'google-login-setting-admin',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
        $this->options = get_option('google_login_options');
?>
        <div class="wrap">
            <h1>Google Login Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('google_login_option_group');
                do_settings_sections('google-login-setting-admin');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    public function page_init()
    {
        register_setting(
            'google_login_option_group',
            'google_login_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'setting_section_id',
            'Google OAuth Settings',
            array($this, 'print_section_info'),
            'google-login-setting-admin'
        );

        add_settings_field(
            'google_client_id',
            'Google Client ID',
            array($this, 'google_client_id_callback'),
            'google-login-setting-admin',
            'setting_section_id'
        );

        add_settings_field(
            'google_client_secret',
            'Google Client Secret',
            array($this, 'google_client_secret_callback'),
            'google-login-setting-admin',
            'setting_section_id'
        );
    }

    public function sanitize($input)
    {
        $new_input = array();
        if (isset($input['google_client_id'])) {
            $new_input['google_client_id'] = sanitize_text_field($input['google_client_id']);
        }

        if (isset($input['google_client_secret'])) {
            $new_input['google_client_secret'] = sanitize_text_field($input['google_client_secret']);
        }

        return $new_input;
    }

    public function print_section_info()
    {
        print 'Enter your Google OAuth settings below:';
    }

    public function google_client_id_callback()
    {
        printf(
            '<input type="text" id="google_client_id" name="google_login_options[google_client_id]" value="%s" />',
            isset($this->options['google_client_id']) ? esc_attr($this->options['google_client_id']) : ''
        );
    }

    public function google_client_secret_callback()
    {
        printf(
            '<input type="text" id="google_client_secret" name="google_login_options[google_client_secret]" value="%s" />',
            isset($this->options['google_client_secret']) ? esc_attr($this->options['google_client_secret']) : ''
        );
    }
}

new Google_Login_Plugin();
?>