<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'loads safe XML documents and preserves namespace attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<pkg xmlns="urn:packet"><item xml:lang="en">Review &amp; Import</item></pkg>',
            'review packet XML'
        );

        $root = $dom->documentElement;
        $item = $dom->getElementsByTagNameNS('urn:packet', 'item')->item(0);

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->true($item instanceof DOMElement);
        $t->same('en', $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $t->same('Review & Import', $item->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><item></pkg>', 'broken XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><pkg>&xxe;</pkg>', 'unsafe XML'));
    },
    'allows XML declarations but rejects XML processing instructions' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg><item>Review packet</item></pkg>',
            'declared review packet XML',
            preserveWhiteSpace: false
        );

        $t->true($dom->documentElement instanceof DOMElement);
        $t->same('pkg', $dom->documentElement->tagName);
        $t->same('Review packet', $dom->documentElement->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg><item>review</item></pkg>',
            'stylesheet XML'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?><item>review</item></pkg>',
            'review PI XML'
        ));
    },
    'recovers HTML5 fragments with list autoclose and void elements' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-id="42">Intro<br>Next<img src="cover.png?x=1&amp;y=2" alt="Cover"></p><ul><li>One<li>Two</ul>',
            'review HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-id' => '42'], $summary[0]['attributes']);
        $t->same('br', $summary[0]['children'][1]['name']);
        $t->same('img', $summary[0]['children'][3]['name']);
        $t->same(['alt' => 'Cover', 'src' => 'cover.png?x=1&y=2'], $summary[0]['children'][3]['attributes']);
        $t->same('ul', $summary[1]['name']);
        $t->same('li', $summary[1]['children'][0]['name']);
        $t->same('One', $summary[1]['children'][0]['text']);
        $t->same('Two', $summary[1]['children'][1]['text']);
        $t->same('<p data-id="42">Intro<br>Next<img alt="Cover" src="cover.png?x=1&amp;y=2"></p><ul><li>One</li><li>Two</li></ul>', $html);
    },
    'serializes entities comments and boolean attributes for HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            'Text&nbsp;<span title="A &quot;quote&quot; &amp; source">source &lt;em&gt;</span><!--review--><input checked>',
            'entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same("Text\u{00A0}", $summary[0]['text']);
        $t->same('span', $summary[1]['name']);
        $t->same(['title' => 'A "quote" & source'], $summary[1]['attributes']);
        $t->same('source <em>', $summary[1]['text']);
        $t->same('comment', $summary[2]['type']);
        $t->same('review', $summary[2]['text']);
        $t->same('input', $summary[3]['name']);
        $t->same(['checked' => 'checked'], $summary[3]['attributes']);
        $t->same("Text\u{00A0}<span title=\"A &quot;quote&quot; &amp; source\">source &lt;em&gt;</span><!--review--><input checked>", $html);
    },
    'normalizes unsafe html comment boundaries before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->',
            'comment boundary HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('comment', $summary[0]['type']);
        $t->same('review-', $summary[0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('comment', $summary[2]['type']);
        $t->same('source -- boundary', $summary[2]['text']);
        $t->same('comment', $summary[3]['type']);
        $t->same('triple---tail-', $summary[3]['text']);
        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
        $t->true(!str_contains($html, 'triple---tail'), 'Expected overlapping comment delimiters to be split before serialization');
    },
    'serializes raw text elements and expanded html5 boolean attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script>'
                . '<style disabled>.legacy > .target::before { content: "&"; }</style>',
            'raw text HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same(['defer' => 'defer', 'src' => 'review.js'], $summary[0]['attributes']);
        $t->same('if (a < b && c > d) { window.review = "&"; }', $summary[0]['text']);
        $t->same('style', $summary[1]['name']);
        $t->same(['disabled' => 'disabled'], $summary[1]['attributes']);
        $t->same('.legacy > .target::before { content: "&"; }', $summary[1]['text']);
        $t->same('<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script><style disabled>.legacy > .target::before { content: "&"; }</style>', $html);
    },
    'serializes detached dom nodes and children for reader handoff' => static function (TestRunner $t): void {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $fragment = $dom->createDocumentFragment();
        $section = $dom->createElement('section');
        $section->setAttribute('hidden', 'hidden');
        $paragraph = $dom->createElement('p');
        $paragraph->appendChild($dom->createTextNode('Detached <text> & notes'));
        $section->appendChild($paragraph);
        $section->appendChild($dom->createElement('br'));
        $section->appendChild($dom->createComment('review -- source'));
        $fragment->appendChild($section);

        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($fragment));
        $t->same('<p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source-->', XmlHtmlDom::serializeHtmlChildren($section));
        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($section));
        $t->same('<!--detached- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached-')));
        $t->same('<!--detached- - -tail- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached---tail-')));
    },
    'preserves svg and mathml foreign content names in deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>',
            'foreign content HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same([
            'preserveAspectRatio' => 'xMidYMid meet',
            'viewBox' => '0 0 10 10',
        ], $summary[0]['attributes']);
        $t->same('linearGradient', $summary[0]['children'][0]['name']);
        $t->same('textPath', $summary[0]['children'][1]['name']);
        $t->same('math', $summary[1]['name']);
        $t->same('definitionURL', array_key_first($summary[1]['children'][0]['attributes']));
        $t->same('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $html);
    },
    'keeps html integration point descendants out of foreign-content casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient data-review="html child">HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>',
            'foreign content integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $foreignObject = $summary[0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $nestedSvg = $foreignDiv['children'][1];
        $mathHtmlAnnotation = $summary[1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $summary[1]['children'][1];

        $t->same('foreignObject', $foreignObject['name']);
        $t->same('div', $foreignDiv['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attributes']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('annotation-xml', $mathHtmlAnnotation['name']);
        $t->same(['encoding' => 'text/html'], $mathHtmlAnnotation['attributes']);
        $t->same('div', $mathHtmlDiv['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attributes']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attributes']);
        $t->same('<svg><foreignObject><div viewbox="html attr"><lineargradient data-review="html child">HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg><math><annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>', $html);
    },
    'serializes html rcdata elements as escaped text not parsed child markup' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>',
            'rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['children'][0]['text']);
        $t->same('title', $summary[1]['name']);
        $t->same('Packet <em>literal</em> & title', $summary[1]['text']);
        $t->same('text', $summary[1]['children'][0]['type']);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $html
        );
    },
    'serializes obsolete html raw text fallback elements as escaped source text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>',
            'obsolete raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('xmp', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['children'][0]['text']);
        $t->same('noembed', $summary[1]['name']);
        $t->same('Fallback <img src=x> & source', $summary[1]['text']);
        $t->same('noframes', $summary[2]['name']);
        $t->same('Frame fallback <a href="/edit">edit</a>', $summary[2]['text']);
        $t->same('<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>', $html);
        $t->true(!str_contains($html, '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
    },
    'treats html plaintext as escaped source text through end of fragment' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>',
            'plaintext review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';

        $t->same(1, count($summary));
        $t->same('plaintext', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same('<plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;</plaintext>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'foster-parents invalid table children before deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>',
            'table foster-parenting review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('p', $summary[0]['name']);
        $t->same('Loose note', $summary[0]['text']);
        $t->same('text', $summary[1]['type']);
        $t->same('orphan text', $summary[1]['text']);
        $t->same('table', $summary[2]['name']);
        $t->same(['class' => 'legacy'], $summary[2]['attributes']);
        $t->same('caption', $summary[2]['children'][0]['name']);
        $t->same('tr', $summary[2]['children'][1]['name']);
        $t->same('tr', $summary[2]['children'][2]['name']);
        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>', $html);
    },
    'hands serialized HTML fragments to WordPress raw HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>',
            'WordPress review fragment'
        );
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>', $html);
        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<aside data-review="source">', $blocks);
        $t->contains('Imported<br>line &amp; reviewer notes', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment("<p>bad\0html</p>", 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!DOCTYPE html><p>bad</p>', 'unsafe HTML fragment'));
    },
    'rejects unsafe HTML fragment declarations before serialization handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment('<p data-review="ok">Safe</p>', 'safe HTML fragment');

        $t->same('<p data-review="ok">Safe</p>', XmlHtmlDom::serializeHtmlFragment($dom));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>', 'unsafe HTML fragment'));
    },
];
