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
