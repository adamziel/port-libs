<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;
use PortLibs\Pandoc\XmlHtml5Dom;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'recovers declaration-looking quoted attributes in legacy html fragments' => static function (TestRunner $t): void {
        $html = '<section data-note="<!DOCTYPE html><?review href=file?>" data-single=\'<!ENTITY reviewer SYSTEM "file:///etc/passwd">\' data-angle="A > B"><p>safe</p></section>';
        $dom = XmlHtmlDom::loadHtmlFragment($html, 'attribute literal declaration HTML fragment');
        $root = XmlHtmlDom::fragmentRoot($dom);
        $section = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'section') : null;
        $paragraph = $section instanceof DOMElement ? XmlHtmlDom::firstChildElement($section, 'p') : null;
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $serialized = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->true($section instanceof DOMElement, 'Expected section with declaration-looking attributes to parse');
        $t->same([
            'data-angle' => 'A > B',
            'data-note' => '<!DOCTYPE html><?review href=file?>',
            'data-single' => '<!ENTITY reviewer SYSTEM "file:///etc/passwd">',
        ], $section instanceof DOMElement ? XmlHtmlDom::htmlAttributes($section) : []);
        $t->true($paragraph instanceof DOMElement, 'Expected child paragraph to stay parsed');
        $t->same('safe', $paragraph instanceof DOMElement ? XmlHtmlDom::normalizedText($paragraph) : null);
        $t->same('section', $summary[0]['name'] ?? null);
        $t->same('<!DOCTYPE html><?review href=file?>', $summary[0]['attributes']['data-note'] ?? null);
        $t->same('<!ENTITY reviewer SYSTEM "file:///etc/passwd">', $summary[0]['attributes']['data-single'] ?? null);
        $t->same('A > B', $summary[0]['attributes']['data-angle'] ?? null);
        $t->same(
            '<section data-angle="A &gt; B" data-note="&lt;!DOCTYPE html&gt;&lt;?review href=file?&gt;" data-single="&lt;!ENTITY reviewer SYSTEM &quot;file:///etc/passwd&quot;&gt;"><p>safe</p></section>',
            $serialized
        );
        $t->true(!str_contains($serialized, '<!DOCTYPE html>'), 'Expected attribute doctype-looking text to serialize escaped');
        $t->true(!str_contains($serialized, '<?review'), 'Expected attribute processing-instruction-looking text to serialize escaped');
    },
    'routes declaration-looking attribute text through html5 document and facade parsers' => static function (TestRunner $t): void {
        $fragment = '<article data-review="<!DOCTYPE html>" title="<?review href=file?>"><a href="/edit" data-entity="<!ENTITY xxe SYSTEM file>">edit</a></article>';
        $body = Html5Dom::parseHtmlFragment($fragment);
        $article = Html5Dom::firstChildElement($body, 'article');
        $link = $article instanceof DOMElement ? Html5Dom::firstChildElement($article, 'a') : null;
        $facadeBody = XmlHtml5Dom::parseHtmlFragmentBody($fragment);
        $document = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><head><title data-note="<!DOCTYPE html>">Review</title></head><body><main data-pi="<?review href=file?>">safe</main></body></html>'
        );
        $documentBody = $document->getElementsByTagName('body')->item(0);
        $main = $documentBody instanceof DOMElement ? Html5Dom::firstChildElement($documentBody, 'main') : null;

        $t->true($article instanceof DOMElement, 'Expected article with declaration-looking attributes to parse');
        $t->same([
            'data-review' => '<!DOCTYPE html>',
            'title' => '<?review href=file?>',
        ], $article instanceof DOMElement ? Html5Dom::attributes($article) : []);
        $t->true($link instanceof DOMElement, 'Expected nested link to stay parsed');
        $t->same([
            'href' => '/edit',
            'data-entity' => '<!ENTITY xxe SYSTEM file>',
        ], $link instanceof DOMElement ? Html5Dom::attributes($link) : []);
        $t->same(
            '<article data-review="&lt;!DOCTYPE html&gt;" title="&lt;?review href=file?&gt;"><a data-entity="&lt;!ENTITY xxe SYSTEM file&gt;" href="/edit">edit</a></article>',
            Html5Dom::serializeHtmlChildren($body)
        );
        $t->true($facadeBody instanceof DOMElement, 'Expected facade fragment body to parse');
        $t->same(
            '<article data-review="&lt;!DOCTYPE html&gt;" title="&lt;?review href=file?&gt;"><a data-entity="&lt;!ENTITY xxe SYSTEM file&gt;" href="/edit">edit</a></article>',
            $facadeBody instanceof DOMElement ? XmlHtml5Dom::serializeHtmlFragment($facadeBody) : ''
        );
        $t->true($main instanceof DOMElement, 'Expected full HTML document body to parse');
        $t->same(['data-pi' => '<?review href=file?>'], $main instanceof DOMElement ? Html5Dom::attributes($main) : []);
        $t->same('<main data-pi="&lt;?review href=file?&gt;">safe</main>', $documentBody instanceof DOMElement ? Html5Dom::serializeHtmlChildren($documentBody) : '');
    },
    'keeps live declarations rejected outside closed quoted html attributes' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment(
            '<section data-note="safe">ok</section><!DOCTYPE html>',
            'live doctype after quoted attribute fragment'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment(
            '<section title="safe"></section><!ENTITY reviewer SYSTEM "file:///etc/passwd">',
            'live entity after quoted attribute fragment'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment(
            '<section data-note="<?review href=file?><p>unterminated</p>',
            'unterminated attribute processing instruction fragment'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment(
            '<p data-note="safe">ok</p><?review href=file?>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!doctype html><html><body><main data-note="safe">ok</main><!ENTITY reviewer SYSTEM "file:///etc/passwd"></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): ?DOMElement => XmlHtml5Dom::parseHtmlFragmentBody(
            '<article title="safe">ok</article><!DOCTYPE html>'
        ));
    },
];
