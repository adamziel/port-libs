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
