# Salesforce Marketing Cloud PHP SDK #
DataProBoston Salesforce Marketing Cloud (formerly ExactTarget) PHP SDK.

##Overview ##
DataProBoston SDK provides clean and easy way to interact with Salesforce Marketing Cloud API's.
Currently only SOAP API is supported, REST API support will be added in future releases.
   
## Requirements ##
PHP version 5.6.x, 7.x

Extensions:
   - soap
   - curl [recommended]
   
DataProBoston SDK uses Guzzle as HTTP client, its' requirements can be found on 
<http://docs.guzzlephp.org/en/latest/overview.html#requirements>

## Installation ##
composer require dataproboston/marketing-cloud-php-sdk

## Basic usage

    use DataProBoston\MarketingCloud\AuthClient;
    use DataProBoston\MarketingCloud\SoapClient;
    
    $authClient = new AuthClient([
        'clientId' => CLIENT_ID,
        'clientSecret' => CLIENT_SECRET,
    ]);
    
    $client = new SoapClient(null, [], $authClient);
    
    // Create object
    $listId = $client->create('List', [
        'ListName' => 'test',
    ]);
    
    // Create mulpiple objects in one call - $listsIds is an array of objects' identifiers.
    $listsIds = $client->create('List', [
        [
            'ListName' => 'test5',
        ],
        [
            'ListName' => 'test6',
        ],    
    ]);
    
    // Array of properties to retrieve.
    $properties = ['ListName'];
    
    $filter = ['ID', '=', $listId];
    
    // Retrieve one object - $list is an object of \stdClass
    $list = $client->retrieveOne('List', $properties, $filter);
    
    // Retrieve collection of objects - $lists is an array of objects of \stdClass
    $lists = $client->retrieve('List', $properties);
    
    $client->update('List', [
        'ID' => $listId,
        'ListName' => 'test2',
    ]);
    
    $client->delete('List', [
        'ID' => $listId,
    ]);
    
    // Delete mulpiple objects in one call
    $client->delete('List', [
        [
            'ID' => $listsIds[0],
        ],
        [
            'ID' => $listsIds[1],
        ],    
    ]);    
    
In addition to low-level SoapClient and RestClient, DataProBoston SDK provides high-level Driver abstraction,
that provides useful high-level methods.  
Driver also can be used as a source for examples.  


Example workflow to send email campaign using Driver:  
    
    use DataProBoston\MarketingCloud\Driver;
    
    $driver = new Driver($soapClient);
    
    $listId = $driver->createList('Test campaign');
    
    $driver->createSubscribers(['youremail@gmail.com'], $listId);
    
    $emailId = $driver->createEmail('Test campaign', 'Test campaign subject', 'Test campaign body');
    
    $senderProfileId = $driver->createSenderProfile('Test sender profile', 'Sender name', 'senderemail@gmail.com');
    
    // Assumed that API account has DeliveryProfile with CustomerKey value 'Default'.
    $sendClassificationId = $driver->createSendClassification('Test send classification', $senderProfileId, 'Default');
    
    $emailSendDefinitionId = $driver->createEmailSendDefinition($listId, $emailId, $sendClassificationId);
    
    $sendId = $driver->sendEmailSendDefinition($emailSendDefinitionId);
     
     
    
## Documentation
    
### AuthClient
    
AuthClient is extracted in a separate class to be shared between SoapClient and RestClient (will be available in future releases). 

    
**Constructor parameters:**
- options (array). Available options:
    - clientId (string). Required.
    - clientSecret (string). Required.
    - authTokenMinExpirationTime (int). Min time in seconds remaining to token expiration
      that will cause token refresh. Default value - 30.
    - timeout (float). Request timeout in seconds. Default value - 10.
    - sslVerifyPeer (bool|string). Enables / disables SSL certificate verification.
      See <http://docs.guzzlephp.org/en/latest/request-options.html#verify>.
      Default value - false.
    
   
### SoapClient    
        
**Constructor parameters:**
- wsdl (string). Absolute path to WSDL file. If null, the bundled one (depending on wsdlInstance option) will be used.
- options (array). Available options:
    - wsdlInstance (string). Instance of bundled WSDL to use.
      Available instances: 'main', 's4', 's6', 's7', 'test'. Default value - 'main'.
    - timeout (float). Request timeout in seconds. Default value - 60.
    - sslVerifyPeer (bool|string). Enables / disables SSL certificate verification.
      See <http://docs.guzzlephp.org/en/latest/request-options.html#verify>.
      Default value - false.
- authClient (AuthClient).

#### Create method

**Method arguments:**
- objectType (string).
- object (array). Object argument can be:
    - associative array with object's properties (one object mode).
    - numeric array of associative arrays with objects' properties (multiple objects mode).
                 
- upsert (bool). Only some objects support upsert.

**Return value:**

Object identifier or array of objects identifiers depending on $object argument.
In case of multiple objects mode, indexes in returned array will correspond to indexes in $object argument.

On error, method will throw an instance of 
DataProBoston\MarketingCloud\Exception\ClientException interface.

See "Exceptions handling" for more information.         

>Note:
>Marketing Cloud API uses two types of object's identifiers:
>- Legacy identifier (ID, int).
>- Actual identifier (ObjectID, string).
>
>Official documentation doesn't provides information about the identifier type that is used for each object.
>
>Create method automatically returns correct identifier.
>
>To update / delete / filter by an identifier, one should correctly specify it's property name ('ID' or 'ObjectID').
>Currently, property name can be simply determined by create method return value - if it's an integer - than 
property name is 'ID', if string - 'ObjectID'. 
>
>Auto selecting correct identifier property is planned for future releases.

>Note:
>Marketing Cloud API uses special Configure method to create / update / delete PropertyDefinition and Role 
>objects. To standardize usage, DataProBoston SDK provides support of this operations via general Create / Update / Delete methods,
>and internally calls Configure method.

#### Update and Delete methods.

**Method arguments:**
- objectType (string).
- object (array). Same format as in create method.  
                  For update method object identifier property should be specified alongside 
                  with object's properties to update.   
                  For delete method object identifier property only is enough.  
                  Usage of other objects' properties instead of identifiers to select objects for update/delete is currently undetermined.

**Return value:**

No value is returned.

On error, method will throw an instance of 
DataProBoston\MarketingCloud\Exception\ClientException interface.

See "Exceptions handling" for more information.  

#### Perform method

**Method arguments:**
- objectType (string).
- object (array). Same format as in create method. 
- action (string). String 'start' is used in most cases, see <https://developer.salesforce.com/docs/atlas.en-us.noversion.mc-apis.meta/mc-apis/perform.htm>

**Return value:**

Task identifier or array of tasks identifiers depending on $object argument.
In case of multiple objects mode, indexes in returned array will correspond to indexes in $object argument.

On error, create method will throw an instance of 
DataProBoston\MarketingCloud\Exception\ClientException interface.

See "Exceptions handling" for more information.  

#### Retrieve and RetrieveOne methods

**Method arguments:**
- objectType (string).
- properties (array). Properties of object(s) to retrieve.
- filters (array). 3-element numeric array, in 2 possible forms:
    - Simple condition: \[$property, $operator, $value\]
    - Logical condition: \[$leftCondition, $operator, $rightCondition\].    
      Applied if operator is 'and' or 'or'.      
      $leftCondition / $rightCondition itself could be simple conditions or nested logical conditions.        
      Dates in filter values should be represented by \DateTime objects.
- sinceLastBatch (bool). Only for Retrieve method.     
       Retrieve only objects added/modified since last retrieve call with the same properties and filters.


**Return value:**

Array of \stdClass objects for Retrieve method, and object of \stdClass for RetrieveOne method.
In case of no results, RetrieveOne method will return null. In case of more than 1 result, RetrieveOne method will throw 
ResponseException exception.

**Available filter operators:**
- =
- !=
- \>
- <
- \>=      
- <=
- isNull
- !isNull
- between
- in
- like
- and
- or

**Usage:**

    $sendId = 11111;
    $startDate = new \DateTime();
    $finishDate = new \DateTime();
    $startDate->modify('-1 day');

    $properties = ['SubscriberKey', 'EventDate'];

    $filter = [
        ['SendID', '=', $sendId],
        'and',
        ['EventDate', 'between', [$startDate, $finishDate]]
    ];

    $sentEvents = $client->retrieve('SentEvent', $properties, $filter);

#### RetrieveMore method

Retrieve method returns max. 2500 objects at one call. To retrieve all objects, one should check if there are more results
using hasMoreResults() method and call retrieveMore(), if any. 


**Method arguments:**

- requestId (string|null). If not specified, the one from getLastRequestId() will be used.
                           In this case it's not possible to call other methods while iterating through results.
                           To be able to call other methods, one should obtain requestId using getLastRequestId()
                           and pass it to retrieveMore().
                           
**Return value:**
                           
Same as in Retrieve method.   
                        
**Usage:**

    // without any other method calls
    $subscribers = $client->retrieve('Subscriber', ['ID']);    
    while ($client->hasMoreResults()) {        
        $subscribers = $client->retrieveMore();
    }
    
    
    // with other method calls
    $subscribers = $client->retrieve('Subscriber', ['ID']);    
    $requestId = $client->getLastRequestId();
    while ($client->hasMoreResults()) {        
        $subscribers = $client->retrieveMore($requestId);
    }    
    
    
#### Exceptions handling
    
Exceptions thrown by DataProBoston SDK are instances of DataProBoston\MarketingCloud\Exception\ClientException interface.

List of available exceptions:
- RequestException. Thrown if an error occurred before request to API.
- ResponseException. Thrown if an error occurred after request to API.
    - MultipleModeResponseException. Thrown if an error occurred after request to API, 
    in Create / Update / Delete / Perform methods in multiple objects mode.
    
In multiple objects mode some objects can be processed successfully, some have errors.
If at least one error present, DataProBoston SDK will throw MultipleModeResponseException exception.
This exception has 2 useful methods:
- getErrors(). Returns an array of errors, where each error is 2-element array
               of message (string) and code (int). 
               Indexes correspond to indexes in $object argument of SoapClient method call.
- getCreatedObjectsIdentifiers(). Returns an array of identifiers identically 
                to return value of Create / Perform methods.
                Indexes correspond to indexes in $object argument of SoapClient method call.




**Usage:**

    try {
        $client->create('List', [
            [
                'ListName' => 'test1',
            ],
            [
                'ListName' => 'test2',
            ],    
        ]);  
    } catch (MultipleModeResponseException $e) {
        // errors
        $errors = $e->getErrors();
        
        // successfully created objects identifiers
        $identifiers = $e->getCreatedObjectsIdentifiers();
    }     
  
    

#### Utility methods

- describe(objectType). Returns array of all objectType properties (\stdClass objects).
- describeSubscriberAttributes(). Returns array of all Subscriber object attributes ("extended properties") (\stdClass objects).
- systemStatus(). Returns system status string: 'OK'|'InMaintenance'|'UnplannedOutage'.
- versionInfo(includeVersionHistory). Returns API version info (\stdClass object).
- requestEndpoint(). Returns API endpoint URL (string).
- getFilterOperators(). Returns array of available filter operators.
- getLastRequestId(). Returns last request id (string).
- hasMoreResults(). Indicates if retrieve request has more results (bool).
- execute. Usable only for retrieving user token:

        $requests = [
            'Name' => 'GetUserToken',
        ];

        $response = $client->execute($requests);

## Running tests

Copy phpunit.xml.dist to phpunit.xml and provide your settings (clientId, clientSecret, etc.) in \<php> section.

## Issues and support

Feel free to contact us on Github with any issues / questions / suggestions.

## Copyright and license

(c) 2017 Yaroslav Honcharuk <yaroslav.xs@gmail.com>

Licensed under the MIT License. For the full copyright and license information, please view the LICENSE file that was distributed 
with this source code.








