<?php
namespace Com\DataGrid\Column;
use ZfcDatagrid;

class Action extends ZfcDatagrid\Column\AbstractColumn
{
    private $actions = array();

    /**
     *
     * @param string $uniqueId
     */
    public function __construct($uniqueId = 'action')
    {
        $this->setUniqueId($uniqueId);
        $this->setLabel('Actions');

        $this->setUserSortDisabled(true);
        $this->setUserFilterDisabled(true);

        $this->setRowClickDisabled(true);
    }
}
