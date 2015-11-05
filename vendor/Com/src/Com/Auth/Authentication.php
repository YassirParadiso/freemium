<?php

namespace Com\Auth;

use Zend;
use Com;
use Zend\Authentication\AuthenticationService;
use \Com\Auth\Adapter\Adapter as AuthAdapter;


class Authentication extends Zend\Authentication\AuthenticationService
{

    /**
     *
     * @var \Com\Db\AbstractDb
     *
     */
    protected $dbTable;

    /**
     *
     * @var AuthAdapter
     */
    protected $authAdapter = null;

    /**
     *
     * @var AuthenticationService
     */
    protected $authService = null;


    /**
     * Check if Identity is present
     *
     * @return bool
     */
    public function hasIdentity()
    {
        return $this->getAuthService()->hasIdentity();
    }


    /**
     * Return current Identity
     *
     * @return mixed null
     */
    public function getIdentity()
    {
        return $this->getAuthService()->getIdentity();
    }


    /**
     * Sets Auth Adapter
     *
     * @param \Com\Auth\Adapter\Adapter $authAdapter
     * @return Authentication
     */
    public function setAuthAdapter(AuthAdapter $authAdapter)
    {
        $this->authAdapter = $authAdapter;
        
        return $this;
    }


    /**
     * Returns Auth Adapter
     *
     * @return \Com\Auth\Adapter\Adapter
     */
    public function getAuthAdapter()
    {
        if($this->authAdapter === null)
        {
            $this->setAuthAdapter(new AuthAdapter());
        }
        
        return $this->authAdapter;
    }


    /**
     * Sets Auth Service
     *
     * @param \Zend\Authentication\AuthenticationService $authService
     * @return Authentication
     */
    public function setAuthService(AuthenticationService $authService)
    {
        $this->authService = $authService;
        
        return $this;
    }


    /**
     * Gets Auth Service
     *
     * @return \Zend\Authentication\AuthenticationService
     */
    public function getAuthService()
    {
        if($this->authService === null)
        {
            $authService = new AuthenticationService();
            
            // get the manager
            $sm = Com\Module::$MVC_EVENT->getApplication()->getServiceManager();
            $manager = $sm->get('Zend\Session\SessionManager');
            
            //
            $storage = new Com\Auth\Storage\Session(null, null, $manager);
            $authService->setStorage($storage);
            
            $this->setAuthService($authService);
        }
        
        return $this->authService;
    }


    /**
     *
     * @param array $values
     */
    function updateIdentity(array $values)
    {
        if($this->hasIdentity())
            $this->getAuthService()
                ->getStorage()
                ->write($values);
    }


    /**
     * Performs an authentication attempt
     *
     * @return \Zend\Authentication\Result
     */
    function authenticate()
    {
        if($this->hasIdentity())
        {
            $this->clearIdentity();
        }
        
        $authAdapter = $this->getAuthAdapter();
        
        return $this->getAuthService()->authenticate($authAdapter);
    }


    /**
     * Clears the identity from persistent storage
     */
    function clearIdentity()
    {
        $storage = $this->getAuthService()->getStorage();
        
        $storage->forgetMe();
        $storage->clear();
    }
}