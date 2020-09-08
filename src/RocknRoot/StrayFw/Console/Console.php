<?php

namespace RocknRoot\StrayFw\Console;

use RocknRoot\StrayFw\Controllers;

/**
 * Bootstrapping class for CLI requests.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Console
{
    /**
     * True if class has already been initialized.
     *
     * @static
     */
    private static bool $isInit = false;

    /**
     * Current namespace prefix.
     */
    protected static ?string $namespace = null;

    /**
     * Registed routes.
     *
     * @var array[]
     */
    protected static array $routes = [];

    /**
     * Current request.
     */
    protected static ?\RocknRoot\StrayFw\Console\Request $request = null;

    /**
     * Current controllers.
     *
     * @var object[]
     */
    protected static array $controllers = [];

    /**
     * Initialize inner states according.
     *
     * @static
     */
    public static function init() : void
    {
        if (self::$isInit === false) {
            self::$isInit = true;
        }
    }

    /**
     * Launch the logic stuff. Console need to be initialized beforehand.
     *
     * @static
     */
    public static function run() : void
    {
        if (self::$isInit === true) {
            self::$request = new Request(self::$routes);
            self::$controllers = array();
            try {
                $before = self::$request->getBefore();
                foreach ($before as $b) {
                    $controller = Controllers::get($b['class']);
                    $action = $b['action'];
                    $controller->$action(self::$request);
                }
                if (self::$request->hasEnded() === false) {
                    $controller = Controllers::get(self::$request->getClass());
                    $action = self::$request->getAction();
                    $controller->$action(self::$request);
                    if (self::$request->hasEnded() === false) {
                        $after = self::$request->getAfter();
                        foreach ($after as $a) {
                            $controller = Controllers::get($a['class']);
                            $action = $a['action'];
                            $controller->$action(self::$request);
                        }
                    }
                }
            } catch (\Exception $e) {
                echo 'Exception: ' . $e->getMessage() . PHP_EOL;
                echo $e->getTraceAsString();
            }
        }
    }

    /**
     * Set namespace prefix for incoming routes.
     *
     * @static
     * @param string $namespace namespace prefix
     */
    public static function prefix(string $namespace) : void
    {
        self::$namespace = $namespace;
    }

    /**
     * Add route to be considered.
     *
     * @static
     * @param string $path   route path
     * @param string $usage  how to use it, for help screen
     * @param string $help   route description, for help screen
     * @param string $action class and method to call
     */
    public static function route(string $path, string $usage, string $help, string $action) : void
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'route',
                'path' => $path,
                'usage' => $usage,
                'help' => $help,
                'action' => $action,
                'namespace' => self::$namespace
            );
        }
    }

    /**
     * Add before hook to be considered.
     *
     * @static
     * @param string $path   route path
     * @param string $usage  how to use it, for help screen
     * @param string $help   route description, for help screen
     * @param string $action class and method to call
     */
    public static function before(string $path, string $usage, string $help, string $action) : void
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'before',
                'path' => $path,
                'usage' => $usage,
                'help' => $help,
                'action' => $action,
                'namespace' => self::$namespace
            );
        }
    }

    /**
     * Add after hook to be considered.
     *
     * @static
     * @param string $path   route path
     * @param string $usage  how to use it, for help screen
     * @param string $help   route description, for help screen
     * @param string $action class and method to call
     */
    public static function after(string $path, string $usage, string $help, string $action) : void
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'after',
                'path' => $path,
                'usage' => $usage,
                'help' => $help,
                'action' => $action,
                'namespace' => self::$namespace
            );
        }
    }

    /**
     * Get all registered routes.
     *
     * @return array[] all routes
     */
    public static function getRoutes() : array
    {
        return self::$routes;
    }
}
