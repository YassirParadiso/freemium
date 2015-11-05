<?php

namespace Com\Crypt;

use Com;
Use Zend;


class Token
{
    
    /**
     *
     * @var string
     */
    const PUBLIC_KEY = 'N86T7YOJLKJH';
    /**
     *
     * @var string
     */
    const PRIVATE_KEY = 'SDFUyrt@345&*yuio,!pkjhfyHASDOFIASHFISDSFAUSDSODUHIUSAH9876987';


    /**
     *
     * @return array
     */
    function getToken()
    {
        $time = time();
        
        $plain = self::PUBLIC_KEY . $time . self::PRIVATE_KEY;
        
        $p = new Com\Crypt\Password();
        
        $r = array();
        $r['token_key'] = self::PUBLIC_KEY;
        $r['token_code'] = $p->encode($plain);
        $r['token_time'] = $time;
        
        return $r;
    }


    /**
     *
     * @return string
     */
    function getHiddenFields()
    {
        $token = $this->getToken();
        
        $k = '<input type="hidden" name="token_key" value="' . $token['token_key'] . '">' . PHP_EOL;
        $c = '<input type="hidden" name="token_code" value="' . $token['token_code'] . '">' . PHP_EOL;
        $t = '<input type="hidden" name="token_time" value="' . $token['token_time'] . '">' . PHP_EOL;
        
        return $k . $c . $t;
    }


    /**
     *
     * @param string $tokenCode
     * @param string $tokenKey
     * @param int $tokenTime
     * @return boolean
     */
    function validate($tokenCode, $tokenKey, $tokenTime)
    {
        $flag = false;
        
        // check token timeout
        $currentTime = time();
        if($currentTime <= $tokenTime + (30 * 60))
        {
            $plain = $tokenKey . $tokenTime . self::PRIVATE_KEY;
            
            $p = new Com\Crypt\Password();
            $flag = $p->validate($plain, $tokenCode);
        }
        
        return $flag;
    }


    /**
     *
     * @return bool
     */
    function validateFromPost()
    {
        $tokenKey = isset($_POST['token_key']) ? $_POST['token_key'] : '';
        $tokenCode = isset($_POST['token_code']) ? $_POST['token_code'] : '';
        $tokenTime = isset($_POST['token_time']) ? $_POST['token_time'] : '';
        
        return $this->validate($tokenCode, $tokenKey, $tokenTime);
    }
}
