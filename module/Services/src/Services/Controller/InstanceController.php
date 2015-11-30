<?php
namespace Services\Controller;

use Zend, Com, Zend\View\Model\JsonModel;
 

class InstanceController extends Com\Controller\AbstractController
{


    function getDueDateAction()
    {
        $this->layout('layout/blank');
        $this->basicAuthentication('webservices');

        $sl = $this->getServiceLocator();
        $com = $this->getCommunicator();
        $instance = $this->_params('instance');

        if($instance)
        {
            $dbClient = $sl->get('App\Db\Client');
            $where = array(
                'domain' => $instance
            );

            $row = $dbClient->findBy($where)->current();
            if($row)
            {
                $dueDate = null;
                $difference = null;
                $dueOn = null;
                if($row->due_date)
                {
                    
                    $dueDate = $row->due_date;
                    $time = strtotime($dueDate);
                    $dueOn = date('M d, Y', $time);
                    $date = date('M d, Y', $time);
                    $today = date('Y-m-d');

                    $datetime1 = new \DateTime($row->due_date);
                    $datetime2 = new \DateTime($today);
                    $interval = $datetime2->diff($datetime1);
                    $difference = (int)$interval->format('%R%a');
                }

                $data = array(
                    'due_date' => $dueDate
                    ,'due_on' => $dueOn
                    ,'due_days' => $difference
                );

                $com->setData($data);
            }
            else
            {
                $com->addError('Instance not found');
            }
        }
        else
        {
            $com->addError('Missing instance name');
        }
        
        $json = $com->toArray();

        $result = new Zend\View\Model\JsonModel($json);
        return $result;
    }
}