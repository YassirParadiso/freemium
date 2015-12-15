<?php

namespace Backend\Controller;

use Zend, Com, App;


class InstanceController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $this->assign('grid_title', "Instances");

        $grid = new App\DataGrid\Client($sl, $this->viewVars);
        $view = $grid->render();                
        
        $colorBoxView = new Zend\View\Model\ViewModel();
        $colorBoxView->setTemplate('backend/instance/list.phtml');
        
        $view->addChild($colorBoxView, 'after_title');
        
        return $view;
    }


    function setDueDateAction()
    {
        $this->layout('layout/blank');

        $sl = $this->getServiceLocator();
        $request = $this->getRequest();

        $client = $this->_params('client');

        $dbClient = $sl->get('App\Db\Client');
        $row = $dbClient->findByPrimarykey($client);
        $found = false;

        if($row && (0 == $row->deleted))
        {
            $found = true;

            if($row->due_date)
            {
                $this->assign('due_date', $row->due_date);
            }
        }

        if(!$found)
        {
            $this->getCommunicator()->addError('Sorry, instance not found');
        }
        
        if($found && $request->isPost())
        {
            // first we need to check if the given date is ok
            $dueDate = $request->getPost('due_date');
            $time = strtotime($dueDate);
            if($time)
            {
                $date = date('M d, Y', $time);
                $today = date('Y-m-d');

                $datetime1 = new \DateTime($dueDate);
                $datetime2 = new \DateTime($today);
                $interval = $datetime2->diff($datetime1);
                $difference = (int)$interval->format('%R%a');

                if($difference > -1)
                {
                    $data = array(
                        'due_date' => $dueDate
                    );
                    $dbClient->doUpdate($data, array('domain' => $row->domain));
                }
                else
                {
                    $this->getCommunicator()->addError('Please provide a valid due date');
                }
            }
            else
            {
                $this->getCommunicator()->addError('Please provide a valid due date');
            }
        }

        $this->assign($request->getPost());

        return $this->viewVars;
    }


    function addInstanceAction()
    {
        $this->loadCommunicator();
        $request = $this->getRequest();
        
        $params = $request->getPost();
        if($request->isPost())
        {
            $sl = $this->getServiceLocator();
            $mInstance = $sl->get('App\Model\Freemium\Instance');

            $params->created_from = 'backend';
            
            $flag = $mInstance->canCreateInstance($params);
            $this->setCommunicator($mInstance->getCommunicator());
            if($flag)
            {
                $flag = $mInstance->doCreateAccount($params);
                $this->setCommunicator($mInstance->getCommunicator());
                if($flag)
                {
                    ;
                }
            }
        }

        $this->assign($params);

        return $this->viewVars;
    }
    
    
    function deleteFreeDbAction()
    {
    	$sl = $this->getServiceLocator();
    	
    	
    	$mInstance = $sl->get('App\Model\Freemium\Instance');
    	
    	$mInstance->deleteFreeDatabases();
    	exit;
    	
    }
    
    
    
    function deleteAction()
    {
        $sl = $this->getServiceLocator();

        try
        {
            $dbClient = $sl->get('App\Db\Client');
            
            $request = $this->getRequest();
            $id = $this->_params('id', '');
            
            if($request->isPost() || ($request->isGet() && $id))
            {
                if($request->isPost())
                {
                    $ids = (array)$request->getPost('item');
                }
                else
                {
                    $ids = array($id);
                }

                $predicateSet = new Zend\Db\Sql\Predicate\PredicateSet();
                $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\In('id', $ids));
                $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('deleted', '=', 0));
                $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('approved', '=', 0));

                try
                {
                    
                    $rowset = $dbClient->findBy($predicateSet);
                    $toDelete = array();
                    
                    foreach($rowset as $row)
                    {
                        $toDelete[] = $row;
                        
                        // find if there are another accounts with the same domain
                        $predicateSet = new Zend\Db\Sql\Predicate\PredicateSet();
                        $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('deleted', '=', 0));
                        $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('domain', '=', $row->domain));
                        $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('approved', '=', 0));
                        $predicateSet->addPredicate(new Zend\Db\Sql\Predicate\Operator('id', '!=', $row->id));
                        $rowset2 = $dbClient->findBy($predicateSet);
                        
                        if($rowset2->count())
                        {
                            foreach($rowset2 as $row2)
                            {
                                $toDelete[] = $row2;
                            }
                        }
                    }
                    
                    $count = 0;
                    foreach($toDelete as $row)
                    {
                        //
                        $where = array();
                        $where['id = ?'] = $row->id;
                        
                        $data = array(
                            'email' => "{$row->email}.{$row->id}"
                            ,'domain' => "{$row->domain}.{$row->id}"
                            ,'deleted' => 1
                            ,'deleted_on' => date('Y-m-d H:i:s')
                        );
                        
                        $dbClient->doUpdate($data, $where);
                        $count++;
                    }
                
                    $com = $this->getCommunicator()->setSuccess("$count rows deleted");
                
                    $this->saveCommunicator($com);
            
                    return $this->redirect()->toRoute('backend/wildcard', array(
                        'controller' => 'instance'
                        ,'action' => 'approval-pending'
                    ));
                }
                catch(\Exception $e)
                {
                    $errorMessage = $e->getMessage();
                    return $this->_redirectToListWithMessage($errorMessage, true, 'approval-pending');
                }
            }
            
            $domain = $this->_params('domain', '');
            
            $cp = $sl->get('cPanelApi');
            $config = $sl->get('config');
            
            $dbDatabase = $sl->get('App\Db\Database');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            
            $mDataPath = $config['freemium']['path']['mdata'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            //
            $rowsetClient = $dbClient->findByDomain($domain);
            $countClient = $rowsetClient->count();
            
            if(0 == $countClient)
            {
                $errorMessage = "$countClient records found with the domain name $domain.";
                return $this->_redirectToListWithMessage($errorMessage, true);
            }
            
            foreach($rowsetClient as $rowClient)
            {
                $clientId = $rowClient->id;
                
                //
                $rowsetDatabase = $dbDatabase->findDatabaseByClientId($clientId);
                $countDatabases = $rowsetDatabase->count();
                if($countDatabases > 1)
                {
                    $errorMessage = "Ups, $count databases found related to the domain name $domain, please have a look first.";
                    return $this->_redirectToListWithMessage($errorMessage, true);
                }
                elseif(1 == $countDatabases)
                {
                    $rowDatabase = $rowsetDatabase->current();
                    $dbName = $rowDatabase->db_name;
                    $dbNameNoPrefix = str_replace($dbPrefix, '', $dbName);
                }
                
                // update client email and domain 
                $where = array();
                $where['id = ?'] = $rowClient->id;
                
                $uid = uniqid();
                $data = array(
                    'email' => "{$rowClient->email}.$uid"
                    ,'domain' => "{$rowClient->domain}.$uid"
                    ,'deleted' => 1
                );
                $dbClient->doUpdate($data, $where);
            }
            
            /*************************************/
            // unpark the domain
            /*************************************/
            $response = $cp->unpark($cpanelUser, $domain);
                
            if(isset($response['error']) || isset($response['event']['error']))
            {
                $errorMessage = isset($response['error']) ? $response['error'] : $response['event']['error'];
                return $this->_redirectToListWithMessage($errorMessage, true);
            }
            
            
            /*************************************/
            // rename mdata folder
            /*************************************/
            #exec("rm {$mDataPath}/$domain/ -Rf");
            exec("mv {$mDataPath}/$domain/ {$mDataPath}/$domain.deleted");
            
            
            /*************************************/
            // rename config file
            /*************************************/
            $configFilename = "{$configPath}/{$domain}.php";
            #exec("rm $configFilename");
            exec("mv $configFilename $configFilename.deleted");
            
            $message = "Domain $domain successfull removed.";
            return $this->_redirectToListWithMessage($message, false);
        }
        catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();
            return $this->_redirectToListWithMessage($errorMessage, true);
        }
    }
    
    
    protected function _redirectToListWithMessage($message, $isError, $list = 'list')
    {
        $com = $this->getCommunicator();
        
        if($isError)
        {
            $com->addError($message);
        }
        else
        {
            $com->setSuccess($message);
        }
        
        $this->saveCommunicator($com);
        
        return $this->redirect()->toRoute('backend/wildcard', array(
            'controller' => 'instance'
            ,'action' => $list
        ));
    }
    
    
    function infoAction()
    {
        $this->layout('layout/blank');
        
        // client id
        $id = $this->_params('id', 0);
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        $dbDatabase = $sl->get('App\Db\Database');
        $dbUser = $sl->get('App\Db\User');
        
        $rowClient = $dbClient->findByPrimaryKey($id);
        $result = $dbDatabase->findDatabaseByClientId($id);
        
        if($rowClient && $result->count() && $rowClient->approved)
        {
            $rowDb = $result->current();
            
            $client = new App\Lms\Services\Client();
            $client->setServicesToken($rowDb->db_name);
            
            $client->setServerUri("http://{$rowClient->domain}/services/index.php");
            
            $this->_assignLastLoginInfo($client);
            $this->_assignCountUsers($client);
            $this->_assignCountLoginInfo($client, date('Y-m-d', strtotime($rowClient->created_on)));
            
            //
            if($rowClient->approved_by)
            {
                $row = $dbUser->findByPrimaryKey($rowClient->approved_by);
                $rowClient->approved_by = "{$row->first_name} {$row->last_name}";
            }

            if($rowClient->created_by)
            {
                $row = $dbUser->findByPrimaryKey($rowClient->created_by);
                $rowClient->created_by = "{$row->first_name} {$row->last_name}";
            }

            $this->assign('client', $rowClient);
        }
        else
        {
            if(!$rowClient)
            {
                $this->getCommunicator()->addError('Client not found');
            }
            else
            {
                if(!$rowClient->approved)
                {
                    $this->getCommunicator()->addError('Instance not approved.');
                }
                else
                {
                    $this->getCommunicator()->addError('The client do not have an assigned database.');
                }
            }
        }
        
        return $this->viewVars;
    }
    
    
    protected function _assignCountUsers(App\Lms\Services\Client $client)
    {
        $response = $client->request('count_users');
            
        if($response->isError())
        {
            $this->getCommunicator()->addError($response->getMessage());
        }
        else
        {
            $this->assign($response->getParams());
        }
    }
    
    
    protected function _assignLastLoginInfo(App\Lms\Services\Client $client)
    {
        $response = $client->request('last_login');
        
        if($response->isError())
        {
            $r = $response->getMessage();
            $this->assign('last_login_date', $r);
            $this->assign('last_login_user', null);
        }
        else
        {
            $params = $response->getParams();
            
            $this->assign('last_login_date', date('F d, Y @ h:i:s a', $params['time']));
            $this->assign('last_login_user', "{$params['user']} - {$params['email']}");
        }
    }
    
    
    protected function _assignCountLoginInfo(App\Lms\Services\Client $client, $startDate)
    {
        $response = $client->request('count_logins', array('start_date' => $startDate));
        
        $this->assign('count_logins_from_date', date('F d, Y', strtotime($startDate)));
        
        if($response->isError())
        {
            $r = $response->getMessage();
            $this->assign('count_logins', $r);
            
        }
        else
        {
            $params = $response->getParams();
            $r = $params['count'];
            
            $this->assign('count_logins', $r);
        }
    }
}