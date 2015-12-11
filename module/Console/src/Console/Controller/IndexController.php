<?php
namespace Console\Controller;

use Zend, Com;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController,
Zend\Console\Request as ConsoleRequest;

class IndexController extends Com\Controller\AbstractController
{



    function runCronAction()
    {
        $request = $this->getRequest();
        
        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        $publicDir = PUBLIC_DIRECTORY;
        $coreDir = CORE_DIRECTORY;
        
        $sl = $this->getServiceLocator();

        $dbClient = $sl->get('App\Db\Client');
        
        $where = array();
        $where['deleted = ?'] = 0;
        $where['approved = ?'] = 1;
        $where['email_verified = ?'] = 1;
        
        $rowset = $dbClient->findby($where);
        
        $client = new Zend\Http\Client();
        $client->setMethod(Zend\Http\Request::METHOD_GET);
        
        // If we want a log this should be executed using wkhtmltopdf
        # $bin = '/usr/local/bin/wkhtmltopdf';
        foreach($rowset as $row)
        {
            $url = "http://{$row->domain}/admin/cron.php";
            
            //
            #if(strpos($row->domain, 'paradisosolutions') === false)
                #continue;
            
            # $command = "wget $url {$coreDir}/data/log/{$row->domain}.pdf > {$coreDir}/data/log/{$row->domain}.log 2>&1 &";
            $command = "wget -q0- $url &>/dev/null";
            
            shell_exec($command);
        }
    }

    

    function createDatabasesAction()
    {
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }

        try
        {
            $console = Console::getInstance();
            
            if($this->_isLocked(__method__))
            {
                $msg = "Already running...";
                $console->writeLine($msg, 10);
                exit;
            }

            $this->_lock(__method__);

            $sl = $this->getServiceLocator();

            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');

            $config = $sl->get('config');
            $langs = array(
                'en','es'
            );

            //
            $msg = "Started at ".date('Y-m-d H:i:s') . PHP_EOL;
            $console->writeLine($msg, 11);

            //
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            $cp = $sl->get('cPanelApi');

            foreach ($langs as $lang)
            {
                $min = $config['freemium']['min_databases'][$lang];
                $existing = $dbDatabase->countFree($lang);
                
                if($existing > $min)
                {
                    $msg = "No need to create more databases for $lang version. Currenlty there are $existing databases";
                    $console->writeLine($msg, 10);
                    continue;
                }

                //
                $msg = "-----------------------------------";
                $console->writeLine($msg, 11);
                
                #$number = $config['freemium']['max_databases'][$lang];
                $number = 1;
                for($i=0; $i < $number; $i++)
                {
                    /*************************************/
                    // add the new database
                    /*************************************/
                    $data = array(
                        'db_host' => $dbHost
                        ,'db_name' => null
                        ,'db_user' => $dbUser
                        ,'db_password' => $dbPassword
                        ,'lang' => $lang
                    );
                    $dbDatabase->doInsert($data);
                    $databaseId = $dbDatabase->getLastInsertValue();
                    
                    $newDatabaseName = "client{$databaseId}";
                    $newDatabaseNamePrefixed = "{$dbPrefix}client{$databaseId}";

                    /*************************************/
                    // update database name
                    /*************************************/
                    $data = array(
                        'db_name' => $newDatabaseNamePrefixed
                        );
                    
                    $dbDatabase->doUpdate($data, function($where) use($databaseId) {
                        $where->equalTo('id', $databaseId)
                    });

                    /*************************************/
                    // create the database
                    /*************************************/
                    $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'createdb', array(
                        'db' => $newDatabaseNamePrefixed,
                        ));

                    if(isset($response['error']) || isset($response['event']['error']))
                    {
                        // delete the database record
                        $dbDatabase->doDelete(function($where) use($databaseId) {
                            $where->equalTo('id', $databaseId)
                        });

                        $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                        throw new \RuntimeException($err);
                    }

                    $console->writeLine("Database $newDatabaseNamePrefixed created for language $lang", 11);
                    
                    /*******************************/
                    // update database schema
                    /*******************************/
                    #$adapter = $sl->get('adapter');
                    #$sql = "ALTER SCHEMA `$newDatabaseNamePrefixed`  DEFAULT CHARACTER SET utf8  DEFAULT COLLATE utf8_general_ci \n";
                    #$statement = $adapter->query($sql, 'execute');
                    #$console->writeLine("Schema updated on database $newDatabaseNamePrefixed", 11);


                    /*******************************/
                    // Assign user to db
                    /*******************************/
                    $dbUserName = $dbUser;
                    $response = $cp->api2_query($cpanelUser,
                        'MysqlFE', 'setdbuserprivileges',
                        array(
                            'privileges' => 'ALL_PRIVILEGES',
                            'db' => $newDatabaseNamePrefixed,
                            'dbuser' => $dbUserName,
                            )
                        );
                    
                    if(isset($response['error']) || isset($response['event']['error']))
                    {
                        $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                        throw new \RuntimeException("Cannot assign privileges to user $dbUserName on database $newDatabaseNamePrefixed . $err");
                    }
                    $console->writeLine("Assiged user $dbUserName to database $newDatabaseNamePrefixed", 11);


                    /*******************************/
                    // RESTORING database
                    /*******************************/
                    $console->writeLine("Restoring tables into $newDatabaseNamePrefixed", 11);
                    $username = $config['freemium']['db']['user'];
                    $password = $config['freemium']['db']['password'];
                    $host = $config['freemium']['db']['host'];
                    $dbName = $newDatabaseNamePrefixed;

                    $folder = $config['freemium']['path']['master_freemium_schema'][$lang];
                    
                    $adapter = $this->_getAdapter($dbName, $host, $username, $password);
                    
                    foreach(glob("$folder/*.sql") as $item)
                    {
                        if(0 == filesize($item))
                        {
                            continue;
                        }

                        $console->writeLine("Restoring $item", 11);

                        $sql = file_get_contents($item);
                        $adapter->query($sql)->execute();
                    }

                    $console->writeLine("Restoration completed into $newDatabaseNamePrefixed", 11);

                    $msg = "-----------------------------------";
                    $console->writeLine($msg, 11);
                }
            }

            $this->_unlock(__method__);
            $msg = "\nEnded at ".date('Y-m-d H:i:s')."";
            $console->writeLine($msg, 11);
        }
        catch (\Exception $e)
        {
            $this->_unlock(__method__);
            $this->_notifyError($e);
        }
    }


    function disableInstancesAction()
    {
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }

        try
        {
            $console = Console::getInstance();

            if($this->_isLocked(__method__))
            {
                $msg = "Already running...";
                $console->writeLine($msg, 10);
                exit;
            }
            else
            {
                $this->_lock(__method__);
                
                $msg = "Started at ".date('Y-m-d H:i:s') . PHP_EOL;
                $console->writeLine($msg, 11);
            }

            $disabled = array();
            $sl = $this->getServiceLocator();

            $dbClient = $sl->get('App\Db\Client');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            $dbDatabase = $sl->get('App\Db\Database');
            $adapter = $sl->get('adapter');

            $config = $sl->get('config');
            $langs = array(
                'en', 'es'
            );

            foreach ($langs as $lang)
            {
                $masterDatabase = $config['freemium']['master_instance'][$lang]['database'];
                $masterHost = $config['freemium']['master_instance'][$lang]['host'];
                $masterUser = $config['freemium']['master_instance'][$lang]['user'];
                $masterPassword = $config['freemium']['master_instance'][$lang]['password'];

                $masterAdapter = $this->_getInstanceAdapter($masterHost, $masterUser, $masterPassword, $masterDatabase);
                $masterUser = $this->_getAdminUserFromMasterInstance($masterAdapter);

                $sql2 = new Zend\Db\Sql\Sql($adapter);

                // get all instances that have to be disabled today
                $msg = "Getting instances that have to be disabled today for language $lang" . PHP_EOL;
                $console->writeLine($msg, 11);

                $sql = $dbClient->getSql();
                $select = $sql->select();
                $select->where(function($where) use($lang){
                    $time = time();
                    $today = date('Y-m-d', $time);

                    $where->equalTo('due_date', $today);
                    $where->equalTo('deleted', 0);
                    $where->equalTo('lang', $lang);
                });

                $select->group('domain');

                $rowset = $dbClient->selectWith($select);

                $msg = "{$rowset->count()} recods found" . PHP_EOL;
                $console->writeLine($msg, 11);
                foreach($rowset as $row)
                {
                    // get related databases
                    $select2 = $sql2->select();
                    $select2->from(array('cd' => $dbClientDatabase->getTable()));
                    $select2->join(array('d' => $dbDatabase->getTable()), 'd.id = cd.database_id');

                    $select2->where(function($where) use($row) {
                        $where->equalTo('client_id', $row->id);
                    });

                    $query = $sql2->buildSqlString($select2);
                    $rowset2 = $adapter->createStatement($query)->execute();
                    foreach ($rowset2 as $row2)
                    {
                        $host = $row2['db_host'];;
                        $username = $row2['db_user'];
                        $password = $row2['db_password'];
                        $database = $row2['db_name'];

                        $adapter3 = $this->_getInstanceAdapter($host, $username, $password, $database);

                        // change the admin user credentials
                        // set the same values we have in the maste rinstance
                        if($masterUser)
                        {
                            $msg = "Update admin user credentials for instance {$row->domain}." . PHP_EOL;
                            $console->writeLine($msg, 11);

                            $query2 = "UPDATE mdl_user SET username = ? ,email = ? ,password = ? WHERE id = 2";
                            $adapter3->query($query2)->execute(array(
                                $masterUser['username']
                                ,$masterUser['email']
                                ,$masterUser['password']
                            ));

                            $disabled[] = array(
                                'domain' => $row->domain
                                ,'username' => $masterUser['email']
                                ,'password' => $masterUser['password']
                            );
                        }

                        $msg = "disable all other users for instance {$row->domain}." . PHP_EOL;
                        $console->writeLine($msg, 11);
                        // now disable all other users of the instance                    
                        $query3 = "UPDATE mdl_user SET suspended = 1 WHERE id != 2";
                        $adapter3->query($query3)->execute();
                    }
                }
            }


            $c = count($disabled);
            if($c)
            {
                $msg = "<p>The following instances have expired today and credentials has been changed so the client won't be able to login</p>";
                $msg .= '<ul>';

                foreach ($disabled as $item)
                {
                    $msg .= "<li>";
                    $msg .= "Instance: http://{$item['doamin']}<br>";
                    $msg .= "Username: {$item['username']}<br>";
                    $msg .= "Password: {$item['password']}<br>";
                    $msg .= "</li>";
                }

                $msg .= '</ul>';

                $mailer = new Com\Mailer();
            
                // prepare the message to be send

                $message = $mailer->prepareMessage($msg, null, "$c Instances Expired!");
                $mailTo = $config['freemium']['mail_to'];
                foreach($mailTo as $mail)
                {
                    $message->addTo($mail);
                }

                // prepare de mail transport and send the message
                $transport = $mailer->getTransport($message, 'smtp1', 'sales');
                $transport->send($message);
            }


            $msg = "\nEnded at ".date('Y-m-d H:i:s') . "";
            $console->writeLine($msg, 11);
        }
        catch (RuntimeException $e)
        {
            $this->_notifyError($e);
            $this->_unlock(__method__);
        }

        $this->_unlock(__method__);
    }


    protected function _getAdminUserFromMasterInstance($adapter)
    {
        $query = "SELECT * FROM mdl_user WHERE id = 2";
        $rowset = $adapter->query($query)->execute();
        return $rowset->current();
    }


    private function _____removeAccountsAction_____()
    {
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        try
        {
            $sl = $this->getServiceLocator();
            
            $config = $sl->get('config');
            
            $console = Console::getInstance();
            
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            
            $topDomain = $config['freemium']['top_domain'];
            $mDataPath = $config['freemium']['path']['mdata'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            //
            $msg = "-----------------------------------";
            $console->writeLine($msg, 11);
            
            
            // lets look for deleted records older than 30 days
            $date = date('Y-m-d', strtotime("-30 days"));

            $predicate = new Zend\Db\Sql\Predicate\Predicate();
            $predicate->lessThanOrEqualTo('deleted_on', "$date 23:59:59");
            $predicate->equalTo('deleted', 1);

            $rowset = $dbClient->findby($predicate);
            $count = $rowset->count();

            if(0 == $count)
            {
                $msg = "No records found to delete.";
                $console->writeLine($msg, 10);
                exit;
            }

            // 
            $cp = $sl->get('cPanelApi');
            foreach($rowset as $row)
            {
                $clientId = $row->id;

                // when a client is "deleted", the script add to the domain random characters
                // so here we are going to remove those random characteres
                $latestDot = strrchr($row->domain, '.');
                $domain = str_replace($latestDot, '', $row->domain);

                /*************************************/
                // delete the domain
                /*************************************/
                #$response = $cp->unpark($cpanelUser, $domain);

                #if(isset($response['error']) || isset($response['event']['error']))
                {
                    #$err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                    #throw new \RuntimeException($err);
                }

                /*************************************/
                // delete mdata folder
                /*************************************/
                $command = "rm {$mDataPath}/{$domain}.deleted/ -Rf";
                exec($command);

                /*************************************/
                // delete config file
                /*************************************/
                $command = "rm {$configPath}/{$domain}.php.deleted";
                exec($command);

                
                /*************************************/
                // delete related databases from cpanel
                /*************************************/
                $rowset2 = $dbDatabase->findDatabaseByClientId($clientId);
                foreach ($rowset2 as $row2)
                {
                    $dbName = $row2->db_name;

                    $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'deletedb', array(
                        'db' => $dbName,
                        ));

                    if(isset($response['error']) || isset($response['event']['error']))
                    {
                        $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                        throw new \RuntimeException($err);
                    }
                }



                /*************************************/
                // delete database from database database ;-)
                /*************************************/
                $where = array();
                $where['client_id = ?'] = $clientId;
                $rowset2 = $dbClientDatabase->findBy($where);
                foreach($rowset2 as $row2)
                {
                    $where = array();
                    $where['id = ?'] = $row2->database_id;
                    $dbDatabase->doDelete($where);
                }

                $where = array();
                $where['client_id = ?'] = $clientId;
                $dbClientDatabase->doDelete($where);
            }
        }
        catch (RuntimeException $e)
        {
            ;
        }
    }


    
    protected function _isLocked($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        return file_exists($fileName);
    }
    
    
    
    protected function _lock($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        $handler = fopen($fileName, 'w') or die("can't open file");
        fclose($handler);
    }
    
    
    
    protected function _unlock($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        unlink($fileName);
    }


    protected function _getInstanceAdapter($host, $username, $password, $database)
    {
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



    protected function _notifyError($e)
    {

        $sl = $this->getServiceLocator();
            
        $config = $sl->get('config');

        try
        {
            $message = '';
            $file = '';
            $line = '';

            if($e instanceof \Exception)
            {
                $message = $e->getMessage();
                $file = $e->getFile();
                $line = $e->getLine();

                $e = $e->getTraceAsString();
            }
                

            $mailer = new Com\Mailer();
        
            // prepare the message to be send

            $m = '';
            $m .= "<p><strong>{$message}</strong></p>";
            $m .= "File: {$file} ({$line})";
            $m .= '<pre>';
            $m .= $e;
            $m .= '</pre>';
            $message = $mailer->prepareMessage($m, null, 'Error grave al intentar crear bases de datos freemium');
            
            $mailTo = $config['freemium']['mail_to'];
            foreach($mailTo as $mail)
            {
                $message->addTo($mail);
            }

            // prepare de mail transport and send the message
            $transport = $mailer->getTransport($message, 'smtp1', 'sales');
            $transport->send($message);
        }
        catch(\Exception $e)
        {
            echo $e->getMessage();
            echo PHP_EOL;
            echo $e->getTraceAsString();
        }
    }



    function _getAdapter($database, $host = null, $username = null, $password = null)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        if(empty($username))
        {
            $username = $config['freemium']['cpanel']['username'];
        }
        
        if(empty($password))
        {
            $password = $config['freemium']['cpanel']['password'];
        }
        
        if(empty($host))
        {
            $host = $config['freemium']['cpanel']['server'];
        }

        // lest connect to the master database
        $adapter = new Zend\Db\Adapter\Adapter(array(
            'driver' => 'mysqli',
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'hostname' => $host,
            'profiler' => false,
            'charset' => 'UTF8',
            'options' => array(
                'buffer_results' => true 
            )
        ));

        return $adapter;
    }
}