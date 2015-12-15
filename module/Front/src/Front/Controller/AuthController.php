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
        $request = $this->getRequest();
        $params = new Zend\Stdlib\Parameters($this->params()->fromRoute());

        $sl = $this->getServiceLocator();
        $mInstance = $sl->get('App\Model\Freemium\Instance');
        
        $flag = $mInstance->canVerifyAccount($params);
        
        $com = $mInstance->getCommunicator();
        if($com->isSuccess())
        {
            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}/auth/create-instance/code/{$params->code}/email/{$params->email}";

            $ch = curl_init();
 
            curl_setopt($ch, CURLOPT_URL, $serverUrl);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
             
            $content = curl_exec($ch);
            curl_close($ch);
        }

        $this->setCommunicator($com);
        
        return $this->viewVars;
    }



    function createInstanceAction()
    {
        set_time_limit(0);

        $this->layout('layout/blank');

        $request = $this->getRequest();
        $params = new Zend\Stdlib\Parameters($this->params()->fromRoute());

        $sl = $this->getServiceLocator();
        $mInstance = $sl->get('App\Model\Freemium\Instance');
        
        $flag = $mInstance->verifyAccount($params);
        
        $com = $mInstance->getCommunicator();
        if($com->isSuccess())
        {
            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}/auth/thanks/code/{$params->code}/email/{$params->email}";
        }
        else
        {
            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}/auth/verify-account/code/{$params->code}/email/{$params->email}";
        }

        exit;
    }



    function thanksAction()
    {
        $sl = $this->getServiceLocator();
        $dbClient = $sl->get('App\Db\Client');

        $request = $this->getRequest();
        $params = new Zend\Stdlib\Parameters($this->params()->fromRoute());
        
        $client = $dbClient->findBy(function($where) use($params) {
            $where->equalTo('email', $params->email);
            $where->equalTo('deleted', 0);
            $where->equalTo('approved', 1);
            
        })->current();

        if($client)
        {
            $this->getCommunicator()->setSuccess($this->_('account_verified', array(
                "http://{$client->domain}/logo.php" 
            )));
        }

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
