<?php
declare(strict_types=1);

namespace Mailings\Graph;

use Mailings\Config;

/** Minimal Microsoft Graph client using the app-only (client credentials) flow. The secret is read from server config only. */
final class GraphClient
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** @return array{tenant_id: string, client_id: string, secret_configured: bool, missing: list<string>, ready: bool} */
    public static function status(): array
    {
        $tenant = Config::get('GRAPH_TENANT_ID', '');
        $client = Config::get('GRAPH_CLIENT_ID', '');
        $secret = Config::get('GRAPH_CLIENT_SECRET', '');
        $missing = [];
        foreach (['GRAPH_TENANT_ID' => $tenant, 'GRAPH_CLIENT_ID' => $client, 'GRAPH_CLIENT_SECRET' => $secret] as $key => $value) {
            if ($value === '') {
                $missing[] = $key;
            }
        }
        return [
            'tenant_id' => $tenant,
            'client_id' => $client,
            'secret_configured' => $secret !== '',
            'missing' => $missing,
            'ready' => $missing === [],
        ];
    }

    /** @return array{token: string, roles: list<string>} */
    public function acquireToken(): array
    {
        $tenant = Config::required('GRAPH_TENANT_ID');
        if (!preg_match('/^[0-9a-fA-F-]{36}$|^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $tenant)) {
            throw new GraphException('GRAPH_TENANT_ID must be the tenant GUID or a domain name');
        }
        $response = $this->request(
            'POST',
            'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token',
            [],
            http_build_query([
                'client_id' => Config::required('GRAPH_CLIENT_ID'),
                'client_secret' => Config::required('GRAPH_CLIENT_SECRET'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ])
        );
        $token = $response['access_token'] ?? null;
        if (!is_string($token)) {
            throw new GraphException($this->describe($response, 'The token request failed'));
        }
        return ['token' => $token, 'roles' => $this->rolesFrom($token)];
    }

    /** @return array<string,mixed> */
    public function get(string $token, string $path): array
    {
        $response = $this->request('GET', self::GRAPH . $path, ['Authorization: Bearer ' . $token]);
        if (isset($response['error'])) {
            throw new GraphException($this->describe($response, 'The Graph request failed'));
        }
        return $response;
    }

    /** Application permissions granted to the app, read from the token's roles claim. @return list<string> */
    private function rolesFrom(string $token): array
    {
        $parts = explode('.', $token);
        $payload = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), false), true) : null;
        $roles = is_array($payload) && is_array($payload['roles'] ?? null) ? $payload['roles'] : [];
        return array_values(array_filter($roles, 'is_string'));
    }

    /** @param array<string,mixed> $response */
    public function describe(array $response, string $fallback): string
    {
        $error = $response['error'] ?? null;
        $text = is_array($error)
            ? trim(($error['code'] ?? '') . ': ' . ($error['message'] ?? ''), ': ')
            : trim((string) ($response['error_description'] ?? $error ?? ''));
        // Entra appends "Trace ID / Correlation ID / Timestamp" lines; keep only the first line.
        $text = trim(explode("\n", str_replace("\r", '', $text))[0]);
        $text = trim((string) preg_replace('/\s*Trace ID:.*$/s', '', $text));
        return $text === '' ? $fallback : mb_substr($text, 0, 300);
    }

    /**
     * Sends one message as the given mailbox (needs the Mail.Send application permission). Graph answers 202 with no body.
     *
     * @param array<string,mixed> $message Graph message resource
     */
    public function sendMail(string $token, string $mailbox, array $message): void
    {
        [$status, $response] = $this->requestRaw(
            'POST',
            self::GRAPH . '/users/' . rawurlencode($mailbox) . '/sendMail',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            json_encode(['message' => $message, 'saveToSentItems' => true], JSON_THROW_ON_ERROR)
        );
        if ($status !== 202) {
            $text = $this->describe($response, 'Microsoft refused the message (HTTP ' . $status . ')');
            throw new GraphException($status === 403 ? $text . ' (check Mail.Send permission and the Application Access Policy for this mailbox)' : $text);
        }
    }

    /**
     * @param list<string> $headers
     * @return array<string,mixed>
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        return $this->requestRaw($method, $url, $headers, $body)[1];
    }

    /**
     * @param list<string> $headers
     * @return array{int, array<string,mixed>}
     */
    private function requestRaw(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $curl = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new GraphException('Could not reach Microsoft: ' . $message);
        }
        curl_close($curl);
        $decoded = json_decode((string) $raw, true);
        return [$status, is_array($decoded) ? $decoded : []];
    }
}
