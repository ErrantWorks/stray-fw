<?php

namespace RocknRoot\StrayFw\Render;

use RocknRoot\StrayFw\Config;
use RocknRoot\StrayFw\Exception\BadUse;
use RocknRoot\StrayFw\Exception\InvalidDirectory;
use Twig\Environment;
use Twig\Extension;

/**
 * Wrapper and configuration class for Twig.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Twig
{
    /**
     * Existing Twig environments.
     *
     * @static
     * @var \Twig\Environment[]
     */
    protected static $environments = array();

    /**
     * Registered extensions.
     *
     * @static
     * @var \Twig\Extension\ExtensionInterface[]
     */
    protected static $extensions = array();

    /**
     * Registered functions.
     *
     * @static
     * @var callable[]
     */
    protected static $functions = array();

    /**
     * Get environment for specified templates directory.
     *
     * @static
     * @throws InvalidDirectory if directory can't be identified
     * @throws BadUse           if tmp path hasn't been defined
     * @throws BadUse           if tmp directory isn't writable
     * @param  string           $dir template directory
     * @return \Twig\Environment corresponding environment
     */
    public static function getEnv(string $dir) : \Twig\Environment
    {
        if (isset(self::$environments[$dir]) === false) {
            $dir = rtrim($dir, '/') . '/';
            if (is_dir($dir) === false) {
                throw new InvalidDirectory('invalid templates directory "' . $dir . '"');
            }
            $settings = Config::getSettings();
            if (empty($settings['tmp']) === true) {
                throw new BadUse('tmp directory hasn\'t been defined in installation settings');
            }
            $tmp = rtrim($settings['tmp'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if ($tmp[0] != DIRECTORY_SEPARATOR) {
                $tmp = constant('STRAY_PATH_ROOT') . $tmp;
            }
            if (is_dir($tmp . 'twig_compil/') == false) {
                if (mkdir($tmp . 'twig_compil') === false) {
                    throw new BadUse('tmp directory doesn\'t seem to be writable');
                }
            }
            $loader = new \Twig\Loader\FilesystemLoader($dir);
            $env = new \Twig\Environment($loader, array(
                'cache' => $tmp . 'twig_compil',
                'debug' => (constant('STRAY_ENV') === 'development')
            ));
            self::$environments[$dir] = $env;
            if (constant('STRAY_ENV') === 'development') {
                self::$environments[$dir]->addExtension(new \Twig\Extension\DebugExtension());
            }
            self::$environments[$dir]->addFunction(new \Twig_Function('tr', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'tr']));
            self::$environments[$dir]->addFunction(new \Twig_Function('langFull', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'langFull']));
            self::$environments[$dir]->addFunction(new \Twig_Function('langPrimary', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'langPrimary']));
            self::$environments[$dir]->addFunction(new \Twig_Function('url', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'url']));
            self::$environments[$dir]->addFunction(new \Twig_Function('localizedDate', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'localizedDate']));
            self::$environments[$dir]->addFunction(new \Twig_Function('session', ['\\RocknRoot\\StrayFw\\Render\\TwigHelper', 'session']));
            foreach (self::$extensions as $ext) {
                self::$environments[$dir]->addExtension($ext);
            }
            foreach (self::$functions as $label => $callable) {
                self::$environments[$dir]->addFunction(new \Twig\TwigFunction($label, $callable));
            }
        }

        return self::$environments[$dir];
    }

    /**
     * Add an extension to Twig environments.
     *
     * @static
     * @param \Twig\Extension\ExtensionInterface $extension instance
     */
    public static function addExtension(\Twig\Extension\ExtensionInterface $extension)
    {
        self::$extensions[] = $extension;
        foreach (self::$environments as $env) {
            $env->addExtension($extension);
        }
    }

    /**
     * Add a function to Twig environments.
     *
     * @static
     * @param string $label       function name in Twig templates
     * @param callable $function function
    */
    public static function addFunction(string $label, callable $function)
    {
        if (isset(self::$functions[$label]) === false) {
            self::$functions[$label] = $function;
            foreach (self::$environments as $env) {
                $env->addFunction(new \Twig\TwigFunction($label, $function));
            }
        }
    }
}
