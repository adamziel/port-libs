<?php

declare(strict_types=1);

namespace PortLibs\Readability;

final class ArticleExtractor
{
    public function extract(string $html): Article
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//nav|//footer|//header|//aside|//form') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $title = $this->title($xpath, $dom);
        $best = $this->bestContentNode($xpath) ?? $dom->documentElement;
        $contentHtml = $best instanceof \DOMNode ? $this->innerHtml($best) : '';
        $text = trim(preg_replace('/\s+/', ' ', $best instanceof \DOMNode ? $best->textContent : '') ?? '');
        $excerpt = mb_substr($text, 0, 180);

        return new Article($title, $contentHtml, $text, $excerpt);
    }

    public function toWordPressBlocks(Article $article): string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<main>' . $article->contentHtml . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $blocks = [];
        $main = $dom->getElementsByTagName('main')->item(0);
        if (!$main) {
            return '';
        }

        foreach ($main->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            $html = trim($dom->saveHTML($child) ?: '');
            if ($html === '') {
                continue;
            }
            if (preg_match('/^h([1-6])$/', $tag, $m)) {
                $blocks[] = '<!-- wp:heading {"level":' . $m[1] . '} -->' . "\n" . $html . "\n" . '<!-- /wp:heading -->';
            } elseif ($tag === 'img') {
                $blocks[] = '<!-- wp:image -->' . "\n" . '<figure class="wp-block-image">' . $html . '</figure>' . "\n" . '<!-- /wp:image -->';
            } else {
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . $html . "\n" . '<!-- /wp:paragraph -->';
            }
        }

        return implode("\n\n", $blocks);
    }

    private function title(\DOMXPath $xpath, \DOMDocument $dom): string
    {
        foreach (['//meta[@property="og:title"]/@content', '//h1', '//title'] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = trim($node?->nodeValue ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function bestContentNode(\DOMXPath $xpath): ?\DOMNode
    {
        $best = null;
        $bestScore = -1;
        foreach ($xpath->query('//article|//main|//section|//div|//body') ?: [] as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            $paragraphs = $xpath->query('.//p', $node)?->length ?? 0;
            $score = strlen($text) + (substr_count($text, ',') * 20) + ($paragraphs * 80);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $node;
            }
        }

        return $best;
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return trim($html);
    }
}

