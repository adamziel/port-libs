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
            '<section data-stage="review">Alpha<br>Beta<img src="/media/a.jpg" alt="A &amp; B"></section><hr>',
            XmlHtml5Dom::serializeHtmlFragment($body)
        );
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
        $t->same('<h1 class="title">Imported Batch</h1><p>Body</p>', XmlHtml5Dom::serializeHtmlFragment($body));
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
