<?php

namespace Com\Controller;

use Zend\Mvc\Controller\AbstractActionController;


abstract class AbstractController extends AbstractActionController
{

    /**
     *
     * @var message
     */
    protected $viewVars = array();


    /**
     *
     * @param mixed $key
     * @param string $Value
     * @return \Com\Controller\AbstractController
     */
    function assign($key, $value = null)
    {
        if(is_object($key))
        {
            if(method_exists($key, 'toArray'))
            {
                $key = $key->toArray();
            }
            elseif(method_exists($key, 'getArrayCopy'))
            {
                $key = $key->getArrayCopy();
            }
        }
        
        if(is_array($key))
        {
            foreach($key as $a => $b)
            {
                $this->viewVars[$a] = $b;
            }
        }
        else
        {
            $this->viewVars[$key] = $value;
        }
        
        return $this;
    }


    /**
     *
     * @param \Com\Communicator $communicator
     * @return \Com\Controller\AbstractController
     */
    function saveCommunicator(\Com\Communicator $communicator)
    {
        $session = new \Zend\Session\Container();
        $session->communicator = $communicator;
        
        return $this;
    }


    /**
     *
     * @param string $key
     */
    function basicAuthentication($key = 'default')
    {
        $sl = $this->getServiceLocator();
        
        $basic = new \Com\Auth\Http\Basic($sl);
        
        $basic->authenticate($key);
    }


    /**
     *
     * @return \Com\Controller\AbstractController
     */
    function loadCommunicator()
    {
        $session = new \Zend\Session\Container();
        
        if(isset($session->communicator))
        {
            $this->setCommunicator($session->communicator);
            $session->offsetUnset('communicator');
        }
        
        return $this;
    }


    /**
     *
     * @param \Com\Communicator $communicator
     * @return \Com\Controller\AbstractController
     */
    function setCommunicator(\Com\Communicator $communicator)
    {
        $this->viewVars['communicator'] = $communicator;
        
        return $this;
    }


    /**
     *
     * @return \Com\Communicator
     */
    function getCommunicator()
    {
        if(! isset($this->viewVars['communicator']) || (! $this->viewVars['communicator'] instanceof \Com\Communicator))
        {
            $this->viewVars['communicator'] = new \Com\Communicator();
        }
        
        return $this->viewVars['communicator'];
    }


    /**
     *
     * @param mixed $value
     * @param mixed $label
     * @return \Com\Controller\AbstractController
     */
    function debug($value, $label)
    {
        if(APP_ENV == APP_DEVELOPMENT)
        {
            $this->getServiceLocator()
                ->get('debugger')
                ->debug($value, $label);
        }
        
        return $this;
    }


    /**
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    public function getRequest()
    {
        return parent::getRequest();
    }


    /**
     * Get response object
     *
     * @return \Zend\Http\PhpEnvironment\Response
     */
    public function getResponse()
    {
        return parent::getResponse();
    }


    /**
     *
     * @return int
     */
    function getUserId()
    {
        $userId = 0;
        
        $identity = $this->getUserIdentity();
        
        if($identity)
        {
            $userId = $identity['id'];
        }
        
        return $userId;
    }


    /**
     *
     * @return \Com\Auth\Authentication | null
     */
    function getUserIdentity()
    {
        $identity = null;
        $auth = new \Com\Auth\Authentication();
        
        if($auth->hasIdentity())
        {
            $identity = $auth->getIdentity();
        }
        
        return $identity;
    }


    /**
     * Translate a string using the given text domain and locale
     *
     * @param string $str
     * @param array $params
     * @param string $textDomain
     * @param string $locale
     * @return string
     */
    function _($str, $params = array(), $textDomain = 'default', $locale = null)
    {
        $sl = $this->getServiceLocator();
        $str = $sl->get('translator')->translate($str, $textDomain, $locale);
        
        if(is_array($params) && count($params))
        {
            array_unshift($params, $str);
            $str = call_user_func_array('sprintf', $params);
        }
        
        return $str;
    }
}