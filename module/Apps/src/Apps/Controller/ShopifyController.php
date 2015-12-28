<?php

namespace Apps\Controller;

use Zend, Com, App, Zend\View\Model\JsonModel;


class ShopifyController extends Com\Controller\AbstractController
{


    function apiAction()
    {
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        $log = $sl->get('Zend\Log\Logger');
        
        $log->debug(print_r(array(
            'dasdaAAS' 
        ), 1));
        
        // check if the user wants to install the application
        if($this->_isInstall())
        {
            // lets check if the request comes from shopify
            $verified = $this->_verifyWebhook($_GET);
            if(! $verified)
            {
                return $this->_badRequest();
            }
            
            //
            
            $sl = $this->getServiceLocator();
            $shop = $_GET['shop'];
            $request = $this->getRequest();
            $uri = $request->getUri();
            
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            
            $row = $dbShopifyApp->findByStore($shop);
            if(! $row)
            {
                if($request->isPost())
                {
                    $clear = function ($fieldName, Com\Communicator $com) use($request)
                    {
                        $value = $request->getPost($fieldName);
                        
                        $trim = new Zend\Filter\StringTrim();
                        $value = $trim->filter($value);
                        
                        if(empty($value))
                        {
                            $com->addError('Please provide all required values');
                        }
                        
                        return $value;
                    };
                    
                    $com = $this->getCommunicator();
                    
                    $instance = $clear('instance', $com);
                    $username = $clear('username', $com);
                    $password = $clear('password', $com);
                    
                    if($com->isSuccess())
                    {
                        $website = new Com\Validator\Website();
                        if($website->isValid($instance))
                        {
                            if('/' == substr($instance, - 1, 1))
                            {
                                $instance = substr($instance, 0, - 1);
                            }
                            
                            $clientConfig = array(
                                'adapter' => 'Zend\Http\Client\Adapter\Curl',
                                'curloptions' => array(
                                    CURLOPT_FOLLOWLOCATION => TRUE,
                                    CURLOPT_SSL_VERIFYPEER => FALSE 
                                ) 
                            );
                            
                            $client = new Zend\Http\Client(null, $clientConfig);
                            $client->setUri("$instance/login/token.php");
                            $client->setMethod('GET');
                            
                            $client->setParameterGet(array(
                                'username' => $username,
                                'password' => $password,
                                'service' => 'lms' 
                            ));
                            
                            try
                            {
                                $response = $client->send();
                                if($response->isOk())
                                {
                                    $body = $response->getBody();
                                    $decoded = Zend\Json\Decoder::decode($body);
                                    if(isset($decoded->error))
                                    {
                                        $com->addError($decoded->error);
                                    }
                                    elseif(! isset($decoded->token))
                                    {
                                        $com->addError("(Token) There was an unexpected error, please try again.", 'instance');
                                    }
                                    else
                                    {
                                        $data = array(
                                            'store' => $shop,
                                            'lms_instance' => $instance,
                                            'lms_token' => $decoded->token 
                                        );
                                        
                                        $dbShopifyApp->doInsert($data);
                                        
                                        // redirect
                                        $queryParams = array(
                                            'hmac' => $_GET['hmac'],
                                            'shop' => $shop,
                                            'signature' => $_GET['signature'],
                                            'timestamp' => $_GET['timestamp'] 
                                        );
                                        $query = http_build_query($queryParams, null, '&');
                                        
                                        $routeParams = array(
                                            'controller' => 'shopify',
                                            'action' => 'api' 
                                        );
                                        
                                        $url = $this->url()->fromRoute('apps', $routeParams) . "?$query";
                                        $this->redirect()->toUrl($url);
                                    }
                                }
                                else
                                {
                                    $com->addError("({$response->getStatusCode()}) There was an unexpected error, please try again.", 'instance');
                                }
                            }
                            catch(\Exception $e)
                            {
                                \App\NotifyError::notify($e);
                                $com->addError($e->getMessage(), 'instance');
                            }
                        }
                        else
                        {
                            $com->addError('Please provide a valid instance url', 'instance');
                        }
                    }
                }
                
                $this->assign($request->getPost());
                // lets show a small form so the user can provide the instance url
                // and login information
                $this->assign(array(
                    'hmac' => $_GET['hmac'],
                    'shop' => $_GET['shop'],
                    'signature' => $_GET['signature'],
                    'timestamp' => $_GET['timestamp'] 
                ));
                
                return $this->viewVars;
            }
            
            $routeParams = array(
                'controller' => 'shopify',
                'action' => 'confirm' 
            );
            
            $routeOptions = array(
                'force_canonical' => true 
            );
            
            $apiKey = $sl->get('shopify_api');
            $scopes = 'read_products,write_products,read_customers,write_customers,read_orders,write_orders';
            $redirectUri = urlencode($this->url()->fromRoute('apps', $routeParams, $routeOptions));
            
            $url = "https://{$shop}/admin/oauth/authorize?client_id=$apiKey&scope=$scopes&redirect_uri=$redirectUri";
            $this->redirect()->toUrl($url);
            return;
        }
        else
        {
            // bad request
            return $this->_badRequest();
        }
    }


    function confirmAction()
    {
        if($this->_isConfirmingInstallation())
        {
            $verified = $this->_verifyWebhook($_GET);
            if(! $verified)
            {
                return $this->_badRequest();
            }
            
            $sl = $this->getServiceLocator();
            $shop = $_GET['shop'];
            
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            $row = $dbShopifyApp->findByStore($shop);
            
            // first step is to check if the app is already installed ion the store
            // if we found a record in our database then, that means the app is installed in the user store
            if(! $row)
            {
                return $this->_badRequest();
            }
            else
            {
                // check if the application is already installed and redirect the user to the lms in such case
                if($row->access_token != '' && $row->code != '' && $row->lms_token != '')
                {
                    // now redirect the user to the help page
                    return $this->_goToHelp();
                }
            }
            
            // the application is not installed yet
            // so we have to install some webhooks in the user store
            
            $uri = "https://$shop/admin/oauth/access_token";
            $apiKey = $sl->get('shopify_api');
            $shopifyAppSecret = $sl->get('shopify_secret');
            
            $paramPost = array(
                'client_id' => $apiKey,
                'client_secret' => $shopifyAppSecret,
                'code' => $_GET['code'] 
            );
            
            try
            {
                $adapter = new Zend\Http\Client\Adapter\Curl();
                
                $client = new Zend\Http\Client($uri);
                $client->setAdapter($adapter);
                $client->setParameterGet($paramPost);
                $client->setMethod('POST');
                
                $response = $client->send();
                
                if($response->isSuccess())
                {
                    $re = Zend\Json\Decoder::decode($response->getBody());
                    if(! $re->access_token)
                    {
                        return $this->_badRequest();
                    }
                    
                    // shopify client
                    $shopifyClient = new App\Model\Shopify\Client($shop, $re->access_token);
                    
                    $routeOptions = array(
                        'force_canonical' => true 
                    );
                    
                    // register the uninstall webhook
                    $webHookUrl = $this->url()->fromRoute('apps', array(
                        'controller' => 'shopify',
                        'action' => 'uninstall' 
                    ), $routeOptions);
                    
                    $shopifyClient->call('POST', '/admin/webhooks.json', array(
                        'webhook' => array(
                            'address' => $webHookUrl,
                            'format' => 'json',
                            'topic' => 'app/uninstalled' 
                        ) 
                    ));
                    
                    // register the orders/paid webhook
                    $webHookUrl = $this->url()->fromRoute('apps', array(
                        'controller' => 'shopify',
                        'action' => 'enrol-user' 
                    ), $routeOptions);
                    
                    $shopifyClient->call('POST', '/admin/webhooks.json', array(
                        'webhook' => array(
                            'address' => $webHookUrl,
                            'format' => 'json',
                            'topic' => 'orders/paid' 
                        ) 
                    ));
                    
                    // save in database the access token
                    $row->code = $_GET['code'];
                    $row->access_token = $re->access_token;
                    
                    $data = $row->toArray();
                    $where = array(
                        'id = ?' => $row->id 
                    );
                    
                    $dbShopifyApp->doUpdate($data, $where);
                    
                    // now redirect the user to the help page
                    return $this->_goToHelp();
                    
                    // $uri = "https://$shop/admin/apps";
                    // $this->redirect()->toUrl($uri);
                    // return $this->getResponse();
                }
                else
                {
                    // there was an error, redirect the suer to the paradiso app on shopify store
                    $uri = "https://apps.shopify.com/paradiso-lms/";
                    return $this->redirect()->toUrl($uri);
                }
            }
            catch(\Exception $e)
            {
                \App\NotifyError::notify($e);
                ddd($e);
            }
        }
        else
        {
            return $this->_badRequest();
        }
    }


    function helpAction()
    {
        $verified = $this->_verifyWebhook($_GET);
        if(! $verified)
        {
            return $this->_badRequest();
        }
        
        $sl = $this->getServiceLocator();
        $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
        
        $row = $dbShopifyApp->findByStore($_GET['shop']);
        if(! $row)
        {
            $this->_badRequest();
        }
        
        $instance = $row->lms_instance;
        $token = $row->lms_token;
        
        #$lmsLink = $this->_getSsoLink($instance, $token);
        
        # 'lms_instance'
        # 'lms_token'
        $this->assign('store_url', "https://{$row->store}");
        $this->assign('lms_url', $row->lms_instance);
        
        return $this->viewVars;
    }


    function uninstallAction()
    {
        $headers = getallheaders();
        
        $params = array();
        $params['hmac'] = isset($headers['X-Shopify-Hmac-Sha256']) ? $headers['X-Shopify-Hmac-Sha256'] : '';
        $params['shop'] = isset($headers['X-Shopify-Shop-Domain']) ? $headers['X-Shopify-Shop-Domain'] : '';
        $params['topic'] = isset($headers['X-Shopify-Topic']) ? $headers['X-Shopify-Topic'] : '';
        
        // TODO
        // we ned to check if the request comes from shopify
        $verified = true; // $this->_verifyWebhook($params, false);
        if($verified && 'app/uninstalled' == $params['topic'])
        {
            $sl = $this->getServiceLocator();
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            
            $row = $dbShopifyApp->findByStore($params['shop']);
            if($row)
            {
                $where = array(
                    'id = ?' => $row->id 
                );
                $dbShopifyApp->doDelete($where);
            }
        }
        
        exit();
    }


    function enrolUserAction()
    {
        header('Content-Type:application/json');
        
        $headers = getallheaders();
        
        $params = array();
        $params['hmac'] = isset($headers['X-Shopify-Hmac-Sha256']) ? $headers['X-Shopify-Hmac-Sha256'] : '';
        $params['shop'] = isset($headers['X-Shopify-Shop-Domain']) ? $headers['X-Shopify-Shop-Domain'] : '';
        $params['topic'] = isset($headers['X-Shopify-Topic']) ? $headers['X-Shopify-Topic'] : '';
        $params['order_id'] = isset($headers['X-Shopify-Order-Id']) ? $headers['X-Shopify-Order-Id'] : '';
        
        $sl = $this->getServiceLocator();
        $log = $sl->get('Zend\Log\Logger');
        
        $log->info(print_r(array(
            $params,
            $headers 
        ), 1));
        
        // TODO
        // we need to check if the request comes from shopify
        $verified = true; // $this->_verifyWebhook($params, false);
        if($verified && 'orders/paid' == $params['topic'])
        {
            $sl = $this->getServiceLocator();
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            
            $row = $dbShopifyApp->findByStore($params['shop']);
            if($row)
            {
                $log->info('show found');
                
                // get the order from the shopify store
                $shopifyClient = new App\Model\Shopify\Client($params['shop'], $row->access_token);
                $order = $shopifyClient->call('GET', "/admin/orders/{$params['order_id']}.json");
                
                $log->info('Order -> ' . print_r($order, 1));
                if(is_array($order))
                {
                    // now we are going to try to create the user into the lms and enrol the user
                    // NOTE: we are not checking if the user aready exist in the lms and also wee are not checking if the course exist
                    $curl = new App\Lms\Curl();
                    
                    $firstName = $order['customer']['first_name'];
                    $lastName = $order['customer']['last_name'];
                    $email = $order['customer']['email'];
                    $sku = null;
                    
                    if(isset($order['line_items']) && isset($order['line_items'][0]) && isset($order['line_items'][0]['sku']))
                        $sku = $order['line_items'][0]['sku'];
                    
                    if($sku)
                    {
                        $getServerUrl = function ($functionName) use($row)
                        {
                            $instance = $row->lms_instance;
                            $token = $row->lms_token;
                            $restFormat = 'json';
                            
                            return "{$instance}/webservice/rest/server.php?wstoken={$token}&wsfunction={$functionName}&moodlewsrestformat={$restFormat}";
                        };
                        
                        // lest create the user
                        $serverUrl = $getServerUrl('local_paradisolms_create_users');
                        
                        $user = array();
                        $user['firstname'] = $firstName;
                        $user['lastname'] = $lastName;
                        $user['email'] = $email;
                        $user['username'] = $email;
                        
                        $params = array(
                            'users' => array(
                                0 => $user 
                            ) 
                        );
                        
                        $resp = $curl->post($serverUrl, $params);
                        
                        $log->info('Response on create user -> ' . print_r($resp, 1));
                        
                        // enrol the user into the course
                        $serverUrl = $getServerUrl('local_paradisolms_manual_enrol_users');
                        
                        $item = array();
                        $item['email'] = $email;
                        $item['idnumber'] = $sku;
                        
                        $params = array(
                            'enrolments' => array(
                                0 => $item 
                            ) 
                        );
                        
                        $resp = $curl->post($serverUrl, $params);
                        
                        $log->info('Response on enrol user -> ' . print_r($resp, 1));
                    }
                }
            }
        }
        
        exit();
    }


    function prefAction()
    {
        echo 21;
        exit();
    }


    function supportAction()
    {
        echo 22;
        exit();
    }


    /**
     *
     * @return \Zend\View\Model\JsonModel
     */
    protected function _badRequest()
    {
        // bad request
        $response = $this->getResponse();
        $response->setStatusCode(\Zend\Http\Response::STATUS_CODE_404);
        
        return $response;
    }


    /**
     * Returns a value indicating if the request is for installing the application
     *
     * @return boolean
     */
    protected function _isInstall()
    {
        $flag = isset($_GET['hmac']);
        $flag = ($flag && isset($_GET['shop']));
        $flag = ($flag && isset($_GET['signature']));
        $flag = ($flag && isset($_GET['timestamp']));
        
        return $flag;
    }


    /**
     * Returns a value indicating if the request is for installing the application
     *
     * @return boolean
     */
    protected function _isConfirmingInstallation()
    {
        $flag = isset($_GET['code']);
        $flag = ($flag && isset($_GET['hmac']));
        $flag = ($flag && isset($_GET['shop']));
        $flag = ($flag && isset($_GET['signature']));
        $flag = ($flag && isset($_GET['timestamp']));
        
        return $flag;
    }


    /**
     * Verify if is a valid request
     *
     * For more information go to: https://docs.shopify.com/api/webhooks/using-webhooks
     * and look for "Verify a webhook created through the API"
     *
     * @param array $params
     * @var string hmac
     * @var string shop
     * @var int timestamp
     *
     * @return boolean
     */
    protected function _verifyWebhook(array $params)
    {
        if(! isset($params['timestamp']))
        {
            return false;
        }
        
        $seconds = 24 * 60 * 60; // seconds in a day
        $olderThan = $params['timestamp'] < (time() - $seconds);
        
        if($olderThan)
        {
            return false;
        }
        
        $p = array();
        foreach($params as $param => $value)
        {
            if($param != 'signature' && $param != 'hmac')
            {
                $p[$param] = "{$param}={$value}";
            }
        }
        
        $sl = $this->getServiceLocator();
        $shopifyAppSecret = $sl->get('shopify_secret');
        
        asort($p);
        
        $p = implode('&', $p);
        $hmac = $params['hmac'];
        
        $calculatedHmac = hash_hmac('sha256', $p, $shopifyAppSecret);
        
        return ($hmac == $calculatedHmac);
    }


    /**
     *
     * @return Zend\Http\Response
     */
    protected function _goToHelp()
    {
        // now redirect the user to the help page
        $queryParams = array(
            'code' => isset($_GET['code']) ? $_GET['code'] : '',
            'hmac' => isset($_GET['hmac']) ? $_GET['hmac'] : '',
            'shop' => isset($_GET['shop']) ? $_GET['shop'] : '',
            'signature' => isset($_GET['signature']) ? $_GET['signature'] : '',
            'timestamp' => isset($_GET['timestamp']) ? $_GET['timestamp'] : '' 
        );
        $query = http_build_query($queryParams, null, '&');
        
        $routeParams = array(
            'controller' => 'shopify',
            'action' => 'help' 
        );
        
        $url = $this->url()->fromRoute('apps', $routeParams) . "?$query";
        return $this->redirect()->toUrl($url);
    }


    protected function _getSsoLink($instance, $token)
    {
        $r = false;
        
        $clientConfig = array(
            'adapter' => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_FOLLOWLOCATION => TRUE,
                CURLOPT_SSL_VERIFYPEER => FALSE 
            ) 
        );
        
        $client = new Zend\Http\Client(null, $clientConfig);
        $client->setUri("$instance/local/paradisolms/ws_proxy.php");
        $client->setMethod('GET');
        
        $client->setParameterGet(array(
            'wstoken' => $token,
            'wsfunction' => 'local_paradisolms_auth_token_salt' 
        ));
        
        $response = $client->send();
        if($response->isSuccess())
        {
            try
            {
                $decoded = Zend\Json\Decoder::decode($response->getBody());
                if(isset($decoded->salt))
                {
                    $salt = $decoded->salt;
                    // now get the user information related to the token code
                    
                    $client->setParameterGet(array(
                        'wstoken' => $token,
                        'wsfunction' => 'local_paradisolms_get_user_by_token',
                        'token' => $token 
                    ));
                    
                    $response = $client->send();
                    if($response->isSuccess())
                    {
                        $decoded = Zend\Json\Decoder::decode($response->getBody());
                        if($decoded->email)
                        {
                            // redirect to the sso page
                            $email = $decoded->email;
                            $time = time();
                            $token = crypt($time . $email, $salt);
                            
                            $r = "$instance/local/paradisolms/sso.php?token=$token&timestamp=$time&email=$email";
                        }
                    }
                }
            }
            catch(\Exception $e)
            {
                ;
            }
        }
        
        return $r;
    }
}