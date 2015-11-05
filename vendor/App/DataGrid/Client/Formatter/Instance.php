<?php
namespace App\DataGrid\Client\Formatter;

use ZfcDatagrid;
use Com, Zend;
use ZfcDatagrid\Column\AbstractColumn;


class Instance extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $dbName = $row['d_db_name'];
        $domain = $row['c_domain'];
        $count = $row['count'];
        
        $time = strtotime($row['c_created_on']);
        $date = date('F d, Y @ h:i a', $time);
            
        if($dbName)
        {
            $dbName = " - <span style='font-size:12px'><i class='fa fa-database'></i> <span class='text-muted'>$dbName</span></span>";
        }
        
        $r  = '';
        $r .= "<div style='font-size:12px'><i class='fa fa-server'></i> <a href='http://$domain' target='_blank'>{$domain}</a></div>";
        $r .= "<div class='text-muted' style='font-size:12px'><i class='fa fa-calendar'></i> {$date}</div>";
        $r .= "<div class='text-muted' style='font-size:12px'><i class='fa fa-user-plus'></i> {$count} $dbName</div>";
        
        return $r;
    }
}