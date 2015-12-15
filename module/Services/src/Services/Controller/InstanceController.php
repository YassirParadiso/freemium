<?php
namespace Services\Controller;

use Zend, Com, App, Zend\View\Model\JsonModel;
 

class InstanceController extends Com\Controller\AbstractController
{


    function getDueDateAction()
    {
        $this->layout('layout/blank');

        try
        {
            $sl = $this->getServiceLocator();
            $com = $this->getCommunicator();
            $instance = $this->_params('instance');

            if($instance)
            {
                $dbClient = $sl->get('App\Db\Client');
                $where = array(
                    'domain' => $instance
                );

                $row = $dbClient->findBy($where, array(), 'id asc')->current();
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
        catch(\Exception $e)
        {
            \App\NotifyError::notify($e);
        }
    }


    /**
    * This method will sync database for the given instance.
    * Sync means that the code will check if the database of the instance already have all required tables, if not then it will create
    *
    * ############# IMPORTANT #############
    * After finished the sync, this method will send the notification email to the user
    * This method should be called using PHP curl
    */
    function syncDatabaseAndNotifyAction()
    {
        # $this->basicAuthentication('webservices');
        $sl = $this->getServiceLocator();

        set_time_limit(0);

        $config = $sl->get('config');
        $domain = $this->_params('domain');

        try
        {
            if(empty($domain))
            {
                throw new \Exception("Invalid call to the webservice. No domain name provided");
            }


            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');

            $clients = $dbClient->findBy(function($where) use($domain) {
                $where->equalTo('domain', $domain);
            }, array(), 'id asc');

            // lest just find the first user of the domain
            $client = $clients->current();

            if(!$client)
            {
                throw new \Exception("Client with domain $domain not found.");
            }

            if($client->deleted)
            {
                throw new \Exception("Client #{$client->id} with domain $domain is deleted. The confirmation email was not send.");
            }

            if($client->email_verified)
            {
                throw new \Exception("Client #{$client->id} with domain $domain is already verified. The confirmation email was not send.");
            }

            $databases = $dbDatabase->findDatabaseByClientId($client->id);
            if(!$databases->count())
            {
                throw new \Exception("No database assigned to client #{$client->id} with domain $domain. The confirmation email was not send.");
            }


            //
            if('backend' != $client->created_from)
            {
                ;
            }



            $lang = $client->lang;
            /////////////////////////////////////////////
            # sync databases
            /////////////////////////////////////////////   

            // get the list of tables from the path
            $pathSchema =  $config['freemium']['path']['master_freemium_schema'][$lang];
            $currentTables = array();
            foreach(glob("$pathSchema/*.sql") as $item)
            {
                $info = pathinfo($item);
                $tableName = $info['filename'];
                $currentTables[$tableName] = $item;
            }
            
            foreach ($databases as $database)
            {
                $tablesToCreate = array();
                $instanceTables = array();
                
                $databaseName = $database->db_name;
                $instanceAdapter = $this->_getInstanceAdapter($databaseName);

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
                    $instanceAdapter->query($sql)->execute();
                }
            }

            /////////////////////////////////////////////
            # send the confirmation email to the user
            /////////////////////////////////////////////

            // prepare the verification code
            $cPassword = new Com\Crypt\Password();
            $plain = $client->email;
            $code = $cPassword->encode($plain);

            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}";
            
            $routeParams = array();
            $routeParams['action'] = 'verify-account';
            $routeParams['code'] = $code;
            $routeParams['email'] = $client->email;
            
            $viewRenderer = $sl->get('ViewRenderer');
            $url = $serverUrl . $viewRenderer->url('auth/wildcard', $routeParams);
            
            // preparing some replacement values
            $data = array();
            $data['follow_us'] = $this->_('follow_us', array(), 'default', $lang);
            $data['body'] = $this->_('confirm_your_email_address_body', array(
                $url,
                $client->email,
                $client->password 
            ), 'default', $lang);
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
            $message = $mailer->prepareMessage($arr['body'], null, $this->_('confirm_your_email_address_subject', array(), 'default', $lang));
            
            $message->setTo($client->email);
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
            \App\NotifyError::notify($e);
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