<?php
/**
 * @author <yoterri@ideasti.com>
 * @copyright 2014
 */
namespace Com;

abstract class Singleton
{

    /**
     *
     * @var array
     */
    protected static $data = array();

    /**
     *
     * @example Namespace1.Namespace2.Class, Namespace1_Namespace2_Class
     * @param string $className            
     * @return mixed
     */
    static function getObject($className)
    {
        $object = null;
        $className = self::_normaliceClassName($className);
        
        if(self::isRegistered($className))
        {
            $object = self::$data[$className];
        }
        else
        {
            $object = new $className();
            self::setObject($object);
        }
        
        return $object;
    }

    /**
     *
     * @param object $object            
     * @return mixed
     */
    static function setObject($object)
    {
        $className = get_class($object);
        
        if(! self::isRegistered($className))
        {
            self::$data[$className] = $object;
        }
        
        return $object;
    }

    /**
     *
     * @param object|string $objectOrClassName            
     * @return bool
     */
    static function isRegistered($objectOrClassName)
    {
        if(is_string($objectOrClassName))
        {
            $className = self::_normaliceClassName($objectOrClassName);
        }
        else
        {
            $className = get_class($objectOrClassName);
        }
        
        return isset(self::$data[$className]);
    }

    /**
     *
     * @param string $filename            
     * @return string
     */
    protected static function _normaliceClassName($filename)
    {
        $filename = str_replace('.', '_', $filename);
        
        return implode('_', array_map('ucfirst', explode('_', $filename)));
    }
}