<?php
namespace App\DataGrid\Client\Formatter;

use ZfcDatagrid;
use Com, Zend;
use ZfcDatagrid\Column\AbstractColumn;


class DueDate extends ZfcDatagrid\Column\Formatter\AbstractFormatter
{

    protected $validRenderers = array(
        'jqGrid',
        'bootstrapTable' 
    );


    public function getFormattedValue(AbstractColumn $column)
    {
        $row = $this->getRowData();
        $dueDate = $row['c_due_date'];

        if($dueDate)
        {
            $time = strtotime($dueDate);
            $date = date('M d, Y', $time);
            $today = date('Y-m-d');

            $datetime1 = new \DateTime($dueDate);
            $datetime2 = new \DateTime($today);
            $interval = $datetime2->diff($datetime1);
            $difference = (int)$interval->format('%R%a');

            $calc = $difference;

            #$cDate = mktime(null, null, null, date('m', $time), date('d', $time), date('Y'));
            #$today = time();
            #$difference = ($cDate - $today) - 1;

            #$calc = floor($difference/60/60/24);

            if($difference > 0)
            {
                $label = "$calc days remaining";
                if(1 == $difference)
                    $label = "$calc day remaining";

                if($difference > 5)
                {
                    $cssClass = "label-success";
                }
                else
                {
                    $cssClass = "label-warning";
                }
            }
            elseif(0 == $difference)
            {
                $label = "DUE TODAY!";
                $cssClass = "label-warning";
            }
            else
            {
                $calcAbs = abs($calc);
                $label = "past due $calcAbs days ago";
                if(-1 == $calc)
                    $label = "past due $calcAbs day ago";

                $cssClass = "label-danger";
            }

            return sprintf('<span class="label %s">%s @ %s</span>', $cssClass, $date, $label);
        }
        else
        {
            return '<span class="label label-default">Not Set</span>';
        }
    }
}