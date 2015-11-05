<?php
namespace App\Model;

use Com, Zend;

class EmailTemplate extends Com\Model\AbstractModel
{

    /**
     *
     * @var array
     */
    protected $data = array();


    /**
     * Obtiene el template de la base de datos y lo devuelve con los valores reempalzados
     *
     * @param string $name
     * @param array | object $data - Puede ser cualquier clase que implemente los metodos: toArray(), getArrayCopy()
     *
     * @return array
     * @var string subject
     * @var string body
     */
    function loadAndParse($name, $data = null)
    {
        if(! isset($this->data[$name]))
        {
            $dbTemplate = $this->getServiceLocator()->get('App\Db\EmailTemplate');
            
            $where = array();
            $where['name = ?'] = $name;
            
            $record = $dbTemplate->findBy($where)->current();
            if(! $record)
            {
                throw new \Exception("Unable to find template name '$name'");
            }
            
            $subject = $this->data[$name]['subject'] = $record->subject;
            $body = $this->data[$name]['body'] = $record->body;
        }
        else
        {
            $subject = $this->data[$name]['subject'];
            $body = $this->data[$name]['body'];
        }
        
        return array(
            'subject' => $this->parse($subject, $data),
            'body' => $this->parse($body, $data) 
        );
    }


    /**
     * Reemplaza las variables definidas en formato {{variable_name}}
     * 
     * @param string $template
     * @param array | object $data - Puede ser cualquier clase que implemente los metodos: toArray(), getArrayCopy()
     * @return string
     */
    function parse($template, $data = null)
    {
        
        $keys = array_keys($data);
        
        foreach($keys as $key => $value)
        {	 
            $keys[$key] = "{{" . $value . "}}";
        }
        
        if(is_object($data))
        {
            if(method_exists($data, 'toArray'))
            {
                $data = $data->toArray();
            }
            elseif(method_exists($data, 'getArrayCopy'))
            {
                $data = $data->getArrayCopy();
            }
        }
        
        if(! is_array($data))
        {
            $data = array();
        }
        $replaced = str_replace($keys, array_values($data), $template);
            
        return $replaced;
    }
}
