<?php
namespace ZendDeveloperTools\Collector;

use Zend\Mvc\MvcEvent;

class SessionCollector extends AbstractCollector
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'session';
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 100;
    }

    /**
     * @inheritdoc
     */
    public function collect(MvcEvent $mvcEvent)
    {
        if(isset($_SESSION))
            $this->data = $_SESSION;
        else 
            $this->data = array();
    }

    /**
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
