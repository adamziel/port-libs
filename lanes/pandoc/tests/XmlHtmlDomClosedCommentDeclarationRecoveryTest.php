<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'ignores closed comment declaration text during xml html dom recovery' => static function (TestRunner $t): void {
        $topComment = ' <!DOCTYPE html><!ENTITY top SYSTEM "file:///etc/passwd"> ';
        $templateSource = '<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"><?xml-stylesheet href="https://example.invalid/review.xsl"?> -->'
            . '<p>Before</p>'
            . '<script>const sentinel = "</template>";</script>'
            . '<style>.x:before{content:"</template>"}</style>'
            . '<p>Tail</p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!--' . $topComment . '--><template id="outer">' . $templateSource . '</template><p>after</p>',
            'closed comment declaration HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $serialized = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(3, count($summary));
        $t->same('comment', $summary[0]['type'] ?? null);
        $t->same($topComment, $summary[0]['text'] ?? null);
        $t->same('template', $summary[1]['name'] ?? null);
        $t->same(['id' => 'outer'], $summary[1]['attributes'] ?? null);
        $t->same($templateSource, $summary[1]['text'] ?? null);
        $t->same('inert-source', $summary[1]['template'] ?? null);
        $t->same('template-inert-escaped-source', $summary[1]['templateReviewPolicy'] ?? null);
        $t->same(true, $summary[1]['templateContainsMarkupLikeText'] ?? null);
        $t->same(true, $summary[1]['templateContainsActiveLikeText'] ?? null);
        $t->same(true, $summary[1]['templateContentParsed'] ?? null);
        $t->same([], $summary[1]['templateContentDiagnostics'] ?? null);
        $t->same(['p', 'script', 'style', 'p'], $summary[1]['templateContentTopLevelElementNames'] ?? null);
        $t->same(['script', 'style'], $summary[1]['templateContentActiveElementNames'] ?? null);
        $t->same(
            'Beforeconst sentinel = "</template>";.x:before{content:"</template>"}Tail',
            $summary[1]['templateContentText'] ?? null
        );
        $t->same('p', $summary[2]['name'] ?? null);
        $t->same('after', $summary[2]['text'] ?? null);
        $t->contains('<!--' . $topComment . '-->', $serialized);
        $t->contains('&lt;?xml-stylesheet href="https://example.invalid/review.xsl"?&gt;', $serialized);
        $t->contains('const sentinel = "&lt;/template&gt;";', $serialized);
        $t->contains('<p>after</p>', $serialized);
        $t->true(!str_contains($serialized, '<script>const sentinel'), 'Expected template script source to stay escaped');
        $t->true(!str_contains($serialized, '<style>.x'), 'Expected template style source to stay escaped');

        $xml = XmlHtmlDom::loadXmlDocument(
            '<!-- <!DOCTYPE pkg><!ENTITY reviewer SYSTEM "file:///etc/passwd"> --><pkg><item>safe</item></pkg>',
            'closed comment declaration XML document',
            preserveWhiteSpace: false
        );
        $root = $xml->documentElement;
        $item = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'item') : null;

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root instanceof DOMElement ? $root->tagName : null);
        $t->true($item instanceof DOMElement);
        $t->same('safe', $item instanceof DOMElement ? $item->textContent : null);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p>before</p><!DOCTYPE html>', 'live doctype HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>bad</p>', 'live entity HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>', 'live PI HTML fragment'));
        $liveDoctype = XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg><pkg/>', 'live doctype XML document');
        $t->same('pkg', $liveDoctype->documentElement instanceof DOMElement ? $liveDoctype->documentElement->tagName : null);
    },
];
