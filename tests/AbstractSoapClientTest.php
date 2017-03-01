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
use DataProBoston\MarketingCloud\Exception\ResponseException;

abstract class AbstractSoapClientTest extends TestCase
{
    /**
     * @var SoapClient
     */
    protected static $client;

    /**
     * @var array
     */
    protected static $objectsToDelete = [];

    public static function setUpBeforeClass()
    {
        $authClient = new AuthClient([
            'clientId' => CLIENT_ID,
            'clientSecret' => CLIENT_SECRET,
        ]);

        self::$client = new SoapClient(null, [], $authClient);
    }

    public static function tearDownAfterClass()
    {
        foreach (self::$objectsToDelete as $i => $object) {
            list ($objectType, $objectId) = $object;

            $identifierProperty = is_int($objectId) ? 'ID' : 'ObjectID';

            $object = [
                $identifierProperty => $objectId,
            ];

            try {
                self::$client->delete($objectType, $object);
                unset(self::$objectsToDelete[$i]);
            }  catch (ResponseException $e) {

            }
        }
    }
}
