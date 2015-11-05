<?php

namespace App;

use Zend, Com;
class Module
{

   function getConfig()
   {
      return array();
   }
    
    function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__
                ) 
            ) 
        );
    }
}