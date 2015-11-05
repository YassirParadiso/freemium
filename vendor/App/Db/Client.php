<?php

namespace App\Db;

use Com, Zend;

class Client extends Com\Db\AbstractDb
{
    /**
     *
     * @var string
     */
    protected $tableName = 'client';
    
    
    /**
    *
    * @return int
    */
    function countByDomain($domain)
    {
        $where = array();
        $where['domain = ?'] = $domain;
        
        return $this->count($where);
    }
    
    
    
    function findByDomain($domain)
    {
        $where = array();
        $where['domain = ?'] = $domain;
        
        return $this->findBy($where);
    }
}