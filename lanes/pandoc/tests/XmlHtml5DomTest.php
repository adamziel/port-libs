<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtml5Dom;

return [
    'parses html5 fragments with void elements and serializes without wrappers' => static function (TestRunner $t): void {
        $body = XmlHtml5Dom::parseHtmlFragmentBody('<section data-stage="review">Alpha<br>Beta<img src="/media/a.jpg" alt="A &amp; B"></section><hr>');
        $section = $body instanceof DOMElement ? $body->childNodes->item(0) : null;
        $hr = $body instanceof DOMElement ? $body->childNodes->item(1) : null;
        $image = $section instanceof DOMElement ? $section->getElementsByTagName('img')->item(0) : null;

        $t->true($body instanceof DOMElement);
        $t->true($section instanceof DOMElement);
        $t->same('section', strtolower($section->localName));
        $t->same('review', $section->getAttribute('data-stage'));
        $t->true($image instanceof DOMElement);
        $t->same('/media/a.jpg', $image->getAttribute('src'));
        $t->same('A & B', $image->getAttribute('alt'));
        $t->true($hr instanceof DOMElement);
        $t->same('hr', strtolower($hr->localName));
        $t->same(
            '<section data-stage="review">Alpha<br>Beta<img alt="A &amp; B" src="/media/a.jpg"></section><hr>',
            XmlHtml5Dom::serializeHtmlFragment($body)
        );
    },
    'routes legacy facade html fragments through hardened html5 parsing' => static function (TestRunner $t): void {
        $body = XmlHtml5Dom::parseHtmlFragmentBody(
            '<p data-review="refs">A&NoBreak;B &hopf; &copy</p>'
                . '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<template data-source="legacy-template"><p>Template <script>drop()</script> &amp; <b>note</b></p></template>'
                . '<svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient><foreignObject><div viewBox="html"><textPath>HTML fallback</textPath></div></foreignObject></svg>'
        );
        $paragraph = $body instanceof DOMElement ? $body->getElementsByTagName('p')->item(0) : null;
        $textarea = $body instanceof DOMElement ? $body->getElementsByTagName('textarea')->item(0) : null;
        $template = $body instanceof DOMElement ? $body->getElementsByTagName('template')->item(0) : null;
        $svg = $body instanceof DOMElement ? $body->getElementsByTagName('svg')->item(0) : null;
        $serialized = $body instanceof DOMElement ? XmlHtml5Dom::serializeHtmlFragment($body) : '';

        $t->true($body instanceof DOMElement);
        $t->true($paragraph instanceof DOMElement);
        $t->same("A\u{2060}B \u{1D559} ©", $paragraph instanceof DOMElement ? $paragraph->textContent : null);
        $t->true($textarea instanceof DOMElement);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->same(0, $textarea instanceof DOMElement ? $textarea->getElementsByTagName('*')->length : -1);
        $t->true($template instanceof DOMElement);
        $t->same('<p>Template <script>drop()</script> & <b>note</b></p>', $template instanceof DOMElement ? $template->textContent : null);
        $t->same(0, $template instanceof DOMElement ? $template->getElementsByTagName('*')->length : -1);
        $t->true($svg instanceof DOMElement);
        $t->same(
            '<p data-review="refs">A' . "\u{2060}" . 'B ' . "\u{1D559}" . ' ©</p>'
                . '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea>'
                . '<template data-source="legacy-template">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;&lt;/p&gt;</template>'
                . '<svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient><foreignObject><div viewbox="html"><textpath>HTML fallback</textpath></div></foreignObject></svg>',
            $serialized
        );
        $t->true(!str_contains($serialized, '&amp;NoBreak;'), 'Expected extra HTML5 references to decode through the facade');
        $t->true(!str_contains($serialized, '<script>alert(1)</script>'), 'Expected RCDATA source-looking script to stay escaped');
        $t->true(!str_contains($serialized, '<b>note</b>'), 'Expected template source-looking inline markup to stay escaped');
    },
    'routes facade punctuation references through bounded html5 decoding' => static function (TestRunner $t): void {
        $body = XmlHtml5Dom::parseHtmlFragmentBody(
            '<section data-source="legacy-facade">'
            . '<p data-review="Issue&num;42">Review&colon; packet&semi; status&equals;ok &lpar;ready&rpar; &lsqb;A&rsqb;</p>'
            . '<textarea>Field&colon; &lpar;text&rpar;</textarea>'
            . '<script type="application/json">{"literal":"&colon;&semi;"}</script>'
            . '</section>'
        );
        $section = $body instanceof DOMElement ? $body->getElementsByTagName('section')->item(0) : null;
        $paragraph = $section instanceof DOMElement ? $section->getElementsByTagName('p')->item(0) : null;
        $textarea = $section instanceof DOMElement ? $section->getElementsByTagName('textarea')->item(0) : null;
        $script = $section instanceof DOMElement ? $section->getElementsByTagName('script')->item(0) : null;
        $serialized = $body instanceof DOMElement ? XmlHtml5Dom::serializeHtmlFragment($body) : '';

        $t->true($section instanceof DOMElement, 'Expected facade section to parse');
        $t->same('Issue#42', $paragraph instanceof DOMElement ? $paragraph->getAttribute('data-review') : null);
        $t->same('Review: packet; status=ok (ready) [A]', $paragraph instanceof DOMElement ? $paragraph->textContent : null);
        $t->same('Field: (text)', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->same('{"literal":"&colon;&semi;"}', $script instanceof DOMElement ? $script->textContent : null);
        $t->same(
            '<section data-source="legacy-facade"><p data-review="Issue#42">Review: packet; status=ok (ready) [A]</p><textarea>Field: (text)</textarea><script type="application/json">{"literal":"&colon;&semi;"}</script></section>',
            $serialized
        );
        $t->true(!str_contains($serialized, '&amp;colon;'), 'Expected facade text punctuation references to decode');
        $t->contains('{"literal":"&colon;&semi;"}', $serialized);
    },
    'parses full html documents and exposes body plus metadata nodes' => static function (TestRunner $t): void {
        $document = XmlHtml5Dom::parseHtmlDocument(<<<'HTML'
<!doctype html>
<html>
<head><title>Imported Batch</title><meta name="generator" content="pandoc"></head>
<body><h1 class="title">Imported Batch</h1><p>Body</p></body>
</html>
HTML);
        $body = $document instanceof DOMDocument ? XmlHtml5Dom::htmlBody($document) : null;
        $title = $document instanceof DOMDocument ? $document->getElementsByTagName('title')->item(0) : null;
        $heading = $body instanceof DOMElement ? $body->getElementsByTagName('h1')->item(0) : null;

        $t->true($document instanceof DOMDocument);
        $t->true($body instanceof DOMElement);
        $t->true($title instanceof DOMElement);
        $t->same('Imported Batch', trim($title->textContent));
        $t->true($heading instanceof DOMElement);
        $t->same('title', $heading->getAttribute('class'));
        $t->same('<h1 class="title">Imported Batch</h1><p>Body</p>', trim(XmlHtml5Dom::serializeHtmlFragment($body)));
    },
    'parses reader xml without network entities or doctype expansion' => static function (TestRunner $t): void {
        $document = XmlHtml5Dom::parseXmlDocument(
            '<w:document xmlns:w="urn:word"><w:body><w:p>Review</w:p></w:body></w:document>',
            'DOCX document XML'
        );
        $paragraph = $document->getElementsByTagNameNS('urn:word', 'p')->item(0);

        $t->same('document', $document->documentElement?->localName);
        $t->true($paragraph instanceof DOMElement);
        $t->same('Review', $paragraph->textContent);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): DOMDocument => XmlHtml5Dom::parseXmlDocument('<!DOCTYPE r [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><r>&xxe;</r>', 'unsafe XML')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): DOMDocument => XmlHtml5Dom::parseXmlDocument('<root><unclosed></root>', 'malformed XML')
        );
    },
    'rejects unsafe html and xml facade inputs before libxml repair' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMElement => XmlHtml5Dom::parseHtmlFragmentBody('<!DOCTYPE html><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMElement => XmlHtml5Dom::parseHtmlFragmentBody('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMElement => XmlHtml5Dom::parseHtmlFragmentBody('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMDocument => XmlHtml5Dom::parseHtmlDocument('<!DOCTYPE html SYSTEM "file:///etc/passwd"><html><body>bad</body></html>'));
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMDocument => XmlHtml5Dom::parseHtmlDocument("<html><body>bad\0packet</body></html>"));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtml5Dom::parseXmlDocument('<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg/>', 'stylesheet XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtml5Dom::parseXmlDocument("<pkg>bad\0packet</pkg>", 'NUL XML'));
    },
    'keeps markdown html reader paths on the shared html5 fragment loader' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '<p>There should be a hard line break<br>',
            ' here.</p>',
            '<br>Manual reviewer break',
        ]));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
        $t->same("There should be a hard line break\nhere.", $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('linebreak', $document->children[1]->children[0]->type);
        $t->same('Manual reviewer break', $document->children[1]->children[1]->attr('text'));
        $t->contains('<p>There should be a hard line break<br/>here.</p>', $blocks);
        $t->contains('<p><br/>Manual reviewer break</p>', $blocks);
    },
    'keeps docbook table xml parsing on the shared safe xml loader' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'XML'
<informaltable>
<tgroup cols="2">
<thead><row><entry>Field</entry><entry>Count</entry></row></thead>
<tbody><row><entry>Posts</entry><entry>42</entry></row></tbody>
</tgroup>
</informaltable>
XML);
        $blocked = (new MarkdownReader())->read(<<<'XML'
<informaltable>
<!DOCTYPE informaltable [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<tgroup cols="1"><tbody><row><entry>&xxe;</entry></row></tbody></tgroup>
</informaltable>
XML);

        $t->same('table', $document->children[0]->type);
        $t->same('Field', $document->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Posts', $document->children[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $blocked->children[0]->type);
        $t->same(false, str_contains((string) $blocked->children[0]->attr('text'), 'root:'));
    },
];
