<?php

class FNPHochtaunusBridge extends BridgeAbstract
{
    const NAME = 'FNP Hochtaunus Bridge';
    const URI = 'https://www.fnp.de';
    const DESCRIPTION = 'RSS feed for FNP (Frankfurter Neue Presse) Hochtaunus local news';
    const MAINTAINER = 'JoeRu';
    const CACHE_TIMEOUT = 1800; // 30 minutes

    const PARAMETERS = [
        [
            'section' => [
                'name' => 'Section',
                'type' => 'list',
                'title' => 'Select news section',
                'values' => [
                    'Hochtaunus (alle)' => 'hochtaunus',
                    'Bad Homburg' => 'bad-homburg-ort47554',
                    'Friedrichsdorf' => 'friedrichsdorf-ort89302',
                    'Glashütten' => 'glashuetten-ort893417',
                    'Grävenwiesbach' => 'graevenwiesbach-ort893418',
                    'Königstein' => 'koenigstein-ort53593',
                    'Kronberg' => 'kronberg-ort79545',
                    'Neu-Anspach' => 'neu-anspach-ort893428',
                    'Oberursel' => 'oberursel-ort69327',
                    'Schmitten' => 'schmitten-ort893434',
                    'Steinbach' => 'steinbach-ort893435',
                    'Usingen' => 'usingen-ort893437',
                    'Wehrheim' => 'wehrheim-ort893438',
                    'Weilrod' => 'weilrod-ort893439',
                ],
                'defaultValue' => 'hochtaunus'
            ],
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'title' => 'Maximum number of items to return',
                'defaultValue' => 15,
                'required' => false
            ]
        ]
    ];

    public function collectData()
    {
        $section = $this->getInput('section');
        $limit = $this->getInput('limit') ?? 15;
        
        $url = self::URI . '/lokales/' . $section . '/';
        $html = getSimpleHTMLDOM($url);
        
        if (!$html) {
            throw new Exception('Unable to load page: ' . $url);
        }
        
        $html = defaultLinkTo($html, self::URI);
        
        // Find all article links
        foreach ($html->find('a[href*="/lokales/"]') as $link) {
            if (count($this->items) >= $limit) {
                break;
            }
            
            $articleUrl = $link->href;
            
            // Skip if not a valid article URL or already processed
            if (
                strpos($articleUrl, '.html') === false ||
                strpos($articleUrl, 'javascript:') !== false ||
                $this->isAlreadyAdded($articleUrl)
            ) {
                continue;
            }
            
            // Get article title from link
            $title = trim($link->plaintext);
            if (empty($title)) {
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
                
                // Strategy 2: If we have a main container, extract teaser/first paragraph
                if ($mainContent) {
                    // First try to find a teaser/intro element
                    $teaser = $mainContent->find('p[class*="intro"]', 0);
                    if (!$teaser) {
                        $teaser = $mainContent->find('p[class*="teaser"]', 0);
                    }
                    if (!$teaser) {
                        $teaser = $mainContent->find('p[class*="lead"]', 0);
                    }
                    
                    // If no teaser found, just take the first substantial paragraph
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
                    if ($src && (strpos($src, 'assets/images') !== false || strpos($src, '/bilder/') !== false)) {
                        $alt = $img->alt ?? '';
                        $imageHtml = '<p><img src="' . $src . '" alt="' . htmlspecialchars($alt) . '" style="max-width:100%;height:auto;"></p>';
                        $content = $imageHtml . "\n" . $content;
                        break;
                    }
                }
                
                // Try to find timestamp
                $timeElement = $articleHtml->find('time', 0);
                if ($timeElement && $timeElement->datetime) {
                    $timestamp = strtotime($timeElement->datetime);
                }
                
                // Try to find date in meta tags
                if (!$timestamp) {
                    $dateMeta = $articleHtml->find('meta[property="article:published_time"]', 0);
                    if ($dateMeta && $dateMeta->content) {
                        $timestamp = strtotime($dateMeta->content);
                    }
                }
                
                // If still no content, add a fallback message with the URL
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
        if ($this->getInput('section')) {
            $sectionName = array_search($this->getInput('section'), self::PARAMETERS[0]['section']['values']);
            return self::NAME . ' - ' . $sectionName;
        }
        return parent::getName();
    }
}
