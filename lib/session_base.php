<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

/**
 * Use for browser fingerprinting
 */
trait Hm_Session_Fingerprint {

    /**
     * Save a value in the session
     * @param string $name the name to save
     * @param string $value the value to save
     * @return void
     */
    abstract protected function set($name, $value);

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    abstract protected function destroy($request);

    /**
     * Return a session value, or a user settings value stored in the session
     * @param string $name session value name to return
     * @param string $default value to return if $name is not found
     * @return mixed the value if found, otherwise $default
     */
    abstract protected function get($name, $default=false);

    /**
     * Check HTTP header "fingerprint" against the session value
     * @param object $request request details
     * @return void
     */
    public function check_fingerprint($request) {
        $id = $this->build_fingerprint($request->server);
        $fingerprint = $this->get('fingerprint', null);
        if ($fingerprint === false) {
            $this->set_fingerprint($request);
            return;
        }
        if (!$fingerprint || $fingerprint !== $id) {
            $this->destroy($request);
            Hm_Debug::add('HTTP header fingerprint check failed');
        }
    }

    /**
     * Build HTTP header "fingerprint"
     * @param array $env server env values
     * @return string fingerprint value
     */
    public function build_fingerprint($env, $input='') {
        $id = $input;
        $id .= (array_key_exists('REMOTE_ADDR', $env)) ? $env['REMOTE_ADDR'] : '';
        $id .= (array_key_exists('HTTP_USER_AGENT', $env)) ? $env['HTTP_USER_AGENT'] : '';
        $id .= (array_key_exists('REQUEST_SCHEME', $env)) ? $env['REQUEST_SCHEME'] : '';
        $id .= (array_key_exists('HTTP_ACCEPT_LANGUAGE', $env)) ? $env['HTTP_ACCEPT_LANGUAGE'] : '';
        $id .= (array_key_exists('HTTP_ACCEPT_CHARSET', $env)) ? $env['HTTP_ACCEPT_CHARSET'] : '';
        $id .= (array_key_exists('HTTP_HOST', $env)) ? $env['HTTP_HOST'] : '';
        return hash('sha256', $id);
    }

    /**
     * Save a fingerprint in the session
     * @param object $request request details
     * @return void
     */
    protected function set_fingerprint($request) {
        $id = $this->build_fingerprint($request->server);
        $this->set('fingerprint', $id);
    }
}

/**
 * Base class for session management. All session interaction happens through
 * classes that extend this.
 * @abstract
 */
abstract class Hm_Session {

    use Hm_Session_Fingerprint;

    /* set to true if the session was just loaded on this request */
    public $loaded = false;

    /* set to true if the session is active */
    public $active = false;

    /* set to true if the user authentication is local (DB) */
    public $internal_users = false;

    /* key used to encrypt session data */
    public $enc_key = '';

    /* authentication class name */
    public $auth_class;

    /* site config object */
    public $site_config;

    /* session data */
    protected $data = array();

    /* session cookie name */
    protected $cname = 'hm_session';

    /* authentication object */
    protected $auth_mech;

    /* close early flag */
    protected $session_closed = false;

    /* session key */
    public $session_key = '';

    /**
     * check for an active session or an attempt to start one
     * @param object $request request object
     * @return bool
     */
    abstract protected function check($request);

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    abstract protected function start($request);

    /**
     * Call the configured authentication method to check user credentials
     * @param string $user username
     * @param string $pass password
     * @return bool true if the authentication was successful
     */
    abstract protected function auth($user, $pass);

    /**
     * Delete a value from the session
     * @param string $name name of value to delete
     * @return void
     */
    abstract protected function del($name);

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    abstract protected function end();

    /**
     * Setup initial data
     * @param object $config site config
     * @param string $auth_type authentication class
     */
    public function __construct($config, $auth_type='Hm_Auth_DB') {
        $this->site_config = $config;
        $this->auth_class = $auth_type;
        $this->internal_users = $auth_type::$internal_users;
    }

    /**
     * Lazy loader for the auth mech so modules can define their own
     * overrides
     * @return void
     */
    protected function load_auth_mech() {
        if (!is_object($this->auth_mech)) {
            $this->auth_mech = new $this->auth_class($this->site_config);
        }
    }

    /**
     * Dump current session contents
     * @return array
     */
    public function dump() {
        return $this->data;
    }

    /**
     * Method called on a new login
     * @return void
     */
    protected function just_started() {
        $this->set('login_time', time());
    }

    /**
     * Record session level changes not yet saved in persistant storage
     * @param string $value short description of the unsaved value
     * @return void
     */
    public function record_unsaved($value) {
        $this->data['changed_settings'][] = $value;
    }

    /**
     * Returns bool true if the session is active
     * @return bool
     */
    public function is_active() {
        return $this->active;
    }

    /**
     * Returns bool true if the user is an admin
     * @return bool
     */
    public function is_admin() {
        if (!$this->active) {
            return false;
        }
        $admins = array_filter(explode(',', $this->site_config->get('admin_users', '')));
        if (empty($admins)) {
            return false;
        }
        $user = $this->get('username', '');
        if (!strlen($user)) {
            return false;
        }
        return in_array($user, $admins, true);
    }

    /**
     * Encrypt session data
     * @param array $data session data to encrypt
     * @return string encrypted session data
     */
    public function ciphertext($data) {
        return Hm_Crypt::ciphertext(Hm_transform::stringify($data), $this->enc_key);
    }

    /**
     * Decrypt session data
     * @param string $data encrypted session data
     * @return false|array decrpted session data
     */
    public function plaintext($data) {
        return Hm_transform::unstringify(Hm_Crypt::plaintext($data, $this->enc_key));
    }

    /**
     * Set the session level encryption key
     * @param Hm_Request $request request details
     * @return void
     */
    protected function set_key($request) {
        $this->enc_key = Hm_Crypt::unique_id();
        $this->secure_cookie($request, 'hm_id', $this->enc_key);
    }

    /**
     * Fetch the current encryption key
     * @param object $request request details
     * @return void
     */
    public function get_key($request) {
        if (array_key_exists('hm_id', $request->cookie)) {
            $this->enc_key = $request->cookie['hm_id'];
        }
        else {
            Hm_Debug::add('Unable to get session encryption key');
        }
    }

    /**
     * @param Hm_Request $request request object
     * @return string
     */
    private function cookie_domain($request) {
        $domain = $this->site_config->get('cookie_domain', false);
        if (!$domain && array_key_exists('HTTP_HOST', $request->server)) {
            $domain = $request->server['HTTP_HOST'];
        }
        if ($domain == 'none') {
            $domain = '';
        }
        return $domain;
    }

    /**
     * Set a cookie, secure if possible
     * @param object $request request details
     * @param string $name cookie name
     * @param string $value cookie value
     * @param integer $lifetime cookie lifetime
     * @param string $path cookie path
     * @param string $domain cookie domain
     * @param boolean $html_only set html only cookie flag
     * @return boolean
     */
    public function secure_cookie($request, $name, $value, $lifetime=0, $path='', $domain='', $html_only=true) {
        if ($name == 'hm_reload_folders') {
            return Hm_Functions::setcookie($name, $value);
        }
        if (!$path && isset($request->path)) {
            $path = $request->path;
        }
        if (!$domain) {
            $domain = $this->cookie_domain($request);
        }
        return Hm_Functions::setcookie($name, $value, $lifetime, $path, $domain, $request->tls, $html_only);
    }
}

/**
 * Set up auth
 * @param object $config site configuration
 * @return string
 */
function setup_auth($config) {
    $auth_type = $config->get('auth_type', false);
    $auth_class = '';
    if ($auth_type == 'dynamic' && !in_array('dynamic_login', $config->get_modules(), true)) {
        Hm_Functions::cease('Invalid auth configuration');
    }
    if ($auth_type && in_array($auth_type, array('DB', 'LDAP', 'IMAP', 'POP3'), true)) {
        $auth_class = sprintf('Hm_Auth_%s', $auth_type);
    }
    elseif ($auth_type == 'custom') {
        $auth_class = 'Custom_Auth';
    }
    if (!$auth_class) {
        Hm_Functions::cease('Invalid auth configuration');
    }
    else {
        return $auth_class;
    }
    /* this special case is for unit testing */
    return 'Hm_Auth_None';
}

/**
 * Start up the selected session type
 * @param object $config site configuration
 * @return object|null
 */
function setup_session($config) {
    $session_type = $config->get('session_type', false);
    $auth_class = setup_auth($config);
    if ($session_type == 'DB') {
        $session_class = 'Hm_DB_Session';
    }
    elseif ($session_type == 'MEM') {
        $session_class = 'Hm_Memcached_Session';
    }
    elseif ($session_type == 'custom' && is_readable(APP_PATH.'modules/site/lib.php')) {
        $session_class = 'Custom_Session';
    }
    else {
        $session_class = 'Hm_PHP_Session';
    }
    if (Hm_Functions::class_exists($auth_class)) {
        Hm_Debug::add(sprintf('Using %s with %s', $session_class, $auth_class));
        return new $session_class($config, $auth_class);
    }
    else {
        Hm_Functions::cease('Invalid auth configuration');
    }
}
