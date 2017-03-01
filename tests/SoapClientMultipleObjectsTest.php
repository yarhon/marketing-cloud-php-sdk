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

use DataProBoston\MarketingCloud\Exception\MultipleModeResponseException;

class SoapClientMultipleObjectsTest extends AbstractSoapClientTest
{
    public function testCreate()
    {
        $lists = [
            [
                'ListName' => 'test1',
            ],
            [
                'ListName' => 'test2',
            ],
        ];

        $listsIds = self::$client->create('List', $lists, false);

        $this->assertInternalType('array', $listsIds);
        $this->assertCount(count($lists), $listsIds);
        $this->assertContainsOnly('int', $listsIds);

        foreach ($listsIds as $listId) {
            $this->assertGreaterThan(0, $listId);
        }

        return $listsIds;
    }

    /**
     * @depends testCreate
     */
    public function testRetrieve($listsIds)
    {
        $filter = [
            ['ID', '=', $listsIds[0]],
            'or',
            ['ID', '=', $listsIds[1]],
        ];

        $properties = ['ID', 'ListName'];

        $lists = self::$client->retrieve('List', $properties, $filter);

        $this->assertCount(count($listsIds), $lists);

        $this->assertEquals('test1', $lists[0]->ListName);
        $this->assertEquals('test2', $lists[1]->ListName);
    }

    /**
     * @depends testCreate
     */
    public function testUpdate($listsIds)
    {
        $lists = [
            [
                'ID' => $listsIds[0],
                'ListName' => 'test3',
            ],
            [
                'ID' => $listsIds[1],
                'ListName' => 'test4',
            ],
        ];

        self::$client->update('List', $lists);

        $filter = [
            ['ID', '=', $listsIds[0]],
            'or',
            ['ID', '=', $listsIds[1]],
        ];

        $properties = ['ID', 'ListName'];

        $lists = self::$client->retrieve('List', $properties, $filter);

        $this->assertCount(count($listsIds), $lists);

        $this->assertEquals('test3', $lists[0]->ListName);
        $this->assertEquals('test4', $lists[1]->ListName);
    }

    /**
     * @depends testCreate
     */
    public function testCreateException($listsIds)
    {
        $subscribers = [
            [
                'EmailAddress' => 'boris.eibelman@gmail.com',
                'Lists' => [
                    'ID' => $listsIds[0],
                ]
            ],
            [
                'EmailAddress' => 'test2@test.com',
                'Lists' => [
                    'ID' => $listsIds[0],
                ],
            ],
        ];

        $this->expectException(MultipleModeResponseException::class);

        try {
            $subscribersIds = self::$client->create('Subscriber', $subscribers, true);
        } catch (MultipleModeResponseException $e) {
            $errors = $e->getErrors();
            $subscribersIds = $e->getCreatedObjectsIdentifiers();

            $this->assertInternalType('array', $subscribersIds);
            $this->assertCount(1, $subscribersIds);
            $this->assertArrayHasKey(0, $subscribersIds);

            $subscriberId = $subscribersIds[0];
            $this->assertInternalType('int', $subscriberId);
            $this->assertGreaterThan(0, $subscriberId);

            $this->assertInternalType('array', $errors);
            $this->assertCount(1, $errors);
            $this->assertArrayHasKey(1, $errors);

            $error = $errors[1];

            $this->assertInternalType('array', $error);
            $this->assertCount(2, $error);

            list($errorMessage, $errorCode) = $error;

            $this->assertInternalType('string', $errorMessage);
            $this->assertGreaterThan(0, strlen($errorMessage));
            $this->assertInternalType('int', $errorCode);
            $this->assertGreaterThan(0, $errorCode);

            throw $e;
        }

    }

    /**
     * @depends testCreate
     */
    public function testDelete($listsIds)
    {
        $lists = [
            [
                'ID' => $listsIds[0],
            ],
            [
                'ID' => $listsIds[1],
            ],
        ];

        self::$client->delete('List', $lists);

        $filter = [
            ['ID', '=', $listsIds[0]],
            'or',
            ['ID', '=', $listsIds[1]],
        ];

        $properties = ['ID', 'ListName'];

        $lists = self::$client->retrieve('List', $properties, $filter);

        $this->assertCount(0, $lists);
    }
}
