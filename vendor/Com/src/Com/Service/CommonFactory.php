<?php

namespace Com\Service;

use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class CommonFactory implements AbstractFactoryInterface
{


    public function canCreateServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {
        if(class_exists($requestedName))
        {
            return true;
        }
        
        return false;
    }


    public function createServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {
        $class = $requestedName;
        return new $class();
    }
}