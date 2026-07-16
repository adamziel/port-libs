<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\IpynbReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\RichPackageUnsupportedFormatRegistry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps bounded ipynb markdown code raw cells and review metadata without notebook tooling' => static function (TestRunner $t): void {
        $json = file_get_contents(__DIR__ . '/../fixtures/ipynb/rich-package-review.ipynb');
        if ($json === false) {
            throw new RuntimeException('Unable to read ipynb fixture');
        }

        $document = (new IpynbReader())->read($json);
        $html = (new WordPressBlockWriter())->write($document);
        $converterHtml = PandocConverter::convert($json, 'ipynb', 'blocks');

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
        $t->same(['text/plain'], $document->attr('notebookOutputMimeTypes'));
        $t->same(2, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(1, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(1, $document->attr('notebookRichOutputUnsupportedCount'));
        $t->same(3, $document->attr('notebookUnsupportedResourceCount'));
        $t->same(['kernelspec', 'language_info'], $document->attr('notebookMetadataKeys'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same('compute', $document->attr('notebookCells')[1]['id']);
        $t->same(7, $document->attr('notebookCells')[1]['executionCount']);
        $t->same(0, $document->attr('notebookCells')[1]['diagnosticCount']);
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'diagnostics' => ['external-notebook-resource-bytes-blocked'],
        ], $document->attr('notebookResourcePolicy'));
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'diagnostics' => ['ipynb-rich-output-bytes-blocked'],
        ], $document->attr('notebookOutputBytePolicy'));
        $t->same('metadata-only', $document->attr('notebookSchemaByteExposurePolicy'));
        $t->same(0, $document->attr('notebookSchemaDiagnosticCount'));
        $t->same(0, $document->attr('notebookSchemaReview')['diagnosticCount']);
        $t->same(4, $document->attr('notebookSchemaReview')['checkedCellCount']);

        $intro = $document->children[0];
        $t->same('div', $intro->type);
        $t->same(['ipynb-cell', 'ipynb-markdown-cell'], $intro->attr('classes'));
        $t->same('markdown', $intro->attr('attributes')['data-ipynb-cell-type']);
        $t->same('1', $intro->attr('attributes')['data-ipynb-attachment-count']);
        $t->same('review', $intro->attr('attributes')['data-ipynb-cell-tags']);
        $t->same('attachment-bytes-blocked', $intro->attr('attributes')['data-ipynb-diagnostics']);
        $t->same(['diagram.png'], $intro->attr('ipynbAttachmentNames'));
        $t->same(['image/png'], $intro->attr('ipynbAttachmentMimeTypes'));
        $t->same(1, $intro->attr('ipynbUnsupportedResourceCount'));
        $t->same(['attachment-bytes-blocked'], $intro->attr('ipynbUnsupportedResourceDiagnostics'));
        $t->same(['tags'], $intro->attr('ipynbCellMetadataKeys'));
        $t->same(['review'], $intro->attr('ipynbCellTags'));
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
        $t->same(['text/plain'], $code->attr('ipynbOutputMimeTypes'));
        $t->same(1, $code->attr('ipynbOutputMimeBundleCount'));
        $t->same(2, $code->attr('ipynbOutputBytePresenceCount'));
        $t->same(['stdout'], $code->attr('ipynbOutputStreamNames'));
        $t->same(1, $code->attr('ipynbRichOutputUnsupportedCount'));
        $t->same('1', $code->attr('attributes')['data-ipynb-rich-output-unsupported-count']);
        $t->same(2, $code->attr('ipynbUnsupportedResourceCount'));
        $t->same([
            'output-bytes-blocked',
            'output-stream-bytes-blocked',
            'output-mime-bundle-metadata-only',
        ], $code->attr('ipynbUnsupportedResourceDiagnostics'));
        $t->same([], $code->attr('ipynbCellMetadataKeys'));
        $t->same([], $code->attr('ipynbCellTags'));
        $t->same('code_block', $source->type);
        $t->same(['python', 'ipynb-code-cell-source'], $source->attr('classes'));
        $t->same('7', $source->attr('attributes')['data-ipynb-execution-count']);
        $t->contains('print("ready")', $source->attr('text'));

        $outputSummaries = $code->attr('ipynbOutputSummaries');
        $t->same('stream', $outputSummaries[0]['type']);
        $t->same('stream', $outputSummaries[0]['outputType']);
        $t->same('stdout', $outputSummaries[0]['streamName']);
        $t->same('present', $outputSummaries[0]['bytePresence']);
        $t->same('blocked', $outputSummaries[0]['byteExposure']);
        $t->same(['output-bytes-blocked', 'output-stream-bytes-blocked'], $outputSummaries[0]['diagnostics']);
        $t->same('display_data', $outputSummaries[1]['type']);
        $t->same(['text/plain'], $outputSummaries[1]['mimeTypes']);
        $t->same(['output-bytes-blocked', 'output-mime-bundle-metadata-only'], $outputSummaries[1]['diagnostics']);

        $cellSummaries = $document->attr('notebookCells');
        $t->same(['image/png'], $cellSummaries[0]['attachmentMimeTypes']);
        $t->same(['attachment-bytes-blocked'], $cellSummaries[0]['diagnostics']);
        $t->same(['review'], $cellSummaries[0]['tags']);
        $t->same(['text/plain'], $cellSummaries[1]['outputMimeTypes']);
        $t->same(2, $cellSummaries[1]['outputBytePresenceCount']);
        $t->same('compute', $cellSummaries[1]['id']);
        $t->same(7, $cellSummaries[1]['executionCount']);
        $t->same(0, $cellSummaries[1]['diagnosticCount']);
        $t->same([
            'output-bytes-blocked',
            'output-stream-bytes-blocked',
            'output-mime-bundle-metadata-only',
        ], $cellSummaries[1]['diagnostics']);

        $raw = $document->children[2]->children[0];
        $t->same('code_block', $raw->type);
        $t->same(['ipynb-raw-cell-source'], $raw->attr('classes'));
        $t->contains('title: Source notebook', $raw->attr('text'));

        $tasks = $document->children[3]->children[0];
        $t->same('bullet_list', $tasks->type);
        $t->same(true, $tasks->attr('taskList'));

        $t->contains('class="wp-block-group ipynb-cell ipynb-markdown-cell"', $html);
        $t->contains('class="wp-block-group ipynb-cell ipynb-markdown-cell"', $converterHtml);
        $t->contains('data-ipynb-attachment-count="1"', $html);
        $t->contains('data-ipynb-cell-tags="review"', $html);
        $t->contains('data-ipynb-diagnostics="attachment-bytes-blocked"', $html);
        $t->contains('data-ipynb-diagnostics="output-bytes-blocked output-stream-bytes-blocked output-mime-bundle-metadata-only"', $html);
        $t->contains('data-ipynb-output-mime-types="text/plain"', $html);
        $t->contains('data-ipynb-output-byte-policy="metadata-only"', $html);
        $t->contains('data-ipynb-output-stream-names="stdout"', $html);
        $t->contains('data-ipynb-rich-output-unsupported-count="1"', $html);
        $t->contains('<h1 id="notebook-import">Notebook import</h1>', $html);
        $t->contains('class="language-python"', $html);
        $t->contains('print(&quot;ready&quot;)', $html);
        $t->same(false, str_contains($html, 'iVBORw0KGgo='));
        $t->same(false, str_contains($html, '<Figure size 640x480>'));
    },
    'reports nbformat version and cells array diagnostics without notebook tooling' => static function (TestRunner $t): void {
        $reader = new IpynbReader();
        $read = static function (array $notebook) use ($reader): AstNode {
            return $reader->read(json_encode($notebook, JSON_THROW_ON_ERROR));
        };
        $types = static function (AstNode $document): array {
            return array_map(
                static fn (array $diagnostic): string => (string) ($diagnostic['type'] ?? ''),
                $document->attr('notebookSchemaDiagnostics')
            );
        };

        $missingCells = $read([
            'metadata' => [
                'kernelspec' => ['name' => 'python3'],
                'language_info' => ['name' => 'python'],
            ],
        ]);
        $t->same(0, $missingCells->attr('notebookCellCount'));
        $t->same([
            'missing-nbformat',
            'missing-nbformat-minor',
            'missing-cells-array',
        ], $types($missingCells));
        $t->same('metadata-only', $missingCells->attr('notebookSchemaByteExposurePolicy'));
        $t->same(3, $missingCells->attr('notebookSchemaReview')['notebookDiagnosticCount']);
        $t->same(0, $missingCells->attr('notebookSchemaReview')['checkedCellCount']);
        $t->same('python3', $missingCells->attr('notebookKernelName'));
        $t->same('python', $missingCells->attr('notebookLanguage'));

        $nonNumeric = $read([
            'nbformat' => 'four',
            'nbformat_minor' => ['minor' => 5],
            'cells' => [],
        ]);
        $t->same([
            'invalid-nbformat',
            'invalid-nbformat-minor',
        ], $types($nonNumeric));
        $t->same('four', $nonNumeric->attr('notebookNbformat'));
        $t->same(['minor' => 5], $nonNumeric->attr('notebookNbformatMinor'));

        $futureMajor = $read([
            'nbformat' => 5,
            'nbformat_minor' => 0,
            'cells' => [],
        ]);
        $t->same(['unsupported-nbformat'], $types($futureMajor));
        $t->same(5, $futureMajor->attr('notebookNbformat'));

        $futureMinor = $read([
            'nbformat' => 4,
            'nbformat_minor' => 99,
            'cells' => [],
        ]);
        $t->same(['future-nbformat-minor'], $types($futureMinor));
        $t->same(99, $futureMinor->attr('notebookNbformatMinor'));

        $invalidMinor = $read([
            'nbformat' => 4,
            'nbformat_minor' => -1,
            'cells' => [],
        ]);
        $t->same(['invalid-nbformat-minor'], $types($invalidMinor));

        $invalidCells = $read([
            'nbformat' => 4,
            'nbformat_minor' => 5,
            'cells' => 'not-a-cell-array',
        ]);
        $t->same(['invalid-cells-array'], $types($invalidCells));
        $t->same(0, $invalidCells->attr('notebookCellCount'));
        $t->same(0, $invalidCells->attr('notebookSchemaReview')['checkedCellCount']);
        $t->same('metadata-only', $invalidCells->attr('notebookSchemaReview')['byteExposurePolicy']);
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
        $t->same(4, $stringCount->attr('ipynbOutputExecutionCountRecords')[0]['executionCount']);

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
        $t->same(false, $mismatch->attr('ipynbOutputExecutionCountRecords')[0]['matchesCell']);
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
    'preserves ipynb metadata keys and unsupported resource diagnostics without exposing resource bytes' => static function (TestRunner $t): void {
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'metadata' => [
                        'tags' => ['beta', '', 'alpha'],
                        'review' => ['owner' => 'qa'],
                    ],
                    'attachments' => [
                        'plot.svg' => [
                            'image/svg+xml' => '<svg><text>hidden</text></svg>',
                        ],
                    ],
                    'source' => 'Attachment cell.',
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [
                        'collapsed' => false,
                    ],
                    'outputs' => [
                        [
                            'output_type' => 'display_data',
                            'data' => [
                                'application/json' => ['points' => [1, 2]],
                                'image/png' => 'iVBORw0KGgo=',
                            ],
                        ],
                        [
                            'output_type' => 'stream',
                            'name' => 'stdout',
                            'text' => 'done',
                        ],
                    ],
                    'source' => 'display(points)',
                ],
            ],
            'metadata' => [
                'custom' => true,
                'kernelspec' => [
                    'language' => 'python',
                    'name' => 'python3',
                ],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $html = (new WordPressBlockWriter())->write($document);

        $t->same(['custom', 'kernelspec'], $document->attr('notebookMetadataKeys'));
        $t->same(3, $document->attr('notebookUnsupportedResourceCount'));
        $t->same('metadata-only', $document->attr('notebookResourcePolicy')['state']);
        $t->same('blocked', $document->attr('notebookResourcePolicy')['byteExposure']);
        $t->same(['external-notebook-resource-bytes-blocked'], $document->attr('notebookResourcePolicy')['diagnostics']);

        $markdown = $document->children[0];
        $t->same(['review', 'tags'], $markdown->attr('ipynbCellMetadataKeys'));
        $t->same(['alpha', 'beta'], $markdown->attr('ipynbCellTags'));
        $t->same(['image/svg+xml'], $markdown->attr('ipynbAttachmentMimeTypes'));
        $t->same(['attachment-bytes-blocked'], $markdown->attr('ipynbUnsupportedResourceDiagnostics'));
        $t->same('alpha beta', $markdown->attr('attributes')['data-ipynb-cell-tags']);

        $code = $document->children[1];
        $t->same(['collapsed'], $code->attr('ipynbCellMetadataKeys'));
        $t->same(['display_data', 'stream'], $code->attr('ipynbOutputTypes'));
        $t->same(['application/json', 'image/png'], $code->attr('ipynbOutputMimeTypes'));
        $t->same([
            'output-bytes-blocked',
            'output-mime-bundle-metadata-only',
            'output-stream-bytes-blocked',
        ], $code->attr('ipynbUnsupportedResourceDiagnostics'));

        $t->contains('data-ipynb-cell-tags="alpha beta"', $html);
        $t->contains('data-ipynb-diagnostics="attachment-bytes-blocked"', $html);
        $t->contains('data-ipynb-diagnostics="output-bytes-blocked output-mime-bundle-metadata-only output-stream-bytes-blocked"', $html);
        $t->contains('display(points)', $html);
        $t->same(false, str_contains($html, '<svg><text>hidden</text></svg>'));
        $t->same(false, str_contains($html, 'iVBORw0KGgo='));
    },
    'reports unsupported rich output verdicts without exposing payload bytes' => static function (TestRunner $t): void {
        $secretPayload = 'SECRET_PAYLOAD_BYTES_MUST_NOT_SURFACE';
        $json = json_encode([
            'cells' => [[
                'cell_type' => 'code',
                'execution_count' => 3,
                'metadata' => [],
                'outputs' => [
                    [
                        'name' => 'stdout',
                        'output_type' => 'stream',
                        'text' => ["summary only\n"],
                    ],
                    [
                        'data' => [
                            'text/plain' => ["<Figure size 640x480>\n"],
                            'text/html' => '<strong>' . $secretPayload . '</strong>',
                            'image/png' => 'iVBORw0KGgo' . $secretPayload,
                        ],
                        'metadata' => [
                            'image/png' => ['width' => 640, 'height' => 480],
                        ],
                        'output_type' => 'display_data',
                    ],
                    [
                        'data' => [
                            'application/json' => ['token' => $secretPayload],
                            'text/plain' => 'application result',
                        ],
                        'execution_count' => 3,
                        'metadata' => [],
                        'output_type' => 'execute_result',
                    ],
                ],
                'source' => 'plot(values)',
            ]],
            'metadata' => [
                'kernelspec' => ['language' => 'python', 'name' => 'python3'],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $html = (new WordPressBlockWriter())->write($document);

        $t->same(3, $document->attr('notebookOutputCount'));
        $t->same(['application/json', 'image/png', 'text/html', 'text/plain'], $document->attr('notebookOutputMimeTypes'));
        $t->same(3, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(2, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(2, $document->attr('notebookRichOutputUnsupportedCount'));

        $diagnostics = $document->attr('notebookOutputDiagnostics');
        $t->same(2, count($diagnostics));
        $t->same('ipynb-rich-output-unsupported', $diagnostics[0]['code']);
        $t->same(0, $diagnostics[0]['cellIndex']);
        $t->same(1, $diagnostics[0]['outputIndex']);
        $t->same('display_data', $diagnostics[0]['outputType']);
        $t->same(['image/png', 'text/html', 'text/plain'], $diagnostics[0]['mimeTypes']);
        $t->same('metadata-only-no-payload-bytes', $diagnostics[0]['payloadPolicy']);
        $t->same(2, $diagnostics[1]['outputIndex']);
        $t->same('execute_result', $diagnostics[1]['outputType']);
        $t->same(['application/json', 'text/plain'], $diagnostics[1]['mimeTypes']);

        $cell = $document->children[0];
        $t->same(['stream', 'display_data', 'execute_result'], $cell->attr('ipynbOutputTypes'));
        $t->same(['application/json', 'image/png', 'text/html', 'text/plain'], $cell->attr('ipynbOutputMimeTypes'));
        $t->same(2, $cell->attr('ipynbOutputMimeBundleCount'));
        $t->same(3, $cell->attr('ipynbOutputBytePresenceCount'));
        $t->same([3], $cell->attr('ipynbOutputExecutionCounts'));
        $t->same(['stdout'], $cell->attr('ipynbOutputStreamNames'));
        $t->same(2, $cell->attr('ipynbRichOutputUnsupportedCount'));
        $t->same('application/json image/png text/html text/plain', $cell->attr('attributes')['data-ipynb-output-mime-types']);
        $t->same('2', $cell->attr('attributes')['data-ipynb-rich-output-unsupported-count']);
        $t->same('metadata-only', $cell->attr('attributes')['data-ipynb-output-byte-policy']);
        $t->same('3', $cell->attr('attributes')['data-ipynb-output-byte-presence-count']);

        $outputSummaries = $cell->attr('ipynbOutputSummaries');
        $t->same('stream', $outputSummaries[0]['outputType']);
        $t->same('stdout', $outputSummaries[0]['streamName']);
        $t->same(1, $outputSummaries[0]['textLineCount']);
        $t->same(['output-bytes-blocked', 'output-stream-bytes-blocked'], $outputSummaries[0]['diagnostics']);
        $t->same('display_data', $outputSummaries[1]['outputType']);
        $t->same('display_data', $outputSummaries[1]['type']);
        $t->same(['image/png', 'text/html', 'text/plain'], $outputSummaries[1]['mimeTypes']);
        $t->same(3, $outputSummaries[1]['mimeCount']);
        $t->same(['image/png'], $outputSummaries[1]['metadataKeys']);
        $t->same(1, $outputSummaries[1]['metadataKeyCount']);
        $t->same('blocked', $outputSummaries[1]['byteExposure']);
        $t->same('ipynb-rich-output-unsupported', $outputSummaries[1]['unsupportedVerdict']);
        $t->same('execute_result', $outputSummaries[2]['outputType']);
        $t->same(3, $outputSummaries[2]['executionCount']);
        $t->same(['application/json', 'text/plain'], $outputSummaries[2]['mimeTypes']);

        $encodedDocument = json_encode($document, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedDocument, $secretPayload));
        $t->same(false, str_contains($encodedDocument, 'iVBORw0KGgo'));
        $t->same(false, str_contains($encodedDocument, '<strong>'));
        $t->same(false, str_contains($html, $secretPayload));
        $t->same(false, str_contains($html, 'iVBORw0KGgo'));
        $t->contains('data-ipynb-output-mime-types="application/json image/png text/html text/plain"', $html);
        $t->contains('data-ipynb-output-byte-policy="metadata-only"', $html);
        $t->contains('data-ipynb-output-byte-presence-count="3"', $html);
        $t->contains('data-ipynb-output-execution-counts="3"', $html);
        $t->contains('data-ipynb-output-stream-names="stdout"', $html);
        $t->contains('data-ipynb-rich-output-unsupported-count="2"', $html);
        $t->contains('plot(values)', $html);
    },
    'summarizes rich ipynb output bundles without extracting embedded bytes' => static function (TestRunner $t): void {
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'execution_count' => 3,
                    'outputs' => [
                        [
                            'output_type' => 'display_data',
                            'data' => [
                                'application/json' => ['points' => [1, 2]],
                                'image/png' => 'iVBORw0KGgo=',
                            ],
                            'metadata' => [
                                'image/png' => ['width' => 320],
                            ],
                        ],
                        [
                            'output_type' => 'execute_result',
                            'execution_count' => 3,
                            'data' => [
                                'text/html' => '<script>hidden output</script>',
                                'text/plain' => ['result payload'],
                            ],
                            'metadata' => [],
                        ],
                        [
                            'output_type' => 'error',
                            'ename' => 'ValueError',
                            'evalue' => 'hidden failure value',
                            'traceback' => [
                                'Traceback hidden frame',
                                'ValueError: hidden failure value',
                            ],
                        ],
                        [
                            'output_type' => 'stream',
                            'name' => 'stderr',
                            'text' => ['hidden stream text'],
                        ],
                    ],
                    'source' => 'run_cell()',
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
        $html = (new WordPressBlockWriter())->write($document);

        $t->same(4, $document->attr('notebookOutputCount'));
        $t->same(4, $document->attr('notebookUnsupportedResourceCount'));
        $t->same(4, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(2, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(2, $document->attr('notebookRichOutputUnsupportedCount'));
        $t->same('metadata-only', $document->attr('notebookOutputBytePolicy')['state']);

        $code = $document->children[0];
        $t->same(['display_data', 'execute_result', 'error', 'stream'], $code->attr('ipynbOutputTypes'));
        $t->same(['application/json', 'image/png', 'text/html', 'text/plain'], $code->attr('ipynbOutputMimeTypes'));
        $t->same(2, $code->attr('ipynbOutputMimeBundleCount'));
        $t->same(4, $code->attr('ipynbOutputBytePresenceCount'));
        $t->same([3], $code->attr('ipynbOutputExecutionCounts'));
        $t->same(['ValueError'], $code->attr('ipynbOutputErrorNames'));
        $t->same(['stderr'], $code->attr('ipynbOutputStreamNames'));

        $outputs = $code->attr('ipynbOutputSummaries');
        $t->same('display_data', $outputs[0]['type']);
        $t->same('display_data', $outputs[0]['outputType']);
        $t->same(['application/json', 'image/png'], $outputs[0]['mimeTypes']);
        $t->same(['image/png'], $outputs[0]['metadataKeys']);
        $t->same('blocked', $outputs[0]['byteExposure']);
        $t->same('ipynb-rich-output-unsupported', $outputs[0]['unsupportedVerdict']);
        $t->same('execute_result', $outputs[1]['type']);
        $t->same(3, $outputs[1]['executionCount']);
        $t->same(['text/html', 'text/plain'], $outputs[1]['mimeTypes']);
        $t->same('ipynb-rich-output-unsupported', $outputs[1]['unsupportedVerdict']);
        $t->same('error', $outputs[2]['type']);
        $t->same('ValueError', $outputs[2]['errorName']);
        $t->same(true, $outputs[2]['errorValuePresent']);
        $t->same(2, $outputs[2]['tracebackLineCount']);
        $t->same([
            'output-bytes-blocked',
            'output-error-metadata-only',
            'output-error-traceback-bytes-blocked',
        ], $outputs[2]['diagnostics']);
        $t->same('stream', $outputs[3]['type']);
        $t->same('stderr', $outputs[3]['streamName']);
        $t->same(['output-bytes-blocked', 'output-stream-bytes-blocked'], $outputs[3]['diagnostics']);

        $encodedDocument = json_encode($document, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedDocument, 'iVBORw0KGgo='));
        $t->same(false, str_contains($encodedDocument, '<script>hidden output</script>'));
        $t->same(false, str_contains($encodedDocument, 'hidden failure value'));
        $t->same(false, str_contains($encodedDocument, 'Traceback hidden frame'));
        $t->same(false, str_contains($encodedDocument, 'hidden stream text'));
        $t->contains('data-ipynb-output-mime-types="application/json image/png text/html text/plain"', $html);
        $t->contains('data-ipynb-output-byte-policy="metadata-only"', $html);
        $t->contains('data-ipynb-output-byte-presence-count="4"', $html);
        $t->contains('data-ipynb-output-execution-counts="3"', $html);
        $t->contains('data-ipynb-output-error-names="ValueError"', $html);
        $t->contains('data-ipynb-output-stream-names="stderr"', $html);
        $t->same(false, str_contains($html, 'iVBORw0KGgo='));
        $t->same(false, str_contains($html, '<script>hidden output</script>'));
        $t->same(false, str_contains($html, 'hidden failure value'));
        $t->same(false, str_contains($html, 'Traceback hidden frame'));
        $t->same(false, str_contains($html, 'hidden stream text'));
    },
    'plans metadata-only ipynb attachment media extraction diagnostics' => static function (TestRunner $t): void {
        $unsafeName = '../private/plot.png?download=1';
        $windowsName = 'C:\\Users\\Ada\\secret.svg';
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'attachments' => [
                        $unsafeName => [
                            'image/png' => 'UNSAFE-PNG-BYTES',
                        ],
                        'plot a.png' => [
                            'image/png' => ['COLLIDING', '-PNG-BYTES'],
                        ],
                        'plot-a.png' => [
                            'image/png' => 'SAFE-PNG-BYTES',
                        ],
                    ],
                    'source' => 'Attachment extraction review.',
                ],
                [
                    'cell_type' => 'markdown',
                    'attachments' => [
                        $windowsName => [
                            'image/svg+xml' => '<svg><text>secret</text></svg>',
                        ],
                    ],
                    'source' => 'Unsafe Windows path review.',
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $html = (new WordPressBlockWriter())->write($document);
        $media = $document->attr('notebookAttachmentMedia');
        $byName = [];
        foreach ($media as $item) {
            $byName[$item['name']] = $item;
        }

        $t->same(4, $document->attr('notebookAttachmentMediaCount'));
        $t->same([
            'attachment-absolute-path',
            'attachment-backslash-path',
            'attachment-bytes-blocked',
            'attachment-media-path-collision',
            'attachment-path-traversal',
            'attachment-query-or-fragment',
            'attachment-safe-path-remapped',
        ], $document->attr('notebookAttachmentMediaDiagnostics'));

        $t->same('ipynb-media/attachment-' . substr(sha1($unsafeName), 0, 12) . '.png', $byName[$unsafeName]['mediaPath']);
        $t->same([
            'attachment-bytes-blocked',
            'attachment-path-traversal',
            'attachment-query-or-fragment',
            'attachment-safe-path-remapped',
        ], $byName[$unsafeName]['diagnostics']);
        $t->same('ipynb-media/plot-a.png', $byName['plot a.png']['mediaPath']);
        $t->same(['attachment-bytes-blocked', 'attachment-safe-path-remapped'], $byName['plot a.png']['diagnostics']);
        $t->same(
            'ipynb-media/plot-a-' . substr(sha1("0\0plot-a.png\0image/png"), 0, 12) . '.png',
            $byName['plot-a.png']['mediaPath']
        );
        $t->same(['attachment-bytes-blocked', 'attachment-media-path-collision'], $byName['plot-a.png']['diagnostics']);
        $t->same('ipynb-media/attachment-' . substr(sha1($windowsName), 0, 12) . '.svg', $byName[$windowsName]['mediaPath']);
        $t->same([
            'attachment-absolute-path',
            'attachment-backslash-path',
            'attachment-bytes-blocked',
            'attachment-safe-path-remapped',
        ], $byName[$windowsName]['diagnostics']);
        $t->same('blocked', $byName['plot-a.png']['byteExposure']);
        $t->same('planned-metadata-only', $byName['plot-a.png']['extractionState']);
        $t->same(['image/png'], $byName['plot a.png']['mimeTypes']);

        $firstCell = $document->children[0];
        $secondCell = $document->children[1];
        $t->same('3', $firstCell->attr('attributes')['data-ipynb-attachment-media-count']);
        $t->same('1', $secondCell->attr('attributes')['data-ipynb-attachment-media-count']);
        $t->same([
            'attachment-bytes-blocked',
            'attachment-media-path-collision',
            'attachment-path-traversal',
            'attachment-query-or-fragment',
            'attachment-safe-path-remapped',
        ], $firstCell->attr('ipynbAttachmentMediaDiagnostics'));
        $t->same([
            'attachment-absolute-path',
            'attachment-backslash-path',
            'attachment-bytes-blocked',
            'attachment-safe-path-remapped',
        ], $secondCell->attr('ipynbAttachmentMediaDiagnostics'));

        $t->contains('data-ipynb-attachment-media-count="3"', $html);
        $t->contains('data-ipynb-attachment-diagnostics="attachment-bytes-blocked attachment-media-path-collision attachment-path-traversal attachment-query-or-fragment attachment-safe-path-remapped"', $html);
        $encodedMedia = json_encode($media, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedMedia, 'UNSAFE-PNG-BYTES'));
        $t->same(false, str_contains($encodedMedia, 'SAFE-PNG-BYTES'));
        $t->same(false, str_contains($encodedMedia, '<svg><text>secret</text></svg>'));
        $t->same(false, str_contains($html, 'UNSAFE-PNG-BYTES'));
        $t->same(false, str_contains($html, '<svg><text>secret</text></svg>'));
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
            'ipynb-attachment-backslash-path',
            'ipynb-attachment-parent-segment',
            'ipynb-attachment-query-fragment',
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
            'ipynb-attachment-parent-segment',
            'ipynb-attachment-query-fragment',
        ], $manifest['entries'][1]['diagnostics']);

        $collision = $manifest['collisionGroups'][0];
        $t->same('plot.png', $collision['safeName']);
        $t->same('plot.png', $collision['caseFoldKey']);
        $t->same(2, $collision['attachmentCount']);
        $t->same([0, 1], array_column($collision['entries'], 'cellIndex'));

        $t->same(['ipynb-attachment-query-fragment'], $document->children[0]->attr('ipynbAttachmentDiagnostics'));
        $t->same([
            'ipynb-attachment-backslash-path',
            'ipynb-attachment-parent-segment',
            'ipynb-attachment-query-fragment',
        ], $document->children[1]->attr('ipynbAttachmentDiagnostics'));
        $t->same(1, $document->attr('notebookAttachmentCollisionCount'));
        $t->same(4, $document->attr('notebookNbformat'));
        $t->same(5, $document->attr('notebookNbformatMinor'));

        $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_ONE_BASE64'));
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_TWO_BASE64'));
        $t->same(false, str_contains($encodedManifest, 'PAYLOAD_TWO_TEXT'));
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
    'reports bounded ipynb source shape and line ending diagnostics without source text' => static function (TestRunner $t): void {
        $lfArraySource = ["alpha\n", "beta\n"];
        $crlfStringSource = "first\r\nsecond";
        $mixedStringSource = "one\r\ntwo\nthree\r";
        $emptyStringSource = '';
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'source' => $lfArraySource,
                ],
                [
                    'cell_type' => 'code',
                    'source' => $crlfStringSource,
                ],
                [
                    'cell_type' => 'raw',
                    'source' => $mixedStringSource,
                ],
                [
                    'cell_type' => 'markdown',
                    'source' => $emptyStringSource,
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $cells = $document->attr('notebookCells');

        $t->same(['list' => 1, 'string' => 3], $document->attr('notebookSourceShapeCounts'));
        $t->same(['crlf' => 1, 'lf' => 1, 'mixed' => 1, 'none' => 1], $document->attr('notebookSourceLineEndingStyles'));
        $t->same(['lf' => 3, 'crlf' => 2, 'cr' => 1], $document->attr('notebookSourceLineEndingCounts'));
        $t->same(2, $document->attr('notebookSourceTrailingNewlineCount'));
        $t->same(1, $document->attr('notebookEmptySourceCount'));

        $t->same('list', $cells[0]['sourceShape']);
        $t->same(2, $cells[0]['sourcePartCount']);
        $t->same(strlen(implode('', $lfArraySource)), $cells[0]['sourceBytes']);
        $t->same(2, $cells[0]['sourceLineCount']);
        $t->same('lf', $cells[0]['sourceLineEnding']);
        $t->same(['lf' => 2, 'crlf' => 0, 'cr' => 0], $cells[0]['sourceLineEndingCounts']);
        $t->same(true, $cells[0]['sourceTrailingNewline']);
        $t->same([
            'source-shape:list',
            'source-parts:2',
            'source-bytes:' . strlen(implode('', $lfArraySource)),
            'source-lines:2',
            'source-line-ending:lf',
            'source-trailing-newline',
        ], $cells[0]['sourceDiagnostics']);

        $t->same('string', $cells[1]['sourceShape']);
        $t->same(1, $cells[1]['sourcePartCount']);
        $t->same(strlen($crlfStringSource), $cells[1]['sourceBytes']);
        $t->same(2, $cells[1]['sourceLineCount']);
        $t->same('crlf', $cells[1]['sourceLineEnding']);
        $t->same(['lf' => 0, 'crlf' => 1, 'cr' => 0], $cells[1]['sourceLineEndingCounts']);
        $t->same(false, $cells[1]['sourceTrailingNewline']);

        $t->same('mixed', $cells[2]['sourceLineEnding']);
        $t->same(['lf' => 1, 'crlf' => 1, 'cr' => 1], $cells[2]['sourceLineEndingCounts']);
        $t->same(3, $cells[2]['sourceLineCount']);
        $t->same(true, $cells[2]['sourceTrailingNewline']);

        $t->same('none', $cells[3]['sourceLineEnding']);
        $t->same(0, $cells[3]['sourceBytes']);
        $t->same(0, $cells[3]['sourceLineCount']);
        $t->same(false, $cells[3]['sourceTrailingNewline']);
        $t->contains('source-empty', implode(',', $cells[3]['sourceDiagnostics']));

        $firstCell = $document->children[0];
        $codeCell = $document->children[1];
        $t->same('list', $firstCell->attr('ipynbSourceShape'));
        $t->same('crlf', $codeCell->attr('ipynbSourceLineEnding'));
        $t->same($crlfStringSource, $codeCell->children[0]->attr('text'));

        $diagnosticJson = json_encode([
            $document->attr('notebookCells'),
            $firstCell->attr('ipynbSourceDiagnostics'),
            $codeCell->attr('ipynbSourceDiagnostics'),
        ], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($diagnosticJson, 'alpha'), 'Source diagnostics should not expose markdown source text');
        $t->true(!str_contains($diagnosticJson, 'first'), 'Source diagnostics should not expose code source text');
        $t->true(!str_contains($diagnosticJson, 'three'), 'Source diagnostics should not expose raw source text');
    },
    'summarizes ipynb cell source shapes digests and duplicate fingerprints without source text' => static function (TestRunner $t): void {
        $repeatedSource = "alpha\r\nbeta\n";
        $whitespaceSource = " \t\r";
        $repeatedFingerprint = 'sha256:' . hash('sha256', $repeatedSource);
        $emptyFingerprint = 'sha256:' . hash('sha256', '');
        $whitespaceFingerprint = 'sha256:' . hash('sha256', $whitespaceSource);

        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'markdown',
                    'source' => $repeatedSource,
                ],
                [
                    'cell_type' => 'code',
                    'source' => [
                        "alpha\r\n",
                        "beta\n",
                    ],
                ],
                [
                    'cell_type' => 'raw',
                ],
                [
                    'cell_type' => 'markdown',
                    'source' => null,
                ],
                [
                    'cell_type' => 'raw',
                    'source' => $whitespaceSource,
                ],
            ],
            'metadata' => [
                'language_info' => [
                    'name' => 'php',
                ],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $cells = $document->attr('notebookCells');
        $summary = $document->attr('notebookSourceSummary');
        $fingerprintCounts = $document->attr('notebookSourceFingerprintCounts');
        $duplicates = $document->attr('notebookDuplicateSourceFingerprints');

        $t->same('string', $cells[0]['sourceShape']);
        $t->same(1, $cells[0]['sourcePartCount']);
        $t->same(strlen($repeatedSource), $cells[0]['sourceBytes']);
        $t->same(2, $cells[0]['sourceLineCount']);
        $t->same(2, $cells[0]['sourceLineEndingCount']);
        $t->same(['lf' => 1, 'crlf' => 1, 'cr' => 0], $cells[0]['sourceLineEndings']);
        $t->same(true, $cells[0]['sourceHasTrailingLineEnding']);
        $t->same(true, $cells[0]['sourceHasMixedLineEndings']);
        $t->same('content', $cells[0]['sourceContentState']);
        $t->same(['algorithm' => 'sha256', 'value' => hash('sha256', $repeatedSource)], $cells[0]['sourceDigest']);
        $t->same($repeatedFingerprint, $cells[0]['sourceFingerprint']);
        $t->same(2, $cells[0]['sourceFingerprintCount']);

        $t->same('list', $cells[1]['sourceShape']);
        $t->same(2, $cells[1]['sourcePartCount']);
        $t->same($repeatedFingerprint, $cells[1]['sourceFingerprint']);
        $t->same(2, $cells[1]['sourceFingerprintCount']);

        $t->same('missing', $cells[2]['sourceShape']);
        $t->same(0, $cells[2]['sourcePartCount']);
        $t->same(0, $cells[2]['sourceBytes']);
        $t->same(0, $cells[2]['sourceLineCount']);
        $t->same('empty', $cells[2]['sourceContentState']);
        $t->same($emptyFingerprint, $cells[2]['sourceFingerprint']);
        $t->same(2, $cells[2]['sourceFingerprintCount']);

        $t->same('null', $cells[3]['sourceShape']);
        $t->same('empty', $cells[3]['sourceContentState']);
        $t->same($emptyFingerprint, $cells[3]['sourceFingerprint']);
        $t->same(2, $cells[3]['sourceFingerprintCount']);

        $t->same('string', $cells[4]['sourceShape']);
        $t->same(strlen($whitespaceSource), $cells[4]['sourceBytes']);
        $t->same(1, $cells[4]['sourceLineCount']);
        $t->same(1, $cells[4]['sourceLineEndingCount']);
        $t->same(['lf' => 0, 'crlf' => 0, 'cr' => 1], $cells[4]['sourceLineEndings']);
        $t->same(true, $cells[4]['sourceHasTrailingLineEnding']);
        $t->same(false, $cells[4]['sourceHasMixedLineEndings']);
        $t->same('whitespace-only', $cells[4]['sourceContentState']);
        $t->same($whitespaceFingerprint, $cells[4]['sourceFingerprint']);
        $t->same(1, $cells[4]['sourceFingerprintCount']);

        $t->same(5, $summary['cellCount']);
        $t->same((strlen($repeatedSource) * 2) + strlen($whitespaceSource), $summary['totalSourceBytes']);
        $t->same(5, $summary['totalSourceLineCount']);
        $t->same(['list' => 1, 'missing' => 1, 'null' => 1, 'string' => 2], $summary['sourceShapeCounts']);
        $t->same(['lf' => 2, 'crlf' => 2, 'cr' => 1], $summary['sourceLineEndingCounts']);
        $t->same(2, $summary['emptySourceCount']);
        $t->same(1, $summary['whitespaceOnlySourceCount']);
        $t->same(2, $summary['contentSourceCount']);
        $t->same(2, $summary['mixedLineEndingSourceCount']);
        $t->same(3, $summary['trailingLineEndingSourceCount']);
        $t->same(3, $summary['uniqueSourceFingerprintCount']);
        $t->same(2, $summary['duplicateSourceFingerprintCount']);
        $t->same(4, $summary['duplicateSourceCellCount']);

        $t->same(2, $fingerprintCounts[$repeatedFingerprint]);
        $t->same(2, $fingerprintCounts[$emptyFingerprint]);
        $t->same(1, $fingerprintCounts[$whitespaceFingerprint]);

        $duplicatesByFingerprint = [];
        foreach ($duplicates as $duplicate) {
            $duplicatesByFingerprint[$duplicate['sourceFingerprint']] = $duplicate;
        }
        $t->same([
            'sourceFingerprint' => $repeatedFingerprint,
            'count' => 2,
            'cellIndexes' => [0, 1],
        ], $duplicatesByFingerprint[$repeatedFingerprint]);
        $t->same([
            'sourceFingerprint' => $emptyFingerprint,
            'count' => 2,
            'cellIndexes' => [2, 3],
        ], $duplicatesByFingerprint[$emptyFingerprint]);

        $metadata = json_encode([
            $cells,
            $summary,
            $fingerprintCounts,
            $duplicates,
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($metadata, 'alpha'));
        $t->same(false, str_contains($metadata, 'beta'));
    },
    'registers ipynb as partial rich package input while output parity stays unsupported' => static function (TestRunner $t): void {
        $inputSupport = PandocFormatRegistry::phpInputSupport();
        $outputSupport = PandocFormatRegistry::phpOutputSupport();
        $inputStatus = RichPackageUnsupportedFormatRegistry::formatStatus('ipynb', 'input');
        $unsupportedRichInputs = array_column(
            RichPackageUnsupportedFormatRegistry::unsupportedDiagnostics('input'),
            'format'
        );

        $t->same('partial', $inputSupport['ipynb']['status']);
        $t->same(IpynbReader::class, $inputSupport['ipynb']['implementation']);
        $t->same('bounded-native-rich-package-input', $inputStatus['state']);
        $t->same('IpynbReader', $inputStatus['component']);
        $t->same(true, $inputStatus['countsAsDirectSupport']);
        $t->same([], $unsupportedRichInputs);

        $t->same('unsupported', $outputSupport['ipynb']['status']);
        $t->same('', $outputSupport['ipynb']['implementation']);
        $t->contains('No native PHP reader or writer is registered', $outputSupport['ipynb']['notes']);
    },
    'reports native ipynb writer unsupported capability reason without notebook tooling' => static function (TestRunner $t): void {
        $inputSupport = PandocFormatRegistry::phpInputSupport()['ipynb'];
        $outputSupport = PandocFormatRegistry::phpOutputSupport()['ipynb'];
        $inputStatus = RichPackageUnsupportedFormatRegistry::formatStatus('ipynb', 'input');
        $outputStatus = RichPackageUnsupportedFormatRegistry::formatStatus('ipynb', 'output');
        $extensionStatus = RichPackageUnsupportedFormatRegistry::extensionStatus('.ipynb');

        $t->same('partial', $inputSupport['status']);
        $t->same(IpynbReader::class, $inputSupport['implementation']);
        $t->same('unsupported', $outputSupport['status']);
        $t->same('', $outputSupport['implementation']);
        $t->same('bounded-native-rich-package-input', $inputStatus['state']);
        $t->same('unsupported-rich-package-output', $outputStatus['state']);
        $t->same(false, $outputStatus['countsAsDirectSupport']);
        $t->same(['ipynb-notebook-writer-core'], $outputStatus['gates']);
        $t->contains('notebook-writer-not-implemented', implode(',', $outputStatus['diagnostics']));
        $t->contains('external-notebook-tooling-disallowed', implode(',', $outputStatus['diagnostics']));
        $t->same(['output'], $extensionStatus['unsupportedDirections']);

        $encodedReport = json_encode([
            'input' => $inputStatus,
            'output' => $outputStatus,
            'extension' => $extensionStatus,
        ], JSON_THROW_ON_ERROR);
        $decodedReport = json_decode($encodedReport, true, 512, JSON_THROW_ON_ERROR);
        $t->same('unsupported-rich-package-output', $decodedReport['output']['state']);
        $t->same(['output'], $decodedReport['extension']['unsupportedDirections']);
    },
];
