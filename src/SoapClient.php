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
use DataProBoston\MarketingCloud\Exception\MultipleModeResponseException;
use DataProBoston\MarketingCloud\WsseSoap;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\TransferException as HttpException;

/**
 * Class SoapClient
 *
 * @link https://developer.salesforce.com/docs/atlas.en-us.noversion.mc-apis.meta/mc-apis/web_service_guide.htm Documentation.
 * @link https://webservice.exacttarget.com/etframework.wsdl WSDL.
 */
class SoapClient extends \SoapClient
{

    const SOAP_NAMESPACE = 'http://exacttarget.com/wsdl/partnerAPI';

    const ENDPOINT_REQUEST_URL = 'https://www.exacttargetapis.com/platform/v1/endpoints/soap?access_token=%s';

    const WSDL_FILENAME = __DIR__.'/Resources/wsdl/%s.exacttarget.wsdl.xml';

    /**
     * @var int Date format used for incoming dates (ISO 8601)
     */
    const DATE_FORMAT = \DateTime::ATOM;

    /**
     * @var array
     */
    const REQUEST_WRAPPERS = [
        'Retrieve' => ['RetrieveRequestMsg', 'RetrieveRequest'],
        'Create' => ['CreateRequest'],
        'Update' => ['UpdateRequest'],
        'Delete' => ['DeleteRequest'],
        'Perform' => ['PerformRequestMsg'],
        'Configure' => ['ConfigureRequestMsg'],
        'Describe' => ['DefinitionRequestMsg', 'DescribeRequests', 'ObjectDefinitionRequest'],
        'VersionInfo' => ['VersionInfoRequestMsg'],
        'GetSystemStatus' => ['SystemStatusRequestMsg'],
        'Execute' => ['ExecuteRequestMsg'],
    ];

    /**
     * @var array
     */
    const RESPONSE_WRAPPERS = [
        'Retrieve' => ['Results'],
        'Create' => ['Results'],
        'Update' => ['Results'],
        'Delete' => ['Results'],
        'Perform' => ['Results', 'Result'],
        'Configure' => ['Results', 'Result'],
        'Describe' => ['ObjectDefinition'],
        'VersionInfo' => ['VersionInfo'],
        'GetSystemStatus' => ['Results', 'Result'],
        'Execute' => ['Results'],
    ];

    /**
     * @var array Describes possible OverallStatus values for method calls.
     */
    const OVERALL_STATUS_SUCCESSFUL = [
        'OK' => '*',
        'MoreDataAvailable' => ['Retrieve'],
        'Error' => ['Create', 'Update', 'Delete', 'Configure', 'Perform'], // Errors will be processed in checkObjectsStatus() method
        'Has Errors' => ['Create', 'Update', 'Delete', 'Configure', 'Perform'], // Errors will be processed in checkObjectsStatus() method
    ];

    /**
     * @var array Mapping from publicly used operators to internal operators.
     */
    const FILTER_OPERATORS = [
        '=' => 'equals',
        '!=' => 'notEquals',
        '>' => 'greaterThan',
        '<' => 'lessThan',
        '>=' => 'greaterThanOrEqual',
        '<=' => 'lessThanOrEqual',
        'isNull' => 'isNull',
        '!isNull' => 'isNotNull',
        'between' => 'between',
        'in' => 'IN',
        'like' => 'like',
        'and' => 'AND',
        'or' => 'OR',
    ];

    /**
     * @var array Objects that support folders (categories).
     */
    const OBJECTS_FOLDERS = [
        'ContentArea' => ['property' => 'CategoryID', 'mediaType' => 'content'],
        'Email' => ['property' => 'CategoryID', 'mediaType' => 'email'],
        'EmailSendDefinition' => ['property' => 'CategoryID', 'mediaType' => 'userinitiatedsends'],
        'List' => ['property' => 'Category', 'mediaType' => 'list'],
        'TriggeredSendDefinition' => ['property' => 'CategoryID', 'mediaType' => 'triggered_send'],
    ];

    /**
     * @var array Objects that should use Configure method with Action parameter instead of Create, Update, Delete.
     */
    const CONFIGURE_OBJECTS = [
        'PropertyDefinition',
        'Role'
    ];

    /**
     * @var string
     */
    protected $lastRequestId;

    /**
     * @var bool
     */
    protected $hasMoreResults;

    /**
     * @var array
     */
    protected $options = [
        'wsdlInstance' => 'main',
        'timeout' => 300,
        'sslVerifyPeer' => false,
    ];

    /**
     * @var string
     */
    protected $wsdl;

    /**
     * @var AuthClient
     */
    protected $authClient;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $dataTypeDescriptionRegexp = '/^(\S+)\s(\S+)\s?\{?$/m';

    /**
     * @var string
     */
    protected $dataTypePropertyDescriptionRegexp = '/^\s(\S+)\s(\S+);$/m';

    /**
     * @var array
     */
    protected $dataTypes = [];

    /**
     * SoapClient constructor.
     *
     * @param string $wsdl   Absolute path to WSDL file. If null, the bundled one (depending on wsdlInstance option)
     *                       will be used.
     *
     * @param array $options Available options:
     *                       - wsdlInstance (string). Instance of bundled WSDL to use.
     *                       Available instances: 'main', 's4', 's6', 's7', 'test'. Default value - 'main'.
     *                       - timeout (float). Request timeout in seconds. Default value - 60.
     *                       - sslVerifyPeer (bool|string). Enables / disables SSL certificate verification.
     *                       @see http://docs.guzzlephp.org/en/latest/request-options.html#verify.
     *                       Default value - false.
     *
     * @param AuthClient $authClient
     *
     * @throws RequestException
     */
    public function __construct($wsdl = null, array $options, AuthClient $authClient)
    {
        $this->options = array_merge($this->options, $options);

        if (!$wsdl) {
            $wsdl = sprintf(self::WSDL_FILENAME, $this->options['wsdlInstance']);
        }

        if (!file_exists($wsdl)) {
            throw new RequestException(sprintf('WSDL file %s do not exists.', $wsdl));
        }

        $this->wsdl = $wsdl;

        $this->authClient = $authClient;

        $this->httpClient = new HttpClient([
            'timeout' => $this->options['timeout'],
            'verify' => $this->options['sslVerifyPeer'],
            'headers' => [
                'User-Agent' => Version::getUserAgent(),
            ],
        ]);

        parent::__construct($wsdl);
    }

    /**
     * @param string $objectType
     * @param array $properties    Properties of object(s) to retrieve.
     * @param array $filters       @see SoapClient::encodeFilters()
     * @param bool $sinceLastBatch @see SoapClient::retrieveSoapCall()
     *
     * @return \stdClass[]         Objects.
     */
    public function retrieve($objectType, array $properties, array $filters = [], $sinceLastBatch = false)
    {
        $results = $this->retrieveSoapCall($objectType, $properties, $filters, $sinceLastBatch);

        if (!is_array($results)) {
            $results = [$results];
        }

        return $results;
    }

    /**
     * @param string $objectType
     * @param array $properties    Properties of object to retrieve.
     * @param array $filters       @see SoapClient::encodeFilters()
     *
     * @return \stdClass|null      Object.
     *
     * @throws ResponseException
     */
    public function retrieveOne($objectType, array $properties, array $filters)
    {
        $result = $this->retrieveSoapCall($objectType, $properties, $filters);

        if (!is_array($result)) {
            return $result;
        }

        if (count($result) == 0) {
            return null;
        }

        throw new ResponseException(sprintf('retrieveOne call received %s results.', count($result)));
    }

    /**
     * @param string|null $requestId
     *
     * @return \stdClass[] Objects.
     *
     * @throws RequestException
     */
    public function retrieveMore($requestId = null)
    {
        if (!$requestId) {
            if (!$this->hasMoreResults) {
                throw new RequestException('retrieveMore can not be called when hasMoreResults is false.');
            }

            $requestId = $this->lastRequestId;
        }

        $request = [
            'ObjectType' => null,
            'ContinueRequest' => $requestId,
        ];

        $results = $this->soapCall('Retrieve', $request);

        if (!is_array($results)) {
            $results = [$results];
        }

        return $results;
    }

    /**
     * @param string $objectType
     * @param array $objects
     * @param bool $upsert Supported for Send, List, and Subscriber objects.
     *
     * @return int|string|int[]|string[] Identifier(s) of created object(s). @see SoapClient::processCreateResults()
     */
    public function create($objectType, array $objects, $upsert = false)
    {
        $result = $this->cudObjectsSoapCall('Create', $objectType, $objects, $upsert);
        $identifiers = $this->processCreateResults($result, [$this, 'decodeCreatedObjectIdentifier']);

        return $identifiers;
    }

    /**
     * @param string $objectType
     * @param array $objects
     * @param string $action     Action to perform
     *                           @link https://developer.salesforce.com/docs/atlas.en-us.noversion.mc-apis.meta/mc-apis/perform.htm Supported actions.
     *
     * @return int|int[]         Identifier(s) of created tasks(s). @see SoapClient::processCreateResults()
     */
    public function perform($objectType, array $objects, $action)
    {
        $buildRequest = function($objects) use ($action) {
            if (!is_array($objects)) {
                $objects = [$objects];
            }

            return [
                'Action' => $action,
                'Definitions' => $objects,
            ];
        };

        $result = $this->objectsSoapCall('Perform', $objectType, $objects, $buildRequest);

        // @todo: maybe $result->Task->InteractionObjectID should be used?
        $decodeIdentifiers = function($result) { return (int) $result->Task->ID; };
        $identifiers = $this->processCreateResults($result, $decodeIdentifiers);

        return $identifiers;
    }

    /**
     * @param string $objectType
     * @param array $objects
     *
     * @todo: check if one of identifiers is specified in $objects.
     * @todo: for what is upsert support in update operation?
     */
    public function update($objectType, array $objects)
    {
        $result = $this->cudObjectsSoapCall('Update', $objectType, $objects);
        $this->checkObjectsStatus($result);
    }

    /**
     * @param string $objectType
     * @param array $objects
     */
    public function delete($objectType, array $objects)
    {
        $result = $this->cudObjectsSoapCall('Delete', $objectType, $objects);
        $this->checkObjectsStatus($result);
    }

    /**
     * @param array $requests
     *
     * @return array Result(s) of request(s).
     *
     * @todo: check for errors
     */
    public function execute(array $requests)
    {
        $request = [
            'Requests' => $requests,
        ];

        $preResult = $this->soapCall('Execute', $request);
        $result = [];

        if (!is_array($preResult)) {
            if (property_exists($preResult, 'Results')) {
                $result = $preResult->Results;
            }

        } else {
            foreach ($preResult as $i => $requestPreResult) {
                $result[$i] = [];
                if (property_exists($requestPreResult, 'Results')) {
                    $result[$i] = $requestPreResult->Results;
                }
            }
        }

        return $result;
    }

    /**
     * @param bool $includeVersionHistory
     *
     * @return \stdClass
     */
    public function versionInfo($includeVersionHistory = false)
    {
        $request = [
            'IncludeVersionHistory' => $includeVersionHistory,
        ];

        $result = $this->soapCall('VersionInfo', $request);

        return $result;
    }

    /**
     * @return string System status: 'OK'|'InMaintenance'|'UnplannedOutage'.
     */
    public function systemStatus()
    {
        $result = $this->soapCall('GetSystemStatus', []);

        return $result->SystemStatus;
    }

    /**
     * @param string $objectType
     *
     * @return \stdClass[] Object properties.
     */
    public function describe($objectType)
    {
        $request = [
            'ObjectType' => $objectType,
        ];

        $response = $this->soapCall('Describe', $request);

        return $response->Properties;
    }

    /**
     * @return \stdClass[] Subscriber object attributes ("extended properties").
     */
    public function describeSubscriberAttributes()
    {
        $request = [
            'ObjectType' => 'Subscriber',
        ];

        $response = $this->soapCall('Describe', $request);

        return $response->ExtendedProperties->ExtendedProperty;
    }

    /**
     * @return string
     */
    public function getLastRequestId()
    {
        return $this->lastRequestId;
    }

    /**
     * @return bool
     */
    public function hasMoreResults()
    {
        return $this->hasMoreResults;
    }

    /**
     * @return array
     */
    public function getFilterOperators()
    {
        return array_keys(self::FILTER_OPERATORS);
    }

    /**
     * @return string Endpoint URL.
     *
     * @throws ResponseException
     */
    public function requestEndpoint()
    {
        $url = sprintf(self::ENDPOINT_REQUEST_URL, $this->authClient->getAuthToken());

        try {
            $response = $this->httpClient->request('GET', $url);
        } catch (HttpException $e) {
            throw new ResponseException(sprintf('Unable to retrieve endpoint: %s', $e->getMessage()), 0, $e);
        }

        $endpointObject = json_decode($response->getBody());
        if (!$endpointObject || !property_exists($endpointObject, 'url')) {
            throw new ResponseException(sprintf('Unable to retrieve endpoint: %s', $response));
        }

        return $endpointObject->url;
    }

    /**
     * @param string $method
     * @param array $request
     *
     * @return \stdClass|\stdClass[] Result or results.
     *
     * @throws RequestException
     * @throws ResponseException
     */
    protected function soapCall($method, array $request)
    {
        $this->hasMoreResults = false;
        $this->lastRequestId = null;

        $wrappers = array_reverse(self::REQUEST_WRAPPERS[$method]);

        foreach ($wrappers as $wrapper) {
            $request = [$wrapper => $request];
        }

        try {
            $response = $this->__soapCall($method, $request);
        } catch (\SoapFault $fault) {
            // $fault->getCode() always returns 0, so using $fault->faultcode instead.
            throw new RequestException(sprintf('{%s} {%s}', $fault->faultcode, $fault->getMessage()));
        }

        $this->lastRequestId = $response->RequestID;

        $wrappers = self::RESPONSE_WRAPPERS[$method];
        $results = $response;

        foreach ($wrappers as $wrapper) {
            if (property_exists($results, $wrapper)) {
                $results = $results->$wrapper;
            } else {
                $results = null;
                break;
            }
        }

        $overallStatus = $this->checkOverallStatus($response, $method);

        if ($method == 'Retrieve') {
            // Retrieve method response can have no Results property in case of empty set.
            if ($results === null) {
                $results = [];
            }

            $this->hasMoreResults = ($overallStatus == 'MoreDataAvailable');
        }

        if ($results === null) {
            throw new ResponseException('No results.');
        }

        return $results;
    }

    /**
     * Not really public method - it's public only to be compatible with parent method.
     * @inheritdoc
     *
     * @throws ResponseException
     */
    public function __doRequest($request, $location, $action, $version, $oneWay = 0)
    {
        $wsse = new WsseSoap($request);
        $wsse->addUserToken('*', '*');
        $wsse->addOAuthToken($this->authClient->getInternalAuthToken());
        $request = $wsse->getXML();

        try {
            $response = $this->httpClient->request('POST', $location, [
                'body' => $request,
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'SOAPAction' => $action,
                ],
            ]);
        } catch (HttpException $e) {
            throw new ResponseException('Unable to execute SOAP request: '.$e->getMessage(), 0, $e);
        }

        return (string) $response->getBody();
    }

    /**
     * @param string $objectType
     * @param array $properties    Properties of object(s) to retrieve.
     * @param array $filters       @see SoapClient::encodeFilters()
     * @param bool $sinceLastBatch Retrieve only objects added/modified since last retrieve call with the same
     *                             properties and filters.
     *
     * @return \stdClass|\stdClass[] Object or objects.
     */
    protected function retrieveSoapCall($objectType, array $properties, array $filters = [], $sinceLastBatch = false)
    {
        $request = [
            'ObjectType' => $objectType,
            'Properties' => $properties,
            'Filter' => count($filters) ? $this->encodeFilters($filters) : null,
            // Despite of documentation, API ignores BatchSize option.
            /*
            'Options' => [
                'BatchSize' => 100,
            ],
            */
        ];

        if ($objectType == 'Account') {
            $request['QueryAllAccounts'] = true;
        }

        if ($sinceLastBatch) {
            $request['RetrieveAllSinceLastBatch'] = true;
        }

        $results = $this->soapCall('Retrieve', $request);

        return $results;
    }

    /**
     * Processes Create, Update, Delete action methods.
     *
     * @param string $method
     * @param string $objectType
     * @param array $objects
     * @param bool $upsert
     *
     * @return \stdClass|\stdClass[] Result or results.
     */
    protected function cudObjectsSoapCall($method, $objectType, array $objects, $upsert = false)
    {
        if (in_array($objectType, self::CONFIGURE_OBJECTS)) {
            $buildRequest = function($objects) use ($method) {
                if (!is_array($objects)) {
                    $objects = [$objects];
                }

                return [
                    'Action' => $method,
                    'Configurations' => $objects,
                ];
            };
            $method = 'Configure';
        } else {
            $buildRequest = function($objects) use ($upsert) {

                return [
                    'Objects' => $objects,
                    'Options' => $this->encodeSaveOptions($upsert),
                ];
            };
        }

        return $this->objectsSoapCall($method, $objectType, $objects, $buildRequest);
    }

    /**
     * Processes objects action methods.
     *
     * @param string $method
     * @param string $objectType
     * @param array $objects
     * @param callable $buildRequest Build request function. It should receive \stdClass|\stdClass[] object|objects
     *                               and return request array.
     *
     * @return \stdClass|\stdClass[] Result or results.
     */
    protected function objectsSoapCall($method, $objectType, array $objects, callable $buildRequest)
    {
        $multipleObjectsMode = ($objects === array_values($objects));

        $objects = $this->encodeObjects($objectType, $objects);

        $request = $buildRequest($objects);

        $result = $this->soapCall($method, $request);

        // In case if $objects input is multi-object array (array of arrays) with only one element, \SoapClient returns
        // single \stdClass element instead of one-element array, so we have to deal with that.
        if ($multipleObjectsMode && !is_array($result)) {
            $result = [$result];
        }

        return $result;
    }

    /**
     * @param array $filters 3-element array, in 2 possible forms:
     *                       a) Simple condition: [$property, $operator, $value]
     *                       b) Logical condition: [$leftCondition, $operator, $rightCondition].
     *                       Applied if operator is 'and' or 'or'.
     *                       $leftCondition / $rightCondition itself could be simple conditions or nested logical conditions.
     *
     *                       Dates in filter values should be represented by \DateTime objects.
     *
     * @return \SoapVar
     *
     * @throws RequestException
     */
    protected function encodeFilters(array $filters)
    {
        if (count($filters) != 3) {
            throw new RequestException('Filters array must have exactly 3 elements.');
        }

        $operator = $filters[1];
        $operators = self::FILTER_OPERATORS;

        if (!isset($operators[$operator])) {
            throw new RequestException(sprintf('Unknown filter operator: %s', $operator));
        }

        $internalOperator = $operators[$operator];

        if ($operator == 'and' || $operator == 'or') {
            if (!is_array($filters[0]) || !is_array($filters[2])) {
                throw new RequestException('First and third elements in filters array must be arrays when using and/or operators.');
            }
            $container = new \stdClass();
            $container->LeftOperand = $this->encodeFilters($filters[0]);
            $container->RightOperand = $this->encodeFilters($filters[2]);
            $container->LogicalOperator = $internalOperator;

            return $this->createSoapVar('ComplexFilterPart', $container);
        }

        // @todo: checks for in and between operators

        $value = $filters[2];

        $dateValue = false;

        if ($value instanceof \DateTime) {
            $dateValue = true;
            $value = $value->format(self::DATE_FORMAT);
        } else if (is_array($value)) {
            $datesCount = 0;
            $value = array_map(function($element) use (&$datesCount) {
                if (!($element instanceof \DateTime)) {
                    return $element;
                }
                $datesCount++;
                return $element->format(self::DATE_FORMAT);
            }, $value);

            if ($datesCount) {
                if ($datesCount != count($value)) {
                    throw new RequestException('Date type values should not be mixed with other value types.');
                }
                $dateValue = true;
            }
        }

        $valueContainer = $dateValue ? 'DateValue' : 'Value';

        $container = [
            'Property' => $filters[0],
            'SimpleOperator' => $internalOperator,
            $valueContainer => $value,
        ];

        return $this->createSoapVar('SimpleFilterPart', $container);
    }

    /**
     * @param string $objectType
     * @param array $objects
     *
     * @return \SoapVar|\SoapVar[]
     */
    protected function encodeObjects($objectType, $objects)
    {
        $multipleObjectsMode = ($objects === array_values($objects));

        if (!$multipleObjectsMode) {
            $encoded = $this->createSoapVar($objectType, $objects);
        } else {
            $encoded = array_map(function($object) use ($objectType) { return $this->createSoapVar($objectType, $object); },
                $objects);
        }

        return $encoded;
    }

    /**
     * @param bool $upsert
     *
     * @return array|string
     *
     * @todo: move if other options
     */
    protected function encodeSaveOptions($upsert)
    {
        if (!$upsert) {
            return '';
        }

        $saveOption = [
            'PropertyName' => '*',
            'SaveAction' => 'UpdateAdd', // AddOnly / Default / Nothing / UpdateAdd / UpdateOnly / Delete
        ];

        $options = [
            'SaveOptions' => [
                'SaveOption' => $saveOption,
            ],
        ];

        return $options;
    }

    /**
     * @param \stdClass $response
     * @param string $method
     *
     * @return string|null Overall status.
     *
     * @throws ResponseException
     */
    protected function checkOverallStatus($response, $method)
    {
        // Describe and VersionInfo methods response has no OverallStatus property.
        if (!property_exists($response, 'OverallStatus')) {
            return null;
        }

        $overallStatus = $response->OverallStatus;

        //@todo: check OverallStatusMessage

        $overallStatusSuccessful = self::OVERALL_STATUS_SUCCESSFUL;

        if (!isset($overallStatusSuccessful[$overallStatus])) {
            $overallStatus = str_replace("\n", ' ', $overallStatus);
            throw new ResponseException(sprintf('Unknown overallStatus: %s', $overallStatus));
        }

        $methods = $overallStatusSuccessful[$overallStatus];

        if ($methods != '*' && !in_array($method, $methods)) {
            throw new ResponseException(sprintf('overallStatus: %s', $overallStatus));
        }

        return $overallStatus;
    }

    /**
     * @param \stdClass|\stdClass[] $results
     *
     * @throws ResponseException
     * @throws MultipleModeResponseException
     */
    protected function checkObjectsStatus($results)
    {
        $multipleObjectsMode = is_array($results);

        if (!$multipleObjectsMode) {
            if ($results->StatusCode != 'OK') {
                throw new ResponseException($results->StatusMessage, $results->ErrorCode);
            }

            return;
        }

        $errorResults = array_filter($results, function ($result) { return $result->StatusCode != 'OK'; });

        if (count($errorResults)) {
            $errors = array_map(function ($result) { return [$result->StatusMessage, $result->ErrorCode]; }, $errorResults);
            throw new MultipleModeResponseException('Errors present.', $errors);
        }
    }

    /**
     * @param \stdClass|\stdClass[] $result
     * @param callable $decodeIdentifiers Decode identifiers function. It should receive \stdClass object and return int|string.
     *
     * @return int|string|int[]|string[] Identifier(s) of created object(s).
     *                                   In case of multiple objects mode, indexes in returned array correspond to
     *                                   indexes in input $objects array.
     *
     * @throws MultipleModeResponseException
     */
    protected function processCreateResults($result, callable $decodeIdentifiers)
    {
        $multipleObjectsMode = is_array($result);

        try {
            $exception = null;
            $this->checkObjectsStatus($result);

        } catch (MultipleModeResponseException $e) {
            $exception = $e;
            $result = array_diff_key($result, $e->getErrors());
        }

        if (!$multipleObjectsMode) {
            $identifiers = $decodeIdentifiers($result);
        } else {
            $identifiers = array_map($decodeIdentifiers, $result);
        }

        if ($exception) {
            $exception->setCreatedObjectsIdentifiers($identifiers);
            throw $exception;
        }

        return $identifiers;
    }

    /**
     * @param \stdClass $result
     *
     * @return int|string Legacy identifier (ID, int) or actual identifier (ObjectID, string).
     *
     * @throws ResponseException
     */
    protected function decodeCreatedObjectIdentifier(\stdClass $result)
    {
        if (property_exists($result, 'NewObjectID') && $result->NewObjectID !== null) {
            return $result->NewObjectID;
        }

        if (property_exists($result, 'NewID') && $result->NewID !== 0) {
            return $result->NewID;
        }

        // Only for Configure method - it returns identifier only inside Object property.
        if (property_exists($result, 'Object')) {
            $object = $result->Object;

            if (property_exists($object, 'ID') && $object->ID !== 0) {
                return $object->ID;
            }
        }

        throw new ResponseException(sprintf('No identifier found for result: %s', print_r($result, true)));
    }

    /**
     * @param string $type
     * @param mixed $value
     *
     * @return \SoapVar
     */
    protected function createSoapVar($type, $value)
    {
        return new \SoapVar($value, SOAP_ENC_OBJECT, $type, self::SOAP_NAMESPACE);
    }

    /**
     * @param string $name
     *
     * @return array
     *
     * @throws RequestException
     */
    protected function getResource($name)
    {
        $path = __DIR__.'/Resources/'.$name.'.php';

        if (!file_exists($path)) {
            throw new RequestException(sprintf('Invalid resource: %s', $name));
        }

        return require $path;
    }

}
