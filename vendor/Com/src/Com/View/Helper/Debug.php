<?php

namespace Com\View\Helper;

use Zend;
use Com;
use Zend\View\Helper\AbstractHelper;
use Zend\Validator\Barcode\Issn;


class Debug extends AbstractHelper
{


    public function __invoke($val, $exit = false)
    {
        echo '<pre>';
        echo print_r($val, true);
        echo '</pre>';
        
        if($exit)
            exit();
    }
}