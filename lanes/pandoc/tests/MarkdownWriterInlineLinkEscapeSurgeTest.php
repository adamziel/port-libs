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
$link = static function (mixed $urlOrAttrs, mixed $labelOrChildren, array $attrs = []) use ($text): AstNode {
    if (is_array($urlOrAttrs)) {
        return new AstNode('link', $urlOrAttrs, is_array($labelOrChildren) ? $labelOrChildren : [$text((string) $labelOrChildren)]);
    }

    return new AstNode('link', ['url' => (string) $urlOrAttrs] + $attrs, [$text((string) $labelOrChildren)]);
};
$image = static function (mixed $urlOrAttrs, mixed $altOrChildren) use ($text): AstNode {
    if (is_array($urlOrAttrs)) {
        return new AstNode('image', $urlOrAttrs, is_array($altOrChildren) ? $altOrChildren : [$text((string) $altOrChildren)]);
    }

    return new AstNode('image', ['url' => (string) $urlOrAttrs, 'alt' => (string) $altOrChildren], []);
};

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

$quoteDestinations = [
    'double quoted path segment' => '/review/"packet".md',
    'single quoted path segment' => "/review/'packet'.md",
    'leading double quote path segment' => '/review/"packet.md',
    'trailing double quote path segment' => '/review/packet".md',
    'leading single quote path segment' => "/review/'packet.md",
    'trailing single quote path segment' => "/review/packet'.md",
    'https double quote query value' => 'https://example.test/source?title="packet"',
    'https single quote query value' => "https://example.test/source?title='packet'",
    'mailto double quote local part' => 'mailto:editor"name@example.test',
    'mailto single quote local part' => "mailto:editor'name@example.test",
    'media double quote filename' => 'media/"hero".png',
    'media single quote filename' => "media/'hero'.png",
    'fragment double quote marker' => '#frag"ment',
    'fragment single quote marker' => "#frag'ment",
    'data uri csv quote payload' => 'data:text/plain,"hello"',
];

$escapedDestination = static fn (string $url): string => '<' . str_replace(
    ['\\', '<', '>', '"', "'"],
    ['\\\\', '\\<', '\\>', '\\"', "\\'"],
    $url
) . '>';

foreach ($quoteDestinations as $destinationName => $url) {
    $expectedDestination = $escapedDestination($url);
    $testName = preg_replace('/[^a-z0-9]+/', ' ', strtolower($destinationName)) ?? $destinationName;

    $tests['maps upstream markdown writer inline link quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$link($url, 'packet')])]));

            $t->same('[packet](' . $expectedDestination . ')', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->children[0]->attr('text'));
        };

    $tests['maps upstream markdown writer image quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $image, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$image($url, 'packet')])]));

            $t->same('![packet](' . $expectedDestination . ')', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('image', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->attr('alt'));
        };

    $tests['maps upstream markdown writer reference quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($document([$paragraph([$link($url, 'packet')])]));

            $t->same("[packet]\n\n  [packet]: " . $expectedDestination, $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->children[0]->attr('text'));
        };

    $tests['maps upstream markdown writer titled link quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$link($url, 'packet', ['title' => 'Source title'])])]));

            $t->same('[packet](' . $expectedDestination . ' "Source title")', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('Source title', $node->attr('title'));
        };
}

$literalTriggerCases = [
    'mid sentence citation id' => ['See @doe2026 literal.', 'See \\@doe2026 literal.', 'citation'],
    'hyphen citation id' => ['Compare @doe-2026 literal.', 'Compare \\@doe-2026 literal.', 'citation'],
    'underscore citation id' => ['Compare @doe_2026 literal.', 'Compare \\@doe_2026 literal.', 'citation'],
    'colon citation id' => ['Compare @doe:chap1 literal.', 'Compare \\@doe:chap1 literal.', 'citation'],
    'fragment citation id' => ['Compare @doe#frag literal.', 'Compare \\@doe#frag literal.', 'citation'],
    'slash citation id' => ['Compare @doe/source literal.', 'Compare \\@doe/source literal.', 'citation'],
    'percent citation id' => ['Compare @doe%2Fsource literal.', 'Compare \\@doe%2Fsource literal.', 'citation'],
    'plus citation id' => ['Compare @doe+source literal.', 'Compare \\@doe+source literal.', 'citation'],
    'query citation id' => ['Compare @doe?source literal.', 'Compare \\@doe?source literal.', 'citation'],
    'dotted citation id' => ['Compare @smith.2026 literal.', 'Compare \\@smith.2026 literal.', 'citation'],
    'parenthesized citation id' => ['Prefix (@doe2026) suffix.', 'Prefix (\\@doe2026) suffix.', 'citation'],
    'semicolon citation boundary' => ['Prefix; @roe2025 suffix.', 'Prefix; \\@roe2025 suffix.', 'citation'],
    'colon citation boundary' => ['Prefix: @roe2025 suffix.', 'Prefix: \\@roe2025 suffix.', 'citation'],
    'two citation literals' => ['Two @one and @two markers.', 'Two \\@one and \\@two markers.', 'citation'],
    'citation locator looking literal' => ['Literal @fig-1, p. 4 marker.', 'Literal \\@fig-1, p. 4 marker.', 'citation'],
    'citation after prose link word' => ['Review @mapreduce after link text.', 'Review \\@mapreduce after link text.', 'citation'],
    'citation slash hyphen marker' => ['Archive @case/source-1 marker.', 'Archive \\@case/source-1 marker.', 'citation'],
    'citation underscored path marker' => ['Packet @source_9/details marker.', 'Packet \\@source_9/details marker.', 'citation'],

    'email simple literal' => ['user@example.test', 'user\\@example.test', 'link'],
    'email prose literal' => ['Contact reviewer@example.test today.', 'Contact reviewer\\@example.test today.', 'link'],
    'email dotted local literal' => ['Mail first.last@example.test now.', 'Mail first.last\\@example.test now.', 'link'],
    'email tagged local literal' => ['Mail local+tag@example.test now.', 'Mail local+tag\\@example.test now.', 'link'],
    'email underscored local literal' => ['Mail local_tag@example.test now.', 'Mail local_tag\\@example.test now.', 'link'],
    'email hyphen local literal' => ['Mail local-tag@example.test now.', 'Mail local-tag\\@example.test now.', 'link'],
    'email multilevel domain literal' => ['Mail a@example.co.uk now.', 'Mail a\\@example.co.uk now.', 'link'],
    'email numeric local literal' => ['Mail reviewer123@example.test now.', 'Mail reviewer123\\@example.test now.', 'link'],
    'email hyphen domain literal' => ['Mail qa@example-archive.org now.', 'Mail qa\\@example-archive.org now.', 'link'],
    'email subdomain literal' => ['Mail name.surname+tag@sub.example.test now.', 'Mail name.surname+tag\\@sub.example.test now.', 'link'],
    'email short local literal' => ['Mail x@y.example now.', 'Mail x\\@y.example now.', 'link'],
    'email travel tld literal' => ['Mail tickets@example.travel now.', 'Mail tickets\\@example.travel now.', 'link'],
    'email io tld literal' => ['Mail support@example.io.', 'Mail support\\@example.io.', 'link'],
    'email comma boundary literal' => ['Mail source@example.test, then continue.', 'Mail source\\@example.test, then continue.', 'link'],
    'two email literals' => ['Mail one@example.test and two@example.test.', 'Mail one\\@example.test and two\\@example.test.', 'link'],
    'email uppercase local literal' => ['Mail A.B@example.test now.', 'Mail A.B\\@example.test now.', 'link'],

    'www bare literal' => ['www.example.test', 'www\\.example.test', 'link'],
    'www path literal' => ['See www.example.test/source now.', 'See www\\.example.test/source now.', 'link'],
    'www query literal' => ['See www.example.test/path?q=1 now.', 'See www\\.example.test/path?q=1 now.', 'link'],
    'www fragment literal' => ['See www.example.test#fragment now.', 'See www\\.example.test#fragment now.', 'link'],
    'www port literal' => ['See www.example.test:8080/path now.', 'See www\\.example.test:8080/path now.', 'link'],
    'www hyphen domain literal' => ['See www.example-test.org now.', 'See www\\.example-test.org now.', 'link'],
    'www subdomain literal' => ['See www.sub.example.test now.', 'See www\\.sub.example.test now.', 'link'],
    'www comma boundary literal' => ['See www.example.test, then continue.', 'See www\\.example.test, then continue.', 'link'],
    'www path hyphen literal' => ['See www.example.test/a-b now.', 'See www\\.example.test/a-b now.', 'link'],
    'www underscore path literal' => ['See www.example.test/a_b now.', 'See www\\.example.test/a_b now.', 'link'],
    'www uppercase literal' => ['See WWW.Example.test now.', 'See WWW\\.Example.test now.', 'link'],
    'two www literals' => ['See www.one.test and www.two.test now.', 'See www\\.one.test and www\\.two.test now.', 'link'],

    'https bare literal' => ['https://example.test', 'https\\://example.test', 'link'],
    'http path literal' => ['http://example.test/path', 'http\\://example.test/path', 'link'],
    'git uri literal' => ['git://example.test/repo', 'git\\://example.test/repo', 'link'],
    'file uri literal' => ['file://localhost/tmp/source.md', 'file\\://localhost/tmp/source.md', 'link'],
    'mailto uri literal' => ['mailto:reviewer@example.test', 'mailto\\:reviewer\\@example.test', 'link'],
    'doi uri literal' => ['doi:10.1000/source', 'doi\\:10.1000/source', 'link'],
    'https query literal' => ['See https://example.test/path?q=1 now.', 'See https\\://example.test/path?q=1 now.', 'link'],
    'http fragment literal' => ['See http://example.test#frag now.', 'See http\\://example.test#frag now.', 'link'],
    'git dotted repo literal' => ['See git://example.test/repo.git now.', 'See git\\://example.test/repo.git now.', 'link'],
    'file hyphen path literal' => ['See file://localhost/tmp/source-file.md now.', 'See file\\://localhost/tmp/source-file.md now.', 'link'],
    'doi numeric literal' => ['See doi:10.5555/12345678 now.', 'See doi\\:10.5555/12345678 now.', 'link'],
    'mailto dotted local literal' => ['See mailto:first.last@example.test now.', 'See mailto\\:first.last\\@example.test now.', 'link'],
    'two https literals' => ['Two https://one.test and https://two.test.', 'Two https\\://one.test and https\\://two.test.', 'link'],
    'parenthesized https literal' => ['Parenthesized (https://example.test) now.', 'Parenthesized (https\\://example.test) now.', 'link'],
];

foreach ($literalTriggerCases as $caseName => [$source, $expected, $triggerType]) {
    $tests['maps upstream markdown writer inline escape literal trigger ' . $caseName] =
        static function (TestRunner $t) use ($document, $expected, $paragraph, $source, $text, $triggerType): void {
            $input = $document([$paragraph([$text($source)])]);
            $markdown = (new MarkdownWriter())->write($input);

            $t->same($expected, $markdown);

            $unescaped = (new MarkdownReader())->read($source);
            $t->true(
                in_array($triggerType, array_map(static fn (AstNode $node): string => $node->type, $unescaped->children[0]->children), true),
                'Unescaped source should demonstrate the inline trigger this writer escape prevents'
            );

            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children[0]->children));
            $t->same($source, $roundTrip->children[0]->attr('text'));
        };
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
    'triple dash setext underline' => ['---', '\\-\\-\\-'],
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
