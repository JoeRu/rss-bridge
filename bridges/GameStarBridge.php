<?php

class GameStarBridge extends BridgeAbstract
{
    const NAME = 'GameStar Bridge';
    const URI = 'https://www.gamestar.de';
    const DESCRIPTION = 'RSS feed for GameStar gaming news';
    const MAINTAINER = 'JoehannesRumpf';
    const CACHE_TIMEOUT = 1800; // 30 minutes

    const PARAMETERS = [
        [
            'category' => [
                'name' => 'Category',
                'type' => 'list',
                'title' => 'Select news category',
                'values' => [
                    'Top News' => 'topnews',
                    'Spiele News' => 'spiele',
                    'Video News' => 'video',
                    'Tech News' => 'tech',
                    'Alle News' => '',
                ],
                'defaultValue' => 'topnews'
            ],
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'title' => 'Maximum number of items to return',
                'defaultValue' => 20,
                'required' => false
            ]
        ]
    ];

    public function collectData()
    {
        $category = $this->getInput('category');
        $limit = $this->getInput('limit') ?? 20;
        
        if ($category === '') {
            $url = self::URI . '/news/';
        } else {
            $url = self::URI . '/news/' . $category . '/';
        }
        
        $html = getSimpleHTMLDOM($url);
        
        if (!$html) {
            throw new Exception('Unable to load page: ' . $url);
        }
        
        $html = defaultLinkTo($html, self::URI);
        
        // Find all article links
        foreach ($html->find('a[href*="/artikel/"]') as $link) {
            if (count($this->items) >= $limit) {
                break;
            }
            
            $articleUrl = $link->href;
            
            // Skip if not a valid article URL or already processed
            if (
                strpos($articleUrl, ',') === false || // GameStar URLs have article ID with comma
                strpos($articleUrl, 'javascript:') !== false ||
                $this->isAlreadyAdded($articleUrl)
            ) {
                continue;
            }
            
            // Get article title from link
            $title = trim($link->plaintext);
            if (empty($title) || strlen($title) < 10) {
                continue;
            }
            
            try {
                $articleHtml = getSimpleHTMLDOMCached($articleUrl, self::CACHE_TIMEOUT);
                
                if (!$articleHtml) {
                    continue;
                }
                
                $articleHtml = defaultLinkTo($articleHtml, self::URI);
                
                // Extract article content
                $content = '';
                $timestamp = null;
                $author = '';
                
                // Find article headline for better title
                $headline = $articleHtml->find('h1', 0);
                if ($headline) {
                    $title = trim($headline->plaintext);
                }
                
                // Strategy 1: Try to find the main article container
                $mainContent = null;
                
                // Look for common article containers
                $articleContainers = [
                    'article',
                    'div[class*="Article"]',
                    'div[class*="article"]',
                    'div[id*="article"]',
                    'main',
                ];
                
                foreach ($articleContainers as $selector) {
                    $mainContent = $articleHtml->find($selector, 0);
                    if ($mainContent) {
                        break;
                    }
                }
                
                // Strategy 2: If we have a main container, extract teaser/intro
                if ($mainContent) {
                    // Try to find intro/teaser paragraph
                    $teaser = $mainContent->find('p[class*="intro"]', 0);
                    if (!$teaser) {
                        $teaser = $mainContent->find('p[class*="teaser"]', 0);
                    }
                    if (!$teaser) {
                        $teaser = $mainContent->find('p[class*="lead"]', 0);
                    }
                    if (!$teaser) {
                        $teaser = $mainContent->find('div[class*="intro"]', 0);
                    }
                    
                    // If no teaser found, take first substantial paragraph
                    if (!$teaser) {
                        $paragraphs = $mainContent->find('p');
                        foreach ($paragraphs as $p) {
                            $text = trim($p->plaintext);
                            if (!empty($text) && strlen($text) > 50) {
                                $teaser = $p;
                                break;
                            }
                        }
                    }
                    
                    if ($teaser) {
                        $content = '<p>' . $teaser->innertext . '</p>';
                    }
                }
                
                // Strategy 3: Fallback - get first substantial paragraph from the whole page
                if (empty($content)) {
                    $allParagraphs = $articleHtml->find('p');
                    foreach ($allParagraphs as $p) {
                        $text = trim($p->plaintext);
                        if (!empty($text) && strlen($text) > 50) {
                            $content = '<p>' . $p->innertext . '</p>';
                            break;
                        }
                    }
                }
                
                // Add main image if available
                $images = $articleHtml->find('img');
                foreach ($images as $img) {
                    $src = $img->src;
                    // Skip small images, icons, and tracking pixels
                    if ($src && 
                        !strpos($src, 'icon') && 
                        !strpos($src, 'logo') && 
                        !strpos($src, '1x1') &&
                        (strpos($src, '/images/') !== false || 
                         strpos($src, '/bilder/') !== false ||
                         strpos($src, 'gamestar.de') !== false)) {
                        $alt = $img->alt ?? '';
                        
                        // Use direct image URL - some feed readers can handle these despite CloudFlare
                        $imageHtml = '<p><img src="' . $src . '" alt="' . htmlspecialchars($alt) . '" style="max-width:100%;height:auto;"></p>';
                        $content = $imageHtml . "\n" . $content;
                        break;
                    }
                }
                
                // Try to find timestamp
                $timeElement = $articleHtml->find('time', 0);
                if ($timeElement) {
                    if ($timeElement->datetime) {
                        $timestamp = strtotime($timeElement->datetime);
                    } elseif ($timeElement->plaintext) {
                        $timestamp = strtotime($timeElement->plaintext);
                    }
                }
                
                // Try to find date in meta tags
                if (!$timestamp) {
                    $dateMeta = $articleHtml->find('meta[property="article:published_time"]', 0);
                    if ($dateMeta && $dateMeta->content) {
                        $timestamp = strtotime($dateMeta->content);
                    }
                }
                
                // Try to find author
                $authorElement = $articleHtml->find('meta[name="author"]', 0);
                if ($authorElement && $authorElement->content) {
                    $author = $authorElement->content;
                }
                
                // If still no content, add a fallback message
                if (empty($content)) {
                    $content = '<p>Artikel-Inhalt konnte nicht extrahiert werden. Bitte besuchen Sie die <a href="' . $articleUrl . '">Original-Seite</a>.</p>';
                }
                
                // Create feed item
                $item = [
                    'uri' => $articleUrl,
                    'title' => $title,
                    'content' => $content,
                ];
                
                if ($timestamp) {
                    $item['timestamp'] = $timestamp;
                }
                
                if (!empty($author)) {
                    $item['author'] = $author;
                }
                
                $this->items[] = $item;
                
            } catch (Exception $e) {
                // Skip articles that fail to load
                continue;
            }
        }
    }
    
    private function isAlreadyAdded($url)
    {
        foreach ($this->items as $item) {
            if ($item['uri'] === $url) {
                return true;
            }
        }
        return false;
    }
    
    public function getName()
    {
        if ($this->getInput('category')) {
            $categoryName = array_search($this->getInput('category'), self::PARAMETERS[0]['category']['values']);
            return self::NAME . ' - ' . $categoryName;
        }
        return parent::getName();
    }
}
