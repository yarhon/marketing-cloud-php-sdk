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

use DataProBoston\MarketingCloud\Exception\ResponseException;

class SoapClientUtilitiesTest extends AbstractSoapClientTest
{
    public function testVersionInfo()
    {
        $versionInfo = self::$client->versionInfo(false);

        $this->assertInstanceOf('stdClass', $versionInfo);
        $this->assertObjectHasAttribute('Version', $versionInfo);

        $version = $versionInfo->Version;

        $this->assertGreaterThan(0, strlen($version));
    }

    public function testSystemStatus()
    {
        $possibleStatuses = ['OK', 'InMaintenance', 'UnplannedOutage'];

        $systemStatus = self::$client->systemStatus();

        $this->assertInternalType('string', $systemStatus);
        $this->assertContains($systemStatus, $possibleStatuses);
    }

    public function testDescribe()
    {
        $description = self::$client->describe('Subscriber');

        $this->assertInternalType('array', $description);
        $this->assertGreaterThan(0, count($description));
        $this->assertContainsOnlyInstancesOf('stdClass', $description);

        $this->expectException(ResponseException::class);
        self::$client->describe('Subscriber2');
    }

    public function testDescribeSubscriberAttributes()
    {
        $attributes = self::$client->describeSubscriberAttributes();

        $this->assertInternalType('array', $attributes);
        $this->assertGreaterThan(0, count($attributes));
        $this->assertContainsOnlyInstancesOf('stdClass', $attributes);
    }

    public function testGetFilterOperators()
    {
        $operators = self::$client->getFilterOperators();

        $this->assertInternalType('array', $operators);
        $this->assertGreaterThan(0, count($operators));
        $this->assertContainsOnly('string', $operators);
    }

    public function testRequestEndpoint()
    {
        $endpoint = self::$client->requestEndpoint();

        $this->assertInternalType('string', $endpoint);
        $this->assertStringStartsWith('http', $endpoint);
    }

    public function testExecute()
    {
        // @todo: https://developer.salesforce.com/docs/atlas.en-us.noversion.mc-apis.meta/mc-apis/getting_a_usertoken_for_entity_direct_login.htm

        $requests = [
            'Name' => 'GetUserToken',
        ];

        $response = self::$client->execute($requests);

        $this->assertInternalType('array', $response);
        $this->assertCount(2, $response);
        $this->assertContainsOnlyInstancesOf('stdClass', $response);

        list($userToken, $loginUrl) = $response;

        $this->assertObjectHasAttribute('Value', $userToken);
        $this->assertObjectHasAttribute('Value', $loginUrl);
        $this->assertEquals(352, strlen($userToken->Value));
        $this->assertStringStartsWith('http', $loginUrl->Value);
    }

}
