<?php
return array(
    
    'router' => array(
        'routes' => array(
            
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Index',
                        'action' => 'home' 
                    ) 
                ) 
            ),
            
            'apps' => array(
                'type' => 'Zend\Mvc\Router\Http\segment',
                'options' => array(
                    'route' => '/apps/:controller/:action',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Apps\Controller',
                            'controller' => 'Shopify',
                        'action' => 'app' 
                    ) 
                ),
                
                'may_terminate' => true,
                
                'child_routes' => array(
                    'wildcard' => array(
                        'type' => 'Wildcard' 
                    ) 
                ) 
            ),
            
            'internal' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/internal',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Index',
                        'action' => 'internal' 
                    ) 
                ) 
            ),
            
            'test' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/test',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Index',
                        'action' => 'test' 
                    ) 
                ) 
            ),
            
            'ajax' => array(
                'type' => 'Zend\Mvc\Router\Http\segment',
                'options' => array(
                    'route' => '/ajax/:action',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Ajax' 
                    ) 
                ),
                
                'may_terminate' => true,
                
                'child_routes' => array(
                    'wildcard' => array(
                        'type' => 'Wildcard' 
                    ) 
                ) 
            ),
            
            'backend' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route' => '/backend[/:controller][/:action]',
                    'constraints' => array(),
                    'defaults' => array(
                        '__NAMESPACE__' => 'Backend\Controller',
                        'controller' => 'Index',
                        'action' => 'dashboard' 
                    ) 
                ),
                
                'may_terminate' => true,
                
                'child_routes' => array(
                    'wildcard' => array(
                        'type' => 'Wildcard' 
                    ) 
                ) 
            ),
            
            'auth' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route' => '/auth/[:action]',
                    'defaults' => array(
                        'controller' => 'Front\Controller\Auth',
                        'action' => 'login' 
                    ) 
                ),
                
                'may_terminate' => true,
                
                'child_routes' => array(
                    'wildcard' => array(
                        'type' => 'Wildcard' 
                    ) 
                ) 
            ) 
        ) 
    ) 
);
