<?php
/**
 * This is a CAS client authentication library for PHP 5.
 * 
 * <code>
 * <?php
 * $server = new SimpleCAS_Server_Version2('login.unl.edu', 443, 'cas');
 * $client = SimpleCAS::client($server);
 * $client->forceAuthentication();
 * 
 * if (isset($_GET['logout'])) {
 *     $client->logout();
 * }
 * 
 * if ($client->isAuthenticated()) {
 *     echo '<h1>Authentication Successful!</h1>';
 *     echo '<p>The user\'s login is '.$client->getUsername().'</p>';
 *     echo '<a href="?logout">Logout</a>';
 * }
 * </code>
 * 
 * PHP version 5
 * 
 * @category  Authentication 
 * @package   SimpleCAS
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2008 Regents of the University of Nebraska
 * @license   http://www1.unl.edu/wdn/wiki/Software_License BSD License
 * @link      http://svn.saltybeagle.com/listing.php?repname=saltybeagle&path=%2Fphp5cas%2F
 */
class SimpleCAS
{
    /**
     * Version of the CAS library.
     */
    const VERSION = '0.0.1';
    
    /**
     * Singleton CAS object
     *
     * @var CAS
     */
    static private $_instance;
    
    /**
     * Is user authenticated?
     *
     * @var bool
     */
    private $_authenticated = false;
    
    /**
     * Server running the CAS service.
     *
     * @var CAS_Server
     */
    protected $server;
    
    /**
     * User's login name if authenticated.
     *
     * @var string
     */
    protected $username;
    
    /**
     * Construct a CAS client object.
     *
     * @param CAS_Server $server Server to use for authentication.
     */
    private function __construct(SimpleCAS_Server $server)
    {
        $this->server = $server;
        
        if ($this->server instanceof SingleSignOut
            && isset($_POST)) {
            if ($ticket = $this->server->validateLogoutRequest($_POST)) {
                $this->logout($ticket);
            }
        }
        
        if (session_id() == '') {
            session_start();
            if (isset($_SESSION['ticket'])) {
                $this->_authenticated = true;
            }
        }
        
        if ($this->_authenticated == false
            && isset($_GET['ticket'])) {
            $this->validateTicket($_GET['ticket']);
        }
    }
    
    /**
     * Checks a ticket to see if it is valid.
     * 
     * If the CAS server verifies the ticket, a session is created and the user
     * is marked as authenticated.
     *
     * @param string $ticket Ticket from the CAS Server
     * 
     * @return bool
     */
    protected function validateTicket($ticket)
    {
        if ($uid = $this->server->validateTicket($ticket, self::getURL())) {
            $this->setAuthenticated($uid);
            $this->redirect(self::getURL());
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Marks the current session as authenticated.
     *
     * @param string $uid User name returned by the CAS server.
     * 
     * @return void
     */
    protected function setAuthenticated($uid)
    {
        $_SESSION['ticket']   = true;
        $_SESSION['uid']      = $uid;
        $this->_authenticated = true;
    }
    
    /**
     * Return the authenticated user's login name.
     *
     * @return string
     */
    public function getUsername()
    {
        return $_SESSION['uid'];
    }
    
    /**
     * Singleton interface, returns CAS object.
     * 
     * @param CAS_Server $server CAS Server object
     * 
     * @return CAS
     */
    static public function client(SimpleCAS_Server $server)
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self($server);
        }
        
        return self::$_instance;
    }
    
    /**
     * If client is not authenticated, this will redirecting to login and exit.
     * 
     * Otherwise, return the CAS object.
     *
     * @return CAS
     */
    function forceAuthentication()
    {
        if (!$this->isAuthenticated()) {
            self::redirect($this->server->getLoginURL(self::getURL()));
            exit();
        }
        return $this;
    }
    
    /**
     * Check if this user has been authenticated or not.
     * 
     * @return bool
     */
    function isAuthenticated()
    {
        return $this->_authenticated;
    }
    
    /**
     * Destroys session data for this client, redirects to the server logout
     * url.
     * 
     * @param string $url URL to provide the client on logout.
     * 
     * @return void
     */
    public function logout($url = '')
    {
        session_destroy();
        $this->redirect($this->server->getLogoutURL(self::getURL()));
        exit();
    }
    
    /**
     * Returns the current URL without CAS affecting parameters.
     * 
     * @return string url
     */
    static public function getURL()
    {
        if (isset($_SERVER['HTTPS'])
            && !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] == 'on') {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }
    
        $url = $protocol.'://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        
        $replacements = array('/\?logout/'        => '',
                              '/&ticket=[^&]*/'   => '',
                              '/\?ticket=[^&;]*/' => '?',
                              '/\?%26/'           => '?',
                              '/\?&/'             => '?',
                              '/\?$/'             => '');
        
        $url = preg_replace(array_keys($replacements),
                            array_values($replacements), $url);
        
        return $url;
    }
    
    /**
     * Send a header to redirect the client to another URL.
     *
     * @param string $url URL to redirect the client to.
     * 
     * @return void
     */
    public static function redirect($url)
    {
        header("Location: $url");
    }
    
    /**
     * Get the version of the CAS library
     *
     * @return string
     */
    static public function getVersion()
    {
        return self::VERSION;
    }
}
