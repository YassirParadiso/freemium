<?php
namespace Front\Controller;

use Zend, Com, Zend\View\Model\JsonModel;
 

class AjaxController extends Com\Controller\AbstractController
{

   
    function checkInstanceCreatedAction()
    {
        $request = $this->getRequest();
        
        $com = $this->getCommunicator();
        $com->setNoSuccess();
      
        if($request->isPost())
        {
            $uri = $request->getPost('website');
            #$uri = 'http://paradisosolutions7824008.paradisolms.com';
            
            $client = new Zend\Http\Client();
            $client->setUri($uri);
            
            try
            {
                $response = $client->send();
                
                if($response->isOk())
                {
                    // analizamos el cuerpo de la pagina para buscar si esta intentando redireccionar
                    // a la pagina por defecto de cpanel
                    $body = $response->getBody();
                    if(false === stripos($body, '/cgi-sys/defaultwebpage.cgi'))
                    {
                        $com->setSuccess();
                    }
                    elseif(false === stripos($body, '<!-- /freemium -->'))
                    {
                        $com->setSuccess();
                    }
                }
            }
            catch(\Exception $e)
            {
                ;
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }
    
    
    function validateEmailAction()
    {
        $request = $this->getRequest();
        
        $com = $this->getCommunicator();
      
        if($request->isPost())
        {
            $email = $request->getPost('email');
        }
        else
        {
            if(isset($_GET['email']))
            {
                $email = $_GET['email'];
            }
            else
            {
                $email = $this->_params('email');
            }
        }

        $sl = $this->getServiceLocator();
        $dbClient = $sl->get('App\Db\Client');
        $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');
        
        // check if the email field looks like a real email address
        $vEmail = new Zend\Validator\EmailAddress();
        if(!$vEmail->isValid($email))
        {
            // add the error message to the communicator
            $com->addError($this->_('provide_valid_email'), 'email');
        }
        else
        {
            // check if already exist registered users with the given email address
            $where = array();
            $where['email = ?'] = $email;
            if($dbClient->count($where))
            {
                // add the error message to the communicator
                $com->addError($this->_('user_email_already_exist'), 'email');
            }
            else
            {
                // check if the domain of the email is allowed to create account
                $exploded = explode('@', $email);
                $emailDomain = $exploded[1];
        
                $where = array();
                $where['domain = ?'] = $emailDomain;
                if($dbBlacklistDomain->count($where))
                {
                    // add the error message to the communicator
                    $com->addError($this->_('email_address_not_allowed'), 'email');
                }
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }
    
    
    function canEditInstanceAction()
    {
        $request = $this->getRequest();
        
        $com = $this->getCommunicator();
        $com->setNoSuccess();
      
        if($request->isPost())
        {
            $email = $request->getPost('email');
            $sl = $this->getServiceLocator();
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
            if($mInstance->canEditInstanceName($email))
            {
                $com->setSuccess();
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }

    function sendcontactAction()
    {
        $request = $this->getRequest();

        $name = $request->getPost('name');
        $email = $request->getPost('email');
        $company = $request->getPost('company');
        $phone = $request->getPost('phone');
        $msg = $request->getPost('msg');
                        
        $mailer = new Com\Mailer();
        
        $html_msg='Este es un mensaje que ha sido enviado del formulario de freemium con la siguiente información:<br/>Name:'.$name.
        '<br/>Email: '.$email.'<br/>Company:'.$company.'<br/>Phone:'.$phone.'<br/>Message:'.$msg;
        
        $message = $mailer->prepareMessage($html_msg, null, 'Contacto Freemium');
        $message->setTo('alberto.g@paradisosolutions.com');
        $transport = $mailer->getTransport($message, 'smtp1', 'sales');
        $transport->send($message);
                    
                    /*$arr=array('status'=>'ok', 'email'=>$request->getPost('email'));
                    
                    $result = new JsonModel($arr);
    return $result;*/
    }

}