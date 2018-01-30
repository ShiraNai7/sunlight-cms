<?php

namespace Sunlight\Twig;

use Kuria\Debug\Dumper;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Url;

class TwigBridge
{
    /** @var \Twig_Environment|null */
    protected static $env;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * @return \Twig_Environment
     */
    public static function getEnvironment()
    {
        if (static::$env === null) {
            static::$env = static::createEnvironment();
        }

        return static::$env;
    }

    protected static function createEnvironment()
    {
        if (!Core::isReady()) {
            throw new \LogicException('Cannot use Twig bridge before full system initialization');
        }

        $loader = new TemplateLoader(array(), _root);

        $loader->setPaths(array(''));
        $loader->setPaths(array('plugins/extend'), 'extend');
        $loader->setPaths(array('plugins/languages'), 'languages');
        $loader->setPaths(array('plugins/templates'), 'templates');

        $env = new \Twig_Environment(
            $loader,
            array(
                'debug' => _debug,
                'strict_variables' => _debug,
                'cache' => _root . 'system/cache/twig',
            )
        );

        static::addGlobals($env);
        static::addFunctions($env);

        Extend::call('twig.init', array('env' => $env, 'loader' => $loader));

        return $env;
    }

    protected static function addGlobals(\Twig_Environment $env)
    {
        $env->addGlobal('sl', array(
            'debug' => _debug,
            'root' => _root,
            'url' => Url::current(),
            'logged_in' => _logged_in,
            'user' => Core::$userData,
            'group' => Core::$groupData,
        ));
    }

    protected static function addFunctions(\Twig_Environment $env)
    {
        // link functions
        $env->addFunction(new \Twig_SimpleFunction('link', '_link'));
        $env->addFunction(new \Twig_SimpleFunction('linkFile', '_linkFile'));
        $env->addFunction(new \Twig_SimpleFunction('linkPage', '_linkPage'));
        $env->addFunction(new \Twig_SimpleFunction('linkRoot', '_linkRoot'));
        $env->addFunction(new \Twig_SimpleFunction('linkArticle', '_linkArticle'));
        $env->addFunction(new \Twig_SimpleFunction('linkTopic', '_linkTopic'));
        $env->addFunction(new \Twig_SimpleFunction('linkModule', function ($module, $params = null, $absolute = false) {
            return _linkModule($module, $params, false, $absolute);
        }));

        // localization
        $env->addFunction(new \Twig_SimpleFunction('lang', '_lang'));

        // hcm
        $env->addFunction(new \Twig_SimpleFunction('hcm', '_runHCM', array('is_variadic' => true, 'is_safe' => array('html'))));

        // extend
        $env->addFunction(new \Twig_SimpleFunction('extend_call', array('Sunlight\\Extend', 'call')));
        $env->addFunction(new \Twig_SimpleFunction('extend_buffer', array('Sunlight\\Extend', 'buffer')));
        $env->addFunction(new \Twig_SimpleFunction('extend_fetch', array('Sunlight\\Extend', 'fetch')));

        // debugging
        $env->addFunction(new \Twig_SimpleFunction('dump', array(__CLASS__, 'dump'), array('needs_context' => true)));
    }

    /**
     * @internal
     */
    public static function dump($context)
    {
        if (func_num_args() > 1) {
            return call_user_func_array(
                array('Kuria\\Debug\\Dumper', 'dump'),
                array_slice(func_get_args(), 1)
            );
        }

        return Dumper::dump($context);
    }
}
