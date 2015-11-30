<?php

// we use the recomendations from the documentation
// http://framework.zend.com/manual/2.2/en/tutorifile:///home/yoterri/htdocs/www/paradiso/freemium/module/Console/Module.phpals/config.advanced.html

// Use the APP_ENV value to determine which modules to load
$modules = array(
   'Routes',
   'Com',
   'App',
   'Console',
   'Backend',
   'ZfcDatagrid',
   'Front',
   'Apps',
   'Services',
);

if (APP_ENV == APP_DEVELOPMENT)
{
    $modules[] = 'ZendDeveloperTools';
    $modules[] = 'StefanoDbProfiler';
}

return array(
    // This should be an array of module namespaces used in the application.
    'modules' => $modules,

    // These are various options for the listeners attached to the ModuleManager
    'module_listener_options' => array(
        // This should be an array of paths in which modules reside.
        // If a string key is provided, the listener will consider that a module
        // namespace, the value of that key the specific path to that module's
        // Module class.
        'module_paths' => array(
            './module',
            './vendor',
        ),

        // An array of paths from which to glob configuration files after
        // modules are loaded. These effectively override configuration
        // provided by modules themselves. Paths may use GLOB_BRACE notation.
        'config_glob_paths' => array(
            sprintf('config/autoload/{,*.}{global,%s,local}.php', APP_ENV),
            sprintf('module/Com/config/autoload/{,*.}{*}.php', APP_ENV),
        ),

        // Whether or not to enable a configuration cache.
        // If enabled, the merged configuration will be cached and used in
        // subsequent requests.
        'config_cache_enabled' => false, #(APP_ENV != APP_DEVELOPMENT),

        // The key used to create the configuration cache file name.
        'config_cache_key' => 'app_config',

        // Whether or not to enable a module class map cache.
        // If enabled, creates a module class map cache which will be used
        // by in future requests, to reduce the autoloading process.
        'module_map_cache_key' => 'module_map',

        // The key used to create the class map cache file name.
       'cache_dir' => 'data/config/',

        // The path in which to cache merged configuration.
        //'cache_dir' => $stringPath,

        // Whether or not to enable modules dependency checking.
        // Enabled by default, prevents usage of modules that depend on other modules
        // that weren't loaded.
        'check_dependencies' => (APP_ENV != APP_PRODUCTION),
    ),

    // Used to create an own service manager. May contain one or more child arrays.
    //'service_listener_options' => array(
    //     array(
    //         'service_manager' => $stringServiceManagerName,
    //         'config_key'      => $stringConfigKey,
    //         'interface'       => $stringOptionalInterface,
    //         'method'          => $stringRequiredMethodName,
    //     ),
    // )

   // Initial configuration with which to seed the ServiceManager.
   // Should be compatible with Zend\ServiceManager\Config.
   // 'service_manager' => array(),
);
