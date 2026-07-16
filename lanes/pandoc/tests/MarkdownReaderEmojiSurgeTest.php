<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$emojiSurgeAliases = [
    '100' => "\u{1F4AF}",
    '1234' => "\u{1F522}",
    '8ball' => "\u{1F3B1}",
    'a' => "\u{1F170}\u{FE0F}",
    'ab' => "\u{1F18E}",
    'abc' => "\u{1F524}",
    'abcd' => "\u{1F521}",
    'accept' => "\u{1F251}",
    'airplane' => "\u{2708}\u{FE0F}",
    'alarm_clock' => "\u{23F0}",
    'alien' => "\u{1F47D}",
    'ambulance' => "\u{1F691}",
    'anchor' => "\u{2693}",
    'anger' => "\u{1F4A2}",
    'apple' => "\u{1F34E}",
    'art' => "\u{1F3A8}",
    'baby' => "\u{1F476}",
    'balloon' => "\u{1F388}",
    'banana' => "\u{1F34C}",
    'bangbang' => "\u{203C}\u{FE0F}",
    'basketball' => "\u{1F3C0}",
    'bell' => "\u{1F514}",
    'bike' => "\u{1F6B2}",
    'bird' => "\u{1F426}",
    'birthday' => "\u{1F382}",
    'blue_heart' => "\u{1F499}",
    'boom' => "\u{1F4A5}",
    'bow' => "\u{1F647}",
    'briefcase' => "\u{1F4BC}",
    'car' => "\u{1F697}",
    'cat' => "\u{1F431}",
    'checkered_flag' => "\u{1F3C1}",
    'cherry_blossom' => "\u{1F338}",
    'clock1' => "\u{1F550}",
    'closed_book' => "\u{1F4D5}",
    'cloud' => "\u{2601}\u{FE0F}",
    'coffee' => "\u{2615}",
    'construction' => "\u{1F6A7}",
    'construction_worker' => "\u{1F477}",
    'cool' => "\u{1F192}",
    'dart' => "\u{1F3AF}",
];

$tests = [];

foreach ($emojiSurgeAliases as $alias => $glyph) {
    $alias = (string) $alias;
    $tests['maps upstream gfm emoji alias surge ' . $alias] = static function (TestRunner $t) use ($alias, $glyph): void {
        $markdown = 'Emoji :' . $alias . ': stays native.';
        $document = (new MarkdownReader())->read($markdown);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $emoji = null;
        foreach ($paragraph->children as $child) {
            if (
                $child->type === 'span'
                && $child->attr('classes') === ['emoji']
                && ($child->attr('attributes')['data-emoji'] ?? null) === $alias
            ) {
                $emoji = $child;
                break;
            }
        }

        $t->same('paragraph', $paragraph->type);
        $t->true($emoji instanceof AstNode, 'Expected emoji alias :' . $alias . ': to become a native emoji span');
        if (!$emoji instanceof AstNode) {
            return;
        }

        $t->same('span', $emoji->type);
        $t->same(['emoji'], $emoji->attr('classes'));
        $t->same(['data-emoji' => $alias], $emoji->attr('attributes'));
        $t->same($glyph, $emoji->children[0]->attr('text'));

        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<span class="emoji" data-emoji="' . $alias . '">' . $glyph . '</span>', $blocks);
    };
}

$tests['records upstream gfm emoji alias surge mapped-case count'] = static function (TestRunner $t) use ($emojiSurgeAliases): void {
    $t->same(41, count($emojiSurgeAliases));

    $unknown = (new MarkdownReader())->read('Unknown :not-a-gfm-surge-emoji: stays literal.');
    $t->same('Unknown :not-a-gfm-surge-emoji: stays literal.', $unknown->children[0]->attr('text'));
};

return $tests;