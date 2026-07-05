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

$wwwCases = [
    'plain host' => ['markdown' => 'www.example.test', 'url' => 'http://www.example.test', 'text' => 'www.example.test'],
    'leading text' => ['markdown' => 'Source www.example.test/docs ready.', 'url' => 'http://www.example.test/docs', 'text' => 'www.example.test/docs', 'after' => ' ready.'],
    'trailing period' => ['markdown' => 'www.example.test.', 'url' => 'http://www.example.test', 'text' => 'www.example.test', 'after' => '.'],
    'trailing comma' => ['markdown' => 'www.example.test,', 'url' => 'http://www.example.test', 'text' => 'www.example.test', 'after' => ','],
    'trailing semicolon' => ['markdown' => 'www.example.test;', 'url' => 'http://www.example.test', 'text' => 'www.example.test', 'after' => ';'],
    'trailing exclamation' => ['markdown' => 'www.example.test!', 'url' => 'http://www.example.test', 'text' => 'www.example.test', 'after' => '!'],
    'parenthesized host' => ['markdown' => '(www.example.test)', 'url' => 'http://www.example.test', 'text' => 'www.example.test', 'after' => ')'],
    'balanced parenthesized path' => ['markdown' => 'www.example.test/path_(draft)', 'url' => 'http://www.example.test/path_(draft)', 'text' => 'www.example.test/path_(draft)'],
    'unbalanced close paren path' => ['markdown' => 'www.example.test/path)', 'url' => 'http://www.example.test/path', 'text' => 'www.example.test/path', 'after' => ')'],
    'bracketed path' => ['markdown' => 'www.example.test/path[one]', 'url' => 'http://www.example.test/path%5Bone%5D', 'text' => 'www.example.test/path[one]'],
    'curly path' => ['markdown' => 'www.example.test/path{one}', 'url' => 'http://www.example.test/path%7Bone%7D', 'text' => 'www.example.test/path{one}'],
    'query ampersand' => ['markdown' => 'www.example.test/search?q=alpha&lang=en', 'url' => 'http://www.example.test/search?q=alpha&lang=en', 'text' => 'www.example.test/search?q=alpha&lang=en'],
    'fragment' => ['markdown' => 'www.example.test/docs#section-2', 'url' => 'http://www.example.test/docs#section-2', 'text' => 'www.example.test/docs#section-2'],
    'port and path' => ['markdown' => 'www.example.test:8080/admin', 'url' => 'http://www.example.test:8080/admin', 'text' => 'www.example.test:8080/admin'],
    'hyphenated domain' => ['markdown' => 'www.review-source.example/a-b', 'url' => 'http://www.review-source.example/a-b', 'text' => 'www.review-source.example/a-b'],
    'uppercase marker' => ['markdown' => 'WWW.EXAMPLE.TEST/UP', 'url' => 'http://WWW.EXAMPLE.TEST/UP', 'text' => 'WWW.EXAMPLE.TEST/UP'],
    'escaped dot' => ['markdown' => 'www.example\.test/docs', 'url' => 'http://www.example.test/docs', 'text' => 'www.example.test/docs'],
    'percent encoded path' => ['markdown' => 'www.example.test/url%20with%20spaces', 'url' => 'http://www.example.test/url%20with%20spaces', 'text' => 'www.example.test/url%20with%20spaces'],
    'plus path' => ['markdown' => 'www.example.test/action+pack', 'url' => 'http://www.example.test/action+pack', 'text' => 'www.example.test/action+pack'],
    'colon path' => ['markdown' => 'www.example.test/a:b', 'url' => 'http://www.example.test/a:b', 'text' => 'www.example.test/a:b'],
    'underscore path' => ['markdown' => 'www.example.test/review_packet', 'url' => 'http://www.example.test/review_packet', 'text' => 'www.example.test/review_packet'],
    'equals query' => ['markdown' => 'www.example.test/?source=fixture', 'url' => 'http://www.example.test/?source=fixture', 'text' => 'www.example.test/?source=fixture'],
    'at sign path' => ['markdown' => 'www.example.test/@review', 'url' => 'http://www.example.test/@review', 'text' => 'www.example.test/@review'],
    'entity query' => ['markdown' => 'www.example.test?a=AT&amp;T', 'url' => 'http://www.example.test?a=AT&T', 'text' => 'www.example.test?a=AT&T'],
    'trailing colon after path' => ['markdown' => 'www.example.test/path:', 'url' => 'http://www.example.test/path', 'text' => 'www.example.test/path', 'after' => ':'],
];

$emailCases = [
    'plain address' => ['markdown' => 'user@example.test', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test'],
    'sentence period' => ['markdown' => 'user@example.test.', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => '.'],
    'trailing comma' => ['markdown' => 'user@example.test,', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => ','],
    'trailing semicolon' => ['markdown' => 'user@example.test;', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => ';'],
    'parenthesized address' => ['markdown' => '(user@example.test)', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => ')'],
    'plus tag local' => ['markdown' => 'first.last+tag@example.test', 'url' => 'mailto:first.last+tag@example.test', 'text' => 'first.last+tag@example.test'],
    'dotted local' => ['markdown' => 'first.last@example.test', 'url' => 'mailto:first.last@example.test', 'text' => 'first.last@example.test'],
    'hyphen local' => ['markdown' => 'source-review@example-domain.test', 'url' => 'mailto:source-review@example-domain.test', 'text' => 'source-review@example-domain.test'],
    'underscore local' => ['markdown' => 'migration_batch@example.test', 'url' => 'mailto:migration_batch@example.test', 'text' => 'migration_batch@example.test'],
    'apostrophe local' => ['markdown' => "o'connor@example.test", 'url' => "mailto:o'connor@example.test", 'text' => "o'connor@example.test"],
    'slash local' => ['markdown' => 'foo/bar@example.test', 'url' => 'mailto:foo/bar@example.test', 'text' => 'foo/bar@example.test'],
    'equals local' => ['markdown' => 'foo=bar@example.test', 'url' => 'mailto:foo=bar@example.test', 'text' => 'foo=bar@example.test'],
    'question local' => ['markdown' => 'question?mark@example.test', 'url' => 'mailto:question?mark@example.test', 'text' => 'question?mark@example.test'],
    'percent local' => ['markdown' => 'foo%bar@example.test', 'url' => 'mailto:foo%bar@example.test', 'text' => 'foo%bar@example.test'],
    'bang local' => ['markdown' => 'review!alert@example.test', 'url' => 'mailto:review!alert@example.test', 'text' => 'review!alert@example.test'],
    'subdomain address' => ['markdown' => 'user@mail.review.example.test', 'url' => 'mailto:user@mail.review.example.test', 'text' => 'user@mail.review.example.test'],
    'uppercase address' => ['markdown' => 'USER@EXAMPLE.TEST', 'url' => 'mailto:USER@EXAMPLE.TEST', 'text' => 'USER@EXAMPLE.TEST'],
    'numeric address' => ['markdown' => 'u123@x42.example', 'url' => 'mailto:u123@x42.example', 'text' => 'u123@x42.example'],
    'long domain labels' => ['markdown' => 'reviewer@source-import.example.test', 'url' => 'mailto:reviewer@source-import.example.test', 'text' => 'reviewer@source-import.example.test'],
    'country domain' => ['markdown' => 'user.name@example.co.uk', 'url' => 'mailto:user.name@example.co.uk', 'text' => 'user.name@example.co.uk'],
    'leading text' => ['markdown' => 'Contact user@example.test now.', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => ' now.'],
    'colon before address' => ['markdown' => 'Email:user@example.test', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test'],
    'trailing exclamation' => ['markdown' => 'user@example.test!', 'url' => 'mailto:user@example.test', 'text' => 'user@example.test', 'after' => '!'],
    'braced local' => ['markdown' => 'name{tag}@example.test', 'url' => 'mailto:name{tag}@example.test', 'text' => 'name{tag}@example.test'],
    'tilde local' => ['markdown' => 'name~tag@example.test', 'url' => 'mailto:name~tag@example.test', 'text' => 'name~tag@example.test'],
];

$tests = [];

foreach ($wwwCases as $name => $case) {
    $tests["maps upstream GFM bare www autolink {$name}"] =
        static function (TestRunner $t) use ($case, $firstLink, $textAfterFirstLink, $html): void {
            $document = (new MarkdownReader(['format' => 'gfm']))->read($case['markdown']);
            $link = $firstLink($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type);
            $t->same($case['url'], $link->attr('url'));
            $t->same(['uri'], $link->attr('classes'));
            $t->same($case['text'], $link->children[0]->attr('text'));
            $t->contains('<a href="' . $html($case['url']) . '">' . $html($case['text']) . '</a>', $blocks);
            if (array_key_exists('after', $case)) {
                $t->same($case['after'], $textAfterFirstLink($document));
            }
        };
}

foreach ($emailCases as $name => $case) {
    $tests["maps upstream GFM bare email autolink {$name}"] =
        static function (TestRunner $t) use ($case, $firstLink, $textAfterFirstLink, $html): void {
            $document = (new MarkdownReader(['format' => 'gfm']))->read($case['markdown']);
            $link = $firstLink($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type);
            $t->same($case['url'], $link->attr('url'));
            $t->same(['email'], $link->attr('classes'));
            $t->same($case['text'], $link->children[0]->attr('text'));
            $t->contains('<a href="' . $html($case['url']) . '">' . $html($case['text']) . '</a>', $blocks);
            if (array_key_exists('after', $case)) {
                $t->same($case['after'], $textAfterFirstLink($document));
            }
        };
}

return $tests;
