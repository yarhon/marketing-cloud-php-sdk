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

class SoapClientOneObjectTest extends AbstractSoapClientTest
{
    public function testCreate()
    {
        $list = [
            'ListName' => 'test',
        ];

        $listId = self::$client->create('List', $list, false);

        $this->assertInternalType('int', $listId);
        $this->assertGreaterThan(0, $listId);

        return $listId;
    }

    /**
     * @depends testCreate
     */
    public function testRetrieve($listId)
    {
        $filter = ['ID', '=', $listId];
        $properties = ['ID', 'ListName'];

        $list = self::$client->retrieveOne('List', $properties, $filter);

        $this->assertEquals('test', $list->ListName);
    }

    /**
     * @depends testCreate
     */
    public function testUpdate($listId)
    {
        $list = [
            'ID' => $listId,
            'ListName' => 'test2',
        ];

        self::$client->update('List', $list);

        $filter = ['ID', '=', $listId];
        $properties = ['ID', 'ListName'];

        $list = self::$client->retrieveOne('List', $properties, $filter);

        $this->assertEquals('test2', $list->ListName);
    }

    /**
     * @depends testCreate
     */
    public function testCreateException($listId)
    {
        $subscriber = [
            'EmailAddress' => 'test@test.com',
            'Lists' => [
                'ID' => $listId,
            ],
        ];

        $this->expectException(ResponseException::class);

        try {
            self::$client->create('Subscriber', $subscriber, false);
        } catch (ResponseException $e) {

            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            $this->assertInternalType('int', $errorCode);
            $this->assertGreaterThan(0, $errorCode);

            $this->assertInternalType('string', $errorMessage);
            $this->assertGreaterThan(0, strlen($errorMessage));

            throw $e;
        }
    }

    /**
     * @depends testCreate
     */
    public function testDelete($listId)
    {
        $list = [
            'ID' => $listId,
        ];

        self::$client->delete('List', $list);

        $filter = ['ID', '=', $listId];
        $properties = ['ID', 'ListName'];

        $list = self::$client->retrieveOne('List', $properties, $filter);

        $this->assertInternalType('null', $list);
    }

}
