<?php

class SAPWC_API_Client
{

    private $base_url;
    private $session_id;
    private $route_id;
    private $cookie;
    private $conn = ['ssl' => false]; // Default SSL to false

    public function __construct($url)
    {
        $this->base_url = untrailingslashit($url) . '/b1s/v1';
    }

    public function get_base_url()
    {
        return $this->base_url;
    }

    public function get_cookie_header()
    {
        return 'B1SESSION=' . $this->session_id . '; ' . $this->route_id;
    }

    public function login($user, $pass, $db, $ssl = false)
    {
        $this->conn['ssl'] = $ssl; // store ssl preference

        $endpoint = $this->base_url . '/Login';

        $body = json_encode([
            'UserName' => $user,
            'Password' => $pass,
            'CompanyDB' => $db
        ]);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $body,
            'timeout' => 20,
            'sslverify' => $ssl
        ]);

        $this->log_response('LOGIN', $response);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($data['SessionId'])) {
            $this->session_id = $data['SessionId'];
            $this->last_login_response = $data;
            $this->company_db = $db;

            // Extraer ROUTEID
            $cookies = wp_remote_retrieve_header($response, 'set-cookie');
            if (is_array($cookies)) {
                foreach ($cookies as $cookie) {
                    if (strpos($cookie, 'ROUTEID=') !== false) {
                        $parts = explode(';', $cookie);
                        foreach ($parts as $part) {
                            if (strpos($part, 'ROUTEID=') !== false) {
                                $this->route_id = trim($part);
                                break 2;
                            }
                        }
                    }
                }
            }

            return ['success' => true];
        }

        return ['success' => false, 'message' => $data['error']['message']['value'] ?? 'Respuesta inválida'];
    }

    public function get($relative_path)
    {
        $url = $this->base_url . $relative_path;

        $response = wp_remote_get($url, [
            'headers' => [
                'Cookie' => $this->get_cookie_header(),
                'Accept' => 'application/json'
            ],
            'timeout' => 20,
            'sslverify' => !empty($this->conn['ssl'])
        ]);

        //$this->log_response('GET ' . $relative_path, $response);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function logout()
    {
        if ($this->session_id) {
            $endpoint = $this->base_url . '/Logout';

            $response = wp_remote_post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Cookie' => $this->get_cookie_header()
                ],
                'timeout' => 20,
                'sslverify' => !empty($this->conn['ssl'])
            ]);

            $this->log_response('LOGOUT', $response);

            if (is_wp_error($response)) {
                return ['success' => false, 'message' => $response->get_error_message()];
            }

            return wp_remote_retrieve_response_code($response) === 200;
        }

        return false;
    }

    private function log_response($context, $response)
    {
        if (is_wp_error($response)) {
            error_log("[$context] Error cURL: " . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            error_log("[$context] HTTP $code\n$body");
        }
    }

    public function get_version_info()
    {
        if (isset($this->last_login_response['Version'])) {
            return [
                'ServiceLayerVersion' => $this->last_login_response['Version'],
                'SAPB1Version'        => '–',
                'CompanyDB'           => $this->company_db ?? '–',
                'DatabaseType'        => '–',
            ];
        }

        return null;
    }



    public function get_price_lists()
    {
        $response = $this->get('/PriceLists?$select=PriceListNo,PriceListName');

        if (!isset($response['value'])) return [];

        return array_map(function ($item) {
            return [
                'id' => $item['PriceListNo'],
                'name' => $item['PriceListName']
            ];
        }, $response['value']);
    }
}
