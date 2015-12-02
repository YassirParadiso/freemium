<?php

namespace Services;

use Zend, Com;


class Module
{

    function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
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