<?php
namespace Com\View\Helper;

use Zend\View\Helper\AbstractHelper;

class ComFormRow extends AbstractHelper
{

    /**
     *
     * @var \Zend\Form\Form
     */
    protected $form;

    /**
     *
     * @param \Zend\Form\Element\Element|string $element            
     * @return \Com\View\Helper\ComFormRow | string
     */
    public function __invoke($element = null)
    {
        if (empty($element)) {
            return $this;
        }
        
        $view = $this->getView();
        
        if (is_string($element)) {
            $formElement = $this->form->get($element);
        } else {
            $formElement = $element;
        }
        
        $id = $formElement->getAttribute('id');
        if (empty($id)) {
            $id = $formElement->getName();
            $formElement->setAttribute('id', $id);
        }
        
        $label = $formElement->getLabel();
        $required = $formElement->getAttribute('required');
        $messages = $formElement->getMessages();
        $hasErrorClass = '';
        $errors = '';
        
        if (count($messages)) {
            // $hasErrorClass = ' has-error';
            $view->formElementErrors()
                ->setMessageOpenFormat('<ul class="form-errors"><li class="text-danger">')
                ->setMessageSeparatorString('</li><li class="text-danger">');
            
            $errors = $view->formElementErrors($formElement);
        }
        
        if ($required) {
            $required = '<span class="required" style="color:red">*</span>';
        }
        
        if (! empty($label)) {
            $label = $view->formLabel($formElement);
            $label = sprintf('<label class="control-label" for="%s">%s%s</label>', $id, $required, strip_tags($label));
        }
        
        $r = sprintf('<div class="form-group%s">%s %s %s</div>', $hasErrorClass, $label, $view->formElement($formElement), $errors);
        
        return $r;
    }

    /**
     *
     * @param \Zend\Form\Form $form            
     * @return \Com\View\Helper\ComFormRow
     */
    function setForm(\Zend\Form\Form $form)
    {
        $this->form = $form;
        return $this;
    }
}