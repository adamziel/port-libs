<?php

declare(strict_types=1);

use PortLibs\Pandoc\IpynbReader;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps bounded ipynb markdown code raw cells and review metadata without notebook tooling' => static function (TestRunner $t): void {
        $json = file_get_contents(__DIR__ . '/../fixtures/ipynb/rich-package-review.ipynb');
        if ($json === false) {
            throw new RuntimeException('Unable to read ipynb fixture');
        }

        $document = (new IpynbReader())->read($json);
        $html = (new WordPressBlockWriter())->write($document);

        $t->same('document', $document->type);
        $t->same('ipynb', $document->attr('sourceFormat'));
        $t->same(4, $document->attr('notebookCellCount'));
        $t->same(2, $document->attr('notebookMarkdownCellCount'));
        $t->same(1, $document->attr('notebookCodeCellCount'));
        $t->same(1, $document->attr('notebookRawCellCount'));
        $t->same(1, $document->attr('notebookAttachmentCount'));
        $t->same(2, $document->attr('notebookOutputCount'));
        $t->same(4, $document->attr('notebookNbformat'));
        $t->same(5, $document->attr('notebookNbformatMinor'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same('metadata-only-no-payload', $document->attr('notebookAttachmentManifest')['reviewPolicy']);

        $intro = $document->children[0];
        $t->same('div', $intro->type);
        $t->same(['ipynb-cell', 'ipynb-markdown-cell'], $intro->attr('classes'));
        $t->same('markdown', $intro->attr('attributes')['data-ipynb-cell-type']);
        $t->same('1', $intro->attr('attributes')['data-ipynb-attachment-count']);
        $t->same(['diagram.png'], $intro->attr('ipynbAttachmentNames'));
        $t->same('heading', $intro->children[0]->type);
        $t->same('paragraph', $intro->children[1]->type);
        $t->same('Notebook import', $intro->children[0]->attr('text'));
        $t->same('strong', $intro->children[1]->children[1]->type);
        $t->same('link', $intro->children[1]->children[3]->type);

        $code = $document->children[1];
        $source = $code->children[0];
        $t->same('code', $code->attr('ipynbCellType'));
        $t->same(2, $code->attr('ipynbOutputCount'));
        $t->same(['stream', 'display_data'], $code->attr('ipynbOutputTypes'));
        $t->same('code_block', $source->type);
        $t->same(['python', 'ipynb-code-cell-source'], $source->attr('classes'));
        $t->same('7', $source->attr('attributes')['data-ipynb-execution-count']);
        $t->contains('print("ready")', $source->attr('text'));

        $raw = $document->children[2]->children[0];
        $t->same('code_block', $raw->type);
        $t->same(['ipynb-raw-cell-source'], $raw->attr('classes'));
        $t->contains('title: Source notebook', $raw->attr('text'));

        $tasks = $document->children[3]->children[0];
        $t->same('bullet_list', $tasks->type);
        $t->same(true, $tasks->attr('taskList'));

        $t->contains('class="ipynb-cell ipynb-markdown-cell"', $html);
        $t->contains('data-ipynb-attachment-count="1"', $html);
        $t->contains('<h1 id="notebook-import">Notebook import</h1>', $html);
        $t->contains('class="language-python"', $html);
        $t->contains('print(&quot;ready&quot;)', $html);
    },
    'summarizes attachment manifest collisions without exposing payload bytes' => static function (TestRunner $t): void {
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'attachments' => [
                        'media/plot.png?raw=1' => [
                            'image/png' => 'PAYLOAD_ONE_BASE64',
                        ],
                    ],
                    'source' => '![plot](attachment:plot.png)',
                ],
                [
                    'cell_type' => 'markdown',
                    'attachments' => [
                        '..\\plot.png#frag' => [
                            'text/plain' => 'PAYLOAD_TWO_TEXT',
                            'image/png' => 'PAYLOAD_TWO_BASE64',
                        ],
                    ],
                    'source' => 'Review second attachment',
                ],
            ],
            'metadata' => [
                'kernelspec' => [
                    'language' => 'python',
                    'name' => 'python3',
                ],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $manifest = $document->attr('notebookAttachmentManifest');

        $t->same('metadata-only-no-payload', $manifest['reviewPolicy']);
        $t->same('ipynb-attachment-payload-bytes-omitted', $manifest['payloadExposurePolicy']);
        $t->same(2, $manifest['attachmentCount']);
        $t->same(2, $manifest['entryCount']);
        $t->same(1, $manifest['collisionGroupCount']);
        $t->same([
            'ipynb-attachment-query-fragment',
            'ipynb-attachment-backslash-path',
            'ipynb-attachment-parent-segment',
            'ipynb-attachment-safe-name-collision',
        ], $manifest['diagnostics']);
        $t->same(4, $manifest['diagnosticCount']);

        $t->same(['media/plot.png?raw=1', '..\\plot.png#frag'], array_column($manifest['entries'], 'name'));
        $t->same(['plot.png', 'plot.png'], array_column($manifest['entries'], 'safeName'));
        $t->same([['image/png'], ['image/png', 'text/plain']], array_column($manifest['entries'], 'mimeTypes'));
        $t->same(['metadata-only-no-payload', 'metadata-only-no-payload'], array_column($manifest['entries'], 'payloadExposurePolicy'));
        $t->same(['ipynb-attachment-query-fragment'], $manifest['entries'][0]['diagnostics']);
        $t->same([
            'ipynb-attachment-backslash-path',
            'ipynb-attachment-query-fragment',
            'ipynb-attachment-parent-segment',
        ], $manifest['entries'][1]['diagnostics']);

        $collision = $manifest['collisionGroups'][0];
        $t->same('plot.png', $collision['safeName']);
        $t->same('plot.png', $collision['caseFoldKey']);
        $t->same(2, $collision['attachmentCount']);
        $t->same([0, 1], array_column($collision['entries'], 'cellIndex'));

        $t->same(['ipynb-attachment-query-fragment'], $document->children[0]->attr('ipynbAttachmentDiagnostics'));
        $t->same([
            'ipynb-attachment-backslash-path',
            'ipynb-attachment-query-fragment',
            'ipynb-attachment-parent-segment',
        ], $document->children[1]->attr('ipynbAttachmentDiagnostics'));
        $t->same(1, $document->attr('notebookAttachmentCollisionCount'));
        $t->same(4, $document->attr('notebookNbformat'));
        $t->same(5, $document->attr('notebookNbformatMinor'));

        $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_ONE_BASE64'));
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_TWO_BASE64'));
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_TWO_TEXT'));
    },
    'registers ipynb as partial rich package input while output parity stays unsupported' => static function (TestRunner $t): void {
        $inputSupport = PandocFormatRegistry::richPackageInputSupport();
        $outputSupport = PandocFormatRegistry::richPackageOutputSupport();

        $t->same('partial', $inputSupport['ipynb']['status']);
        $t->same(IpynbReader::class, $inputSupport['ipynb']['implementation']);
        $t->same([
            'pptx',
            'xlsx',
        ], PandocFormatRegistry::unsupportedRichPackageInputFormats());

        $t->same('unsupported', $outputSupport['ipynb']['status']);
        $t->same('', $outputSupport['ipynb']['implementation']);
        $t->contains('No native PHP reader or writer is registered', $outputSupport['ipynb']['notes']);
    },
];
