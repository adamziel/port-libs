<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write($document([$paragraph($children)]));
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children): AstNode => new AstNode('image', $attrs, $children);

$markers = [
    'default period marker' => ['#. ', '\\#. '],
    'default paren marker' => ['#) ', '\\#) '],
    'upper alpha period marker' => ['A.  ', 'A\\.  '],
    'upper alpha paren marker' => ['B)  ', 'B\\)  '],
    'lower alpha period marker' => ['a.  ', 'a\\.  '],
    'lower alpha paren marker' => ['z)  ', 'z\\)  '],
    'upper roman single period marker' => ['I.  ', 'I\\.  '],
    'upper roman multi period marker' => ['IV. ', 'IV\\. '],
    'lower roman multi period marker' => ['iv. ', 'iv\\. '],
    'lower roman nine period marker' => ['ix. ', 'ix\\. '],
    'parenthesized decimal marker' => ['(1) ', '\\(1) '],
    'parenthesized multi decimal marker' => ['(12) ', '\\(12) '],
    'parenthesized upper alpha marker' => ['(A)  ', '\\(A)  '],
    'parenthesized lower alpha marker' => ['(z)  ', '\\(z)  '],
    'numbered example marker' => ['(@) ', '\\(@) '],
    'labeled numbered example marker' => ['(@fig-1) ', '\\(@fig-1) '],
];

$suffixes = [
    'literal import',
    'source packet',
    'review handoff',
    'plain paragraph',
];

$tests = [];
foreach ($markers as $markerName => [$sourceMarker, $expectedMarker]) {
    foreach ($suffixes as $suffix) {
        $source = $sourceMarker . $suffix;
        $expected = $expectedMarker . $suffix;
        $testName = preg_replace('/[^a-z0-9]+/', ' ', strtolower($markerName . ' ' . $suffix)) ?? $markerName;

        $tests['maps upstream markdown writer inline escape fancy ordered literal ' . trim($testName)] =
            static function (TestRunner $t) use ($document, $expected, $paragraph, $source, $text): void {
                $input = $document([$paragraph([$text($source)])]);
                $markdown = (new MarkdownWriter())->write($input);

                $t->same($expected, $markdown);

                $unescaped = (new MarkdownReader())->read($source);
                $t->same('ordered_list', $unescaped->children[0]->type);

                $roundTrip = (new MarkdownReader())->read($markdown);
                $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
                $t->same($source, $roundTrip->children[0]->attr('text'));
            };
    }
}

$indents = ['', ' ', '  ', '   '];
$lineStartGuards = [
    'atx heading marker' => ['# heading', '\\# heading'],
    'deep atx heading marker' => ['### heading', '\\### heading'],
    'dash bullet marker' => ['- bullet', '\\- bullet'],
    'plus bullet marker' => ['+ bullet', '\\+ bullet'],
    'asterisk bullet marker' => ['* bullet', '\\* bullet'],
    'decimal ordered period marker' => ['1. ordered', '1\\. ordered'],
    'decimal ordered paren marker' => ['2) ordered', '2\\) ordered'],
    'default ordered period marker' => ['#. default', '\\#. default'],
    'default ordered paren marker' => ['#) default', '\\#) default'],
    'upper alpha ordered period marker' => ['A.  alpha', 'A\\.  alpha'],
    'upper alpha ordered paren marker' => ['B)  alpha', 'B\\)  alpha'],
    'lower roman ordered period marker' => ['iv. roman', 'iv\\. roman'],
    'parenthesized decimal marker' => ['(1) parenthesized', '\\(1) parenthesized'],
    'parenthesized alpha marker' => ['(A)  alpha', '\\(A)  alpha'],
    'numbered example marker' => ['(@) example', '\\(@) example'],
    'labeled numbered example marker' => ['(@fig) example', '\\(@fig) example'],
    'definition colon marker' => [': definition', '\\: definition'],
    'definition tilde marker' => ['~ definition', '\\~ definition'],
    'single equals setext underline' => ['=', '\\='],
    'double equals setext underline' => ['==', '\\=='],
    'triple equals setext underline' => ['===', '\\==='],
    'long equals setext underline' => ['====', '\\===='],
    'single dash setext underline' => ['-', '\\-'],
    'double dash setext underline' => ['--', '\\--'],
    'triple dash setext underline' => ['---', '\\---'],
];

$tests['records markdown writer inline link escape indented line start guard mapped case count'] =
    static function (TestRunner $t) use ($indents, $lineStartGuards): void {
        $t->same(100, count($indents) * count($lineStartGuards));
    };

foreach ($indents as $indent) {
    $indentName = $indent === '' ? 'no indent' : strlen($indent) . ' space indent';
    foreach ($lineStartGuards as $guardName => [$sourceLine, $expectedLine]) {
        $source = 'Term' . "\n" . $indent . $sourceLine;
        $expected = 'Term' . "\n" . $indent . $expectedLine;
        $testName = preg_replace('/[^a-z0-9]+/', ' ', strtolower($indentName . ' ' . $guardName)) ?? $guardName;

        $tests['maps upstream markdown writer inline link escape indented line start guard ' . trim($testName)] =
            static function (TestRunner $t) use ($document, $expected, $paragraph, $source, $text): void {
                $markdown = (new MarkdownWriter())->write($document([$paragraph([$text($source)])]));

                $t->same($expected, $markdown);

                $roundTrip = (new MarkdownReader())->read($markdown);
                $t->same(1, count($roundTrip->children));
                $t->same('paragraph', $roundTrip->children[0]->type);
            };
    }
}

$nestedGuardCases = [
    'emphasis label indented atx marker' => [
        'children' => [new AstNode('emph', [], [$text('Term'), $softbreak(), $text('  # heading')])],
        'expected' => "*Term\n  \\# heading*",
    ],
    'strong label indented setext marker' => [
        'children' => [new AstNode('strong', [], [$text('Term'), $softbreak(), $text('  ===')])],
        'expected' => "**Term\n  \\===**",
    ],
    'span label indented definition marker' => [
        'children' => [new AstNode('span', ['classes' => ['review']], [$text('Term'), $softbreak(), $text('  : definition')])],
        'expected' => "[Term\n  \\: definition]{.review}",
    ],
    'inline link label indented atx marker' => [
        'children' => [$link(['url' => '/review'], [$text('Term'), $softbreak(), $text('  # heading')])],
        'expected' => "[Term\n  \\# heading](/review)",
    ],
    'image label indented setext marker' => [
        'children' => [$image(['url' => '/image.png', 'alt' => 'Term ==='], [$text('Term'), $softbreak(), $text('  ===')])],
        'expected' => "![Term\n  \\===](/image.png)",
    ],
    'reference link label indented setext marker' => [
        'children' => [$link(['url' => '/review'], [$text('Term'), $softbreak(), $text('  ===')])],
        'expected' => "[Term\n  \\===]\n\n  [Term ===]: /review",
        'options' => ['referenceLinks' => true],
    ],
];

foreach ($nestedGuardCases as $label => $case) {
    $tests['maps upstream markdown writer inline link escape nested label guard ' . $label] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $markdown = $writeParagraph($case['children'], $case['options'] ?? []);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
