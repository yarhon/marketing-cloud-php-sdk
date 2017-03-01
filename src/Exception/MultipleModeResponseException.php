<?php

/*
 * This file is part of DataProBoston Salesforce Marketing Cloud PHP SDK.
 *
 * (c) 2017 Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DataProBoston\MarketingCloud\Exception;

class MultipleModeResponseException extends ResponseException
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    protected $createdObjectsIdentifiers = [];


    /**
     * PartialSuccessClientResponseException constructor.
     * @param string $message
     * @param array $errors
     */
    public function __construct($message, array $errors)
    {
        $this->errors = $errors;

        parent::__construct($message);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $identifiers
     */
    public function setCreatedObjectsIdentifiers($identifiers)
    {
        $this->createdObjectsIdentifiers = $identifiers;
    }

    /**
     * @return array
     */
    public function getCreatedObjectsIdentifiers()
    {
        return $this->createdObjectsIdentifiers;
    }


}