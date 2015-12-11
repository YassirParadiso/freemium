<?php

namespace App\Db;

use Com, Zend;

class Database extends Com\Db\AbstractDb
{
    /**
     *
     * @var string
     */
    protected $tableName = 'database';
    
    
    
    /**
    *
    * @return int
    */
    function countFree($lang = null)
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        $count = new Zend\Db\Sql\Literal('COUNT(*) AS c');
        $select->columns(array($count));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array(), 'left');

        $select->where(function($where) use($lang) {
            $where->isNull('chd.client_id');

            if(!empty($lang))
            {
                $where->equalTo('lang', $lang);
            }
        });

        // $this->debugSql($select);
        
        //
        return $this->executeCustomSelect($select)->current()->c;
    }
    
    
    /**
    *
    * @return Com\Entity\Record | null
    */
    function findFreeDatabase($lang = null)
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array(), 'LEFT');

        //
        $select->where(function($where) use($lang) {
            $where->isNull('chd.client_id');

            if(!empty($lang))
            {
                $where->equalTo('lang', $lang);
            }
        });

        $select->order('d.id ASC');

        //
        $select->limit(1);
        
        //
        return $this->executeCustomSelect($select)->current();
    }
    
    
    function findDatabaseByClientId($clientId)
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array());

        //
        $where = array();
        $where['chd.client_id = ?'] = $clientId;
        $select->where($where);
        
        //
        return $this->executeCustomSelect($select);
    }
    
    
    function findAllWithClientInfo()
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');
        $dbClient = $sl->get('App\Db\Client');

        
        $literal = function($str)
        {
            return new Zend\Db\Sql\Literal($str);
        };
        
        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));
        
        //
        $cols = array();
        $cols['db_name'] = $literal('d.db_name');
        $cols['domain'] = $literal("IF(ISNULL(c.domain), '', CONCAT(' - ', c.domain))");
        $cols['email'] = $literal('c.email');
        $cols['first_name'] = $literal('c.first_name');
        $cols['last_name'] = $literal('c.last_name');

        $select->columns($cols);
        
        //
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array(), 'LEFT');
        
        //
        $select->join(array('c' => $dbClient->getTable()), 'c.id = chd.client_id', array(), 'LEFT');
        
        //
        $select->order('d.id ASC');
        
        //
        $select->where('c.deleted = 0 OR c.deleted IS NULL');
        
        //
        #$this->debugSql($select);
        
        //
        return $this->executeCustomSelect($select);
    }
}