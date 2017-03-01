<?php

/*
 * This file is part of DataProBoston Salesforce Marketing Cloud PHP SDK.
 *
 * (c) 2017 Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DataProBoston\MarketingCloud;

use DataProBoston\MarketingCloud\Version;
use DataProBoston\MarketingCloud\Exception\RequestException;
use DataProBoston\MarketingCloud\Exception\ResponseException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\TransferException as HttpException;

class AuthClient
{
    const TOKEN_REQUEST_URL = 'https://auth.exacttargetapis.com/v1/requestToken?legacy=1';

    /**
     * @var array
     */
    protected $tokens = [
        'auth' => null,
        'expirationDate' => null,
        'internalAuth' => null,
        'refresh' => null,
    ];

    /**
     * @var array
     */
    protected $options = [
        'authTokenMinExpirationTime' => 30,
        'timeout' => 10,
        'sslVerifyPeer' => false,
    ];

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * AuthClient constructor.
     *
     * @param array $options Available options:
     *                       - clientId (string). Required.
     *                       - clientSecret (string). Required.
     *                       - authTokenMinExpirationTime (int). Min time in seconds remaining to token expiration
     *                       that will cause token refresh. Default value - 30.
     *                       - timeout (float). Request timeout in seconds. Default value - 10.
     *                       - sslVerifyPeer (bool|string). Enables / disables SSL certificate verification.
     *                       See http://docs.guzzlephp.org/en/latest/request-options.html#verify.
     *                       Default value - false.
     *
     * @throws RequestException
     */
    public function __construct(array $options)
    {
        $requiredOptions = [
            'clientId',
            'clientSecret',
        ];

        $missedOptions = array_diff($requiredOptions, array_keys($options));

        if (count($missedOptions)) {
            throw new RequestException(sprintf('Options %s are required.', implode(', ', $missedOptions)));
        }

        $this->options = array_merge($this->options, $options);

        $this->httpClient = new HttpClient([
            'timeout' => $this->options['timeout'],
            'verify' => $this->options['sslVerifyPeer'],
            'headers' => [
                'User-Agent' => Version::getUserAgent(),
            ],
        ]);
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        $this->refreshToken();

        return $this->tokens['auth'];
    }

    /**
     * @return string
     */
    public function getInternalAuthToken()
    {
        $this->refreshToken();

        return $this->tokens['internalAuth'];
    }

    /**
     * @param bool $forceRefresh
     *
     * @throws ResponseException
     */
    protected function refreshToken($forceRefresh = false)
    {
        $expirationDate = $this->tokens['expirationDate'];

        if ($expirationDate) {
            $currentDate = new \DateTime();
            $timeDiff = $expirationDate->format('U') - $currentDate->format('U');
        } else {
            $timeDiff = 0;
        }

        $authToken = $this->tokens['auth'];

        if ($authToken !== null && $timeDiff >= $this->options['authTokenMinExpirationTime'] && !$forceRefresh) {
            return;
        }

        $request = new \stdClass();
        $request->clientId = $this->options['clientId'];
        $request->clientSecret = $this->options['clientSecret'];
        $request->accessType = 'offline';

        $refreshToken = $this->tokens['refresh'];
        if ($refreshToken !== null) {
            $request->refreshToken = $refreshToken;
        }

        try {
            $response = $this->httpClient->request('POST', self::TOKEN_REQUEST_URL, ['json' => $request]);

        } catch (HttpException $e) {
            throw new ResponseException('Unable to validate clientId / clientSecret: '.$e->getMessage(), 0, $e);
        }

        $response = json_decode($response->getBody());

        /*
        if (json_last_error()) {
            throw new ResponseException(sprintf('Invalid json response: %s', json_last_error_msg()));
        }
        */

        if (!property_exists($response, 'accessToken')) {
            throw new ResponseException('Unable to validate clientId / clientSecret, requestToken response: '.$response);
        }

        $authToken = $response->accessToken;
        $expirationDate = new \DateTime();
        $expirationDate->modify('+'.$response->expiresIn.' seconds');
        $internalAuthToken = $response->legacyToken;
        $refreshToken = (property_exists($response, 'refreshToken')) ? $response->refreshToken : null;

        $this->tokens = [
            'auth' => $authToken,
            'expirationDate' => $expirationDate,
            'internalAuth' => $internalAuthToken,
            'refresh' => $refreshToken,
        ];
    }
}