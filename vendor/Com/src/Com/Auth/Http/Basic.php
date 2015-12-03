<?php

namespace Com\Auth\Http;

use Zend, Com;


class Basic
{

    /**
     *
     * @var Zend\ServiceManager\ServiceManager
     */
    protected $serviceManager;


    /**
     *
     * @param Zend\ServiceManager\ServiceManager $sl
     */
    function __construct(Zend\ServiceManager\ServiceManager $sl)
    {
        $this->serviceManager = $sl;
    }


    /**
     *
     * @param array $config
     * @return \Zend\Authentication\Adapter\Http
     */
    protected function _getAdapter(array $config)
    {
        $authConfig = $config['auth_adapter'];
        $authFile = CORE_DIRECTORY . DIRECTORY_SEPARATOR . $authConfig['basic_passwd_file'];

        $resolver = new Zend\Authentication\Adapter\Http\FileResolver();
        $resolver->setFile($authFile);
        
        $adapter = new Zend\Authentication\Adapter\Http($authConfig['config']);
        $adapter->setBasicResolver($resolver);
        
        return $adapter;
    }


    /**
     *
     * @param string $key
     * @return boolean
     */
    function authenticate($key)
    {
        $config = $this->_getConfig($key);
        
        $sl = $this->serviceManager;
        $application = $sl->get('application');
        $event = $application->getMvcEvent();
        
        $request = $event->getRequest();
        $response = $event->getResponse();
        
        // check the ip
        $authConfig = $config['auth_adapter'];
        if(isset($authConfig['allow_from']) && is_array($authConfig['allow_from']))
        {
            if(count($authConfig['allow_from']))
            {
                $remote = $sl->get('Zend\Http\PhpEnvironment\RemoteAddress');
                $allowedIp = false;
                
                foreach($authConfig['allow_from'] as $ip)
                {
                    if($ip == $remote->getIpAddress())
                    {
                        $allowedIp = true;
                        break;
                    }
                }
                
                if(! $allowedIp)
                {
                    $response->setStatusCode(Zend\Http\Response::STATUS_CODE_404);
                    $response->setContent('Page not found');
                    
                    // short-circuit to application end
                    // and stop event prop
                    $event->setResult($response);
                    $event->stopPropagation(true);
                    
                    $response->send();
                    exit();
                }
            }
        }
        
        // headers
        if(isset($authConfig['headers']) && is_array($authConfig['headers']))
        {
            $headers = $authConfig['headers'];
            foreach($headers as $key => $value)
            {
                $response->getHeaders()->addHeaderLine($key, $value);
            }
        }
        
        $authAdapter = $this->_getAdapter($config);
        $authAdapter->setRequest($request);
        $authAdapter->setResponse($response);
        $result = $authAdapter->authenticate();

        if($result->isValid())
        {
            $event->setResult($response);
        }
        else
        {
            $msg = implode('. ', $result->getMessages());
            $response->setStatusCode(Zend\Http\Response::STATUS_CODE_401);
            $response->setContent("<strong>Access denied</strong><br> $msg");
            
            $event->setResult($response);
            
            // short-circuit to application end
            // and stop event prop
            $event->stopPropagation(true);
            
            $response->send();
            exit();
        }
    }


    /**
     *
     * @param array $key
     * @throws \Exception
     */
    protected function _getConfig($key)
    {
        $sl = $this->serviceManager;
        
        $config = $sl->get('config');
        
        if(! isset($config['basic-authentication']) || ! isset($config['basic-authentication'][$key]))
            throw new \Exception('Authentication config key not found');
        
        return $config['basic-authentication'][$key];
    }
}