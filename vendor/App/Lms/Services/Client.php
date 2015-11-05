<?php
namespace App\Lms\Services;

use Com, Zend;


class Client
{
    /**
    * @var string
    */
    protected $servicesToken;
    
    /**
    * @var string
    */
    protected $serverUri;
    
    
    /**
    * @param string $serverUri
    *
    * @return \App\Lms\Services\Client
    */
    function setServerUri($serverUri)
    {
        $this->serverUri = $serverUri;
    }
    
    
    /**
    *
    * @return string
    */
    function getServerUri()
    {
        return $this->serverUri;
    }
    
    
    /**
    * @param string $servicesToken
    *
    * @return \App\Lms\Services\Client
    */
    function setServicesToken($servicesToken)
    {
        $this->servicesToken = $servicesToken;
    }
    
    
    /**
    *
    * @return string
    */
    function getServicesToken()
    {
        return !empty($this->servicesToken) ? $this->servicesToken : mt_rand(1000000, 9000000);
    }
    
    
    /**
    *
    * @return Zend\Http\Client
    */
    function getHttpClient()
    {
        $client = new Zend\Http\Client();
        
        $uri = $this->getServerUri();
        $client->setUri($uri);
        
        return $client;
    }
    
    
    /**
    *
    * @param string $action
    * @param array $data
    * @param string $method
    *
    * @return \App\Lms\Services\Response
    */
    function request($action, array $data = array(), $method = Zend\Http\Request::METHOD_POST)
    {
        $clientResponse = new \App\Lms\Services\Response();
        
        try
        {
            
            $client = $this->getHttpClient();
            
            $timestamp = time();
            $token = sha1($timestamp . $this->getServicesToken());
            
            $data['token'] = $token;
            $data['timestamp'] = $timestamp;
            $data['action'] = $action;
            
            if(Zend\Http\Request::METHOD_POST == $method)
            {
                $client->setParameterPost($data);
            }
            else
            {
                $method = Zend\Http\Request::METHOD_GET;
                $client->setParameterGet($data);
            }
            
            $client->setMethod($method);
            $response = $client->send();
            $code = $response->getStatusCode();
            
            if(200 == $code)
            {
                $body = $response->getBody();
                $clientResponse->setFromJson($body);
            }
            else
            {
                $data = array();
                $data['message'] = 'Unable to connect to the service';
                $data['type'] = 'error';
                $data['code'] = $code;
                $data['params'] = '';
                
                $clientResponse->setFromArray($data);
            }
        }
        catch(\Exception $e)
        {
            $data = array();
            $data['message'] = $e->getMessage();
            $data['type'] = 'error';
            $data['code'] = $e->getCode();
            $data['params'] = '';
            
            $clientResponse->setFromArray($data);
        }
        
        return $clientResponse;
    } 
    
    
}