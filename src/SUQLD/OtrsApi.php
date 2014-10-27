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
    private function send($requestData)
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
        } catch (\Exception $e) {
            // This is not how we should handle an exception
            return false;
        }

    }

    /**
     * Create a new ticket
     */
    public function createTicket(
        $title,
        $queue = null,
        $queueID = null,
        $customerUser = null,
        $customerID = null,
        $lockState = 'unlock',
        $priorityID = 2,
        $state = 'new',
        $ownerID = 1,
        $userID = 1
        )
    {
        if (strlen(trim($title)) == 0) {
            throw new \Exception('Need a title. Title is empty');
        }
        if (!$queue && !$queueID) {
            throw new \Exception('Queue/QueueID is empty');
        }
        if (!$customerUser && !$customerID) {
            throw new \Exception('CustomerID or CustomerUser is empty');
        }

        $request = array(
            "TicketObject", "TicketCreate",
            "Title", $title,
            "Lock", $lockState,
            "PriorityID", $priorityID,
            "State", $state,
            "OwnerID", $ownerID,
            "UserID", $userID
        );

        if ($queueID) {
            $request[] = "QueueID";
            $request[] = $queueID;
        } else {
            $request[] = "Queue";
            $request[] = $queue;
        }

        if ($customerID) {
            $request[] = "CustomerID";
            $request[] = $customerID;
        }
        if ($customerUser) {
            $request[] = "CustomerUser";
            $request[] = $customerUser;
        }

        // Returns the TicketID
        return $this->send($request);
    }

    public function addArticle(
        $ticketID,
        $createdBy,
        $userID,
        $subject,
        $body,
        $articleType = 'webrequest',
        $from = null,
        $contentType = 'text/plain; charset=ISO-8859-1'
    )
    {

        if (strlen(trim($subject)) == 0) {
            throw new \Exception('Need a subject. Subject is empty');
        }
        if (strlen(trim($body)) == 0) {
            throw new \Exception('Need a body. Body is empty');
        }
        if (strlen(trim($articleType)) == 0) {
            throw new \Exception('Article Type can not be empty.');
        }
        if (!is_int($ticketID)) {
            throw new \Exception('TicketID needs to be an integer');
        }

        $request =  [
            "TicketObject", "ArticleCreate",
            "TicketID", $ticketID,
            "ArticleType", $articleType,
            "SenderType", "system",
            "HistoryType", "WebRequestCustomer",
            "HistoryComment", $createdBy,
            "Subject", $subject,
            "ContentType", $contentType,
            "Body", $body,
            "UserID", $userID,

            ];

        switch ($articleType) {
            case 'note-internal':
                $request = array_merge($request, [
                    "NoAgentNotify", 1,
                ]);
                break;
            case 'webrequest':
                $request = array_merge($request, [
                    "Loop", 0,
                    "From", $from,
                    "AutoResponseType", 'auto reply',
                    "OrigHeader", [
                        'From' => $from,
                        'To' =>  'Postmaster',
                        'Subject' => $subject,
                        'Body' => $body,
                    ]
                ]);
                break;
        }

        $articleID = $this->send($request);

        return $articleID;
    }

    /**
     * Attach file to ticket
     */

    public function attachFileToArticle($articleID, $filePath, $fileName, $mimeType)
    {
        $request = array(
            "TicketObject", "ArticleWriteAttachment",
            "Content", new \SoapVar(file_get_contents($filePath),XSD_BASE64BINARY, 'xsd:base64Binary'),
            "ContentType", $mimeType,
            "Filename", $fileName,
            "ArticleID", $articleID,
            "UserID", 1
        );

        return $this->send($request);
    }

    /**
     * Get the Ticket Number
     */
    public function getTicketNumber($TicketID)
    {
        $request = [
            'TicketObject', 'TicketNumberLookup',
            'TicketID', $TicketID,
        ];

        $TicketNumber = $this->send($request);

        $TicketNumber = number_format($TicketNumber, 0, '.', '');

        return $TicketNumber;
    }

    /**
     * Get the database TicketID
     */
    public function getID($TicketNumber)
    {
        $request = [
            'TicketObject', 'TicketIDLookup',
            'TicketNumber', $TicketNumber,
        ];

        $TicketID = $this->send($request);

        return $TicketID;
    }

    /**
     * Get Ticket Information
     */
    public function getTicket($TicketID, $Extended = false)
    {
        $request = [
            'TicketObject' , 'TicketGet',
            'TicketID', $TicketID,
            'Extended', (int)$Extended,
        ];
        $body = $this->send($request);
        return $body;
    }


    /**
     * Move a Ticket to a queue
     */
    public function moveTicket($TicketID, $Queue, $UserID = 1)
    {
        $request = [
            'TicketObject', 'TicketQueueSet',
            'TicketID', $TicketID,
            'UserID', $UserID,
        ];

        if (is_int($Queue)) {
            $request[] = 'QueueID';
            $request[] = $Queue;
        } else {
            $request[] = 'Queue';
            $request[] = $Queue;
        }
        $success = $this->send($request);
        return $success;
    }

} 
