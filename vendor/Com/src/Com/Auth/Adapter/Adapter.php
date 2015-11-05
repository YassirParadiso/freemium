<?php

namespace Com\Auth\Adapter;

use Zend;
use Com;


class Adapter extends Zend\Authentication\Adapter\AbstractAdapter
{

    /**
     *
     * @var \Com\Db\AbstractDb
     *
     */
    protected $dbTable;


    /**
     *
     * @param \Com\Db\AbstractDb $dbTable
     * @return Authentication
     */
    function setDbTable(\Com\Db\AbstractDb $dbTable)
    {
        $this->dbTable = $dbTable;
        return $this;
    }


    /**
     *
     * @return \Com\Db\AbstractDb | null
     */
    function getDbTable()
    {
        return $this->dbTable;
    }


    /**
     * Performs an authentication attempt
     * 
     * @see https://app.asana.com/0/14725105905099/14983561213443
     *
     * @return \Zend\Authentication\Result
     */
    function authenticate()
    {
        $found = null;
        $identity = $this->getIdentity();
        $credential = $this->getCredential();
        $dbTable = $this->getDbTable();
        
        if(! $dbTable)
        {
            throw new \Exception('DbTable not set', 1);
        }
        
        $where['email = ?'] = $identity;
        $where['status = ?'] = 'enabled';
        
        try
        {
            $row = $dbTable->findBy($where)->current();
            
            if($row)
            {
                $code = Zend\Authentication\Result::FAILURE_CREDENTIAL_INVALID; // -3
                
                $password = new Com\Crypt\Password();
                if($password->validate($credential, $row->password))
                {
                    $code = Zend\Authentication\Result::SUCCESS; // 1
                }
            }
            else
            {
                $code = Zend\Authentication\Result::FAILURE_IDENTITY_NOT_FOUND; // -1
            }
        }
        catch(\Exception $e)
        {
            $code = Zend\Authentication\Result::FAILURE; // 0
        }
        
        if(Zend\Authentication\Result::SUCCESS == $code)
        {
            $found = $row->toArray();
        }
        
        return new Zend\Authentication\Result($code, $found);
    }
}