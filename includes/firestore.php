<?php
/**
 * Samrajya Mens Wear - Firebase Firestore REST Client
 * Communicates with Google Cloud Firestore v1 REST API using Service Account OAuth2 JWT.
 * No heavy C-extensions or gRPC required; works natively in pure PHP on Windows/Linux.
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

class FirestoreClient {
    private string $projectId;
    private ?string $clientEmail;
    private ?string $privateKey;
    private ?string $tokenUri;
    private string $database = '(default)';
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;
    private string $cacheFile;

    public function __construct(array $config) {
        $serviceAccountPath = $config['service_account_path'] ?? '';
        $this->projectId = $config['project_id'] ?? '';
        $this->cacheFile = (defined('STORAGE_PATH') ? STORAGE_PATH : (APP_ROOT . '/storage')) . '/data/.token_cache_' . md5($this->projectId) . '.json';

        if (!empty($serviceAccountPath) && file_exists($serviceAccountPath)) {
            $json = json_decode(file_get_contents($serviceAccountPath), true);
            if (is_array($json)) {
                $this->clientEmail = $json['client_email'] ?? null;
                $this->privateKey = $json['private_key'] ?? null;
                $this->tokenUri = $json['token_uri'] ?? 'https://oauth2.googleapis.com/token';
                if (empty($this->projectId) && !empty($json['project_id'])) {
                    $this->projectId = $json['project_id'];
                }
            }
        }
    }

    public function isConfigured(): bool {
        return !empty($this->projectId) && !empty($this->clientEmail) && !empty($this->privateKey);
    }

    public function getProjectId(): string {
        return $this->projectId;
    }

    /**
     * Obtains or refreshes Google OAuth2 Access Token using RS256 JWT
     */
    private function getAccessToken(): string {
        if (!empty($this->accessToken) && time() < ($this->tokenExpiry - 60)) {
            return $this->accessToken;
        }

        // Check file cache
        if (file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if (is_array($cached) && !empty($cached['token']) && time() < ($cached['expiry'] - 60)) {
                $this->accessToken = $cached['token'];
                $this->tokenExpiry = (int)$cached['expiry'];
                return $this->accessToken;
            }
        }

        if (!$this->isConfigured()) {
            throw new Exception("Firebase Service Account credentials are not configured or invalid.");
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claimSet = [
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/cloud-platform',
            'aud' => $this->tokenUri,
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlClaimSet = $this->base64UrlEncode(json_encode($claimSet));
        $dataToSign = $base64UrlHeader . "." . $base64UrlClaimSet;

        $binarySignature = "";
        $keyResource = openssl_pkey_get_private($this->privateKey);
        if (!$keyResource) {
            throw new Exception("Invalid private key format in Firebase service account file: " . openssl_error_string());
        }

        if (!openssl_sign($dataToSign, $binarySignature, $keyResource, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Failed to sign OAuth2 JWT token: " . openssl_error_string());
        }

        $jwt = $dataToSign . "." . $this->base64UrlEncode($binarySignature);

        // Exchange JWT for OAuth2 Token
        $ch = curl_init($this->tokenUri);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Curl error requesting Google OAuth2 token: " . $curlError);
        }

        $result = json_decode($response, true);
        if ($httpCode !== 200 || empty($result['access_token'])) {
            $errorMsg = $result['error_description'] ?? ($result['error'] ?? 'Unknown token exchange error');
            throw new Exception("Failed to acquire Google OAuth2 access token (HTTP $httpCode): " . $errorMsg);
        }

        $this->accessToken = $result['access_token'];
        $this->tokenExpiry = $now + (int)($result['expires_in'] ?? 3600);

        // Save token to cache
        @file_put_contents($this->cacheFile, json_encode([
            'token' => $this->accessToken,
            'expiry' => $this->tokenExpiry
        ]), LOCK_EX);

        return $this->accessToken;
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Executes HTTP Request against Firestore REST API v1
     */
    private function request(string $method, string $path, ?array $body = null, array $queryParams = []): array {
        $token = $this->getAccessToken();
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->database}/documents" . $path;

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Firestore request failed ($method $path): " . $curlError);
        }

        $decoded = json_decode($rawResponse, true);

        if ($httpCode >= 400) {
            if ($httpCode === 404) {
                return ['__not_found' => true, 'code' => 404];
            }
            $msg = $decoded['error']['message'] ?? "HTTP error {$httpCode}";
            throw new Exception("Firestore API Error ({$httpCode}): " . $msg);
        }

        return $decoded ?? [];
    }

    /**
     * Retrieve a single document
     */
    public function getDocument(string $collection, string $documentId): ?array {
        $res = $this->request('GET', "/{$collection}/{$documentId}");
        if (isset($res['__not_found']) && $res['__not_found']) {
            return null;
        }
        return $this->decodeDocument($res);
    }

    /**
     * Set / Overwrite or merge document
     */
    public function setDocument(string $collection, string $documentId, array $data, bool $merge = false): array {
        $firestoreData = $this->encodeFields($data);
        $queryParams = [];
        
        if ($merge) {
            $updateMask = [];
            foreach (array_keys($data) as $field) {
                $updateMask[] = "updateMask.fieldPaths=" . urlencode($field);
            }
            $queryStr = implode('&', $updateMask);
            $res = $this->request('PATCH', "/{$collection}/{$documentId}" . ($queryStr ? '?' . $queryStr : ''), [
                'fields' => $firestoreData
            ]);
        } else {
            $res = $this->request('PATCH', "/{$collection}/{$documentId}", [
                'fields' => $firestoreData
            ]);
        }

        return $this->decodeDocument($res);
    }

    /**
     * Create a document with optional ID or auto-generated ID
     */
    public function createDocument(string $collection, array $data, ?string $documentId = null): array {
        if ($documentId !== null && trim($documentId) !== '') {
            return $this->setDocument($collection, $documentId, $data);
        }

        $firestoreData = $this->encodeFields($data);
        $res = $this->request('POST', "/{$collection}", [
            'fields' => $firestoreData
        ]);
        return $this->decodeDocument($res);
    }

    /**
     * Delete document
     */
    public function deleteDocument(string $collection, string $documentId): bool {
        $this->request('DELETE', "/{$collection}/{$documentId}");
        return true;
    }

    /**
     * List all documents in a collection
     */
    public function listDocuments(string $collection, int $pageSize = 300): array {
        $res = $this->request('GET', "/{$collection}", null, [
            'pageSize' => $pageSize
        ]);

        $documents = [];
        if (!empty($res['documents']) && is_array($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $decoded = $this->decodeDocument($doc);
                if ($decoded) {
                    $documents[] = $decoded;
                }
            }
        }
        return $documents;
    }

    /**
     * Atomic Counter Increment using commit transaction transform
     */
    public function incrementCounter(string $counterId, int $delta = 1): int {
        $token = $this->getAccessToken();
        $docPath = "projects/{$this->projectId}/databases/{$this->database}/documents/counters/{$counterId}";
        
        $body = [
            'writes' => [
                [
                    'transform' => [
                        'document' => $docPath,
                        'fieldTransforms' => [
                            [
                                'fieldPath' => 'current',
                                'increment' => ['integerValue' => (string)$delta]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->database}/documents:commit");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($rawResponse, true);
        if ($httpCode === 200 && !empty($res['writeResults'][0]['transformResults'][0]['integerValue'])) {
            return (int)$res['writeResults'][0]['transformResults'][0]['integerValue'];
        }

        // Fallback: Read-modify-write if transform is unsupported
        $doc = $this->getDocument('counters', $counterId);
        $val = ($doc['current'] ?? 0) + $delta;
        $this->setDocument('counters', $counterId, [
            'current' => $val,
            'updatedAt' => date('c')
        ]);
        return $val;
    }

    /**
     * Marshalls PHP array into Firestore Typed Fields
     */
    public function encodeFields(array $data): array {
        $fields = [];
        foreach ($data as $key => $val) {
            $fields[$key] = $this->encodeValue($val);
        }
        return $fields;
    }

    private function encodeValue($val): array {
        if (is_null($val)) {
            return ['nullValue' => null];
        }
        if (is_bool($val)) {
            return ['booleanValue' => $val];
        }
        if (is_int($val)) {
            return ['integerValue' => (string)$val];
        }
        if (is_float($val)) {
            return ['doubleValue' => $val];
        }
        if (is_string($val)) {
            return ['stringValue' => $val];
        }
        if (is_array($val)) {
            // Check if associative array (map) or indexed array (list)
            if (empty($val)) {
                return ['arrayValue' => ['values' => []]];
            }
            $isAssoc = array_keys($val) !== range(0, count($val) - 1);
            if ($isAssoc) {
                return ['mapValue' => ['fields' => $this->encodeFields($val)]];
            } else {
                $values = [];
                foreach ($val as $item) {
                    $values[] = $this->encodeValue($item);
                }
                return ['arrayValue' => ['values' => $values]];
            }
        }
        return ['stringValue' => (string)$val];
    }

    /**
     * Unmarshalls Firestore Document response to associative PHP array
     */
    public function decodeDocument(array $raw): ?array {
        if (empty($raw) || !isset($raw['fields'])) {
            return null;
        }

        $data = $this->decodeFields($raw['fields']);
        
        // Extract Document ID from name (e.g. projects/.../documents/products/P123)
        if (!empty($raw['name'])) {
            $parts = explode('/', $raw['name']);
            $data['__id'] = end($parts);
            $data['__createTime'] = $raw['createTime'] ?? null;
            $data['__updateTime'] = $raw['updateTime'] ?? null;
        }

        return $data;
    }

    private function decodeFields(array $fields): array {
        $result = [];
        foreach ($fields as $key => $val) {
            $result[$key] = $this->decodeValue($val);
        }
        return $result;
    }

    private function decodeValue(array $val) {
        if (array_key_exists('stringValue', $val)) return $val['stringValue'];
        if (array_key_exists('integerValue', $val)) return (int)$val['integerValue'];
        if (array_key_exists('doubleValue', $val)) return (float)$val['doubleValue'];
        if (array_key_exists('booleanValue', $val)) return (bool)$val['booleanValue'];
        if (array_key_exists('timestampValue', $val)) return $val['timestampValue'];
        if (array_key_exists('nullValue', $val)) return null;
        if (array_key_exists('arrayValue', $val)) {
            $items = [];
            if (!empty($val['arrayValue']['values']) && is_array($val['arrayValue']['values'])) {
                foreach ($val['arrayValue']['values'] as $item) {
                    $items[] = $this->decodeValue($item);
                }
            }
            return $items;
        }
        if (array_key_exists('mapValue', $val)) {
            if (!empty($val['mapValue']['fields']) && is_array($val['mapValue']['fields'])) {
                return $this->decodeFields($val['mapValue']['fields']);
            }
            return [];
        }
        return null;
    }
}
