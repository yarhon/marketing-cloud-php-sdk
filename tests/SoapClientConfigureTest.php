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

class SoapClientConfigureTest extends AbstractSoapClientTest
{
    public function testCreate()
    {
        $propertiesDefinitions = [
            [
                'Name' => 'test5',
                'DataType' => 'string',
            ],
            [
                'Name' => 'test6',
                'DataType' => 'string',
            ],
        ];

        $propertiesIds = self::$client->create('PropertyDefinition', $propertiesDefinitions);

        $this->assertInternalType('array', $propertiesIds);
        $this->assertCount(count($propertiesDefinitions), $propertiesIds);
        $this->assertContainsOnly('int', $propertiesIds);

        foreach ($propertiesIds as $propertyId) {
            $this->assertGreaterThan(0, $propertyId);
        }

        return $propertiesIds;
    }

    /**
     * @depends testCreate
     */
    public function testDelete($propertiesIds)
    {
        $propertiesDefinitions = [];

        foreach ($propertiesIds as $propertyId) {
            $propertiesDefinitions[] = [
                'ID' => $propertyId,
            ];
        }

        self::$client->delete('PropertyDefinition', $propertiesDefinitions);
    }

}
