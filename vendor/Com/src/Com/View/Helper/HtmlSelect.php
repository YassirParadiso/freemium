<?php

namespace Com\View\Helper;

use Zend;
use Zend\View\Helper\AbstractHelper;


class HtmlSelect extends AbstractHelper
{

    /**
     *
     * @var Zend\Form\Element\Select
     */
    protected $element;


    /**
     *
     * @param string $name
     * @param array $valueOptions
     * @param string|array $selected
     * @param string $emptyOption 
     * @param array $properties
     * @return \Com\View\Helper\HtmlSelect
     */
    public function __invoke($name, array $valueOptions, $selected = array(), $emptyOption = null, array $properties = array())
    {
        $properties['name'] = $name;
        
        $select = new Zend\Form\Element\Select($name);
        $select->setAttributes($properties);
        $select->setValueOptions($valueOptions);
        $select->setEmptyOption($emptyOption);
        
        if(! is_null($selected))
            $select->setValue($selected);

        $this->element = $select;
        return $this;
    }


    /**
     *
     * @return \Zend\Form\Element\Select
     */
    function getElement()
    {
        return $this->element;
    }


    function __toString()
    {
        return $this->view->formSelect($this->element);
    }
}