<?php

namespace Com\Auth\Storage;

use Zend;

class Session extends Zend\Authentication\Storage\Session
{

    /**
     * @param int $time default value 2 weeks
     */
    function setRememberMe($time = 1209600)
    {
         $this->session->getManager()->rememberMe($time);
    }

     
    function forgetMe()
    {
        $this->session->getManager()->forgetMe();
    } 
}