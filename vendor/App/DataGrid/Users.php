<?php

namespace App\DataGrid;

use Com, Zend, ZfcDatagrid, App;


class Users extends Com\DataGrid\AbstractDataGrid
{


    function setupColumns()
    {
        $obj = $this;
        
        $formatter = new Com\DataGrid\Column\Formatter\Custom(function ($column, $row) use($obj)
        {
            $urlUpdateInfo = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'users',
                'action' => 'update-info',
                'id' => $row['u_id']
            ));

            $urlChangePass = $obj->url()->fromRoute('backend/wildcard', array(
                'controller' => 'users',
                'action' => 'update-password',
                'id' => $row['u_id']
            ));

            $status = $row['u_status'];

            if('enabled' == $status)
            {
                $urlStatus = $obj->url()->fromRoute('backend/wildcard', array(
                    'controller' => 'users',
                    'action' => 'update-status',
                    'id' => $row['u_id'],
                    'status' => 0
                ));

                $statusItem = sprintf('<li><a href="%s"><i class="fa fa-user-times"></i> Disdable</a></li>', $urlStatus);
            }
            else
            {
                $urlStatus = $obj->url()->fromRoute('backend/wildcard', array(
                    'controller' => 'users',
                    'action' => 'update-status',
                    'id' => $row['u_id'],
                    'status' => 1
                ));

                $statusItem = sprintf('<li><a href="%s"><i class="fa fa-user"></i> Enable</a></li>', $urlStatus);
            }
            
            $text_return = <<<xxx
<div class="btn-group">
    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
        <span class="caret"></span>
    </button>

    <ul class="dropdown-menu" role="menu">
        <li><a href="{$urlUpdateInfo}"><i class="fa fa-info-circle"></i> Update info</a></li>
        <li><a href="{$urlChangePass}"><i class="fa fa-key"></i> Change password</a></li>
        $statusItem
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
        $col = new ZfcDatagrid\Column\Select('id', 'u');
        $col->setIdentity();
        $col->setSortDefault(1, 'DESC');
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('email', 'u');
        $col->setLabel('Email');
        $this->addColumn($col);
        
        // 
        $col = new ZfcDatagrid\Column\Select('first_name', 'u');
        $col->setLabel('First name');
        $this->addColumn($col);

        // 
        $col = new ZfcDatagrid\Column\Select('last_name', 'u');
        $col->setLabel('Last name');
        $this->addColumn($col);
        
        // 
        $formatter = new App\DataGrid\Users\Status();
        
        $options = array('enabled' => 'Enabled', 'disabled' => 'Disabled');

        $col = new ZfcDatagrid\Column\Select('status', 'u');
        $col->setFormatter($formatter);
        $col->setFilterSelectOptions($options);
        $col->setLabel('Status');
        $this->addColumn($col);
    }


    function setupDataSource()
    {
        $sl = $this->getServiceLocator();
        
        $dbUser = $sl->get('App\Db\User');
        
        $select = new Zend\Db\Sql\Select();
        
        $select->from(array(
            'u' => $dbUser->getTable() 
        ));
        
        //
        $this->dataSource = $select;
    }
}
