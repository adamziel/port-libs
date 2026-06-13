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
];
