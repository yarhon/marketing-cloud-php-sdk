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

/*
 * WsseSoap class uses random_bytes() function, that is available from PHP 7.0.
 * For PHP 5.x, use https://github.com/paragonie/random_compat for random_bytes().
 */
class WsseSoap
{
    const WSSE_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    const WSU_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

    const WSU_NAME = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0';

    const OAUTH_NAMESPACE = 'http://exacttarget.com';

    const WSSE_PREFIX = 'wsse';

    const WSU_PREFIX = 'wsu';

    const SESSION_KEY_LENGTH = 32;

    /**
     * @var \DOMDocument
     */
    protected $soap;

    /**
     * @var \DOMXPath
     */
    protected $xpath;

    /**
     * @var string
     */
    protected $soapNamespace;

    /**
     * @var string
     */
    protected $soapPrefix;


    /**
     * WsseSoap constructor.
     * @param string $soapXML
     */
    public function __construct($soapXML)
    {
        $this->soap = new \DOMDocument();
        $this->soap->loadXML($soapXML);

        $this->xpath = new \DOMXPath($this->soap);

        $this->soapNamespace = $this->soap->documentElement->namespaceURI;
        $this->soapPrefix = $this->soap->documentElement->prefix;

        $this->xpath->registerNamespace('wssoap', $this->soapNamespace);
        $this->xpath->registerNamespace('wswsse', self::WSSE_NAMESPACE);
    }

    /**
     * @return string
     */
    public function getXML()
    {
        return $this->soap->saveXML();
    }

    /**
     * @param string $token
     */
    public function addOAuthToken($token)
    {
        $header = $this->locateHeaderNode();

        $oAuthNode = $this->soap->createElementNS(self::OAUTH_NAMESPACE, 'oAuth');
        $header->appendChild($oAuthNode);

        $tokenNode = $this->soap->createElement('oAuthToken', $token);
        $oAuthNode->appendChild($tokenNode);
    }

    /**
     * @param string $username
     * @param string|null $password
     * @param bool $passwordDigest
     *
     * @throws \InvalidArgumentException
     */
    public function addUserToken($username, $password = null, $passwordDigest = false)
    {
        if ($passwordDigest && !$password) {
            throw new \InvalidArgumentException('Cannot calculate the digest without a password.');
        }

        $nonce = random_bytes(self::SESSION_KEY_LENGTH);

        $createdDate = gmdate('Y-m-d\TH:i:s\Z');

        if ($passwordDigest) {
            $password = base64_encode(sha1($nonce.$createdDate.$password, true));
        }

        $securityNode = $this->locateSecurityNode();

        $tokenNode = $this->soap->createElement(self::WSSE_PREFIX.':UsernameToken');
        $securityNode->insertBefore($tokenNode, $securityNode->firstChild);

        $usernameNode = $this->soap->createElement(self::WSSE_PREFIX.':Username', $username);
        $tokenNode->appendChild($usernameNode);

        if ($password) {
            $passwordType = $passwordDigest ? '#PasswordDigest' : '#PasswordText';
            $passwordNode = $this->soap->createElement(self::WSSE_PREFIX.':Password', $password);
            $passwordNode->setAttribute('Type', self::WSU_NAME.$passwordType);
            $tokenNode->appendChild($passwordNode);
        }

        $nonceNode = $this->soap->createElement(self::WSSE_PREFIX.':Nonce', base64_encode($nonce));
        $tokenNode->appendChild($nonceNode);

        $createdNode = $this->soap->createElementNS(self::WSU_NAMESPACE, self::WSU_PREFIX.':Created', $createdDate);
        $tokenNode->appendChild($createdNode);
    }

    /**
     * @return \DOMElement
     */
    protected function locateSecurityNode()
    {
        $header = $this->locateHeaderNode();

        $securityNode = $this->xpath->query('./wswsse:Security', $header)->item(0);

        if (!$securityNode) {
            $securityNode = $this->soap->createElementNS(self::WSSE_NAMESPACE, self::WSSE_PREFIX.':Security');
            $securityNode->setAttribute($this->soapPrefix.':mustUnderstand', '1');
            $header->appendChild($securityNode);
        }

        return $securityNode;
    }

    /**
     * @return \DOMElement
     */
    protected function locateHeaderNode()
    {
        $envelope = $this->soap->documentElement;

        $header = $this->xpath->query('./wssoap:Header', $envelope)->item(0);

        if (!$header) {
            $header = $this->soap->createElementNS($this->soapNamespace, $this->soapPrefix.':Header');
            $envelope->insertBefore($header, $envelope->firstChild);
        }

        return $header;
    }

}
