<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$readFirstNode = static function (string $markdown, string $type) use ($findFirstNode): AstNode {
    return $findFirstNode((new MarkdownReader())->read($markdown), $type);
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$controlCases = [
    'nul' => 0x00,
    'soh' => 0x01,
    'stx' => 0x02,
    'etx' => 0x03,
    'eot' => 0x04,
    'enq' => 0x05,
    'ack' => 0x06,
    'bel' => 0x07,
    'backspace' => 0x08,
    'shift-out' => 0x0E,
    'shift-in' => 0x0F,
    'data-link-escape' => 0x10,
    'device-control-one' => 0x11,
    'device-control-two' => 0x12,
    'device-control-three' => 0x13,
    'device-control-four' => 0x14,
    'negative-ack' => 0x15,
    'sync-idle' => 0x16,
    'end-transmission-block' => 0x17,
    'cancel' => 0x18,
    'end-medium' => 0x19,
    'substitute' => 0x1A,
    'escape' => 0x1B,
    'file-separator' => 0x1C,
    'group-separator' => 0x1D,
    'record-separator' => 0x1E,
    'unit-separator' => 0x1F,
    'delete' => 0x7F,
];

$tests = [];

foreach (array_slice($controlCases, 0, 20, true) as $name => $code) {
    $tests["maps upstream markdown inline link control-byte destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $code): void {
            $encoded = sprintf('%%%02X', $code);
            $markdown = '[control ' . $name . '](https://example.test/a' . chr($code) . 'b "Control ' . $name . '")';
            $link = $readFirstNode($markdown, 'link');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('link', $link->type);
            $t->same('https://example.test/a' . $encoded . 'b', $link->attr('url'));
            $t->same('Control ' . $name, $link->attr('title'));
            $t->same('control ' . $name, $link->children[0]->attr('text'));
            $t->contains(
                '<a href="' . $html('https://example.test/a' . $encoded . 'b') . '" title="Control ' . $name . '">control ' . $name . '</a>',
                $blocks
            );
        };
}

foreach (array_slice($controlCases, 18, 10, true) as $name => $code) {
    $tests["maps upstream markdown image control-byte destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $code): void {
            $encoded = sprintf('%%%02X', $code);
            $markdown = '![Image ' . $name . '](/media/a' . chr($code) . 'b.png "Image ' . $name . '")';
            $image = $readFirstNode($markdown, 'image');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('image', $image->type);
            $t->same('/media/a' . $encoded . 'b.png', $image->attr('url'));
            $t->same('Image ' . $name, $image->attr('title'));
            $t->same('Image ' . $name, $image->attr('alt'));
            $t->contains(
                '<img src="' . $html('/media/a' . $encoded . 'b.png') . '" alt="Image ' . $name . '" title="Image ' . $name . '"/>',
                $blocks
            );
        };
}

foreach (array_slice($controlCases, 4, 10, true) as $name => $code) {
    $tests["maps upstream markdown reference link control-byte destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $code): void {
            $encoded = sprintf('%%%02X', $code);
            $markdown = '[reference ' . $name . ']' . "\n\n"
                . '[reference ' . $name . ']: /refs/a' . chr($code) . 'b "Reference ' . $name . '"';
            $link = $readFirstNode($markdown, 'link');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('link', $link->type);
            $t->same('/refs/a' . $encoded . 'b', $link->attr('url'));
            $t->same('Reference ' . $name, $link->attr('title'));
            $t->same('reference ' . $name, $link->children[0]->attr('text'));
            $t->contains(
                '<a href="' . $html('/refs/a' . $encoded . 'b') . '" title="Reference ' . $name . '">reference ' . $name . '</a>',
                $blocks
            );
        };
}

$angleAutolinkControlCases = ['shift-out' => $controlCases['shift-out']]
    + array_slice($controlCases, 14, 10, true);

foreach ($angleAutolinkControlCases as $name => $code) {
    $tests["maps upstream markdown angle autolink control-byte destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $code): void {
            $encoded = sprintf('%%%02X', $code);
            $markdown = '<https://example.test/autolink/a' . chr($code) . 'b>';
            $link = $readFirstNode($markdown, 'link');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));
            $url = 'https://example.test/autolink/a' . $encoded . 'b';

            $t->same('link', $link->type);
            $t->same($url, $link->attr('url'));
            $t->same(['uri'], $link->attr('classes'));
            $t->same($url, $link->children[0]->attr('text'));
            $t->contains('<a href="' . $html($url) . '">' . $html($url) . '</a>', $blocks);
        };
}

return $tests;
