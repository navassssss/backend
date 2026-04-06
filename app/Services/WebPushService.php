<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * WebPushService — sends Web Push notifications using VAPID auth.
 *
 * VAPID spec: RFC 8292
 * Encryption:  RFC 8291 (aesgcm)
 *
 * No external packages required — uses PHP OpenSSL + curl.
 */
class WebPushService
{
    private string $publicKey;
    private string $privateKey;
    private string $subject;

    public function __construct()
    {
        $this->publicKey  = config('webpush.vapid_public_key', '');
        $this->privateKey = config('webpush.vapid_private_key', '');
        $this->subject    = config('webpush.vapid_subject', 'mailto:admin@dhic.edu');
    }

    // ─── Public API ───────────────────────────────────────────────

    /**
     * Send a push notification to a single subscription.
     */
    public function sendToSubscription(PushSubscription $sub, array $payload): bool
    {
        Log::info("[WebPush] Attempting send to User ID: {$sub->user_id}, Sub ID: {$sub->id}");
        try {
            $result = $this->dispatch($sub->endpoint, $sub->p256dh_key, $sub->auth_token, $payload);
            Log::info("[WebPush] Result for Sub {$sub->id}: " . ($result ? 'Success' : 'Failure (Sub likely expired)'));
            return $result;
        } catch (\Throwable $e) {
            Log::warning("[WebPush] Failed for subscription {$sub->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send to all subscriptions of a given user.
     */
    public function sendToUser(int $userId, array $payload): void
    {
        $subs = PushSubscription::where('user_id', $userId)->get();
        foreach ($subs as $sub) {
            /** @var PushSubscription $sub */
            $ok = $this->sendToSubscription($sub, $payload);
            if (!$ok) {
                $sub->delete();
            }
        }
    }

    /**
     * Send to all subscriptions of multiple users.
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        $subs = PushSubscription::whereIn('user_id', $userIds)->get();
        foreach ($subs as $sub) {
            /** @var PushSubscription $sub */
            $ok = $this->sendToSubscription($sub, $payload);
            if (!$ok) {
                $sub->delete();
            }
        }
    }

    /**
     * Broadcast to ALL subscriptions in the DB.
     */
    public function broadcast(array $payload): void
    {
        PushSubscription::chunk(100, function ($subs) use ($payload) {
            foreach ($subs as $sub) {
                /** @var PushSubscription $sub */
                $ok = $this->sendToSubscription($sub, $payload);
                if (!$ok) $sub->delete();
            }
        });
    }

    // ─── Core dispatch ────────────────────────────────────────────

    private function dispatch(
        string $endpoint,
        ?string $p256dh,
        ?string $auth,
        array $payload
    ): bool {
        $body    = json_encode($payload);
        $headers = $this->buildHeaders($endpoint, $body, $p256dh, $auth);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $headers['encrypted_body'] ?? $body,
            CURLOPT_HTTPHEADER     => $this->headersArray($headers['headers']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error("[WebPush] Curl Error for {$endpoint}: {$err}");
            return true; // Don't delete on network error
        }

        Log::info("[WebPush] Push server response: HTTP {$httpCode} - " . substr($response, 0, 500));


        // 201 = success, 410 = subscription expired
        if ($httpCode === 410 || $httpCode === 404) {
            return false; // Signal caller to delete this sub
        }

        if ($httpCode >= 400) {
            Log::warning("[WebPush] HTTP {$httpCode} for {$endpoint}: " . substr($response, 0, 200));
            return true; // Don't delete, might be transient
        }

        return true;
    }

    private function buildHeaders(
        string $endpoint,
        string $body,
        ?string $p256dh,
        ?string $auth
    ): array {
        $origin    = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $expiry    = time() + 43200; // 12h

        $vapidToken = $this->buildVapidJWT($origin, $expiry);

        $headers = [
            'Authorization' => "vapid t={$vapidToken}, k={$this->publicKey}",
            'TTL'           => '86400',
        ];

        // If we have subscription encryption keys, encrypt the payload
        if ($p256dh && $auth) {
            [$encrypted, $saltB64, $serverPublicB64] = $this->encryptPayload($body, $p256dh, $auth);
            $headers['Content-Type']     = 'application/octet-stream';
            $headers['Content-Encoding'] = 'aesgcm';
            $headers['Encryption']       = "salt={$saltB64}";
            $headers['Crypto-Key']       = "dh={$serverPublicB64};p256ecdsa={$this->publicKey}";

            return ['headers' => $headers, 'encrypted_body' => $encrypted];
        }

        // Plain text (no encryption keys given)
        $headers['Content-Type']   = 'application/json';
        $headers['Content-Length'] = strlen($body);

        return ['headers' => $headers, 'encrypted_body' => $body];
    }

    // ─── VAPID JWT ────────────────────────────────────────────────

    private function buildVapidJWT(string $audience, int $expiry): string
    {
        $header  = $this->base64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = $this->base64url(json_encode([
            'aud' => $audience,
            'exp' => $expiry,
            'sub' => $this->subject,
        ]));

        $signingInput = "{$header}.{$claims}";

        // Import private key
        $privDer = $this->base64urlDecode($this->privateKey);
        $privPem = $this->ecPrivateKeyToPem($privDer);
        $privKey = openssl_pkey_get_private($privPem);

        openssl_sign($signingInput, $signature, $privKey, OPENSSL_ALGO_SHA256);

        // DER -> raw r||s (64 bytes)
        $rawSig = $this->derToRaw($signature);

        return "{$signingInput}." . $this->base64url($rawSig);
    }

    /**
     * Wrap a raw 32-byte EC private scalar into a PEM-encoded key.
     */
    private function ecPrivateKeyToPem(string $d): string
    {
        // ASN.1 DER for EC private key (prime256v1 / P-256)
        // ECPrivateKey ::= SEQUENCE {
        //   version INTEGER { ecPrivkeyVer1(1) }
        //   privateKey OCTET STRING,
        //   parameters [0] OID namedCurve prime256v1 (optional)
        // }
        $oid       = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // OID prime256v1
        $params    = "\xa0\x0a" . $oid;                             // [0] EXPLICIT
        $privBytes = "\x04\x20" . $d;                               // OCTET STRING, 32B
        $version   = "\x02\x01\x01";                                // INTEGER 1

        $inner = $version . $privBytes . $params;
        $seq   = "\x30" . $this->derLen(strlen($inner)) . $inner;

        return "-----BEGIN EC PRIVATE KEY-----\n" .
               chunk_split(base64_encode($seq), 64, "\n") .
               "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Convert DER-encoded ECDSA signature to raw r||s (64 bytes).
     */
    private function derToRaw(string $der): string
    {
        // Parse SEQUENCE { INTEGER r, INTEGER s }
        $offset = 2; // skip 0x30 + length
        $r      = $this->parseDerInt($der, $offset);
        $offset += 2 + strlen($r);
        $s      = $this->parseDerInt($der, $offset);

        // Pad/trim to 32 bytes each
        $r = substr(str_pad($r, 32, "\x00", STR_PAD_LEFT), -32);
        $s = substr(str_pad($s, 32, "\x00", STR_PAD_LEFT), -32);

        return $r . $s;
    }

    private function parseDerInt(string $der, int $offset): string
    {
        // 0x02 = INTEGER tag
        $len   = ord($der[$offset + 1]);
        $value = substr($der, $offset + 2, $len);
        // Remove leading 0x00 padding bytes
        return ltrim($value, "\x00");
    }

    // ─── Payload Encryption (RFC 8291 / aesgcm) ──────────────────

    private function encryptPayload(string $plaintext, string $p256dh, string $auth): array
    {
        // Generate server EC key pair
        $serverKey     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $serverDetails = openssl_pkey_get_details($serverKey);

        $sx = str_pad($serverDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $sy = str_pad($serverDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $serverPublicRaw = "\x04" . $sx . $sy;

        // Decode client public key
        $clientPublicRaw = $this->base64urlDecode($p256dh);
        $authSecret      = $this->base64urlDecode($auth);

        // ECDH shared secret using openssl_pkey_derive (PHP 8.1+)
        $clientPubPem  = $this->ecPublicKeyToPem($clientPublicRaw);
        $clientPubKey  = openssl_pkey_get_public($clientPubPem);
        openssl_pkey_export($serverKey, $serverPrivPem);

        $sharedSecret = openssl_pkey_derive($clientPubKey, $serverKey);
        if ($sharedSecret === false) {
            throw new \RuntimeException('ECDH key derivation failed: ' . openssl_error_string());
        }

        // Derive encryption key + nonce using HKDF
        $salt = random_bytes(16);

        $prk    = $this->hkdf($authSecret, $sharedSecret, "Content-Encoding: auth\x00", 32);
        $cek    = $this->hkdf($salt, $prk, $this->buildInfo('aesgcm', $clientPublicRaw, $serverPublicRaw), 16);
        $nonce  = $this->hkdf($salt, $prk, $this->buildInfo('nonce', $clientPublicRaw, $serverPublicRaw), 12);

        // Pad plaintext
        $padded    = "\x00\x00" . $plaintext; // 2-byte pad length prefix

        // AES-128-GCM encrypt
        $encrypted = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $ciphertext = $encrypted . $tag;

        return [
            $ciphertext,
            $this->base64url($salt),
            $this->base64url($serverPublicRaw),
        ];
    }

    private function buildInfo(string $type, string $clientKey, string $serverKey): string
    {
        return "Content-Encoding: {$type}\x00P-256\x00"
            . pack('n', strlen($clientKey)) . $clientKey
            . pack('n', strlen($serverKey)) . $serverKey;
    }

    private function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk    = hash_hmac('sha256', $ikm, $salt, true);
        $output = '';
        $prev   = '';
        $i      = 0;
        while (strlen($output) < $length) {
            $prev   = hash_hmac('sha256', $prev . $info . chr(++$i), $prk, true);
            $output .= $prev;
        }
        return substr($output, 0, $length);
    }

    private function ecPublicKeyToPem(string $rawKey): string
    {
        // SubjectPublicKeyInfo wrapper for P-256 uncompressed public key
        $prefix = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
        $der    = $prefix . $rawKey;
        return "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function derLen(int $len): string
    {
        if ($len < 0x80) return chr($len);
        $bytes = '';
        while ($len > 0) { $bytes = chr($len & 0xFF) . $bytes; $len >>= 8; }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function headersArray(array $headers): array
    {
        return array_map(fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers);
    }
}
