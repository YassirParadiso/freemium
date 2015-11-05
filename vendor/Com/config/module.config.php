<?php
return array(
    'view_manager' => array(
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        
        'template_map' => array(
            'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'layout/backend' => __DIR__ . '/../view/layout/backend.phtml',
            
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml' 
        ),
        
        'template_path_stack' => array(
            __DIR__ . '/../view' 
        ) 
    ),
);
