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

use PHPUnit\Framework\TestCase;
use DataProBoston\MarketingCloud\AuthClient;
use DataProBoston\MarketingCloud\SoapClient;
use DataProBoston\MarketingCloud\Driver;

class DriverTest extends AbstractSoapClientTest
{
    /**
     * @var Driver
     */
    protected static $driver;

    protected static $campaign = [];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$driver = new Driver(self::$client);

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

    public function testEmailSendWorkflow()
    {
        $listId = self::$driver->createList(self::$campaign['name']);

        self::$objectsToDelete[] = ['List', $listId];

        $this->assertInternalType('int', $listId);
        $this->assertGreaterThan(0, $listId);

        self::$driver->createSubscribers(self::$campaign['subscribers'], $listId);

        $emailId = self::$driver->createEmail(self::$campaign['name'], self::$campaign['subject'], self::$campaign['body']);

        self::$objectsToDelete[] = ['Email', $emailId];

        $this->assertInternalType('int', $emailId);
        $this->assertGreaterThan(0, $emailId);

        $senderProfileId = self::$driver->createSenderProfile(self::$campaign['fromName'], self::$campaign['fromName'], self::$campaign['fromEmail']);

        self::$objectsToDelete[] = ['SenderProfile', $senderProfileId];

        $this->assertInternalType('string', $senderProfileId);
        $this->assertEquals(36, strlen($senderProfileId));

        $sendClassificationId = self::$driver->createSendClassification(self::$campaign['fromName'], $senderProfileId, self::$campaign['deliveryProfile']);

        self::$objectsToDelete[] = ['SendClassification', $sendClassificationId];

        $this->assertInternalType('string', $sendClassificationId);
        $this->assertEquals(36, strlen($sendClassificationId));

        $emailSendDefinitionId = self::$driver->createEmailSendDefinition($listId, $emailId, $sendClassificationId);

        self::$objectsToDelete[] = ['EmailSendDefinition', $emailSendDefinitionId];

        $this->assertInternalType('string', $emailSendDefinitionId);
        $this->assertEquals(36, strlen($emailSendDefinitionId));

        $sendId = self::$driver->sendEmailSendDefinition($emailSendDefinitionId);

        $this->assertInternalType('int', $sendId);
        $this->assertGreaterThan(0, $sendId);
    }

}
