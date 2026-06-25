<?php

declare(strict_types=1);

use PortLibs\Pandoc\MediaWikiReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads mediawiki article blocks and inline markup for wordpress conversion' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '= Article Title =',
            '',
            "Intro ''emph'' '''strong''' and [[Help|help page]] plus [https://example.test Example] and <math>x^2</math>.",
            "Line with <nowiki>''literal''</nowiki> and {{Infobox|name=Demo}}.",
            '',
            '[[Category:Migration|M]]',
            '',
            '* First',
            '** Nested [[Child Page]]',
            '# Step one',
            '# Step two',
            '',
            '; Term',
            ': Definition with <code>code</code>',
            '',
            '<pre>',
            'plain code',
            '</pre>',
        ]);

        $document = (new MediaWikiReader())->read($source);
        $meta = $document->attr('meta');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, $meta['mediawikiCategoryCount']);
        $t->same(1, $meta['mediawikiTemplateCount']);
        $t->same(0, $meta['mediawikiTableCount']);
        $t->same('heading', $document->children[0]->type);
        $t->same('article-title', $document->children[0]->attr('id'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('bullet_list', $document->children[2]->type);
        $t->same('ordered_list', $document->children[3]->type);
        $t->same('definition_list', $document->children[4]->type);
        $t->same('code_block', $document->children[5]->type);
        $t->contains('<h1 id="article-title">Article Title</h1>', $blocks);
        $t->contains('<em>emph</em> <strong>strong</strong>', $blocks);
        $t->contains('<a href="Help" class="wikilink">help page</a>', $blocks);
        $t->contains('<a href="https://example.test">Example</a>', $blocks);
        $t->contains('<span class="math inline">\\(x^2\\)</span>', $blocks);
        $t->contains('&#039;&#039;literal&#039;&#039;', $blocks);
        $t->contains('<span class="pandoc-raw-mediawiki" data-pandoc-raw-format="mediawiki">{{Infobox|name=Demo}}</span>', $blocks);
        $t->contains('<ul><li>First<ul><li>Nested <a href="Child_Page" class="wikilink">Child Page</a></li></ul></li></ul>', $blocks);
        $t->contains('<ol><li>Step one</li><li>Step two</li></ol>', $blocks);
        $t->contains('<dl><dt>Term</dt><dd>Definition with <code>code</code></dd></dl>', $blocks);
        $t->contains('<pre class="wp-block-code"><code>plain code</code></pre>', $blocks);
    },
    'reads mediawiki tables with captions attributes headers and spans' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '{| id="nordics" class="wikitable" source="wikipedia"',
            "|+ States belonging to the ''Nordics.''",
            '|-',
            '! style="text-align: center;"| Name',
            '! style="text-align: center;"| Capital',
            '|- class="country"',
            '! style="text-align: center;"| Denmark',
            '| style="text-align: left;"| Copenhagen',
            '|-',
            '! colspan="2" style="text-align: left;"| Summary',
            '|}',
        ]);

        $document = PandocConverter::read($source, 'mediawiki');
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $blocks = PandocConverter::convert($source, 'mediawiki', 'blocks');

        $t->same(1, $meta['mediawikiTableCount']);
        $t->same('table', $table->type);
        $t->same('States belonging to the Nordics.', $table->attr('caption'));
        $t->same(['default', 'default'], $table->attr('alignments'));
        $t->same('table_head', $table->children[0]->type);
        $t->same('table_body', $table->children[1]->type);
        $t->same('Denmark', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same(2, $table->children[1]->children[1]->children[0]->attr('colspan'));
        $t->contains('<table id="nordics" class="wikitable" data-mediawiki-source="wikipedia" data-pandoc-source="mediawiki">', $blocks);
        $t->contains('<th style="text-align: center">Name</th><th style="text-align: center">Capital</th>', $blocks);
        $t->contains('<tr class="country"><th style="text-align: center">Denmark</th><td style="text-align: left">Copenhagen</td></tr>', $blocks);
        $t->contains('<th colspan="2" style="text-align: left">Summary</th>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">States belonging to the <em>Nordics.</em></figcaption>', $blocks);
    },
    'routes mediawiki raw templates code tags and figures through the converter' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '{{Infobox',
            '| name = Demo',
            '}}',
            '',
            '<syntaxhighlight lang="ruby" start=100>',
            'puts "hi"',
            '</syntaxhighlight>',
            '',
            "[[File:example.jpg|frameless|30x40px|A ''caption'' with [https://example.test a link]]]",
        ]);

        $document = PandocConverter::read($source, 'mediawiki');
        $blocks = PandocConverter::convert($source, 'mediawiki', 'blocks');

        $t->same('raw_block', $document->children[0]->type);
        $t->same('mediawiki', $document->children[0]->attr('format'));
        $t->same('code_block', $document->children[1]->type);
        $t->same(['ruby'], $document->children[1]->attr('classes'));
        $t->same('figure', $document->children[2]->type);
        $t->same('A caption with a link', $document->children[2]->attr('caption'));
        $t->contains('<pre class="wp-block-code pandoc-raw-mediawiki" data-pandoc-raw-format="mediawiki"><code class="language-mediawiki">{{Infobox', $blocks);
        $t->contains('<pre class="wp-block-code" data-mediawiki-start="100"><code class="language-ruby">puts &quot;hi&quot;</code></pre>', $blocks);
        $t->contains('<figure class="wp-block-image mediawiki-image"><img src="example.jpg" alt="A caption with a link"', $blocks);
        $t->contains('data-pandoc-width="30px"', $blocks);
        $t->contains('data-pandoc-height="40px"', $blocks);
        $t->contains('<figcaption>A <em>caption</em> with <a href="https://example.test">a link</a></figcaption>', $blocks);
    },
];
