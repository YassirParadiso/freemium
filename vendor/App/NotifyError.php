<?php

namespace App;

use Zend, Com;

class NotifyError
{


    static function notify($error, $subject = 'Freemium ERROR!')
    {
        $sl = Com\Module::$MVC_EVENT->getApplication()->getServiceManager();

        $config = $sl->get('config');
        $emails = $config['freemium']['mail_to_errors'];

        if(!is_array($emails) || !count($emails))
        {
            return;
        }

        try
        {
            $mailer = new Com\Mailer();

            if($error instanceof \Exception)
            {
                $m = '';
                $m .= "<p><strong>{$error->getMessage()}</strong></p>";
                $m .= "<strong>File:</strong> {$error->getFile()} ({$error->getLine()})";
                $m .= '<pre>';
                $m .= $error->getTraceAsString();
                $m .= '</pre>';

                $error = $m;
            }
                
            // prepare the message to be send
            $message = $mailer->prepareMessage($error, null, $subject);
            foreach($emails as $mail)
            {
                $message->addTo($client->email);
            }

            // prepare de mail transport and send the message
            $transport = $mailer->getTransport($message, 'smtp1', 'sales');
            $transport->send($message);
        }
        catch(\Exception $e)
        {
            ;
        }
    }
}