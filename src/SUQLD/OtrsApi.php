<?php

namespace SUQLD;

class OtrsApi
{

    private $log = [];

    private $url;
    private $username;
    private $password;

    private $client;

    public function __construct($url, $username, $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->Client();
    }

    /**
     * Connect to the Ticket System
     */
    public function Client($URI = 'Core')
    {
        $this->client = new \SoapClient(
            null, [
                'location' => $this->url,
                'uri' => $URI,
                'trace' => 1,
                'login' => $this->username,
                'password' => $this->password,
                'style' => SOAP_RPC,
                'use' => SOAP_ENCODED
            ]
        );
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get any log items created
     */
    public function getLog()
    {
        return $this->log;
    }


    /**
     * Send the data to the Ticket System and process the response
     */
    public function send($requestData)
    {
        try {
            array_unshift(
                $requestData,
                $this->username,
                $this->password
            );

            $result = $this->client->__soapCall('Dispatch', $requestData);

            if (is_array($result)) {
                $new_result = [];
                $result = array_values($result);
                for ($c = 0; $c < sizeof($result); $c = $c + 2) {
                    $new_result[$result[$c]] = $result[$c + 1];
                }
                $result = $new_result;
            }

            return $result;
        } catch (Exception $e) {
            // This is not how we should handle an exception
            return false;
        }

    }

    /**
     * Conversion of default with supplied data
     */
    public function createData($defaults, $columns = [], $updateData = [])
    {
        $request = $defaults;

        foreach ($columns as $column) {
            if (isset($updateData[$column])) {
                $request[$column] = $updateData[$column];
            }
        }

        return $request;
    }

    /**
     * Create a new ticket
     */
    public function createTicket($updateData, $createdBy)
    {
        $send_request = true;

        $defaults = [
            "TicketObject" => "TicketCreate",
            "Lock" => "Unlock",
            "PriorityID" => 2,
            "State" => "new",
            "OwnerID" => 1,
            "UserID" => 1,
        ];

        $columns = [
            'Title',
            'Queue',
            'QueueID',
            'Lock',
            'Priority',
            'PriorityID',
            'State',
            'StateID',
            'Type',
            'TypeID',
            'Service',
            'ServiceID ',
            'SLA',
            'SLAID',
            'CustomerID',
            'CustomerUser',
            'OwnerID',
            'ResponsibleID',
            'ArchiveFlag',
            'UserID'
        ];

        $request = $this->createData($defaults, $columns, $updateData);

        if (!$updateData['Title']) {
            $send_request = false;
            $this->log[] = 'Title is empty.';
        }
        if (!$updateData['Queue'] && !$updateData['QueueID']) {
            $send_request = false;
            $this->log[] = 'Queue/QueueID is empty.';
        }
        if (!$updateData['CustomerUser'] && !$updateData['CustomerID']) {
            $send_request = false;
            $this->log[] = 'CustomerID or CustomerUser is empty.';
        }
        if (!$send_request) {
            return false;
        }

        $ticket_id = $this->send($request);
        if ($ticket_id) {
            $defaults = [
                "TicketObject" => "ArticleCreate",
                "TicketID" => $ticket_id,
                "ArticleType" => "webrequest",
                "SenderType" => "system",
                "HistoryType" => "WebRequestCustomer",
                "HistoryComment" => $createdBy,
                "ContentType" => "text/plain; charset=ISO-8859-1",
                "UserID" => 1,
                "Loop" => 0,
                "AutoResponseType" => 'auto reply',
                "OrigHeader" => [
                    'From' => $updateData['From'],
                    'To' => 'Postmaster',
                    'Subject' => $updateData['Title'],
                    'Body' => $updateData['Body'],
                ],
            ];
            $columns = [
                'From',
                'Title',
                'Body',
            ];
            $request = $this->createData($defaults, $columns, $updateData);
            $ArticleID = $this->send($request);
        }

        return $ticket_id;
    }

    /**
     * Add a note to the ticket
     */
    public function addNote($TicketID, $updateData, $createdBy)
    {
        $send_request = true;

        $defaults = [
            "TicketObject" => "ArticleCreate",
            "TicketID" => $TicketID,
            "ArticleType" => "note-internal",
            "SenderType" => "system",
            "HistoryType" => "WebRequestCustomer",
            "HistoryComment" => $createdBy,
            "ContentType" => "text/plain; charset=ISO-8859-1",
            "UserID" => 1,
            "Loop" => 0,
            "NoAgentNotify" => 1
        ];

        $columns = [
            'Subject',
            'Body',
            'NoAgentNotify',
            'ArticleType',
        ];

        if (!$updateData['Subject']) {
            $send_request = false;
            $this->log[] = 'Subject is empty.';
        }

        if (!$updateData['Body']) {
            $send_request = false;
            $this->log[] = 'Body is empty.';
        }

        if ($send_request) {
            $request = $this->createData($defaults, $columns, $updateData);
            $ArticleID = $this->send($request);
        }
    }

    /**
     * Get the Ticket Number
     */
    public function number($TicketID)
    {
        $defaults = [
            'TicketObject' => 'TicketNumberLookup',
            'TicketID' => $TicketID,
        ];

        $request = $this->createData($defaults);
        $TicketNumber = $this->send($request);

        $TicketNumber = number_format($TicketNumber, 0, '.', '');

        return $TicketNumber;
    }

    /**
     * Get the database TicketID
     */
    public function id($TicketNumber)
    {
        $defaults = [
            'TicketObject' => 'TicketIDLookup',
            'TicketNumber' => $TicketNumber,
        ];

        $request = $this->createData($defaults);
        $TicketID = $this->send($request);

        return $TicketID;
    }

    /**
     * Retrieve a ticket subject
     */
    public function outgoingSubject($TicketNumber, $updateData)
    {
        $send_request = true;

        $defaults = [
            'TicketObject' => 'TicketSubjectBuild',
            'TicketNumber' => $TicketNumber,
        ];

        $columns = [
            'Subject',
            'Action',
            'Type',
            'NoCleanup',
        ];

        if (!$updateData['Subject']) {
            $send_request = false;
            $this->log[] = 'Subject is empty.';
        }

        if ($send_request) {
            $request = $this->createData($defaults, $columns, $updateData);
            $subject = $this->send($request);
            return $subject;
        } else {
            return false;
        }
    }


    /**
     * Get Ticket Information
     */
    public function get($TicketID, $Extended = false)
    {
        $defaults = [
            'TicketObject' => 'TicketGet',
            'TicketID' => $TicketID,
            'Extended' => (int)$Extended,
        ];
        $request = $this->createData($defaults);
        $body = $this->send($request);
        return $body;
    }


    /**
     * Move a Ticket to a queue
     */
    public function move($TicketID, $Queue, $UserID = 1)
    {
        $defaults = [
            'TicketObject' => 'TicketQueueSet',
            'TicketID' => $TicketID,
            'UserID' => $UserID,
        ];

        if (is_int($Queue)) {
            $defaults['QueueID'] = $Queue;
        } else {
            $defaults['Queue'] = $Queue;
        }

        $request = $this->createData($defaults);
        $success = $this->send($request);
        return $success;
    }

} 