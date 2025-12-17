<?php

class PerplexityBridge extends BridgeAbstract
{
    const NAME = 'Perplexity Discover Feed';
    const URI = 'https://www.perplexity.ai';
    const DESCRIPTION = 'Returns news and articles from Perplexity.ai Discover feed using API key authentication';
    const MAINTAINER = 'RSS-Bridge Community';
    const CACHE_TIMEOUT = 1800; // 30 minutes

    const CONFIGURATION = [
        'api_key' => [
            'required' => false,
            'name' => 'API Key (or use PERPLEXITY_API_KEY env variable)',
            'defaultValue' => '',
        ],
        'session_token' => [
            'required' => false,
            'name' => 'Session Token (legacy)',
            'defaultValue' => '',
        ],
        'cf_clearance' => [
            'required' => false,
            'name' => 'Cloudflare Clearance Cookie (legacy)',
            'defaultValue' => '',
        ],
        'cf_bm' => [
            'required' => false,
            'name' => 'Cloudflare BM Cookie (legacy)',
            'defaultValue' => '',
        ],
    ];

    const PARAMETERS = [
        [
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'required' => false,
                'defaultValue' => 20,
                'title' => 'Number of items to return (1-100)',
            ],
            'source' => [
                'name' => 'Source',
                'type' => 'list',
                'required' => false,
                'values' => [
                    'Default' => 'default',
                    'News' => 'news',
                    'Technology' => 'technology',
                    'Science' => 'science',
                ],
                'defaultValue' => 'default',
            ],
            'language' => [
                'name' => 'Language',
                'type' => 'list',
                'required' => false,
                'values' => [
                    'English' => 'en',
                    'German / Deutsch' => 'de',
                    'French / Français' => 'fr',
                    'Spanish / Español' => 'es',
                    'Italian / Italiano' => 'it',
                    'Portuguese / Português' => 'pt',
                    'Dutch / Nederlands' => 'nl',
                    'Japanese / 日本語' => 'ja',
                    'Chinese / 中文' => 'zh',
                ],
                'defaultValue' => 'en',
                'title' => 'Preferred language for content (may not affect all results)',
            ],
        ]
    ];

    private $apiVersion = '2.18';

    public function collectData()
    {
        $limit = $this->getInput('limit') ?? 20;
        $source = $this->getInput('source') ?? 'default';

        // Validate limit
        if ($limit < 1 || $limit > 100) {
            throw new \Exception('Limit must be between 1 and 100');
        }

        $url = sprintf(
            'https://www.perplexity.ai/rest/discover/feed?limit=%d&offset=0&version=%s&source=%s',
            $limit,
            $this->apiVersion,
            $source
        );

        $json = $this->getApiContents($url);
        
        if (empty($json)) {
            throw new \Exception('Empty API response received');
        }
        
        $data = Json::decode($json, false);

        // Check for both possible response formats
        $items = null;
        if (isset($data->items)) {
            $items = $data->items;
        } elseif (isset($data->data)) {
            $items = $data->data;
        } else {
            // Provide detailed error for debugging
            $availableFields = implode(', ', array_keys((array)$data));
            throw new \Exception('Invalid API response: missing items/data field. Available fields: ' . $availableFields);
        }

        foreach ($items as $entry) {
            $item = $this->parseEntry($entry);
            if ($item) {
                $this->items[] = $item;
            }
        }
    }

    private function parseEntry($entry)
    {
        // Skip entries without required fields
        if (!isset($entry->uuid) || !isset($entry->title)) {
            return null;
        }

        $item = [];
        $item['uid'] = $entry->uuid;
        $item['title'] = $entry->title;

        // Build the article URL using slug or uuid
        if (isset($entry->slug) && !empty($entry->slug)) {
            $item['uri'] = self::URI . '/page/' . $entry->slug;
        } else {
            $item['uri'] = self::URI . '/page/' . $entry->uuid;
        }

        // Parse timestamp - API uses updated_datetime
        if (isset($entry->updated_datetime)) {
            $item['timestamp'] = strtotime($entry->updated_datetime);
        } elseif (isset($entry->created_at)) {
            $item['timestamp'] = strtotime($entry->created_at);
        }

        // Build content
        $content = '';

        // Add description/summary if available
        if (isset($entry->description) && !empty($entry->description)) {
            $content .= '<p>' . htmlspecialchars($entry->description) . '</p>';
        }

        if (isset($entry->summary) && !empty($entry->summary)) {
            $content .= '<p>' . htmlspecialchars($entry->summary) . '</p>';
        }

        // Add featured images if available
        if (isset($entry->featured_images) && is_array($entry->featured_images) && count($entry->featured_images) > 0) {
            $image = $entry->featured_images[0];
            if (isset($image->image)) {
                $content .= '<img src="' . htmlspecialchars($image->image) . '" />';
                $item['enclosures'] = [$image->image];
            }
        }

        // Add author information
        if (isset($entry->author_username)) {
            $item['author'] = $entry->author_username;
            if (isset($entry->author_image)) {
                $content .= '<p><em>By: ' . htmlspecialchars($entry->author_username) . '</em></p>';
            }
        }

        $item['content'] = $content;

        return $item;
    }

    private function getApiContents($url)
    {
        // Try environment variable first, then config
        $apiKey = getenv('PERPLEXITY_API_KEY') ?: $this->getOption('api_key');
        $sessionToken = $this->getOption('session_token');

        if (!$apiKey && !$sessionToken) {
            throw new \Exception('API key is required. Please configure it in config.ini.php or set PERPLEXITY_API_KEY environment variable');
        }

        $headers = $this->buildHeaders($apiKey);
        $options = $this->buildCurlOptions();

        return getContents($url, $headers, $options);
    }

    private function buildHeaders($apiKey = null)
    {
        // Use passed API key or try environment variable, then config
        if (!$apiKey) {
            $apiKey = getenv('PERPLEXITY_API_KEY') ?: $this->getOption('api_key');
        }
        $sessionToken = $this->getOption('session_token');

        // Get language preference
        $language = $this->getInput('language') ?? 'en';
        $acceptLanguage = $this->buildAcceptLanguageHeader($language);

        $headers = [
            'Accept: */*',
            'Accept-Language: ' . $acceptLanguage,
            'Accept-Encoding: gzip, deflate, br',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        ];

        // Use API key authentication if available (preferred method)
        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'Accept: application/json';
            $headers[] = 'Content-Type: application/json';
        } else {
            // Fall back to session token method (legacy)
            $cookies = [
                '__Secure-next-auth.session-token=' . $sessionToken,
            ];

            // Add optional Cloudflare cookies if configured
            $cfClearance = $this->getOption('cf_clearance');
            if ($cfClearance) {
                $cookies[] = 'cf_clearance=' . $cfClearance;
            }

            $cfBm = $this->getOption('cf_bm');
            if ($cfBm) {
                $cookies[] = '__cf_bm=' . $cfBm;
            }

            $headers[] = 'Referer: https://www.perplexity.ai/discover';
            $headers[] = 'sec-fetch-dest: empty';
            $headers[] = 'sec-fetch-mode: cors';
            $headers[] = 'sec-fetch-site: same-origin';
            $headers[] = 'x-app-apiclient: default';
            $headers[] = 'x-app-apiversion: ' . $this->apiVersion;
            $headers[] = 'Cookie: ' . implode('; ', $cookies);
        }

        return $headers;
    }

    private function buildCurlOptions()
    {
        $options = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => '', // Enable automatic decompression of gzip, deflate, br
        ];

        return $options;
    }

    private function buildAcceptLanguageHeader($language)
    {
        // Map language codes to proper Accept-Language headers
        $languageHeaders = [
            'en' => 'en-US,en;q=0.9',
            'de' => 'de-DE,de;q=0.9,en;q=0.8',
            'fr' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'es' => 'es-ES,es;q=0.9,en;q=0.8',
            'it' => 'it-IT,it;q=0.9,en;q=0.8',
            'pt' => 'pt-PT,pt;q=0.9,en;q=0.8',
            'nl' => 'nl-NL,nl;q=0.9,en;q=0.8',
            'ja' => 'ja-JP,ja;q=0.9,en;q=0.8',
            'zh' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ];

        return $languageHeaders[$language] ?? 'en-US,en;q=0.9';
    }

    public function getName()
    {
        $source = $this->getInput('source');
        if ($source && $source !== 'default') {
            return 'Perplexity Discover - ' . ucfirst($source);
        }
        return parent::getName();
    }
}
