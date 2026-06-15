<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inlineDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);
$link = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, $children);
$note = static fn (array $attrs, array $blocks = []): AstNode => new AstNode('note', $attrs, $blocks);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);
$case = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $inlineDocument($children),
    'expected' => $expected,
    'options' => $options,
];

$cases = [
    'one space atx heading marker remains literal' => $case([$text(' # imported heading literal')], ' \# imported heading literal'),
    'two space atx heading marker remains literal' => $case([$text('  ## imported heading literal')], '  \## imported heading literal'),
    'three space atx heading marker remains literal' => $case([$text('   ### imported heading literal')], '   \### imported heading literal'),
    'one space dash bullet marker remains literal' => $case([$text(' - imported bullet literal')], ' \- imported bullet literal'),
    'two space plus bullet marker remains literal' => $case([$text('  + imported bullet literal')], '  \+ imported bullet literal'),
    'three space star bullet marker remains literal' => $case([$text('   * imported bullet literal')], '   \* imported bullet literal'),
    'one space decimal period marker remains literal' => $case([$text(' 1. imported ordered literal')], ' 1\. imported ordered literal'),
    'two space decimal paren marker remains literal' => $case([$text('  12) imported ordered literal')], '  12\) imported ordered literal'),
    'three space padded decimal marker remains literal' => $case([$text('   003. imported ordered literal')], '   003\. imported ordered literal'),
    'one space default period marker remains literal' => $case([$text(' #. imported default ordered literal')], ' \#. imported default ordered literal'),
    'two space default paren marker remains literal' => $case([$text('  #) imported default ordered literal')], '  \#) imported default ordered literal'),
    'one space alpha period marker remains literal' => $case([$text(' A.  imported alpha literal')], ' A\.  imported alpha literal'),
    'two space alpha paren marker remains literal' => $case([$text('  b)  imported alpha literal')], '  b\)  imported alpha literal'),
    'one space roman period marker remains literal' => $case([$text(' IV. imported roman literal')], ' IV\. imported roman literal'),
    'three space lower roman marker remains literal' => $case([$text('   ix. imported roman literal')], '   ix\. imported roman literal'),
    'one space parenthesized decimal marker remains literal' => $case([$text(' (1) imported parenthesized literal')], ' \(1) imported parenthesized literal'),
    'two space parenthesized alpha marker remains literal' => $case([$text('  (A)  imported parenthesized alpha literal')], '  \(A)  imported parenthesized alpha literal'),
    'three space numbered example marker remains literal' => $case([$text('   (@) imported example literal')], '   \(@) imported example literal'),
    'one space labeled example marker remains literal' => $case([$text(' (@fig-1) imported example literal')], ' \(@fig-1) imported example literal'),
    'one space colon definition marker remains literal' => $case([$text(' : imported definition literal')], ' \: imported definition literal'),
    'two space tilde definition marker remains literal' => $case([$text('  ~ imported definition literal')], '  \~ imported definition literal'),
    'soft line one space heading marker remains literal' => $case([$text("alpha\n # nested heading literal")], "alpha\n \\# nested heading literal"),
    'soft line two space bullet marker remains literal' => $case([$text("alpha\n  - nested bullet literal")], "alpha\n  \\- nested bullet literal"),
    'soft line three space ordered marker remains literal' => $case([$text("alpha\n   1. nested ordered literal")], "alpha\n   1\\. nested ordered literal"),
    'soft line three space definition marker remains literal' => $case([$text("alpha\n   : nested definition literal")], "alpha\n   \\: nested definition literal"),
    'nul control inside text becomes a space' => $case([$text("A\x00B")], 'A B'),
    'del control inside text becomes a space' => $case([$text("A\x7FB")], 'A B'),
    'vertical tab inside text becomes a space' => $case([$text("A\x0BB")], 'A B'),
    'form feed inside text becomes a space' => $case([$text("A\x0CB")], 'A B'),
    'carriage return inside text becomes a space' => $case([$text("A\rB")], 'A B'),
    'control run inside text collapses to one space' => $case([$text("A\x00\x01\x02B")], 'A B'),
    'control before citation marker keeps citation literal' => $case([$text("see\x00@doe2026")], 'see \@doe2026'),
    'control before heading marker keeps heading literal' => $case([$text("\x00# heading")], ' \# heading'),
    'reference shortcut label escapes backslash consistently' => $case([
        $link('/source', [$text('Source \ Path')]),
    ], "[Source \\\\ Path]\n\n  [Source \\\\ Path]: /source", ['referenceLinks' => true]),
    'reference shortcut label normalizes nul control' => $case([
        $link('/source', [$text("Source\x00Packet")]),
    ], "[Source Packet]\n\n  [Source Packet]: /source", ['referenceLinks' => true]),
    'reference shortcut label normalizes del control' => $case([
        $link('/source', [$text("Source\x7FPacket")]),
    ], "[Source Packet]\n\n  [Source Packet]: /source", ['referenceLinks' => true]),
    'reference label collision after control normalization generates suffix' => $case([
        $link('/one', [$text("Source\x00Packet")]),
        $text(' and '),
        $link('/two', [$text('Source Packet')]),
    ], "[Source Packet] and [Source Packet][1]\n\n  [Source Packet]: /one\n  [1]: /two", ['referenceLinks' => true]),
    'reference duplicate backslash label generates escaped suffix target' => $case([
        $link('/one', [$text('Source \ Path')]),
        $text(' and '),
        $link('/two', [$text('Source \ Path')]),
    ], "[Source \\\\ Path] and [Source \\\\ Path][1]\n\n  [Source \\\\ Path]: /one\n  [1]: /two", ['referenceLinks' => true]),
    'reference definition title normalizes controls' => $case([
        $link('/source', [$text('Source')], ['title' => "Line\x00Two"]),
    ], "[Source]\n\n  [Source]: /source \"Line Two\"", ['referenceLinks' => true]),
    'reference definition title escapes backslash' => $case([
        $link('/source', [$text('Source')], ['title' => 'Line \ Path']),
    ], "[Source]\n\n  [Source]: /source \"Line \\\\ Path\"", ['referenceLinks' => true]),
    'footnote label escapes backslash consistently' => $case([
        $text('note'),
        $note(['label' => 'review\label'], [$paragraph([$text('body')])]),
    ], "note[^review\\\\label]\n\n[^review\\\\label]: body"),
    'duplicate footnote labels escape backslash with suffix' => $case([
        $note(['label' => 'review\label'], [$paragraph([$text('one')])]),
        $text(' and '),
        $note(['label' => 'review\label'], [$paragraph([$text('two')])]),
    ], "[^review\\\\label] and [^review\\\\label-2]\n\n[^review\\\\label]: one\n\n[^review\\\\label-2]: two"),
    'nul control footnote label falls back to generated label' => $case([
        $note(['label' => "bad\x00label"], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'del control footnote label falls back to generated label' => $case([
        $note(['label' => "bad\x7Flabel"], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'empty footnote label falls back to generated label' => $case([
        $note(['label' => ''], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'abbreviation term escapes backslash in definition' => $case([
        $span([$text('API\Name')], ['classes' => ['abbr'], 'attributes' => ['title' => 'Application Programming Interface']]),
    ], "API\\\\Name\n\n*[API\\\\Name]: Application Programming Interface"),
    'abbreviation title escapes backslash in definition' => $case([
        $span([$text('API')], ['classes' => ['abbr'], 'attributes' => ['title' => 'Line \ Path']]),
    ], "API\n\n*[API]: Line \\\\ Path"),
    'abbreviation title normalizes nul control in definition' => $case([
        $span([$text('API')], ['classes' => ['abbr'], 'attributes' => ['title' => "Line\x00Two"]]),
    ], "API\n\n*[API]: Line Two"),
    'abbreviation title normalizes carriage return in definition' => $case([
        $span([$text('API')], ['classes' => ['abbr'], 'attributes' => ['title' => "Line\rTwo"]]),
    ], "API\n\n*[API]: Line Two"),
    'abbreviation term normalizes control in content and definition' => $case([
        $span([$text("A\x00B")], ['classes' => ['abbr'], 'attributes' => ['title' => 'letters']]),
    ], "A B\n\n*[A B]: letters"),
];

$tests = [
    'records markdown writer inline link escape expansion surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer inline link escape expansion surge ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options'] ?? []))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
