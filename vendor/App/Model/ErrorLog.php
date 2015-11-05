<?php

namespace App\Model;

use Com, Zend;


class ErrorLog extends Com\Model\AbstractModel
{


    function logError($type, $message, $file, $line)
    {
        $sl = $this->getServiceLocator();
        $request = $sl->get('request');
        
        $errorInfo = array(
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line 
        );
        
        //
        try
        {
            $ipAdress = new Zend\Http\PhpEnvironment\RemoteAddress();
            $ipAdress->setUseProxy(true);
            
            $post = null;
            if(method_exists('getPost', $request))
            {
                $post = $request->getPost();
            }
            
            $files = null;
            if(method_exists('getFiles', $request))
            {
                $files = $request->getFiles();
            }
            
            if($files)
            {
                $post = array_merge($post->toArray(), $files->toArray());
            }
            
            $method = 'GET';
            if(method_exists('getMethod', $request))
                $method = $request->getMethod();
            
            $cookie = null;
            if(method_exists('getCookie', $request))
                $cookie = $request->getCookie();
            
            $userAgent = '';
            if(method_exists('getServer', $request))
                $userAgent = $request->getServer('HTTP_USER_AGENT');
            
            $uri = '';
            if(method_exists('getUriString', $request))
                $uri = $request->getUriString();
            
            $sessionHanlder = $sl->get('session');
            
            $session = array(
                $sessionHanlder->getArrayCopy() 
            );
            
            if(isset($_SESSION))
            {
                $session[] = $_SESSION;
            }
            
            $data = array();
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['type'] = $type;
            $data['request_method'] = $method;
            $data['error_info'] = json_encode($errorInfo);
            $data['cookies'] = json_encode($cookie);
            $data['session'] = json_encode($session);
            $data['post'] = json_encode($post);
            $data['user_agent'] = $userAgent;
            $data['url'] = $uri;
            $data['ip_address'] = $ipAdress->getIpAddress();
            $data['fixed'] = 0;
            
            $dbErrorLog = $sl->get('App\Db\ErrorLog');
            $dbErrorLog->insert($data);
        }
        catch(\Exception $e)
        {
            print_r($e->getMessage());
            // TODO
            // save the log in the file system
        }
    }
}