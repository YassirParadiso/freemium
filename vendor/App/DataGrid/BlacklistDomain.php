<?php

namespace App\DataGrid;

use Com, Zend, ZfcDatagrid, App;


class BlacklistDomain extends Com\DataGrid\AbstractDataGrid
{


    function setupColumns()
    {
        $obj = $this;
        
        $formatter = new Com\DataGrid\Column\Formatter\Custom(function ($column, $row) use($obj)
        {
            $urlInfo = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'blacklist-domain',
                'action' => 'info',
                'id' => $row['d_id']
            ));
            
            $urlDelete = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'blacklist-domain',
                'action' => 'delete',
                'id' => $row['d_id']
            ));

            
            $text_return = <<<xxx
            <a class="delete btn btn-default btn-sm" href="$urlDelete"><i class="fa fa-trash"></i></a>
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
        $col = new ZfcDatagrid\Column\Select('id', 'd');
        $col->setIdentity();
        $col->setSortDefault(1, 'DESC');
        $this->addColumn($col);
        
        //
        $formatter = new App\DataGrid\Formatter\Domain();
        
        $col = new ZfcDatagrid\Column\Select('domain', 'd');
        $col->setLabel('Domain name');
        $col->setWidth(50);
        $col->setFormatter($formatter);
        $this->addColumn($col);
    }


    function setupDataSource()
    {
        $sl = $this->getServiceLocator();

        $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');
        
        $select = new Zend\Db\Sql\Select();
        
        $select->from(array(
            'd' => $dbBlacklistDomain->getTable() 
        ));
        
        //
        $this->dataSource = $select;
    }
}
