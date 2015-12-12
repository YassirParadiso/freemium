<?php

namespace App\DataGrid;

use Com, Zend, ZfcDatagrid, App;


class BlacklistPhrase extends Com\DataGrid\AbstractDataGrid
{


    function setupColumns()
    {
        $obj = $this;
        
        $formatter = new Com\DataGrid\Column\Formatter\Custom(function ($column, $row) use($obj)
        {
            $urlInfo = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'blacklist-phrase',
                'action' => 'info',
                'id' => $row['d_id']
            ));
            
            $urlDelete = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'blacklist-phrase',
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
        
        $col = new ZfcDatagrid\Column\Select('phrase', 'd');
        $col->setLabel('Phrase');
        $col->setWidth(50);
        $this->addColumn($col);
    }


    function setupDataSource()
    {
        $sl = $this->getServiceLocator();

        $dbBlacklistPharse = $sl->get('App\Db\BlacklistPhrase');
        
        $select = new Zend\Db\Sql\Select();
        
        $select->from(array(
            'd' => $dbBlacklistPharse->getTable() 
        ));
        
        //
        $this->dataSource = $select;
    }
}
