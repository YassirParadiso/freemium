<?php
namespace App\Model;

use Com, Zend;
use Zend\Form\Element\Email;

class BlacklistDomain extends Com\Model\AbstractModel
{

    function canSave(Zend\Stdlib\Parameters $params)
    {
        $fields = array(
            'domain',
        );

        $params->domain = trim($params->domain);
        
        if($this->hasEmptyValues($fields, $params))
        {
            return false;
        }

        $sl = $this->getServiceLocator();

        try
        {
            $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');

            $domain = $params->domain;
            $id = $params->id;

            $count = $dbBlacklistDomain->count(function($where) use($domain, $id) {
                $where->equalTo('domain', $domain);
                if($id)
                {
                    $where->notEqualTo('id', $id);
                }
            });

            if($count)
            {
                $this->getCommunicator()->addError('Domain name already exists', 'domain');
                return false;
            }
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }

        return $this->isSuccess();
    }


    function save(Zend\Stdlib\Parameters $params)
    {
        $sl = $this->getServiceLocator();

        try
        {
            $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');

            $data = array(
                'domain' => trim($params->domain)
            );

            if($params->id)
            {

                $where = array(
                    'id = ?' => $params->id
                );

                $dbBlacklistDomain->doUpdate($data, $where);
            }
            else
            {
                $dbBlacklistDomain->doInsert($data);
            }

            $this->getCommunicator()->setSuccess('Domain name added');
            
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }

        return $this->isSuccess();
    }
}
