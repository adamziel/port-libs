<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'repairs HTML5 fragments and serializes a deterministic normalized tree' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<section><p>One<br>Two<p>Three &amp; four</section>');
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->same('<section><p>One<br>Two</p><p>Three &amp; four</p></section>', $fragment->serialize());
        $t->same('OneTwoThree & four', $fragment->textContent());
        $t->same('html', $summary['mode']);
        $t->same(1, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(3, $summary['textNodes']);
        $t->same(['br', 'p', 'section'], $summary['elementNames']);
        $t->same('element', $nodes[0]['type']);
        $t->same('section', $nodes[0]['name']);
        $t->same(2, count($nodes[0]['children']));
        $t->same('p', $nodes[0]['children'][0]['name']);
        $t->same('br', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(['libxml-repair'], $fragment->diagnosticCodes());
    },
    'filters active tags unsafe attributes and unsafe URLs before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p onclick="alert(1)">'
            . '<a href=" javascript:alert(1) " data-source="legacy" onmouseover="x">Bad</a>'
            . '<img src="https://example.test/a.png" alt="A" srcset="javascript:bad">'
            . '<script>alert(1)</script>'
            . '<span style="color:red" data-review="yes">Safe</span>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $ast = $fragment->toRawHtmlAst(['source' => 'wordpress-import']);

        $t->same('<p><a data-source="legacy">Bad</a><img src="https://example.test/a.png" alt="A"><span data-review="yes">Safe</span></p>', $fragment->serialize());
        $t->same('BadSafe', $fragment->textContent());
        $t->same(['a', 'img', 'p', 'span'], $summary['elementNames']);
        $t->same(['script'], $summary['blockedTags']);
        $t->same(['href', 'onclick', 'onmouseover', 'srcset', 'style'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['unsafe-attribute', 'unsafe-url', 'unsafe-attribute', 'unsafe-url', 'blocked-tag', 'unsafe-attribute'], $fragment->diagnosticCodes());
        $t->same('raw_html', $ast->type);
        $t->same('html', $ast->attr('format'));
        $t->same('wordpress-import', $ast->attr('source'));
        $t->same($fragment->serialize(), $ast->attr('html'));
        $t->same(6, count($ast->attr('diagnostics')));
    },
    'filters mixed unsafe srcset candidates before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">'
            . '<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x, javascript:alert(1) 2x" alt="Mixed">'
            . '<img src="/media/fallback.png" srcset="/media/hero.webp 1x, mailto:review@example.test 2x" alt="Fallback">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();

        $t->contains('<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">', $html);
        $t->contains('<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x" alt="Mixed">', $html);
        $t->contains('<img src="/media/fallback.png" srcset="/media/hero.webp 1x" alt="Fallback">', $html);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url'], $fragment->diagnosticCodes());
    },
    'normalizes srcset width and density descriptors while dropping invalid candidates' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="/media/hero.png" srcset=" ./hero-0640.webp 0640w, https://cdn.example.test/hero@2x.webp 02.00x, /media/fallback.jpg, javascript:alert(1) 4x, /media/zero.webp 0w, /media/mixed.webp 1x 640w, /media/uppercase.webp 1.50X " alt="Hero">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();

        $expectedSrcset = './hero-0640.webp 640w, https://cdn.example.test/hero@2x.webp 2x, /media/fallback.jpg, /media/uppercase.webp 1.5x';
        $t->contains('<img src="/media/hero.png" srcset="' . $expectedSrcset . '" alt="Hero">', $html);
        $t->same($expectedSrcset, $nodes[0]['children'][0]['attrs']['srcset']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(3, $summary['diagnostics']);
        $t->same(['unsafe-url', 'invalid-srcset-descriptor', 'invalid-srcset-descriptor'], $fragment->diagnosticCodes());
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset URL candidate to be removed');
        $t->true(!str_contains($html, 'zero.webp'), 'Expected zero-width srcset candidate to be removed');
        $t->true(!str_contains($html, 'mixed.webp'), 'Expected mixed descriptor srcset candidate to be removed');
    },
    'serializes html5 boolean attributes without redundant values for review media' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<details open="open"><summary>Review packet</summary>'
            . '<video controls="" muted playsinline loop poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video>'
            . '</details>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/media-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<details open><summary>Review packet</summary><video controls muted playsinline loop poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video></details>';
        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same(['details', 'source', 'summary', 'video'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['open' => 'open'], $nodes[0]['attrs']);
        $t->same([
            'controls' => '',
            'muted' => '',
            'playsinline' => '',
            'loop' => '',
            'poster' => '/media/cover.jpg',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('/migration/media-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($fragment->serialize(), 'open="open"'), 'Expected open to serialize as an HTML5 boolean attribute');
        $t->true(!str_contains($fragment->serialize(), 'controls=""'), 'Expected controls to serialize as an HTML5 boolean attribute');
    },
    'parses XML fragments strictly and rejects DTD entity expansion inputs' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromXml('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>');
        $summary = $fragment->summary();

        $t->same('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>', $fragment->serialize());
        $t->same('A & B', $fragment->textContent());
        $t->same('xml', $summary['mode']);
        $t->same(2, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(['br', 'custom', 'note', 'root'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<root><unclosed></root>'));
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><root>&xxe;</root>'));
    },
    'normalizes svg and mathml foreign content for raw html review packets' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<aside><svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math></aside>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->contains('<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>', $fragment->serialize());
        $t->contains('<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $fragment->serialize());
        $t->same('Logoxx', $fragment->textContent());
        $t->true(in_array('linearGradient', $summary['elementNames'], true), 'Expected adjusted SVG linearGradient element in summary');
        $t->true(in_array('textPath', $summary['elementNames'], true), 'Expected adjusted SVG textPath element in summary');
        $t->same('svg', $nodes[0]['children'][0]['name']);
        $t->same([
            'viewBox' => '0 0 10 10',
            'preserveAspectRatio' => 'xMidYMid meet',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('linearGradient', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same('definitionURL', array_key_first($nodes[0]['children'][1]['children'][0]['attrs']));
        $t->same([], $summary['blockedTags']);
    },
    'hands normalized HTML fragments to WordPress raw HTML blocks without browser or Pandoc execution' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>');
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->same('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $document->children[0]->attr('html'));
        $t->same('/migration/review-fragment.html', $document->children[0]->attr('part'));
        $t->same([], $document->children[0]->attr('diagnostics'));
    },
];
