<?php

namespace Console;

use Zend, Com;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface,
    Zend\ModuleManager\Feature\ConfigProviderInterface,
    Zend\ModuleManager\Feature\ConsoleUsageProviderInterface,
    Zend\Console\Adapter\AdapterInterface as Console;


class Module implements AutoloaderProviderInterface, ConfigProviderInterface, ConsoleUsageProviderInterface
{

    function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    
    public function getConsoleUsage(Console $console)
    {
      return array(
      
            // Describe available commands
            'create-databases' => 'Create databases',
            # 'remove-accounts' => 'Remove all deleted accounts older than 30 days',
            'run-cron' => 'Executes the cron of all instances',
            'disable-instances' => 'Disable overdue instances',
         
            // Describe expected parameters
        );
    }


    function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__ 
                ) 
            ) 
        );
    }
}