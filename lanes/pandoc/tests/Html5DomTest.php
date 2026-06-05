<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;

return [
    'parses and serializes bounded HTML5 fragments without wrapper nodes' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<section data-source="wp"><p>AT&amp;T<br>review</p><figure><img src="cover.png" alt="Cover"><figcaption>Cover</figcaption></figure></section>'
        );
        $section = Html5Dom::firstChildElement($body, 'section');
        $figure = $section instanceof DOMElement ? Html5Dom::firstChildElement($section, 'figure') : null;

        $t->true($section instanceof DOMElement, 'Expected section child from HTML fragment body');
        $t->same(['data-source' => 'wp'], Html5Dom::attributes($section));
        $t->true($figure instanceof DOMElement, 'Expected HTML5 figure element to survive DOM parse');
        $t->same('AT&TreviewCover', $section->textContent);
        $t->same('AT&T reviewCover', Html5Dom::normalizedText($section));
        $t->same(
            '<section data-source="wp"><p>AT&amp;T<br>review</p><figure><img alt="Cover" src="cover.png"><figcaption>Cover</figcaption></figure></section>',
            Html5Dom::serializeHtmlChildren($body)
        );
    },
    'decodes HTML entities once and keeps comparison text safe on serialization' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment('<p>AT&amp;T &lt;source&gt; &copy;</p><p>AT&amp;amp;T</p>');
        $paragraphs = Html5Dom::childElements($body, 'p');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same(2, count($paragraphs));
        $t->same('AT&T <source> ©', Html5Dom::normalizedText($paragraphs[0]));
        $t->same('AT&amp;T', Html5Dom::normalizedText($paragraphs[1]));
        $t->contains('<p>AT&amp;T &lt;source&gt; ©</p>', $serialized);
        $t->contains('<p>AT&amp;amp;T</p>', $serialized);
    },
    'maps HTML fragment attributes and descendant elements for reviewer links' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<article id="post-42" class="legacy source" data-source="html" aria-label="Packet"><h1>Packet</h1><p><a href="/edit" title="Edit &amp; verify">edit</a></p></article>'
        );
        $article = Html5Dom::firstChildElement($body, 'article');
        $links = $article instanceof DOMElement ? Html5Dom::descendantElements($article, 'a') : [];

        $t->true($article instanceof DOMElement, 'Expected article child from HTML fragment body');
        $t->same([
            'id' => 'post-42',
            'class' => 'legacy source',
            'data-source' => 'html',
            'aria-label' => 'Packet',
        ], Html5Dom::attributes($article));
        $t->same(1, count($links));
        $t->same('/edit', Html5Dom::attributes($links[0])['href'] ?? null);
        $t->same('Edit & verify', Html5Dom::attributes($links[0])['title'] ?? null);
    },
    'parses complete HTML documents with safe doctypes and rejects DTD or processing inputs' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><head><title>Review</title></head><body><article data-source="export"><h1>Packet</h1><p>Imported<br>line</p></article></body></html>'
        );
        $body = $dom->getElementsByTagName('body')->item(0);
        $article = $body instanceof DOMElement ? Html5Dom::firstChildElement($body, 'article') : null;

        $t->true($dom->documentElement instanceof DOMElement, 'Expected complete HTML document to parse');
        $t->same('html', strtolower($dom->documentElement?->tagName ?? ''));
        $t->true($article instanceof DOMElement, 'Expected article body child from complete HTML document');
        $t->same(['data-source' => 'export'], $article instanceof DOMElement ? Html5Dom::attributes($article) : []);
        $t->same('PacketImported line', $article instanceof DOMElement ? Html5Dom::normalizedText($article) : null);
        $t->same('<article data-source="export"><h1>Packet</h1><p>Imported<br>line</p></article>', $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : '');
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><html><body>&reviewer;</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!ELEMENT html ANY><html><body>bad</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><html><body>bad</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument("<html><body>bad\0packet</body></html>"));
    },
    'rejects external and non-html complete document doctypes before parser loading' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseHtmlDocument('<!DOCTYPE html><html><body><main><p>Review packet</p></main></body></html>');
        $body = $dom->getElementsByTagName('body')->item(0);

        $t->true($body instanceof DOMElement, 'Expected simple HTML doctype document to parse');
        $t->same('<main><p>Review packet</p></main>', $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : '');
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html SYSTEM "file:///etc/passwd"><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://example.invalid/xhtml.dtd"><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE svg><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html><!DOCTYPE html><html><body><p>bad</p></body></html>'
        ));
    },
    'preserves bounded svg and mathml foreign content names for HTML reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<figure><svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math></figure>'
        );
        $figure = Html5Dom::firstChildElement($body, 'figure');
        $svg = $figure instanceof DOMElement ? Html5Dom::firstChildElement($figure, 'svg') : null;
        $gradient = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'linearGradient') : null;
        $textPath = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'textPath') : null;
        $math = $figure instanceof DOMElement ? Html5Dom::firstChildElement($figure, 'math') : null;
        $mi = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'mi') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($svg instanceof DOMElement, 'Expected SVG foreign-content root to survive parsing');
        $t->same([
            'viewBox' => '0 0 10 10',
            'preserveAspectRatio' => 'xMidYMid meet',
        ], Html5Dom::attributes($svg));
        $t->true($gradient instanceof DOMElement, 'Expected adjusted linearGradient lookup to work');
        $t->true($textPath instanceof DOMElement, 'Expected adjusted textPath lookup to work');
        $t->true($mi instanceof DOMElement, 'Expected MathML mi child to survive parsing');
        $t->same(['definitionURL' => '#x'], Html5Dom::attributes($mi));
        $t->contains('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>', $serialized);
        $t->contains('<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $serialized);
    },
    'treats svg foreignObject and math annotation html descendants as html' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="application/xhtml+xml"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $foreignObject = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'foreignObject') : null;
        $foreignDiv = $foreignObject instanceof DOMElement ? Html5Dom::firstChildElement($foreignObject, 'div') : null;
        $htmlGradient = $foreignDiv instanceof DOMElement ? Html5Dom::firstChildElement($foreignDiv, 'lineargradient') : null;
        $nestedSvg = $foreignDiv instanceof DOMElement ? Html5Dom::firstChildElement($foreignDiv, 'svg') : null;
        $nestedGradient = $nestedSvg instanceof DOMElement ? Html5Dom::firstChildElement($nestedSvg, 'linearGradient') : null;
        $math = Html5Dom::firstChildElement($body, 'math');
        $annotations = $math instanceof DOMElement ? Html5Dom::childElements($math, 'annotation-xml') : [];
        $mathHtmlDiv = isset($annotations[0]) ? Html5Dom::firstChildElement($annotations[0], 'div') : null;
        $mathHtmlTextPath = $mathHtmlDiv instanceof DOMElement ? Html5Dom::firstChildElement($mathHtmlDiv, 'textpath') : null;
        $mathCi = isset($annotations[1]) ? Html5Dom::firstChildElement($annotations[1], 'ci') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($foreignObject instanceof DOMElement, 'Expected SVG foreignObject to retain foreign-content casing');
        $t->true($foreignDiv instanceof DOMElement, 'Expected HTML div child inside foreignObject');
        $t->same(['viewbox' => 'html attr'], Html5Dom::attributes($foreignDiv));
        $t->true($htmlGradient instanceof DOMElement, 'Expected HTML child name to stay lowercase inside foreignObject');
        $t->true($nestedGradient instanceof DOMElement, 'Expected nested SVG child to re-enter foreign casing');
        $t->true($mathHtmlDiv instanceof DOMElement, 'Expected MathML annotation HTML child');
        $t->same(['viewbox' => 'math html'], Html5Dom::attributes($mathHtmlDiv));
        $t->true($mathHtmlTextPath instanceof DOMElement, 'Expected HTML descendant in annotation-xml to stay lowercase');
        $t->same(['definitionURL' => '#x'], $mathCi instanceof DOMElement ? Html5Dom::attributes($mathCi) : []);
        $t->contains('<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>', $serialized);
        $t->contains('<annotation-xml encoding="application/xhtml+xml"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>', $serialized);
    },
    'parses html foreign-content cdata sections as text for reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $desc = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'desc') : null;
        $text = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'text') : null;
        $math = Html5Dom::firstChildElement($body, 'math');
        $annotation = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'annotation') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($desc instanceof DOMElement, 'Expected SVG desc CDATA container to survive parsing');
        $t->true($text instanceof DOMElement, 'Expected SVG text CDATA container to survive parsing');
        $t->same('Reviewer <source> & notes', $desc instanceof DOMElement ? Html5Dom::normalizedText($desc) : null);
        $t->same('A < B & C', $text instanceof DOMElement ? Html5Dom::normalizedText($text) : null);
        $t->true($annotation instanceof DOMElement, 'Expected MathML annotation CDATA container to survive parsing');
        $t->same(['encoding' => 'application/x-tex'], $annotation instanceof DOMElement ? Html5Dom::attributes($annotation) : []);
        $t->same('x < y & z', $annotation instanceof DOMElement ? Html5Dom::normalizedText($annotation) : null);
        $t->same('<svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math>', $serialized);
        $t->true(!str_contains($serialized, '<![CDATA['), 'Expected CDATA delimiters to be normalized away before serialization');
        $t->true(!str_contains($serialized, '<source>'), 'Expected CDATA tag-looking source text to stay escaped');
    },
    'treats html title and textarea bodies as rcdata text before dom traversal' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>'
        );
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $title = Html5Dom::firstChildElement($body, 'title');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($textarea instanceof DOMElement, 'Expected textarea review field to survive DOM parsing');
        $t->true($title instanceof DOMElement, 'Expected title element to survive DOM parsing');
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->same('Packet <em>literal</em> & title', $title instanceof DOMElement ? $title->textContent : null);
        $t->same([], $textarea instanceof DOMElement ? Html5Dom::childElements($textarea) : []);
        $t->same([], $title instanceof DOMElement ? Html5Dom::childElements($title) : []);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $serialized
        );
    },
    'treats obsolete html raw text fallback bodies as literal source text' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>'
        );
        $xmp = Html5Dom::firstChildElement($body, 'xmp');
        $noembed = Html5Dom::firstChildElement($body, 'noembed');
        $noframes = Html5Dom::firstChildElement($body, 'noframes');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($xmp instanceof DOMElement, 'Expected xmp fallback container to survive DOM parsing');
        $t->true($noembed instanceof DOMElement, 'Expected noembed fallback container to survive DOM parsing');
        $t->true($noframes instanceof DOMElement, 'Expected noframes fallback container to survive DOM parsing');
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $xmp instanceof DOMElement ? $xmp->textContent : null);
        $t->same('Fallback <img src=x> & source', $noembed instanceof DOMElement ? $noembed->textContent : null);
        $t->same('Frame fallback <a href="/edit">edit</a>', $noframes instanceof DOMElement ? $noframes->textContent : null);
        $t->same([], $xmp instanceof DOMElement ? Html5Dom::childElements($xmp) : []);
        $t->same([], $noembed instanceof DOMElement ? Html5Dom::childElements($noembed) : []);
        $t->same([], $noframes instanceof DOMElement ? Html5Dom::childElements($noframes) : []);
        $t->same(
            '<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>',
            $serialized
        );
        $t->true(!str_contains($serialized, '<textarea>'), 'Expected raw text textarea-looking source to serialize as escaped text');
        $t->true(!str_contains($serialized, '<script>alert(1)</script>'), 'Expected tag-looking raw text to serialize as escaped text');
        $t->true(!str_contains($serialized, '<img src=x>'), 'Expected fallback image-looking source text to serialize as escaped text');
    },
    'treats html plaintext as escaped source text without capturing wrapper tags' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<textarea><plaintext>literal</textarea><p>after</p>'
                . '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>hidden</p>'
        );
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $plaintext = Html5Dom::firstChildElement($body, 'plaintext');
        $serialized = Html5Dom::serializeHtmlChildren($body);
        $expectedPlaintext = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>hidden</p>';

        $t->true($textarea instanceof DOMElement, 'Expected textarea to stay separate from plaintext handling');
        $t->same('<plaintext>literal', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->true($plaintext instanceof DOMElement, 'Expected plaintext review source to survive DOM parsing');
        $t->same(['data-source' => 'legacy'], $plaintext instanceof DOMElement ? Html5Dom::attributes($plaintext) : []);
        $t->same($expectedPlaintext, $plaintext instanceof DOMElement ? $plaintext->textContent : null);
        $t->same(
            '<textarea>&lt;plaintext&gt;literal</textarea><p>after</p><plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;hidden&lt;/p&gt;</plaintext>',
            $serialized
        );
        $t->true(!str_contains($serialized, '</body>'), 'Expected synthetic wrapper close tags not to leak into plaintext text');
        $t->true(!str_contains($serialized, '<p>hidden</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'serializes invalid table-scope children before the table for html5 reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>'
        );
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>', $serialized);
        $t->true(!str_contains($serialized, '</caption><p>Loose note</p>'), 'Expected loose paragraph to move outside the table');
        $t->true(!str_contains($serialized, '</tr>orphan text<tr>'), 'Expected loose text to move outside the table rows');
    },
    'parses XML fragments with namespaces and serializes multiple root children' => static function (TestRunner $t): void {
        $fragment = Html5Dom::parseXmlFragment(
            '<m:math xmlns:m="urn:math"><m:mi>x</m:mi></m:math><w:t xmlns:w="urn:word" xml:space="preserve"> reviewer text </w:t>'
        );
        $children = Html5Dom::childElements($fragment);
        $wordText = Html5Dom::childElements($fragment, 't', 'urn:word')[0] ?? null;
        $serialized = Html5Dom::serializeXmlChildren($fragment);

        $t->same(2, count($children));
        $t->same('math', $children[0]->localName);
        $t->same('urn:math', $children[0]->namespaceURI);
        $t->true($wordText instanceof DOMElement, 'Expected namespace-filtered Word text child');
        $t->same(['xml:space' => 'preserve'], Html5Dom::attributes($wordText));
        $t->same('x reviewer text', Html5Dom::normalizedText($fragment));
        $t->contains('<m:math xmlns:m="urn:math"><m:mi>x</m:mi></m:math>', $serialized);
        $t->contains('<w:t xmlns:w="urn:word" xml:space="preserve"> reviewer text </w:t>', $serialized);
    },
    'parses XML documents with declarations and rejects processing instruction nodes' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg xmlns="urn:packet"><item>Review packet</item></pkg>',
            'declared XML document'
        );
        $root = $dom->documentElement;

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->same('Review packet', Html5Dom::normalizedText($root));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg/>',
            'stylesheet XML document'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?></pkg>',
            'review PI XML document'
        ));
    },
    'rejects unsafe XML declarations doctypes entities and NUL bytes before parsing' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<?xml version="1.0"?><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!DOCTYPE root><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!ENTITY reviewer SYSTEM "https://example.invalid/reviewer"><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment("review\0packet"));
        $t->throws(RuntimeException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<root><child></root>'));
    },
    'rejects unsafe HTML fragment declarations before parser repair' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment('<p data-review="ok">Safe</p>');

        $t->same('<p data-review="ok">Safe</p>', Html5Dom::serializeHtmlChildren($body));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<!DOCTYPE html><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
    },
];
