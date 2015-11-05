<?php

namespace Com\Model;


abstract class AbstractModel
{

    /**
     *
     * @var \Com\Communicator
     */
    protected $communicator;

    /**
     *
     * @var \Zend\Db\Sql\Sql
     */
    protected $sql;

    /**
     *
     * @var \Zend\EventManager\EventInterface
     */
    protected $event;

    /**
     *
     * @var \Zend\EventManager\EventManagerInterface
     */
    protected $events;

    /**
     *
     * @var \Zend\Db\Adapter\Adapter
     */
    protected $dbAdapter;

    /**
     *
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator = null;

    /**
     * Translation object
     *
     * @var \Zend\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    /**
     * Text domain to be used with translator
     *
     * @var string
     */
    protected $translatorTextDomain = 'default';

    /**
     *
     * @var string
     */
    private $cacheSufix = null;

    /**
     *
     * @var array
     */
    protected $cacheKeys = array();


    /**
     *
     * @param string $index
     * @throws \Exception
     */
    function getCacheKey($index)
    {
        if(! isset($this->cacheKeys[$index]))
        {
            throw new \Exception("Cache index '$index' not found");
        }
        
        return $this->cacheKeys[$index] . '-' . $this->_getCacheSufix();
    }


    /**
     *
     * @param string $index
     * @param string $cacheKey
     * @return mixed
     */
    function getCachItem($index, $cacheKey = 'cache-fs1')
    {
        $sl = $this->getServiceLocator();
        
        $cache = $sl->get($cacheKey);
        
        $key = $this->getCacheKey($index);
        
        return $cache->getItem($key);
    }


    /**
     *
     * @param string $index
     * @param mixed $value
     * @param string $cacheKey
     * @return \Com\Model\AbstractModel
     */
    function setCacheItem($index, $value, $cacheKey = 'cache-fs1')
    {
        $sl = $this->getServiceLocator();
        
        $cache = $sl->get($cacheKey);
        
        $key = $this->getCacheKey($index);
        
        $cache->setItem($key, $value);
        
        return $this;
    }


    /**
     *
     * @param string $index
     * @param string $cacheKey
     * @return \Com\Model\AbstractModel
     */
    function removeCacheItem($index, $cacheKey = 'cache-fs1')
    {
        $sl = $this->getServiceLocator();
        $cache = $sl->get($cacheKey);
        
        $key = $this->getCacheKey($index);
        
        $cache->removeItem($key);
        return $this;
    }


    /**
     *
     * @return string
     */
    private function _getCacheSufix()
    {
        if(is_null($this->cacheSufix))
        {
            $this->cacheSufix = str_replace('\\', '', get_class($this->cacheSufix));
        }
        
        return $this->cacheSufix;
    }


    /**
     * Set translation object
     *
     * @param \Zend\I18n\Translator\TranslatorInterface|null $translator
     * @param string $textDomain
     */
    public function setTranslator(\Zend\I18n\Translator\TranslatorInterface $translator = null, $textDomain = null)
    {
        $this->translator = $translator;
        
        if(null !== $textDomain)
        {
            $this->setTranslatorTextDomain($textDomain);
        }
    }


    /**
     * Set default translation text domain
     *
     * @param string $textDomain
     */
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        $this->translatorTextDomain = $textDomain;
    }


    /**
     * Get translation object
     *
     * @return \Zend\I18n\Translator\TranslatorInterface | null
     */
    public function getTranslator()
    {
        $sl = $this->getServiceLocator();
        
        if(is_null($this->translator))
        {
            $this->translator = $sl->get('translator');
        }
        
        return $this->translator;
    }


    /**
     *
     * @param \Zend\EventManager\EventInterface $e
     */
    function setEvent(\Zend\EventManager\EventInterface $e)
    {
        $this->event = $e;
    }


    /**
     *
     * @return \Zend\EventManager\EventInterface
     */
    function getEvent()
    {
        if(! $this->event)
        {
            $this->setEvent(new \Zend\EventManager\Event());
        }
        
        return $this->event;
    }


    /**
     * Set the event manager instance used by this context
     *
     * @param \Zend\EventManager\EventManagerInterface $events
     * @return mixed
     */
    function setEventManager(\Zend\EventManager\EventManagerInterface $events)
    {
        $identifiers = array(
            __CLASS__,
            get_class($this) 
        );
        if(isset($this->eventIdentifier))
        {
            if((is_string($this->eventIdentifier)) || (is_array($this->eventIdentifier)) || ($this->eventIdentifier instanceof Traversable))
            {
                $identifiers = array_unique(array_merge($identifiers, (array)$this->eventIdentifier));
            }
            elseif(is_object($this->eventIdentifier))
            {
                $identifiers[] = $this->eventIdentifier;
            }
            // silently ignore invalid eventIdentifier types
        }
        $events->setIdentifiers($identifiers);
        $this->events = $events;
        return $this;
    }


    /**
     *
     * @return \Zend\EventManagerEventManagerInterface
     */
    function getEventManager()
    {
        if(! $this->events instanceof \Zend\EventManager\EventManagerInterface)
        {
            $this->setEventManager(new \Zend\EventManager\EventManager());
        }
        return $this->events;
    }


    /**
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     */
    function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }


    /**
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    function getServiceLocator()
    {
        return $this->serviceLocator;
    }


    /**
     *
     * @param \Zend\Db\Adapter\Adapter $adapter
     */
    function setDbAdapter(\Zend\Db\Adapter\Adapter $adapter)
    {
        $this->dbAdapter = $adapter;
        return $this;
    }


    /**
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    function getDbAdapter()
    {
        return $this->dbAdapter;
    }


    /**
     *
     * @return boolean
     */
    function isSuccess()
    {
        return $this->getCommunicator()->isSuccess();
    }


    /**
     *
     * @return \Com\Communicator
     */
    function getCommunicator()
    {
        if(! $this->communicator instanceof \Com\Communicator)
            $this->resetCommunicator();
        
        return $this->communicator;
    }


    /**
     *
     * @return \Com\Model\AbstractModel
     */
    function resetCommunicator()
    {
        $this->communicator = new \Com\Communicator();
        
        return $this;
    }


    /**
     *
     * @param Exception $e
     * @return \Com\Model\AbstractModel
     */
    function setException(\Exception $e)
    {
        if(APP_ENV == APP_DEVELOPMENT)
        {
            $message = "<pre>$e</pre>";
        }
        else
        {
            $sl = $this->getServiceLocator();
            
            $message = $e->getMessage() . " ({$ex->getCode()}) " . PHP_EOL . $e->getTraceAsString();
            
            $mErrorLog = $sl->get('App\Model\ErrorLog');
            $mErrorLog->logError('exception', $message, $e->getFile(), $e->getLine());
            
            $message = 'There was an unexpected error, please try again.';
        }
        
        $this->getCommunicator()->addError($message);
        
        return $this;
    }


    /**
     *
     * @param array $required
     * @param mixed $params
     *
     * @return bool
     */
    function hasEmptyValues(array $required = array(), &$params)
    {
        $isObject = is_object($params);
        
        $flag = false;
        if(count($required))
        {
            foreach($required as $item)
            {
                if(($isObject && isset($params->$item)) || isset($params[$item]))
                {
                    $field = $isObject ? $params->$item : $params[$item];
                    if((is_string($field) && '' == $field) || (is_array($field)) && 0 == count($field))
                    {
                        $flag = true;
                        break;
                    }
                }
                else
                {
                    $flag = true;
                    break;
                }
            }
        }
        else
        {
            $flag = true;
        }
        
        if($flag)
        {
            $m = 'Please fill in all required fields.';
            $m = $this->_($m);
            
            $this->getCommunicator()->addError($m);
        }
        
        return $flag;
    }


    /**
     *
     * @param array $filters
     * @param array $fields
     * @param mixed $params
     * @return mixed
     */
    function applyFilters(array $filters, array $fields, $params)
    {
        $isObject = is_object($params);
        
        $flag = false;
        if(count($fields) && count($filters))
        {
            foreach($filters as $filter)
            {
                foreach($fields as $field)
                {
                    if(isset($params->$field) || isset($params[$field]))
                    {
                        $fieldValue = $isObject ? $params->$field : $params[$field];
                        if($isObject)
                        {
                            $params->$field = \Zend\Filter\StaticFilter::execute($fieldValue, $filter);
                        }
                        else
                        {
                            $params[$field] = \Zend\Filter\StaticFilter::execute($fieldValue, $filter);
                        }
                    }
                }
            }
        }
        
        return $params;
    }


    /**
     *
     * @return int
     */
    function getUserId()
    {
        $userId = 0;
        
        $identity = $this->getUserIdentity();
        
        if($identity)
        {
            $userId = $identity['id'];
        }
        
        return $userId;
    }


    /**
     *
     * @return \Com\Auth\Authentication | null
     */
    function getUserIdentity()
    {
        $identity = null;
        $auth = new \Com\Auth\Authentication();
        
        if($auth->hasIdentity())
        {
            $identity = $auth->getIdentity();
        }
        
        return $identity;
    }


    /**
     *
     * @return \Zend\Db\Sql\Sql
     */
    function getSql()
    {
        if(! $this->sql)
            $this->sql = new \Zend\Db\Sql\Sql($this->getDbAdapter());
        
        return $this->sql;
    }


    /**
     * Translate a string using the given text domain and locale
     *
     * @param string $str
     * @param array $params
     * @param string $textDomain
     * @param string $locale
     * @return string
     */
    function _($str, $params = array(), $textDomain = 'default', $locale = null)
    {
        $str = $this->getTranslator()->translate($str, $textDomain, $locale);
        
        if(is_array($params) && count($params))
        {
            array_unshift($params, $str);
            $str = call_user_func_array('sprintf', $params);
        }
        
        return $str;
    }


    /**
     *
     * @param mixed $value
     * @param mixed $label
     * @return \Com\Model\AbstractModel
     */
    function debug($value, $label)
    {
        if(APP_ENV == APP_DEVELOPMENT)
        {
            $this->getServiceLocator()
                ->get('debugger')
                ->debug($value, $label);
        }
        
        return $this;
    }
}