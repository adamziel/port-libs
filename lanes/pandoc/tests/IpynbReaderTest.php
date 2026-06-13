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
        $t->same(3, $document->attr('notebookUnsupportedResourceCount'));
        $t->same(2, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(1, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(0, $document->attr('notebookOutputRepeatedMimeBundleKeyCount'));
        $t->same(1, $document->attr('notebookOutputAggregateDiagnosticCount'));
        $t->same(true, $document->attr('notebookCellIdsRequired'));
        $t->same(1, $document->attr('notebookCellExecutionCountPresentCount'));
        $t->same(1, $document->attr('notebookCellExecutionCountValidCount'));
        $t->same(0, $document->attr('notebookOutputExecutionCountRecordCount'));
        $t->same(0, $document->attr('notebookOutputExecutionCountMismatchCount'));
        $t->same(0, $document->attr('notebookDiagnosticCount'));
        $t->same([], $document->attr('notebookDiagnostics'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same('metadata-only', $document->attr('notebookResourcePolicy')['state']);
        $t->same('blocked', $document->attr('notebookOutputBytePolicy')['byteExposure']);
        $t->same('compute', $document->attr('notebookCells')[1]['id']);
        $t->same(7, $document->attr('notebookCells')[1]['executionCount']);
        $t->same(['stream', 'display_data'], $document->attr('notebookCells')[1]['outputOrderTypes']);
        $t->same([0, 1], $document->attr('notebookCells')[1]['outputIndexes']);

        $intro = $document->children[0];
        $t->same('div', $intro->type);
        $t->same(['ipynb-cell', 'ipynb-markdown-cell'], $intro->attr('classes'));
        $t->same('markdown', $intro->attr('attributes')['data-ipynb-cell-type']);
        $t->same('1', $intro->attr('attributes')['data-ipynb-attachment-count']);
        $t->same(['diagram.png'], $intro->attr('ipynbAttachmentNames'));
        $t->same(['image/png'], $intro->attr('ipynbAttachmentMimeTypes'));
        $t->same(['attachment-bytes-blocked'], $intro->attr('ipynbUnsupportedResourceDiagnostics'));
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
        $t->same(['stream', 'display_data'], $code->attr('ipynbOutputOrderTypes'));
        $t->same([0, 1], $code->attr('ipynbOutputIndexes'));
        $t->same(['text/plain'], $code->attr('ipynbOutputMimeTypes'));
        $t->same(1, $code->attr('ipynbOutputMimeBundleCount'));
        $t->same(2, $code->attr('ipynbOutputBytePresenceCount'));
        $t->same([
            'output-bytes-blocked',
            'output-stream-bytes-blocked',
            'output-mime-bundle-metadata-only',
        ], $code->attr('ipynbUnsupportedResourceDiagnostics'));
        $t->same(['mixed-output-display-order'], array_column($code->attr('ipynbOutputAggregateDiagnostics'), 'issue'));
        $t->same(true, $code->attr('ipynbExecutionCountPresent'));
        $t->same(true, $code->attr('ipynbExecutionCountValid'));
        $t->same(0, $code->attr('ipynbOutputExecutionCountRecordCount'));
        $t->same(0, $code->attr('ipynbOutputExecutionCountMismatchCount'));
        $t->same([], $code->attr('ipynbDiagnostics'));
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
        $t->same(false, str_contains($html, '<Figure size 640x480>'));
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
        $t->same(['mixed-output-display-order'], array_column($mismatch->attr('ipynbOutputAggregateDiagnostics'), 'issue'));
    },
    'reports ipynb output display order and repeated mime bundle diagnostics without output bytes' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'execution_count' => 10,
                    'id' => 'display-order',
                    'metadata' => ['tags' => ['review']],
                    'outputs' => [
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['streamed line from output'],
                        ],
                        [
                            'data' => [
                                'image/png' => 'image bytes should stay hidden',
                                'text/plain' => ['plain display bytes'],
                            ],
                            'metadata' => ['transient' => ['display_id' => 'figure-1']],
                            'output_type' => 'display_data',
                        ],
                        [
                            'data' => [
                                'application/json' => ['ok' => true],
                                'text/plain' => ['result bytes'],
                            ],
                            'execution_count' => 9,
                            'metadata' => ['review' => 'kept'],
                            'output_type' => 'execute_result',
                        ],
                        [
                            'ename' => 'ValueError',
                            'evalue' => 'bad value bytes',
                            'output_type' => 'error',
                            'traceback' => ['traceback bytes'],
                        ],
                    ],
                    'source' => 'mixed_outputs()',
                ],
            ],
            'metadata' => [
                'language_info' => ['name' => 'python'],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $cell = $document->children[0];
        $html = (new WordPressBlockWriter())->write($document);
        $serialized = json_encode($document, JSON_THROW_ON_ERROR);

        $t->same(1, $document->attr('notebookCellCount'));
        $t->same(4, $document->attr('notebookOutputCount'));
        $t->same(4, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(2, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedMimeBundleKeyCount'));
        $t->same(2, $document->attr('notebookOutputAggregateDiagnosticCount'));
        $t->same(1, $document->attr('notebookOutputExecutionCountRecordCount'));
        $t->same(1, $document->attr('notebookOutputExecutionCountMismatchCount'));
        $t->same(1, $document->attr('notebookDiagnosticCount'));
        $t->same(['output-execution-count-mismatch'], array_column($document->attr('notebookDiagnostics'), 'issue'));

        $t->same('0 1 2 3', $cell->attr('attributes')['data-ipynb-output-indexes']);
        $t->same('stream display_data execute_result error', $cell->attr('attributes')['data-ipynb-output-display-order']);
        $t->same('text/plain', $cell->attr('attributes')['data-ipynb-output-repeated-mime-keys']);
        $t->same('2', $cell->attr('attributes')['data-ipynb-output-aggregate-diagnostic-count']);
        $t->same('1', $cell->attr('attributes')['data-ipynb-output-execution-count-mismatch-count']);
        $t->same('metadata-only', $cell->attr('attributes')['data-ipynb-output-byte-policy']);
        $t->same('4', $cell->attr('attributes')['data-ipynb-output-byte-presence-count']);

        $t->same(['stream', 'display_data', 'execute_result', 'error'], $cell->attr('ipynbOutputTypes'));
        $t->same(['stream', 'display_data', 'execute_result', 'error'], $cell->attr('ipynbOutputOrderTypes'));
        $t->same([0, 1, 2, 3], $cell->attr('ipynbOutputIndexes'));
        $t->same(['application/json', 'image/png', 'text/plain'], $cell->attr('ipynbOutputMimeTypes'));
        $t->same(['text/plain'], $cell->attr('ipynbOutputRepeatedMimeBundleKeys'));
        $t->same([
            'mimeType' => 'text/plain',
            'outputIndexes' => [1, 2],
            'count' => 2,
        ], $cell->attr('ipynbOutputRepeatedMimeBundleRecords')[0]);
        $t->same([
            'mixed-output-display-order',
            'repeated-output-mime-bundle-key',
        ], array_column($cell->attr('ipynbOutputAggregateDiagnostics'), 'issue'));

        $outputs = $cell->attr('ipynbOutputSummaries');
        $t->same(0, $outputs[0]['index']);
        $t->same('stream', $outputs[0]['type']);
        $t->same('stdout', $outputs[0]['streamName']);
        $t->same('blocked', $outputs[0]['byteExposure']);
        $t->same(1, $outputs[1]['index']);
        $t->same(['image/png', 'text/plain'], $outputs[1]['mimeTypes']);
        $t->same(['transient'], $outputs[1]['metadataKeys']);
        $t->same(2, $outputs[2]['index']);
        $t->same(['application/json', 'text/plain'], $outputs[2]['mimeTypes']);
        $t->same(9, $outputs[2]['executionCount']);
        $t->same(false, $outputs[2]['executionCountRecord']['matchesCell']);
        $t->same(3, $outputs[3]['index']);
        $t->same('ValueError', $outputs[3]['errorName']);
        $t->same(true, $outputs[3]['errorValuePresent']);
        $t->same(1, $outputs[3]['tracebackLineCount']);

        $t->same(['output-execution-count-mismatch'], array_column($cell->attr('ipynbDiagnostics'), 'issue'));
        $t->same(10, $cell->attr('ipynbDiagnostics')[0]['cellExecutionCount']);
        $t->same(9, $cell->attr('ipynbDiagnostics')[0]['outputExecutionCount']);
        $t->same(['review'], $cell->attr('ipynbCellTags'));
        $t->contains('mixed_outputs()', $html);

        foreach ([
            'streamed line from output',
            'image bytes should stay hidden',
            'plain display bytes',
            'result bytes',
            'bad value bytes',
            'traceback bytes',
        ] as $forbidden) {
            $t->same(false, str_contains($serialized, $forbidden), "Serialized AST leaked {$forbidden}");
            $t->same(false, str_contains($html, $forbidden), "WordPress HTML leaked {$forbidden}");
        }
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
