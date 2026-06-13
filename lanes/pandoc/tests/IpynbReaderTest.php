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

        $t->same(['line-array' => 1, 'string' => 3], $document->attr('notebookSourceShapeCounts'));
        $t->same(['crlf' => 1, 'lf' => 1, 'mixed' => 1, 'none' => 1], $document->attr('notebookSourceLineEndingStyles'));
        $t->same(['lf' => 3, 'crlf' => 2, 'cr' => 1], $document->attr('notebookSourceLineEndingCounts'));
        $t->same(2, $document->attr('notebookSourceTrailingNewlineCount'));
        $t->same(1, $document->attr('notebookEmptySourceCount'));

        $t->same('line-array', $cells[0]['sourceShape']);
        $t->same(2, $cells[0]['sourcePartCount']);
        $t->same(strlen(implode('', $lfArraySource)), $cells[0]['sourceBytes']);
        $t->same(2, $cells[0]['sourceLineCount']);
        $t->same('lf', $cells[0]['sourceLineEnding']);
        $t->same(['lf' => 2, 'crlf' => 0, 'cr' => 0], $cells[0]['sourceLineEndingCounts']);
        $t->same(true, $cells[0]['sourceTrailingNewline']);
        $t->same([
            'source-shape:line-array',
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
        $t->same('line-array', $firstCell->attr('ipynbSourceShape'));
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
