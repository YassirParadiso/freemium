<?php

namespace Com\DataGrid;

use Zend, ZfcDatagrid;


abstract class AbstractDataGrid
{

    /**
     *
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     *
     * @var \ZfcDatagrid\Datagrid
     */
    protected $grid;

    /**
     *
     * @var array
     */
    protected $viewVars = array();

    /**
     *
     * @var array
     */
    protected $params = array();

    /**
     *
     * @var mixed
     */
    protected $dataSource;

    /**
     *
     * @var array
     */
    protected $columns;


    /**
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     */
    function __construct(\Zend\ServiceManager\ServiceLocatorInterface $sl = null, array $viewVars = array())
    {
        if($sl)
        {
            $this->setServiceLocator($sl);
        }
        
        $this->setViewVars($viewVars);
    }


    /**
     *
     * @param string $key
     * @param mixed $value
     * @return \Com\DataGrid\AbstractDataGrid
     */
    function addParam($key, $value)
    {
        $this->params[$key] = $value;
        return $this;
    }


    /**
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    function getParam($key, $mixed = null)
    {
        $v = $mixed;
        
        if(isset($this->params[$key]))
        {
            $v = $this->params[$key];
        }
        
        return $v;
    }


    /**
     *
     * @param ZfcDatagrid\Column\AbstractColumn $col
     * @return \Com\DataGrid\AbstractDataGrid
     */
    function addColumn(ZfcDatagrid\Column\AbstractColumn $col)
    {
        $this->columns[] = $col;
        return $this;
    }


    /**
     *
     * @param array $viewVars
     * @return \Com\DataGrid\AbstractDataGrid
     */
    function setViewVars(array $viewVars)
    {
        $this->viewVars = $viewVars;
        return $this;
    }


    /**
     *
     * @return array
     */
    function getViewVars()
    {
        return $this->viewVars;
    }


    /**
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return \Com\DataGrid\AbstractDataGrid
     */
    function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $sl)
    {
        $this->serviceLocator = $sl;
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
     * @return \Zend\Mvc\Controller\Plugin\Url
     */
    function url()
    {
        $pl = $this->serviceLocator->get('ControllerPluginManager');
        return $pl->get('url');
    }


    /**
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    function getRequest()
    {
        return $this->serviceLocator->get('request');
    }


    /**
     *
     * @return \Zend\Mvc\Router\Http\TreeRouteStack
     */
    function getRouter()
    {
        return $this->serviceLocator->get('router');
    }


    /**
     *
     * @return \ZfcDatagrid\Datagrid
     */
    function getGrid()
    {
        if(is_null($this->grid))
        {
            $this->grid = $this->serviceLocator->get('ZfcDatagrid\Datagrid');
            $this->grid->setDefaultItemsPerPage(15);
            $this->grid->setToolbarTemplate('grid-toolbar');
            
            $sl = $this->getServiceLocator();
            if($sl)
            {
                $this->grid->setServiceLocator($sl);
            }
        }
        
        return $this->grid;
    }


    /**
     *
     * @return \Zend\Authentication\AuthenticationService
     */
    function getAuth()
    {
        return $this->serviceLocator->get('AuthService');
    }


    /**
     *
     * @return int
     */
    function getUserId()
    {
        $userId = 0;
        $auth = $this->getAuth();
        
        if($auth->hasIdentity())
        {
            $identity = $auth->hasIdentity();
            
            $userId = $identity['id'];
        }
        
        return $userId;
    }


    /**
     *
     * @return \Zend\Mvc\Router\Http\RouteMatch
     */
    function getRouteMath()
    {
        $request = $this->getRequest();
        $router = $this->getRouter();
        
        return $routeMatch = $router->match($request);
    }


    /**
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    function getAdapter()
    {
        return $this->serviceLocator->get('adapter');
    }


    /**
     *
     * @return \Zend\Http\Response\Stream
     */
    function render()
    {
        $this->setupDataSource();
        $this->setupColumns();
        
        $grid = $this->getGrid();
        $grid->setDataSource($this->dataSource, $this->getAdapter());
        
        foreach($this->columns as $col)
        {
            $grid->addColumn($col);
        }
        
        $grid = $this->getGrid();
        $grid->render();
        
        return $grid->getResponse()->setVariables($this->getViewVars());
    }


    abstract function setupColumns();


    abstract function setupDataSource();
}