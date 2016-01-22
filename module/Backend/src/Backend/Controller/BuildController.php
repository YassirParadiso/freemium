<?php

namespace Backend\Controller;

use Zend, Com, App;


class BuildController extends Com\Controller\BackendController
{
    


    function indexAction()
    {
        $this->loadCommunicator();

        $sl = $this->getServiceLocator();
        $config = $sl->get('config');

        $this->chdirRepo();
        $tags = $this->getTags();

        $output = array();

        $request = $this->getRequest();

        if($request->isPost())
        {
            if($this->checkParams() !== true)
                return;

            $major = $request->getPost('major', null);
            $nextVersion = $this->generateNextVersion($major, $tags);

            if($this->createNewTag($nextVersion) !== true)
                return;
        }

        $keys = array_keys($config['freemium']['builds_type']);
        $dsBuildType = array_combine($keys, $keys);
        
        $this->assign('build_type_ds', $dsBuildType);
        
        //
        $instance_ds = array(
            'qaen.paradisolms.net' => 'qaen.paradisolms.net'
        );
        $this->assign('instance_ds', $instance_ds);

        //
        $this->assign('builds', $tags);

        return $this->viewVars;
    }


    function checkoutAction()
    {
        set_time_limit(0);

        $sl = $this->getServiceLocator();
        $config = $sl->get('config');

        $params = $this->params();

        $this->chdirRepo();

        $build = $params->fromPost('build');
        $instance = $params->fromPost('instance');

        $updateDatabase = $params->fromPost('database');
        $updateMdata = $params->fromPost('mdata');

        if(!$build)
        {
            $this->getCommunicator()->addError("Please choose a build", 'build');
            $this->saveCommunicator($this->getCommunicator());
            return $this->_redirect();
        }

        $tags = $this->getTags();
        $found = false;
        foreach ($tags as $item)
        {
            if($item == $build)
            {
                $found = true;
                break;
            }
        }

        if(!$found)
        {
            $this->getCommunicator()->addError("Unable to find the build $build", 'build');
            $this->saveCommunicator($this->getCommunicator());
            return $this->_redirect();
        }

        if(!$instance)
        {
            $this->getCommunicator()->addError("Please choose an instance", 'instance');
            $this->saveCommunicator($this->getCommunicator());
            return $this->_redirect();
        }

        if(!isset($config['freemium']['instances'][$instance]))
        {
            $this->getCommunicator()->addError("Unable to find configuration for the instance $instance", 'instance');
            $this->saveCommunicator($this->getCommunicator());
            return $this->_redirect();
        }

        //
        if($updateDatabase)
        {
            // we need to empty the database
            $dbDatabase = $sl->get('App\Db\Database');
            $rowset = $dbDatabase->findDatabaseByDomain($instance);

            $row = $rowset->current();
            if(!$row)
            {
                $this->getCommunicator()->addError("Unable to find database configuration for the instance $instance", 'instance');
                $this->saveCommunicator($this->getCommunicator());
                return $this->_redirect();
            }

            $instanceAdapter = new Zend\Db\Adapter\Adapter(array(
                'driver' => 'mysqli',
                'database' => $row->db_name,
                'username' => $row->db_user,
                'password' => $row->db_password,
                'hostname' => $row->db_host,
                'profiler' => true,
                'charset' => 'UTF8',
                'options' => array(
                    'buffer_results' => true 
                ) 
            ));
        }


        //

        $repoPath = $config['freemium']['instances'][$instance]['repo_path'];
        $mdataPath = $config['freemium']['instances'][$instance]['mdata_path'];
        $mdataSource = $config['freemium']['instances'][$instance]['mdata_source']($config);
        $schemaSource = $config['freemium']['instances'][$instance]['db_schema_source']($config);
        $dataSource = $config['freemium']['instances'][$instance]['db_data_source']($config);

        // checkout to the tag
        $output = array();
        $retVal = -1;
        exec("git checkout $build 2>&1", $output, $retVal);
        if($retVal !== 0)
        {
            $msg = implode('<br>', $output);
            $this->getcommunicator()->addError($msg);
            $this->saveCommunicator($this->getcommunicator());
            return $this->_redirect();
        }

        if($updateDatabase)
        {
            # delete all tables
            $sql = "SHOW TABLES";
            $rowset = $instanceAdapter->query($sql)->execute();
            $instanceTables = array();
            foreach ($rowset as $row2)
            {
                $tabelName = current($row2);

                $sql = "DROP TABLE `$tabelName`";
                $instanceAdapter->query($sql)->execute();
            }

            # get the list of tables from the path and create
            $currentTables = array();
            foreach(glob("$schemaSource/*.sql") as $item)
            {
                $sql = file_get_contents($item);
                $instanceAdapter->query($sql)->execute();
            }

            # here we have to connect using standard mysqli library, Zend adapter doesn't support yet LOAD DATA command
            $cnn = mysqli_connect($row->db_host, $row->db_user, $row->db_password, $row->db_name);
            foreach(glob("$dataSource/*.sql") as $item)
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

        //
        if($updateMdata)
        {
            # delete mdata files first
            $output = array();
            $retVal = -1;
            exec("rm $mdataPath/* -rf 2>&1", $output, $retVal);

            # copy mdata files
            $output = array();
            $retVal = -1;
            exec("cp $mdataSource/* -R $mdataPath/ 2>&1", $output, $retVal);

            # chmod mdata
            $output = array();
            $retVal = -1;
            exec("chmod 777 $mdataPath/ -R 2>&1", $output, $retVal);

            // remove unwanted folders
            $toRemove = array(
                "$mdataPath/cache/",
                "$mdataPath/localcache/",
                "$mdataPath/sessions/",
                "$mdataPath/temp/",
                "$mdataPath/trashdir/",
            );

            foreach ($toRemove as $pathToRemove)
            {
                $command = "rm $pathToRemove -rf";
                exec($command);
            }
        }

        //
        $this->getCommunicator()->setSuccess("Done. <strong>$instance</strong> instance is using <strong>$build</strong>", '');
        $this->saveCommunicator($this->getCommunicator());
        return $this->_redirect();
    }



    function generateAction()
    {
        set_time_limit(0);

        $request = $this->getRequest();

        $this->chdirRepo();
        $tags = $this->getTags();

        if($request->isPost())
        {
            if($this->checkParams() !== true)
                return;

            $major = $request->getPost('major', null);
            $nextVersion = $this->generateNextVersion($major, $tags);

            if($this->createNewTag($nextVersion) !== true)
                return;

            $this->getCommunicator()->setSuccess("New build $nextVersion generated");
            $this->saveCommunicator($this->getCommunicator());
        }

        $this->_redirect();
    }


    function createNewTag($nextVersion)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
        $keys = $config['freemium']['builds_type'];

        $request = $this->getRequest();
        $buildType = $request->getPost('build_type', null);

        $commands = $keys[$buildType];
        foreach ($commands as $command)
        {
            $command = str_replace('{{tag}}', $nextVersion, $command);
            $command = "$command 2>&1";

            $output = array();
            $retVal = -1;

            exec($command, $output, $retVal);
            if($retVal !== 0)
            {
                $msg = implode('<br>', $output);
                $this->getcommunicator()->addError($msg);
                $this->saveCommunicator($this->getcommunicator());
                return $this->_redirect();
            }
        }

        $commands = array(
            # first we need to reset the repo 
            ######3'git reset --hard 2>&1',

            # chage to the dev branch
            ######3'git checkout dev 2>&1',

            # get the latest changes in dev branch
            ######3'git pull 2>&1',

            # go to the build branch 
            ######3'git checkout build 2>&1',

            # get the latest changes in build branch
            ######3'git pull 2>&1',

            # merge with dev into build branch
            ######3'git merge dev 2>&1',

            # push to remote server
            ######3'git push -u origin build 2>&1',

            # create the tag
            ######3"git tag $nextVersion 2>&1",

            # send tag to the server
            ######3'git push --tags 2>&1',
        );

        return true;
    }



    function chdirRepo()
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');

        $repoPath = $config['freemium']['build_repo_path'];

        chdir($repoPath);
    }


    function getTags()
    {
        $output = array();
        exec('git fetch --prune 2>&1', $output);

        $tags = array();
        exec('git tag 2>&1', $tags);
       
        $tags = array_reverse($tags);

        return $tags;
    }


    function generateNextVersion($major, $tags)
    {
        $next = "v{$major}";
        $currentRevision = -1;
        foreach ($tags as $tag)
        {
            if('v' == substr($tag, 0, 1))
            {
                $numericPart = substr($tag, 1);
                $exploded = explode('.', $numericPart);
                if(3 == count($exploded))
                {
                    $majorToCheck = "{$exploded[0]}.{$exploded[1]}";
                    $revision = (int)$exploded[2];

                    if($major == $majorToCheck)
                    {
                        if($currentRevision == -1 || $currentRevision < $revision)
                            $currentRevision = $revision;
                    }
                }
            }
        }

        $currentRevision++;

        if(strlen($currentRevision) == 1)
            $currentRevision = "0$currentRevision";

        return "{$next}.{$currentRevision}";
    }



    function checkParams()
    {
        $request = $this->getRequest();
        $major = $request->getPost('major', null);
        $buildType = $request->getPost('build_type', null);

        if(empty($major))
        {
            $this->getcommunicator()->addError('Please provide a value', 'major');
            $this->saveCommunicator($this->getcommunicator());
            $this->_redirect();
        }
        else
        {
            $pattern = '/^[0-9]+.[0-9]+$/';
            if(!preg_match($pattern, $major))
            {
                $this->getcommunicator()->addError('Please provide a valid version', 'major');
                $this->saveCommunicator($this->getcommunicator());
                return $this->_redirect();
            }
        }

        if(empty($buildType))
        {
            $this->getcommunicator()->addError('Please choose a build type', 'build_type');
            $this->saveCommunicator($this->getcommunicator());
            return $this->_redirect();
        }
        else
        {
            $sl = $this->getServiceLocator();
            $config = $sl->get('config');
            $keys = array_keys($config['freemium']['builds_type']);
            if(!in_array($buildType, $keys))
            {
                $this->getcommunicator()->addError('Please choose a build type', 'build_type');
                $this->saveCommunicator($this->getcommunicator());
                return $this->_redirect();
            }
        }

        return true;
    }



    function _redirect()
    {
        $urlParams = array(
            'controller' => 'build'
            ,'action' => 'index'
        );
        $url = $this->url()->fromRoute('backend/wildcard', $urlParams);

        return $this->redirect()->toUrl($url);
    }
}