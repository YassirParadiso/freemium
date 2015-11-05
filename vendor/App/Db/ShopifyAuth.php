<?php

namespace App\Db;

use Com, Zend;


class ShopifyAuth extends Com\Db\AbstractDb
{

    /**
     *
     * @var string
     */
    protected $tableName = 'shopify_auth';


    /**
     *
     * @param string $store
     * @return Com\Entity\Record | bool
     */
    function findByStore($store)
    {
        $where = new Zend\Db\Sql\Predicate\Operator('store', '=', $store);
        $row = $this->findBy($where)->current();
        
        return $row;
    }
}
