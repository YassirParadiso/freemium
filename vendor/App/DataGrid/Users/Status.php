<?php
namespace App\DataGrid\Users;

use ZfcDatagrid;
use Com, Zend;
use ZfcDatagrid\Column\AbstractColumn;


class Status extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        
        if('enabled' == $row['u_status'])
        {
            $status = '<span class="label label-success">enabled</span>';
        }
        else
        {
            $status = '<span class="label label-danger">disabled</span>';
        }
        
        return $status;
    }
}