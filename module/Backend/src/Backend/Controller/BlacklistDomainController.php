<?php

namespace Backend\Controller;

use Zend, Com, App;


class BlacklistDomainController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $this->assign('grid_title', "Domain list");

        $grid = new App\DataGrid\BlacklistDomain($sl, $this->viewVars);
        $view = $grid->render();

        $childView = new Zend\View\Model\ViewModel();
        $childView->setTemplate('backend/blacklist-domain/list.phtml');
        
        $view->addChild($childView, 'after_title', true);
        
        return $view;
    }


    function addAction()
    {
        $this->layout('layout/blank');

        $sl = $this->getServiceLocator();
        $request = $this->getRequest();

        if($request->isPost())
        {
            $params = $request->getPost();
            $mBlacklistDomain = $sl->get('App\Model\BlacklistDomain');

            $flag = $mBlacklistDomain->canSave($params);
            $com = $mBlacklistDomain->getCommunicator();
            if($flag)
            {
                $mBlacklistDomain->save($params);
            }
            

            $this->setCommunicator($com);

            $this->assign($params);
        }

        $view = new Zend\View\Model\ViewModel($this->viewVars);
        $view->setTemplate('backend/blacklist-domain/save.phtml');

        return $view;
    }

    function deleteAction()
    {
        $sl = $this->getServiceLocator();
        $id = $this->_params('id');

        $mBlacklistDomain = $sl->get('App\Db\BlacklistDomain');

        $count = $mBlacklistDomain->doDelete(function($where) use($id){
            $where->equalTo('id', $id);
        });

        if($count)
        {
            $this->getCommunicator()->setSuccess("Domain removed");
        }
        else
        {
            $this->getCommunicator()->setSuccess("No domain was removed", true);
        }

        $this->saveCommunicator($this->getCommunicator());

        return $this->redirect()->toRoute('backend/wildcard', array(
            'controller' => 'blacklist-domain'
            ,'action' => 'list'
        ));
    }
}