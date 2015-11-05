<?php

namespace Routes;

use Zend, Com;

class Module
{

    function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
}