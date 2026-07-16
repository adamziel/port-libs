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
    'recovers declaration-looking quoted attributes with angle text in ast html fragments' => static function (TestRunner $t): void {
        $legacy = XmlHtmlDomFragment::parseHtml(
            '<section data-note="<!DOCTYPE html><?review href=file?>" data-single=\'<!ENTITY reviewer SYSTEM "file:///etc/passwd">\' data-angle="A > B"><p>safe</p></section>'
        );
        $legacyRoot = $legacy->children()[0] ?? null;
        $legacyAttrs = is_object($legacyRoot) ? $legacyRoot->attr('attributes', []) : [];
        $legacyHtml = $legacy->serializeHtml();

        $modern = Html5DomFragment::fromHtml(
            '<div data-review="<!DOCTYPE html>" title="<?review href=file?>"><a href="/edit" data-entity="<!ENTITY xxe SYSTEM file>">edit</a><span data-angle="A > B">safe</span></div>'
        );
        $modernNodes = $modern->nodes();
        $div = is_array($modernNodes[0] ?? null) ? $modernNodes[0] : [];
        $divAttrs = is_array($div['attrs'] ?? null) ? $div['attrs'] : [];
        $children = is_array($div['children'] ?? null) ? $div['children'] : [];
        $link = is_array($children[0] ?? null) ? $children[0] : [];
        $linkAttrs = is_array($link['attrs'] ?? null) ? $link['attrs'] : [];
        $span = is_array($children[1] ?? null) ? $children[1] : [];
        $spanAttrs = is_array($span['attrs'] ?? null) ? $span['attrs'] : [];
        $modernHtml = $modern->serialize();

        $t->same('section', is_object($legacyRoot) ? $legacyRoot->attr('name') : null);
        $t->same('<!DOCTYPE html><?review href=file?>', $legacyAttrs['data-note'] ?? null);
        $t->same('<!ENTITY reviewer SYSTEM "file:///etc/passwd">', $legacyAttrs['data-single'] ?? null);
        $t->same('A > B', $legacyAttrs['data-angle'] ?? null);
        $t->same('safe', $legacy->textContent());
        $t->same([], $legacy->diagnostics());
        $t->contains('data-note="&lt;!DOCTYPE html&gt;&lt;?review href=file?&gt;"', $legacyHtml);
        $t->contains('data-single="&lt;!ENTITY reviewer SYSTEM &quot;file:///etc/passwd&quot;&gt;"', $legacyHtml);
        $t->true(!str_contains($legacyHtml, '<!DOCTYPE html>'), 'Expected legacy fragment serialization to escape declaration-looking attributes');

        $t->same('element', $div['type'] ?? null);
        $t->same('div', $div['name'] ?? null);
        $t->same('<!DOCTYPE html>', $divAttrs['data-review'] ?? null);
        $t->same('<?review href=file?>', $divAttrs['title'] ?? null);
        $t->same('<!ENTITY xxe SYSTEM file>', $linkAttrs['data-entity'] ?? null);
        $t->same('A > B', $spanAttrs['data-angle'] ?? null);
        $t->same('editsafe', $modern->textContent());
        $t->same([], $modern->diagnosticCodes());
        $t->contains('data-review="&lt;!DOCTYPE html&gt;"', $modernHtml);
        $t->contains('title="&lt;?review href=file?&gt;"', $modernHtml);
        $t->contains('data-entity="&lt;!ENTITY xxe SYSTEM file&gt;"', $modernHtml);
        $t->true(!str_contains($modernHtml, '<!DOCTYPE html>'), 'Expected html5 fragment serialization to escape declaration-looking attributes');
    },
    'keeps live declarations rejected outside closed ast fragment attributes' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml(
            '<section data-note="safe">ok</section><!DOCTYPE html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml(
            '<section data-note="<?review href=file?><p>unterminated</p>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml(
            '<div title="safe">ok</div><!ENTITY reviewer SYSTEM "file:///etc/passwd">'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml(
            '<div title="<?review href=file?><p>unterminated</p>'
        ));
    },
];
