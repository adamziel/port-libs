<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'parses html fragments into normalized dom nodes' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml('<SECTION Data-Post="42"><p>Review &amp; import<br/>now</p><img SRC="/media/hero.jpg" alt="Hero"></SECTION>');
        $root = $fragment->children()[0];
        $paragraph = $root->children[0];
        $image = $root->children[1];

        $t->same('dom_fragment', $fragment->fragment()->type);
        $t->same('html', $fragment->fragment()->attr('format'));
        $t->same(['section', 'p', 'br', 'img'], $fragment->elementNames());
        $t->same('section', $root->attr('name'));
        $t->same(['data-post' => '42'], $root->attr('attributes'));
        $t->same('Review & importnow', $fragment->textContent());
        $t->same('p', $paragraph->attr('name'));
        $t->same('Review & import', $paragraph->children[0]->attr('text'));
        $t->same('br', $paragraph->children[1]->attr('name'));
        $t->same('now', $paragraph->children[2]->attr('text'));
        $t->same(['src' => '/media/hero.jpg', 'alt' => 'Hero'], $image->attr('attributes'));
        $t->same([], $fragment->diagnostics());
    },
    'serializes html with escaped text attributes and html5 void elements' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml('<p title="A &amp; B">A < B & C "quoted"</p><input checked disabled value="publish">');

        $t->same('<p title="A &amp; B">A &lt; B &amp; C "quoted"</p><input checked disabled value="publish">', $fragment->serializeHtml());
        $t->same('<p title="A &amp; B">A &lt; B &amp; C "quoted"</p><input checked disabled value="publish">', $fragment->serialize());
    },
    'serializes newer html5 boolean attributes without valued fallbacks' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml(
            '<section inert data-review="42">'
            . '<video controls disablepictureinpicture disableremoteplayback><source src="/clip.mp4" typemustmatch=""></video>'
            . '<div shadowrootclonable shadowrootcustomelementregistry shadowrootdelegatesfocus shadowrootserializable></div>'
            . '</section>'
        );
        $section = $fragment->children()[0];
        $video = $section->children[0];
        $source = $video->children[0];
        $div = $section->children[1];

        $t->same('<section inert data-review="42"><video controls disablepictureinpicture disableremoteplayback><source src="/clip.mp4" typemustmatch></video><div shadowrootclonable shadowrootcustomelementregistry shadowrootdelegatesfocus shadowrootserializable></div></section>', $fragment->serializeHtml());
        $t->same(['inert' => true, 'data-review' => '42'], $section->attr('attributes'));
        $t->same(['controls' => true, 'disablepictureinpicture' => true, 'disableremoteplayback' => true], $video->attr('attributes'));
        $t->same(['src' => '/clip.mp4', 'typemustmatch' => true], $source->attr('attributes'));
        $t->same([
            'shadowrootclonable' => true,
            'shadowrootcustomelementregistry' => true,
            'shadowrootdelegatesfocus' => true,
            'shadowrootserializable' => true,
        ], $div->attr('attributes'));
        $t->same([], $fragment->diagnostics());
    },
    'applies html parser implicit paragraph close behavior' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml('<p>First legacy paragraph<p>Second legacy paragraph');

        $t->same(['p', 'p'], $fragment->elementNames());
        $t->same('<p>First legacy paragraph</p><p>Second legacy paragraph</p>', $fragment->serializeHtml());
    },
    'drops active html and unsafe attributes with diagnostics' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml(
            '<div onclick="steal()" style="color:red; background:url(javascript:bad)">'
            . '<a href="java&#x0A;script:alert(1)" title="source">source</a>'
            . '<img src="data:text/html;base64,PHNjcmlwdA==" onerror="bad()" alt="bad">'
            . '<script>alert(1)</script>'
            . '</div>'
        );

        $div = $fragment->children()[0];
        $link = $div->children[0];
        $image = $div->children[1];

        $t->same('<div><a title="source">source</a><img alt="bad"></div>', $fragment->serializeHtml());
        $t->same([], $div->attr('attributes'));
        $t->same(['title' => 'source'], $link->attr('attributes'));
        $t->same(['alt' => 'bad'], $image->attr('attributes'));
        $t->same([
            ['code' => 'dropped-event-attribute', 'element' => 'div', 'attribute' => 'onclick'],
            ['code' => 'dropped-unsafe-style', 'element' => 'div', 'attribute' => 'style'],
            ['code' => 'dropped-unsafe-url', 'element' => 'a', 'attribute' => 'href'],
            ['code' => 'dropped-unsafe-url', 'element' => 'img', 'attribute' => 'src'],
            ['code' => 'dropped-event-attribute', 'element' => 'img', 'attribute' => 'onerror'],
            ['code' => 'dropped-active-element', 'element' => 'script'],
        ], $fragment->diagnostics());
    },
    'preserves html comments while serializing surrounding void elements' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml('<p>Before</p><!--source marker--><hr/>');

        $t->same(['p', 'hr'], $fragment->elementNames());
        $t->same('<p>Before</p><!--source marker--><hr>', $fragment->serializeHtml());
    },
    'unwraps inert template content while preserving sanitized html children' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml(
            '<template data-source="review"><p data-review="yes">Template <a href="/note">note</a><script>bad()</script></p></template><p>after</p>'
        );

        $children = $fragment->children();
        $templateParagraph = $children[0];
        $afterParagraph = $children[1];

        $t->same(['p', 'a', 'p'], $fragment->elementNames());
        $t->same('<p data-review="yes">Template <a href="/note">note</a></p><p>after</p>', $fragment->serializeHtml());
        $t->same('Template noteafter', $fragment->textContent());
        $t->same('p', $templateParagraph->attr('name'));
        $t->same(['data-review' => 'yes'], $templateParagraph->attr('attributes'));
        $t->same('a', $templateParagraph->children[1]->attr('name'));
        $t->same(['href' => '/note'], $templateParagraph->children[1]->attr('attributes'));
        $t->same('p', $afterParagraph->attr('name'));
        $t->same([
            ['code' => 'unwrapped-template-element', 'element' => 'template'],
            ['code' => 'dropped-active-element', 'element' => 'script'],
        ], $fragment->diagnostics());
    },
    'parses xml fragments with namespaces and serializes self closing elements' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseXml('<w:p xmlns:w="urn:word"><w:r w:rsidR="001"><w:t>Imported &amp; reviewed</w:t></w:r><w:br/></w:p>');
        $paragraph = $fragment->children()[0];
        $run = $paragraph->children[0];

        $t->same('xml', $fragment->fragment()->attr('format'));
        $t->same(['w:p', 'w:r', 'w:t', 'w:br'], $fragment->elementNames());
        $t->same(['xmlns:w' => 'urn:word'], $paragraph->attr('attributes'));
        $t->same(['w:rsidR' => '001'], $run->attr('attributes'));
        $t->same('Imported & reviewed', $fragment->textContent());
        $t->same('<w:p xmlns:w="urn:word"><w:r w:rsidR="001"><w:t>Imported &amp; reviewed</w:t></w:r><w:br/></w:p>', $fragment->serializeXml());
        $t->same('<w:p xmlns:w="urn:word"><w:r w:rsidR="001"><w:t>Imported &amp; reviewed</w:t></w:r><w:br/></w:p>', $fragment->serialize());
    },
    'rejects xml declarations doctypes entities and malformed xml fragments' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml('<?xml version="1.0"?><root/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml('<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><x>&ext;</x>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><root/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml("<root>bad\0packet</root>"));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml('<root><unclosed></root>'));
    },
    'rejects unsafe html fragment declarations before parser repair' => static function (TestRunner $t): void {
        $safe = XmlHtmlDomFragment::parseHtml(
            '<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> -->'
            . '<p data-source="review">Safe packet</p>'
        );

        $t->same('Safe packet', $safe->textContent());
        $t->same('dom_comment', $safe->children()[0]->type);
        $t->same(' <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> ', $safe->children()[0]->attr('text'));
        $t->same('<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> --><p data-source="review">Safe packet</p>', $safe->serializeHtml());
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml('<!DOCTYPE html><p>bad</p>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
        $t->throws(\InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml("<p>bad\0packet</p>"));
    },
    'preserves declaration-looking text inside xml comments before handoff' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseXml(
            '<!-- <?xml-stylesheet href="https://example.invalid/review.xsl"?> -->'
            . '<root data-source="review">Safe XML packet</root>'
        );

        $t->same('Safe XML packet', $fragment->textContent());
        $t->same('dom_comment', $fragment->children()[0]->type);
        $t->same(' <?xml-stylesheet href="https://example.invalid/review.xsl"?> ', $fragment->children()[0]->attr('text'));
        $t->same('root', $fragment->children()[1]->attr('name'));
        $t->same('<!-- <?xml-stylesheet href="https://example.invalid/review.xsl"?> --><root data-source="review">Safe XML packet</root>', $fragment->serializeXml());
    },
    'serializes xml text attributes and comments with xml escaping' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseXml('<review note="A &amp; B">Use &lt;blocks&gt; "as-is"<!--source marker--></review>');

        $t->same('<review note="A &amp; B">Use &lt;blocks&gt; "as-is"<!--source marker--></review>', $fragment->serializeXml());
        $t->same('Use <blocks> "as-is"', $fragment->textContent());
    },
];
