<?php

namespace App\Cpanel;

use Com;

class ApiResponse 
{
    
    protected $result;
    
    
    /**
    *
    * @param mixed $result 
    */
    function __construct($result = null)
    {
       $this->setResult($result);
    }
    
    
    /**
    *
    * @return bool
    */
    function isError()
    {
       if(is_array($this->result))
       {
          return (isset($this->result['error']) || isset($this->result['event']['error']));
       }
       else
       {
          return false;
       }
    }
    
    
    /**
    *
    * @return string
    */
    function getError()
    {
       return isset($this->result['error']) ? $this->result['error'] : $this->result['event']['error'];
    }
    
    
    /**
    *
    * @param mixed $result 
    * @return  App\Cpanel\ApiResponse
    */
    function setResult($result)
    {
       $this->result = $result;
       return $this;
    }
    
    
    /**
    *
    * @return mixed
    */
    function getResult()
    {
       return $this->result;
    }
}