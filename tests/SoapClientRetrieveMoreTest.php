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

class SoapClientRetrieveMoreTest extends AbstractSoapClientTest
{
    protected static $limit = 2500;

    public function testRetrieve()
    {
        $this->markTestSkipped();

        ini_set('memory_limit', '512M');

        $objects = self::$client->retrieve('Subscriber', ['ID']);

        if (count($objects) != self::$limit) {
            var_dump('Not enough objects.');
            return;
        }

        $this->assertTrue(self::$client->hasMoreResults());

        while (self::$client->hasMoreResults()) {
            $objects = self::$client->retrieveMore();
            $this->assertGreaterThan(0, count($objects));
            break;
        }
    }

}
