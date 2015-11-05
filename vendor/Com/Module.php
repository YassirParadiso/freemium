<?php

namespace Com;

use Zend;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Com;


class Module
{

    /**
     *
     * @var \Zend\Mvc\MvcEvent
     */
    static $MVC_EVENT;


    function onBootstrap(MvcEvent $event)
    {
        self::$MVC_EVENT = $event;
        
        set_error_handler(array(
            '\Com\Module',
            'handlePhpErrors' 
        ));
        
        set_exception_handler(array(
            '\Com\Module',
            'handlePhpExceptions' 
        ));
        
        $eventManager = $event->getApplication()->getEventManager();
        $serviceManager = $event->getApplication()->getServiceManager();
        
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        
        $config = $serviceManager->get('config');
        
        $this->_setupDebug($event);
        $this->_setupPhpSettings($config);
        $this->_setupTablePrefix($serviceManager, $event, $eventManager, $config);
        $this->_setupSession($serviceManager);
        $this->_setupTranslator($serviceManager);
        $this->_setupAuthorisation($eventManager);
        $this->_setupGlobalVariables($event);
        $this->_setupLayout($event);
        $this->_newDomainTld($event);
    }


    protected function _setupDebug()
    {
        new \Kint();
        \kint::enabled((APP_ENV == APP_DEVELOPMENT));
    }


    protected function _setupPhpSettings($config)
    {
        // php configration settings
        if(isset($config['phpSettings']))
        {
            foreach($config['phpSettings'] as $key => $value)
            {
                ini_set($key, $value);
            }
        }
    }


    protected function _setupTranslator($serviceManager)
    {
        // cookie expiration = now + 1 year
        $expiration = time() + 365 * 60 * 60 * 24;
        $request = $serviceManager->get('request');
        $response = $serviceManager->get('response');
        
        if(method_exists($request, 'getCookie'))
        {
            $cookie = $request->getCookie();
            
            $lang = null;
            $changeLanguage = false;
            
            // first we have to check if the user is trying to change language
            if(isset($_GET['lang']))
            {
                $lang = $_GET['lang'];
                $changeLanguage = true;
            }
            else
            {
                // if not trying to change the language, then check if the language was already set in the session variable
                // if not session was set then we try to get the language from the browser
                if(! isset($cookie->lang))
                {
                    // get language from browser preferences
                    $request = $serviceManager->get('request');
                    $headers = $request->getHeaders();
                    if($headers->has('Accept-Language'))
                    {
                        $languages = $headers->get('Accept-Language');
                        $locales = $languages->getPrioritized();
                        if($locales)
                        {
                            $first = array_shift($locales);
                            $lang = $first->getPrimaryTag();
                            
                            $changeLanguage = true;
                        }
                    }
                }
                else
                {
                    // language already set in the session
                    $lang = $cookie->lang;
                }
            }
            
            // change language
            if($changeLanguage)
            {
                $setCookie = new Zend\Http\Header\SetCookie('lang', $lang, $expiration, '/');
                
                $headers = $response->getHeaders();
                $headers->addHeader($setCookie);
            }
        }
        else
        {
            $lang = 'en';
        }
        
        $translator = $serviceManager->get('translator');
        $translator->setLocale($lang)->setFallbackLocale('en');
        
        \Zend\Validator\AbstractValidator::setDefaultTranslator($translator);
    }


    protected function _setupTablePrefix($serviceManager, $event, $eventManager, $config)
    {
        $eventManager->getSharedManager()->attach('Com\Db\AbstractDb', 'prefixing', function (\Zend\EventManager\Event $event) use($config)
        {
            if(isset($config['db_prefix']))
            {
                $event->setParam('prefix', $config['db_prefix']);
            }
        });
    }


    protected function _setupSession(Zend\ServiceManager\ServiceManager $serviceManager)
    {
        $sessionManager = $serviceManager->get('Zend\Session\SessionManager');
        
        $sessionName = $sessionManager->getName();
        $sessionId = $sessionManager->getId();
        
        try
        {
            if(isset($_POST[$sessionName]) && $_POST[$sessionName] != $sessionId)
            {
                $token = new Com\Crypt\Token();
                if($token->validateFromPost())
                {
                    $sessionManager->setId($_POST[$sessionName]);
                    $session = $serviceManager->get('session');
                }
            }
        }
        catch(\Exception $e)
        {
            ;
        }
        
        Zend\Session\Container::setDefaultManager($sessionManager);
        $sessionManager->start();
    }


    protected function _setupAuthorisation(Zend\EventManager\EventManager $eventManager)
    {
        // set higher priority to this event
        $eventManager->attach('dispatch', function (Zend\Mvc\MvcEvent $event)
        {
            $serviceManager = $event->getApplication()
                ->getServiceManager();
            
            $auth = $serviceManager->get('Com\Auth\Authentication');
            $hasIdentity = $auth->hasIdentity();
            
            $controller = $event->getRouteMatch()
                ->getParam('controller');
            $controllerClass = $controller . 'Controller';
            
            if(is_subclass_of($controllerClass, '\Com\Controller\UserController'))
            {
                if($hasIdentity)
                {
                    // check authorisation
                }
                else
                {
                    $request = $event->getRequest();
                    $response = $event->getResponse();
                    
                    $sl = $event->getApplication()
                        ->getServiceManager();
                    
                    $session = $sl->get('session');
                    $session->back = $request->getRequestUri();
                    
                    $options['name'] = 'auth';
                    $params = array(
                        'action' => 'login' 
                    );
                    
                    $url = $event->getRouter()
                        ->assemble($params, $options);
                    
                    $headers = $response->getHeaders()
                        ->addHeaderLine('Location', $url);
                    
                    $response->setStatusCode(302);
                    $response->sendHeaders();
                }
            }
        }, 1000);
    }


    protected function _setupGlobalVariables(Zend\Mvc\MvcEvent $event)
    {
        $eventManager = $event->getApplication()->getEventManager();
        
        $eventManager->getSharedManager()->attach('Zend\Mvc\Controller\AbstractController', 'dispatch', function (Zend\Mvc\MvcEvent $event)
        {
            $auth = $event->getApplication()
                ->getServiceManager()
                ->get('Com\Auth\Authentication');
            $hasIdentity = $auth->hasIdentity();
            
            $globals = array();
            
            if($hasIdentity)
            {
                $globals['has_identity'] = true;
                $globals['identity'] = $auth->getIdentity();
            }
            else
            {
                $globals['has_identity'] = false;
            }
            
            $controller = $event->getTarget();
            $controller->layout()->global_vars = $globals;
        });
    }


    protected function _setupLayout(Zend\Mvc\MvcEvent $event)
    {
        $eventManager = $event->getApplication()->getEventManager();
        
        // set lowest priority to this event
        // this event should be executed after the Authorisation
        $eventManager->getSharedManager()->attach('Zend\Mvc\Controller\AbstractController', 'dispatch', function (Zend\Mvc\MvcEvent $event)
        {
            $controller = $event->getTarget();
            $controllerClass = get_class($controller);
            
            $moduleNamespace = substr($controllerClass, 0, strpos($controllerClass, '\\'));
            $layoutKey = 'layout/' . strtolower($moduleNamespace);
            
            $config = $event->getApplication()
                ->getServiceManager()
                ->get('config');
            
            if(isset($config['view_manager']['template_map'][$layoutKey]))
            {
                $controller->layout($layoutKey);
            }
            else
            {
                $controller->layout('layout/layout');
            }
        }, 50);
    }


    protected function _newDomainTld(Zend\Mvc\MvcEvent $event)
    {
        $request = $event->getRequest();
        
        // small code to redirect requests to the new tld
        if(method_exists($request, 'getUri'))
        {
            $uri = $request->getUri();
            $host = $uri->getHost();
            
            $oldTld = '.com';
            if($oldTld == substr($host, - 4))
            {
                $scheme = $uri->getScheme();
                $host = str_replace($oldTld, '.net', $host);
                $path = $uri->getPath();
                
                $query = $uri->getQuery();
                if(! empty($query))
                {
                    $query = "?$query";
                }
                
                $url = "{$scheme}://{$host}{$path}{$query}";
                
                $response = $event->getResponse();
                
                $headers = $response->getHeaders()->addHeaderLine('Location', $url);
                $response->setStatusCode(302);
                $response->sendHeaders();
            }
        }
    }


    function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }


    function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__ 
                ) 
            ),
            'Zend\Loader\ClassMapAutoloader' => array(
                array(
                    'Kint' => 'vendor/3rdParty/Kint/Kint.class.php' 
                ) 
            ) 
        );
    }


    static function handlePhpErrors($type, $message, $file, $line)
    {
        if(APP_ENV == APP_DEVELOPMENT)
        {
            throw new \Exception("Error: $message \nIn file $file \nAt line $line");
        }
        else
        {
            $event = self::$MVC_EVENT;
            
            $serviceManager = $event->getApplication()->getServiceManager();
            
            $mErrorLog = $serviceManager->get('App\Model\ErrorLog');
            $mErrorLog->logError($type, $message, $file, $line);
        }
    }


    static function handlePhpExceptions(\Exception $ex)
    {
        if(APP_ENV == APP_DEVELOPMENT)
        {
            throw new \Exception("Error: {$ex->getMessage()}", null, $ex);
        }
        else
        {
            $event = self::$MVC_EVENT;
            
            $serviceManager = $event->getApplication()->getServiceManager();
            
            $message = $ex->getMessage() . " ({$ex->getCode()}) " . PHP_EOL . $ex->getTraceAsString();
            
            $mErrorLog = $serviceManager->get('App\Model\ErrorLog');
            $mErrorLog->logError('excpetion', $message, $ex->getFile(), $ex->getLine());
        }
    }
}