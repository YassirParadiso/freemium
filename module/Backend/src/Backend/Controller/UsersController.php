<?php

namespace Backend\Controller;

use Zend, Com, App;


class UsersController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $this->assign('grid_title', "Users");

        $grid = new App\DataGrid\Users($sl, $this->viewVars);
        $view = $grid->render();

        $childView = new Zend\View\Model\ViewModel();
        $childView->setTemplate('backend/users/list.phtml');
        
        $view->addChild($childView, 'after_title', true);
        
        return $view;
    }


    function updateStatusAction()
    {
        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $params = $this->params();

        $id = $params->fromRoute('id');
        $status = $params->fromRoute('status');

        $dbUser = $sl->get('App\Db\User');

        $row = $dbUser->findByPrimarykey($id);

        if($row)
        {
            if(1 == $status)
            {
                $row->status = 'enabled';
            }
            else
            {
                $row->status = 'disabled';
            }

            $data = $row->getArrayCopy();
            $dbUser->doUpdate($data, function($where) use($id) {
                $where->equalTo('id', $id);
            });

            $com->setSuccess('User updated');
        }
        else
        {
            $com->addError('Unable to find the user');
        }

        $this->saveCommunicator($com);

        return $this->redirect()->toRoute('backend/wildcard', array(
            'controller' => 'users'
            ,'action' => 'list'
        ));
    }


    function updateInfoAction()
    {
        $this->loadCommunicator();

        $request = $this->getRequest();
        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $params = $this->params();

        $id = $params->fromRoute('id');

        $dbUser = $sl->get('App\Db\User');

        $row = $dbUser->findByPrimarykey($id);

        if($row)
        {
            $this->assign($row);

            if($request->isPost())
            {
                $isOk = true;

                $post = $request->getPost();

                $this->assign($post);

                $e = new Zend\Validator\EmailAddress();
                if(!$e->isValid($post->email))
                {
                    $com->addError('Please provide a valid email address', 'email');
                }

                $email = $post->email;

                if($dbUser->count(function($where) use($email, $id) {
                    $where->equalTo('email', $email);
                    $where->notEqualTo('id', $id);
                }))
                {
                    $com->addError('The provided email address is in use', 'email');
                }

                if($com->isSuccess())
                {
                    $com->setSuccess('User information updated');
                    $this->saveCommunicator($com);

                    $row->email = $post->email;
                    $row->first_name = $post->first_name;
                    $row->last_name = $post->last_name;

                    $data = $row->getArrayCopy();

                    $dbUser->doUpdate($data, function($where) use($id) {
                        $where->equalTo('id', $id);
                    });

                    return $this->redirect()->refresh();
                }
            }
        }
        else
        {
            $com->addError('Unable to find the user');

            $this->saveCommunicator($com);

            return $this->redirect()->toRoute('backend/wildcard', array(
                'controller' => 'users'
                ,'action' => 'list'
            ));
        }

        return $this->viewVars;
    }



    function updatePasswordAction()
    {
        $this->loadCommunicator();

        $request = $this->getRequest();
        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $params = $this->params();

        $id = $params->fromRoute('id');

        $dbUser = $sl->get('App\Db\User');

        $row = $dbUser->findByPrimarykey($id);

        if($row)
        {
            $this->assign($row);

            if($request->isPost())
            {
                $isOk = true;

                $post = $request->getPost();
                $password = trim($post->password);

                if(empty($password))
                {
                    $com->addError('Please provide a password', 'password');
                }

                if($com->isSuccess())
                {
                    $com->setSuccess('User password updated');
                    $this->saveCommunicator($com);

                    $p = new Com\Crypt\Password();

                    $row->password = $p->encode($password);
                    $data = $row->getArrayCopy();

                    $dbUser->doUpdate($data, function($where) use($id) {
                        $where->equalTo('id', $id);
                    });

                    return $this->redirect()->refresh();
                }
            }
        }
        else
        {
            $com->addError('Unable to find the user');

            $this->saveCommunicator($com);

            return $this->redirect()->toRoute('backend/wildcard', array(
                'controller' => 'users'
                ,'action' => 'list'
            ));
        }


        return $this->viewVars;
    }



    function addAction()
    {
        $this->loadCommunicator();

        $request = $this->getRequest();
        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $params = $this->params();

        $dbUser = $sl->get('App\Db\User');
        if($request->isPost())
        {
            $isOk = true;

            $post = $request->getPost();

            $this->assign($post);

            $e = new Zend\Validator\EmailAddress();
            if(!$e->isValid($post->email))
            {
                $com->addError('Please provide a valid email address', 'email');
            }

            $email = $post->email;

            if($dbUser->count(function($where) use($email) {
                $where->equalTo('email', $email);
            }))
            {
                $com->addError('The provided email address is in use', 'email');
            }

            if($com->isSuccess())
            {
                $com->setSuccess('User account created');
                $this->saveCommunicator($com);

                $data = array();
                $data['email'] = $post->email;
                $data['first_name'] = $post->first_name;
                $data['last_name'] = $post->last_name;

                $dbUser->doInsert($data);

                return $this->redirect()->refresh();
            }
        }
        

        return $this->viewVars;
    }
}