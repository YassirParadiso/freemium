<?php

/**
 *
 * @author <yoterri@ideasti.com>
 * @copyright 2014
 */
namespace Com;

use Zend;


class DataSourceFormatter
{

    /**
     *
     * @var array
     */
    protected $_data = null;


    /**
     *
     * @param array
     */
    function __construct(array $rowset = null)
    {
        if(! is_null($rowset))
            $this->_data = $rowset;
    }


    /**
     *
     * @param array $rowset
     * @return \Com\DataSourceFormatter
     */
    function setDataSource($rowset)
    {
        if($rowset instanceof Zend\Db\ResultSet\ResultSet)
        {
            $rowset = $rowset->toArray();
        }
        
        if(is_array($rowset))
        {
            $this->_data = $rowset;
        }
        else 
        {
            $this->_data = array();
        }
        
        return $this;
    }


    /**
     *
     * @param string|array $textField
     * @param string|array $valueField
     * @example example 1
     * $textField = array('te column is: %colname_1% xxx, other value %colname_2%', array('%colname_1%'=>'colname_1', '%colname_2%'=>'colname_2'));
     * $valueField = array('te column is: %colname_3% xxx, other value %colname_4%', array('%colname_3%'=>'colname_3', '%colname_4%'=>'colname_4'));
     * $formatter->toFormSelect($textField, $valueField);
     *
     * example 2
     * $textField = 'column_1';
     * $valueField = 'column_2';
     * $formatter->toFormSelect($textField, $valueField);
     *
     * @return array
     */
    function toFormSelect($textField, $valueField)
    {
        $r = array();
        $textIsArray = is_array($textField);
        $valueIsArray = is_array($valueField);
        
        foreach($this->_data as $row)
        {
            if($textIsArray)
                $text = $this->_arrayFormatting($textField, $row);
            else
            {
                if(is_array($row))
                    $text = $row[$textField];
                else
                    $text = $row->$textField;
            }
            
            if($valueIsArray)
                $value = $this->_arrayFormatting($valueField, $row);
            else
            {
                if(is_array($row))
                    $value = $row[$valueField];
                else
                    $value = $row->$valueField;
            }
            
            $r[$value] = $text;
        }
        
        return $r;
    }


    /**
     *
     * @param string|array $item
     * @example example 1
     * $item = array('%colname_1%'=>'column_1', '%colname_2%'=>'column_2');
     * $formatter->toFormSelectSelected($item);
     *
     * example 2
     * $item = 'id';
     * $formatter->toFormSelectSelected($item);
     *
     * @return array
     */
    function toFormSelectSelected($item)
    {
        $r = array();
        
        $itemIsArray = is_array($item);
        
        foreach($this->_data as $row)
        {
            if($itemIsArray)
                $text = $this->_arrayFormatting($item, $row);
            else
            {
                if(is_array($row))
                    $text = $row[$item];
                else
                    $text = $row->$item;
            }
            
            $r[] = $text;
        }
        
        return $r;
    }


    /**
     *
     * @param array $fields
     * @return string
     * @example example 1
     * $fields = array('f1', 'f2');
     * $formatter->toJson($fields);
     *
     * example 2
     * $fields = array('f1' => 'field 1', 'f2' => 'field 2');
     * $formatter->toJson($fields);
     */
    function toJson(array $fields = array())
    {
        $r = array();
        $data = $this->_data;
        
        if(count($fields) > 0)
        {
            $c = 0;
            foreach($data as $row)
            {
                foreach($fields as $key => $field)
                {
                    if(is_int($key))
                    {
                        if(isset($row[$field]))
                        {
                            if(is_array($row))
                                $r[$c][$field] = $row[$field];
                            else
                                $r[$c][$field] = $row->$field;
                        }
                    }
                    else
                    {
                        if(isset($row[$key]))
                        {
                            if(is_array($key))
                                $r[$c][$field] = $row[$key];
                            else
                                $r[$c][$field] = $row->$key;
                        }
                    }
                }
                
                $c ++;
            }
        }
        else
        {
            $r = $data;
        }
        
        return Zend\Json\Json::encode($r);
    }


    /**
     *
     * @param array $fields
     * @return array
     * @example example 1
     * $fields = array('f1', 'f2');
     * $formatter->toArray($fields);
     *
     * example 2
     * $fields = array('f1' => 'field 1', 'f2' => 'field 2');
     * $formatter->toArray($fields);
     */
    function toArray(array $fields = array())
    {
        $r = array();
        $data = $this->_data;
        
        if(count($fields) > 0)
        {
            $c = 0;
            foreach($data as $row)
            {
                foreach($fields as $key => $field)
                {
                    if(is_int($key))
                    {
                        if(isset($row[$field]))
                        {
                            if(is_array($row))
                                $r[$c][$field] = $row[$field];
                            else
                                $r[$c][$field] = $row->$field;
                        }
                    }
                    else
                    {
                        if(isset($row[$key]))
                        {
                            $r[$c][$field] = $row[$key];
                        }
                    }
                }
                
                $c ++;
            }
        }
        else
        {
            $r = $data;
        }
        
        return $r;
    }


    protected function _arrayFormatting($array, $row)
    {
        $vars = array();
        
        foreach($array[1] as $key => $value)
        {
            if(is_array($row))
                $vars[$key] = $row[$value];
            else
                $vars[$key] = $row->$value;
        }
        
        return $this->_replace($array[0], $vars);
    }


    protected function _replace($str, array $vars = array(), $chrStart = '', $chrEnd = '')
    {
        if(! empty($chrStart) || ! empty($chrEnd))
        {
            foreach($vars as $key => $val)
            {
                $key = $chrStart . $key . $chrEnd;
                $vars[$key] = $val;
            }
        }
        
        return strtr($str, $vars);
    }
}