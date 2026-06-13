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
        $t->same('python3', $document->attr('notebookKernelName'));
        $t->same('python', $document->attr('notebookLanguage'));

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
        $t->same(['text/plain'], $code->attr('ipynbOutputMimeTypes'));
        $t->same(1, $code->attr('ipynbRichOutputUnsupportedCount'));
        $t->same('1', $code->attr('attributes')['data-ipynb-rich-output-unsupported-count']);
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
        $t->contains('data-ipynb-output-mime-types="text/plain"', $html);
        $t->contains('data-ipynb-rich-output-unsupported-count="1"', $html);
        $t->contains('<h1 id="notebook-import">Notebook import</h1>', $html);
        $t->contains('class="language-python"', $html);
        $t->contains('print(&quot;ready&quot;)', $html);
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
