<?php

/**
 * Clase base para acceso a datos.
 * Proporciona métodos comunes de acceso a datos y eventos.
 * Todas las clases de acceso a datos deben extender de esta clase.
 *
 * @author yoterri@ideasti.com
 * @since 2014
 *
 */
namespace Com\Db;

use Com, Zend;


abstract class AbstractDb extends \Zend\Db\TableGateway\TableGateway
{
    
    /**
     *
     * @var string
     */
    const EVENT_PREFIXING = 'prefixing';
    
    /**
     *
     * @var string
     */
    const EVENT_BEFORE_INSERT = 'before-insert';
    
    /**
     *
     * @var string
     */
    const EVENT_AFTER_INSERT = 'after-insert';
    
    /**
     *
     * @var string
     */
    const EVENT_BEFORE_UPDATE = 'before-update';
    
    /**
     *
     * @var string
     */
    const EVENT_AFTER_UPDATE = 'after-update';
    
    /**
     *
     * @var string
     */
    const EVENT_BEFORE_DELETE = 'before-delete';
    
    /**
     *
     * @var string
     */
    const EVENT_AFTER_DELETE = 'after-delete';

    /**
     * Nombre de la tabla en la base de datos.
     * Se debe poner el nombre de la tabla sin prefijos
     *
     * @example nombre_tabla
     * @var string
     */
    protected $tableName = '';

    /**
     *
     * @var Nombre de la base de datos
     */
    protected $schemaName = '';

    /**
     *
     * @var string
     */
    protected $adapterKey;

    /**
     * Nombre de la clase que se debe usar como entidad para retornar los registros
     *
     * @example \Com\Entity\News
     * @var string
     */
    protected $entityClassName = '\Com\Entity\Record';

    /**
     *
     * @var \Zend\Stdlib\Hydrator\HydratorInterface
     */
    protected $hydrator;

    /**
     *
     * @var \Zend\Db\ResultSet\ResultSet
     */
    protected $resultSet = null;

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
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator = null;

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
     * @param string $features
     * @param Zend\Db\ResultSet\ResultSetInterface $resultSetPrototype
     * @param Zend\Db\Sql\Sql $sql
     */
    function __construct($features = null, Zend\Db\ResultSet\ResultSetInterface $resultSetPrototype = null)
    {
        // process features
        if($features !== null)
        {
            if($features instanceof \Zend\Db\TableGateway\Feature\AbstractFeature)
            {
                $features = array(
                    $features 
                );
            }
            
            if(is_array($features))
            {
                $this->featureSet = new \Zend\Db\TableGateway\Feature\FeatureSet($features);
            }
            elseif($features instanceof \Zend\Db\TableGateway\Feature\FeatureSet)
            {
                $this->featureSet = $features;
            }
            else
            {
                throw new \Exception('TableGateway expects $feature to be an instance of an AbstractFeature or a FeatureSet, or an array of AbstractFeatures');
            }
        }
        else
        {
            $this->featureSet = new \Zend\Db\TableGateway\Feature\FeatureSet();
        }
        
        // result prototype
        $this->resultSetPrototype = ($resultSetPrototype) ?  : new \Zend\Db\ResultSet\ResultSet();
    }


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
     * @return \Com\Db\AbstractDb
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
     * @return \Com\Db\AbstractDb
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
     * Set an event
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function setEvent(\Zend\EventManager\EventInterface $e)
    {
        $this->event = $e;
    }


    /**
     * Get the attached event
     *
     * Will create a new Event if none provided.
     *
     * @return \Zend\EventManager\EventInterface
     */
    public function getEvent()
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
     * Este método es llamado de forma automática en la seccion 'initializers' del archivo de configuración del módulo
     *
     * @param \Zend\Db\Adapter\AdapterInterface $adapter
     * @return \Com\Db\AbstractDb
     */
    function setAdapter(\Zend\Db\Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }


    /**
     * Retorna el nombre de la clase de entidad que se haya configurado
     *
     * @return string
     */
    function getEntityClassName()
    {
        return $this->entityClassName;
    }


    /**
     *
     * @param \Zend\Stdlib\Hydrator\HydratorInterface $hydrator
     * @return \Com\Db\AbstractDb
     */
    function setHydrator(\Zend\Stdlib\Hydrator\HydratorInterface $hydrator)
    {
        $this->hydrator;
        return $this;
    }


    /**
     *
     * @return \Zend\Stdlib\Hydrator\HydratorInterface
     */
    function getHydrator()
    {
        return $this->hydrator;
    }


    /**
     * Verifica que la instancia cumpla con todos los requerimientos.
     * Se encarga de configurar el prefijo a los nombres de las tablas
     *
     * Este método es llamado de forma atumática en la seccion 'initializers' del archivo de configuración del módulo.
     *
     * @see \Zend\Db\TableGateway\AbstractTableGateway::initialize()
     */
    function initialize()
    {
        if($this->isInitialized)
            return;
        
        $event = $this->getEvent();
        $event->setTarget($this);
        
        $this->getEventManager()->trigger(self::EVENT_PREFIXING, $event);
        $prefix = $event->getParam('prefix');
        
        if(! empty($this->schemaName))
        {
            $this->table = new \Zend\Db\Sql\TableIdentifier("{$prefix}{$this->tableName}", $this->schemaName);
        }
        else
        {
            $this->table = "{$prefix}{$this->tableName}";
        }
        
        // Sql object (factory for select, insert, update, delete)
        $this->sql = new \Zend\Db\Sql\Sql($this->getAdapter(), $this->table);
        
        $this->hydrator = new \Zend\Stdlib\Hydrator\ObjectProperty();
        
        // check sql object bound to same table
        if($this->sql->getTable() != $this->table)
        {
            throw new \Exception('The table inside the provided Sql object must match the table of this TableGateway');
        }
        
        return parent::initialize();
    }


    /**
     *
     * @param array $data
     * @return int affected rows
     */
    function doInsert(array $data)
    {
        $event = $this->getEvent();
        $event->setTarget($this);
        
        $event->setParams(array());
        $event->setParam('data', $data);
        
        $this->getEventManager()->trigger(self::EVENT_BEFORE_INSERT, $event);
        $data = $event->getParam('data');
        
        if(! $event->propagationIsStopped())
        {
            
            $sql = $this->getSql();
            $insert = $sql->insert();
            $insert->values($data);
            
            $statement = $sql->prepareStatementForSqlObject($insert);
            
            $result = $this->executeInsert($insert);
            
            $event->setParams(array());
            $event->setParam('affected_rows', $result);
            $event->setParam('last_insert_value', $this->getLastInsertValue());
            $event->setParam('data', $data);
            
            $this->getEventManager()->trigger(self::EVENT_AFTER_INSERT, $event);
        }
        else
        {
            $result = 0;
        }
        
        return $result;
    }


    /**
     *
     * @param array $data
     * @param string|array|closure $where
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    function doUpdate(array $data, $where)
    {
        $event = $this->getEvent();
        $event->setTarget($this);
        
        $event->setParams(array());
        $event->setParam('data', $data);
        $event->setParam('where', $where);
        
        $this->getEventManager()->trigger(self::EVENT_BEFORE_UPDATE, $event);
        $data = $event->getParam('data');
        $where = $event->getParam('where');
        
        if(! $event->propagationIsStopped())
        {
            
            $sql = $this->getSql();
            $update = $sql->update();
            $where = $update->set($data)->where($where);
            
            $statement = $sql->prepareStatementForSqlObject($update);
            
            $result = $statement->execute();
            
            $event->setParams(array());
            $event->setParam('data', $data);
            $event->setParam('where', $where);
            $event->setParam('result', $result);
            $event->setParam('affected_rows', $result->getAffectedRows());
            
            $this->getEventManager()->trigger(self::EVENT_AFTER_UPDATE, $event);
            $result = $event->getParam('result');
        }
        else
        {
            $result = new \Zend\Db\ResultSet\ResultSet();
        }
        
        return $result;
    }


    /**
     *
     * @param string|array|closure $where
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    function doDelete($where)
    {
        $event = $this->getEvent();
        $event->setTarget($this);
        
        $event->setParams(array());
        $event->setParam('where', $where);
        
        $this->getEventManager()->trigger(self::EVENT_BEFORE_DELETE, $event);
        $where = $event->getParam('where');
        
        if(! $event->propagationIsStopped())
        {
            
            $sql = $this->getSql();
            $delete = $sql->delete();
            $delete->where($where);
            
            $statement = $sql->prepareStatementForSqlObject($delete);
            
            $result = $statement->execute();
            
            $event->setParams(array());
            $event->setParam('result', $result);
            $event->setParam('where', $where);
            $event->setParam('affected_rows', $result->getAffectedRows());
            $this->getEventManager()->trigger(self::EVENT_AFTER_DELETE, $event);
            $result = $event->getParam('result');
        }
        else
        {
            $result = new \Zend\Db\ResultSet\ResultSet();
        }
        
        return $result;
    }


    /**
     * Returns a ResultSet of \Com\Entity\Record
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $where
     * @param array $cols
     * @param string $order
     * @param int $count
     * @param int $offset
     * @param Com\Entity\AbstractEntity $entity
     * @return Zend\Db\ResultSet\AbstractResultSet
     * @throws \RuntimeException
     */
    function findBy($where = null, array $cols = array(), $order = null, $count = null, $offset = null, Com\Entity\AbstractEntity $entity = null)
    {
        $sql = $this->getSql();
        $select = $sql->select();
        
        if($where)
            $select->where($where);
        
        if($cols)
            $select->columns($cols);
        
        if($order)
            $select->order($order);
        
        if($offset)
            $select->offset($offset);
        
        if($count)
            $select->limit($count);
        
        return $this->executeCustomSelect($select, $entity);
    }


    /**
     *
     * @param string $order
     * @param Com\Entity\Record $entity
     * @return Zend\Db\ResultSet\AbstractResultSet
     */
    function findAll($order = null, Com\Entity\Record $entity = null)
    {
        new Zend\Db\ResultSet\ResultSet();
        $cols = array(
            '*' 
        );
        
        return $this->findBy(null, $cols, $order, null, null, $entity);
    }


    /**
     *
     * @param int $mixed
     * @param string $colName
     * @param Com\Entity\Record $entity
     * @return Com\Entity\AbstractEntity | null
     */
    function findByPrimaryKey($mixed, $colName = null, Com\Entity\AbstractEntity $entity = null)
    {
        if(! is_array($mixed))
        {
            if(empty($colName))
            {
                $colName = 'id';
            }
            
            $where = array();
            $where["$colName = ?"] = $mixed;
        }
        else
        {
            $where = $mixed;
        }
        
        return $this->findBy($where, array(), null, null, null, $entity)->current();
    }


    /**
     *
     * @param int $mixed
     * @param string $colName
     * @return bool
     */
    function existByPrimaryKey($mixed, $colName = null)
    {
        if(! is_array($mixed))
        {
            if(empty($colName))
            {
                $colName = 'id';
            }
            
            $where = array();
            $where["$colName = ?"] = $mixed;
        }
        else
        {
            $where = $mixed;
        }
        
        $sql = $this->getSql();
        $select = $sql->select();
        
        $cols = array();
        $cols['count'] = new \Zend\Db\Sql\Predicate\Expression('COUNT(*)');
        $select->columns($cols);
        
        $select->where($where);
        
        return 1 == $this->executeCustomSelect($select, null)->current()->count;
    }


    /**
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $where
     * @param string $group
     * @return int
     */
    function count($where = null, $group = null)
    {
        $sql = $this->getSql();
        $select = $sql->select();
        
        if($where)
            $select->where($where);
        
        if($group)
            $select->group($group);
        
        $cols = array();
        $cols['count'] = new \Zend\Db\Sql\Predicate\Expression('COUNT(*)');
        $select->columns($cols);
        
        return $this->executeCustomSelect($select, null)->current()->count;
    }


    /**
     *
     * @param \Zend\Db\Sql\SqlInterface $sql Consulta sql a mostrar
     * @param string $exit Indica si se debe detener la ejecucion del código
     */
    function debugSql(\Zend\Db\Sql\SqlInterface $sql, $exit = true)
    {
        $str = $sql->getSqlString($this->getAdapter()
            ->getPlatform());
        
        echo '<pre>';
        echo $str;
        echo '</pre>';
        
        if($exit)
            exit();
    }


    /**
     *
     * @param \Zend\Db\Sql\Select $select
     * @param \Com\Entity\AbstractEntity $entity
     * @return Zend\Db\ResultSet\AbstractResultSet | mixed
     */
    function executeCustomSelect(\Zend\Db\Sql\Select $select, \Com\Entity\AbstractEntity $entity = null)
    {
        // prepare and execute
        $statement = $this->getSql()->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        
        // build result set
        $resultSet = clone $this->resultSetPrototype;
        
        if(is_null($entity))
        {
            $entity = new \Com\Entity\Record();
        }
        
        $resultSet->setArrayObjectPrototype($entity);
        $resultSet->initialize($result);
        
        return $resultSet;
    }


    /**
     *
     * @return string
     */
    function getAdpaterKey()
    {
        return $this->adapterKey;
    }
}
