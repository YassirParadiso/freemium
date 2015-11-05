<?php

namespace Com\Json\Service;

use Com, Zend;


class Consumer
{

    /**
     *
     * @var string
     */
    protected $server;

    /**
     *
     * @var string
     */
    protected $username;

    /**
     *
     * @var string
     */
    protected $password;


    /**
     *
     * @param string $server
     * @return \Com\Json\Service\Consumer
     */
    function setServer($server)
    {
        $this->server = $server;
        return $this;
    }


    /**
     *
     * @param string $username
     * @return \Com\Json\Service\Consumer
     */
    function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }


    /**
     *
     * @param string $password
     * @return \Com\Json\Service\Consumer
     */
    function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }


    /**
     * Send a JSON-RPC request to the service (for a specific method).
     *
     * @param string $serviceName Name of the service and method we want to call (sevice.methodName).
     * @param array $params Array of parameters for the method.
     * @return mixed Method call results.
     * @throws Exception\ErrorException When remote call fails.
     */
    function call($serviceName, array $params)
    {
        $exploded = explode('.', $serviceName);
        
        $endPoint = '';
        $method = '';
        if(2 == count($exploded))
        {
            $endPoint = $exploded[0];
            $method = $exploded[1];
        }
        
        $uri = new Zend\Uri\Uri($this->server);
        $uri->setQuery(array(
            'service' => $endPoint 
        ));
        
        $client = new \Zend\Json\Server\Client($uri->toString());
        $client->getHttpClient()->setAuth($this->username, $this->password);
        
        return $client->call($method, $params);
    }
}