<?php

namespace Backend\Controller;

use Zend, Com, App;


class BlacklistPhraseController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $this->assign('grid_title', "Phrases list");

        $grid = new App\DataGrid\BlacklistPhrase($sl, $this->viewVars);
        $view = $grid->render();

        $childView = new Zend\View\Model\ViewModel();
        $childView->setTemplate('backend/blacklist-phrase/list.phtml');
        
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
            $mBlacklistPhrase = $sl->get('App\Model\BlacklistPhrase');

            $flag = $mBlacklistPhrase->canSave($params);
            $com = $mBlacklistPhrase->getCommunicator();
            if($flag)
            {
                $flag = $mBlacklistPhrase->save($params);
                if($flag)
                {
                    $params->phrase = '';
                }
            }

            $this->setCommunicator($com);

            $this->assign($params);
        }

        $view = new Zend\View\Model\ViewModel($this->viewVars);
        $view->setTemplate('backend/blacklist-phrase/save.phtml');

        return $view;
    }

    function deleteAction()
    {
        $sl = $this->getServiceLocator();
        $id = $this->_params('id');

        $dbBlacklistPhrase = $sl->get('App\Db\BlacklistPhrase');

        $count = $dbBlacklistPhrase->doDelete(function($where) use($id){
            $where->equalTo('id', $id);
        });

        if($count)
        {
            $this->getCommunicator()->setSuccess("Phrase removed");
        }
        else
        {
            $this->getCommunicator()->setSuccess("No phrase was removed", true);
        }

        $this->saveCommunicator($this->getCommunicator());

        return $this->redirect()->toRoute('backend/wildcard', array(
            'controller' => 'blacklist-phrase'
            ,'action' => 'list'
        ));
    }
}