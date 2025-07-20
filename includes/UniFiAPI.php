<?php
/**
 * UniFi Controller API Integration
 * Based on UniFi API documentation and best practices
 */

class UniFiAPI {
    private $host;
    private $username;
    private $password;
    private $site;
    private $version;
    private $cookies;
    private $debug;

    public function __construct($host, $username, $password, $site = 'default', $version = 'UDMP-unifiOS', $debug = false) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->site = $site;
        $this->version = $version;
        $this->debug = $debug;
        $this->cookies = tempnam(sys_get_temp_dir(), 'unifi_cookies');
    }

    /**
     * Login to UniFi Controller
     */
    public function login() {
        $login_data = [
            'username' => $this->username,
            'password' => $this->password
        ];

        if ($this->version === 'UDMP-unifiOS') {
            $url = $this->host . '/api/auth/login';
        } else {
            $url = $this->host . '/api/login';
        }

        $response = $this->curl_request($url, $login_data, 'POST');
        
        if ($response === false) {
            throw new Exception('Failed to connect to UniFi Controller');
        }

        $data = json_decode($response, true);
        
        if ($this->version === 'UDMP-unifiOS') {
            if (!isset($data['meta']['rc']) || $data['meta']['rc'] !== 'ok') {
                throw new Exception('UniFi login failed: ' . ($data['meta']['msg'] ?? 'Unknown error'));
            }
        } else {
            if (!isset($data['meta']['rc']) || $data['meta']['rc'] !== 'ok') {
                throw new Exception('UniFi login failed: ' . ($data['meta']['msg'] ?? 'Unknown error'));
            }
        }

        return true;
    }

    /**
     * Authorize a guest device
     */
    public function authorizeGuest($mac, $minutes = 480, $up_bandwidth = null, $down_bandwidth = null, $quota = null) {
        if (!$this->login()) {
            return false;
        }

        $mac = strtolower(str_replace([':', '-'], '', $mac));
        $mac = implode(':', str_split($mac, 2));

        $auth_data = [
            'cmd' => 'authorize-guest',
            'mac' => $mac,
            'minutes' => intval($minutes)
        ];

        // Add bandwidth limits if specified
        if ($up_bandwidth !== null) {
            $auth_data['up'] = intval($up_bandwidth);
        }
        if ($down_bandwidth !== null) {
            $auth_data['down'] = intval($down_bandwidth);
        }
        if ($quota !== null) {
            $auth_data['bytes'] = intval($quota);
        }

        if ($this->version === 'UDMP-unifiOS') {
            $url = $this->host . '/proxy/network/api/s/' . $this->site . '/cmd/stamgr';
        } else {
            $url = $this->host . '/api/s/' . $this->site . '/cmd/stamgr';
        }

        $response = $this->curl_request($url, $auth_data, 'POST');
        
        if ($response === false) {
            throw new Exception('Failed to authorize guest');
        }

        $data = json_decode($response, true);
        
        if (!isset($data['meta']['rc']) || $data['meta']['rc'] !== 'ok') {
            throw new Exception('Guest authorization failed: ' . ($data['meta']['msg'] ?? 'Unknown error'));
        }

        return true;
    }

    /**
     * Get guest devices
     */
    public function getGuests() {
        if (!$this->login()) {
            return false;
        }

        if ($this->version === 'UDMP-unifiOS') {
            $url = $this->host . '/proxy/network/api/s/' . $this->site . '/stat/guest';
        } else {
            $url = $this->host . '/api/s/' . $this->site . '/stat/guest';
        }

        $response = $this->curl_request($url, null, 'GET');
        
        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        
        if (!isset($data['meta']['rc']) || $data['meta']['rc'] !== 'ok') {
            return false;
        }

        return $data['data'] ?? [];
    }

    /**
     * Unauthorize/kick a guest
     */
    public function unauthorizeGuest($mac) {
        if (!$this->login()) {
            return false;
        }

        $mac = strtolower(str_replace([':', '-'], '', $mac));
        $mac = implode(':', str_split($mac, 2));

        $unauth_data = [
            'cmd' => 'unauthorize-guest',
            'mac' => $mac
        ];

        if ($this->version === 'UDMP-unifiOS') {
            $url = $this->host . '/proxy/network/api/s/' . $this->site . '/cmd/stamgr';
        } else {
            $url = $this->host . '/api/s/' . $this->site . '/cmd/stamgr';
        }

        $response = $this->curl_request($url, $unauth_data, 'POST');
        
        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        
        return isset($data['meta']['rc']) && $data['meta']['rc'] === 'ok';
    }

    /**
     * Logout from UniFi Controller
     */
    public function logout() {
        if ($this->version === 'UDMP-unifiOS') {
            $url = $this->host . '/api/auth/logout';
        } else {
            $url = $this->host . '/logout';
        }

        $this->curl_request($url, null, 'POST');
        
        // Clean up cookie file
        if (file_exists($this->cookies)) {
            unlink($this->cookies);
        }
    }

    /**
     * Make cURL request
     */
    private function curl_request($url, $data = null, $method = 'GET') {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Frontier Tower Captive Portal v1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false || !empty($error)) {
            if ($this->debug) {
                error_log("cURL Error: $error");
            }
            return false;
        }

        if ($http_code >= 400) {
            if ($this->debug) {
                error_log("HTTP Error: $http_code - Response: $response");
            }
            return false;
        }

        return $response;
    }

    /**
     * Destructor - ensure logout
     */
    public function __destruct() {
        $this->logout();
    }
}
?>
