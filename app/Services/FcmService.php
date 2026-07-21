<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DeviceToken;

class FcmService
{
    private string $projectId;
    private string $serviceAccountPath;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', '');
        $this->serviceAccountPath = config('services.firebase.service_account_path', '');
    }

    public function getAccessToken(): ?string
    {
        if (empty($this->serviceAccountPath) || !file_exists($this->serviceAccountPath)) {
            return null;
        }

        $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $data = "{$header}.{$payload}";

        openssl_sign($data, $signature, $serviceAccount['private_key'], 'SHA256');
        $jwt = "{$data}." . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('FCM: Failed to get access token', ['response' => $response->body()]);
        return null;
    }

    public function sendToDevice(DeviceToken $deviceToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            Log::warning('FCM: No access token available');
            return false;
        }

        $message = [
            'message' => [
                'token' => $deviceToken->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'jani_notifications',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

            if ($response->successful()) {
                $deviceToken->update(['last_used_at' => now()]);
                return true;
            }

            $error = $response->json('error', []);
            $errorCode = $error['code'] ?? $response->status();

            if (in_array($errorCode, [404, 400])) {
                $deviceToken->update(['is_active' => false]);
            }

            Log::error('FCM: Send failed', ['token' => $deviceToken->id, 'error' => $error]);
            return false;
        } catch (\Exception $e) {
            Log::error('FCM: Exception', ['token' => $deviceToken->id, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function sendToMultipleTokens(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'failed_tokens' => []];

        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            $results['failed'] = count($deviceTokens);
            return $results;
        }

        foreach ($deviceTokens as $deviceToken) {
            $success = $this->sendToDevice($deviceToken, $title, $body, $data);

            if ($success) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['failed_tokens'][] = $deviceToken->id;
            }
        }

        return $results;
    }
}
