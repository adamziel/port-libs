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
        $t->same('metadata-only', $document->attr('notebookRawMarkdownCellByteExposurePolicy'));
        $t->same(3, $document->attr('notebookRawMarkdownCellDiagnosticCount'));
        $t->same(3, $document->attr('notebookRawMarkdownCellReview')['checkedCellCount']);
        $t->same(3, $document->attr('notebookRawMarkdownCellReview')['diagnosticCount']);

        $intro = $document->children[0];
        $t->same('div', $intro->type);
        $t->same(['ipynb-cell', 'ipynb-markdown-cell'], $intro->attr('classes'));
        $t->same('markdown', $intro->attr('attributes')['data-ipynb-cell-type']);
        $t->same('1', $intro->attr('attributes')['data-ipynb-attachment-count']);
        $t->same(['diagram.png'], $intro->attr('ipynbAttachmentNames'));
        $t->same(1, $intro->attr('ipynbCellSourceDiagnosticCount'));
        $t->same('string-array', $intro->attr('ipynbCellSourceDiagnostics')[0]['sourceShape']);
        $t->same(['tags'], $intro->attr('ipynbCellSourceDiagnostics')[0]['unsafeMetadataKeys']);
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
        $t->same('unsupported-native-conversion-preserved-as-code-block', $document->children[2]->attr('ipynbCellSourceDiagnostics')[0]['conversionVerdict']);

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
    'collects raw and markdown source diagnostics without leaking private source or metadata payloads' => static function (TestRunner $t): void {
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'id' => 'markdown-review',
                    'metadata' => [
                        'trusted' => true,
                        'tags' => ['remove-input'],
                        'private' => ['token' => 'secret-metadata-token'],
                    ],
                    'source' => [
                        "# Secret heading should not appear in diagnostics\n",
                        'private markdown payload secret-markdown-token',
                    ],
                ],
                [
                    'cell_type' => 'raw',
                    'id' => 'raw-review',
                    'metadata' => [
                        'jupyter' => ['source_hidden' => true],
                        'private' => 'secret-raw-metadata',
                    ],
                    'source' => "secret raw payload\nstill private",
                ],
                [
                    'cell_type' => 'code',
                    'metadata' => ['trusted' => true],
                    'execution_count' => null,
                    'outputs' => [],
                    'source' => 'print("not checked by this slice")',
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $review = $document->attr('notebookRawMarkdownCellReview');

        $t->same('raw-markdown-cell-source', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(2, $review['checkedCellCount']);
        $t->same(2, $review['diagnosticCount']);
        $t->same([
            'markdown-cell-source-review',
            'raw-cell-source-review',
        ], array_column($review['diagnostics'], 'type'));
        $t->same(2, $document->attr('notebookRawMarkdownCellDiagnosticCount'));
        $t->same($review['diagnostics'], $document->attr('notebookRawMarkdownCellDiagnostics'));

        $markdownDiagnostic = $review['diagnostics'][0];
        $t->same(0, $markdownDiagnostic['cellIndex']);
        $t->same('markdown', $markdownDiagnostic['cellType']);
        $t->same('string-array', $markdownDiagnostic['sourceShape']);
        $t->same(2, $markdownDiagnostic['sourceLineCount']);
        $t->same(false, $markdownDiagnostic['sourcePayloadIncluded']);
        $t->same('keys-only', $markdownDiagnostic['metadataPolicy']);
        $t->same(3, $markdownDiagnostic['metadataKeyCount']);
        $t->same(['tags', 'trusted'], $markdownDiagnostic['unsafeMetadataKeys']);
        $t->same(true, $markdownDiagnostic['conversionSupported']);
        $t->same('parsed-as-native-markdown-blocks', $markdownDiagnostic['conversionVerdict']);

        $rawDiagnostic = $review['diagnostics'][1];
        $t->same(1, $rawDiagnostic['cellIndex']);
        $t->same('raw', $rawDiagnostic['cellType']);
        $t->same('string', $rawDiagnostic['sourceShape']);
        $t->same(2, $rawDiagnostic['sourceLineCount']);
        $t->same(false, $rawDiagnostic['sourcePayloadIncluded']);
        $t->same('keys-only', $rawDiagnostic['metadataPolicy']);
        $t->same(2, $rawDiagnostic['metadataKeyCount']);
        $t->same(['jupyter'], $rawDiagnostic['unsafeMetadataKeys']);
        $t->same(false, $rawDiagnostic['conversionSupported']);
        $t->same('unsupported-native-conversion-preserved-as-code-block', $rawDiagnostic['conversionVerdict']);

        $markdownCell = $document->children[0];
        $rawCell = $document->children[1];
        $codeCell = $document->children[2];
        $t->same(1, $markdownCell->attr('ipynbCellSourceDiagnosticCount'));
        $t->same([$markdownDiagnostic], $markdownCell->attr('ipynbCellSourceDiagnostics'));
        $t->same(1, $rawCell->attr('ipynbCellSourceDiagnosticCount'));
        $t->same([$rawDiagnostic], $rawCell->attr('ipynbCellSourceDiagnostics'));
        $t->same(null, $codeCell->attr('ipynbCellSourceDiagnostics'));

        $cells = $document->attr('notebookCells');
        $t->same(1, $cells[0]['sourceDiagnosticCount']);
        $t->same(1, $cells[1]['sourceDiagnosticCount']);
        $t->same(false, array_key_exists('sourceDiagnosticCount', $cells[2]));

        $diagnosticsJson = json_encode([
            $review,
            $markdownCell->attr('ipynbCellSourceDiagnostics'),
            $rawCell->attr('ipynbCellSourceDiagnostics'),
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($diagnosticsJson, 'secret-markdown-token'));
        $t->same(false, str_contains($diagnosticsJson, 'secret raw payload'));
        $t->same(false, str_contains($diagnosticsJson, 'secret-metadata-token'));
        $t->same(false, str_contains($diagnosticsJson, 'secret-raw-metadata'));
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
