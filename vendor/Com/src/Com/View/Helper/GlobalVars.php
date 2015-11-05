<?php

namespace Com\View\Helper;

use Zend, Com;


class GlobalVars extends Zend\View\Helper\AbstractHelper
{


    function __invoke($key = null, $default = null)
    {
        $global = $this->getView()->Layout()->global_vars;
        
        if(empty($key))
        {
            return $global;
        }
        else
        {
            if(isset($global[$key]))
            {
                return $global[$key];
            }
            elseif(stripos($key, '.') !== false)
            {
                $exploded = explode('.', $key);
                $counter = 0;
                foreach($exploded as $key)
                {
                    if(is_array($global))
                    {
                        $global = $this->_digg($global, $key, $default);
                        $counter ++;
                    }
                }
                
                if($counter == count($exploded))
                {
                    return $global;
                }
            }
        }
        
        return $default;
    }


    /**
     *
     * @param array $arr
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function _digg(array $arr, $key, $default = null)
    {
        if(isset($arr[$key]))
            return $arr[$key];
        else
            return $default;
    }
}