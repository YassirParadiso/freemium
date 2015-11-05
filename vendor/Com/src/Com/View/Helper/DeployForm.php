<?php
namespace Com\View\Helper;

use Zend\View\Helper\AbstractHelper;

class DeployForm extends AbstractHelper
{

    /**
     *
     * @return \Zend\Form\Form
     */
    public function __invoke(\Zend\Form\Form $form)
    {
        $form->prepare();
        
        $view = $this->getView();
        $view->ComFormRow()->setForm($form);
        
        $r = '';
        $elements = $form->getElements();
        foreach ($elements as $element) {
            $r .= $view->ComFormRow($element);
        }
        
        return $r;
    }
}