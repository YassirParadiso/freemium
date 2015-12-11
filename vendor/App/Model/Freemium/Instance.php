<?php

namespace App\Model\Freemium;

use Zend, Com, App;


class Instance extends Com\Model\AbstractModel
{

    protected $fileMimeType;

    protected $mailTo = array();


    function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        parent::setServiceLocator($serviceLocator);
        
        $config = $serviceLocator->get('config');
        $mailTo = $config['freemium']['mail_to'];

        $this->mailTo = $mailTo;

        return $this;
    }




    /**
    * This method will check if provided parametters are valid to create an instance.
    * Also it will check if there is a free database available.
    * No instance will be created at this point.
    * This method DO NOT check password because the password is asked to the user after email verification
    * 
    * @return bool
    */
    function canCreateInstance(Zend\Stdlib\Parameters $params)
    {
        // check required fields
        $fields = array(
            'email',
            'first_name',
            'last_name',
            'lang'
        );
        
        $this->hasEmptyValues($fields, $params);

        if($this->isSuccess())
        {
            $sl = $this->getServiceLocator();
        
            try
            {
                $dbClient = $sl->get('App\Db\Client');
                $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');
                $dbDatabase = $sl->get('App\Db\Database');
                $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');
                $config = $sl->get('config');
                
                $email = strtolower($params->email);
                $lang = $params->lang;
                
                // check if the email field looks like a real email address
                $vEmail = new Zend\Validator\EmailAddress();
                if(! $vEmail->isValid($email))
                {
                    $this->getCommunicator()->addError($this->_('provide_valid_email'), 'email');
                    return false;
                }
                else
                {
                    // check if already exist registered users with the given email address
                    if($dbClient->count(function($where) use($email) {
                        $where->equalTo('email', $email);
                    }))
                    {
                        $this->getCommunicator()->addError($this->_('user_email_already_exist'), 'email');
                        return false;
                    }
                }
                
                // all good so far, now we continue with the validations
                // Here we check stuff related to the domain and instance name
                $exploded = explode('@', $email);
                $exploded2 = explode('.', $exploded[1]);
                $instance = trim($exploded2[0]);
                
                if(! preg_match('/^[A-Za-z0-9\-]+$/', $instance))
                {
                    $this->getCommunicator()->addError($this->_('invalid_characters_instance_name'), 'instance');
                    return false;
                }
                
                
                // check if the domain of the email is allowed to create account
                $emailDomain = $exploded[1];
                if($dbBlacklistDomain->count(function($where) use($emailDomain) {
                    $where->equalTo('domain', $emailDomain);
                }))
                {
                    $this->getCommunicator()->addError($this->_('email_address_not_allowed'), 'email');
                    return false;
                }
                
                // check if the user can provide a custom instance name
                // Have in mind that paradiso people can provide instance names
                $topDomain = $config['freemium']['top_domain'];
                $domain = "$instance.$lang.$topDomain";
                
                // check if the domain name is good
                if(! $this->_isValidDomainName($domain))
                {
                    $this->getCommunicator()->addError($this->_('invalid_characters_instance_name'), 'instance');
                }
                
                // find a free database
                $rowDb = $dbDatabase->findFreeDatabase($lang);
                // ups, no free database found
                if(! $rowDb)
                {
                    $this->getCommunicator()->addError($this->_('unexpected_error'));
                    $this->_createDatabasesScript();
                    return false;
                }
            }
            catch(\Exception $e)
            {
                $this->setException($e);
                \App\NotifyError::notify($e);
            }
        }
        
        return $this->isSuccess();
    }


    /**
    *
    * This method will insert records in `client` table.
    * Also this method will sync tables as probably the database schema is not updated since the creation.
    * No instance will be created at this point.
    *
    * ############# IMPORTANT #############
    * Before calling this method you have to validate $params using canCreateInstance() method
    *
    * @return bool
    */
    function doCreateAccount(Zend\Stdlib\Parameters $params)
    {
        $sl = $this->getServiceLocator();

        try
        {
            $dbClient = $sl->get('App\Db\Client');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            $dbDatabase = $sl->get('App\Db\Database');
            $config = $sl->get('config');

            $exploded = explode('@', $params->email);
            $exploded2 = explode('.', $exploded[1]);
            $instance = trim($exploded2[0]);

            $lang = $params->lang;

            $topDomain = $config['freemium']['top_domain'];
            $domain = "{$instance}.$lang.$topDomain";


            // make sure the instance is unique
            $where = array();
            $where['domain = ?'] = $domain;
            while($dbClient->count($where))
            {
                $str .= mt_rand(1, 9000000);
                    
                $domain = "{$instance}{$str}.$lang.$topDomain";

                $where = array();
                $where['domain = ?'] = $domain;
            }

            //
            $data = array();
            $data['email'] = $params->email;
            $data['password'] = 'trial';
            $data['domain'] = $domain;
            $data['first_name'] = $params->first_name;
            $data['last_name'] = $params->last_name;
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['deleted'] = 0;
            $data['approved'] = 1;
            $data['approved_on'] = date('Y-m-d H:i:s');
            $data['email_verified'] = 0;
            $data['logo'] = '';
            $data['lang'] = $lang;
            $data['due_date'] = null;

            if($params->password)
            {
                $data['password'] = $params->password;
            }

            //
            $dbClient->doInsert($data);
            $clientId = $dbClient->getLastInsertValue();

            // assign database
            $db = $dbDatabase->findFreeDatabase($lang);
            $data = array(
                'client_id' => $clientId
                ,'database_id' => $db->id
            );

            $dbClientDatabase->doInsert($data);
            
            //
            $request = $sl->get('request');
            $uri = $request->getUri();
            $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}/services/instance/sync-database-and-notify/domain/$domain";

            // make a call to the method that will create the tables and notify the user
            $ch = curl_init();
 
            curl_setopt($ch, CURLOPT_URL, $serverUrl);
            #curl_setopt($ch, CURLOPT_USERPWD, "client:Laure1es");
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
             
            $content = curl_exec($ch);
            curl_close($ch);

            #echo $content;
            $this->getCommunicator()->setSuccess("User account created");
        }
        catch(\Exception $e)
        {
            $this->setException($e);
            \App\NotifyError::notify($e);
        }

        return $this->isSuccess();
    }



    /**
     *
     * @param Zend\Stdlib\Parameters $params
     * @var string email
     *
     * @return boolean
     */
    function verifyAccount(Zend\Stdlib\Parameters $params)
    {
        $vEmail = new Zend\Validator\EmailAddress();
        
        if(! $vEmail->isValid($params->email))
        {
            $this->getCommunicator()->addError($this->_('invalid_email_address'), 'email');
            return false;
        }
        
        $sl = $this->getServiceLocator();
        
        try
        {
            
            // lets look for the same email in the database
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            
            $client = $dbClient->findBy(function($where) use($params) {
                $where->equalTo('email', $params->email);
                $where->equalTo('deleted', 0);
                $where->equalTo('approved', 1);
                
            })->current();

            if(! $client)
            {
                $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                return false;
            }
            elseif($client->email_verified)
            {
                $this->getCommunicator()->addError($this->_('account_already_verified', array(
                    "http://{$client->domain}/logo.php" 
                )));
                return false;
            }
            else
            {
                $cPassword = new Com\Crypt\Password();
                if(! $cPassword->validate($client->email, $params->code))
                {
                    $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                    return false;
                }
            }
            
            //
            $databases = $dbDatabase->findDatabaseByClientId($client->id);
            if(!$databases->count())
            {
                $this->getCommunicator()->addError($this->_('unexpected_error'));
                return false;
            }

            // park domain
            $flag = $this->_parkDomain($client->domain);
            if(!$flag)
            {
                return false;
            }

            $lang = $client->lang;

            $config = $sl->get('config');
            $mDataMasterPath = $config['freemium']['path']['master_mdata'][$lang];
            $mDataPath = $config['freemium']['path']['mdata'];
            $configPath = $config['freemium']['path']['config'];
            $cpanelUser = $config['freemium']['cpanel']['username'];

            // mdata
            $newUmask = 0777;
            $oldUmask = umask($newUmask);

            if(! file_exists("$mDataPath/{$client->domain}"))
            {
                mkdir("$mDataPath/{$client->domain}", $newUmask, true);
            }
            
            chmod("$mDataPath/{$client->domain}", $newUmask);
            
            // Copying from master data folder
            exec("cp -Rf {$mDataMasterPath}/* {$mDataPath}/{$client->domain}/");
            
            // Changing owner for the data folder
            exec("chown -R {$cpanelUser}:{$cpanelUser} {$mDataPath}/{$client->domain} -R");
            exec("chmod 777 {$mDataPath}/{$client->domain} -R");

            // creating config file
            $database = $databases->current();

            $configStr = file_get_contents('data/config.template');
            $configStr = str_replace('{$dbHost}', $database->db_host, $configStr);
            $configStr = str_replace('{$dbName}', $database->db_name, $configStr);
            $configStr = str_replace('{$dbUser}', $database->db_user, $configStr);
            $configStr = str_replace('{$dbPassword}', $database->db_password, $configStr);
            $configStr = str_replace('{$domain}', $client->domain, $configStr);
            $configStr = str_replace('{$dataPath}', "{$mDataPath}/{$client->domain}", $configStr);

            $configFilename = "{$configPath}/{$client->domain}.php";
            $handlder = fopen($configFilename, 'w');
            fwrite($handlder, $configStr);
            fclose($handlder);
            
            exec("chown {$cpanelUser}:{$cpanelUser} $configFilename");
            exec("chmod 755 $configFilename");


            // database
            foreach ($databases as $database)
            {
                $this->_restoreData($database, $lang);
            }

            
            //
            $days = $config['freemium']['due_days'];

            $client->email_verified = 1;
            $client->email_verified_on = date('Y-m-d H:i:s');
            $client->due_date = date('Y-m-d', strtotime("+$days days"));
            
            $where = array();
            $where['id = ?'] = $client->id;
            
            $dbClient->doUpdate($client->toArray(), $where);
            
            //
            require_once 'vendor/3rdParty/moodle/moodlelib.php';
            require_once 'vendor/3rdParty/moodle/password.php';
            $password = hash_internal_user_password($client->password);

            foreach ($databases as $database)
            {
                $instanceAdapter = $this->_getAdapter($database->db_name, $database->db_host, $database->db_user, $database->db_password);

                $sql = "
                UPDATE mdl_user SET 
                    `username` = ?
                    ,`email` = ?
                    ,`password` = ?
                    ,`idnumber` = ?
                    ,`firstname` = ?
                    ,`lastname` = ?
                    ,`lang` = ?
                    ,`timecreated` = ?
                    ,`confirmed` = 1
                    ,`mnethostid` = 1
                WHERE `id` = 2
                ";

                $instanceAdapter->query($sql)->execute(array(
                      $client->email
                    , $client->email
                    , $password
                    , $client->email
                    , $client->first_name
                    , $client->last_name
                    , $client->lang
                    , time()
                ));


                // change the language
                $sql = "
                UPDATE mdl_config SET
                    `value` = ?
                WHERE `name` = 'lang'
                ";

                $instanceAdapter->query($sql)->execute(array(
                  $client->lang
                ));
            }
            
            $this->getCommunicator()->setSuccess($this->_('account_verified', array(
                "http://{$client->domain}/logo.php" 
            )));
            
        }
        catch(\Exception $e)
        {
            $this->setException($e);
            \App\NotifyError::notify($e);
        }
        
        return $this->isSuccess();
    }



    



    /**
    * This method will park the given domain if is not already set as client.instance_ready = 1
    * @return bool
    */
    protected function _parkDomain($domain)
    {
        $sl = $this->getServiceLocator();
        $dbClient = $sl->get('App\Db\Client');

        $cp = $sl->get('cPanelApi');

        $cpUser = $cp->get_user();
        $result = $cp->park($cpUser, $domain, null);
        
        $apiResponse = new App\Cpanel\ApiResponse($result);
        
        if($apiResponse->isError())
        {
            $err = $apiResponse->getError();
            if(stripos($err, 'already exists') !== false)
            {
                return true;
            }
            else
            {
                $this->getCommunicator()->addError($err);
                return false;
            }
        }

        return true;
    }



    protected function _restoreData($database, $lang)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        $dbName = $database->db_name;
        
        $username = $database->db_user;
        $password = $database->db_password;
        $host = $database->db_host;

        $cnn = mysqli_connect($host, $username, $password, $dbName);

        $folder = $config['freemium']['path']['master_freemium_data'][$lang];

        foreach(glob("$folder/*.sql") as $item)
        {
            if(0 == filesize($item))
            {
                continue;
            }

            $info = pathinfo($item);
            $tableName = str_replace('.sql', '', $info['basename']);

            $sql = "LOAD DATA LOCAL INFILE '$item' INTO TABLE `$tableName`";
            mysqli_query($cnn, $sql);
        }
    }



    protected function _createDatabasesScript()
    {
        // final step, lets run the cron
        $publicDir = PUBLIC_DIRECTORY;
        $coreDir = CORE_DIRECTORY;
        
        $command = "/usr/local/bin/php {$publicDir}/index.php create-databases > {$coreDir}/data/log/create-databases.cron.log 2>&1 &";
        shell_exec($command);
    }



    /**
    *
    * @return bool
    */
    protected function _isValidDomainName($domainName)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domainName) &&         // valid chars check
        preg_match("/^.{1,253}$/", $domainName) &&         // overall length check
        preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domainName)); // length of each label
    }


    /**
    *
    * @return Zend\Db\Adapter\Adapter
    */
    protected function _getAdapter($database, $host = null, $username = null, $password = null)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        if(empty($username))
        {
            $username = $config['freemium']['db']['user'];
        }
        
        if(empty($password))
        {
            $password = $config['freemium']['db']['password'];
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