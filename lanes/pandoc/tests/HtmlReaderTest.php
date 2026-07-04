<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlReader;

$tests = [];

$fixture = static function (string $name): string {
    $bytes = file_get_contents(__DIR__ . '/../fixtures/' . $name);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read fixture {$name}");
    }

    return $bytes;
};

$tests['imports upstream html xml lang metadata from root element'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-xml-lang-metadata.html'));
        $meta = $document->attr('meta');

        $t->same('es', $meta['lang']);
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('hola', $document->children[0]->attr('text'));
    };

$tests['appends html meta name fields without trimming or lowercasing like upstream pandoc'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(<<<'HTML'
<html><head>
<meta name="keywords" content="one">
<meta name="keywords" content="two">
<meta name="Empty" content="">
<meta name="spaced" content="  keep  ">
</head><body><p>x</p></body></html>
HTML);
        $meta = $document->attr('meta');

        $t->same(['one', 'two'], $meta['keywords'] ?? null);
        $t->same('', $meta['Empty'] ?? null);
        $t->same('  keep  ', $meta['spaced'] ?? null);
        $t->true(!array_key_exists('empty', $meta));
        $t->same('x', $document->children[0]->attr('text'));
    };

$tests['imports direct pandoc html meta refresh boundary fixture metadata'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-meta-refresh-boundary.html'));
        $meta = $document->attr('meta');

        $t->same('HTML Meta Refresh Import', $meta['title']);
        $t->same('Keep me', $meta['description']);
        $t->true(!array_key_exists('refresh', $meta));
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Visible.', $document->children[0]->attr('text'));
    };

$tests['imports upstream html sup and sub inline nodes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-sup-sub-inline.html'));
        $paragraph = $document->children[0];

        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['text', 'subscript', 'text', 'superscript', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Formula H', $paragraph->children[0]->attr('text'));
        $t->same('2', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('O and release note', $paragraph->children[2]->attr('text'));
        $t->same('review', $paragraph->children[3]->children[0]->attr('text'));
        $t->same(' stay inline.', $paragraph->children[4]->attr('text'));
    };

$tests['imports direct pandoc html standalone sup and sub fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-sup-sub-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['subscript', 'text', 'superscript'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('2', $plain->children[0]->children[0]->attr('text'));
        $t->same(' and ', $plain->children[1]->attr('text'));
        $t->same('review', $plain->children[2]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone time fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-time-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('handoff', $plain->children[0]->children[0]->attr('text'));
        $t->same(' day', $plain->children[1]->attr('text'));
    };

$tests['imports direct pandoc html standalone progress fragment as plain blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-progress-inline.html'));
        $progress = $document->children[0];
        $after = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain', 'plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $progress->children));
        $t->same('70%', $progress->children[0]->attr('text'));
        $t->same('import complete.', $after->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $after->children));
    };

$tests['imports direct pandoc html progress in paragraph as fallback text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-progress-in-paragraph.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('before fallback after', $paragraph->attr('text'));
        $t->same(['text', 'text', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(
            ['before ', 'fallback', ' after'],
            array_map(static fn ($node): string => $node->attr('text'), $paragraph->children)
        );
    };

$tests['imports direct pandoc html inline time without raw wrappers'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read('<p>At <time datetime="2026-07-04"><strong>noon</strong></time>.</p>');
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('At noon.', $paragraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('At ', $paragraph->children[0]->attr('text'));
        $t->same('noon', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html standalone keyboard fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-kbd-inline.html'));
        $plain = $document->children[0];
        $kbd = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['kbd'], $kbd->attr('classes'));
        $t->same('Cmd', $kbd->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone underline fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-underline-inline.html'));
        $plain = $document->children[0];
        $underline = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('underline', $underline->type);
        $t->same(
            ['text', 'strong'],
            array_map(static fn ($node): string => $node->type, $underline->children)
        );
        $t->same('under ', $underline->children[0]->attr('text'));
        $t->same('review', $underline->children[1]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone semantic inline fragments as plain'] =
    static function (TestRunner $t): void {
        $cases = [
            '<abbr title="Hypertext">HTML</abbr>' => ['span', 'HTML', ['abbr']],
            '<b>bold</b>' => ['strong', 'bold', []],
            '<i>italics</i>' => ['emph', 'italics', []],
            '<small>fine print</small>' => ['span', 'fine print', ['small']],
            '<dfn>term</dfn>' => ['span', 'term', ['dfn']],
            '<del>old</del>' => ['strikeout', 'old', []],
            '<ins>new</ins>' => ['underline', 'new', []],
            '<s>gone</s>' => ['strikeout', 'gone', []],
            '<strike>gone</strike>' => ['strikeout', 'gone', []],
        ];

        foreach ($cases as $html => [$expectedType, $expectedText, $expectedClasses]) {
            $document = (new HtmlReader())->read($html);
            $plain = $document->children[0];
            $inline = $plain->children[0];

            $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same([$expectedType], array_map(static fn ($node): string => $node->type, $plain->children), $html);
            $t->same($expectedText, $plain->attr('text'), $html);
            $t->same($expectedText, $inline->children[0]->attr('text'), $html);
            $t->same($expectedClasses, $inline->attr('classes', []), $html);
        }

        $abbr = (new HtmlReader())->read('<abbr title="Hypertext">HTML</abbr>')->children[0]->children[0];
        $t->same('Hypertext', $abbr->attr('attributes')['title'] ?? null);
    };

$tests['imports direct pandoc html standalone abbr and dfn fixture as plain spans'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-abbr-dfn-inline.html'));
        $plain = $document->children[0];
        $abbr = $plain->children[0];
        $separator = $plain->children[1];
        $dfn = $plain->children[2];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('HTML and term', $plain->attr('text'));
        $t->same(['span', 'text', 'span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['abbr'], $abbr->attr('classes'));
        $t->same('Hypertext', $abbr->attr('attributes')['title'] ?? null);
        $t->same('HTML', $abbr->children[0]->attr('text'));
        $t->same(' and ', $separator->attr('text'));
        $t->same(['dfn'], $dfn->attr('classes'));
        $t->same('term', $dfn->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone bdo mark q fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-bdo-mark-q-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['span', 'span', 'quoted'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same(['dir' => 'rtl'], $plain->children[0]->attr('attributes'));
        $t->same('abc', $plain->children[0]->children[0]->attr('text'));
        $t->same(['mark'], $plain->children[1]->attr('classes'));
        $t->same('hi', $plain->children[1]->children[0]->attr('text'));
        $t->same('double', $plain->children[2]->attr('kind'));
        $t->same('/source', $plain->children[2]->children[0]->attr('attributes')['cite'] ?? null);
        $t->same('quote', $plain->children[2]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone bdi fragment as visible text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-bdi-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('abc and name', $plain->attr('text'));
        $t->same(
            ['text', 'text', 'text'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('abc', $plain->children[0]->attr('text'));
        $t->same(' and ', $plain->children[1]->attr('text'));
        $t->same('name', $plain->children[2]->attr('text'));
    };

$tests['imports upstream html self closing anchor without href as span'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read('<a name="anchor"/>');
        $plain = $document->children[0];
        $anchor = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('anchor', $anchor->attr('id'));
        $t->same([], $anchor->children);
    };

$tests['imports upstream html standalone inline code aliases as plain blocks'] =
    static function (TestRunner $t): void {
        $cases = [
            '<code>Answer is 42</code>' => [[], 'Answer is 42'],
            '<tt>Answer is 42</tt>' => [[], 'Answer is 42'],
            '<samp>Answer is 42</samp>' => [['sample'], 'Answer is 42'],
            '<var>result</var>' => [['variable'], 'result'],
        ];

        foreach ($cases as $html => [$classes, $text]) {
            $document = (new HtmlReader())->read($html);
            $plain = $document->children[0];
            $code = $plain->children[0];

            $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same(['code'], array_map(static fn ($node): string => $node->type, $plain->children), $html);
            $t->same($classes, $code->attr('classes', []), $html);
            $t->same($text, $code->attr('text'), $html);
        }
    };

$tests['imports upstream html base absolute image without rewriting absolute url'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-base-absolute-image.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $image = $paragraph->children[0];

        $t->same('HTML Base Absolute Image Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('http://example.com/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('The title', $image->attr('title'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html standalone image fragments as plain images'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-image-data-external.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('', $plain->attr('text'));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://example.com/stickman.gif', $image->attr('url'));
        $t->same('', $image->attr('alt'));
        $t->same(['external' => '1'], $image->attr('attributes'));
        $t->same('1', $image->attr('htmlAttributes')['data-external'] ?? null);
        $t->same([], $image->children);

        $titled = (new HtmlReader())->read('<img title="The title" src="http://example.com/stickman.gif">');
        $titledImage = $titled->children[0]->children[0];
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $titled->children));
        $t->same('http://example.com/stickman.gif', $titledImage->attr('url'));
        $t->same('', $titledImage->attr('alt'));
        $t->same('The title', $titledImage->attr('title'));
        $t->same([], $titledImage->children);
    };

$tests['imports upstream html picture fallback images without source text leakage'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-picture-fallback-image.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $image = $paragraph->children[1];
        $after = $document->children[1];

        $t->same('HTML Picture Fallback Import', $meta['title']);
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Hero Hero frame selected.', $paragraph->attr('text'));
        $t->same(['text', 'image', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('Hero ', $paragraph->children[0]->attr('text'));
        $t->same('hero-small.jpg', $image->attr('url'));
        $t->same('Hero frame', $image->attr('alt'));
        $t->same('Fallback title', $image->attr('title'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $image->children));
        $t->same('Hero frame', $image->children[0]->attr('text'));
        $t->same(' selected.', $paragraph->children[2]->attr('text'));
        $t->same('After picture.', $after->attr('text'));

        $standalone = (new HtmlReader())->read(
            '<picture><source srcset="large.jpg"><img src="small.jpg" alt="Small" title="Title"></picture>'
        );
        $standaloneImage = $standalone->children[0]->children[0];
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $standalone->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $standalone->children[0]->children));
        $t->same('small.jpg', $standaloneImage->attr('url'));
        $t->same('Small', $standaloneImage->attr('alt'));
        $t->same('Title', $standaloneImage->attr('title'));
    };

$tests['imports direct pandoc html standalone emphasis strong span fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-emph-strong-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['strong', 'emph', 'span'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('bold', $plain->children[0]->children[0]->attr('text'));
        $t->same('em', $plain->children[1]->children[0]->attr('text'));
        $t->same(['x'], $plain->children[2]->attr('classes'));
        $t->same('sp', $plain->children[2]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone s inline as strikeout'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-s-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Before obsolete after.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strikeout', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Before ', $paragraph->children[0]->attr('text'));
        $t->same('obsolete', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(' after.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html data value inline as visible children'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-data-value-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Answer forty two.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strong', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Answer ', $paragraph->children[0]->attr('text'));
        $t->same('forty two', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html meter inline as visible children'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-meter-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Load sixty percent complete.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strong', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Load ', $paragraph->children[0]->attr('text'));
        $t->same('sixty percent', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(' complete.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html inline-only main body as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-main-inline-plain.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('hello', $plain->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('hello', $plain->children[0]->attr('text'));

        $explicitParagraph = (new HtmlReader())->read('<main><p>hello</p></main>');
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $explicitParagraph->children));
        $t->same('hello', $explicitParagraph->children[0]->attr('text'));
    };

$tests['imports upstream html main explicit role as native div'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-main-role-native-divs.html'));
        $div = $document->children[0];
        $plain = $div->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['role' => 'foobar'], $div->attr('attributes'));
        $t->same(['role' => 'foobar'], $div->attr('htmlAttributes'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $div->children));
        $t->same('hello', $plain->attr('text'));
    };

$tests['imports direct pandoc html transparent inline fragments as plain text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-transparent-inline-fragment.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('answer and half with isolated text', $plain->attr('text'));
        $t->same(
            ['text', 'text', 'text', 'text', 'text', 'text'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same(
            ['answer', ' and ', 'half', ' with ', 'isolated', ' text'],
            array_map(static fn ($node): string => $node->attr('text'), $plain->children)
        );
    };

$tests['imports upstream html head body fragment base relative image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-base-relative-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/images/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html head body fragment base trailing slash image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-base-trailing-slash-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/images/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html head body fragment base root-relative image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-base-root-relative-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports generated current html inline quote cites resolved against base'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-inline-quote-cite-base.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $outerQuote = $paragraph->children[1];
        $outerSpan = $outerQuote->children[0];
        $innerQuote = $outerSpan->children[1];
        $innerSpan = $innerQuote->children[0];

        $t->same('HTML Inline Quote Cite Base Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'quoted', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('double', $outerQuote->attr('kind'));
        $t->same('single', $innerQuote->attr('kind'));
        $t->same(
            'https://source.example.test/import/posts/quotes/source.html#frag',
            $outerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(
            'https://source.example.test/import/shared/context.html',
            $innerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(['text', 'quoted'], array_map(static fn ($node): string => $node->type, $outerSpan->children));
        $t->same('outer ', $outerSpan->children[0]->attr('text'));
        $t->same('inner source', $innerSpan->children[0]->attr('text'));
        $t->same(' stays linked.', $paragraph->children[2]->attr('text'));
    };

$tests['imports generated current html blockquote fixture as native blockquote'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-blockquote.html'));
        $meta = $document->attr('meta');
        $quote = $document->children[0];
        $quoteParagraph = $quote->children[0];
        $after = $document->children[1];

        $t->same('HTML Blockquote Import', $meta['title']);
        $t->same(['blockquote', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same([], $quote->attrs);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
        $t->same('Quoted source paragraph.', $quoteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $quoteParagraph->children));
        $t->same('Quoted ', $quoteParagraph->children[0]->attr('text'));
        $t->same('source', $quoteParagraph->children[1]->children[0]->attr('text'));
        $t->same(' paragraph.', $quoteParagraph->children[2]->attr('text'));
        $t->same('After quote.', $after->attr('text'));
    };

$tests['imports generated current html definition list fixture as native definition list'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-definition-list.html'));
        $meta = $document->attr('meta');
        $list = $document->children[0];
        $item = $list->children[0];
        $term = $item->children[0];
        $primary = $item->children[1]->children[0];
        $secondary = $item->children[2]->children[0];

        $t->same('HTML Definition List Import', $meta['title']);
        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('definition_item', $item->type);
        $t->same('Packet source', $item->attr('term'));
        $t->same('term', $term->type);
        $t->same('Packet source', $term->attr('text'));
        $t->same(['definition', 'definition'], [$item->children[1]->type, $item->children[2]->type]);
        $t->same('paragraph', $primary->type);
        $t->same('plain', $secondary->type);
        $t->same('Definition primary.', $primary->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $primary->children));
        $t->same('primary', $primary->children[1]->children[0]->attr('text'));
        $t->same('Secondary note.', $secondary->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $secondary->children));
        $t->same('note', $secondary->children[1]->children[0]->attr('text'));
        $t->same('After glossary.', $document->children[1]->attr('text'));
    };

$tests['imports direct pandoc html optional definition-list end tags as tight definitions'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-optional-definition-list-tree-construction.html'));
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $secondItem = $list->children[1];
        $firstDefinition = $firstItem->children[1]->children[0];
        $secondDefinition = $secondItem->children[1]->children[0];
        $after = $document->children[1];

        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['definition_item', 'definition_item'], array_map(static fn ($node): string => $node->type, $list->children));
        $t->same('Term alpha', $firstItem->attr('term'));
        $t->same('Term beta', $secondItem->attr('term'));
        $t->same('plain', $firstDefinition->type);
        $t->same('Definition alpha', $firstDefinition->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $secondDefinition->children));
        $t->same('Definition beta', $secondDefinition->attr('text'));
        $t->same('beta', $secondDefinition->children[1]->children[0]->attr('text'));
        $t->same('After definitions.', $after->attr('text'));
    };

$tests['imports direct pandoc html multi-term definition-list optional end tags'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-multi-term-definition-list.html'));
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $firstTerm = $firstItem->children[0];
        $firstDefinition = $firstItem->children[1]->children[0];
        $secondDefinition = $firstItem->children[2];
        $secondItem = $list->children[1];

        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same("Term alpha\nAlias alpha", $firstItem->attr('term'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $firstTerm->children));
        $t->same('Term alpha', $firstTerm->children[0]->attr('text'));
        $t->same('Alias alpha', $firstTerm->children[2]->attr('text'));
        $t->same('plain', $firstDefinition->type);
        $t->same('First definition', $firstDefinition->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $firstDefinition->children));
        $t->same('definition', $firstDefinition->children[1]->children[0]->attr('text'));
        $t->same(['paragraph', 'bullet_list'], array_map(static fn ($node): string => $node->type, $secondDefinition->children));
        $t->same('Second block', $secondDefinition->children[0]->attr('text'));
        $t->same('Nested note', $secondDefinition->children[1]->children[0]->attr('text'));
        $t->same('Term beta', $secondItem->attr('term'));
        $t->same('Final definition', $secondItem->children[1]->children[0]->attr('text'));
        $t->same('After glossary.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html details summary fixture as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-details-summary-raw-block.html'));
        $summary = $document->children[0];
        $firstBody = $document->children[1];
        $secondBody = $document->children[2];
        $after = $document->children[3];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Show imported source notes', $summary->attr('text'));
        $t->same('Details body keeps emphasis inside the raw disclosure.', $firstBody->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $firstBody->children));
        $t->same('emphasis', $firstBody->children[1]->children[0]->attr('text'));
        $t->same('Second note keeps strong context.', $secondBody->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $secondBody->children));
        $t->same('strong', $secondBody->children[1]->children[0]->attr('text'));
        $t->same('After disclosure.', $after->attr('text'));

        $inlineSummary = (new HtmlReader())->read('<details><summary><strong>Sum</strong></summary><p>Body</p></details>');
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $inlineSummary->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $inlineSummary->children[0]->children));
        $t->same('Sum', $inlineSummary->children[0]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html template content as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-template-raw-boundary.html'));
        $fallback = $document->children[0];
        $after = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Fallback content', $fallback->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $fallback->children));
        $t->same('Fallback ', $fallback->children[0]->attr('text'));
        $t->same('content', $fallback->children[1]->children[0]->attr('text'));
        $t->same('After template.', $after->attr('text'));
    };

$tests['extracts html reader microdata item metadata with itemref and nested item scopes'] =
    static function (TestRunner $t): void {
        $html = '<!doctype html><html><head><title>Microdata Dispatch</title></head><body>'
            . '<article id="post" itemscope itemtype="https://schema.org/Article" itemid="/posts/42" itemref="extra missing">'
            . '<h1 itemprop="headline">Launch Notes</h1>'
            . '<a itemprop="url mainEntityOfPage" href="/posts/42">Canonical</a>'
            . '<img itemprop="image" src="/media/cover.jpg" alt="Cover">'
            . '<time itemprop="datePublished" datetime="2026-06-25">today</time>'
            . '<div id="author" itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">Ada Lovelace</span></div>'
            . '<section id="comment" itemscope itemtype="https://schema.org/Comment"><span itemprop="text">Nested unrelated</span></section>'
            . '</article>'
            . '<p id="extra"><span itemprop="keywords">migration, html</span></p>'
            . '</body></html>';

        $document = (new HtmlReader())->read($html);
        $meta = $document->attr('meta');
        $items = $meta['htmlMicrodataItems'];
        $article = $items[0];
        $articleProperties = $article['properties'];
        $authorItem = $items[1];
        $commentItem = $items[2];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('Microdata Dispatch', $meta['title']);
        $t->same('parsed', $meta['htmlMicrodataParseStatus']);
        $t->same('html-microdata-metadata-only', $meta['htmlMicrodataReviewPolicy']);
        $t->same(3, $meta['htmlMicrodataItemCount']);
        $t->same(3, $meta['htmlMicrodataReportedItemCount']);
        $t->same(1, $meta['htmlMicrodataTopLevelItemCount']);
        $t->same([0], $meta['htmlMicrodataTopLevelItemIndexes']);
        $t->same(8, $meta['htmlMicrodataPropertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords', 'name', 'text'],
            $meta['htmlMicrodataPropertyNames']
        );
        $t->same(['missing-itemref:missing'], $meta['htmlMicrodataDiagnostics']);

        $t->same('article', $article['elementName']);
        $t->same('post', $article['elementId']);
        $t->same(['https://schema.org/Article'], $article['itemTypes']);
        $t->same('/posts/42', $article['itemId']);
        $t->same(['extra', 'missing'], $article['itemrefIds']);
        $t->same(['missing'], $article['missingItemrefIds']);
        $t->same(6, $article['propertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords'],
            $article['propertyNames']
        );
        $t->same([
            'headline' => 1,
            'url' => 1,
            'mainEntityOfPage' => 1,
            'image' => 1,
            'datePublished' => 1,
            'author' => 1,
            'keywords' => 1,
        ], $article['propertyNameCounts']);

        $t->same(['headline'], $articleProperties[0]['names']);
        $t->same('Launch Notes', $articleProperties[0]['value']);
        $t->same('text', $articleProperties[0]['valueSource']);
        $t->same(['url', 'mainEntityOfPage'], $articleProperties[1]['names']);
        $t->same('/posts/42', $articleProperties[1]['value']);
        $t->same('href', $articleProperties[1]['valueSource']);
        $t->same('/media/cover.jpg', $articleProperties[2]['value']);
        $t->same('src', $articleProperties[2]['valueSource']);
        $t->same('2026-06-25', $articleProperties[3]['value']);
        $t->same('datetime', $articleProperties[3]['valueSource']);
        $t->same('item', $articleProperties[4]['valueType']);
        $t->same(['https://schema.org/Person'], $articleProperties[4]['item']['itemTypes']);
        $t->same('author', $articleProperties[4]['item']['elementId']);
        $t->same('Ada Lovelace', $articleProperties[4]['item']['text']);
        $t->same('migration, html', $articleProperties[5]['value']);
        $t->same('text', $articleProperties[5]['valueSource']);

        $t->same('div', $authorItem['elementName']);
        $t->same(['name'], $authorItem['propertyNames']);
        $t->same('Ada Lovelace', $authorItem['properties'][0]['value']);
        $t->same('section', $commentItem['elementName']);
        $t->same(['text'], $commentItem['propertyNames']);
        $t->same('Nested unrelated', $commentItem['properties'][0]['value']);
    };

$tests['resolves html reader microdata url values against document base'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<!doctype html><html><head><base href="https://source.example.test/import/posts/"></head><body>'
            . '<article itemscope>'
            . '<a itemprop="url" href="../review/source.html">source</a>'
            . '<img itemprop="image" src="media/cover.jpg" alt="cover">'
            . '<object itemprop="downloadUrl" data="/exports/archive.zip"></object>'
            . '<source itemprop="contentUrl" src="//cdn.example.test/video.mp4">'
            . '<track itemprop="caption" src="#captions">'
            . '<a itemprop="variant" href="?edition=full">variant</a>'
            . '</article>'
            . '</body></html>'
        );
        $properties = $document->attr('meta')['htmlMicrodataItems'][0]['properties'];

        $t->same('https://source.example.test/import/review/source.html', $properties[0]['value']);
        $t->same('href', $properties[0]['valueSource']);
        $t->same('url', $properties[0]['valueType']);
        $t->same('https://source.example.test/import/posts/media/cover.jpg', $properties[1]['value']);
        $t->same('src', $properties[1]['valueSource']);
        $t->same('url', $properties[1]['valueType']);
        $t->same('https://source.example.test/exports/archive.zip', $properties[2]['value']);
        $t->same('data', $properties[2]['valueSource']);
        $t->same('url', $properties[2]['valueType']);
        $t->same('https://cdn.example.test/video.mp4', $properties[3]['value']);
        $t->same('src', $properties[3]['valueSource']);
        $t->same('url', $properties[3]['valueType']);
        $t->same('https://source.example.test/import/posts/#captions', $properties[4]['value']);
        $t->same('src', $properties[4]['valueSource']);
        $t->same('url', $properties[4]['valueType']);
        $t->same('https://source.example.test/import/posts/?edition=full', $properties[5]['value']);
        $t->same('href', $properties[5]['valueSource']);
        $t->same('url', $properties[5]['valueType']);
    };

$tests['consumes upstream html doc-endnotes container after resolving doc-noteref note'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-doc-noteref-footnotes.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $note = $paragraph->children[1];
        $noteParagraph = $note->children[0];

        $t->same('doc-endnotes-containers-consumed-after-note-resolution', $meta['htmlFootnoteContainerPolicy']);
        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'note', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $note->children));
        $t->same('Editor note with source context.', $noteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $noteParagraph->children));
        $t->same('source context', $noteParagraph->children[1]->children[0]->attr('text'));
    };

$tests['resolves upstream html doc-noteref notes in table placements and consumes endnotes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-doc-noteref-table-placement.html'));
        $meta = $document->attr('meta');
        $firstParagraphNote = $document->children[1]->children[1];
        $table = $document->children[2];
        $captionInlines = $table->attr('captionInlines');
        $headCell = $table->children[0]->children[0]->children[0];
        $bodyCell = $table->children[1]->children[0]->children[0];
        $lastParagraphNote = $document->children[4]->children[1];

        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(
            ['heading', 'paragraph', 'table', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('doc footnote', $firstParagraphNote->children[0]->attr('text'));
        $t->same(['center'], $table->attr('alignments'));
        $t->same('width:17%', $table->attr('attributes')['style'] ?? null);
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same('caption footnote', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $headCell->children));
        $t->same('header footnote', $headCell->children[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $bodyCell->children));
        $t->same('table cell footnote', $bodyCell->children[1]->children[0]->attr('text'));
        $t->same('doc footnote', $lastParagraphNote->children[0]->attr('text'));
    };

$tests['imports generated current html table foot rows'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-table-foot.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];

        $t->same(['table', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Quarterly totals', $table->attr('caption'));
        $t->same(['audit-grid'], $table->attr('classes'));
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Quarter', 'Total'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(2, count($body->children));
        $t->same(['Q2', '18'], array_map(static fn ($cell): string => $cell->attr('text'), $body->children[1]->children));
        $t->same(['Combined', '30'], array_map(static fn ($cell): string => $cell->attr('text'), $foot->children[0]->children));
        $t->same('After table.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html multiple tbody row header columns'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-multi-tbody-row-header-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $firstBody = $table->children[1];
        $secondBody = $table->children[2];
        $geometry = $table->attr('tableGeometry');

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Region', 'Metric', 'Q1', 'Q2'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(2, $firstBody->attr('rowHeadColumns'));
        $t->same(1, $secondBody->attr('rowHeadColumns'));
        $t->same(['North', '12', '15'], array_map(static fn ($cell): string => $cell->attr('text'), $firstBody->children[0]->children));
        $t->same(2, $firstBody->children[0]->children[0]->attr('colspan'));
        $t->same(['Ops', 'Latency', '4', '3'], array_map(static fn ($cell): string => $cell->attr('text'), $secondBody->children[1]->children));
        $t->same(2, $geometry['summary']['rowHeadGroupCount'] ?? null);
        $t->same([2, 1], $geometry['summary']['rowHeadColumnCounts'] ?? null);
        $t->same(true, $geometry['summary']['hasDifferingRowHeadColumns'] ?? null);
    };

$tests['imports direct pandoc html implicit tbody row header table'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-implicit-tbody-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $row = $body->children[0];

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same([], $head->children);
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(['Item', 'Count'], array_map(static fn ($cell): string => $cell->attr('text'), $row->children));
    };

$tests['imports direct pandoc html table row and column spans'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-table-row-col-span.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $headRow = $head->children[0];
        $firstBodyRow = $body->children[0];
        $secondBodyRow = $body->children[1];

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same('Span audit', $table->attr('caption'));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(['Region', 'Totals'], array_map(static fn ($cell): string => $cell->attr('text'), $headRow->children));
        $t->same(2, $headRow->children[1]->attr('colspan'));
        $t->same(['North', 'Q1', '12'], array_map(static fn ($cell): string => $cell->attr('text'), $firstBodyRow->children));
        $t->same(2, $firstBodyRow->children[0]->attr('rowspan'));
        $t->same(['Q2', '18'], array_map(static fn ($cell): string => $cell->attr('text'), $secondBodyRow->children));
    };

$tests['imports direct pandoc html colgroup column widths'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-colgroup-width-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $bodyRow = $body->children[0];
        $valueCell = $bodyRow->children[1];
        $columnSpecs = $table->attr('columnSpecs');
        $columnSources = $table->attr('columnSources');

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Column layout', $table->attr('caption'));
        $t->same(['default', 'default'], $table->attr('alignments'));
        $t->same([0.25, 0.75], $table->attr('widths'));
        $t->same([0.25, 0.75], array_map(static fn (array $spec): float => $spec['width'], $columnSpecs));
        $t->same(['col', 'col'], array_map(static fn (array $source): string => $source['kind'], $columnSources));
        $t->same('width: 25%', $columnSources[0]['colAttributes']['attributes']['style'] ?? null);
        $t->same('width: 75%', $columnSources[1]['colAttributes']['attributes']['style'] ?? null);
        $t->same(['Metric', 'Value'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(['Latency', '42 ms'], array_map(static fn ($cell): string => $cell->attr('text'), $bodyRow->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $valueCell->children));
    };

$tests['imports upstream html block children inside table cells'] =
    static function (TestRunner $t): void {
        $html = '<table><tr><td><ul><li>one</li><li>two</li></ul></td><td><blockquote><p>quote</p></blockquote></td></tr></table>';
        $fullDocument = '<!doctype html><html><body>' . $html . '</body></html>';
        $assertTable = static function ($document) use ($t): void {
            $table = $document->children[0];
            $body = $table->children[1];
            $row = $body->children[0];
            $listCell = $row->children[0];
            $quoteCell = $row->children[1];
            $list = $listCell->children[0];
            $quote = $quoteCell->children[0];

            $t->same('table', $table->type);
            $t->same('bullet_list', $list->type);
            $t->same(['one', 'two'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
            $t->same('blockquote', $quote->type);
            $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
            $t->same('quote', $quote->children[0]->attr('text'));
        };

        $assertTable((new HtmlReader())->read($html));
        $assertTable((new HtmlReader())->read($fullDocument));
    };

$tests['imports direct pandoc html invalid table children as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $html = $fixture('upstream-html-invalid-table-children.html');
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(
                ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
                array_map(static fn ($node): string => $node->type, $document->children),
                $label
            );
            $t->same(
                ['loose', 'A', 'tail', 'B', 'after'],
                array_map(static fn ($node): string => $node->attr('text'), $document->children),
                $label
            );
        };

        $assertBlocks((new HtmlReader())->read($html), 'fragment');
        $assertBlocks((new HtmlReader())->read('<!doctype html><html><body>' . $html . '</body></html>'), 'document');
    };

$tests['imports direct pandoc html orphan table fragment tree construction as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-orphan-table-fragment-tree-construction.html'));

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['A', 'B', 'C', 'after'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html paragraph table tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-paragraph-table-tree-construction.html'));
        $table = $document->children[1];
        $body = $table->children[1];
        $row = $body->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'table', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Cell'], array_map(static fn ($cell): string => $cell->attr('text'), $row->children));
    };

$tests['imports direct pandoc html paragraph hr tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-paragraph-hr-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'horizontal_rule', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same([], $document->children[1]->attrs);
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph list tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-paragraph-block-tree-construction.html'));
        $list = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'bullet_list', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same(['one', 'two'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('two', $list->children[1]->children[0]->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph section tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-paragraph-section-tree-construction.html'));
        $section = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'div', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('one', $document->children[0]->attr('text'));
        $t->same(['section'], $section->attr('classes'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $section->children));
        $t->same('two', $section->children[0]->attr('text'));
        $t->same('three', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph transparent block tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-paragraph-transparent-block-tree-construction.html'));
        $searchBody = $document->children[1];
        $heading = $document->children[3];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('Find term', $searchBody->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $searchBody->children));
        $t->same('term', $searchBody->children[1]->children[0]->attr('text'));
        $t->same('Middle', $document->children[2]->attr('text'));
        $t->same(1, $heading->attr('level'));
        $t->same('Title', $heading->attr('text'));
        $t->same('After', $document->children[4]->attr('text'));
    };

$tests['preserves upstream html omitted table cell closures as visible blocks'] =
    static function (TestRunner $t): void {
        $html = '<table><tbody><tr><td>A<td>B</tbody></table><p>after</p>';
        $explicit = '<table><tbody><tr><td>A</td><td>B</td></tr></tbody></table>';
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(
                ['paragraph', 'paragraph', 'paragraph'],
                array_map(static fn ($node): string => $node->type, $document->children),
                $label
            );
            $t->same(
                ['A', 'B', 'after'],
                array_map(static fn ($node): string => $node->attr('text'), $document->children),
                $label
            );
        };

        $assertBlocks((new HtmlReader())->read($html), 'fragment');
        $assertBlocks((new HtmlReader())->read('<!doctype html><html><body>' . $html . '</body></html>'), 'document');
        $t->same('table', (new HtmlReader())->read($explicit)->children[0]->type);
        $t->same('table', (new HtmlReader())->read('<!doctype html><html><body>' . $explicit . '</body></html>')->children[0]->type);
    };

$tests['imports upstream html transparent block containers as structural children'] =
    static function (TestRunner $t): void {
        $tags = ['address', 'article', 'center', 'dialog', 'dir', 'fieldset', 'footer', 'form', 'hgroup', 'menu', 'nav', 'search', 'summary'];
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(['heading', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children), $label);
            $t->same('Title', $document->children[0]->attr('text'), $label);
            $t->same('Body text.', $document->children[1]->attr('text'), $label);
            $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $document->children[1]->children), $label);
            $t->same('text', $document->children[1]->children[1]->children[0]->attr('text'), $label);
            $t->same('After', $document->children[2]->attr('text'), $label);
        };

        foreach ($tags as $tag) {
            $html = '<' . $tag . ' id="wrapper"><h1>Title</h1><p>Body <em>text</em>.</p></' . $tag . '><p>After</p>';
            $assertBlocks((new HtmlReader())->read($html), $tag . ' fragment');
            $assertBlocks((new HtmlReader())->read('<!doctype html><html><body>' . $html . '</body></html>'), $tag . ' document');
        }
    };

$tests['imports generated current html thematic break as horizontal rule'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-thematic-break.html'));

        $t->same(
            ['paragraph', 'horizontal_rule', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before rule.', $document->children[0]->attr('text'));
        $t->same([], $document->children[1]->attrs);
        $t->same('After rule.', $document->children[2]->attr('text'));
    };

$tests['imports html fragment hr variants as horizontal rules'] =
    static function (TestRunner $t): void {
        foreach (['<hr>', '<hr />', '<hr class="x" id="y">'] as $html) {
            $document = (new HtmlReader())->read($html);

            $t->same(['horizontal_rule'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same([], $document->children[0]->attrs, $html);
        }
    };

$tests['imports direct pandoc html omitted heading end-tag fixture'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-omitted-heading-end-tags.html'));

        $t->same(['heading', 'heading', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Title', $document->children[0]->attr('text'));
        $t->same(2, $document->children[1]->attr('level'));
        $t->same('Next heading', $document->children[1]->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same('Body.', $document->children[2]->attr('text'));
    };

$tests['imports html ordered lists with signed start values'] =
    static function (TestRunner $t): void {
        foreach ([['0', 0], ['-2', -2], ['+3', 3]] as [$rawStart, $expectedStart]) {
            $document = (new HtmlReader())->read('<ol type="1" start="' . $rawStart . '"><li>Item</li></ol>');
            $list = $document->children[0];

            $t->same('ordered_list', $list->type, $rawStart);
            $t->same($expectedStart, $list->attr('start'), $rawStart);
            $t->same('decimal', $list->attr('style'), $rawStart);
            $t->same('Item', $list->children[0]->attr('text'), $rawStart);
        }
    };

$tests['imports direct pandoc html ordered list type and start fixture'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-ordered-list-type-start.html'));
        $list = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(3, $list->attr('start'));
        $t->same('upper_alpha', $list->attr('style'));
        $t->same(['Alpha', 'Beta'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('Beta', $list->children[1]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html optional list item end-tag tree construction'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-optional-list-item-tree-construction.html'));
        $list = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['bullet_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['alpha', 'beta', 'gamma'],
            array_map(static fn ($item): string => $item->attr('text'), $list->children)
        );
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $list->children[0]->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('beta', $list->children[1]->children[0]->children[0]->attr('text'));
    };

$tests['imports generated current html ruby annotation text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-ruby-annotation.html'));
        $paragraph = $document->children[0];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Ruby Annotation Import', $document->attr('meta')['title']);
        $t->same('Japanese ' . "\u{6F22}" . '(kan) annotation.', $paragraph->attr('text'));
        $t->same(
            ['Japanese ', "\u{6F22}", '(', 'kan', ')', ' annotation.'],
            array_map(static fn ($node): string => $node->attr('text'), $paragraph->children)
        );
        $t->same('After ruby.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html keyboard sample and variable inline semantics'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-kbd-samp-var-inline.html'));
        $paragraph = $document->children[0];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Kbd Samp Var Import', $document->attr('meta')['title']);
        $t->same('Press Cmd and inspect stdout with name.', $paragraph->attr('text'));
        $t->same(
            ['text', 'span', 'text', 'code', 'text', 'code', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same(['kbd'], $paragraph->children[1]->attr('classes'));
        $t->same('Cmd', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(['sample'], $paragraph->children[3]->attr('classes'));
        $t->same('stdout', $paragraph->children[3]->attr('text'));
        $t->same(['variable'], $paragraph->children[5]->attr('classes'));
        $t->same('name', $paragraph->children[5]->attr('text'));
        $t->same('After inline semantics.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html form control visible text semantics'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-form-controls.html'));

        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Form Controls Import', $document->attr('meta')['title']);
        $t->same('Title', $document->children[0]->attr('text'));
        $t->same('DraftReady', $document->children[1]->attr('text'));
        $t->same(
            ['text', 'text'],
            array_map(static fn ($node): string => $node->type, $document->children[1]->children)
        );
        $t->same('Draft', $document->children[1]->children[0]->attr('text'));
        $t->same('Ready', $document->children[1]->children[1]->attr('text'));
        $t->same('After form.', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html standalone select optgroup fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-standalone-select-optgroup-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('DraftReady done', $plain->attr('text'));
        $t->same(['text', 'text', 'text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['Draft', 'Ready', ' done'], array_map(static fn ($node): string => $node->attr('text'), $plain->children));
    };

$tests['imports html result control fragments without swallowing following block boundary'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<output name="total">Calculated <strong>total</strong></output><p>After output.</p>' . "\n"
                . '<select name="status"><option>Draft</option><option selected>Ready</option></select><p>After select.</p>'
        );

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Calculated total', $document->children[0]->attr('text'));
        $t->same(
            ['text', 'strong'],
            array_map(static fn ($node): string => $node->type, $document->children[0]->children)
        );
        $t->same('Calculated ', $document->children[0]->children[0]->attr('text'));
        $t->same('total', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('After output.', $document->children[1]->attr('text'));
        $t->same('DraftReady', $document->children[2]->attr('text'));
        $t->same(
            ['text', 'text'],
            array_map(static fn ($node): string => $node->type, $document->children[2]->children)
        );
        $t->same('Draft', $document->children[2]->children[0]->attr('text'));
        $t->same('Ready', $document->children[2]->children[1]->attr('text'));
        $t->same('After select.', $document->children[3]->attr('text'));
    };

$tests['imports generated current html address block as paragraph content'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-address-block.html'));
        $contact = $document->children[0];
        $link = $contact->children[2];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Address Block Import', $document->attr('meta')['title']);
        $t->same('Migration Desk migration@example.test', $contact->attr('text'));
        $t->same(
            ['strong', 'linebreak', 'link'],
            array_map(static fn ($node): string => $node->type, $contact->children)
        );
        $t->same('Migration Desk', $contact->children[0]->children[0]->attr('text'));
        $t->same('mailto:migration@example.test', $link->attr('url'));
        $t->same('migration@example.test', $link->children[0]->attr('text'));
        $t->same('After contact.', $document->children[1]->attr('text'));
    };

$tests['preserves html doc-endnotes container when no noteref was resolved'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<section role="doc-endnotes"><ol><li id="fn1"><p>Loose footnote body.</p></li></ol></section>'
        );
        $meta = $document->attr('meta');
        $container = $document->children[0];

        $t->same(0, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('doc-endnotes', $container->attr('htmlAttributes')['role'] ?? null);
        $t->same('ordered_list', $container->children[0]->type);
        $t->same('Loose footnote body.', $container->children[0]->children[0]->attr('text'));
    };

$valueSourceCases = [
    'data value attribute' => [
        '<data itemprop="ratingValue" value="4.5">four and half</data>',
        'ratingValue',
        '4.5',
        'value',
        'string',
    ],
    'meter value attribute' => [
        '<meter itemprop="confidence" value="0.82">82%</meter>',
        'confidence',
        '0.82',
        'value',
        'string',
    ],
    'object data url' => [
        '<object itemprop="downloadUrl" data="/review.bin"></object>',
        'downloadUrl',
        '/review.bin',
        'data',
        'url',
    ],
    'time text fallback' => [
        '<time itemprop="reviewedAt">June 25</time>',
        'reviewedAt',
        'June 25',
        'text',
        'string',
    ],
    'meta empty content' => [
        '<meta itemprop="empty" content="">',
        'empty',
        '',
        'content',
        'string',
    ],
];

foreach ($valueSourceCases as $name => [$markup, $propertyName, $expectedValue, $expectedSource, $expectedType]) {
    $tests['extracts html reader microdata property value source ' . $name] =
        static function (TestRunner $t) use ($expectedSource, $expectedType, $expectedValue, $markup, $propertyName): void {
            $document = (new HtmlReader())->read(
                '<!doctype html><html><body><section itemscope itemtype="https://schema.org/Review">'
                . $markup
                . '</section></body></html>'
            );
            $property = $document->attr('meta')['htmlMicrodataItems'][0]['properties'][0];

            $t->same([$propertyName], $property['names']);
            $t->same($expectedValue, $property['value']);
            $t->same($expectedSource, $property['valueSource']);
            $t->same($expectedType, $property['valueType']);
        };
}

$tests['keeps html reader imports alive when microdata dom parse is unavailable'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<!doctype html [<!ENTITY review SYSTEM "file:///etc/passwd">]><html><body><p>Unsafe declaration source.</p></body></html>'
        );
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('unavailable', $meta['htmlMicrodataParseStatus']);
        $t->same(0, $meta['htmlMicrodataItemCount']);
        $t->same(0, $meta['htmlMicrodataPropertyCount']);
        $t->same(['html-microdata-dom-parse-failed'], $meta['htmlMicrodataDiagnostics']);
    };

$tests['records html reader microdata metadata mapped-case count'] =
    static function (TestRunner $t) use ($valueSourceCases): void {
        $t->same(11, 1 + 4 + count($valueSourceCases) + 1);
    };

return $tests;
