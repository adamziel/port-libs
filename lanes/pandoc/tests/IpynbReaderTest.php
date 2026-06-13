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
        $t->same(['kernelspec', 'language_info'], $document->attr('notebookMetadataKeys'));
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));
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
        $t->same(1, $code->attr('ipynbOutputMimeBundleCount'));
        $t->same(2, $code->attr('ipynbOutputBytePresenceCount'));
        $t->same(['stdout'], $code->attr('ipynbOutputStreamNames'));
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
        $t->contains('data-ipynb-diagnostics="output-bytes-blocked output-stream-bytes-blocked output-mime-bundle-metadata-only"', $html);
        $t->contains('data-ipynb-output-mime-types="text/plain"', $html);
        $t->contains('data-ipynb-output-byte-policy="metadata-only"', $html);
        $t->contains('data-ipynb-output-stream-names="stdout"', $html);
        $t->contains('<h1 id="notebook-import">Notebook import</h1>', $html);
        $t->contains('class="language-python"', $html);
        $t->contains('print(&quot;ready&quot;)', $html);
        $t->same(false, str_contains($html, 'iVBORw0KGgo='));
        $t->same(false, str_contains($html, '<Figure size 640x480>'));
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
        $t->same(['application/json', 'image/png'], $outputs[0]['mimeTypes']);
        $t->same(['image/png'], $outputs[0]['metadataKeys']);
        $t->same('blocked', $outputs[0]['byteExposure']);
        $t->same('execute_result', $outputs[1]['type']);
        $t->same(3, $outputs[1]['executionCount']);
        $t->same(['text/html', 'text/plain'], $outputs[1]['mimeTypes']);
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
