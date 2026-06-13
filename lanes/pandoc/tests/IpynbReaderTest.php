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
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same('metadata-only', $document->attr('notebookSchemaByteExposurePolicy'));
        $t->same(0, $document->attr('notebookSchemaDiagnosticCount'));
        $t->same(0, $document->attr('notebookSchemaReview')['diagnosticCount']);
        $t->same(4, $document->attr('notebookSchemaReview')['checkedCellCount']);

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
    'collects bounded ipynb schema diagnostics without exposing attachment or output payload bytes' => static function (TestRunner $t): void {
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'id' => '',
                    'metadata' => 'private-cell-metadata',
                    'attachments' => 'iVBORw0KGgo=',
                    'source' => '# Review',
                ],
                [
                    'cell_type' => 'code',
                    'metadata' => [],
                    'execution_count' => '7',
                    'outputs' => [
                        'payload' => [
                            'output_type' => 'stream',
                            'text' => ['secret-output-payload'],
                        ],
                    ],
                    'source' => 'print("schema")',
                ],
                [
                    'cell_type' => 'diagram',
                    'metadata' => [],
                    'source' => 'unsupported cell source',
                ],
            ],
            'metadata' => 'private-notebook-metadata',
            'nbformat' => '4',
            'nbformat_minor' => '5',
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $review = $document->attr('notebookSchemaReview');

        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same('nbformat-v4-bounded', $review['schema']);
        $t->same(3, $review['checkedCellCount']);
        $t->same(9, $review['diagnosticCount']);
        $t->same(3, $review['notebookDiagnosticCount']);
        $t->same(6, $review['cellDiagnosticCount']);
        $t->same([
            'invalid-nbformat',
            'invalid-nbformat-minor',
            'invalid-notebook-metadata',
            'invalid-cell-metadata',
            'invalid-cell-id',
            'invalid-cell-attachments',
            'invalid-code-execution-count',
            'invalid-code-outputs',
            'unsupported-cell-type',
        ], array_column($review['diagnostics'], 'type'));
        $t->same(9, $document->attr('notebookSchemaDiagnosticCount'));
        $t->same($review['diagnostics'], $document->attr('notebookSchemaDiagnostics'));

        $firstCell = $document->children[0];
        $t->same(3, $firstCell->attr('ipynbCellSchemaDiagnosticCount'));
        $t->same([
            'invalid-cell-metadata',
            'invalid-cell-id',
            'invalid-cell-attachments',
        ], array_column($firstCell->attr('ipynbCellSchemaDiagnostics'), 'type'));

        $secondCell = $document->children[1];
        $t->same(2, $secondCell->attr('ipynbCellSchemaDiagnosticCount'));
        $t->same([
            'invalid-code-execution-count',
            'invalid-code-outputs',
        ], array_column($secondCell->attr('ipynbCellSchemaDiagnostics'), 'type'));

        $thirdCell = $document->children[2];
        $t->same(1, $thirdCell->attr('ipynbCellSchemaDiagnosticCount'));
        $t->same(['unsupported-cell-type'], array_column($thirdCell->attr('ipynbCellSchemaDiagnostics'), 'type'));

        $cells = $document->attr('notebookCells');
        $t->same(3, $cells[0]['schemaDiagnosticCount']);
        $t->same(2, $cells[1]['schemaDiagnosticCount']);
        $t->same(1, $cells[2]['schemaDiagnosticCount']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'iVBORw0KGgo='));
        $t->same(false, str_contains($reviewJson, 'secret-output-payload'));
        $t->same(false, str_contains($reviewJson, 'private-cell-metadata'));
        $t->same(false, str_contains($reviewJson, 'private-notebook-metadata'));
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
