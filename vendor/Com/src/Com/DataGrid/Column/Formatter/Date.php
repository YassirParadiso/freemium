<?php

namespace Com\DataGrid\Column\Formatter;

use ZfcDatagrid, Com;
use ZfcDatagrid\Column\AbstractColumn;


class Date extends ZfcDatagrid\Column\Formatter\AbstractFormatter
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
        $month = $this->_translateMonth(date('m', $time));
        
        return $month . date(' j, Y', $time);
    }


    protected function _translateMonth($month)
    {
        $sm = Com\Module::$MVC_EVENT->getApplication()->getServiceManager();
        $helper = $sm->get('viewhelpermanager');
        $translate = $helper->get('translate');


        return $translate("month_{$month}_short");
    }
}
