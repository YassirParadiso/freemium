<?php
namespace App\Model;

use Com, Zend;
use Zend\Form\Element\Email;

class BlacklistPhrase extends Com\Model\AbstractModel
{

    function canSave(Zend\Stdlib\Parameters $params)
    {
        $fields = array(
            'phrase',
        );

        $params->phrase = trim($params->phrase);
        
        if($this->hasEmptyValues($fields, $params))
        {
            return false;
        }

        $sl = $this->getServiceLocator();

        try
        {
            $dbBlacklistPhrase = $sl->get('App\Db\BlacklistPhrase');

            $phrase = $params->phrase;
            $id = $params->id;

            $count = $dbBlacklistPhrase->count(function($where) use($phrase, $id) {
                $where->equalTo('phrase', $phrase);
                if($id)
                {
                    $where->notEqualTo('id', $id);
                }
            });

            if($count)
            {
                $this->getCommunicator()->addError('Phrase already exists', 'phrase');
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
            $dbBlacklistPhrase = $sl->get('App\Db\BlacklistPhrase');

            $data = array(
                'phrase' => trim($params->phrase)
            );

            if($params->id)
            {

                $where = array(
                    'id = ?' => $params->id
                );

                $dbBlacklistPhrase->doUpdate($data, $where);
            }
            else
            {
                $dbBlacklistPhrase->doInsert($data);
            }

            $this->getCommunicator()->setSuccess('Phrase added');
            
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }

        return $this->isSuccess();
    }
}
