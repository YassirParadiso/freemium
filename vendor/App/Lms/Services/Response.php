<?php
namespace App\Lms\Services;

use Com, Zend;


class Response
{
    
    /**
     *
     * @var string
     */
    protected $type;

    /**
     *
     * @var string
     */
    protected $message;

    /**
     *
     * @var array
     */
    protected $params;

    /**
     *
     * @var code
     */
    protected $code;


    /**
     *
     * @return string
     */
    function getMessage()
    {
        return $this->message;
    }


    /**
     *
     * @param string $message
     * @return \App\Lms\Services\Response
     */
    function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }


    /**
     *
     * @return string
     */
    function getType()
    {
        return $this->type;
    }


    /**
     *
     * @param string $type
     * @return \App\Lms\Services\Response
     */
    function setType($type)
    {
        $this->type = $type;
        return $this;
    }


    /**
     *
     * @return array
     */
    function getParams()
    {
        return $this->params;
    }


    /**
     *
     * @param array $params
     * @return \App\Lms\Services\Response
     */
    function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }


    /**
     *
     * @return int
     */
    function getCode()
    {
        return $this->code;
    }


    /**
     *
     * @param int $code
     * @return \App\Lms\Services\Response
     */
    function setCode($code)
    {
        $this->code = (int)$code;
        return $this;
    }


    /**
     *
     * @param array $data
     * @return \App\Lms\Services\Response
     */
    function setFromArray(array $data)
    {
        if(isset($data['message']))
        {
            $this->setMessage($data['message']);
        }
        
        if(isset($data['type']))
        {
            $this->setType($data['type']);
        }
        
        if(isset($data['code']))
        {
            $this->setCode($data['code']);
        }
        
        if(isset($data['params']) && is_array($data['params']))
        {
            $this->setParams($data['params']);
        }
        
        return $this;
    }


    /**
     *
     * @param string $data
     * @return \App\Lms\Services\Response
     */
    function setFromJson($json)
    {
        $decoded = json_decode($json, true);
        
        if($decoded && is_array($decoded))
        {
            $this->setFromArray($decoded);
        }
        
        return $this;
    }


    /**
     *
     * @return boolean
     */
    function isError()
    {
        return ($this->getType() == 'error');
    }


    /**
     *
     * @param bool $exit
     */
    function printError($exit = true)
    {
        echo "<h1 style='color:red'>Error: {$this->getMessage()}</h1>";
        
        if($exit)
        {
            exit();
        }
    }


    /**
     */
    function debug()
    {
        echo '<pre>';
        print_r(array(
            'message' => $this->getMessage(),
            'type' => $this->getType(),
            'code' => $this->getCode(),
            'params' => $this->getParams() 
        ));
        echo '</pre>';
    } 
    
    
}