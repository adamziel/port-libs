<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'recovers declaration-looking quoted attributes in html fragment facades' => static function (TestRunner $t): void {
        $html = '<section data-note="<!DOCTYPE html><?review href=file?>" data-single=\'<!ENTITY reviewer SYSTEM "file:///etc/passwd">\'><p>safe</p></section>';
        $normalized = Html5DomFragment::fromHtml($html);
        $nodes = $normalized->nodes();
        $summary = $normalized->summary();

        $t->same('safe', $normalized->textContent());
        $t->same(['libxml-repair'], $normalized->diagnosticCodes());
        $t->same(1, $summary['topLevelNodes']);
        $t->same(['p', 'section'], $summary['elementNames']);
        $t->same([], $summary['filteredAttributes']);
        $t->same('section', $nodes[0]['name'] ?? null);
        $t->same([
            'data-note' => '<!DOCTYPE html><?review href=file?>',
            'data-single' => '<!ENTITY reviewer SYSTEM "file:///etc/passwd">',
        ], $nodes[0]['attrs'] ?? []);
        $t->same(
            '<section data-note="&lt;!DOCTYPE html&gt;&lt;?review href=file?&gt;" data-single="&lt;!ENTITY reviewer SYSTEM &quot;file:///etc/passwd&quot;&gt;"><p>safe</p></section>',
            $normalized->serialize()
        );

        $fragment = XmlHtmlDomFragment::parseHtml($html);
        $root = $fragment->children()[0];

        $t->same('safe', $fragment->textContent());
        $t->same([], $fragment->diagnostics());
        $t->same('section', $root->attr('name'));
        $t->same([
            'data-note' => '<!DOCTYPE html><?review href=file?>',
            'data-single' => '<!ENTITY reviewer SYSTEM "file:///etc/passwd">',
        ], $root->attr('attributes'));
        $t->same(
            '<section data-note="&lt;!DOCTYPE html&gt;&lt;?review href=file?&gt;" data-single="&lt;!ENTITY reviewer SYSTEM &quot;file:///etc/passwd&quot;&gt;"><p>safe</p></section>',
            $fragment->serializeHtml()
        );

        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<section data-note="safe">ok</section><!DOCTYPE html>'));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml('<section data-note="safe">ok</section><?review href=file?>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<section data-note="<?review href=file?><p>unterminated</p>'));
    },
];
