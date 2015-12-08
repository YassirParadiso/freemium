<?php
namespace App\DataGrid\Formatter;

use ZfcDatagrid;
use Com, Zend;
use ZfcDatagrid\Column\AbstractColumn;


class Domain extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $domain = $row['d_domain'];

        return sprintf('<a target="_blank" href="http://%s">%s</a>', $domain, $domain);
    }
}