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

class AuthClientTest extends TestCase
{
    public function testTokens()
    {
        $client = new AuthClient([
            'clientId' => CLIENT_ID,
            'clientSecret' => CLIENT_SECRET,
        ]);

        $authToken = $client->getAuthToken();
        $internalAuthToken = $client->getInternalAuthToken();

        $this->assertEquals(24, strlen($authToken));
        $this->assertEquals(257, strlen($internalAuthToken));
    }
}
