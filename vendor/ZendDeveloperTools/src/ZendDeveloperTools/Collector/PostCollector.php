<?php
namespace ZendDeveloperTools\Collector;

use Zend\Mvc\MvcEvent;

class PostCollector extends AbstractCollector
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'post';
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
        $post = $mvcEvent->getRequest()->getPost();
        $this->data = $post->getArrayCopy();
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
