<?php
namespace Com;

use Com;
use Zend;


/**
 * 
 * @author yoterri
 * 
 * @example
 * 
 * 
 * $mailer = $sl->get('Com\Mailer');
 *        
 * $result = true;
 * 
 * try
 * {
 * 
 *    $attachments[] = array(
 *      'path' => '/path/to/file/file1.jpg'
 *      ,'name' => 'Testing.jpg'
 *    );
 * 
 *    $attachments[] = array(
 *       'path' => '/path/to/file/fil-2.jpg'
 *    );
 *        
 *    $message = $mailer->prepareMessage('<b>El texto en html</b>', null, 'testing', $attachments);
 *    $message->addTo('email@email.com');
 *    
 *    $transport = $mailer->getTransport($message, 'smtp1', 'no-reply');
 *    $transport->send($message);
 * }
 * catch(\Exception $e)
 * {
 *    $result = false;
 * }
 * 
 */
class Mailer
{
    
    /**
     * 
     * @param Zend\Mail\Message $message
     * @param string $transportKey
     * @param string $fromKey
     * @param string $replyTo
     * @throws \Exception
     * @return Zend\Mail\Transport\TransportInterface
     */
    function getTransport(Zend\Mail\Message &$message, $transportKey = null, $fromKey = 'no-reply', $replyTo = null)
    {
        $sl = Com\Module::$MVC_EVENT->getApplication()->getServiceManager();
        $config = $sl->get('config');
        
        if(!isset($config['mail']['from'][$fromKey]))
        {
            throw new \Exception("Configuration key '$fromKey' for From field not found.");
        }
        
        $from = $config['mail']['from'][$fromKey];
        $message->setFrom($from['email'], $from['name']);
        
        // 
        if(empty($replyTo))
            $replyTo = $from['email'];
        
        $message->setReplyTo($replyTo);
       
        if(empty($transportKey))
        {
            $transport = new \Zend\Mail\Transport\Sendmail("-f{$replyTo}");
        }
        else
        {
            if(!isset($config['mail']['transport'][$transportKey]))
            {
                throw new \Exception("Configuration key '$transportKey' for Transport not found.");
            }
            
            $options = $config['mail']['transport'][$transportKey]['options'];
            $smptOptions = new Zend\Mail\Transport\SmtpOptions($options);
            
            $transport = new  Zend\Mail\Transport\Smtp($smptOptions);
        }
        
        return $transport;
    }
    
    
    
    /**
     * 
     * @param string $htmlBody
     * @param string $textBody
     * @param string $subject
     * @param array $attachments
     * @return Zend\Mail\Message
     */
    function prepareMessage($htmlBody, $textBody, $subject, array $attachments = array())
    {
        $parts = array();
        
        
        //
        foreach ($attachments as $attachment)
        {
            $fileContents = fopen($attachment['path'], 'r');
        
            $attachmentPart = new Zend\Mime\Part($fileContents);
            $attachmentPart->type = Com\Func\File::getMimeType($attachment['path']);
        
            if(!isset($attachment['name']))
            {
                $pathinfo = pathinfo($attachment['path']);
                $attachmentPart->filename = $pathinfo['basename'];
            }
            else
            {
                $attachmentPart->filename = $attachment['name'];
            }
        
            $attachmentPart->disposition = Zend\Mime\Mime::DISPOSITION_ATTACHMENT;
            $attachmentPart->encoding = Zend\Mime\Mime::ENCODING_BASE64;
            $attachmentPart->type = Zend\Mime\Mime::TYPE_OCTETSTREAM;
        
            $parts[] = $attachmentPart;
        }
        
        //
        if(!empty($htmlBody))
        {
            $htmlPart = new Zend\Mime\Part($htmlBody);
            $htmlPart->type = Zend\Mime\Mime::TYPE_HTML;
            $htmlPart->charset = 'UTF-8';
            $parts[] = $htmlPart;
        }

        //
        if(!empty($textBody))
        {
            $textPart = new Zend\Mime\Part($textBody);
            $textPart->type = Zend\Mime\Mime::TYPE_TEXT;
            $textPart->charset = 'UTF-8';
            
            $parts[] = $textPart;
        }
        
        //
        $body = new Zend\Mime\Message();
        $body->setParts($parts);

        //
        $message = new Zend\Mail\Message();
        $message->setSubject($subject);
        $message->setEncoding('UTF-8');
        $message->setBody($body);
        $message->getHeaders()
            ->get('content-type')
            ->setType('text/html');
        
        return $message;
        
    }
}