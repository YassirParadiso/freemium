<?php
namespace App\Model;

use Com, Zend;
use Zend\Form\Element\Email;

class User extends Com\Model\AbstractModel
{

    /**
     * Metodo para el inicio de autenticacion del usuario con la informacion
     * enviada por parametro segun los valores definidos en $params, y registro del inicio de sesion en el caso
     * que la informacion enviada sea valida.
     *
     * @param Zend\Stdlib\Parameters $params 
     * @field string username - String del email del usuario que quiere autenticarse e iniciar sesion.
     * @field string password - String del password del usuario que quiere autenticarse e iniciar sesion.
     * @return boolean
     */
    function login(Zend\Stdlib\Parameters $params)
    {
        $sl = $this->getServiceLocator();
        
        try
        {
            $auth = $sl->get('Com\Auth\Authentication');
            $dbUser = $sl->get('App\Db\User');
            
            $auth->getAuthAdapter()->setIdentity($params->username);
            $auth->getAuthAdapter()->setCredential($params->password);
            $auth->getAuthAdapter()->setDbTable($dbUser);
            
            // Authenticate, saving the result, and persisting the identity on
            // success
            $result = $auth->authenticate();
            
            if(! $result->isValid())
            {
                // Authentication failed; return the reasons why
                
                $error = $result->getCode();
                
                if(- 1 == $error)
                {
                    $errorMessage = 'Failure due to identity not being found.';
                }
                elseif(- 2 == $error)
                {
                    $errorMessage = 'Failure due to identity being ambiguous.';
                }
                elseif(- 3 == $error)
                {
                    $errorMessage = 'Failure due to invalid credential being supplied.';
                }
                elseif(- 4 == $error) // -4
                {
                    $errorMessage = 'Failure due to uncategorized reasons.';
                }
                else // 0
                {
                    $errorMessage = 'failure';
                }
                
                $auth->clearIdentity();
                
                $this->getCommunicator()->addError($errorMessage);
            }
            else
            {
                // Authentication ok
                $identity = $result->getIdentity();
                
                //
                if($params->remember_me)
                {
                    $storage = $auth->getAuthService()->getStorage();
                    $storage->setRememberMe();
                    
                    $auth->getAuthService()->setStorage($storage);
                }
                
                // limpiar algunos valores antes de iniciar session
                unset($identity['password']);
                
                //
                $auth->getAuthService()
                    ->getStorage()
                    ->write($identity);
            }
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }
        
        return $this->isSuccess();
    }


    /**
     * @see https://app.asana.com/0/14725105905099/15626302371149
     * @return array | null
     */
    function setInitGroup()
    {
        ;
    }
}