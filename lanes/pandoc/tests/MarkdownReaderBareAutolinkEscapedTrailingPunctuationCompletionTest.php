<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstLink = static function (AstNode $document): AstNode {
    foreach ($document->children as $block) {
        foreach ($block->children as $child) {
            if ($child->type === 'link') {
                return $child;
            }
        }
    }

    return new AstNode('missing');
};

$textAfterFirstLink = static function (AstNode $document): string {
    foreach ($document->children as $block) {
        $after = false;
        $text = '';
        foreach ($block->children as $child) {
            if ($after && $child->type === 'text') {
                $text .= (string) $child->attr('text', '');
                continue;
            }

            if ($child->type === 'link') {
                $after = true;
            }
        }

        if ($after) {
            return $text;
        }
    }

    return '';
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$cases = [
    'https escaped trailing period' => [
        'markdown' => 'https://example.test/review\.',
        'url' => 'https://example.test/review.',
        'text' => 'https://example.test/review.',
    ],
    'https escaped trailing bang' => [
        'markdown' => 'https://example.test/review\!',
        'url' => 'https://example.test/review!',
        'text' => 'https://example.test/review!',
    ],
    'https escaped trailing colon' => [
        'markdown' => 'https://example.test/review\:',
        'url' => 'https://example.test/review:',
        'text' => 'https://example.test/review:',
    ],
    'https escaped trailing question mark' => [
        'markdown' => 'https://example.test/review\?',
        'url' => 'https://example.test/review?',
        'text' => 'https://example.test/review?',
    ],
    'https escaped trailing comma with sentence comma' => [
        'markdown' => 'https://example.test/review\,,',
        'url' => 'https://example.test/review,',
        'text' => 'https://example.test/review,',
        'after' => ',',
    ],
    'https escaped trailing period with sentence period' => [
        'markdown' => 'https://example.test/review\..',
        'url' => 'https://example.test/review.',
        'text' => 'https://example.test/review.',
        'after' => '.',
    ],
    'www escaped trailing period' => [
        'markdown' => 'www.example.test/review\.',
        'url' => 'http://www.example.test/review.',
        'text' => 'www.example.test/review.',
    ],
    'www escaped trailing semicolon' => [
        'markdown' => 'www.example.test/review\;',
        'url' => 'http://www.example.test/review;',
        'text' => 'www.example.test/review;',
    ],
    'www escaped trailing close paren' => [
        'markdown' => 'www.example.test/review\)',
        'url' => 'http://www.example.test/review)',
        'text' => 'www.example.test/review)',
    ],
    'www escaped trailing close bracket' => [
        'markdown' => 'www.example.test/review\]',
        'url' => 'http://www.example.test/review%5D',
        'text' => 'www.example.test/review]',
    ],
    'www escaped trailing close brace' => [
        'markdown' => 'www.example.test/review\}',
        'url' => 'http://www.example.test/review%7D',
        'text' => 'www.example.test/review}',
    ],
    'www escaped trailing close paren with sentence paren' => [
        'markdown' => 'www.example.test/review\))',
        'url' => 'http://www.example.test/review)',
        'text' => 'www.example.test/review)',
        'after' => ')',
    ],
    'mailto escaped trailing period' => [
        'markdown' => 'mailto:editor@example.test\.',
        'url' => 'mailto:editor@example.test.',
        'text' => 'mailto:editor@example.test.',
    ],
    'mailto escaped trailing bang' => [
        'markdown' => 'mailto:editor@example.test\!',
        'url' => 'mailto:editor@example.test!',
        'text' => 'mailto:editor@example.test!',
    ],
    'doi escaped trailing question mark' => [
        'markdown' => 'doi:10.1000/review\?',
        'url' => 'doi:10.1000/review?',
        'text' => 'doi:10.1000/review?',
    ],
    'file escaped trailing semicolon' => [
        'markdown' => 'file:///tmp/review\;',
        'url' => 'file:///tmp/review;',
        'text' => 'file:///tmp/review;',
    ],
    'balanced escaped parens stay inside url' => [
        'markdown' => 'https://example.test/\(review\)',
        'url' => 'https://example.test/(review)',
        'text' => 'https://example.test/(review)',
    ],
    'escaped trailing period before link attributes' => [
        'markdown' => 'www.example.test/review\.{#review-source .import}',
        'url' => 'http://www.example.test/review.',
        'text' => 'www.example.test/review.',
        'id' => 'review-source',
        'classes' => ['import'],
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream bare autolink escaped trailing punctuation ' . $name] =
        static function (TestRunner $t) use ($case, $firstLink, $textAfterFirstLink, $html): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($case['markdown']);
            $link = $firstLink($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type);
            $t->same($case['url'], $link->attr('url'));
            $t->same($case['text'], $link->children[0]->attr('text'));
            $t->contains('<a href="' . $html($case['url']) . '"', $blocks);
            $t->same($case['after'] ?? '', $textAfterFirstLink($document));
            if (isset($case['id'])) {
                $t->same($case['id'], $link->attr('id'));
            }
            if (isset($case['classes'])) {
                $t->same($case['classes'], $link->attr('classes'));
            }
        };
}

$tests['records markdown reader bare autolink escaped trailing punctuation mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(18, count($cases));
    };

return $tests;
