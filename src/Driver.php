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

use DataProBoston\MarketingCloud\AuthClient;
use DataProBoston\MarketingCloud\SoapClient;
use DataProBoston\MarketingCloud\Exception\RequestException;
use DataProBoston\MarketingCloud\Exception\ResponseException;

class Driver
{
    /**
     * @var SoapClient
     */
    protected $soapClient;

    public function __construct(SoapClient $soapClient)
    {
        $this->soapClient = $soapClient;
    }

    /**
     * @param string $name
     *
     * @return int List id
     */
    public function createList($name)
    {
        $list = [
            'ListName' => $name,
        ];

        $listId = $this->soapClient->create('List', $list);

        return $listId;
    }

    /**
     * @param array $subscribers
     * @param int $listId
     */
    public function createSubscribers(array $subscribers, $listId)
    {
        $subscribers = $this->transformSubscribers($subscribers, $listId);

        $this->soapClient->create('Subscriber', $subscribers, true);
    }

    /**
     * @param string $name
     * @param string $subject
     * @param string $body
     *
     * @return int Email id
     */
    public function createEmail($name, $subject, $body)
    {
        $email = [
            'Name' => $name,
            'Subject' => $subject,
            'HTMLBody' => $body,
            'EmailType' => 'HTML',
            'IsHTMLPaste' => 'true',
            'CharacterSet' => 'UTF-8',
        ];

        $emailId = $this->soapClient->create('Email', $email);

        return $emailId;
    }

    /**
     * @param string $name
     * @param string $fromName
     * @param string $fromEmail
     *
     * @return string SenderProfile id
     */
    public function createSenderProfile($name, $fromName, $fromEmail)
    {
        $senderProfile = [
            'Name' => $name,
            'FromName' => $fromName,
            'FromAddress' => $fromEmail,
        ];

        $senderProfileId = $this->soapClient->create('SenderProfile', $senderProfile);

        return $senderProfileId;
    }

    /**
     * @param string $name
     * @param string $senderProfileId
     * @param string $deliveryProfile
     *
     * @return string SendClassification id
     */
    public function createSendClassification($name, $senderProfileId, $deliveryProfile)
    {
        $sendClassification = [
            'Name' => $name,
            'SenderProfile' => [
                'ObjectID' => $senderProfileId,
            ],
            'DeliveryProfile' => [
                'CustomerKey' => $deliveryProfile,
            ],
        ];

        $sendClassificationId = $this->soapClient->create('SendClassification', $sendClassification);

        return $sendClassificationId;
    }

    /**
     * @param int $listId
     * @param int $emailId
     * @param string $sendClassificationId
     *
     * @return string EmailSendDefinition id
     */
    public function createEmailSendDefinition($listId, $emailId, $sendClassificationId)
    {
        $emailSendDefinition = [
            'Email' => ['ID' => $emailId],
            'Name' => $emailId,
            'IsMultipart' => 'true',
            'SendDefinitionList' => [
                'List' => ['ID' => $listId],
                'DataSourceTypeID' => 'List',
            ],
            'SendClassification' => [
                'ObjectID' => $sendClassificationId,
            ],
        ];

        $emailSendDefinitionId = $this->soapClient->create('EmailSendDefinition', $emailSendDefinition);

        return $emailSendDefinitionId;
    }

    /**
     * @param string $emailSendDefinitionId
     *
     * @return int Send id
     */
    public function sendEmailSendDefinition($emailSendDefinitionId)
    {
        $request = [
            'ObjectID' => $emailSendDefinitionId,
        ];

        $sendId = $this->soapClient->perform('EmailSendDefinition', $request, 'start');

        return $sendId;
    }


    public function sendEmail()
    {
        $campaign = [
            'name' => '',
            'fromEmail' => '',
            'fromName' => '',
            'subscribers' => [],
            'subject' => '',
            'body' => '',
            'deliveryProfile' => '',
        ];

        $listId = $this->createList($campaign['name']);
        $this->createSubscribers($campaign['subscribers'], $listId);
        $emailId = $this->createEmail($campaign['name'], $campaign['subject'], $campaign['body']);
        $senderProfileId = $this->createSenderProfile($campaign['fromName'], $campaign['fromName'], $campaign['fromEmail']);
        $sendClassificationId = $this->createSendClassification(
            $campaign['fromName'],
            $senderProfileId,
            $campaign['deliveryProfile']
        );
        $emailSendDefinitionId = $this->createEmailSendDefinition($listId, $emailId, $sendClassificationId);
        $sendId = $this->sendEmailSendDefinition($emailSendDefinitionId);

    }


    /**
     * @param array $subscribers. Array of subscribers in 2 possible forms:
     *                            - numeric array, just subscribers' emails
     *                            - associative array of arrays, with subscribers email as a key,
     *                              and array of subscriber attributes as a value.
     * @param $listId
     *
     * @return array              Transformed subscribers array to pass to create method.
     */
    public function transformSubscribers(array $subscribers, $listId)
    {
        $isWithAttributes = ($subscribers !== array_values($subscribers));

        $transformed = [];

        foreach ($subscribers as $key => $value) {
            $subscriber = [
                'EmailAddress' => $isWithAttributes ? $key : $value,
                'Lists' => [
                    'ID' => $listId,
                ],
            ];

            $transformed[] = &$subscriber;

            if (!$isWithAttributes) {
                continue;
            }

            $attributes = [];

            foreach ($value as $attributeName => $attributeValue) {
                $attributes[] = [
                    'Name' => $attributeName,
                    'Value' => $attributeValue,
                ];
            }

            if (count($attributes)) {
                $subscriber['Attributes'] = $attributes;
            }
        }

        return $transformed;
    }

}