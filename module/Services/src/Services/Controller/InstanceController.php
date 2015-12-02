<?php
namespace Services\Controller;

use Zend, Com, Zend\View\Model\JsonModel;
 

class InstanceController extends Com\Controller\AbstractController
{


    function getDueDateAction()
    {
        $this->layout('layout/blank');
        $this->basicAuthentication('webservices');

        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $instance = $this->_params('instance');

        if($instance)
        {
            $dbClient = $sl->get('App\Db\Client');
            $where = array(
                'domain' => $instance
            );

            $row = $dbClient->findBy($where)->current();
            if($row)
            {
                $dueDate = null;
                $difference = null;
                $dueOn = null;
                if($row->due_date)
                {
                    
                    $dueDate = $row->due_date;
                    $time = strtotime($dueDate);
                    $dueOn = date('M d, Y', $time);
                    $date = date('M d, Y', $time);
                    $today = date('Y-m-d');

                    $datetime1 = new \DateTime($row->due_date);
                    $datetime2 = new \DateTime($today);
                    $interval = $datetime2->diff($datetime1);
                    $difference = (int)$interval->format('%R%a');
                }

                $data = array(
                    'due_date' => $dueDate
                    ,'due_on' => $dueOn
                    ,'due_days' => $difference
                );

                $com->setData($data);
            }
            else
            {
                $com->addError('Instance not found');
            }
        }
        else
        {
            $com->addError('Missing instance name');
        }
        
        $json = $com->toArray();

        $result = new Zend\View\Model\JsonModel($json);
        return $result;
    }


    /**
    * This method will sync a database for the given instance.
    * Sync means that the code will check if the database of the instance already have all required tables, if not then it will create
    *
    * ############# IMPORTANT #############
    * After finished the sync, this method will send the notification email to the user
    * This method should be called using PHP curl
    */
    function syncDatabaseAndNotifyAction()
    {
        $this->basicAuthentication('webservices');
        $sl = $this->getServiceLocator();

        set_time_limit(0);

        $config = $sl->get('config');
        $clientId = $this->_params('client_id');

        try
        {
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $rowClient = $dbClient->findByPrimarykey($clientId);
            if(!$rowClient)
            {
                throw new \Exception("Client with ID $clientId not found");
            }

            if($rowClient->deleted || $rowClient->email_verified)
            {
                throw new \Exception("Client with ID $clientId is deleted or is already verified ");
            }

            $rowsetDatabase = $dbDatabase->findDatabaseByClientId($rowClient->id);
            if(!$rowsetDatabase->count())
            {
                throw new \Exception("No database assigned to the client");
            }

            

            $pathSchema =  $config['freemium']['path']['master_freemium_schema'];
            $currentTables = array();
            foreach(glob("$pathSchema/*.sql") as $item)
            {
                $info = pathinfo($item);
                $tableName = $info['filename'];
                $currentTables[$tableName] = $item;
            }
            
            foreach ($rowsetDatabase as $rowDatabase)
            {
                $tablesToCreate = array();
                $instanceTables = array();

                /////////////////////////////////////////////
                # sync databases
                /////////////////////////////////////////////   
                $database = $rowDatabase->db_name;
                $instanceAdapter = $this->_getInstanceAdapter($database);

                $sql = 'show tables';
                $rowset = $instanceAdapter->query($sql)->execute();
                foreach ($rowset as $row)
                {
                    $tabelName = current($row);
                    $instanceTables[$tabelName] = $tabelName;
                }

                foreach ($currentTables as $key => $item)
                {
                    if(!isset($instanceTables[$key]))
                    {
                        $tablesToCreate[$key] = $item;
                    }
                }

                foreach ($tablesToCreate as $item)
                {
                    $sql = file_get_contents($item);
                    # $instanceAdapter->query($sql)->execute();
                }
            }

            /////////////////////////////////////////////
            # send the confirmation email to the user
            /////////////////////////////////////////////

            $lang = $rowClient->lang;

            // prepare the verification code
            $cPassword = new Com\Crypt\Password();
            $plain = $rowClient->email;
            $code = $cPassword->encode($plain);

            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}";
            
            $routeParams = array();
            $routeParams['action'] = 'verify-account';
            $routeParams['code'] = $code;
            $routeParams['email'] = $rowClient->email;
            
            $viewRenderer = $sl->get('ViewRenderer');
            $url = $serverUrl . $viewRenderer->url('auth/wildcard', $routeParams);
            
            // preparing some replacement values
            $data = array();
            $data['follow_us'] = $this->_('follow_us');
            $data['body'] = $this->_('confirm_your_email_address_body', array(
                $url,
                $rowClient->email,
                $rowClient->password 
            ));
            $data['header'] = '';
            
            // load the email template and replace values
            $mTemplate = $sl->get('App\Model\EmailTemplate');
            
            $langString = '';
            if('es' == $lang)
            {
                $langString = "_$lang";
            }
            
            $arr = $mTemplate->loadAndParse("common{$langString}", $data);
            
            //
            $mailer = new Com\Mailer();
            
            // prepare the message to be send
            $message = $mailer->prepareMessage($arr['body'], null, $this->_('confirm_your_email_address_subject'));
            
            $message->setTo($rowClient->email);
            $mailTo = $config['freemium']['mail_to'];
            foreach($mailTo as $mail)
            {
                $message->addBcc($mail);
            }

            // prepare de mail transport and send the message
            $transport = $mailer->getTransport($message, 'smtp1', 'sales');
            $transport->send($message);
            echo "Done!";
        }
        catch(\Exception $e)
        {
            try
            {
                $mailer = new Com\Mailer();
            
                // prepare the message to be send

                $m = '';
                $m .= "<p><strong>{$e->getMessage()}</strong></p>";
                $m .= "File: {$e->getFile()} ({$e->getLine()})";
                $m .= '<pre>';
                $m .= $e->getTraceAsString();
                $m .= '</pre>';
                $message = $mailer->prepareMessage($m, null, 'Error grave al intentar sincronizar base de datos freemium');
                
                $mailTo = $config['freemium']['mail_to'];
                foreach($mailTo as $mail)
                {
                    $message->addTo($mail);
                }

                // prepare de mail transport and send the message
                $transport = $mailer->getTransport($message, 'smtp1', 'sales');
                $transport->send($message);
            }
            catch(\Exception $e2)
            {
                ddd($e2);
            }

            ddd($e);
        }

        exit;
    }



    protected function _getInstanceAdapter($database, $host = null, $username = null, $password = null)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');

        if(empty($host))
            $host = $config['freemium']['cpanel']['server'];

        if(empty($username))
            $username = $config['freemium']['db']['user'];

        if(empty($password))
            $password = $config['freemium']['db']['password'];


        return new \Zend\Db\Adapter\Adapter(array(
            'driver' => 'mysqli',
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'hostname' => $host,
            'profiler' => true,
            'charset' => 'UTF8',
            'options' => array(
                'buffer_results' => true 
            ) 
        ));
    }
}