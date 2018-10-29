<?php

namespace RocknRoot\StrayFw\Http;

use RocknRoot\StrayFw\Controllers;
use RocknRoot\StrayFw\Exception\NotARender;
use RocknRoot\StrayFw\Locale\Locale;
use RocknRoot\StrayFw\Render\RenderInterface;

/**
 * Bootstrapping class for HTTP requests.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Http
{
    /**
     * True if class has already been initialized.
     *
     * @static
     * @var bool
     */
    private static $isInit = false;

    /**
     * Current namespace prefix.
     *
     * @var string
     */
    protected static $namespace;

    /**
     * Current subdomain prefix.
     *
     * @var array
     */
    protected static $subdomain;

    /**
     * Current URI prefix.
     *
     * @var string
     */
    protected static $uri;

    /**
     * Registed routes.
     *
     * @var array[]
     */
    protected static $routes;

    /**
     * Current raw request.
     *
     * @var RawRequest|null
     */
    protected static $rawRequest;

    /**
     * Current request.
     *
     * @var Request
     */
    protected static $request;

    /**
     * Current render.
     *
     * @var Response
     */
    protected static $response;

    /**
     * Current controllers.
     *
     * @var object[]
     */
    protected static $controllers;

    /**
     * Initialize inner states according to current HTTP request.
     *
     * @static
     */
    public static function init()
    {
        if (self::$isInit === false) {
            self::$rawRequest = null;
            self::$routes = array();
            self::$isInit = true;
            if (defined('STRAY_IS_HTTP') === true && constant('STRAY_IS_HTTP') === true) {
                self::$rawRequest = new RawRequest();
                Session::init();
                Locale::init(self::$rawRequest);
            }
        }
    }

    /**
     * Launch the logic stuff. Http need to be initialized beforehand.
     *
     * @static
     * @throws NotARender if response->render is a non RenderInterface implementing object
     */
    public static function run()
    {
        if (self::$isInit === true) {
            self::$request = new Request(self::$rawRequest, self::$routes);
            self::$controllers = array();
            self::$response = new Response();
            try {
                ob_start();
                $before = self::$request->getBefore();
                foreach ($before as $b) {
                    $controller = Controllers::get($b['class']);
                    $action = $b['action'];
                    $controller->$action(self::$request, self::$response);
                    if (self::$request->hasEnded() === true) {
                        break;
                    }
                }
                if (self::$request->hasEnded() === false) {
                    $controller = Controllers::get(self::$request->getClass());
                    $action = self::$request->getAction();
                    $controller->$action(self::$request, self::$response);
                    if (self::$request->hasEnded() === false) {
                        $after = self::$request->getAfter();
                        foreach ($after as $a) {
                            $controller = Controllers::get($a['class']);
                            $action = $a['action'];
                            $controller->$action(self::$request, self::$response);
                        }
                    }
                }
                $render = self::$response->getRender();
                if (!($render instanceof RenderInterface)) {
                    throw new NotARender('response->render is a non RenderInterface implementing object');
                }
                echo $render->render(self::$response->data);
                ob_end_flush();
            } catch (\Exception $e) {
                ob_end_clean();
                throw $e;
            }
        }
    }

    /**
     * Set namespace, subdomain and url prefixes for incoming routes.
     *
     * @static
     * @param  string           $namespace namespace prefix
     * @param  string|array     $subdomain subdomain prefix
     * @param  string           $uri uri prefix
     */
    public static function prefix($namespace, $subdomain = null, $uri = null)
    {
        self::$namespace = $namespace;
        self::$subdomain = is_array($subdomain) ? $subdomain : [ $subdomain ];
        self::$uri = $uri;
    }

    /**
     * Add route to be considered.
     *
     * @static
     * @param  string           $method route HTTP method
     * @param  string           $path   route path
     * @param  string           $action class and method to call
     */
    public static function route($method, $path, $action)
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'route',
                'method' => $method,
                'path' => $path,
                'action' => $action,
                'namespace' => self::$namespace,
                'subdomain' => self::$subdomain,
                'uri' => self::$uri
            );
        }
    }

    /**
     * Add before hook to be considered.
     *
     * @static
     * @param  string           $method route HTTP method
     * @param  string           $path   route path
     * @param  string           $action class and method to call
     */
    public static function before($method, $path, $action)
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'before',
                'method' => $method,
                'path' => $path,
                'action' => $action,
                'namespace' => self::$namespace,
                'subdomain' => self::$subdomain,
                'uri' => self::$uri
            );
        }
    }

    /**
     * Add before hook to be considered.
     *
     * @static
     * @param  string           $method route HTTP method
     * @param  string           $path   route path
     * @param  string           $action class and method to call
     */
    public static function after($method, $path, $action)
    {
        if (self::$isInit === true) {
            self::$routes[] = array(
                'type' => 'after',
                'method' => $method,
                'path' => $path,
                'action' => $action,
                'namespace' => self::$namespace,
                'subdomain' => self::$subdomain,
                'uri' => self::$uri
            );
        }
    }

    /**
     * Get all registered routes.
     *
     * @return array[] all routes
     */
    public static function getRoutes()
    {
        return self::$routes;
    }

    /**
     * Get current raw request.
     *
     * @static
     * @return RawRequest|null
     */
    public static function getRawRequest()
    {
        return self::$rawRequest;
    }

    /**
     * Get current request.
     *
     * @static
     * @return Request
     */
    public static function getRequest()
    {
        return self::$request;
    }
}
