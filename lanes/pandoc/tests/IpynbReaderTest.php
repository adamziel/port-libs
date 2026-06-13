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
        $t->same(true, $document->attr('notebookCellIdsRequired'));
        $t->same(1, $document->attr('notebookCellExecutionCountPresentCount'));
        $t->same(1, $document->attr('notebookCellExecutionCountValidCount'));
        $t->same(0, $document->attr('notebookOutputExecutionCountRecordCount'));
        $t->same(0, $document->attr('notebookOutputExecutionCountMismatchCount'));
        $t->same(0, $document->attr('notebookDiagnosticCount'));
        $t->same([], $document->attr('notebookDiagnostics'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same('compute', $document->attr('notebookCells')[1]['id']);
        $t->same(7, $document->attr('notebookCells')[1]['executionCount']);
        $t->same(0, $document->attr('notebookCells')[1]['diagnosticCount']);

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
        $t->same([], $code->attr('ipynbDiagnostics'));
        $t->same(true, $code->attr('ipynbExecutionCountPresent'));
        $t->same(true, $code->attr('ipynbExecutionCountValid'));
        $t->same(0, $code->attr('ipynbOutputExecutionCountRecordCount'));
        $t->same(0, $code->attr('ipynbOutputExecutionCountMismatchCount'));
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
    'reports bounded ipynb execution metadata diagnostics without executing notebooks' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'execution_count' => 1,
                    'id' => 'intro',
                    'source' => 'Intro',
                ],
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'data' => ['text/plain' => ['missing']],
                            'output_type' => 'execute_result',
                        ],
                    ],
                    'source' => 'missing_id_and_count()',
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => '4',
                    'id' => 'string-count',
                    'outputs' => [
                        [
                            'data' => ['text/plain' => ['4']],
                            'execution_count' => 4,
                            'output_type' => 'execute_result',
                        ],
                    ],
                    'source' => 'string_count()',
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => -1,
                    'id' => 'negative-count',
                    'outputs' => [
                        [
                            'data' => ['text/plain' => ['negative']],
                            'execution_count' => -1,
                            'output_type' => 'execute_result',
                        ],
                    ],
                    'source' => 'negative_count()',
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => 3,
                    'id' => 'mismatch-count',
                    'outputs' => [
                        [
                            'data' => ['text/plain' => ['4']],
                            'execution_count' => 4,
                            'output_type' => 'execute_result',
                        ],
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['done'],
                        ],
                    ],
                    'source' => 'mismatch_count()',
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $t->same(true, $document->attr('notebookCellIdsRequired'));
        $t->same(5, $document->attr('notebookCellCount'));
        $t->same(4, $document->attr('notebookCellExecutionCountPresentCount'));
        $t->same(2, $document->attr('notebookCellExecutionCountValidCount'));
        $t->same(3, $document->attr('notebookOutputExecutionCountRecordCount'));
        $t->same(1, $document->attr('notebookOutputExecutionCountMismatchCount'));
        $t->same(8, $document->attr('notebookDiagnosticCount'));
        $t->same([
            'unexpected-cell-execution-count',
            'missing-cell-id',
            'missing-cell-execution-count',
            'output-execution-count-missing',
            'cell-execution-count-invalid-type',
            'cell-execution-count-out-of-range',
            'output-execution-count-out-of-range',
            'output-execution-count-mismatch',
        ], array_column($document->attr('notebookDiagnostics'), 'issue'));
        $t->same([1, 3, 1, 2, 1], array_column($document->attr('notebookCells'), 'diagnosticCount'));

        $missing = $document->children[1];
        $t->same(3, count($missing->attr('ipynbDiagnostics')));
        $t->same('3', $missing->attr('attributes')['data-ipynb-diagnostic-count']);
        $t->same(false, $missing->attr('ipynbExecutionCountPresent'));
        $t->same(false, $missing->attr('ipynbExecutionCountValid'));
        $t->same('missing', $missing->attr('ipynbExecutionCountType'));

        $stringCount = $document->children[2];
        $t->same('string', $stringCount->attr('ipynbExecutionCountType'));
        $t->same(false, $stringCount->attr('ipynbExecutionCountValid'));
        $t->same(1, $stringCount->attr('ipynbOutputExecutionCountRecordCount'));
        $t->same(4, $stringCount->attr('ipynbOutputExecutionCounts')[0]['executionCount']);

        $negative = $document->children[3];
        $t->same(-1, $negative->attr('ipynbExecutionCount'));
        $t->same(false, $negative->attr('ipynbExecutionCountValid'));
        $t->same([
            'cell-execution-count-out-of-range',
            'output-execution-count-out-of-range',
        ], array_column($negative->attr('ipynbDiagnostics'), 'issue'));

        $mismatch = $document->children[4];
        $t->same('1', $mismatch->attr('attributes')['data-ipynb-output-execution-count-mismatch-count']);
        $t->same(1, $mismatch->attr('ipynbOutputExecutionCountMismatchCount'));
        $t->same(false, $mismatch->attr('ipynbOutputExecutionCounts')[0]['matchesCell']);
        $t->same(3, $mismatch->attr('ipynbDiagnostics')[0]['cellExecutionCount']);
        $t->same(4, $mismatch->attr('ipynbDiagnostics')[0]['outputExecutionCount']);
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
