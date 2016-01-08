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
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');

        $params = $this->params();

        $this->chdirRepo();

        $build = $params->fromPost('build');
        $instance = $params->fromPost('instance');

        $database = $params->fromPost('database');
        $mdata = $params->fromPost('mdata');

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

        $repoPath = $config['freemium']['instances'][$instance]['repo_path'];
        $mdataPath = $config['freemium']['instances'][$instance]['mdata_path'];
        $mdataSource = $config['freemium']['instances'][$instance]['mdata_source']($config);
        $schemaSource = $config['freemium']['instances'][$instance]['db_schema_source']($config);
        $dataSource = $config['freemium']['instances'][$instance]['db_data_source']($config);

        // we need to empty the database
        $dbDatabase = $sl->get('App\Db\Database');
        $rowset = $dbDatabase->findDatabaseByDomain($instance);

        echo $rowset->count();


        exit;
        return $this->_redirect();
    }



    function generateAction()
    {
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
        $commands = array(
            # first we need to reset the repo 
            'git reset --hard 2>&1',

            # chage to the dev branch
            'git checkout dev 2>&1',

            # get the latest changes in dev branch
            'git pull 2>&1',

            # go to the build branch 
            'git checkout build 2>&1',

            # get the latest changes in build branch
            'git pull 2>&1',

            # merge with dev into build branch
            'git merge dev 2>&1',

            # push to remote server
            'git push -u origin build 2>&1',

            # create the tag
            "git tag $nextVersion 2>&1",

            # send tag to the server
            'git push --tags 2>&1',
        );

        foreach ($commands as $command)
        {
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
        $tags = array();
        exec('git fetch --prune');
        exec('git tag', $tags);

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