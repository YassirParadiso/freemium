<?php

namespace App\DataGrid;

use Com, Zend, ZfcDatagrid, App;


class Client extends Com\DataGrid\AbstractDataGrid
{


    function setupColumns()
    {
        $obj = $this;
        
        $formatter = new Com\DataGrid\Column\Formatter\Custom(function ($column, $row) use($obj)
        {
            $urlInfo = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'instance',
                'action' => 'info',
                'id' => $row['c_id']
            ));
            
            $urlDelete = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'instance',
                'action' => 'delete',
                'domain' => $row['c_domain']
            ));
            
            $text_return = <<<xxx
<div class="btn-group">
    <a href="$urlInfo" class="iframe btn btn-primary btn-xs info">Info</a>
    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
        <span class="caret"></span>
    </button>
    <ul class="dropdown-menu" role="menu">
        <li><a data-users="{$row['count']}" class="delete" href="$urlDelete">Delete</a></li>
    </ul>
</div>
xxx;

            return $text_return;
        });
        
        // 
        $col = new Com\DataGrid\Column\Action();
        $col->setFormatter($formatter);
        $col->setLabel('Actions');
        $col->setWidth(1);
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('id', 'c');
        $col->setIdentity();
        $col->setSortDefault(1, 'DESC');
        $this->addColumn($col);
        
        {// hidden columsn
            // 
            $col = new ZfcDatagrid\Column\Select('db_host', 'd');
            $col->setHidden();
            $this->addColumn($col);
            
            // 
            $col = new ZfcDatagrid\Column\Select('email_verified_on', 'c');
            $col->setHidden();
            $this->addColumn($col);
            
            // 
            $col = new ZfcDatagrid\Column\Select('db_name', 'd');
            $col->setHidden();
            $this->addColumn($col);
            
            //
            $col = new ZfcDatagrid\Column\Select('created_on', 'c');
            $col->setHidden();
            $this->addColumn($col);
            
            //
            $literal = new Zend\Db\Sql\Literal('COUNT(*)');
            $col = new ZfcDatagrid\Column\Select($literal, 'count');
            $col->setHidden();
            $this->addColumn($col);
        }
        
        
        // 
        $col = new ZfcDatagrid\Column\Select('first_name', 'c');
        $col->setLabel('First name');
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('last_name', 'c');
        $col->setLabel('Last name');
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('email', 'c');
        $col->setLabel('Email');
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('lang', 'c');
        $col->setLabel('Lang');
        $this->addColumn($col);
        
        // 
        $formatter = new App\DataGrid\Client\Formatter\Verified();
        $options = array('1' => 'Yes', '0' => 'No');
        
        $col = new ZfcDatagrid\Column\Select('email_verified', 'c');
        $col->setLabel('Verified');
        $col->setFilterSelectOptions($options);
        $col->setFormatter($formatter);
        $this->addColumn($col);
        
        
        //
        $formatter = new App\DataGrid\Client\Formatter\Instance();
        
        $col = new ZfcDatagrid\Column\Select('domain', 'c');
        $col->setLabel('Instance');
        $col->setFormatter($formatter);
        $this->addColumn($col);
        
    }


    function setupDataSource()
    {
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        $dbHasDatabase = $sl->get('App\Db\Client\HasDatabase');
        $dbDatabase = $sl->get('App\Db\Database');
        
        $select = new Zend\Db\Sql\Select();
        
        $select->from(array(
            'c' => $dbClient->getTable() 
        ));
        
        //
        $select->join(array('chd' => $dbHasDatabase->getTable()), 'chd.client_id = c.id', array(), 'left');
        
        //
        $select->join(array('d' => $dbDatabase->getTable()), 'd.id = chd.database_id', array(), 'left');
        
        //
        $select->where(array('deleted' => 0, 'approved' => 1));
        
        //
        $select->group('c.domain');

        //
        $this->dataSource = $select;
    }
}
