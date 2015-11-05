<?php

namespace Com\DataGrid\Column\Formatter;

use ZfcDatagrid;
use ZfcDatagrid\Column\AbstractColumn;


class Custom extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );

    protected $fn;


    /**
     *
     * @param unknown $fn
     */
    function __construct($fn)
    {
        $this->fn = $fn;
    }


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $fn = $this->fn;
        return $fn($column, $row);
    }
}
