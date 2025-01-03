<?php
/**
 * User: Joe Linn
 * Date: 9/12/13
 * Time: 4:46 PM
 *
 * Modified:
 * User: George Colon
 * Date: 01/03/2025
 * Time: 4:19 PM
 * Notable changes:
 *
 * 1.    use GuzzleHttp\Client;
 * •    Guzzle 7 is in the package guzzlehttp/guzzle, so you import from that namespace.
 * 2.    new Client([...])
 * •    We pass an array with base_uri instead of instantiating with $baseUrl directly.
 * 3.    request('POST', $endpoint, [...])
 * •    Instead of $client->post(...)->send(), Guzzle 7 has a single call that returns a response object.
 * 4.    ServerException
 * •    Guzzle 7 no longer uses ServerErrorResponseException. The modern approach is GuzzleHttp\Exception\ServerException.
 * 5.    json_decode($rawBody, true)
 * •    Mandrill typically sends JSON responses. Converting it to an associative array makes it easier to work with in PHP.
 *
 */
namespace Jlinn\Mandrill;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Jlinn\Mandrill\Exception\APIException;

abstract class Api
{
    const BASE_URL = 'https://mandrillapp.com/api/1.0/';

    /**
     * @var string Mandrill API key
     */
    protected $apiKey;

    /**
     * @var string Used to store an alternative base url for the API. Typically used only for testing.
     */
    protected $baseUrl;

    /**
     * @param string $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Set an alternative base url for the API. Typically used for testing purposes.
     *
     * @param string $url
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }

    /**
     * Send a request to Mandrill. All requests are sent via HTTP POST.
     *
     * @param  string $url
     * @param  array  $body
     * @return array
     *
     * @throws APIException
     */
    protected function request($url, array $body = [])
    {
        // Determine which base URL to use
        $baseUrl = $this->baseUrl ?? self::BASE_URL;

        // Guzzle 7: configure the client with a base_uri
        $client = new Client([
            'base_uri' => $baseUrl,
        ]);

        // Insert API key
        $body['key'] = $this->apiKey;

        // Derive the section from the called class (e.g. 'Messages')
        $section = explode('\\', get_called_class());
        $section = strtolower(end($section));  // e.g. 'messages'

        $endpoint = sprintf('%s/%s.json', $section, $url);

        try {
            // Use 'json' => $body to send JSON in the request body
            $response = $client->request('POST', $endpoint, [
                'json' => $body,
            ]);

            $rawBody = (string) $response->getBody();
        } catch (ServerException $e) {
            // Mandrill often returns a JSON error response
            $responseBody = (string) $e->getResponse()->getBody();
            $responseData = json_decode($responseBody, true);

            // If Mandrill provides error details in JSON, we can pass them to our exception
            if (isset($responseData['message'], $responseData['code'], $responseData['status'], $responseData['name'])) {
                throw new APIException(
                    $responseData['message'],
                    $responseData['code'],
                    $responseData['status'],
                    $responseData['name'],
                    $e
                );
            }

            // If we don't have structured data, re-throw the original exception or wrap it
            throw new APIException($e->getMessage(), $e->getCode(), 'ServerError', 'ServerException', $e);
        }

        return json_decode($rawBody, true);
    }
}