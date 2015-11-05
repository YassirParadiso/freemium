<?php

namespace Com\Validator;

use Zend;


class Website extends Zend\Validator\Uri
{
    const INVALID = 'uriInvalid';
    const NOT_WEBSITE = 'notWebsite';

    /**
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String expected",
        self::NOT_WEBSITE => "The input does not appear to be a valid Website" 
    );


    /**
     * Returns true if and only if $value validates as a Website
     *
     * @param string $value
     * @return bool
     */
    public function isValid($value)
    {
        if(! is_string($value))
        {
            $this->error(self::INVALID);
            return false;
        }
        
        if(! preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $value))
        {
            $this->error(self::NOT_WEBSITE);
            return false;
        }
        
        return true;
    }
}