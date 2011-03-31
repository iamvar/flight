<?php
/**
 * Flight: an extensible PHP micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * @version     0.1
 */
class Flight {
    /**
     * Stored variables.
     *
     * @var array
     */
    protected static $vars = array();

    /**
     * Registered classes.
     *
     * @var array
     */
    protected static $classes = array();

    /**
     * Mapped methods.
     *
     * @var array
     */
    protected static $methods = array();

    /**
     * Method filters.
     *
     * @var array
     */
    protected static $filters = array();

    // Don't allow object instantiation
    private function __construct() {}
    private function __destruct() {}
    private function __clone() {}

    /**
     * Handles calls to static methods.
     *
     * @param string $name Method name
     * @param array $args Method parameters
     */
    public static function __callStatic($name, $params) {
        // Check if call is mapped to a method
        if (isset(self::$methods[$name]) || method_exists(__CLASS__, '_'.$name)) {
            $method = self::$methods[$name] ?: array(__CLASS__, '_'.$name);

            // Run pre-filters
            if (!empty(self::$filters['before'][$name])) {
                self::filter(self::$filters['before'][$name], $params);
            }

            // Run requested method
            $output = self::execute($method, $params);

            // Run post-filters
            if (!empty(self::$filters['after'][$name])) {
                self::filter(self::$filters['after'][$name], $output);
            }

            return $output;
        }

        // Otherwise try to autoload class
        return self::load($name, (bool)$params[0]);
    }

    /**
     * Maps a callback to a framework method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public static function map($name, $callback) {
        if (method_exists(__CLASS__, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }
        self::$methods[$name] = $callback;
    }

    /**
     * Registers a class to a framework method.
     *
     * @param string $name Method name
     * @param string $class Class name
     * @param array $params Class initialization parameters
     * @param callback $callback Function to call after object instantiation
     */
    public static function register($name, $class, array $params = array(), $callback = null) {
        if (method_exists(__CLASS__, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }
        self::$classes[$name] = array($class, $params, $callback);
    }

    /**
     * Loads a registered class.
     *
     * @param string $class Class name
     * @param array $method List of methods to load
     */
    public static function load($name, $shared = true) {
        if (isset(self::$classes[$name])) {
            list($class, $params, $callback) = self::$classes[$name];

            $obj = ($shared) ?
                self::getInstance($class, $params) :
                self::getClass($class, $params);

            if ($callback) self::execute($callback, $ref = array($obj));

            return $obj;
        }

        return self::getInstance(ucfirst($name));
    }

    /**
     * Adds a pre-filter to a method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public static function before($name, $callback) {
        self::$filters['before'][$name][] = $callback;
    }

    /**
     * Adds a post-filter to a method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public static function after($name, $callback) {
        self::$filters['after'][$name][] = $callback;
    }

    /**
     * Executes a callback function.
     *
     * @param callback $callback Callback function
     * @param array $params Function parameters
     * @return mixed Function results
     */
    public static function execute($callback, &$params) {
        if (is_callable($callback)) {
            return is_array($callback) ?
                self::invokeMethod($callback, $params) :
                self::callFunction($callback, $params);
        }
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array $filters Chain of filters
     * @param array $params Method parameters
     * @param mixed $data Method output
     */
    public static function filter($filters, &$data) {
        foreach ($filters as $callback) {
            $continue = self::execute($callback, $params = array(&$data));
            if ($continue === false) break;
        }
    }

    /**
     * Calls a function.
     *
     * @param string $func Name of function to call
     * @param array $params Function parameters 
     */
    public static function callFunction($func, array &$params = array()) {
        switch (count($params)) {
            case 0:
                return $func();
            case 1:
                return $func($params[0]);
            case 2:
                return $func($params[0], $params[1]);
            case 3:
                return $func($params[0], $params[1], $params[2]);
            case 4:
                return $func($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $func($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array($func, $params);
        }
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func Class method as either an array or string
     * @param array $params Class initialization parameters
     */
    public static function invokeMethod($func, array &$params = array()) {
        list($class, $method) = $func;

        switch (count($params)) {
            case 0:
                return $class::$method();
            case 1:
                return $class::$method($params[0]);
            case 2:
                return $class::$method($params[0], $params[1]);
            case 3:
                return $class::$method($params[0], $params[1], $params[2]);
            case 4:
                return $class::$method($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $class::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array($func, $params);
        }
    }

    /**
     * Gets a single instance of a class.
     *
     * @param string $class Class name
     * @param array $params Class initialization parameters
     */
    public static function getInstance($class, array $params = array()) {
        static $instances = array();

        if (!isset($instances[$class])) {
            $instances[$class] = self::getClass($class, $params);
        }

        return $instances[$class];
    }

    /**
     * Gets a class object.
     *
     * @param string $class Class name
     * @param array $params Class initialization parameters
     */
    public static function getClass($class, array $params = array()) {
        switch (count($params)) {
            case 0:
                return new $class();
            case 1:
                return new $class($params[0]);
            case 2:
                return new $class($params[0], $params[1]);
            case 3:
                return new $class($params[0], $params[1], $params[2]);
            case 4:
                return new $class($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                $ref_class = new ReflectionClass($class);
                return $ref_class->newInstanceArgs($params);
        }
    }  

    /**
     * Gets a variable.
     *
     * @param string $key Key
     * @return mixed
     */
    public static function get($key) {
        return self::$vars[$key];
    }

    /**
     * Sets a variable.
     *
     * @param mixed $key Key
     * @param string $value Value
     */
    public static function set($key, $value = null) {
        // If key is an array, save each key value pair
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::$vars[$k] = $v;
            }
        }
        // If key is an object, save each property
        else if (is_object($key)) {
            foreach (get_object_vars($key) as $k => $v) {
                self::$vars[$k] = $v;
            }
        }
        else if (is_string($key)) {
            self::$vars[$key] = $value;
        }
    }

    /**
     * Checks if a variable exists.
     *
     * @param string $key Key
     * @return bool Variable status
     */
    public static function exists($key) {
        return isset(self::$vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public static function clear($key = null) {
        if (is_null($key)) {
            self::$vars = array();
        }
        else {
            unset(self::$vars[$key]);
        }
    }

    /**
     * Initializes the framework.
     */
    public static function init() {
        static $initialized = false;

        if (!$initialized) {
            // Register autoloader
            spl_autoload_register(array(__CLASS__, 'autoload'));

            // Handle errors internally
            set_error_handler(array(__CLASS__, 'handleError'));

            // Handle exceptions internally
            set_exception_handler(array(__CLASS__, 'handleException'));

            // Turn off notices
            error_reporting (E_ALL ^ E_NOTICE);

            // Fix magic quotes
            if (get_magic_quotes_gpc()) {
                $func = function ($value) use (&$func) {
                    return is_array($value) ? array_map($func, $value) : stripslashes($value);
                };
                $_GET = array_map($func, $_GET);
                $_POST = array_map($func, $_POST);
                $_COOKIE = array_map($func, $_COOKIE);
            }

            // Enable output buffering
            ob_start();

            $initialized = true;
        }
    }

    /**
     * Autoloads classes.
     *
     * @param string $class Class name
     */
    public static function autoload($class) {
        $file = str_replace('\\', '/', str_replace('_', '/', $class)).'.php';
        $base = (strpos($file, '/') === false) ? __DIR__ : (self::get('flight.lib.path') ?: '.');

        if (file_exists($base.'/'.$file)) {
            require $base.'/'.$file;
        }
        else {
            throw new Exception('Unable to load file: '.$base.'/'.$file);
        }
    }

    /**
     * Custom error handler.
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (in_array($errno, array(E_USER_ERROR, E_RECOVERABLE_ERROR))) {
            static::error(new ErrorException($errstr, 0, $errno, $errfile, $errline));
        }
    }

    /**
     * Custom exception handler.
     */
    public static function handleException(Exception $e) {
        try {
            static::error($e);
        }
        catch (Exception $ex) {
            exit(
                '<h1>500 Internal Server Error</h1>'.
                '<h3>'.$ex->getMessage().'</h3>'.
                '<pre>'.$ex->getTraceAsString().'</pre>'
            );
        }
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     */
    public static function _route($pattern, $callback) {
        self::router()->map($pattern, $callback);
    }

    /**
     * Start the framework.
     */
    public static function _start() {
        // Route the request
        $result = self::router()->route(self::request());

        if ($result !== false) {
            list($callback, $params) = $result;

            self::execute($callback, $params);
        }
        else {
            self::notFound();
        }

        // Disable caching for AJAX requests
        if (self::request()->isAjax) {
            self::response()->cache(false);
        }

        // Allow post-filters to run
        self::after('start', array(__CLASS__, 'stop'));
    }

    /**
     * Stops the framework and outputs the current response.
     */
    public static function _stop() {
        self::response()->
            write(ob_get_clean())->
            send();
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int $code HTTP status code
     * @param int $msg Response text
     */
    public static function _halt($code = 200, $text = '') {
        self::response(false)->
            status($code)->
            write($text)->
            cache(false)->
            send();
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param object $ex Exception
     */
    public static function _error(Exception $e) {
        self::response(false)->
            status(500)->
            write(
                '<h1>500 Internal Server Error</h1>'.
                '<h3>'.$e->getMessage().'</h3>'.
                '<pre>'.$e->getTraceAsString().'</pre>'
            )->
            send();
    }

    /**
     * Sends an HTTP 404 response when a URL is not found.
     */
    public static function _notFound() {
        self::response(false)->
            status(404)->
            write(
                '<h1>404 Not Found</h1>'.
                '<h3>The page you have requested could not be found.</h3>'.
                str_repeat(' ', 512)
            )->
            send();
    }

    /**
     * Redirects the current request to another URL.
     *
     * @param string $url URL
     */
    public static function _redirect($url, $code = 303) {
        self::response(false)->
            status($code)->
            header('Location', $url)->
            write($url)->
            send();
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     */
    public static function _render($file, $data = null) {
        self::view()->render($file, $data);
    }

    /**
     * Handles ETag HTTP caching.
     *
     * @param string $id ETag identifier
     * @param string $type ETag type
     */
    public static function _etag($id, $type = 'strong') {
        $id = (($type === 'weak') ? 'W/' : '').$id;

        self::response()->header('ETag', $id);
        
        if ($_SERVER['HTTP_IF_NONE_MATCH'] === $id) {
            self::halt(304);
        }
    }

    /**
     * Handles last modified HTTP caching.
     *
     * @param int $time Unix timestamp
     */
    public static function _lastModified($time) {
        self::response()->header('Last-Modified', date(DATE_RFC1123, $time));

        if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) {
            self::halt(304);
        }
    }
}

// Initialize framework on include
Flight::init();
?>