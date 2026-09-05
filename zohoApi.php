<?php
namespace Rahul\Helpers;

use Exception;

class ZohoBooks
{
    private $accessToken;
    private $orgId;
    private $baseUrl = 'https://www.zohoapis.com/books/v3/';

    public function __construct($accessToken)
    {
        $this->accessToken = $accessToken;
        $this->orgId = defined('organizationId') ? organizationId : 0;
    }

    public function request($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->baseUrl . $endpoint . '?organization_id=' . $this->orgId;
        $headers = [
            "Authorization: Zoho-oauthtoken {$this->accessToken}",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Zoho API request error: $error");
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    public static function exchangeToken($code, $clientId, $clientSecret, $redirectUri)
    {
        $url = 'https://accounts.zoho.com/oauth/v2/token';
        $params = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Zoho token exchange error: $error");
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    public static function refreshToken($clientId, $clientSecret, $refreshToken, $redirectUri)
    {
        $url = 'https://accounts.zoho.com/oauth/v2/token';
        $params = http_build_query([
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'refresh_token'
        ]);

        $ch = curl_init("$url?$params");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Zoho token refresh error: $error");
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    public static function getValidAccessToken($userId)
    {
        if (isset($userId) && $userId == 0) {
            $userId = 1;
            $dbName = defined('common_db') ? common_db : null;
            $res = RpAction::dbAction('token', 4, [], "api_user = $userId", 0, $dbName);
        } else {
            $res = RpAction::dbAction('token', 4, [], "api_user = $userId");
        }

        $row = $res && is_object($res) ? $res->fetch_assoc() : null;
        if (!$row) {
            throw new Exception("No token found for user $userId");
        }

        if (isset($row['expires_at']) && time() + 300 < intval($row['expires_at'])) {
            return $row['access_token'];
        }

        $clientId = defined('client_idb') ? client_idb : '';
        $clientSecret = defined('client_secretb') ? client_secretb : '';
        $baseUri = defined('BASE') ? BASE : '';

        $refresh = self::refreshToken(
            $clientId,
            $clientSecret,
            $row['refresh_token'],
            $baseUri . "admin.php?page=outh"
        );

        if (!isset($refresh['access_token'])) {
            file_put_contents('zoho_refresh_error.log', json_encode($refresh));
            throw new Exception('Failed to refresh Zoho token');
        }

        $update = [
            'access_token' => $refresh['access_token'],
            'expires_in' => $refresh['expires_in'],
            'expires_at' => time() + intval($refresh['expires_in']),
            'date' => date('Y-m-d H:i:s')
        ];

        RpAction::dbAction('token', 2, $update, "api_user = $userId");
        return $refresh['access_token'];
    }

    public static function getTokenUser($userId)
    {
        return self::getValidAccessToken($userId);
    }

    public static function getApiData($data, $url_path = "", $method = "", $crm = 2, $fullPath = "", $query_param = "", $is_org = 1)
    {
        $userId = $_SESSION['id'] ?? ($_SERVER['HTTP_X_POS_ID'] ?? 0);
        if (isset($_POST['action']) && $_POST['action'] === 'customer_switch') {
            $access_token = self::getValidAccessToken(1);
        } else {
            $access_token = self::getValidAccessToken($userId);
        }

        $base = "https://www.zohoapis.com/books/v3/";
        if ($method === "") $method = 0;
        
        $orgId = defined('organizationId') ? organizationId : '';
        $api = '';
        if ($is_org) {
            $api .= $base . $url_path . "?organization_id=" . $orgId;
        }
        if (!empty($query_param)) $api .= $query_param;
        if (!empty($fullPath)) {
            $api = ($is_org == 0) ? $fullPath : $fullPath . "?organization_id=" . $orgId;
        }

        $headers = [];
        $curlOptions = [
            CURLOPT_URL => $api,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        switch ($method) {
            case 0:
                if (!empty($data)) {
                    $api .= '&' . http_build_query($data);
                    $curlOptions[CURLOPT_URL] = $api;
                }
                $headers[] = "Authorization: Zoho-oauthtoken $access_token";
                $headers[] = "Content-Type: application/x-www-form-urlencoded;charset=UTF-8";
                break;

            case 1:
                $curlOptions[CURLOPT_URL] = $url_path;
                $curlOptions[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($data);
                }
                $headers[] = "Content-Type: application/x-www-form-urlencoded";
                break;

            case 2:
            case 3:
            case 4:
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                $headers[] = "Authorization: Zoho-oauthtoken $access_token";
                $headers[] = "Content-Type: application/json";
                if ($method == 3) $curlOptions[CURLOPT_CUSTOMREQUEST] = "PUT";
                if ($method == 4) $curlOptions[CURLOPT_CUSTOMREQUEST] = "DELETE";
                break;

            case 5:
                $curlOptions[CURLOPT_POST] = true;
                $curlOptions[CURLOPT_POSTFIELDS] = $data;
                $headers[] = "Authorization: Zoho-oauthtoken $access_token";
                $headers[] = "Content-Type: multipart/form-data";
                break;
        }

        $curlOptions[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $output = curl_exec($ch);
        curl_close($ch);

        $return = !empty($output) ? json_decode($output) : null;

        if (isset($return->code)) {
            if ($return->code == 0 && $method != 1) {
                return $return;
            } else {
                $mgs = "Code: {$return->code} Error: " . str_replace("'", "", $return->message);
                if (!strpos($url_path, "backorder") && ($_SERVER['SERVER_NAME'] ?? '') != 'wh.neufinix.com') {
                    echo "<script>boom('$mgs', 'error', true)</script>";
                }
            }
        } else {
            echo "<script>boom('Error: Empty Response!', 'error', true)</script>";
        }

        return null;
    }
}
