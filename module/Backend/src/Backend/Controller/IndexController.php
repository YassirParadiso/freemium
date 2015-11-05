<?php

namespace Backend\Controller;

use Zend, Com;
use Zend\Dom\Document;


class IndexController extends Com\Controller\BackendController
{


    function dashboardAction()
    {
        $this->assign('a', 'b');
        
        return $this->viewVars;
    }


    function clearCacheAction()
    {
        $arr[] = realpath('data/ZfcDatagrid');
        $arr[] = realpath('data/cache');
        
        foreach($arr as $cacheDir)
        {
            $handle = @opendir($cacheDir);
            if($handle)
            {
                while(false !== ($entry = readdir($handle)))
                {
                    if('.' != $entry && '..' != $entry)
                    {
                        if(is_dir("$cacheDir/$entry"))
                        {
                            $files = glob("$cacheDir/$entry/{,.}*", GLOB_BRACE); // get all file names
                            foreach($files as $file)
                            {
                                if(is_file($file))
                                {
                                    echo "unlink: $file<hr>";
                                    unlink($file); // delete file
                                }
                            }
                            
                            echo "rmdir: $cacheDir/$entry<hr>";
                            rmdir("$cacheDir/$entry");
                        }
                        else
                        {
                            echo "unlink: $cacheDir/$entry<hr>";
                            unlink("$cacheDir/$entry"); // delete file
                        }
                    }
                }
                
                closedir($handle);
            }
        }
        
        exit();
    }
}