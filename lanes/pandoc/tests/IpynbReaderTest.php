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
        $t->same(['kernelspec', 'language_info'], $document->attr('notebookMetadataKeys'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'diagnostics' => ['external-notebook-resource-bytes-blocked'],
        ], $document->attr('notebookResourcePolicy'));

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
    'reports bounded ipynb stream output grouping without exposing output bytes' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'execution_count' => 11,
                    'id' => 'stream-groups',
                    'outputs' => [
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['alpha stdout bytes'],
                        ],
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['beta stdout bytes'],
                        ],
                        [
                            'name' => 'stderr',
                            'output_type' => 'stream',
                            'text' => 'warning stderr bytes',
                        ],
                        [
                            'data' => [
                                'image/png' => 'hidden image bytes',
                                'text/plain' => ['plain display bytes'],
                            ],
                            'metadata' => ['transient' => ['display_id' => 'fig-1']],
                            'output_type' => 'display_data',
                        ],
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['gamma stdout bytes'],
                        ],
                        [
                            'ename' => 'ValueError',
                            'evalue' => 'bad value bytes',
                            'output_type' => 'error',
                            'traceback' => ['stack line bytes'],
                        ],
                        [
                            'data' => [
                                'application/json' => ['ok' => true],
                                'text/plain' => ['result bytes'],
                            ],
                            'metadata' => ['review' => 'kept'],
                            'output_type' => 'execute_result',
                        ],
                    ],
                    'source' => 'stream_groups()',
                ],
            ],
            'metadata' => [
                'language_info' => ['name' => 'python'],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $cell = $document->children[0];
        $attrs = $cell->attr('attributes');
        $html = (new WordPressBlockWriter())->write($document);
        $serialized = json_encode($document, JSON_THROW_ON_ERROR);

        $t->same(7, $document->attr('notebookOutputCount'));
        $t->same(7, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(2, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(6, $document->attr('notebookOutputGroupCount'));
        $t->same(3, $document->attr('notebookOutputStreamGroupCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedStreamNameCount'));
        $t->same(3, $document->attr('notebookOutputAggregateDiagnosticCount'));
        $t->same('metadata-only', $document->attr('notebookOutputBytePolicy')['state']);
        $t->same('blocked', $document->attr('notebookOutputBytePolicy')['byteExposure']);

        $t->same('0 1 2 3 4 5 6', $attrs['data-ipynb-output-indexes']);
        $t->same('stream stream stream display_data stream error execute_result', $attrs['data-ipynb-output-display-order']);
        $t->same('6', $attrs['data-ipynb-output-group-count']);
        $t->same('3', $attrs['data-ipynb-output-stream-group-count']);
        $t->same('stderr stdout', $attrs['data-ipynb-output-stream-names']);
        $t->same('stdout', $attrs['data-ipynb-output-repeated-stream-names']);
        $t->same('metadata-only', $attrs['data-ipynb-output-byte-policy']);
        $t->same('7', $attrs['data-ipynb-output-byte-presence-count']);
        $t->same('3', $attrs['data-ipynb-output-aggregate-diagnostic-count']);
        $t->same('application/json image/png text/plain', $attrs['data-ipynb-output-mime-types']);
        $t->same('text/plain', $attrs['data-ipynb-output-repeated-mime-keys']);

        $t->same(['stream', 'display_data', 'error', 'execute_result'], $cell->attr('ipynbOutputTypes'));
        $t->same([
            'stream',
            'stream',
            'stream',
            'display_data',
            'stream',
            'error',
            'execute_result',
        ], $cell->attr('ipynbOutputOrderTypes'));
        $t->same([0, 1, 2, 3, 4, 5, 6], $cell->attr('ipynbOutputIndexes'));
        $t->same(6, $cell->attr('ipynbOutputGroupCount'));
        $t->same(3, $cell->attr('ipynbOutputStreamGroupCount'));
        $t->same(['stderr', 'stdout'], $cell->attr('ipynbOutputStreamNames'));
        $t->same(['stdout'], $cell->attr('ipynbOutputRepeatedStreamNames'));

        $streamGroups = $cell->attr('ipynbOutputStreamGroups');
        $t->same('stdout', $streamGroups[0]['streamName']);
        $t->same([0, 1], $streamGroups[0]['outputIndexes']);
        $t->same(2, $streamGroups[0]['count']);
        $t->same('stderr', $streamGroups[1]['streamName']);
        $t->same([2], $streamGroups[1]['outputIndexes']);
        $t->same('stdout', $streamGroups[2]['streamName']);
        $t->same([4], $streamGroups[2]['outputIndexes']);

        $groups = $cell->attr('ipynbOutputGroups');
        $t->same(['stream', 'stream', 'output', 'stream', 'output', 'output'], array_column($groups, 'kind'));
        $t->same([0, 2, 3, 4, 5, 6], array_column($groups, 'startIndex'));
        $t->same([1, 2, 3, 4, 5, 6], array_column($groups, 'endIndex'));

        $diagnostics = $cell->attr('ipynbOutputAggregateDiagnostics');
        $t->same([
            'mixed-output-display-order',
            'repeated-output-mime-bundle-key',
            'repeated-output-stream-name',
        ], array_column($diagnostics, 'issue'));
        $t->same([0, 1, 4], $cell->attr('ipynbOutputRepeatedStreamNameRecords')[0]['outputIndexes']);
        $t->same([0, 3], $cell->attr('ipynbOutputRepeatedStreamNameRecords')[0]['groupIndexes']);
        $t->same([3, 6], $cell->attr('ipynbOutputRepeatedMimeBundleRecords')[0]['outputIndexes']);

        $outputs = $cell->attr('ipynbOutputSummaries');
        $t->same('stdout', $outputs[0]['streamName']);
        $t->same(1, $outputs[0]['streamTextLineCount']);
        $t->same('stderr', $outputs[2]['streamName']);
        $t->same(['image/png', 'text/plain'], $outputs[3]['mimeTypes']);
        $t->same(['transient'], $outputs[3]['metadataKeys']);
        $t->same('ValueError', $outputs[5]['errorName']);
        $t->same(true, $outputs[5]['errorValuePresent']);
        $t->same(1, $outputs[5]['tracebackLineCount']);
        $t->same(['application/json', 'text/plain'], $outputs[6]['mimeTypes']);

        $cellSummary = $document->attr('notebookCells')[0];
        $t->same(6, $cellSummary['outputGroupCount']);
        $t->same(3, $cellSummary['outputStreamGroupCount']);
        $t->same(['stdout'], $cellSummary['outputRepeatedStreamNames']);
        $t->same(3, $cellSummary['outputAggregateDiagnosticCount']);

        $t->contains('data-ipynb-output-display-order="stream stream stream display_data stream error execute_result"', $html);
        $t->contains('data-ipynb-output-stream-group-count="3"', $html);
        $t->same(false, str_contains($serialized, 'alpha stdout bytes'));
        $t->same(false, str_contains($serialized, 'hidden image bytes'));
        $t->same(false, str_contains($serialized, 'bad value bytes'));
        $t->same(false, str_contains($serialized, 'stack line bytes'));
        $t->same(false, str_contains($serialized, 'result bytes'));
    },
    'reports deterministic ipynb MIME bundle digest diagnostics without exposing output bytes' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'execution_count' => 42,
                    'id' => 'mime-digests',
                    'outputs' => [
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret label alpha',
                                    'series' => [1, 2, 3],
                                ],
                                'text/plain' => ['secret plain alpha'],
                            ],
                            'metadata' => ['display_id' => 'plot-a'],
                            'output_type' => 'display_data',
                        ],
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret label beta',
                                    'series' => [4, 5, 6],
                                ],
                                'text/plain' => ['secret plain beta'],
                            ],
                            'execution_count' => 42,
                            'metadata' => ['execution' => ['shell' => 1]],
                            'output_type' => 'execute_result',
                        ],
                        [
                            'data' => [
                                'text/html' => '<div>secret html payload</div>',
                            ],
                            'metadata' => ['isolated' => true],
                            'output_type' => 'display_data',
                        ],
                        [
                            'ename' => 'ZeroDivisionError',
                            'evalue' => 'division by secret zero',
                            'output_type' => 'error',
                            'traceback' => ['secret traceback line'],
                        ],
                    ],
                    'source' => 'mime_digests()',
                ],
            ],
            'metadata' => [
                'language_info' => ['name' => 'python'],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $cell = $document->children[0];
        $attrs = $cell->attr('attributes');
        $html = (new WordPressBlockWriter())->write($document);
        $serialized = json_encode($document, JSON_THROW_ON_ERROR);

        $digests = $cell->attr('ipynbOutputMimeBundleDigests');
        $t->same(3, $document->attr('notebookOutputMimeBundleDigestCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedMimeBundleDigestCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedMimeBundleDigestDuplicateCount'));
        $t->same(3, $cell->attr('ipynbOutputMimeBundleCount'));
        $t->same(3, count($digests));
        $t->true(preg_match('/^sha256:[a-f0-9]{64}$/', $digests[0]) === 1, 'MIME bundle digest should be a sha256 fingerprint');
        $t->same($digests[0], $digests[1]);
        $t->true($digests[0] !== $digests[2], 'Distinct MIME bundle shapes should not share a digest');

        $t->same($digests[0] . ' ' . $digests[1] . ' ' . $digests[2], $attrs['data-ipynb-output-mime-bundle-digests']);
        $t->same($digests[0], $attrs['data-ipynb-output-repeated-mime-bundle-digests']);
        $t->same('1', $attrs['data-ipynb-output-repeated-mime-bundle-digest-count']);
        $t->same('1', $attrs['data-ipynb-output-repeated-mime-bundle-duplicate-count']);

        $digestRecords = $cell->attr('ipynbOutputMimeBundleDigestRecords');
        $t->same($digests[0], $digestRecords[0]['digest']);
        $t->same(0, $digestRecords[0]['outputIndex']);
        $t->same(0, $digestRecords[0]['groupIndex']);
        $t->same('display_data', $digestRecords[0]['outputType']);
        $t->same(['application/json', 'text/plain'], $digestRecords[0]['mimeTypes']);
        $t->same('metadata-only', $digestRecords[0]['fingerprintSource']);
        $t->same([
            'kind' => 'object',
            'keys' => ['label', 'series'],
            'fieldKinds' => [
                'label' => 'string',
                'series' => 'list',
            ],
        ], $digestRecords[0]['payloadShapes']['application/json']);
        $t->same([
            'kind' => 'list',
            'count' => 1,
            'entryKinds' => ['string'],
            'stringLineCount' => 1,
        ], $digestRecords[0]['payloadShapes']['text/plain']);

        $repeatedDigests = $cell->attr('ipynbOutputRepeatedMimeBundleDigestRecords');
        $t->same($digests[0], $repeatedDigests[0]['digest']);
        $t->same([0, 1], $repeatedDigests[0]['outputIndexes']);
        $t->same([0, 1], $repeatedDigests[0]['groupIndexes']);
        $t->same(['display_data', 'execute_result'], $repeatedDigests[0]['outputTypes']);
        $t->same(2, $repeatedDigests[0]['count']);
        $t->same(1, $repeatedDigests[0]['duplicateCount']);

        $outputs = $cell->attr('ipynbOutputSummaries');
        $t->same('display_data', $outputs[0]['richOutputKind']);
        $t->same($digests[0], $outputs[0]['mimeBundleDigest']);
        $t->same(0, $outputs[0]['groupIndex']);
        $t->same('execute_result', $outputs[1]['richOutputKind']);
        $t->same($digests[1], $outputs[1]['mimeBundleDigest']);
        $t->same(42, $outputs[1]['executionCount']);
        $t->same(1, $outputs[1]['groupIndex']);
        $t->same('display_data', $outputs[2]['richOutputKind']);
        $t->same(['text/html'], $outputs[2]['mimeTypes']);
        $t->same('error', $outputs[3]['richOutputKind']);
        $t->same('ZeroDivisionError', $outputs[3]['errorName']);
        $t->same(true, $outputs[3]['errorValuePresent']);
        $t->same(1, $outputs[3]['tracebackLineCount']);

        $diagnostics = $cell->attr('ipynbOutputAggregateDiagnostics');
        $t->same([
            'mixed-output-display-order',
            'repeated-output-mime-bundle-key',
            'repeated-output-mime-bundle-key',
            'repeated-output-mime-bundle-digest',
        ], array_column($diagnostics, 'issue'));
        $t->same(2, $cell->attr('ipynbOutputAggregateDiagnostics')[3]['occurrenceCount']);
        $t->same(1, $cell->attr('ipynbOutputAggregateDiagnostics')[3]['duplicateCount']);

        $cellSummary = $document->attr('notebookCells')[0];
        $t->same($digests, $cellSummary['outputMimeBundleDigests']);
        $t->same([$digests[0]], $cellSummary['outputRepeatedMimeBundleDigests']);
        $t->same(1, $cellSummary['outputRepeatedMimeBundleDigestCount']);
        $t->same(1, $cellSummary['outputRepeatedMimeBundleDigestDuplicateCount']);

        $t->contains('data-ipynb-output-repeated-mime-bundle-digest-count="1"', $html);
        $t->same(false, str_contains($serialized, 'secret label alpha'));
        $t->same(false, str_contains($serialized, 'secret label beta'));
        $t->same(false, str_contains($serialized, 'secret plain alpha'));
        $t->same(false, str_contains($serialized, 'secret plain beta'));
        $t->same(false, str_contains($serialized, 'secret html payload'));
        $t->same(false, str_contains($serialized, 'division by secret zero'));
        $t->same(false, str_contains($serialized, 'secret traceback line'));
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
        $t->same(['string' => 2, 'list' => 1, 'missing' => 1, 'null' => 1], $summary['sourceShapeCounts']);
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
        $t->same([
            [
                'sourceFingerprint' => $repeatedFingerprint,
                'count' => 2,
                'cellIndexes' => [0, 1],
            ],
            [
                'sourceFingerprint' => $emptyFingerprint,
                'count' => 2,
                'cellIndexes' => [2, 3],
            ],
        ], $duplicates);

        $metadata = json_encode([
            $cells,
            $summary,
            $fingerprintCounts,
            $duplicates,
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($metadata, 'alpha'));
        $t->same(false, str_contains($metadata, 'beta'));
    },
    'summarizes ipynb per-cell language hints with notebook fallback and source digests' => static function (TestRunner $t): void {
        $phpSource = "secret_python_source()\n";
        $javascriptSource = "secret_javascript_source()\n";
        $fallbackSource = "secret_fallback_source()\n";
        $unknownSource = "secret_unknown_source()\n";

        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'metadata' => [
                        'language' => 'PHP',
                    ],
                    'source' => $phpSource,
                ],
                [
                    'cell_type' => 'code',
                    'metadata' => [
                        'vscode' => [
                            'languageId' => 'JavaScript',
                        ],
                    ],
                    'source' => $javascriptSource,
                ],
                [
                    'cell_type' => 'code',
                    'metadata' => [],
                    'source' => $fallbackSource,
                ],
            ],
            'metadata' => [
                'language_info' => [
                    'name' => 'php',
                ],
                'kernelspec' => [
                    'language' => 'python',
                    'name' => 'python3',
                ],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $cells = $document->attr('notebookCells');
        $summary = $document->attr('notebookLanguageHintSummary');
        $html = (new WordPressBlockWriter())->write($document);

        $t->same('php', $document->attr('notebookLanguage'));
        $t->same([
            'notebookLanguage' => 'php',
            'notebookLanguageSource' => 'notebook.language_info.name',
            'cellCount' => 3,
            'cellMetadataLanguageHintCount' => 2,
            'notebookLanguageFallbackCount' => 1,
            'unknownLanguageHintCount' => 0,
            'mismatchedLanguageHintCount' => 1,
            'languageHintCounts' => [
                'javascript' => 1,
                'php' => 2,
            ],
            'languageHintSourceCounts' => [
                'cell.metadata.language' => 1,
                'cell.metadata.vscode.languageId' => 1,
                'notebook.language_info.name' => 1,
            ],
            'languageHintDiagnosticCounts' => [
                'language-hint-mismatch-notebook-language' => 1,
            ],
        ], $summary);

        $t->same('php', $cells[0]['languageHint']);
        $t->same('cell.metadata.language', $cells[0]['languageHintSource']);
        $t->same(true, $cells[0]['languageHintIsCellMetadata']);
        $t->same(false, $cells[0]['languageHintIsNotebookFallback']);
        $t->same(true, $cells[0]['languageHintMatchesNotebook']);
        $t->same([], $cells[0]['languageHintDiagnostics']);
        $t->same(['algorithm' => 'sha256', 'value' => hash('sha256', $phpSource)], $cells[0]['sourceDigest']);

        $t->same('javascript', $cells[1]['languageHint']);
        $t->same('cell.metadata.vscode.languageId', $cells[1]['languageHintSource']);
        $t->same(false, $cells[1]['languageHintMatchesNotebook']);
        $t->same(['language-hint-mismatch-notebook-language'], $cells[1]['languageHintDiagnostics']);
        $t->same('sha256:' . hash('sha256', $javascriptSource), $cells[1]['sourceFingerprint']);

        $t->same('php', $cells[2]['languageHint']);
        $t->same('notebook.language_info.name', $cells[2]['languageHintSource']);
        $t->same(false, $cells[2]['languageHintIsCellMetadata']);
        $t->same(true, $cells[2]['languageHintIsNotebookFallback']);
        $t->same(true, $cells[2]['languageHintMatchesNotebook']);
        $t->same([], $cells[2]['languageHintDiagnostics']);
        $t->same('sha256:' . hash('sha256', $fallbackSource), $cells[2]['sourceFingerprint']);

        $firstCode = $document->children[0]->children[0];
        $secondCode = $document->children[1]->children[0];
        $fallbackCode = $document->children[2]->children[0];
        $t->same(['php', 'ipynb-code-cell-source'], $firstCode->attr('classes'));
        $t->same(['javascript', 'ipynb-code-cell-source'], $secondCode->attr('classes'));
        $t->same(['php', 'ipynb-code-cell-source'], $fallbackCode->attr('classes'));
        $t->same('language-hint-mismatch-notebook-language', $secondCode->attr('attributes')['data-ipynb-language-diagnostics']);
        $t->contains('data-ipynb-language-hint="javascript"', $html);
        $t->contains('data-ipynb-language-diagnostics="language-hint-mismatch-notebook-language"', $html);

        $unknownJson = json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'source' => $unknownSource,
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);
        $unknownDocument = (new IpynbReader())->read($unknownJson);
        $unknownCells = $unknownDocument->attr('notebookCells');
        $unknownSummary = $unknownDocument->attr('notebookLanguageHintSummary');

        $t->same('unknown', $unknownCells[0]['languageHint']);
        $t->same('none', $unknownCells[0]['languageHintSource']);
        $t->same(['language-hint-unknown'], $unknownCells[0]['languageHintDiagnostics']);
        $t->same([
            'notebookLanguage' => '',
            'notebookLanguageSource' => 'none',
            'cellCount' => 1,
            'cellMetadataLanguageHintCount' => 0,
            'notebookLanguageFallbackCount' => 0,
            'unknownLanguageHintCount' => 1,
            'mismatchedLanguageHintCount' => 0,
            'languageHintCounts' => [
                'unknown' => 1,
            ],
            'languageHintSourceCounts' => [
                'none' => 1,
            ],
            'languageHintDiagnosticCounts' => [
                'language-hint-unknown' => 1,
            ],
        ], $unknownSummary);

        $metadata = json_encode([
            $cells,
            $summary,
            $unknownCells,
            $unknownSummary,
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($metadata, 'secret_python_source'));
        $t->same(false, str_contains($metadata, 'secret_javascript_source'));
        $t->same(false, str_contains($metadata, 'secret_fallback_source'));
        $t->same(false, str_contains($metadata, 'secret_unknown_source'));
    },
    'summarizes ipynb language output consistency with stream grouping metadata only' => static function (TestRunner $t): void {
        $source = "secret_language_consistency_source()\n";
        $json = json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'metadata' => [
                        'language' => 'PHP',
                    ],
                    'outputs' => [
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['secret stream bytes'],
                        ],
                        [
                            'data' => [
                                'application/javascript' => 'secret js output bytes',
                                'text/plain' => 'plain output bytes',
                                'text/x-php' => ['secret php output bytes'],
                            ],
                            'metadata' => ['display_id' => 'lang-review'],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => $source,
                ],
            ],
            'metadata' => [
                'language_info' => [
                    'name' => 'php',
                ],
                'kernelspec' => [
                    'language' => 'python',
                    'name' => 'python3',
                ],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR);

        $document = (new IpynbReader())->read($json);
        $cell = $document->children[0];
        $cellSummaries = $document->attr('notebookCells');
        $metadataSummary = $document->attr('notebookLanguageMetadataSummary');
        $consistency = $document->attr('notebookLanguageOutputConsistencySummary');
        $policySummary = $document->attr('notebookMimeLanguagePolicySummary');
        $records = $cell->attr('ipynbOutputLanguageRecords');
        $html = (new WordPressBlockWriter())->write($document);

        $t->same([
            'languageInfoName' => 'php',
            'languageInfoLanguage' => 'php',
            'kernelspecLanguage' => 'python',
            'resolvedLanguage' => 'php',
            'resolvedLanguageSource' => 'notebook.language_info.name',
            'diagnostics' => ['notebook-language-info-kernelspec-mismatch'],
        ], $metadataSummary);
        $t->same([
            'cellCount' => 1,
            'notebookLanguage' => 'php',
            'notebookLanguageSource' => 'notebook.language_info.name',
            'notebookLanguageDiagnostics' => ['notebook-language-info-kernelspec-mismatch'],
            'cellMetadataLanguageHintCount' => 1,
            'notebookLanguageFallbackCount' => 0,
            'unknownLanguageHintCount' => 0,
            'mismatchedLanguageHintCount' => 0,
            'outputLanguageLikeMimeCount' => 2,
            'outputLanguageLikeMimeCellCount' => 1,
            'outputLanguageMatchCount' => 1,
            'outputLanguageMismatchCount' => 1,
            'outputLanguageUnknownCount' => 0,
            'streamAssociatedOutputLanguageCount' => 2,
            'streamAssociatedOutputLanguageMismatchCount' => 1,
            'outputLanguageDigestAssociationCount' => 2,
            'outputLanguageDigestMissingCount' => 0,
            'outputLanguagePolicyCounts' => [
                'digest-associated' => 2,
                'digest-missing' => 0,
                'match' => 1,
                'mismatch' => 1,
                'unknown' => 0,
            ],
            'outputLanguageCounts' => [
                'javascript' => 1,
                'php' => 1,
            ],
            'outputLanguageMimeCounts' => [
                'application/javascript' => 1,
                'text/x-php' => 1,
            ],
            'outputLanguageDiagnosticCounts' => [
                'output-language-mismatch-cell-language' => 1,
            ],
        ], $consistency);
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'notebookLanguage' => 'php',
            'notebookLanguageSource' => 'notebook.language_info.name',
            'notebookLanguageDiagnostics' => ['notebook-language-info-kernelspec-mismatch'],
            'languageLikeMimeCount' => 2,
            'languageLikeMimeCellCount' => 1,
            'matchedLanguageCount' => 1,
            'mismatchedLanguageCount' => 1,
            'unknownLanguageCount' => 0,
            'digestAssociatedLanguageCount' => 2,
            'digestMissingLanguageCount' => 0,
            'policyCounts' => [
                'digest-associated' => 2,
                'digest-missing' => 0,
                'match' => 1,
                'mismatch' => 1,
                'unknown' => 0,
            ],
            'languageCounts' => [
                'javascript' => 1,
                'php' => 1,
            ],
            'mimeTypeCounts' => [
                'application/javascript' => 1,
                'text/x-php' => 1,
            ],
            'diagnosticCounts' => [
                'output-language-mismatch-cell-language' => 1,
            ],
        ], $policySummary);

        $t->same(2, $cell->attr('ipynbOutputLanguageLikeMimeCount'));
        $t->same(['application/javascript', 'text/x-php'], $cell->attr('ipynbOutputLanguageLikeMimeTypes'));
        $t->same(['javascript', 'php'], $cell->attr('ipynbOutputLanguageHints'));
        $t->same(1, $cell->attr('ipynbOutputLanguageMatchCount'));
        $t->same(1, $cell->attr('ipynbOutputLanguageMismatchCount'));
        $t->same(0, $cell->attr('ipynbOutputLanguageUnknownCount'));
        $t->same(['output-language-mismatch-cell-language'], $cell->attr('ipynbOutputLanguageDiagnostics'));
        $t->same(['output-language-mismatch-cell-language' => 1], $cell->attr('ipynbOutputLanguageDiagnosticCounts'));
        $t->same($cell->attr('ipynbOutputLanguageRecords'), $cell->attr('ipynbOutputMimeLanguagePolicyRecords'));
        $t->same([
            'state' => 'metadata-only',
            'byteExposure' => 'blocked',
            'cellLanguageHint' => 'php',
            'cellLanguageHintSource' => 'cell.metadata.language',
            'languageLikeMimeCount' => 2,
            'matchedLanguageCount' => 1,
            'mismatchedLanguageCount' => 1,
            'unknownLanguageCount' => 0,
            'digestAssociatedLanguageCount' => 2,
            'digestMissingLanguageCount' => 0,
            'digestProvenanceCounts' => [
                'linkedRecordCount' => 2,
                'missingRecordCount' => 0,
                'digestRecordIndexedCount' => 2,
                'outputGroupAssociatedRecordCount' => 2,
                'matchedRecordCount' => 1,
                'mismatchedRecordCount' => 1,
                'unknownRecordCount' => 0,
            ],
            'policyCounts' => [
                'digest-associated' => 2,
                'digest-missing' => 0,
                'match' => 1,
                'mismatch' => 1,
                'unknown' => 0,
            ],
            'diagnostics' => ['output-language-mismatch-cell-language'],
            'diagnosticCounts' => [
                'output-language-mismatch-cell-language' => 1,
            ],
        ], $cell->attr('ipynbOutputMimeLanguagePolicySummary'));

        $t->same('application/javascript', $records[0]['mimeType']);
        $t->same('javascript', $records[0]['language']);
        $t->same('php', $records[0]['cellLanguageHint']);
        $t->same(false, $records[0]['matchesCellLanguage']);
        $t->same('mismatches-cell-language', $records[0]['policy']);
        $t->same('metadata-only', $records[0]['reviewPolicy']);
        $t->same('blocked', $records[0]['byteExposure']);
        $t->same(1, $records[0]['outputGroupIndex']);
        $t->same(0, $records[0]['associatedStreamGroupIndex']);
        $t->same('stdout', $records[0]['associatedStreamGroupName']);
        $t->same([0], $records[0]['associatedStreamGroupOutputIndexes']);
        $t->same($cell->attr('ipynbOutputMimeBundleDigests')[0], $records[0]['mimeBundleDigest']);
        $t->same('metadata-only', $records[0]['mimeBundleFingerprintSource']);
        $t->same(['output-language-mismatch-cell-language'], $records[0]['diagnostics']);
        $t->same('text/x-php', $records[1]['mimeType']);
        $t->same('php', $records[1]['language']);
        $t->same(true, $records[1]['matchesCellLanguage']);
        $t->same('matches-cell-language', $records[1]['policy']);
        $t->same($records[0]['mimeBundleDigest'], $records[1]['mimeBundleDigest']);
        $t->same(1, $records[1]['outputGroupIndex']);
        $t->same(0, $records[1]['associatedStreamGroupIndex']);
        $t->same([], $records[1]['diagnostics']);

        $summary = $cellSummaries[0];
        $t->same(2, $summary['outputLanguageLikeMimeCount']);
        $t->same(['javascript', 'php'], $summary['outputLanguageHints']);
        $t->same(1, $summary['outputLanguageMismatchCount']);
        $t->same(['output-language-mismatch-cell-language'], $summary['outputLanguageDiagnostics']);
        $t->same($cell->attr('ipynbOutputMimeLanguagePolicySummary'), $summary['outputMimeLanguagePolicySummary']);
        $t->same($records, $summary['outputMimeLanguagePolicyRecords']);
        $t->same('sha256:' . hash('sha256', $source), $summary['sourceFingerprint']);
        $t->same(2, $summary['outputGroupCount']);
        $t->same(1, $summary['outputStreamGroupCount']);

        $t->contains('data-ipynb-output-language-like-mime-count="2"', $html);
        $t->contains('data-ipynb-output-language-hints="javascript php"', $html);
        $t->contains('data-ipynb-output-language-policy="metadata-only"', $html);
        $t->contains('data-ipynb-output-language-diagnostics="output-language-mismatch-cell-language"', $html);

        $metadataOnly = json_encode([
            $metadataSummary,
            $consistency,
            $policySummary,
            $records,
            $cell->attr('ipynbOutputMimeLanguagePolicySummary'),
            $summary['outputLanguageRecords'],
            $summary['outputMimeLanguagePolicyRecords'],
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($metadataOnly, 'secret_language_consistency_source'));
        $t->same(false, str_contains($metadataOnly, 'secret stream bytes'));
        $t->same(false, str_contains($metadataOnly, 'secret js output bytes'));
        $t->same(false, str_contains($metadataOnly, 'secret php output bytes'));

        $unknownDocument = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'data' => [
                                'application/javascript' => 'secret unknown js bytes',
                            ],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => "secret_unknown_language_output_source()\n",
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));
        $unknownCell = $unknownDocument->children[0];
        $unknownConsistency = $unknownDocument->attr('notebookLanguageOutputConsistencySummary');
        $unknownRecords = $unknownCell->attr('ipynbOutputLanguageRecords');

        $t->same(['notebook-language-unknown'], $unknownDocument->attr('notebookLanguageMetadataSummary')['diagnostics']);
        $t->same(1, $unknownCell->attr('ipynbOutputLanguageUnknownCount'));
        $t->same(['output-language-unknown-cell-language'], $unknownCell->attr('ipynbOutputLanguageDiagnostics'));
        $t->same('unknown-cell-language', $unknownRecords[0]['policy']);
        $t->same('metadata-only', $unknownRecords[0]['reviewPolicy']);
        $t->same('blocked', $unknownRecords[0]['byteExposure']);
        $t->true(isset($unknownRecords[0]['mimeBundleDigest']), 'unknown language records still associate MIME digests');
        $t->same(null, $unknownRecords[0]['matchesCellLanguage']);
        $t->same(['output-language-unknown-cell-language'], $unknownRecords[0]['diagnostics']);
        $t->same(1, $unknownConsistency['outputLanguageUnknownCount']);
        $t->same(1, $unknownConsistency['outputLanguageDigestAssociationCount']);
        $t->same([
            'digest-associated' => 1,
            'digest-missing' => 0,
            'match' => 0,
            'mismatch' => 0,
            'unknown' => 1,
        ], $unknownConsistency['outputLanguagePolicyCounts']);
        $t->same([
            'output-language-unknown-cell-language' => 1,
        ], $unknownConsistency['outputLanguageDiagnosticCounts']);
    },
    'links ipynb MIME language policy rows to digest provenance without exposing payload bytes' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'metadata' => [
                        'language' => 'php',
                    ],
                    'outputs' => [
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['secret stream cell zero'],
                        ],
                        [
                            'data' => [
                                'application/javascript' => 'secret js cell zero',
                                'text/x-php' => ['secret php cell zero'],
                            ],
                            'metadata' => ['display_id' => 'digest-link-0'],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => "secret_digest_language_source_zero()\n",
                ],
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'name' => 'stderr',
                            'output_type' => 'stream',
                            'text' => ['secret stream cell one'],
                        ],
                        [
                            'data' => [
                                'application/javascript' => 'secret js cell one',
                                'text/x-php' => ['secret php cell one'],
                            ],
                            'metadata' => ['display_id' => 'digest-link-1'],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => "secret_digest_language_source_one()\n",
                ],
            ],
            'metadata' => [],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $cellZero = $document->children[0];
        $cellOne = $document->children[1];
        $cellZeroRecords = $cellZero->attr('ipynbOutputLanguageRecords');
        $cellOneRecords = $cellOne->attr('ipynbOutputLanguageRecords');
        $summary = $document->attr('notebookMimeLanguageDigestProvenanceSummary');
        $provenanceRecords = $document->attr('notebookMimeLanguageDigestProvenanceRecords');
        $duplicateSummaries = $document->attr('notebookMimeLanguageDigestDuplicateSummaries');

        $t->same('mismatches-cell-language', $cellZeroRecords[0]['policy']);
        $t->same('matches-cell-language', $cellZeroRecords[1]['policy']);
        $t->same('unknown-cell-language', $cellOneRecords[0]['policy']);
        $t->same('unknown-cell-language', $cellOneRecords[1]['policy']);

        $digest = $cellZero->attr('ipynbOutputMimeBundleDigests')[0];
        $t->same($digest, $cellOne->attr('ipynbOutputMimeBundleDigests')[0]);
        $t->same($digest, $cellZeroRecords[0]['mimeBundleDigest']);
        $t->same(0, $cellZeroRecords[0]['mimeBundleDigestRecordIndex']);
        $t->same([
            'state' => 'linked',
            'recordIndex' => 0,
            'digest' => $digest,
            'outputIndex' => 1,
            'outputGroupIndex' => 1,
            'outputType' => 'display_data',
            'mimeTypes' => ['application/javascript', 'text/x-php'],
            'fingerprintSource' => 'metadata-only',
            'payloadPolicy' => 'shape-only',
            'byteExposure' => 'blocked',
        ], $cellZeroRecords[0]['mimeBundleDigestProvenance']);
        $t->same(0, $cellZeroRecords[0]['associatedStreamGroupIndex']);
        $t->same('stdout', $cellZeroRecords[0]['associatedStreamGroupName']);
        $t->same(0, $cellOneRecords[0]['associatedStreamGroupIndex']);
        $t->same('stderr', $cellOneRecords[0]['associatedStreamGroupName']);

        $t->same([
            'linkedRecordCount' => 2,
            'missingRecordCount' => 0,
            'digestRecordIndexedCount' => 2,
            'outputGroupAssociatedRecordCount' => 2,
            'matchedRecordCount' => 1,
            'mismatchedRecordCount' => 1,
            'unknownRecordCount' => 0,
        ], $cellZero->attr('ipynbOutputMimeLanguagePolicySummary')['digestProvenanceCounts']);
        $t->same([
            'linkedRecordCount' => 2,
            'missingRecordCount' => 0,
            'digestRecordIndexedCount' => 2,
            'outputGroupAssociatedRecordCount' => 2,
            'matchedRecordCount' => 0,
            'mismatchedRecordCount' => 0,
            'unknownRecordCount' => 2,
        ], $cellOne->attr('ipynbOutputMimeLanguagePolicySummary')['digestProvenanceCounts']);

        $t->same('metadata-only', $summary['state']);
        $t->same('blocked', $summary['byteExposure']);
        $t->same(4, $summary['policyRecordCount']);
        $t->same(4, $summary['digestLinkedRecordCount']);
        $t->same(0, $summary['digestMissingRecordCount']);
        $t->same(4, $summary['digestRecordIndexedCount']);
        $t->same(4, $summary['outputGroupAssociatedRecordCount']);
        $t->same(1, $summary['matchedRecordCount']);
        $t->same(1, $summary['mismatchedRecordCount']);
        $t->same(2, $summary['unknownRecordCount']);
        $t->same(1, $summary['duplicateDigestCount']);
        $t->same(4, $summary['duplicateDigestPolicyRecordCount']);
        $t->same(1, $summary['crossCellDuplicateDigestCount']);
        $t->same(4, $summary['crossCellDuplicatePolicyRecordCount']);
        $t->same(['javascript' => 2, 'php' => 2], $summary['languageCounts']);
        $t->same(['application/javascript' => 2, 'text/x-php' => 2], $summary['mimeTypeCounts']);
        $t->same([
            'matches-cell-language' => 1,
            'mismatches-cell-language' => 1,
            'unknown-cell-language' => 2,
        ], $summary['policyCounts']);

        $t->same(4, count($provenanceRecords));
        $t->same(0, $provenanceRecords[0]['languagePolicyRecordIndex']);
        $t->same(1, $provenanceRecords[2]['cellIndex']);
        $t->same('unknown-cell-language', $provenanceRecords[2]['policy']);
        $t->same(0, $provenanceRecords[2]['mimeBundleDigestCollisionRecordIndex']);

        $t->same(1, count($duplicateSummaries));
        $duplicate = $duplicateSummaries[0];
        $t->same($digest, $duplicate['digest']);
        $t->same(4, $duplicate['recordCount']);
        $t->same(3, $duplicate['duplicateCount']);
        $t->same(true, $duplicate['crossCell']);
        $t->same([0, 1], $duplicate['cellIndexes']);
        $t->same([1], $duplicate['outputIndexes']);
        $t->same([1], $duplicate['outputGroupIndexes']);
        $t->same([0, 1, 2, 3], $duplicate['languagePolicyRecordIndexes']);
        $t->same(['javascript', 'php'], $duplicate['languages']);
        $t->same(['application/javascript', 'text/x-php'], $duplicate['mimeTypes']);
        $t->same([
            'matches-cell-language',
            'mismatches-cell-language',
            'unknown-cell-language',
        ], $duplicate['policies']);
        $t->same([
            'matches-cell-language' => 1,
            'mismatches-cell-language' => 1,
            'unknown-cell-language' => 2,
        ], $duplicate['policyCounts']);
        $t->same(0, $duplicate['mimeBundleDigestCollisionRecordIndex']);
        $t->same('shape-only', $duplicate['payloadPolicy']);
        $t->same('blocked', $duplicate['byteExposure']);

        $metadataOnly = json_encode([
            $cellZeroRecords,
            $cellOneRecords,
            $summary,
            $provenanceRecords,
            $duplicateSummaries,
        ], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($metadataOnly, 'secret js cell zero'));
        $t->same(false, str_contains($metadataOnly, 'secret php cell zero'));
        $t->same(false, str_contains($metadataOnly, 'secret js cell one'));
        $t->same(false, str_contains($metadataOnly, 'secret php cell one'));
        $t->same(false, str_contains($metadataOnly, 'secret stream cell zero'));
        $t->same(false, str_contains($metadataOnly, 'secret_digest_language_source'));
    },
    'reports ipynb MIME bundle digest collision policy across cells without exposing output bytes' => static function (TestRunner $t): void {
        $document = (new IpynbReader())->read(json_encode([
            'cells' => [
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret cell zero display label',
                                    'series' => [1, 2],
                                ],
                                'text/plain' => ['secret cell zero display text'],
                            ],
                            'metadata' => ['display_id' => 'same-shape-0'],
                            'output_type' => 'display_data',
                        ],
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret cell zero execute label',
                                    'series' => [3, 4],
                                ],
                                'text/plain' => ['secret cell zero execute text'],
                            ],
                            'execution_count' => 8,
                            'metadata' => ['execution' => ['iopub' => 1]],
                            'output_type' => 'execute_result',
                        ],
                    ],
                    'source' => 'same_shape_zero()',
                ],
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret cell one display label',
                                    'series' => [5, 6],
                                ],
                                'text/plain' => ['secret cell one display text'],
                            ],
                            'metadata' => ['display_id' => 'same-shape-1'],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => 'same_shape_one()',
                ],
                [
                    'cell_type' => 'code',
                    'outputs' => [
                        [
                            'name' => 'stdout',
                            'output_type' => 'stream',
                            'text' => ['secret stream line'],
                        ],
                        [
                            'ename' => 'RuntimeError',
                            'evalue' => 'secret error value',
                            'output_type' => 'error',
                            'traceback' => ['secret traceback value'],
                        ],
                        [
                            'data' => [
                                'application/json' => [
                                    'label' => 'secret cell two display label',
                                    'series' => [7, 8],
                                ],
                                'text/plain' => ['secret cell two display text'],
                            ],
                            'metadata' => ['display_id' => 'same-shape-2'],
                            'output_type' => 'display_data',
                        ],
                    ],
                    'source' => 'same_shape_two()',
                ],
            ],
            'metadata' => [
                'language_info' => ['name' => 'python'],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ], JSON_THROW_ON_ERROR));

        $serialized = json_encode($document, JSON_THROW_ON_ERROR);
        $policy = $document->attr('notebookOutputMimeBundleDigestCollisionPolicy');
        $counts = $policy['counts'];
        $records = $document->attr('notebookOutputMimeBundleDigestCollisionRecords');
        $duplicateCounts = $document->attr('notebookOutputDuplicatePolicyCounts');
        $grouping = $document->attr('notebookOutputGroupingSummary');

        $t->same(6, $document->attr('notebookOutputCount'));
        $t->same(6, $document->attr('notebookOutputBytePresenceCount'));
        $t->same(4, $document->attr('notebookOutputMimeBundleCount'));
        $t->same(4, $document->attr('notebookOutputMimeBundleDigestCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedMimeBundleDigestCount'));
        $t->same(1, $document->attr('notebookOutputRepeatedMimeBundleDigestDuplicateCount'));
        $t->same(1, $document->attr('notebookOutputMimeBundleDigestCollisionCount'));
        $t->same(3, $document->attr('notebookOutputMimeBundleDigestCollisionDuplicateCount'));

        $t->same('metadata-only', $policy['state']);
        $t->same('metadata-only', $policy['fingerprintSource']);
        $t->same('shape-only', $policy['payloadPolicy']);
        $t->same('blocked', $policy['byteExposure']);
        $t->same('review', $policy['collisionPolicy']);
        $t->same([
            'ipynb-output-mime-bundle-digest-metadata-only',
            'ipynb-output-mime-bundle-digest-collision-review',
            'ipynb-output-mime-bundle-digest-cross-cell-review',
            'ipynb-output-mime-bundle-digest-different-output-index-review',
            'ipynb-output-mime-bundle-digest-different-group-index-review',
            'ipynb-output-mime-bundle-digest-mixed-output-type-review',
        ], $policy['diagnostics']);
        $t->same([
            'digestRecordCount' => 4,
            'uniqueDigestCount' => 1,
            'collisionCount' => 1,
            'duplicateCount' => 3,
            'crossCellCount' => 1,
            'sameOutputIndexCount' => 0,
            'differentOutputIndexCount' => 1,
            'sameGroupIndexCount' => 0,
            'differentGroupIndexCount' => 1,
            'mixedOutputTypeCount' => 1,
        ], $counts);

        $t->same(1, count($records));
        $record = $records[0];
        $t->true(preg_match('/^sha256:[a-f0-9]{64}$/', $record['digest']) === 1, 'Collision record should retain the shape digest only');
        $t->same(4, $record['occurrenceCount']);
        $t->same(3, $record['duplicateCount']);
        $t->same([0, 1, 2], $record['cellIndexes']);
        $t->same([0, 1, 2], $record['outputIndexes']);
        $t->same([0, 1, 2], $record['groupIndexes']);
        $t->same(['display_data', 'execute_result'], $record['outputTypes']);
        $t->same(['application/json', 'text/plain'], $record['mimeTypes']);
        $t->same([
            'cross-cell',
            'different-output-index',
            'different-group-index',
            'mixed-output-type',
        ], $record['collisionScopes']);
        $t->same('metadata-only', $record['fingerprintSource']);
        $t->same('blocked', $record['byteExposure']);
        $t->same(0, $record['occurrences'][0]['cellIndex']);
        $t->same(0, $record['occurrences'][0]['outputIndex']);
        $t->same('display_data', $record['occurrences'][0]['outputType']);
        $t->same(2, $record['occurrences'][3]['cellIndex']);
        $t->same(2, $record['occurrences'][3]['outputIndex']);

        $t->same([
            'repeatedStreamNameCount' => 0,
            'repeatedStreamNameGroupDuplicateCount' => 0,
            'repeatedMimeBundleKeyCount' => 2,
            'repeatedMimeBundleKeyDuplicateCount' => 2,
            'repeatedMimeBundleDigestCount' => 1,
            'repeatedMimeBundleDigestDuplicateCount' => 1,
            'digestCollisionCount' => 1,
            'digestCollisionDuplicateCount' => 3,
            'digestCrossCellCollisionCount' => 1,
            'digestSameOutputIndexCollisionCount' => 0,
            'digestDifferentOutputIndexCollisionCount' => 1,
            'digestSameGroupIndexCollisionCount' => 0,
            'digestDifferentGroupIndexCollisionCount' => 1,
            'digestMixedOutputTypeCollisionCount' => 1,
        ], $duplicateCounts);

        $t->same(6, $grouping['outputCount']);
        $t->same(6, $grouping['groupCount']);
        $t->same(1, $grouping['streamGroupCount']);
        $t->same([
            'display_data' => 3,
            'error' => 1,
            'execute_result' => 1,
            'stream' => 1,
        ], $grouping['outputTypeCounts']);
        $t->same([
            'display_data' => 3,
            'error' => 1,
            'execute_result' => 1,
        ], $grouping['richOutputKindCounts']);
        $t->same([
            'output' => 5,
            'stream' => 1,
        ], $grouping['groupKindCounts']);
        $t->same([
            'display_data' => 3,
            'error' => 1,
            'execute_result' => 1,
            'stream' => 1,
        ], $grouping['groupTypeCounts']);
        $t->same([
            'output-bytes-blocked' => 6,
            'output-error-metadata-only' => 1,
            'output-error-traceback-bytes-blocked' => 1,
            'output-mime-bundle-metadata-only' => 4,
            'output-stream-bytes-blocked' => 1,
        ], $document->attr('notebookOutputPolicyDiagnosticCounts'));

        $t->same(false, str_contains($serialized, 'secret cell zero display label'));
        $t->same(false, str_contains($serialized, 'secret cell zero execute text'));
        $t->same(false, str_contains($serialized, 'secret cell one display label'));
        $t->same(false, str_contains($serialized, 'secret cell two display text'));
        $t->same(false, str_contains($serialized, 'secret stream line'));
        $t->same(false, str_contains($serialized, 'secret error value'));
        $t->same(false, str_contains($serialized, 'secret traceback value'));
    },
];
