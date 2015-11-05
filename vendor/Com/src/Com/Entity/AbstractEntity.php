<?php
namespace Com\Entity;

use Traversable;

abstract class AbstractEntity implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    /**
     *
     * @var array
     */
    private $attr = null;

    /**
     *
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param array|Traversable|null $options            
     */
    public function __construct($options = null)
    {
        if (null !== $options) {
            $this->setFromArray($options);
        }
    }

    /**
     * Set service locator
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator            
     */
    function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get service locator
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     *
     * @param array|Traversable|AbstractOptions $data            
     * @return \Com\Entity\AbstractEntity
     */
    function setFromArray($data)
    {
        $this->populate($data);
        return $this;
    }

    /**
     *
     * @param array|Traversable|AbstractOptions $data            
     * @return \Com\Entity\AbstractEntity
     */
    function populate($data)
    {
        if ($data instanceof self)
        {
            $data = $data->toArray();
        }
        
        if (! is_array($data) && ! $data instanceof Traversable)
        {
            throw new \Exception(sprintf('Parameter provided to %s must be an %s, %s or %s', __METHOD__, 'array', 'Traversable', 'Zend\Stdlib\AbstractOptions'));
        }
        
        foreach ($data as $key => $value)
        {
            $this->__set($key, $value);
        }
        
        return $this;
    }

    /**
     *
     * @param array|Traversable|AbstractOptions $data            
     * @return \Com\Entity\AbstractEntity
     */
    function exchangeArray($data)
    {
        $attributes = $this->getEntityAttributes();

        foreach ($attributes as $key)
        {
            $this->__unset($key);
        }

        $this->populate($data);
        return $this;
    }

    /**
     *
     * @return array
     */
    function toArray()
    {
        $array = array();
        $methods = array();
        
        $keys = array_keys($this->getEntityAttributes());
        foreach ($keys as $key) {
            $getter = 'get' . str_replace(' ', '', str_replace('_', ' ', $key));
            
            $methods[$getter] = $key;
            $array[$key] = $this->__get($key);
        }
        
        $classMethods = get_class_methods($this);
        
        foreach ($classMethods as $method) {
            if (preg_match('/^get/', $method)) {
                $lower = strtolower($method);
                if ('getarraycopy' != $lower && 'getentityattributes' != $lower && 'getservicelocator' != $lower && ! isset($methods[$lower])) {
                    $method = substr($method, 3);
                    $separated = preg_replace('/(?<!\ )[A-Z]/', '_$0', $method);
                    if ('_' == substr($separated, 0, 1))
                        $separated = substr($separated, 1);
                    
                    $prop = strtolower($separated);
                    $val = $this->__get($prop);
                    
                    if (! is_null($val))
                        $array[$prop] = $this->__get($prop);
                }
            }
        }
        
        return $array;
    }

    /**
     *
     * @return array
     */
    function extract()
    {
        $data = array();
        
        $keys = array_keys($this->getEntityAttributes());
        foreach ($keys as $key) {
            $data[$key] = $this->__get($key);
        }
        
        return $data;
    }

    /**
     *
     * @return array
     */
    function getArrayCopy()
    {
        return $this->extract();
    }

    /**
     *
     * @return string
     */
    function toJson()
    {
        return \Zend\Json\Encoder::encode($this->toArray());
    }

    /**
     *
     * @return string
     */
    function toString()
    {
        $r = '';
        $r .= '<pre>';
        $r .= print_r($this->toArray(), 1);
        $r .= '</pre>';
        
        return $r;
    }

    /**
     *
     * @param string $key            
     * @param mixed $value            
     */
    public function __set($key, $value)
    {
        $getter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        
        if (method_exists($this, $getter))
        {
            $this->{$getter}($value);
        }
        else
        {
            if ($this->_propertyExist($key))
            {
                $this->$key = $value;
            }
        }
    }

    /**
     *
     * @param string $key            
     * @return mixed
     */
    function __get($key)
    {
        $value = null;
        
        $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists($this, $getter))
        {
            $value = $this->{$getter}();
        }
        else
        {
            $value = $this->$key;
        }
        
        return $value;
    }

    /**
     * Test if a configuration property is null
     *
     * @param string $key            
     * @return bool
     */
    public function __isset($key)
    {
        return null !== $this->__get($key);
    }

    /**
     * Set a configuration property to NULL
     *
     * @param string $key            
     * @return void
     */
    public function __unset($key)
    {
        $this->__set($key, null);
    }

    /**
     *
     * @return string
     */
    function __toString()
    {
        return $this->toString();
    }

    /**
     *
     * @param string $property            
     * @return boolean
     */
    protected function _propertyExist($property)
    {
        $attr = $this->getEntityAttributes();
        return array_key_exists($property, $attr);
    }

    /**
     *
     * @return array
     */
    function getEntityAttributes()
    {
        if (is_null($this->attr))
        {
            $this->attr = get_class_vars(get_class($this));
            unset($this->attr['attr']);
            unset($this->attr['serviceLocator']);
        }
        
        return $this->attr;
    }
}
