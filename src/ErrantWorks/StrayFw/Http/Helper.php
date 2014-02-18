<?php

namespace ErrantWorks\StrayFw\Http;

use ErrantWorks\StrayFw\Config;
use ErrantWorks\StrayFw\Exception\RouteNotFound;

/**
 * Useful functions for the framework users.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Helper
{
    /**
     * Extract domain from raw request.
     *
     * @static
     * @param  RawRequest $rawRequest base raw request
     * @return string     domain
     */
    public static function extractDomain(RawRequest $rawRequest)
    {
        $matches = null;
        preg_match('/[^\.\/]+\.[^\.\/]+$/', $rawRequest->getHost(), $matches);
        $domain = strtolower($matches[0]);
        $settings = Config::getSettings();
        if (empty($settings['domain_prefix']) === false) {
            $domain = $settings['domain_prefix'] . '.' . $domain;
        }

        return $domain;
    }

    /**
     * Get nice URL.
     *
     * @static
     * @param  string $url raw URL
     * @return string nice URL
     */
    public static function niceUrl($url)
    {
        $nice = null;
        if (($pos = stripos($url, '.')) !== false) {
            list($subDomain, $url) = explode('.', $url);
            $request = Http::getRequest();
            $nice = $request->getRawRequest()->getScheme() . '://';
            if ($subDomain != null) {
                $nice .= $subDomain . '.';
            }
            $nice .= self::extractDomain($request->getRawRequest());
        } else {
            $file = Http::getRequest()->getFile();
        }

        return $nice . '/' . ltrim(preg_replace('/\/+/', '/', $url), '/');
    }

    /**
     * Get nice URL for specified route.
     *
     * @static
     * @throws RouteNotFound if needed route can't be found
     * @param  Request       $request current request
     * @param  string        $route   route name
     * @param  array         $args    route arguments
     * @return string        nice URL
     */
    public static function niceUrlForRoute(Request $request, $route, $args = array())
    {
        $file = null;
        $url = null;
        if (($pos = stripos($route, '.')) !== false) {
        } else {
            $file = $request->getDir() . $request->getFile();
        }
        $routes = Config::get($file);
        if (isset($routes['routes'][$route]) === false) {
            throw new RouteNotFound('no route "' . $route . '" in "' . $file . '"');
        }
        $url .= $routes['routes'][$route]['path'];
        foreach ($args as $name => $value) {
            $url = preg_replace('/\(\?<' . $name . '>(.*?)\)/', $value, $url);
        }
        $url = preg_replace('/\(\?<(\w)+>(.*?)\)[?*]/', null, $url);
        $url = str_replace([ '(', ')', '?' ], null, $url);

        return self::niceUrl(rtrim($url, '/'));
    }
}
