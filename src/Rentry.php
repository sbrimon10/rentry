<?php
/*
*   Rentry.co API PHP library
*   Author: SB Rimon <sbrimon10@gmail.com>
*   Version:1.0.0  
*   
*/




class RentryAPI {
    private $baseProtocol;
    private $baseUrl;
    private $baseReferer;
    private $headers;
    private $cookieFile;
    private $client;

    public function __construct($baseProtocol='https://',$baseUrl='rentry.co') {
        $this->baseProtocol = $baseProtocol;
        $this->baseUrl = $baseUrl;
        $this->baseReferer = $this->baseProtocol . $this->baseUrl;
        $this->headers = ['Referer: ' . $this->baseReferer];
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cookies');
        $this->client = new CurlClient();
    }
    /**
     * To Check the Raw Data
     * @param (Required) url
     */
    public function raw($url) {
        $response = $this->client->get($this->baseProtocol . $this->baseUrl . '/api/raw/' . $url, $this->headers);
        return json_decode($response['data'], true);
    }

    /**
     * To Check the Fetch Data
     * @param (Required) url
     * @param (Required) editCode
     */
    public function fetch($url,$editCode) {
       
        $response = $this->client->get($this->baseProtocol . $this->baseUrl, $this->headers);

        // Extract CSRF token from response HTML
        $csrfToken = $this->extractCSRFToken($response['data']);

        // Build payload for the edit request
        $payload = [
            'csrfmiddlewaretoken' => $csrfToken,
            'edit_code' => $editCode
        ];


        // Perform POST request to edit API endpoint
        $editUrl = $this->baseProtocol . $this->baseUrl . '/api/fetch/' . $url;
        $editResponse = $this->client->post($editUrl, $payload, $this->headers);

        return json_decode($editResponse['data'], true);
    }
    /**
     * To Edit The Data
     * @param (Required) url
     * @param (Required) editCode
     * @param (Required) text
     * @param (optional) metadata
     * @param (optional) mode by default (false). This causes only these metadata options to change, rather than a full replacement.
     * 
     */
    public function edit($url, $editCode, $text,$metadata=null,$mode=false) {
        $response = $this->client->get($this->baseProtocol . $this->baseUrl, $this->headers);

        // Extract CSRF token from response HTML
        $csrfToken = $this->extractCSRFToken($response['data']);

        // Build payload for the edit request
        $payload = [
            'csrfmiddlewaretoken' => $csrfToken,
            'edit_code' => $editCode,
            'text' => $text
            
        ];
        if ($mode===true) {
            $payload['update_mode'] = 'upsert';
        }
        // Add meta data to payload if provided
        if (!empty($metadata)) {
            $payload['metadata'] = is_array($metadata)? $this->metaFormat($metadata): $metadata;
        }
        // Perform POST request to edit API endpoint
        $editUrl = $this->baseProtocol . $this->baseUrl . '/api/edit/' . $url;
        $editResponse = $this->client->post($editUrl, $payload, $this->headers);

        return json_decode($editResponse['data'], true);
    }
    /**
     * To Create New Data
     * @param (Optional) url
     * @param (Optional) editCode
     * @param (Required) text
     * @param (optional) metadata
     * 
     */
    public function create($url= null,$editCode = null, $text,$metadata=null) {
        $response = $this->client->get($this->baseProtocol . $this->baseUrl, $this->headers);

        // Extract CSRF token from response HTML
        $csrfToken = $this->extractCSRFToken($response['data']);

        // Build payload for the create request
        $payload = [
            'csrfmiddlewaretoken' => $csrfToken,
            'text' => $text,
            'url'   =>$url
        ];
        // Add meta data to payload if provided
        if (!empty($metadata)) {
            $payload['metadata'] = is_array($metadata)? $this->metaFormat($metadata): $metadata;
        }
        // Add edit code to payload if provided
        if (!empty($editCode)) {
            $payload['edit_code'] = $editCode;
        }


        // Perform POST request to create API endpoint
        $createUrl = $this->baseProtocol . $this->baseUrl . '/api/new';
        $createResponse = $this->client->post($createUrl, $payload, $this->headers);

        return json_decode($createResponse['data'], true);
    }
    public function delete($url, $editCode) {
        $response = $this->client->get($this->baseProtocol . $this->baseUrl, $this->headers);

        // Extract CSRF token from response HTML
        $csrfToken = $this->extractCSRFToken($response['data']);

        // Build payload for the delete request
        $payload = [
            'csrfmiddlewaretoken' => $csrfToken,
            'edit_code' => $editCode
            ];

        // Perform POST request to DELETE API endpoint
        $deleteUrl = $this->baseProtocol . $this->baseUrl . '/api/delete/' . $url;
        $deleteResponse = $this->client->post($deleteUrl, $payload, $this->headers);

        return json_decode($deleteResponse['data'], true);

    }

    private function metaFormat($arrayData)
    {
        return implode("\n", array_map(fn($key, $value) => "$key = $value", array_keys($arrayData), $arrayData));
    }
    private function extractCSRFToken($html) {
        $pattern = '/<input(?:.*?)name=\"csrfmiddlewaretoken\"(?:.*)value=\"([^"]+).*>/i';
        preg_match($pattern, $html, $matches);
        return $matches[1];
    }
}

class CurlClient {
    private $cookieFile;

    public function __construct() {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cookies');
    }

    public function get($url, $headers = []) {
        return $this->request($url, 'GET', null, $headers);
    }

    public function post($url, $data = null, $headers = []) {
        return $this->request($url, 'POST', $data, $headers);
    }

    private function request($url, $method, $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return ['status_code' => $statusCode, 'data' => $response];
    }
}