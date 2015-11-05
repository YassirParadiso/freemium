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
    
    
    
    function approvalPendingAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $grid = new App\DataGrid\Approval($sl, $this->viewVars);
        $view = $grid->render();                
        
        $colorBoxView = new Zend\View\Model\ViewModel();
        $colorBoxView->setTemplate('backend/instance/approval-pending.phtml');
        
        $view->addChild($colorBoxView, 'after_title');
        
        return $view;
    }

    
    function deleteFreeDbAction()
    {
    	$sl = $this->getServiceLocator();
    	
    	
    	$mInstance = $sl->get('App\Model\Freemium\Instance');
    	
    	$mInstance->deleteFreeDatabases();
    	exit;
    	
    }
    
    
    function approveAction()
    {
        $sl = $this->getServiceLocator();
        $request = $this->getRequest();
        try
        {
            $ids = array();
            
            if($request->isPost())
            {
                $ids = (array)$request->getPost('item');
            }
            else
            {
                $ids[] = $this->_params('id', 0);
            }
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
            
            $mInstance->doApprove($ids);
            $com = $mInstance->getCommunicator();
            
            if($com->isSuccess())
            {
                $isError = false;
                $message = 'Account successfull approved';
            }
            else
            {
                $isError = true;
                $errors = $com->getGlobalErrors();
                $message = $errors[0];
            }
            
            return $this->_redirectToListWithMessage($message, $isError, 'approval-pending');
        }
        catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();
            return $this->_redirectToListWithMessage($errorMessage, true, 'approval-pending');
        }
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
                        );
                        
                        $dbClient->doUpdate($data, $where);
                        
                        if($row->logo)
                        {
                            @unlink($row->logo);
                        }
                        
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
            
            $topDomain = $config['freemium']['top_domain'];
            $mDataPath = $config['freemium']['path']['mdata'];
            $masterSqlFile = $config['freemium']['path']['master_sql_file'];
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
                    
                    

                    /*************************************/
                    // delete the database
                    /*************************************/
                    /*
                    $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'deletedb', array(
                        'db' => $dbName,
                    ));
                    
                    if(isset($response['error']) || isset($response['event']['error']))
                    {
                        $errorMessage = isset($response['error']) ? $response['error'] : $response['event']['error'];
                        return $this->_redirectToListWithMessage($errorMessage, true);
                    }
                    */
                    
                    // delete database from database database ;-)
                    /*
                    $where = array();
                    $where['id = ?'] = $rowDatabase->id;
                    $dbDatabase->doDelete($where);
                    
                    //
                    $where = array();
                    $where['database_id = ?'] = $rowDatabase->id;
                    $dbClientDatabase->doDelete($where);
                    */
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
            // delete the domain
            /*************************************/
            $response = $cp->unpark($cpanelUser, $domain);
                
            if(isset($response['error']) || isset($response['event']['error']))
            {
                $errorMessage = isset($response['error']) ? $response['error'] : $response['event']['error'];
                return $this->_redirectToListWithMessage($errorMessage, true);
            }
            
            
            /*************************************/
            // delete mdata folder
            /*************************************/
            #exec("rm {$mDataPath}/$domain/ -Rf");
            exec("mv {$mDataPath}/$domain/ {$mDataPath}/$domain.deleted");
            
            
            /*************************************/
            // delete config file
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
            $this->assign('client', $rowClient);
            
            if($rowClient->approved_by)
            {
                $row = $dbUser->findByPrimaryKey($rowClient->approved_by);
                $rowClient->approved_by = "{$row->first_name} {$row->last_name}";
            }
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