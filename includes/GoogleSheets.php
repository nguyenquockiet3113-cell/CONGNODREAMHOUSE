<?php
/**
 * Client toi gian cho Google Sheets API, dung Service Account (JWT Bearer),
 * khong can thu vien Composer/Google API Client - chi dung openssl + curl
 * co san trong PHP.
 *
 * Quyen han service account can co: da duoc chia se (Share) Google Sheet
 * dich voi email cua service account (dang "...@...iam.gserviceaccount.com")
 * voi quyen Editor.
 */
class GoogleSheets
{
    private string $clientEmail;
    private string $privateKey;
    private string $spreadsheetId;
    private ?string $accessToken = null;

    public function __construct(array $serviceAccount, string $spreadsheetId)
    {
        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            throw new RuntimeException('File Service Account JSON không hợp lệ (thiếu client_email/private_key).');
        }
        $this->clientEmail = $serviceAccount['client_email'];
        $this->privateKey = $serviceAccount['private_key'];
        $this->spreadsheetId = $spreadsheetId;
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $now = time();
        $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = self::base64url(json_encode([
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signInput = $header . '.' . $claim;
        $signature = '';
        $ok = @openssl_sign($signInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Không thể ký JWT bằng private key trong Service Account JSON.');
        }
        $jwt = $signInput . '.' . self::base64url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('Lấy access token Google thất bại: ' . ($data['error_description'] ?? $response));
        }

        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $token = $this->getAccessToken();
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Lỗi kết nối Google Sheets API: ' . $err);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true) ?? [];
        if ($httpCode >= 400) {
            throw new RuntimeException('Google Sheets API lỗi (' . $httpCode . '): ' . ($data['error']['message'] ?? $response));
        }
        return $data;
    }

    private function baseUrl(): string
    {
        return 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId;
    }

    /** Danh sach ten cac sheet (tab) hien co trong file */
    public function listSheetTitles(): array
    {
        $data = $this->request('GET', $this->baseUrl() . '?fields=sheets.properties.title');
        return array_map(fn($s) => $s['properties']['title'], $data['sheets'] ?? []);
    }

    /** Tao sheet (tab) moi neu chua ton tai */
    public function ensureSheetExists(string $title): void
    {
        if (in_array($title, $this->listSheetTitles(), true)) {
            return;
        }
        $this->request('POST', $this->baseUrl() . ':batchUpdate', [
            'requests' => [['addSheet' => ['properties' => ['title' => $title]]]],
        ]);
    }

    /** Ghi de toan bo noi dung 1 sheet: dong 1 la header, cac dong sau la du lieu */
    public function writeSheet(string $title, array $headers, array $rows): void
    {
        $this->ensureSheetExists($title);
        $range = "'" . str_replace("'", "", $title) . "'";

        // Xoa noi dung cu truoc khi ghi de
        $this->request('POST', $this->baseUrl() . '/values/' . rawurlencode($range) . ':clear', []);

        $values = array_merge([$headers], $rows);
        $this->request(
            'PUT',
            $this->baseUrl() . '/values/' . rawurlencode($range) . '?valueInputOption=RAW',
            ['values' => $values]
        );
    }

    /** Doc toan bo 1 sheet, tra ve mang 2 chieu (dong 1 la header) */
    public function readSheet(string $title): array
    {
        if (!in_array($title, $this->listSheetTitles(), true)) {
            return [];
        }
        $range = "'" . str_replace("'", "", $title) . "'";
        $data = $this->request('GET', $this->baseUrl() . '/values/' . rawurlencode($range));
        return $data['values'] ?? [];
    }
}
