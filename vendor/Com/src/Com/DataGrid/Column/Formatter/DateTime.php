<?php

namespace Com\DataGrid\Column\Formatter;

use ZfcDatagrid;
use ZfcDatagrid\Column\AbstractColumn;


class DateTime extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $index = $column->getUniqueId();
        
        $date = $row[$index];
        
        return date('d M, Y g:i a', strtotime($date));
    }
}
