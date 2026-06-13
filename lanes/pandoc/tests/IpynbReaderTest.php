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
        $t->same(['text/plain'], $document->attr('notebookOutputMimeTypes'));
        $t->same(1, $document->attr('notebookRichOutputUnsupportedCount'));
        $t->same(3, $document->attr('notebookUnsupportedResourceCount'));
        $t->same(['kernelspec', 'language_info'], $document->attr('notebookMetadataKeys'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'diagnostics' => ['external-notebook-resource-bytes-blocked'],
        ], $document->attr('notebookResourcePolicy'));
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
        $t->same(['text/plain'], $code->attr('ipynbOutputMimeTypes'));
        $t->same(1, $code->attr('ipynbRichOutputUnsupportedCount'));
        $t->same('1', $code->attr('attributes')['data-ipynb-rich-output-unsupported-count']);
        $t->same(2, $code->attr('ipynbUnsupportedResourceCount'));
        $t->same(['output-bytes-blocked', 'output-mime-bundle-metadata-only'], $code->attr('ipynbUnsupportedResourceDiagnostics'));
        $t->same([], $code->attr('ipynbCellMetadataKeys'));
        $t->same([], $code->attr('ipynbCellTags'));
        $t->same('code_block', $source->type);
        $t->same(['python', 'ipynb-code-cell-source'], $source->attr('classes'));
        $t->same('7', $source->attr('attributes')['data-ipynb-execution-count']);
        $t->contains('print("ready")', $source->attr('text'));

        $cellSummaries = $document->attr('notebookCells');
        $t->same(['image/png'], $cellSummaries[0]['attachmentMimeTypes']);
        $t->same(['attachment-bytes-blocked'], $cellSummaries[0]['diagnostics']);
        $t->same(['review'], $cellSummaries[0]['tags']);
        $t->same(['text/plain'], $cellSummaries[1]['outputMimeTypes']);
        $t->same(['output-bytes-blocked', 'output-mime-bundle-metadata-only'], $cellSummaries[1]['diagnostics']);

        $raw = $document->children[2]->children[0];
        $t->same('code_block', $raw->type);
        $t->same(['ipynb-raw-cell-source'], $raw->attr('classes'));
        $t->contains('title: Source notebook', $raw->attr('text'));

        $tasks = $document->children[3]->children[0];
        $t->same('bullet_list', $tasks->type);
        $t->same(true, $tasks->attr('taskList'));

        $t->contains('class="ipynb-cell ipynb-markdown-cell"', $html);
        $t->contains('data-ipynb-attachment-count="1"', $html);
        $t->contains('data-ipynb-cell-tags="review"', $html);
        $t->contains('data-ipynb-diagnostics="attachment-bytes-blocked"', $html);
        $t->contains('data-ipynb-diagnostics="output-bytes-blocked output-mime-bundle-metadata-only"', $html);
        $t->contains('data-ipynb-output-mime-types="text/plain"', $html);
        $t->contains('data-ipynb-rich-output-unsupported-count="1"', $html);
        $t->contains('<h1 id="notebook-import">Notebook import</h1>', $html);
        $t->contains('class="language-python"', $html);
        $t->contains('print(&quot;ready&quot;)', $html);
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
        $t->same(['output-bytes-blocked', 'output-mime-bundle-metadata-only'], $code->attr('ipynbUnsupportedResourceDiagnostics'));

        $t->contains('data-ipynb-cell-tags="alpha beta"', $html);
        $t->contains('data-ipynb-diagnostics="attachment-bytes-blocked"', $html);
        $t->contains('data-ipynb-diagnostics="output-bytes-blocked output-mime-bundle-metadata-only"', $html);
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
        $t->same(2, $cell->attr('ipynbRichOutputUnsupportedCount'));
        $t->same('application/json image/png text/html text/plain', $cell->attr('attributes')['data-ipynb-output-mime-types']);
        $t->same('2', $cell->attr('attributes')['data-ipynb-rich-output-unsupported-count']);

        $outputSummaries = $cell->attr('ipynbOutputSummaries');
        $t->same('stream', $outputSummaries[0]['outputType']);
        $t->same('stdout', $outputSummaries[0]['streamName']);
        $t->same(1, $outputSummaries[0]['textLineCount']);
        $t->same('display_data', $outputSummaries[1]['outputType']);
        $t->same(['image/png', 'text/html', 'text/plain'], $outputSummaries[1]['mimeTypes']);
        $t->same(3, $outputSummaries[1]['mimeCount']);
        $t->same(1, $outputSummaries[1]['metadataKeyCount']);
        $t->same('ipynb-rich-output-unsupported', $outputSummaries[1]['unsupportedVerdict']);
        $t->same('execute_result', $outputSummaries[2]['outputType']);
        $t->same(['application/json', 'text/plain'], $outputSummaries[2]['mimeTypes']);

        $encodedDocument = json_encode($document, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedDocument, $secretPayload));
        $t->same(false, str_contains($encodedDocument, 'iVBORw0KGgo'));
        $t->same(false, str_contains($encodedDocument, '<strong>'));
        $t->same(false, str_contains($html, $secretPayload));
        $t->same(false, str_contains($html, 'iVBORw0KGgo'));
        $t->contains('data-ipynb-output-mime-types="application/json image/png text/html text/plain"', $html);
        $t->contains('data-ipynb-rich-output-unsupported-count="2"', $html);
        $t->contains('plot(values)', $html);
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
    'reports native ipynb writer unsupported capability reason without notebook tooling' => static function (TestRunner $t): void {
        $report = PandocFormatRegistry::ipynbNativeWriterCapabilityReport();

        $t->same('ipynb', $report['format']);
        $t->same('native-ipynb-writer', $report['capability']);
        $t->same('output', $report['direction']);
        $t->same('unsupported', $report['status']);
        $t->same('partial-input-unsupported-output', $report['verdict']);
        $t->same('partial', $report['readerStatus']);
        $t->same('unsupported', $report['writerStatus']);
        $t->same(IpynbReader::class, $report['inputImplementation']);
        $t->same('', $report['outputImplementation']);
        $t->same(false, $report['countsAsDirectSupport']);
        $t->same(false, $report['nativeWriterParity']);
        $t->same(false, $report['requiresNotebookExecution']);
        $t->same(true, $report['externalToolFree']);
        $t->same([], $report['externalValidators']);
        $t->same(['output'], $report['unsupportedDirections']);
        $t->same(['ipynb-notebook-writer-core'], $report['gates']);
        $t->contains('notebook-writer-not-implemented', implode(',', $report['diagnostics']));
        $t->contains('external-notebook-tooling-disallowed', implode(',', $report['diagnostics']));

        $t->same('native-ipynb-writer-not-implemented', $report['reason']);
        $t->same('native-ipynb-writer-not-implemented', $report['reasonPacket']['code']);
        $t->contains('notebook-execution-not-run', implode(',', $report['reasonPacket']['details']));

        $decodedReason = json_decode($report['reasonJson'], true, 512, JSON_THROW_ON_ERROR);
        $t->same($report['reasonPacket'], $decodedReason);

        $encodedReport = json_encode($report, JSON_THROW_ON_ERROR);
        $decodedReport = json_decode($encodedReport, true, 512, JSON_THROW_ON_ERROR);
        $t->same('native-ipynb-writer-not-implemented', $decodedReport['reason']);
        $t->same('', $decodedReport['outputImplementation']);
    },
];
