<?php

class StarToZoteroExtension extends Minz_Extension {

    public function init() {
        $this->registerTranslates();

        // Watch for star (favorite) activity
        $this->registerHook('entries_favorite', [$this, 'handleStar']);

        // Controller + views (for JS vars etc.)
        $this->registerController('starToZotero');
        $this->registerViews();
    }

    public function handleConfigureAction() {
        $this->registerTranslates();

        if (Minz_Request::isPost()) {
            FreshRSS_Context::$user_conf->zotero_api_key = Minz_Request::param('zotero_api_key', '');
            FreshRSS_Context::$user_conf->zotero_user_id = Minz_Request::param('zotero_user_id', '');
            FreshRSS_Context::$user_conf->zotero_collection_key = Minz_Request::param('zotero_collection_key', '');
            FreshRSS_Context::$user_conf->zotero_translation_server_url = Minz_Request::param('zotero_translation_server_url', '');
            FreshRSS_Context::$user_conf->zotero_extra_tags = Minz_Request::param('zotero_extra_tags', 'source:rss,inbox');
            FreshRSS_Context::$user_conf->zotero_keyboard_shortcut = Minz_Request::param('keyboard_shortcut', '');
            FreshRSS_Context::$user_conf->save();
        }
    }

    /**
     * If $isStarred == true, send each entry to Zotero.
     */
    public function handleStar(array $starredEntries, bool $isStarred): void {
        $this->registerTranslates();

        if (!$isStarred) {
            return;
        }

        $apiKey = trim(FreshRSS_Context::$user_conf->zotero_api_key ?? '');
        $userId = trim(FreshRSS_Context::$user_conf->zotero_user_id ?? '');
        $collectionKey = trim(FreshRSS_Context::$user_conf->zotero_collection_key ?? '');

        if ($apiKey === '' || $userId === '' || $collectionKey === '') {
            Minz_Log::warning('StarToZotero: missing configuration (API key / user ID / collection key).');
            return;
        }

        $entry_dao = FreshRSS_Factory::createEntryDao();

        foreach ($starredEntries as $id) {
            $entry = $entry_dao->searchById($id);
            if ($entry === null) {
                continue;
            }
            $this->addToZotero($entry, $apiKey, $userId, $collectionKey);
        }
    }

    private function addToZotero($entry, string $apiKey, string $userId, string $collectionKey): void {
        $link = trim($entry->link());
        if ($link === '') {
            return;
        }
        $title = trim($entry->title() ?? '');

        $translationServer = trim(FreshRSS_Context::$user_conf->zotero_translation_server_url ?? '');
        $extraTagsCsv = FreshRSS_Context::$user_conf->zotero_extra_tags ?? 'source:rss,inbox';
        $extraTags = array_values(array_filter(array_map('trim', explode(',', $extraTagsCsv))));

        // Prefer Translation Server for rich metadata
        $items = null;
        if ($translationServer !== '') {
            $items = $this->translateUrl($translationServer, $link);
        }
        $usedTranslation = ($items !== null);
        Minz_Log::notice('StarToZotero: '.($usedTranslation ? 'translation server' : 'minimal fallback').' for "'.$title.'"');

        if ($items === null) {
            // Minimal fallback item
            $items = array(array(
                'itemType'    => 'webpage',
                'title'       => $title !== '' ? $title : $link,
                'url'         => $link,
                'tags'        => array_map(fn($t) => array('tag' => $t), $extraTags),
                'collections' => array($collectionKey),
            ));
        } else {
            $items = $this->augmentItems($items, $collectionKey, $extraTags);
        }

        try {
            $this->postToZotero($userId, $apiKey, $items);
            // Optionally: emit a notification via logs
            Minz_Log::notice('StarToZotero: added "' . $title . '"');
        } catch (\Throwable $e) {
            Minz_Log::warning('StarToZotero write failed: ' . $e->getMessage());
        }
    }

    private function translateUrl(string $base, string $url): ?array {
        $endpoint = rtrim($base, '/') . '/web';

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
        );

        $res = $this->curlRequest($endpoint, 'POST', $headers, $url);
        if ($res['status'] >= 200 && $res['status'] < 300 && !empty($res['response'])) {
            $data = json_decode($res['response'], true);
            if (is_array($data) && !isset($data['items'])) {
                // Expected array of Zotero items
                return $data;
            }
        }
        return null; // fall back to minimal
    }

    private function augmentItems(array $items, string $collectionKey, array $extraTags): array {
        $tagObjs = array_map(fn($t) => array('tag' => $t), $extraTags);
        $out = array();

        foreach ($items as $it) {
            if (!is_array($it)) {
                $out[] = $it; continue;
            }
            if (($it['itemType'] ?? '') !== 'attachment' && !isset($it['note'])) {
                // top-level bibliographic item
                $existingTags = array_map(fn($t) => $t['tag'] ?? '', $it['tags'] ?? array());
                foreach ($tagObjs as $to) {
                    if (!in_array($to['tag'], $existingTags, true)) {
                        $it['tags'][] = $to;
                    }
                }
                $cols = $it['collections'] ?? array();
                if (!in_array($collectionKey, $cols, true)) {
                    $cols[] = $collectionKey;
                }
                $it['collections'] = $cols;
            }
            $out[] = $it;
        }

        return $out;
    }

    private function postToZotero(string $userId, string $apiKey, array $items): void {
        $url = 'https://api.zotero.org/users/' . rawurlencode($userId) . '/items';

        // Per Zotero Web API v3, POST a JSON ARRAY of items
        $headers = array(
            'Content-Type: application/json; charset=UTF-8',
            'Zotero-API-Key: ' . $apiKey,
            'Zotero-Write-Token: ' . bin2hex(random_bytes(8)),
        );

        $payload = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $res = $this->curlRequest($url, 'POST', $headers, $payload);
        if ($res['status'] !== 200 && $res['status'] !== 201) {
            $msg = $res['response'] ?: 'HTTP ' . $res['status'];
            throw new \RuntimeException($msg);
        }
    }

    private function curlRequest(string $url, string $method, array $headers, string $body = ''): array {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        $hdr = array();
        foreach ($headers as $h) { $hdr[] = $h; }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $hdr);

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $response_header = substr($response, 0, $header_size);
        $response_body = substr($response, $header_size);

        return array(
            'response' => $response_body,
            'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'headers' => $this->httpHeaderToArray($response_header),
        );
    }

    private function httpHeaderToArray($header) {
        $headers = array();
        $headers_parts = explode("\r\n", $header);

        foreach ($headers_parts as $header_part) {
            if (strlen($header_part) <= 0) {
                continue;
            }
            if (strpos($header_part, ':')) {
                $header_name = substr($header_part, 0, strpos($header_part, ':'));
                $header_value = substr($header_part, strpos($header_part, ':') + 1);
                $headers[$header_name] = trim($header_value);
            }
        }
        return $headers;
    }
}
