<?php

namespace Backend\Controller;

use Zend, Com, App;
use Zend\Dom\Document;


class SqlController extends Com\Controller\BackendController
{

    function indexAction()
    {
        $sl = $this->getServiceLocator();
        $request = $this->getRequest();
        $dsFormatter = new Com\DataSourceFormatter();
        
        $dbDatabase = $sl->get('App\Db\Database');
        $databases = $dbDatabase->findAllWithClientInfo();
        
        if($request->isPost())
        {
            $params = $request->getPost();
            $database = $params->database;
            $exploded = explode("\n", $params->query);
            $result = array();
         
            if(count($exploded))
            {
                foreach($exploded as $query)
                {
                    if(!empty($query))
                    {
                        if(empty($database))
                        {
                            foreach($databases as $item)
                            {
                                try
                                {
                                    $this->_execute($item->db_name, $query);
                                }
                                catch(\Exception $e)
                                {
                                    $result[] = array(
                                        'query' => $query . " - {$item->db_name}"
                                        ,'error' => $e->getMessage()
                                    );
                                }
                            }
                        }
                        else
                        {
                            try
                            {
                                $this->_execute($database, $query);
                            }
                            catch(\Exception $e)
                            {
                                $result[] = array(
                                    'query' => $query . " - {$item->db_name}"
                                    ,'error' => $e->getMessage()
                                );
                            }
                        }
                    }
                }
            }
            
            if($params->clear_cache)
            {
                foreach($databases as $item)
                {
                    if($item->domain)
                    {
                        $domain = str_replace(' - ', '', $item->domain);
                        if(!empty($database) && ($database == $item->db_name))
                        {
                            $this->_clearCache($item->db_name, $domain);
                        }
                        else
                        {
                            $this->_clearCache($item->db_name, $domain);
                        }
                    }
                }
            }
            
            $this->assign('result', $result);
            $this->assign('executed', 1);
            $this->assign($params);
        }

        //
        $textField = array('%db_name% %domain%', array('%db_name%' => 'db_name', '%domain%'=>'domain'));
        $valueField = 'db_name';
        
        $ds = $dsFormatter->setDatasource($databases)->toFormSelect($textField, $valueField);
        
        $this->assign('database_ds', $ds);
        
        return $this->viewVars;
    }


    /*
    function deleteTablesAction()
    {
        exit;
        $dbName = 'paradiso_bcp1';
        $adapter = $this->_getAdapter($dbName);

        $sql = "show tables";
        $rowset = $adapter->query($sql)->execute();

        $date = date('H:i:s');
        foreach($rowset as $row)
        {
            $tableName = current($row);

            // get the create table statement
            // and remove the AUTO_INCREMENT=$number from the statement
            $sql = "drop table `$tableName`";
            $adapter->query($sql)->execute();
        }

        $date2 = date('H:i:s');

        echo "Done
        <br>
        Stared At: $date 
        <br>
        Ended At: $date2
        ";
        exit;
    }
    */

    /*
    function createTablesAction()
    {
        exit;
        $dbName = 'paradiso_cp1';
        $adapter = $this->_getAdapter($dbName);

        $folder = $this->_params('folder');

        $unique = $folder;
        $corePath = CORE_DIRECTORY;
        $tmpPath = "{$corePath}/data/tmp";

        $dumpPath = "$tmpPath/dump";
        $uniquePath = "$dumpPath/$unique";
        $schema = "$uniquePath/schema";

        $date = date('H:i:s');
        foreach(glob("$schema/*.sql") as $item)
        {
            $sql = file_get_contents($item);
            $adapter->query($sql)->execute();
        }
        $date2 = date('H:i:s');

        echo "Done
        <br>
        Stared At: $date 
        <br>
        Ended At: $date2
        ";
        exit;
    }
    */


    /*
    function restoreDataAction()
    {
        exit;
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        $username = $config['freemium']['cpanel']['username'];
        $password = $config['freemium']['cpanel']['password'];
        $host = $config['freemium']['cpanel']['server'];
        $dbName = 'paradiso_cp1';
        #$db = new \PDO("mysql:host={$host};dbname=$dbName;charset=utf8", $username, $password);
        #mysql_connect($host, $username, $password);
        $cnn = mysqli_connect($host, $username, $password, $dbName);
        #mysql_select_db($dbName);

        $folder = $this->_params('folder');
        $adapter = $this->_getAdapter($dbName);

        $unique = $folder;
        $corePath = CORE_DIRECTORY;
        $tmpPath = "{$corePath}/data/tmp";

        $dumpPath = "$tmpPath/dump";
        $uniquePath = "$dumpPath/$unique";

        $folders = array(
             $dumpPath
            ,$uniquePath
            ,'schema' => "$uniquePath/schema"
            ,'data' => "$uniquePath/data"
        );

        try
        {
            $date = date('H:i:s');

            foreach(glob("{$folders['data']}/*.sql") as $item)
            {
                if(0 == filesize($item))
                {
                    continue;
                }

                $info = pathinfo($item);
                $tableName = str_replace('.sql', '', $info['basename']);


                $sql = <<<xxx

LOAD DATA LOCAL INFILE '$item' 
INTO TABLE `$tableName`

xxx;

                mysqli_query($cnn, $sql);
            }

            $date2 = date('H:i:s');

            echo "Done
            <br>
            Stared At: $date 
            <br>
            Ended At: $date2
            ";
        }
        catch(\Exception $e)
        {
            echo "<pre>";
            echo $e;
            echo "</pre>";
            exit;
        }

        exit;
    }
    */



    function dumpDatabaseAction()
    {
        ########### IMPORTANT ########### 
        ########### ########### ########### 
        # make sure you have granted FILE to user
        # GRANT FILE ON *.* TO 'asdfsdf'@'%';
        ########### ########### ########### 
        ########### ########### ########### 
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        try
        {
            // first lets cerate the folder to dump the data
            $unique = uniqid();
            $newUmask = 0777;
            $oldUmask = umask($newUmask);

            $corePath = CORE_DIRECTORY;
            $tmpPath = "{$corePath}/data/tmp";

            $dumpPath = "$tmpPath/dump";
            $uniquePath = "$dumpPath/$unique";

            $folders = array(
                 $dumpPath
                ,$uniquePath
                ,'schema' => "$uniquePath/schema"
                ,'data' => "$uniquePath/data"
            );

            foreach ($folders as $folder)
            {
                if(!file_exists($folder))
                {
                    mkdir($folder, $newUmask, true);
                    chmod($folder, $newUmask);
                }
            }
            

            // lest connect to the master database
            $adapter = $this->_getAdapter('paradiso_trial');

            // get all tables
            $sql = "show tables";
            $rowset = $adapter->query($sql)->execute();

            foreach($rowset as $row)
            {
                $tableName = current($row);

                // get the create table statement
                // and remove the AUTO_INCREMENT=$number from the statement
                $sql = "show create table `$tableName`";
                $result = $adapter->query($sql)->execute()->current();
                $statement = end($result);

                $statement = preg_replace('/AUTO_INCREMENT=\d+ /', '', $statement);

                $path = "{$folders['schema']}/{$tableName}.sql";
                file_put_contents($path, $statement);
                chmod($path, $newUmask);

                // export data
                $path = "{$folders['data']}/{$tableName}.sql";

                $sql = <<<xxx
                SELECT * INTO OUTFILE '$path'
                FROM `$tableName`
xxx;
               
                $adapter->query($sql)->execute();
            }

            # echo "<a target='_blank' href='/backend/sql/delete-tables/folder/$unique'>Delete tables</a> - $unique";
            # echo "<br>";
            # echo "<a target='_blank' href='/backend/sql/create-tables/folder/$unique'>Create tables</a> - $unique";
            # echo "<br>";
            # echo "<a target='_blank' href='/backend/sql/restore-data/folder/$unique'>Restore data</a> - $unique";
            # exit;

            if($rowset->count())
            {
                // compress
                $command = "zip -r {$uniquePath}.zip $uniquePath/";
                shell_exec($command);
                chmod("{$uniquePath}.zip", $newUmask);

                // delete the folder
                $command = "rm $uniquePath/ -rf";
                shell_exec($command);

                // donwload the file
                $response = new \Zend\Http\Response\Stream();
                $response->setStream(fopen("{$uniquePath}.zip", 'r'));
                $response->setStatusCode(200);

                $date = date('Y-m-d_H.i.s');
                $fileName = "master.{$date}.sql.zip";
                $headers = new \Zend\Http\Headers();
                $headers->addHeaderLine('Content-Type', 'whatever your content type is')
                    ->addHeaderLine('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                    ->addHeaderLine('Content-Length', filesize("{$uniquePath}.zip"));

                $response->setHeaders($headers);
                return $response;
            }
            else
            {
                echo "There are no records";
            }
        }
        catch(\Exception $e)
        {
            echo "<pre>";
            echo $e;
            echo "</pre>";
            exit;
        }

        exit;
    }
    
    
    protected function _clearCache($token, $domain)
    {
        $client = new App\Lms\Services\Client();
        $client->setServicesToken($token);
        
        $client->setServerUri("http://{$domain}/services/index.php");
        $response = $client->request('purge_cache');
    }
    
    
    protected function _execute($database, $query)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        $username = $config['freemium']['cpanel']['username'];
        $password = $config['freemium']['cpanel']['password'];
        $host = $config['freemium']['db']['host'];
        
        //
        $config = array(
            'driver' => 'mysqli',
            'database' => $database,
            'username' => $username,
            'password' => $password, //
            'hostname' => $host,
            'profiler' => false,
            'charset' => 'UTF8',
            'options' => array(
                'buffer_results' => true 
            ) 
        );
        
        $adapter = new Zend\Db\Adapter\Adapter($config);
        
        $driver = $adapter->getDriver();
        $connection = $driver->getConnection();
        
        $connection->execute($query);
    }



    function _getAdapter($database)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        
        $username = $config['freemium']['cpanel']['username'];
        $password = $config['freemium']['cpanel']['password'];
        $host = $config['freemium']['cpanel']['server'];

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