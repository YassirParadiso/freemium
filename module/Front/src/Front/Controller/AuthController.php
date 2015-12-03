<?php

namespace Front\Controller;

use Com, Zend;


class AuthController extends Com\Controller\AbstractController
{


    function loginAction()
    {
        $this->layout('layout/backend');
        
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        
        $auth = $sl->get('Com\Auth\Authentication');
        
        if($auth->hasIdentity())
        {
            // lets redirect to auth-init
            return $this->redirect()->toRoute('auth', array('action' => 'init'));
        }
        else
        {
            
            if($request->isPost())
            {
                $sl = $this->getServiceLocator();
                
                $params = $request->getPost();
                
                $mUser = $sl->get('App\Model\User');
                $flag = $mUser->login($params);
                
                if($flag)
                {
                    return $this->redirect()->toRoute('auth', array('action' => 'init'));
                }
                else
                {
                    $this->assign($params);
                    $com = $mUser->getCommunicator();
                    $this->setCommunicator($com);
                }
            }
        }
        
        return $this->viewVars;
    }


    function logoutAction()
    {
        $auth = new Com\Auth\Authentication();
        $auth->clearIdentity();
        
        return $this->redirect()->toRoute('auth', array('action' => 'login'));
    }


    function verifyAccountAction()
    {
        set_time_limit(0);
        $params = new Zend\Stdlib\Parameters($this->params()->fromRoute());
        
        $sl = $this->getServiceLocator();
        $mInstance = $sl->get('App\Model\Freemium\Instance');
        
        $flag = $mInstance->verifyAccount($params);
        
        $com = $mInstance->getCommunicator();
        $this->setCommunicator($com);
        
        return $this->viewVars;
    }
    
    
    /**
     * @see https://app.asana.com/0/14725105905099/15626302371149
     */
    function initAction()
    {
        $sl = $this->getServiceLocator();
        
        $session = $sl->get('session');
        $back = $session->back;
        
        $identity = $this->getUserIdentity();

        if ($identity)
        {
            if ($back)
            {
                $session->back = null;
                return $this->redirect()->toUrl($back);
            }
            else
            {
                return $this->redirect()->toRoute('backend');
            }
        }
        else
        {
            return $this->redirect()->toRoute('auth', array('action' => 'login'));
        }
    }

}
