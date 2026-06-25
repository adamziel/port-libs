<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\RstReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads rst headings fields inlines references lists and literals' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'Article Title',
            '=============',
            '',
            ':Author: Ada Lovelace',
            ':Date: 1843',
            '',
            'Intro with *emphasis*, **strong**, ``literal``, :code:`role`, `Example <https://example.test>`__, and Target_.',
            '',
            '.. _Target: https://target.test',
            '',
            '* first',
            '* second',
            '',
            '1. step one',
            '2. step two',
            '',
            '::',
            '',
            '   literal block',
        ]);

        $document = (new RstReader())->read($source);
        $meta = $document->attr('meta');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, $meta['rstReferenceCount']);
        $t->same(1, $meta['rstFieldListCount']);
        $t->same(1, $meta['rstCodeBlockCount']);
        $t->same('heading', $document->children[0]->type);
        $t->same('article-title', $document->children[0]->attr('id'));
        $t->same('definition_list', $document->children[1]->type);
        $t->same('paragraph', $document->children[2]->type);
        $t->same('bullet_list', $document->children[3]->type);
        $t->same('ordered_list', $document->children[4]->type);
        $t->same('code_block', $document->children[5]->type);
        $t->contains('<h1 id="article-title">Article Title</h1>', $blocks);
        $t->contains('<dl><dt>Author</dt><dd>Ada Lovelace</dd><dt>Date</dt><dd>1843</dd></dl>', $blocks);
        $t->contains('<em>emphasis</em>, <strong>strong</strong>, <code>literal</code>', $blocks);
        $t->contains('<a href="https://example.test">Example</a>', $blocks);
        $t->contains('<a href="https://target.test">Target</a>', $blocks);
        $t->contains('<ul><li>first</li><li>second</li></ul>', $blocks);
        $t->contains('<ol><li>step one</li><li>step two</li></ol>', $blocks);
        $t->contains('<pre class="wp-block-code"><code>literal block</code></pre>', $blocks);
    },
    'reads rst code and figure directives through the converter' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '.. code:: python',
            '   :number-lines: 34',
            '   :class: sample',
            '',
            '   def func(x):',
            '     return x + 1',
            '',
            '.. figure:: images/chart.png',
            '   :alt: Chart image',
            '   :width: 300px',
            '   :height: 200px',
            '   :class: framed',
            '',
            '   **Caption** text.',
        ]);

        $document = PandocConverter::read($source, 'rst');
        $blocks = PandocConverter::convert($source, 'rst', 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['rstDirectiveCount']);
        $t->same(1, $meta['rstCodeBlockCount']);
        $t->same('code_block', $document->children[0]->type);
        $t->same(['python', 'numberLines', 'sample'], $document->children[0]->attr('classes'));
        $t->same('34', $document->children[0]->attr('htmlAttributes')['data-rst-start-from']);
        $t->same('figure', $document->children[1]->type);
        $t->same('Caption text.', $document->children[1]->attr('caption'));
        $t->contains('<pre class="wp-block-code" data-rst-start-from="34"><code class="language-python">def func(x):', $blocks);
        $t->contains('return x + 1</code></pre>', $blocks);
        $t->contains('<figure class="wp-block-image rst-image framed"><img src="images/chart.png" alt="Caption text."', $blocks);
        $t->contains('data-pandoc-width="300px"', $blocks);
        $t->contains('data-pandoc-height="200px"', $blocks);
        $t->contains('<figcaption><strong>Caption</strong> text.</figcaption>', $blocks);
    },
    'reads rst simple and grid tables into wordpress table blocks' => static function (TestRunner $t): void {
        $simple = implode("\n", [
            '.. table:: Demo *table*.',
            '',
            '   =====  =====',
            '   Name   Value',
            '   =====  =====',
            '   One    1',
            '   Two    2',
            '   =====  =====',
        ]);
        $grid = implode("\n", [
            '+------+-------+',
            '| Left | Right |',
            '+======+=======+',
            '| A    | B     |',
            '+------+-------+',
        ]);

        $simpleDocument = PandocConverter::read($simple, 'rst');
        $gridDocument = PandocConverter::read($grid, 'rst');
        $simpleBlocks = PandocConverter::convert($simple, 'rst', 'blocks');
        $gridBlocks = PandocConverter::convert($grid, 'rst', 'blocks');

        $t->same(1, $simpleDocument->attr('meta')['rstTableCount']);
        $t->same('table', $simpleDocument->children[0]->type);
        $t->same('Demo table.', $simpleDocument->children[0]->attr('caption'));
        $t->same('Name', $simpleDocument->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('One', $simpleDocument->children[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<table data-pandoc-source="rst">', $simpleBlocks);
        $t->contains('<th>Name</th><th>Value</th>', $simpleBlocks);
        $t->contains('<td>One</td><td>1</td>', $simpleBlocks);
        $t->contains('<figcaption class="wp-element-caption">Demo <em>table</em>.</figcaption>', $simpleBlocks);
        $t->same(1, $gridDocument->attr('meta')['rstTableCount']);
        $t->same('table_head', $gridDocument->children[0]->children[0]->type);
        $t->contains('<th>Left</th><th>Right</th>', $gridBlocks);
        $t->contains('<td>A</td><td>B</td>', $gridBlocks);
    },
];
