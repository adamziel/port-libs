<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

return [
    'converts markdown to wordpress block markup through registered reader and local block writer alias' => static function (TestRunner $t): void {
        $blocks = PandocConverter::convert(
            "# Converter Demo\n\nA **bold** [link](https://example.test/).",
            'md',
            'blocks'
        );

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<a href="https://example.test/">link</a>', $blocks);
    },
    'round trips markdown through pandoc json and native formats' => static function (TestRunner $t): void {
        $markdown = "# Round Trip\n\n- One\n- Two\n";

        $json = PandocConverter::convert($markdown, 'markdown', 'json');
        $jsonDocument = PandocConverter::read($json, 'json');
        $jsonBlocks = PandocConverter::write($jsonDocument, 'wp');

        $t->contains('"pandoc-api-version"', $json);
        $t->same('heading', $jsonDocument->children[0]->type);
        $t->same('Round Trip', $jsonDocument->children[0]->attr('text'));
        $t->contains('<!-- wp:list -->', $jsonBlocks);

        $native = PandocConverter::write($jsonDocument, 'native', ['standalone' => true]);
        $nativeDocument = PandocConverter::read($native, 'native');
        $nativeBlocks = PandocConverter::write($nativeDocument, 'wordpress');

        $t->contains("Pandoc\n", $native);
        $t->same('heading', $nativeDocument->children[0]->type);
        $t->same('Round Trip', $nativeDocument->children[0]->attr('text'));
        $t->contains('<!-- wp:list -->', $nativeBlocks);
    },
    'uses registry aliases for supported output formats' => static function (TestRunner $t): void {
        $html = PandocConverter::convert('# Alias Demo', 'markdown', 'html5');

        $t->same('markdown', PandocConverter::canonicalInputFormat('md'));
        $t->same('html', PandocConverter::canonicalOutputFormat('html5'));
        $t->contains('<h1', $html);
        $t->contains('Alias Demo', $html);
    },
    'reads html through the dedicated registered reader path' => static function (TestRunner $t): void {
        $document = PandocConverter::read('<html lang="en"><head><title>HTML Dispatch</title></head><body><main><h1>HTML Dispatch</h1><p>Ready.</p></main></body></html>', 'html');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('HTML Dispatch', $meta['title']);
        $t->same('html', $meta['sourceFormat']);
        $t->same(\PortLibs\Pandoc\PandocHtmlTagSoupReader::class, $meta['reader']);
        $t->same('tagsoup-pandoc-html-reader-port', $meta['readerScope']);
        $t->same(\PortLibs\Pandoc\TagSoupParser::class, $meta['htmlTokenizer']);
        $t->same(null, $meta['htmlReaderDelegate'] ?? null);
        $t->same(null, $meta['htmlNativeDivs'] ?? null);
        $t->contains('<h1 id="html-dispatch">HTML Dispatch</h1>', $blocks);
        $t->contains('<p>Ready.</p>', $blocks);
    },
    'passes canonical markdown family formats through converter reader dispatch' => static function (TestRunner $t): void {
        $source = "---\ntitle: Converter Probe\n---\n\n# Head\n";
        $markdown = PandocConverter::read($source, 'markdown');
        $commonmark = PandocConverter::read($source, 'commonmark');
        $strict = PandocConverter::read($source, 'markdown-strict');
        $mmd = PandocConverter::read($source, 'markdown-mmd');

        $t->same('Converter Probe', $markdown->attr('meta')['title'] ?? null);
        $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $markdown->children));
        $t->same(null, $commonmark->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $commonmark->children));
        $t->same(null, $strict->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $strict->children));
        $t->same(null, $mmd->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $mmd->children));
    },
    'converts rtf through the registered reader path' => static function (TestRunner $t): void {
        $blocks = PandocConverter::convert('{\\rtf1\\ansi\\pard RTF {\\b bold} import.\\par}', 'rtf', 'blocks');

        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('<p>RTF <strong>bold</strong> import.</p>', $blocks);
    },
    'converts markdown to plain through the registered plain writer' => static function (TestRunner $t): void {
        $plain = PandocConverter::convert('Plain **writer** output.', 'markdown', 'plain', [
            'writerOptions' => ['columns' => 80],
        ]);

        $t->same('Plain writer output.', $plain);
    },
    'reports supported and unsupported formats from registry state' => static function (TestRunner $t): void {
        $t->true(PandocConverter::canRead('markdown'));
        $t->true(PandocConverter::canRead('bibtex'));
        $t->true(PandocConverter::canRead('biblatex'));
        $t->true(PandocConverter::canRead('csljson'));
        $t->true(PandocConverter::canRead('json'));
        $t->true(PandocConverter::canRead('csv'));
        $t->true(PandocConverter::canRead('endnotexml'));
        $t->true(PandocConverter::canRead('ris'));
        $t->true(PandocConverter::canRead('tsv'));
        $t->true(PandocConverter::canRead('doc'));
        $t->true(PandocConverter::canRead('docbook'));
        $t->true(PandocConverter::canRead('docx'));
        $t->true(PandocConverter::canRead('epub'));
        $t->true(PandocConverter::canRead('fb2'));
        $t->true(PandocConverter::canRead('ipynb'));
        $t->true(PandocConverter::canRead('jira'));
        $t->true(PandocConverter::canRead('odt'));
        $t->true(PandocConverter::canRead('opml'));
        $t->true(PandocConverter::canRead('pptx'));
        $t->true(PandocConverter::canRead('rtf'));
        $t->true(PandocConverter::canRead('pdf'));
        $t->true(PandocConverter::canRead('xml'));
        $t->true(PandocConverter::canRead('jats'));
        $t->true(PandocConverter::canRead('bits'));
        $t->true(PandocConverter::canRead('xlsx'));
        $t->true(PandocConverter::canWrite('blocks'));
        $t->true(PandocConverter::canWrite('native'));
        $t->true(PandocConverter::canWrite('opml'));
        $t->true(PandocConverter::canWrite('epub'));
        $t->true(PandocConverter::canWrite('epub3'));
        $t->true(!PandocConverter::canWrite('epub2'));
        $t->true(!PandocConverter::canWrite('pdf'));
    },
    'converts markdown sections to opml through the registered writer path' => static function (TestRunner $t): void {
        $opml = PandocConverter::convert("# Root\n\nIntro **note**.\n\n## Child\n", 'markdown', 'opml', [
            'writerOptions' => ['standalone' => false],
        ]);

        $t->contains('<outline text="Root" _note="Intro **note**.">', $opml);
        $t->contains('  <outline text="Child">', $opml);
    },
    'fails explicitly for unsupported registry formats' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('unsupported input', 'asciidoc');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::write(new AstNode('document'), 'pdf');
        });
    },
];
