<?php

namespace Com\DataGrid\Column\Formatter;

use ZfcDatagrid, Com;
use ZfcDatagrid\Column\AbstractColumn;


class ShortDate extends ZfcDatagrid\Column\Formatter\AbstractFormatter
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
        $time = strtotime($date);

        return date('d.m.Y', $time);
    }
}
