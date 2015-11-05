<?php

namespace App\DataGrid;

use Com, Zend, ZfcDatagrid, App;


class Approval extends Com\DataGrid\AbstractDataGrid
{


    function setupColumns()
    {
        $obj = $this;
        
        $formatter = new Com\DataGrid\Column\Formatter\Custom(function ($column, $row) use($obj)
        {
            $urlApprove = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'instance',
                'action' => 'approve',
                'id' => $row['c_id']
            ));
            
            $urlDelete = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'instance',
                'action' => 'delete',
                'id' => $row['c_id']
            ));
            
            $text_return = <<<xxx
<input type="checkbox" class="row" name="item[]" value="{$row['c_id']}" />
<div class="btn-group">
    <a href="$urlApprove" class="btn btn-primary btn-xs info">Approve</a>
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
        $col->setWidth(2);
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('id', 'c');
        $col->setIdentity();
        $this->addColumn($col);
        
        {// hidden columsn
            // 
            $col = new ZfcDatagrid\Column\Select('db_host', 'd');
            $col->setHidden();
            $this->addColumn($col);
            
            // 
            $col = new ZfcDatagrid\Column\Select('db_name', 'd');
            $col->setHidden();
            $this->addColumn($col);
            
            //
            $col = new ZfcDatagrid\Column\Select('created_on', 'c');
            $col->setHidden();
            $col->setSortDefault(1, 'ASC');
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
        $formatter = new App\DataGrid\Client\Formatter\Instance();
        
        $col = new ZfcDatagrid\Column\Select('domain', 'c');
        $col->setLabel('Reserved domain');
        
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
        $select->where(array('deleted' => 0, 'approved' => '0'));
        
        //
        $select->group('c.domain');

        //
        $this->dataSource = $select;
    }
}
