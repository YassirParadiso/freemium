<?php
namespace ZendDeveloperTools\Collector;

use Zend\Mvc\MvcEvent;

class CookieCollector extends AbstractCollector
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'cookie';
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
        $cookie = $mvcEvent->getRequest()->getCookie();
        if($cookie)
        {
            $this->data = $cookie->getArrayCopy();
        }
        else
        {
            $this->data = array();
        }
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
