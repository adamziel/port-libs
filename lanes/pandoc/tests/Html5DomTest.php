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
    'rejects unsafe XML declarations doctypes entities and NUL bytes before parsing' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<?xml version="1.0"?><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!DOCTYPE root><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!ENTITY reviewer SYSTEM "https://example.invalid/reviewer"><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment("review\0packet"));
        $t->throws(RuntimeException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<root><child></root>'));
    },
];
