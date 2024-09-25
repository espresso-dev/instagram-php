<?php

namespace EspressoDev\Instagram;

class Instagram
{
    const API_URL = 'https://graph.instagram.com/';
    const API_VERSION = 'v20.0';
    const AUTH_URL = 'https://www.instagram.com/oauth/authorize';
    const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    const EXCHANGE_TOKEN_URL = 'https://graph.instagram.com/access_token';
    const REFRESH_TOKEN_URL = 'https://graph.instagram.com/refresh_access_token';

    /**
     * @var string
     */
    private $_appId;

    /**
     * @var string
     */
    private $_appSecret;

    /**
     * @var string
     */
    private $_redirectUri;

    /**
     * @var string
     */
    private $_accessToken;

    /**
     * @var string
     */
    private $_userFields = 'user_id, username';

    /**
     * @var string
     */
    private $_mediaFields = 'caption, id, media_type, media_url, permalink, thumbnail_url, timestamp, username, children{id, media_type, media_url, permalink, thumbnail_url, timestamp, username}';

    /**
     * @var string
     */
    private $_mediaChildrenFields = 'id, media_type, media_url, permalink, thumbnail_url, timestamp, username';


    /**
     * @var int
     */
    private $_timeout = 3000;

    /**
     * @var int
     */
    private $_connectTimeout = 3000;

    /**
     * InstagramBasicDisplay constructor.
     * @param string[string]|string $config configuration parameters
     * @throws InstagramException
     */
    public function __construct($config = null)
    {
        if (is_array($config)) {
            $this->_appId = $config['appId'];
            $this->_appSecret = $config['appSecret'];
            $this->_redirectUri = $config['redirectUri'];
        } elseif (is_string($config)) {
            $this->setAccessToken($config);
        } else {
            throw new InstagramException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * Get login URL
     * @param array $scopes
     * @param string $state
     * @return string
     */
    public function getLoginUrl($scopes = ['instagram_business_basic'], $state = '')
    {
        $params = [
            'client_id' => $this->_appId,
            'redirect_uri' => $this->_redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Get OAuth token
     * @param string $code
     * @return object
     * @throws InstagramException
     */
    public function getOAuthToken($code)
    {
        $params = [
            'client_id' => $this->_appId,
            'client_secret' => $this->_appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->_redirectUri,
            'code' => $code,
        ];

        $response = $this->_makeCall(self::TOKEN_URL, $params, 'POST');

        if (isset($response->access_token)) {
            $this->setAccessToken($response->access_token);
            return $response;
        }

        throw new InstagramException('Error getting access token: ' . print_r($response, true));
    }

    /**
     * Get long-lived token
     * @return object
     * @throws InstagramException
     */
    public function getLongLivedToken()
    {
        if (!$this->_accessToken) {
            throw new InstagramException('No access token set. Call getOAuthToken() first.');
        }

        $params = [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->_appSecret,
            'access_token' => $this->_accessToken,
        ];

        $response = $this->_makeCall(self::EXCHANGE_TOKEN_URL, $params);

        if (isset($response->access_token)) {
            $this->setAccessToken($response->access_token);
            return $response;
        }

        throw new InstagramException('Error getting long-lived token: ' . print_r($response, true));
    }

    /**
     * Refresh long-lived token
     * @return object
     * @throws InstagramException
     */
    public function refreshLongLivedToken()
    {
        if (!$this->_accessToken) {
            throw new InstagramException('No access token set. Call getAccessToken() first.');
        }

        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $this->_accessToken,
        ];

        $response = $this->_makeCall(self::REFRESH_TOKEN_URL, $params);

        if (isset($response->access_token)) {
            $this->setAccessToken($response->access_token);
            return $response;
        }

        throw new InstagramException('Error refreshing long-lived token: ' . print_r($response, true));
    }

    /**
     * Get user profile
     * @return object
     * @throws InstagramException
     */
    public function getUserProfile()
    {
        return $this->_makeCall('me', ['fields' => $this->_userFields]);
    }

    /**
     * Get user media
     * @param string $userId
     * @param int $limit
     * @param string $since
     * @param string $until
     * @return object
     * @throws InstagramException
     */
    public function getUserMedia($userId, $limit = 10, array $pagination = [])
    {
        $params = ['fields' => $this->_mediaFields, 'limit' => $limit];

        $paginationParams = ['since', 'until', 'before', 'after'];

        foreach ($pagination as $key => $value) {
            if (in_array($key, $paginationParams)) {
                $params[$key] = $value;
            }
        }

        return $this->_makeCall($userId . '/media', $params);
    }

    /**
     * Get media
     * @param string $mediaId
     * @return object
     * @throws InstagramException
     */
    public function getMedia($mediaId)
    {
        return $this->_makeCall($mediaId, ['fields' => $this->_mediaFields]);
    }

    /**
     * Get media children
     * @param string $mediaId
     * @return object
     * @throws InstagramException
     */
    public function getMediaChildren($mediaId)
    {
        return $this->_makeCall($mediaId . '/children', ['fields' => $this->_mediaChildrenFields]);
    }

    /**
     * Pagination
     * @param object $obj
     * @return object|null
     * @throws InstagramException
     */
    public function pagination($obj)
    {
        if (is_object($obj) && !is_null($obj->paging)) {
            if (!isset($obj->paging->next)) {
                return null;
            }

            $apiCall = explode('?', $obj->paging->next);

            if (count($apiCall) < 2) {
                return null;
            }

            $function = str_replace(self::API_URL, '', $apiCall[0]);
            parse_str($apiCall[1], $params);

            // No need to include access token as this will be handled by _makeCall
            unset($params['access_token']);

            return $this->_makeCall($function, $params);
        }

        throw new InstagramException("Error: pagination() | This method doesn't support pagination.");
    }

    /**
     * Make a call to the Instagram API
     * @param string $endpoint
     * @param array $params
     * @param string $method
     * @return object
     * @throws InstagramException
     */
    private function _makeCall($endpoint, $params = [], $method = 'GET')
    {
        $url = (strpos($endpoint, 'https://') === 0) ? $endpoint : self::API_URL . self::API_VERSION . '/' . $endpoint;

        if ($method === 'GET' && isset($this->_accessToken)) {
            $params['access_token'] = $this->_accessToken;
        }

        $paramString = http_build_query($params);
        $url .= ($method === 'GET') ? '?' . $paramString : '';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->_connectTimeout);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramString);
        }

        $jsonData = curl_exec($ch);
        if (!$jsonData) {
            throw new InstagramException('Error: _makeCall() - cURL error: ' . curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);
        return json_decode($jsonData);
    }

    /**
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->_accessToken = $token;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_accessToken;
    }

    /**
     * @param string $fields
     */
    public function setUserFields($fields)
    {
        $this->_userFields = $fields;
    }

    /**
     * @param string $fields
     */
    public function setMediaFields($fields)
    {
        $this->_mediaFields = $fields;
    }

    /**
     * @param string $fields
     */
    public function setMediaChildrenFields($fields)
    {
        $this->_mediaChildrenFields = $fields;
    }

    public function setTimeout($timeout)
    {
        $this->_timeout = $timeout;
    }

    public function setConnectTimeout($connectTimeout)
    {
        $this->_connectTimeout = $connectTimeout;
    }
}
