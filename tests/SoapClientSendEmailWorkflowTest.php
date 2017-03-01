<?php

/*
 * This file is part of DataProBoston Salesforce Marketing Cloud PHP SDK.
 *
 * (c) 2017 Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DataProBoston\MarketingCloud\Tests;

class SoapClientSendEmailWorkflowTest extends AbstractSoapClientTest
{
    protected static $campaign = [];

    /**
     * @var int Time to wait after send before tracking events.
     */
    protected static $sendWaitTime = CAMPAIGN_SEND_WAIT_TIME;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$campaign = [
            'name' => CAMPAIGN_NAME,
            'fromEmail' => CAMPAIGN_FROM_EMAIL,
            'fromName' => CAMPAIGN_FROM_NAME,
            'subscribers' => explode(',', CAMPAIGN_SUBSCRIBERS),
            'subject' => CAMPAIGN_SUBJECT,
            'body' => CAMPAIGN_BODY,
            'deliveryProfile' => CAMPAIGN_DELIVERY_PROFILE,
        ];
    }

    public function testCreateList()
    {
        $list = [
            'ListName' => self::$campaign['name'],
        ];

        $listId = self::$client->create('List', $list);

        $this->assertInternalType('int', $listId);
        $this->assertGreaterThan(0, $listId);

        self::$objectsToDelete[] = ['List', $listId];

        return $listId;
    }

    /**
     * @depends testCreateList
     */
    public function testCreateSubscribers($listId)
    {
        $subscribers = [];

        foreach (self::$campaign['subscribers'] as $subscriber) {
            $subscribers[] = [
                'EmailAddress' => $subscriber,
                'Lists' => ['ID' => $listId],
            ];
        }

        $subscribersIds = self::$client->create('Subscriber', $subscribers, true);

        $this->assertInternalType('array', $subscribersIds);
        $this->assertCount(count(self::$campaign['subscribers']), $subscribersIds);
        $this->assertContainsOnly('int', $subscribersIds);
    }

    /**
     * @depends testCreateSubscribers
     */
    public function testCreateEmail()
    {
        $email = [
            'Name' => self::$campaign['name'],
            'Subject' => self::$campaign['subject'],
            'HTMLBody' => self::$campaign['body'],
            'EmailType' => 'HTML',
            'IsHTMLPaste' => 'true',
            'CharacterSet' => 'UTF-8',
        ];

        $emailId = self::$client->create('Email', $email);

        $this->assertInternalType('int', $emailId);
        $this->assertGreaterThan(0, $emailId);

        self::$objectsToDelete[] = ['Email', $emailId];

        return $emailId;
    }

    /**
     * @depends testCreateEmail
     */
    public function testCreateSenderProfile()
    {
        $senderProfile = [
            'Name' => self::$campaign['fromName'],
            'FromName' => self::$campaign['fromName'],
            'FromAddress' => self::$campaign['fromEmail'],
        ];

        $senderProfileId = self::$client->create('SenderProfile', $senderProfile);

        $this->assertInternalType('string', $senderProfileId);
        $this->assertEquals(36, strlen($senderProfileId));

        self::$objectsToDelete[] = ['SenderProfile', $senderProfileId];

        return $senderProfileId;
    }

    /**
     * @depends testCreateSenderProfile
     */
    public function testCreateSendClassification($senderProfileId)
    {
        $sendClassification = [
            'Name' => self::$campaign['fromName'],
            'SenderProfile' => [
                'ObjectID' => $senderProfileId,
            ],
            'DeliveryProfile' => [
                'CustomerKey' => self::$campaign['deliveryProfile'],
            ],
        ];

        $sendClassificationId = self::$client->create('SendClassification', $sendClassification);

        $this->assertInternalType('string', $sendClassificationId);
        $this->assertEquals(36, strlen($sendClassificationId));

        self::$objectsToDelete[] = ['SendClassification', $sendClassificationId];

        return $sendClassificationId;
    }

    /**
     * @depends testCreateSendClassification
     * @depends testCreateList
     * @depends testCreateEmail
     */
    public function testCreateEmailSendDefinition($sendClassificationId, $listId, $emailId)
    {
        $emailSendDefinition = [
            'Email' => ['ID' => $emailId],
            'Name' => $emailId,
            'IsMultipart' => 'true',
            'SendDefinitionList' => [
                'List' => ['ID' => $listId],
                'DataSourceTypeID' => 'List',
            ],
            'SendClassification' => [
                'ObjectID' => $sendClassificationId,
            ],
        ];

        $emailSendDefinitionId = self::$client->create('EmailSendDefinition', $emailSendDefinition);

        $this->assertInternalType('string', $emailSendDefinitionId);
        $this->assertEquals(36, strlen($emailSendDefinitionId));

        self::$objectsToDelete[] = ['EmailSendDefinition', $emailSendDefinitionId];

        return $emailSendDefinitionId;
    }

    /**
     * @depends testCreateEmailSendDefinition
     */
    public function testPerformEmailSendDefinition($emailSendDefinitionId)
    {
        $request = [
                'ObjectID' => $emailSendDefinitionId,
            ];

        $sendId = self::$client->perform('EmailSendDefinition', $request, 'start');

        $this->assertInternalType('int', $sendId);
        $this->assertGreaterThan(0, $sendId);

        return $sendId;
    }

    /**
     * @depends testPerformEmailSendDefinition
     */
    public function testRetrieveSend($sendId)
    {
        $properties = [
            // int properties
            'Duplicates', 'InvalidAddresses', 'ExistingUndeliverables', 'ExistingUnsubscribes', 'HardBounces',
            'SoftBounces', 'OtherBounces', 'ForwardedEmails', 'UniqueClicks', 'UniqueOpens', 'NumberSent', 'NumberDelivered',
            'Unsubscribes', 'MissingAddresses', 'NumberTargeted', 'NumberErrored', 'NumberExcluded',
            // other properties
            'Status', 'PreviewURL', 'SendDate', 'SentDate',
        ];

        // Status known values: 'Scheduled'|'Sending'|'Complete'

        $filter = ['ID', '=', $sendId];

        $send = self::$client->retrieveOne('Send', $properties, $filter);

        $this->assertInstanceOf('stdClass', $send);
        $this->assertEquals('Scheduled', $send->Status);
    }

    /**
     * @depends testPerformEmailSendDefinition
     */
    public function testRetrieveSentEvents($sendId)
    {
        $this->markTestSkipped();

        sleep(self::$sendWaitTime);

        $properties = ['Status'];
        $filter = ['ID', '=', $sendId];
        $send = self::$client->retrieveOne('Send', $properties, $filter);

        $this->assertInstanceOf('stdClass', $send);
        $this->assertEquals('Complete', $send->Status);
        $this->assertEquals(count(self::$campaign['subscribers']), $send->NumberSent);


        $startDate = new \DateTime();
        $finishDate = new \DateTime();
        $startDate->modify(sprintf('-%s seconds', self::$sendWaitTime));

        $properties = ['SubscriberKey', 'EventDate'];

        $filter = [
            ['SendID', '=', $sendId],
            'and',
            ['EventDate', 'between', [$startDate, $finishDate]]
        ];

        $sentEvents = self::$client->retrieve('SentEvent', $properties, $filter);

        $this->assertInternalType('array', $sentEvents);
        $this->assertCount(count(self::$campaign['subscribers']), $sentEvents);
        $this->assertContainsOnlyInstancesOf('stdClass', $sentEvents);
    }

}
