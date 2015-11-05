<?php
namespace App\DataGrid\Client\Formatter;

use ZfcDatagrid;
use Com, Zend;
use ZfcDatagrid\Column\AbstractColumn;


class Verified extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $time = strtotime($row['c_email_verified_on']);
        
        if($row['c_email_verified'])
        {
            $date = date('Y.m.d', $time);
            $date = "<br><span style='font-size:10px'>$date</span>";
        }
        
        return $row['c_email_verified'] ? "<span class='label label-success'>Yes</span> $date" : '<span class="label label-danger">No</span>';
    }
}