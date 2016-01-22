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
            $dbPrefix = $config['freemium']['db']['prefix'];

            $langs = array(
                'en','es'
            );

            //
            $msg = "Started at ".date('Y-m-d H:i:s') . PHP_EOL;
            $console->writeLine($msg, 11);

            //
            $cPanelUser = $config['freemium']['cpanel']['username'];
            $cp = $sl->get('cPanelApi2');

            foreach ($langs as $lang)
            {
                $min = $config['freemium']['min_databases'][$lang];
                $existing = $dbDatabase->countFree($lang);
                
                if($existing > $min)
                {
                    $msg = "No need to create more databases for '$lang' version. Currenlty there are $existing databases";
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
                    $connection = $this->_getDbConnectionParams();

                    /*************************************/
                    // add the new database
                    /*************************************/
                    $data = array(
                        'db_host' => $connection['db_host']
                        ,'db_name' => null
                        ,'db_user' => $connection['db_user']
                        ,'db_password' => $connection['db_password']
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
                        $where->equalTo('id', $databaseId);
                    });

                    /*************************************/
                    // create the database
                    /*************************************/
                    $queryMF = array(
                        'module' => 'MysqlFE',
                        'function' => 'createdb',
                        'user' => $cPanelUser,
                    );
                    $queryArgs = array(
                        'db' => $newDatabaseNamePrefixed,
                    );
                    $response = $cp->cpanel_api2_request('cpanel', $queryMF, $queryArgs);
                    $aResponse = $response->getResponse('array');
                    if(isset($aResponse['cpanelresult']['error']))
                    {
                        // delete the database record
                        $dbDatabase->doDelete(function($where) use($databaseId) {
                            $where->equalTo('id', $databaseId);
                        });

                        throw new \Exception($aResponse['cpanelresult']['error']);
                    }

                    $console->writeLine("Database $newDatabaseNamePrefixed created for language $lang", 11);
                    
                    /*******************************/
                    // Assign user to db
                    /*******************************/
                    $queryMF = array(
                        'module' => 'MysqlFE',
                        'function' => 'setdbuserprivileges',
                        'user' => $cPanelUser,
                    );
                    $queryArgs = array(
                        'privileges' => 'ALL_PRIVILEGES',
                        'db' => $newDatabaseNamePrefixed,
                        'dbuser' => $connection['db_user'],
                    );
                    $response = $cp->cpanel_api2_request('cpanel', $queryMF, $queryArgs);
                    $aResponse = $response->getResponse('array');
                    if(isset($aResponse['cpanelresult']['error']))
                    {
                        // delete the database record
                        $dbDatabase->doDelete(function($where) use($databaseId) {
                            $where->equalTo('id', $databaseId);
                        });

                        throw new \Exception("Cannot assign privileges to user {$connection['db_user']} on database $newDatabaseNamePrefixed.<br> {$aResponse['cpanelresult']['error']}");
                    }
                    $console->writeLine("Assiged user {$connection['db_user']} to database $newDatabaseNamePrefixed", 11);


                    /*******************************/
                    // update database schema
                    /*******************************/
                    $username = $connection['db_user'];
                    $password = $connection['db_password'];
                    $host = $connection['db_host'];
                    $dbName = $newDatabaseNamePrefixed;                    
                    $adapter = $this->_getAdapter($dbName, $host, $username, $password);
                    
                    $sql = "ALTER SCHEMA `$newDatabaseNamePrefixed`  DEFAULT CHARACTER SET utf8  DEFAULT COLLATE utf8_general_ci";
                    $statement = $adapter->query($sql, 'execute');
                    $console->writeLine("Schema updated on database $newDatabaseNamePrefixed", 11);


                    /*******************************/
                    // RESTORING database
                    /*******************************/
                    $folder = $config['freemium']['path']['master_freemium_schema'][$lang];
                    $username = $connection['db_user'];
                    $password = $connection['db_password'];
                    $host = $connection['db_host'];
                    $dbName = $newDatabaseNamePrefixed;

                    $console->writeLine("Restoring tables from $folder into $newDatabaseNamePrefixed", 11);
                    
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

            $time = time();
            $today = date('Y-m-d', $time);

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
                $select->where(function($where) use($today, $lang){
                    $where->equalTo('due_date', $today);
                    $where->equalTo('deleted', 0);
                    $where->equalTo('internal', 0);
                    $where->equalTo('lang', $lang);
                });

                $select->group('domain');

                $rowset = $dbClient->selectWith($select);

                $msg = "{$rowset->count()} recods found" . PHP_EOL;
                $console->writeLine($msg, 11);

                
                require_once 'vendor/3rdParty/moodle/moodlelib.php';
                require_once 'vendor/3rdParty/moodle/password.php';
                $year = date('Y');
                $newPass = "trial-$year";
                $adminPassword = hash_internal_user_password($newPass);

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
                        // set the same values we have in the master instance
                        if($masterUser)
                        {
                            $msg = "Update admin user credentials for instance {$row->domain}." . PHP_EOL;
                            $console->writeLine($msg, 11);

                            $query2 = "UPDATE mdl_user SET username = ? ,email = ? ,password = ? WHERE id = 2";
                            $adapter3->query($query2)->execute(array(
                                $masterUser['username']
                                ,$masterUser['email']
                                ,$adminPassword
                            ));

                            $disabled[] = array(
                                'domain' => $row->domain
                                ,'username' => $masterUser['email']
                                ,'password' => $adminPassword
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
                $msg = "<p>The following instances expired today and credentials has been changed so the client won't be able to login</p>";
                $msg .= '<ul>';

                foreach ($disabled as $item)
                {
                    $msg .= "<li>";
                    $msg .= "Instance: http://{$item['domain']}<br>";
                    $msg .= "Username: {$item['username']}<br>";
                    $msg .= "Password: $newPass<br>";
                    $msg .= "</li>";
                }

                $msg .= '</ul>';

                $mailer = new Com\Mailer();
            
                // prepare the message to be send
                $message = $mailer->prepareMessage($msg, null, "$c Instances Expired!");
                if(1 == $c)
                    $message = $mailer->prepareMessage($msg, null, "$c Instance Expired!");

                $mailTo = $config['freemium']['mail_to'];
                foreach($mailTo as $mail)
                {
                    $message->addTo($mail);
                }

                // prepare de mail transport and send the message
                $transport = $mailer->getTransport($message, 'smtp1', 'notifications');
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


    function mdataSyncAction()
    {
        $request = $this->getRequest();

        ini_set('display_errors', 1);
        error_reporting(E_ALL);

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

            $sl = $this->getServiceLocator();
            $config = $sl->get('config');
            $sources = $config['freemium']['path']['mdata_source'];

            

            foreach ($sources as $lang => $sourcePath)
            {
                $console->writeLine("");
                $console->writeLine("Executing commands for language: $lang", 11);

                $finalPath = $config['freemium']['path']['master_mdata'][$lang];
                $tmpPath = '/home/paradisolms/mdata/mdata_tmp';

                // create temp path

                $command = "cp $sourcePath $tmpPath -r";
                $console->writeLine("$command", 11);

                exec($command);
                
                // remove unwanted folders
                $toRemove = array(
                    "$tmpPath/cache/",
                    "$tmpPath/localcache/",
                    "$tmpPath/sessions/",
                    "$tmpPath/temp/",
                    "$tmpPath/trashdir/",
                );

                foreach ($toRemove as $pathToRemove)
                {
                    $command = "rm $pathToRemove -rf";
                    $console->writeLine("$command", 11);
                    exec($command);
                }

                // change chmod
                $command = "chmod 777 $tmpPath/ -R";
                $console->writeLine("$command", 11);
                exec($command);

                // rename the existing final path so we can remove latter
                $command = "mv $finalPath/ {$finalPath}_delete";
                $console->writeLine("$command", 11);
                exec($command);

                // rename the tmp path folder to the final path
                $command = "mv $tmpPath $finalPath";
                $console->writeLine("$command", 11);
                exec($command);

                // remove the final renamed folder
                $command = "rm {$finalPath}_delete/ -rf";
                $console->writeLine("$command", 11);
                exec($command);
            }

            $console->writeLine("");
            $console->writeLine("Done!", 11);
        }
        catch (RuntimeException $e)
        {
            $this->_notifyError($e);
            $this->_unlock(__method__);
        }

        $this->_unlock(__method__);
    }



    function removeDeletedAction()
    {
        $request = $this->getRequest();

        ini_set('display_errors', 1);
        error_reporting(E_ALL);

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
            
            $msg = "Started at ".date('Y-m-d H:i:s') . PHP_EOL;
            $console->writeLine($msg, 11);
            

            $sl = $this->getServiceLocator();
            $config = $sl->get('config');

            $cp = $sl->get('cPanelApi2');
            $cPanelUser = $config['freemium']['cpanel']['username'];

            $mDataPath = $config['freemium']['path']['mdata'];
            $configPath = $config['freemium']['path']['config'];

            $adapter = $sl->get('adapter');

            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');

            //
            $sql = new Zend\Db\Sql\Sql($adapter);
            $select = $sql->select();

            $select->columns(array(
                'client_id' => new Zend\Db\Sql\Literal('c.id'),
                'domain' => new Zend\Db\Sql\Literal('c.domain'),
                'db_name' => new Zend\Db\Sql\Literal('d.db_name'),
                'db_id' => new Zend\Db\Sql\Literal('d.id'),
            ));

            $select->from(array('c' => $dbClient->getTable()));
            $select->join(array('chd' => $dbClientDatabase->getTable()), 'chd.client_id = c.id', array(), 'left');
            $select->join(array('d' => $dbDatabase->getTable()), 'd.id = chd.database_id', array(), 'left');

            $select->where(function($where) {
                $where->equalTo('deleted', 1);
            });

            $rowset = $dbClient->executeCustomSelect($select);

            $msg = "{$rowset->count()} records found";
            $console->writeLine($msg, 11);

            
            $console->writeLine("", 11);
            $msg = "Removing mdata and config file";
            $console->writeLine($msg, 11);
            foreach ($rowset as $row)
            {
                $domain = $row->domain;
                $clientId = $row->client_id;
                $dbName = $row->db_name;
                $dbId = $row->db_id;

                $exploded = explode('.', $domain);
                array_pop($exploded);
                $domain = implode('.', $exploded);

                $instanceConfig = "$configPath/{$domain}.php.deleted";
                $instanceMdata = "$mDataPath/{$domain}.deleted";

                //
                $msg = "Removing $instanceMdata";
                $console->write($msg, 11);
                if(file_exists($instanceMdata))
                {
                    exec("rm -Rf {$instanceMdata}/");
                    $msg = " - Done";
                    $console->write($msg, 11);
                }
                else
                {
                    $msg = " - Not found";
                    $console->write($msg, 10);
                }

                //
                $console->writeLine("", 11);
                $msg = "Removing $instanceConfig";
                $console->write($msg, 11);

                if(file_exists($instanceConfig))
                {
                    exec("rm {$instanceConfig}");
                    $msg = " - Done";
                    $console->write($msg, 11);
                }
                else
                {
                    $msg = " - Not found";
                    $console->write($msg, 10);
                }

                //
                $console->writeLine("", 11);
                $msg = "Removing database $dbName";
                $console->write($msg, 11);

                if($dbName)
                {
                    $queryMF = array(
                        'module' => 'MysqlFE',
                        'function' => 'deletedb',
                        'user' => $cPanelUser,
                    );

                    $queryArgs = array(
                        'db' => $dbName,
                    );

                    $response = $cp->cpanel_api2_request('cpanel', $queryMF, $queryArgs);
                    $aResponse = $response->getResponse('array');
                    if(!isset($aResponse['cpanelresult']['error']))
                    {
                        $msg = " - Done";
                        $console->write($msg, 11);
                    }
                    else
                    {
                        $msg = " - {$aResponse['cpanelresult']['error']}";
                        $console->write($msg, 10);
                    }
                }
                else
                {
                    $msg = " - No database assigned";
                    $console->write($msg, 10);
                }
                
                //
                $dbClient->doDelete(function($where) use($clientId) {
                    $where->equalTo('id', $clientId);
                });

                $dbDatabase->doDelete(function($where) use($dbId) {
                    $where->equalTo('id', $dbId);
                });

                $dbClientDatabase->doDelete(function($where) use($dbId) {
                    $where->equalTo('database_id', $dbId);
                });

                //
                $console->writeLine("", 11);
                $console->writeLine("", 11);
            }
        }
        catch (RuntimeException $e)
        {
            $this->_notifyError($e);
            $this->_unlock(__method__);
        }


        $this->_unlock(__method__);
        $msg = "\nEnded at ".date('Y-m-d H:i:s')."";
        $console->writeLine($msg, 11);
    }


    protected function _getAdminUserFromMasterInstance($adapter)
    {
        $query = "SELECT * FROM mdl_user WHERE id = 2";
        $rowset = $adapter->query($query)->execute();
        return $rowset->current();
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
            
            $mailTo = $config['freemium']['mail_to_errors'];
            foreach($mailTo as $mail)
            {
                $message->addTo($mail);
            }

            // prepare de mail transport and send the message
            $transport = $mailer->getTransport($message, 'smtp1', 'dev');
            $transport->send($message);
        }
        catch(\Exception $e)
        {
            echo $e->getMessage();
            echo PHP_EOL;
            echo $e->getTraceAsString();
        }
    }


    /**
    * 
    */
    function _getDbConnectionParams()
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        $dbDatabase = $sl->get('App\Db\Database');

        $dbPerUser = $config['freemium']['db_per_users'];

        $sql = $dbDatabase->getSql();
        $select = $sql->select();

        $select->columns(array(
            '*'
            ,'c' => new Zend\Db\Sql\Literal('COUNT(*)')
        ));

        $select->group('db_user');
        $select->having("c < $dbPerUser");
        $select->order('c DESC');
        $select->limit(1);

        $row = $dbDatabase->executeCustomSelect($select)->current();
        if($row)
        {
            return array(
                'db_host' => $row->db_host
                ,'db_user' => $row->db_user
                ,'db_password' => $row->db_password
            );
        }
        else
        {
            
            $cPanelUser = $config['freemium']['cpanel']['username'];

            $letters = 'abcefghijklmnopqrstuvwxyz1234567890';
            $dbPassword = substr(str_shuffle($letters . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 15);
            $dbHost = $config['freemium']['db']['host'];
            $dbPrefix = $config['freemium']['db']['prefix'];

            // generate a unique user
            do
            {
                $rand = substr(str_shuffle($letters), 0, 6);
                $dbUser = "{$dbPrefix}{$rand}";

                $count = $dbDatabase->count(function($where) use($dbUser){
                    $where->equalTo('db_user', $dbUser);
                });
            }
            while($count > 0);

            $cp = $sl->get('cPanelApi2');

            $queryMF = array(
                'module' => 'MysqlFE',
                'function' => 'createdbuser',
                'user' => $cPanelUser,
            );

            $queryArgs = array(
                'dbuser' => $dbUser,
                'password' => $dbPassword
            );

            $response = $cp->cpanel_api2_request('cpanel', $queryMF, $queryArgs);
            $aResponse = $response->getResponse('array');
            if(isset($aResponse['cpanelresult']['error']))
            {
                throw new \Exception($aResponse['cpanelresult']['error']);
            }

            return array(
                'db_host' => $dbHost
                ,'db_user' => $dbUser
                ,'db_password' => $dbPassword
            );
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