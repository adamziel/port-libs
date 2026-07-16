<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$readFirstLink = static function (string $markdown): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    foreach ($document->children as $block) {
        foreach ($block->children as $child) {
            if ($child->type === 'link') {
                return $child;
            }
        }
    }

    return new AstNode('missing');
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$escapedClosingBracketLabels = [
    'alpha' => ['alpha \] beta', 'alpha ] beta'],
    'bravo' => ['bravo \] charlie', 'bravo ] charlie'],
    'charlie' => ['charlie \] delta', 'charlie ] delta'],
    'delta' => ['delta \] echo', 'delta ] echo'],
    'echo' => ['echo \] foxtrot', 'echo ] foxtrot'],
    'foxtrot' => ['foxtrot \] golf', 'foxtrot ] golf'],
    'golf' => ['golf \] hotel', 'golf ] hotel'],
    'hotel' => ['hotel \] india', 'hotel ] india'],
    'india' => ['india \] juliet', 'india ] juliet'],
    'juliet' => ['juliet \] kilo', 'juliet ] kilo'],
    'kilo' => ['kilo \] lima', 'kilo ] lima'],
    'lima' => ['lima \] mike', 'lima ] mike'],
    'mike' => ['mike \] november', 'mike ] november'],
    'november' => ['november \] oscar', 'november ] oscar'],
    'oscar' => ['oscar \] papa', 'oscar ] papa'],
    'papa' => ['papa \] quebec', 'papa ] quebec'],
    'quebec' => ['quebec \] romeo', 'quebec ] romeo'],
    'romeo' => ['romeo \] sierra', 'romeo ] sierra'],
    'sierra' => ['sierra \] tango', 'sierra ] tango'],
    'tango' => ['tango \] uniform', 'tango ] uniform'],
    'uniform' => ['uniform \] victor', 'uniform ] victor'],
    'victor' => ['victor \] whiskey', 'victor ] whiskey'],
    'whiskey' => ['whiskey \] xray', 'whiskey ] xray'],
    'xray' => ['xray \] yankee', 'xray ] yankee'],
    'yankee' => ['yankee \] zulu', 'yankee ] zulu'],
    'zulu' => ['zulu \] alpha', 'zulu ] alpha'],
    'double close' => ['double \] close \] label', 'double ] close ] label'],
    'punctuated' => ['punctuated \] label!', 'punctuated ] label!'],
    'entity close' => ['entity &amp; \] label', 'entity & ] label'],
];

$nestedLabels = [
    'simple nested' => 'outer [inner] label',
    'double nested words' => 'outer [inner words] label',
    'numeric nested' => 'case [123] label',
    'punct nested' => 'case [a-b_c] label',
    'entity nested' => 'case [AT&amp;T] label',
    'escaped nested open' => 'case [open \[ marker] label',
    'escaped nested close' => 'case [close \] marker] label',
    'bracket suffix' => 'case suffix [tail]',
    'bracket prefix' => '[head] case suffix',
    'two bracket groups' => 'case [one] and [two]',
    'wide nested text' => 'review [inline link target] label',
    'mixed digits' => 'release [v1.2.3] note',
    'slash nested' => 'path [/docs/api] label',
    'hash nested' => 'heading [#fragment] label',
    'colon nested' => 'scheme [mailto:user@example.test] label',
    'quoted word nested' => 'quote [quoted title] label',
    'apostrophe word nested' => 'quote [single quoted title] label',
    'paren nested' => 'paren [(title)] label',
    'literal word nested' => 'code [literal span] label',
    'final nested' => 'final [inner] reference',
];

$multilineTitleCases = [
    'double same-line title continuation' => ['double-a', '/double-a', "\"first line\nsecond line\"", "first line\nsecond line"],
    'double second-line title continuation' => ['double-b', '/double-b', "\n\"first line\nsecond line\"", "first line\nsecond line"],
    'single same-line title continuation' => ['single-a', '/single-a', "'first line\nsecond line'", "first line\nsecond line"],
    'single second-line title continuation' => ['single-b', '/single-b', "\n'first line\nsecond line'", "first line\nsecond line"],
    'paren same-line title continuation' => ['paren-a', '/paren-a', "(first line\nsecond line)", "first line\nsecond line"],
    'paren second-line title continuation' => ['paren-b', '/paren-b', "\n(first line\nsecond line)", "first line\nsecond line"],
    'double escaped quote continuation' => ['double-escaped', '/double-escaped', "\"first \\\"line\nsecond line\"", "first \"line\nsecond line"],
    'single escaped quote continuation' => ['single-escaped', '/single-escaped', "'first \\'line\nsecond line'", "first 'line\nsecond line"],
    'paren escaped close continuation' => ['paren-escaped', '/paren-escaped', "(first \\)line\nsecond line)", "first )line\nsecond line"],
    'angle destination double continuation' => ['angle-double', '<docs/search?q=one two>', "\"first line\nsecond line\"", "first line\nsecond line"],
    'tab-indented title continuation' => ['tab-title', '/tab-title', "\"first line\n\tsecond line\"", "first line\nsecond line"],
];

$tests = [];

$tests['maps upstream escaped closing bracket reference labels'] = static function (TestRunner $t) use ($readFirstLink, $slug, $plainInlineText, $escapedClosingBracketLabels): void {
    $t->same(29, count($escapedClosingBracketLabels));

    foreach ($escapedClosingBracketLabels as $name => [$sourceLabel, $expectedText]) {
        $url = '/escaped-bracket-' . $slug($name);
        $markdown = "[{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"Escaped bracket {$name}\"";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type, $name);
        $t->same($url, $link->attr('url'), $name);
        $t->same("Escaped bracket {$name}", $link->attr('title'), $name);
        $t->same($expectedText, $plainInlineText($link->children), $name);
    }
};

$tests['maps upstream nested bracket reference labels'] = static function (TestRunner $t) use ($readFirstLink, $slug, $plainInlineText, $nestedLabels): void {
    $t->same(20, count($nestedLabels));

    foreach ($nestedLabels as $name => $label) {
        $url = '/nested-reference-' . $slug($name);
        $markdown = "[{$label}]\n\n[{$label}]: {$url} \"Nested {$name}\"";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type, $name);
        $t->same($url, $link->attr('url'), $name);
        $t->same("Nested {$name}", $link->attr('title'), $name);
        $t->same(str_replace(['\]', '\[', '&amp;'], [']', '[', '&'], $label), $plainInlineText($link->children), $name);
    }
};

$tests['maps upstream multiline reference title continuations'] = static function (TestRunner $t) use ($readFirstLink, $multilineTitleCases): void {
    $t->same(11, count($multilineTitleCases));

    foreach ($multilineTitleCases as $name => [$label, $url, $titleSource, $expectedTitle]) {
        $definitionUrl = $url === '' ? '' : $url;
        $definition = "[{$label}]: {$definitionUrl}";
        if ($titleSource !== '') {
            $definition .= str_starts_with($titleSource, "\n") ? $titleSource : ' ' . $titleSource;
        }
        $markdown = "[{$label}]\n\n{$definition}";
        $link = $readFirstLink($markdown);
        $expectedUrl = str_starts_with($url, '<') ? substr($url, 1, -1) : $url;

        $t->same('link', $link->type, $name);
        $t->same(str_replace(' ', '%20', $expectedUrl), $link->attr('url'), $name);
        $t->same($expectedTitle, $link->attr('title'), $name);
        $t->same($label, $link->children[0]->attr('text'), $name);
    }
};

$tests['maps upstream escaped and nested labels through image and wordpress handoff'] = static function (TestRunner $t): void {
    $document = (new MarkdownReader())->read(implode("\n", [
        '[alpha \] beta] and ![outer [inner] image].',
        '',
        '[alpha \] beta]: /alpha-close "Alpha title"',
        '[outer [inner] image]: media/nested.png "Nested image"',
    ]));
    $blocks = (new WordPressBlockWriter())->write($document);
    $paragraph = $document->children[0] ?? new AstNode('missing');

    $t->same(['link', 'text', 'image', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
    $t->same('/alpha-close', $paragraph->children[0]->attr('url'));
    $t->same('media/nested.png', $paragraph->children[2]->attr('url'));
    $t->same('outer [inner] image', $paragraph->children[2]->attr('alt'));
    $t->contains('<a href="/alpha-close" title="Alpha title">alpha ] beta</a>', $blocks);
    $t->contains('<img src="media/nested.png" alt="outer [inner] image" title="Nested image"/>', $blocks);
};

$tests['records markdown reference surge mapped-case count'] = static function (TestRunner $t) use ($escapedClosingBracketLabels, $nestedLabels, $multilineTitleCases): void {
    $t->same(60, count($escapedClosingBracketLabels) + count($nestedLabels) + count($multilineTitleCases));
};

return $tests;
