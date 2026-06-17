<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$escapablePunctuation = str_split("!\"#\$%&'()*+,-./:;<=>?@[\\]^_`{|}~");
$nonEscapableCharacters = [
    'letter' => 'a',
    'digit' => '1',
    'space' => ' ',
];

return [
    'maps upstream commonmark backslash escapes for every escapable punctuation mark' =>
        static function (TestRunner $t) use ($escapablePunctuation): void {
            $reader = new MarkdownReader(['format' => 'commonmark']);

            $t->same(32, count($escapablePunctuation));
            foreach ($escapablePunctuation as $character) {
                $source = 'before \\' . $character . ' after';
                $expected = 'before ' . $character . ' after';
                $paragraph = $reader->read($source)->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $source);
                $t->same($expected, $paragraph->attr('text'), $source);
                $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $source);
                $t->same($expected, $paragraph->children[0]->attr('text'), $source);
            }
        },

    'keeps upstream commonmark backslashes before non escapable characters literal' =>
        static function (TestRunner $t) use ($nonEscapableCharacters): void {
            $reader = new MarkdownReader(['format' => 'commonmark']);

            $t->same(3, count($nonEscapableCharacters));
            foreach ($nonEscapableCharacters as $label => $character) {
                $source = 'before \\' . $character . ' after';
                $expected = 'before \\' . $character . ' after';
                $paragraph = $reader->read($source)->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $label);
                $t->same($expected, $paragraph->attr('text'), $label);
                $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $label);
                $t->same($expected, $paragraph->children[0]->attr('text'), $label);
            }
        },

    'keeps upstream commonmark guarded contexts from applying backslash escapes' =>
        static function (TestRunner $t) use ($plainText): void {
            $reader = new MarkdownReader(['format' => 'commonmark']);

            $code = $reader->read('`\\*`')->children[0]->children[0] ?? new AstNode('missing');
            $raw = $reader->read('<span title="\\*">raw</span>')->children[0] ?? new AstNode('missing');
            $uri = $reader->read('<https://example.test/a\\*b?x=1&amp;y=2>')->children[0]->children[0] ?? new AstNode('missing');
            $email = $reader->read('<review\\+tag@example.test>')->children[0]->children[0] ?? new AstNode('missing');
            $inline = $reader->read('[escaped](/a\\*b "T \\*")')->children[0]->children[0] ?? new AstNode('missing');

            $t->same('code', $code->type);
            $t->same('\\*', $code->attr('text'));

            $t->same('raw_html_inline', $raw->children[0]->type ?? 'missing');
            $t->same('<span title="\\*">', $raw->children[0]->attr('html'));
            $t->same('raw_html_inline', $raw->children[2]->type ?? 'missing');
            $t->same('</span>', $raw->children[2]->attr('html'));
            $t->same('raw', $plainText($raw));

            $t->same('link', $uri->type);
            $t->same('https://example.test/a\\*b?x=1&y=2', $uri->attr('url'));
            $t->same('https://example.test/a\\*b?x=1&y=2', $plainText($uri));

            $t->same('link', $email->type);
            $t->same('mailto:review\\+tag@example.test', $email->attr('url'));
            $t->same('review\\+tag@example.test', $plainText($email));

            $t->same('link', $inline->type);
            $t->same('/a*b', $inline->attr('url'));
            $t->same('T *', $inline->attr('title'));
            $t->same('escaped', $plainText($inline));
        },

    'records upstream commonmark backslash escape completion mapped-case count' =>
        static function (TestRunner $t) use ($escapablePunctuation, $nonEscapableCharacters): void {
            $t->same(40, count($escapablePunctuation) + count($nonEscapableCharacters) + 5);
        },
];
