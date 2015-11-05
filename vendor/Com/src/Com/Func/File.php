<?php
namespace Com\Func;

class File
{

    /**
     *
     * @param string $file            
     * @return string
     */
    static function getMimeType($file)
    {
        if(function_exists('finfo_files'))
        {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_file($finfo, $file);
            finfo_close($finfo);
        }
        else
        {
            require_once 'vendor/3rdParty/upgradephp/ext/unfinished/mime.php';
            $type = mime_content_type($file);
        }
        
        $arr = array();
        $arr[] = 'application/octet-stream';
        $arr[] = 'text/plain';
        
        if(! $type || in_array($type, $arr))
        {
            $returnCode = null;
            $foo = null;
            $secondOpinion = exec('file -b --mime-type ' . escapeshellarg($file), $foo, $returnCode);
            if($returnCode === 0 && $secondOpinion)
            {
                $type = $secondOpinion;
            }
        }
        
        if(! $type || in_array($type, $arr))
        {
            require_once 'vendor/3rdParty/upgradephp/ext/unfinished/mime.php';
            $exifImageType = exif_imagetype($file);
            if($exifImageType !== false)
            {
                $type = image_type_to_mime_type($exifImageType);
            }
        }
        
        return $type;
    }

    static function fileSize($bytes, $unit = '', $decimals = 2)
    {
        $units = array(
            'B' => 0,
            'KB' => 1,
            'MB' => 2,
            'GB' => 3,
            'TB' => 4,
            'PB' => 5,
            'EB' => 6,
            'ZB' => 7,
            'YB' => 8
        );
        
        $value = 0;
        if($bytes > 0)
        {
            // Generate automatic prefix by bytes
            // If wrong prefix given
            if(! array_key_exists($unit, $units))
            {
                $pow = floor(log($bytes) / log(1024));
                $unit = array_search($pow, $units);
            }
            
            // Calculate byte value by prefix
            $value = ($bytes / pow(1024, floor($units[$unit])));
        }
        
        // If decimals is not numeric or decimals is less than 0
        // then set default value
        if(! is_numeric($decimals) || $decimals < 0)
        {
            $decimals = 2;
        }
        
        // Format output
        return sprintf('%.' . $decimals . 'f' . $unit, $value);
    }
}