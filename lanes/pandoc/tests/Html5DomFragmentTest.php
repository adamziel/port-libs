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
