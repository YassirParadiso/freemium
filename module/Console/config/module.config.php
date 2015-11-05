<?php 
return array(

    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    
    
    'console' => array(
        'router' => array(
            'routes' => array(
            
                'create-databases' => array(
                    'options' => array(
                        'route' => 'create-databases',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'create-databases'
                        ),
                    ),
                ),
                
                'run-cron' => array(
                    'options' => array(
                        'route' => 'run-cron',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'run-cron'
                        ),
                    ),
                ),
                
                'remove-accounts' => array(
                    'options' => array(
                        'route' => 'remove-accounts',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'remove-accounts'
                        ),
                    ),
                ),
            )
        )
    ),
);
