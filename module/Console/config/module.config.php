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

                'disable-instances' => array(
                    'options' => array(
                        'route' => 'disable-instances',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'disable-instances'
                        ),
                    ),
                ),

                'mdata-sync' => array(
                    'options' => array(
                        'route' => 'mdata-sync',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'mdata-sync'
                        ),
                    ),
                ),

                
                /*'remove-accounts' => array(
                    'options' => array(
                        'route' => 'remove-accounts',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Console\Controller',
                            'controller' => 'Index',
                            'action' => 'remove-accounts'
                        ),
                    ),
                ),*/
            )
        )
    ),
);
