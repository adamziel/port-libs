<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    return (new MarkdownReader())->read(<<<'MARKDOWN'
---
title: PDF Handoff Packet
author: [Migration Desk, Content Reviewer]
date: 2026-06-04
---

Reviewer formula $E = mc^2$ keeps raw source \cite{packet}.
MARKDOWN);
};

return [
    'plans latex pdf engine handoff without executing a tex engine' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'build/review-packet.pdf',
            'engineOptions' => ['-file-line-error'],
        ]);

        $t->same('pandoc-pdf-engine-handoff', $plan['kind']);
        $t->same('pdf', $plan['target']);
        $t->same(false, $plan['willExecute']);
        $t->same('xelatex', $plan['engine']);
        $t->same('latex', $plan['engineFamily']);
        $t->same('latex', $plan['intermediateFormat']);
        $t->same('build/review-packet.tex', $plan['sourceFile']);
        $t->same('build/review-packet.pdf', $plan['outputFile']);
        $t->same(['xelatex', '-halt-on-error', '-interaction=nonstopmode', '-file-line-error', 'build/review-packet.tex'], $plan['argv']);
        $t->contains('Reviewer formula $E = mc^2$ keeps raw source \\cite{packet}.', (string) $plan['sourceBytes']);
        $t->same(hash('sha256', (string) $plan['sourceBytes']), $plan['sourceSha256']);
        $t->same('PDF Handoff Packet', $plan['metadata']['title']);
        $t->same(['Migration Desk', 'Content Reviewer'], $plan['metadata']['author']);
        $t->same('2026-06-04', $plan['metadata']['date']);
        $t->contains('pdf-engine-not-executed', implode(',', $plan['diagnostics']));
        $t->contains('intermediate-source-rendered:latex', implode(',', $plan['diagnostics']));
    },

    'maps pandoc pdf engine families to bounded intermediate handoff argv' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $typst = $handoff->plan($document(), [
            'engine' => '/usr/local/bin/typst',
            'outputPath' => 'pdf/review.pdf',
            'source' => '#set page(width: 8.5in, height: 11in)',
            'engineOptions' => ['--font-path=fonts'],
        ]);
        $weasy = $handoff->plan($document(), [
            'engine' => 'weasyprint',
            'outputPath' => 'review.pdf',
            'source' => '<!doctype html><title>Review</title>',
        ]);
        $prince = $handoff->plan($document(), [
            'engine' => 'prince',
            'outputPath' => 'review.pdf',
            'source' => '<!doctype html><title>Review</title>',
        ]);
        $roff = $handoff->plan($document(), [
            'engine' => 'pdfroff',
            'outputPath' => 'review.pdf',
            'source' => '.TL' . "\n" . 'Review',
        ]);
        $context = $handoff->plan($document(), [
            'engine' => 'context',
            'outputPath' => 'context/review.pdf',
        ]);

        $t->same('typst', $typst['engine']);
        $t->same('typst', $typst['intermediateFormat']);
        $t->same(['--font-path=fonts'], $typst['engineOptions']);
        $t->same(['/usr/local/bin/typst', 'compile', '--font-path=fonts', 'pdf/review.typ', 'pdf/review.pdf'], $typst['argv']);
        $t->same('html', $weasy['engineFamily']);
        $t->same('html5', $weasy['intermediateFormat']);
        $t->same(['weasyprint', 'review.html', 'review.pdf'], $weasy['argv']);
        $t->same(['prince', 'review.html', '-o', 'review.pdf'], $prince['argv']);
        $t->same('roff', $roff['engineFamily']);
        $t->same('ms', $roff['intermediateFormat']);
        $t->same(['pdfroff', '-o', 'review.pdf', 'review.ms'], $roff['argv']);
        $t->same('context', $context['intermediateFormat']);
        $t->same(null, $context['sourceBytes']);
        $t->contains('intermediate-writer-pending:context', implode(',', $context['diagnostics']));
    },

    'plans pdf template variables headers and resource paths for source handoff' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/review.pdf',
            'templatePath' => 'templates/review.tex',
            'includeInHeader' => ['templates/header.tex', 'templates/fonts.tex'],
            'resourcePaths' => ['media', 'shared assets'],
            'variables' => [
                'documentclass' => 'scrartcl',
                'geometry' => ['margin=1in', 'includeheadfoot'],
                'colorlinks' => true,
                'draft' => false,
                'mainfont' => 'Source Serif 4',
            ],
        ]);

        $t->same('templates/review.tex', $plan['templateFile']);
        $t->same(['templates/header.tex', 'templates/fonts.tex'], $plan['includeInHeaderFiles']);
        $t->same(['templates/review.tex', 'templates/header.tex', 'templates/fonts.tex'], $plan['sourceArtifacts']);
        $t->same(['media', 'shared assets'], $plan['resourcePaths']);
        $t->same('PDF Handoff Packet', $plan['templateVariables']['title']);
        $t->same(['Migration Desk', 'Content Reviewer'], $plan['templateVariables']['author']);
        $t->same('scrartcl', $plan['templateVariables']['documentclass']);
        $t->same(['margin=1in', 'includeheadfoot'], $plan['templateVariables']['geometry']);
        $t->same(true, $plan['templateVariables']['colorlinks']);
        $t->same(false, $plan['templateVariables']['draft']);
        $t->same([
            '--template=templates/review.tex',
            '--include-in-header=templates/header.tex',
            '--include-in-header=templates/fonts.tex',
            '--resource-path=media:shared assets',
            '-V',
            'documentclass=scrartcl',
            '-V',
            'geometry=margin=1in',
            '-V',
            'geometry=includeheadfoot',
            '-V',
            'colorlinks=true',
            '-V',
            'draft=false',
            '-V',
            'mainfont=Source Serif 4',
        ], $plan['writerArguments']);
        $t->contains('pdf-template-supplied', implode(',', $plan['diagnostics']));
        $t->contains('pdf-include-in-header:2', implode(',', $plan['diagnostics']));
        $t->contains('pdf-resource-paths:2', implode(',', $plan['diagnostics']));
    },

    'plans and validates pdf resource dependency manifest without fetching media' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'MARKDOWN'
---
title: PDF Resource Packet
---

Cover image ![Cover frame](media/cover.png "Cover source").
Remote media ![Remote chart](https://example.test/media/chart.png).
MARKDOWN);
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document, [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/resources.pdf',
            'resourcePaths' => ['media', 'refs'],
            'resourceFiles' => ['refs/migration-log.bib', 'media/cover.png'],
        ]);

        $t->same(['media/cover.png', 'refs/migration-log.bib'], $plan['resourceFiles']);
        $t->same([
            [
                'path' => 'media/cover.png',
                'kind' => 'image',
                'sources' => ['explicit', 'document-image'],
                'title' => 'Cover source',
                'alt' => 'Cover frame',
            ],
            [
                'path' => 'refs/migration-log.bib',
                'kind' => 'resource',
                'sources' => ['explicit'],
                'title' => null,
                'alt' => null,
            ],
        ], $plan['resourceFileManifest']);
        $t->same(['https://example.test/media/chart.png'], $plan['remoteResourceReferences']);
        $t->same([], $plan['skippedResourceReferences']);
        $t->contains('pdf-resource-files:2', implode(',', $plan['diagnostics']));
        $t->contains('pdf-remote-resources:1', implode(',', $plan['diagnostics']));

        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $missing = $handoff->fakeRun($plan, [
            'files' => [
                'handoff/resources.pdf' => $pdfBytes,
            ],
        ]);
        $ok = $handoff->fakeRun($plan, [
            'files' => [
                'media/cover.png' => 'fake cover bytes',
                'refs/migration-log.bib' => '@book{migration-log,title={Migration Log}}',
                'handoff/resources.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(false, $missing['ok']);
        $t->same('missing-resource-file', $missing['reason']);
        $t->same(['media/cover.png', 'refs/migration-log.bib'], $missing['missingResourceFiles']);
        $t->contains('missing-resource-file:media/cover.png', implode(',', $missing['diagnostics']));
        $t->same(true, $ok['ok']);
        $t->same([
            'media/cover.png' => hash('sha256', 'fake cover bytes'),
            'refs/migration-log.bib' => hash('sha256', '@book{migration-log,title={Migration Log}}'),
        ], $ok['resourceArtifactsSha256']);
        $t->same([], $ok['missingResourceFiles']);
        $t->contains('resource-files-validated:2', implode(',', $ok['diagnostics']));
    },

    'plans latex engine log and sidecar artifact paths without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'latexmk',
            'outputPath' => 'handoff/review.pdf',
        ]);

        $t->same('handoff/review.log', $plan['engineLogFile']);
        $t->same([
            'handoff/review.log',
            'handoff/review.aux',
            'handoff/review.out',
            'handoff/review.toc',
            'handoff/review.fls',
            'handoff/review.fdb_latexmk',
        ], $plan['expectedEngineArtifacts']);
        $t->contains('pdf-engine-log:handoff/review.log', implode(',', $plan['diagnostics']));
        $t->contains('pdf-engine-artifacts:6', implode(',', $plan['diagnostics']));
    },

    'plans and validates latex recorder file dependencies without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/dependency.pdf',
            'engineOptions' => ['-file-line-error', '-recorder'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff with recorder file\n%%EOF\n";
        $fls = implode("\n", [
            'PWD /tmp/pandoc-pdf-work',
            'INPUT handoff/dependency.tex',
            'INPUT ./styles/review-header.tex',
            'INPUT refs/migration-log.bib',
            'INPUT /usr/share/texlive/texmf-dist/tex/latex/base/article.cls',
            'OUTPUT handoff/dependency.aux',
            'OUTPUT handoff/dependency.log',
            'OUTPUT handoff/dependency.pdf',
            '',
        ]);

        $missing = $handoff->fakeRun($plan, [
            'files' => [
                'handoff/dependency.fls' => $fls,
                'handoff/dependency.pdf' => $pdfBytes,
            ],
        ]);
        $ok = $handoff->fakeRun($plan, [
            'files' => [
                'styles/review-header.tex' => '\usepackage{fontspec}',
                'refs/migration-log.bib' => '@book{migration-log,title={Migration Log}}',
                'handoff/dependency.fls' => $fls,
                'handoff/dependency.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'styles/review-header.tex' => '\usepackage{fontspec}',
                    'refs/migration-log.bib' => '@book{migration-log,title={Migration Log}}',
                    'handoff/dependency.fls' => $fls,
                    'handoff/dependency.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->contains('handoff/dependency.fls', implode(',', $plan['expectedEngineArtifacts']));
        $t->contains('pdf-engine-recorder:handoff/dependency.fls', implode(',', $plan['diagnostics']));
        $t->same(false, $missing['ok']);
        $t->same('missing-engine-input-file', $missing['reason']);
        $t->same(['refs/migration-log.bib', 'styles/review-header.tex'], $missing['missingEngineInputFiles']);
        $t->contains('missing-engine-input-file:styles/review-header.tex', implode(',', $missing['diagnostics']));
        $t->same(true, $ok['ok']);
        $t->same(['handoff/dependency.fls' => hash('sha256', $fls)], $ok['engineDependencyArtifactsSha256']);
        $t->same([
            'handoff/dependency.tex',
            'refs/migration-log.bib',
            'styles/review-header.tex',
        ], $ok['engineInputFiles']);
        $t->same(['article.cls'], $ok['engineExternalInputFiles']);
        $t->same([
            'handoff/dependency.aux',
            'handoff/dependency.log',
            'handoff/dependency.pdf',
        ], $ok['engineOutputFiles']);
        $t->same([], $ok['missingEngineInputFiles']);
        $t->contains('engine-dependency-files:3', implode(',', $ok['diagnostics']));
        $t->contains('engine-external-input-files:1', implode(',', $ok['diagnostics']));
        $t->contains('engine-output-files:3', implode(',', $ok['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($ok['engineDependencyArtifactsSha256'], $sequence['finalEngineDependencyArtifactsSha256']);
        $t->same($ok['engineInputFiles'], $sequence['finalEngineInputFiles']);
        $t->same($ok['engineOutputFiles'], $sequence['finalEngineOutputFiles']);
    },

    'fake runner parses latex transcript include graph without recorder files' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/transcript.pdf',
            'engineOptions' => ['-file-line-error'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff with transcript inputs\n%%EOF\n";
        $log = implode("\n", [
            'This is XeTeX, Version 3.141592653',
            '(./handoff/transcript.tex',
            '(./styles/review-header.tex',
            '(/usr/share/texlive/texmf-dist/tex/latex/base/article.cls)',
            '(./media/logo.png)',
            'Output written on handoff/transcript.pdf (1 page, ' . strlen($pdfBytes) . ' bytes).',
            '',
        ]);

        $missing = $handoff->fakeRun($plan, [
            'files' => [
                'handoff/transcript.log' => $log,
                'handoff/transcript.pdf' => $pdfBytes,
            ],
        ]);
        $ok = $handoff->fakeRun($plan, [
            'files' => [
                'styles/review-header.tex' => '\usepackage{fontspec}',
                'media/logo.png' => 'fake logo bytes',
                'handoff/transcript.log' => $log,
                'handoff/transcript.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'styles/review-header.tex' => '\usepackage{fontspec}',
                    'media/logo.png' => 'fake logo bytes',
                    'handoff/transcript.log' => $log,
                    'handoff/transcript.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(false, $missing['ok']);
        $t->same('missing-engine-transcript-input-file', $missing['reason']);
        $t->same(['media/logo.png', 'styles/review-header.tex'], $missing['missingEngineTranscriptInputFiles']);
        $t->contains('missing-engine-transcript-input-file:styles/review-header.tex', implode(',', $missing['diagnostics']));
        $t->same(true, $ok['ok']);
        $t->same([
            'handoff/transcript.tex',
            'media/logo.png',
            'styles/review-header.tex',
        ], $ok['engineTranscriptInputFiles']);
        $t->same(['article.cls'], $ok['engineTranscriptExternalInputFiles']);
        $t->same([], $ok['missingEngineTranscriptInputFiles']);
        $t->contains('engine-transcript-input-files:3', implode(',', $ok['diagnostics']));
        $t->contains('engine-transcript-external-input-files:1', implode(',', $ok['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($ok['engineTranscriptInputFiles'], $sequence['finalEngineTranscriptInputFiles']);
        $t->same($ok['engineTranscriptExternalInputFiles'], $sequence['finalEngineTranscriptExternalInputFiles']);
    },

    'plans and parses synctex source map sidecars without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/review.pdf',
            'engineOptions' => ['-file-line-error', '-synctex=1'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff with source map\n%%EOF\n";
        $sourceMap = implode("\n", [
            'SyncTeX Version:1',
            'Input:1:handoff/review.tex',
            'Input:2:chapters/source-note.tex',
            'Output:pdf',
            'Content:',
            '!1',
            '{1',
            '[1,4:100,200:300,400',
            '(2,12:120,220',
            '(2,18:130,240',
            ']',
            '}',
            'Postamble:',
            'Count:3',
            '',
        ]);
        $sourceMapBytes = GzipStream::build($sourceMap, ['filename' => 'review.synctex']);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'handoff/review.tex' => (string) $plan['sourceBytes'],
                'handoff/review.synctex.gz' => $sourceMapBytes,
                'handoff/review.pdf' => $pdfBytes,
            ],
        ]);
        $stale = $handoff->fakeRun($plan, [
            'files' => [
                'handoff/review.synctex' => "SyncTeX Version:1\nInput:1:other-source.tex\nContent:\n[1,1:0,0\n",
                'handoff/review.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'handoff/review.synctex.gz' => $sourceMapBytes,
                    'handoff/review.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->contains('handoff/review.synctex.gz', implode(',', $plan['expectedEngineArtifacts']));
        $t->contains('pdf-source-map:handoff/review.synctex.gz', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same(['handoff/review.synctex.gz'], $result['sourceMapFiles']);
        $t->same(['handoff/review.synctex.gz' => hash('sha256', $sourceMapBytes)], $result['sourceMapArtifactsSha256']);
        $t->same([
            ['tag' => 1, 'path' => 'handoff/review.tex'],
            ['tag' => 2, 'path' => 'chapters/source-note.tex'],
        ], $result['sourceMapInputs']);
        $t->same(['chapters/source-note.tex', 'handoff/review.tex'], $result['sourceMapInputFiles']);
        $t->same([
            ['tag' => 1, 'path' => 'handoff/review.tex', 'minLine' => 4, 'maxLine' => 4, 'references' => 1],
            ['tag' => 2, 'path' => 'chapters/source-note.tex', 'minLine' => 12, 'maxLine' => 18, 'references' => 2],
        ], $result['sourceMapLineRanges']);
        $t->same([], $result['sourceMapExternalInputs']);
        $t->contains('source-map-files:1', implode(',', $result['diagnostics']));
        $t->contains('source-map-inputs:2', implode(',', $result['diagnostics']));
        $t->contains('source-map-line-ranges:2', implode(',', $result['diagnostics']));
        $t->same(false, $stale['ok']);
        $t->same('source-map-source-missing', $stale['reason']);
        $t->contains('source-map-source-missing:handoff/review.tex', implode(',', $stale['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same(['handoff/review.synctex.gz' => hash('sha256', $sourceMapBytes)], $sequence['finalSourceMapArtifactsSha256']);
        $t->same([
            ['tag' => 1, 'path' => 'handoff/review.tex', 'minLine' => 4, 'maxLine' => 4, 'references' => 1],
            ['tag' => 2, 'path' => 'chapters/source-note.tex', 'minLine' => 12, 'maxLine' => 18, 'references' => 2],
        ], $sequence['finalSourceMapLineRanges']);
    },

    'fake runner validates staged source and pdf-like output bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'lualatex', 'outputPath' => 'review.pdf']);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $result = $handoff->fakeRun($plan, [
            'stdout' => 'fake runner accepted argv',
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same('ok', $result['status']);
        $t->same(null, $result['reason']);
        $t->same('lualatex', $result['engine']);
        $t->same(strlen($pdfBytes), $result['bytes']);
        $t->same(hash('sha256', (string) $plan['sourceBytes']), $result['sourceSha256']);
        $t->same(hash('sha256', $pdfBytes), $result['pdfSha256']);
        $t->same('fake runner accepted argv', $result['stdout']);
        $t->contains('fake-runner-no-execution', implode(',', $result['diagnostics']));
    },

    'fake runner validates required pdf source artifacts without rendering' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'lualatex',
            'outputPath' => 'review.pdf',
            'templatePath' => 'templates/review.tex',
            'includeInHeader' => 'templates/header.tex',
        ]);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";

        $missing = $handoff->fakeRun($plan, [
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.pdf' => $pdfBytes,
            ],
        ]);
        $ok = $handoff->fakeRun($plan, [
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'templates/review.tex' => '$body$',
                'templates/header.tex' => '\usepackage{fontspec}',
                'review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(false, $missing['ok']);
        $t->same('missing-source-artifact', $missing['reason']);
        $t->contains('missing-source-artifact:templates/review.tex', implode(',', $missing['diagnostics']));
        $t->same(true, $ok['ok']);
        $t->same(hash('sha256', '$body$'), $ok['sourceArtifactsSha256']['templates/review.tex']);
        $t->same(hash('sha256', '\usepackage{fontspec}'), $ok['sourceArtifactsSha256']['templates/header.tex']);
    },

    'fake runner classifies engine logs sidecars warnings and rerun signals' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'lualatex', 'outputPath' => 'review.pdf']);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $log = implode("\n", [
            'This is LuaHBTeX, Version 1.18.0',
            "LaTeX Warning: Citation `packet' on page 1 undefined on input line 4.",
            "Package rerunfilecheck Warning: File `review.out' has changed.",
            'LaTeX Warning: Label(s) may have changed. Rerun to get cross-references right.',
            'Output written on review.pdf (1 page, ' . strlen($pdfBytes) . ' bytes).',
            '',
        ]);
        $result = $handoff->fakeRun($plan, [
            'stderr' => "warning: reviewer font substituted\n",
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.aux' => "\\relax\n",
                'review.out' => '',
                'review.log' => $log,
                'review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(['review.aux', 'review.log', 'review.out'], array_keys($result['producedArtifactsSha256']));
        $t->same(hash('sha256', $log), $result['producedArtifactsSha256']['review.log']);
        $t->same(['review.log'], $result['engineLogFiles']);
        $t->same(true, $result['rerunNeeded']);
        $t->same([], $result['engineErrors']);
        $t->contains('Citation `packet\' on page 1 undefined', implode("\n", $result['engineWarnings']));
        $t->contains('reviewer font substituted', implode("\n", $result['engineWarnings']));
        $t->contains('engine-log-warnings:4', implode(',', $result['diagnostics']));
        $t->contains('engine-rerun-needed', implode(',', $result['diagnostics']));
        $t->contains('produced-engine-artifacts:3', implode(',', $result['diagnostics']));
    },

    'fake runner classifies bibliography sidecars and biber rerun diagnostics' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'review.pdf']);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $blg = implode("\n", [
            'This is Biber 2.19',
            'Biber warning: Entry missing-key not found in bibliography',
            'Package biblatex Warning: Please (re)run Biber on the file: review and rerun LaTeX afterwards.',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'review.pdf' => $pdfBytes,
                'review.bcf' => '<bcf:controlfile />',
                'review.run.xml' => '<requests />',
                'review.bbl' => "\\begin{thebibliography}{1}\n\\end{thebibliography}\n",
                'review.blg' => $blg,
            ],
        ]);
        $failed = $handoff->fakeRun($plan, [
            'files' => [
                'review.pdf' => $pdfBytes,
                'review.blg' => "Biber ERROR - Cannot find 'refs.bib'\n",
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'review.pdf' => $pdfBytes,
                    'review.bcf' => '<bcf:controlfile />',
                    'review.blg' => $blg,
                ],
            ],
            [
                'files' => [
                    'review.pdf' => $pdfBytes,
                    'review.bbl' => "\\begin{thebibliography}{1}\n\\end{thebibliography}\n",
                    'review.blg' => "This is Biber 2.19\n",
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(['review.bbl', 'review.bcf', 'review.blg', 'review.run.xml'], array_keys($result['bibliographyArtifactsSha256']));
        $t->same(['review.blg'], $result['bibliographyLogFiles']);
        $t->contains('Entry missing-key not found', implode("\n", $result['bibliographyWarnings']));
        $t->same([], $result['bibliographyErrors']);
        $t->same(true, $result['bibliographyNeeded']);
        $t->same(true, $result['rerunNeeded']);
        $t->contains('bibliography-sidecars:4', implode(',', $result['diagnostics']));
        $t->contains('bibliography-log-files:1', implode(',', $result['diagnostics']));
        $t->contains('bibliography-warnings:2', implode(',', $result['diagnostics']));
        $t->contains('bibliography-run-needed', implode(',', $result['diagnostics']));
        $t->same(false, $failed['ok']);
        $t->same('bibliography-log-error', $failed['reason']);
        $t->contains("Cannot find 'refs.bib'", implode("\n", $failed['bibliographyErrors']));
        $t->same(true, $sequence['ok']);
        $t->same(false, $sequence['rerunNeeded']);
        $t->same(['review.bbl', 'review.blg'], array_keys($sequence['finalBibliographyArtifactsSha256']));
        $t->contains('Entry missing-key not found', implode("\n", $sequence['bibliographyWarnings']));
        $t->contains('fake-runner-attempt-bibliography-needed:1', implode(',', $sequence['diagnostics']));
        $t->contains('fake-runner-final-bibliography-cleared', implode(',', $sequence['diagnostics']));
    },

    'fake runner records engine declared pdf output metrics when artifact matches' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/review.pdf']);
        $pdfBytes = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $log = 'Output written on packets/review.pdf (2 pages, ' . strlen($pdfBytes) . " bytes).\n";
        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/review.log' => $log,
                'packets/review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same('packets/review.pdf', $result['declaredOutputFile']);
        $t->same(2, $result['declaredOutputPages']);
        $t->same(strlen($pdfBytes), $result['declaredOutputBytes']);
        $t->same(true, $result['pdfTrailerComplete']);
        $t->contains('engine-output-file:packets/review.pdf', implode(',', $result['diagnostics']));
        $t->contains('engine-output-pages:2', implode(',', $result['diagnostics']));
        $t->contains('engine-output-bytes:' . strlen($pdfBytes), implode(',', $result['diagnostics']));
    },

    'fake runner extracts bounded pdf trailer revisions and startxref metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/incremental.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Title (Initial export) >>',
            'endobj',
            'xref',
            '0 5',
            'trailer',
            '<< /Size 5 /Root 1 0 R /Info 4 0 R /ID [<00112233445566778899aabbccddeeff> <00112233445566778899aabbccddeeff>] >>',
            'startxref',
            '128',
            '%%EOF',
            '5 0 obj',
            '<< /Title (Reviewer note appended) >>',
            'endobj',
            'xref',
            '5 1',
            'trailer',
            '<< /Size 6 /Root 1 0 R /Info 5 0 R /Prev 128 /ID [<00112233445566778899aabbccddeeff> <ffeeddccbbaa99887766554433221100>] >>',
            'startxref',
            '512',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/incremental.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/incremental.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(true, $result['pdfTrailerComplete']);
        $t->same(2, $result['pdfTrailerCount']);
        $t->same(true, $result['pdfIncrementalUpdates']);
        $t->same([128, 512], $result['pdfStartXrefOffsets']);
        $t->same([
            [
                'revision' => 1,
                'size' => 5,
                'root' => '1 0 R',
                'info' => '4 0 R',
                'encrypt' => null,
                'prev' => null,
                'startxref' => 128,
                'id' => [
                    '00112233445566778899AABBCCDDEEFF',
                    '00112233445566778899AABBCCDDEEFF',
                ],
            ],
            [
                'revision' => 2,
                'size' => 6,
                'root' => '1 0 R',
                'info' => '5 0 R',
                'encrypt' => null,
                'prev' => 128,
                'startxref' => 512,
                'id' => [
                    '00112233445566778899AABBCCDDEEFF',
                    'FFEEDDCCBBAA99887766554433221100',
                ],
            ],
        ], $result['pdfTrailerRevisions']);
        $t->contains('pdf-byte-trailers:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-startxref:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-incremental-updates', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same(2, $sequence['finalPdfTrailerCount']);
        $t->same(true, $sequence['finalPdfIncrementalUpdates']);
        $t->same([128, 512], $sequence['finalPdfStartXrefOffsets']);
        $t->same($result['pdfTrailerRevisions'], $sequence['finalPdfTrailerRevisions']);
    },

    'fake runner extracts bounded pdf page tree and outline titles from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/review.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>',
            'endobj',
            '6 0 obj',
            '<< /Title (Migration packet) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 7 0 R >>',
            'endobj',
            '7 0 obj',
            '<< /Title <FEFF00460069006E0061006C00200070006100670065> /Parent 5 0 R /Dest [4 0 R /Fit] /Prev 6 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);
        $log = 'Output written on packets/review.pdf (2 pages, ' . strlen($pdfBytes) . " bytes).\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/review.log' => $log,
                'packets/review.pdf' => $pdfBytes,
            ],
        ]);
        $mismatch = $handoff->fakeRun($plan, [
            'files' => [
                'packets/review.log' => 'Output written on packets/review.pdf (1 page, ' . strlen($pdfBytes) . " bytes).\n",
                'packets/review.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/review.log' => $log,
                    'packets/review.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(2, $result['pdfPageCount']);
        $t->same(['Migration packet', 'Final page'], $result['pdfOutlineTitles']);
        $t->contains('pdf-byte-page-count:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-items:2', implode(',', $result['diagnostics']));
        $t->same(false, $mismatch['ok']);
        $t->same('pdf-output-page-mismatch', $mismatch['reason']);
        $t->contains('engine-output-page-mismatch:1:2', implode(',', $mismatch['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same(2, $sequence['finalPdfPageCount']);
        $t->same(['Migration packet', 'Final page'], $sequence['finalPdfOutlineTitles']);
    },

    'fake runner extracts bounded pdf page boxes and rotations from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/geometry.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /MediaBox [0 0 612 792] /CropBox [18 18 594 774] /Rotate 0 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /TrimBox [36 36 576 756] /BleedBox [9 9 603 783] /ArtBox [72 72 540 720] /Rotate 90 >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /CropBox [24 24 768 588] /Rotate -90 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/geometry.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/geometry.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same([
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'mediaBox' => [0.0, 0.0, 612.0, 792.0],
                'cropBox' => [18.0, 18.0, 594.0, 774.0],
                'bleedBox' => [9.0, 9.0, 603.0, 783.0],
                'trimBox' => [36.0, 36.0, 576.0, 756.0],
                'artBox' => [72.0, 72.0, 540.0, 720.0],
                'rotation' => 90,
                'inherited' => ['cropBox', 'mediaBox'],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'mediaBox' => [0.0, 0.0, 792.0, 612.0],
                'cropBox' => [24.0, 24.0, 768.0, 588.0],
                'bleedBox' => null,
                'trimBox' => null,
                'artBox' => null,
                'rotation' => 270,
                'inherited' => [],
            ],
        ], $result['pdfPageBoxes']);
        $t->same([1 => 90, 2 => 270], $result['pdfPageRotations']);
        $t->contains('pdf-byte-page-boxes:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-rotations:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($result['pdfPageBoxes'], $sequence['finalPdfPageBoxes']);
        $t->same([1 => 90, 2 => 270], $sequence['finalPdfPageRotations']);
    },

    'fake runner extracts bounded pdf page label ranges from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-labels.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /PageLabels 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 5 /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Kids [9 0 R 10 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Nums [0 << /S /r /P (front-) /St 3 >> 2 << /S /D /P (Chapter ) /St 1 >>] >>',
            'endobj',
            '10 0 obj',
            '<< /Nums [4 << /S /A /P (Appendix-) /St 27 >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-labels.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-labels.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'pageIndex' => 0,
                'pageNumber' => 1,
                'style' => 'r',
                'styleLabel' => 'lower-roman',
                'prefix' => 'front-',
                'start' => 3,
                'firstLabel' => 'front-iii',
                'source' => 'catalog.PageLabels.Kids.9 0 R',
            ],
            [
                'pageIndex' => 2,
                'pageNumber' => 3,
                'style' => 'D',
                'styleLabel' => 'decimal',
                'prefix' => 'Chapter ',
                'start' => 1,
                'firstLabel' => 'Chapter 1',
                'source' => 'catalog.PageLabels.Kids.9 0 R',
            ],
            [
                'pageIndex' => 4,
                'pageNumber' => 5,
                'style' => 'A',
                'styleLabel' => 'upper-alpha',
                'prefix' => 'Appendix-',
                'start' => 27,
                'firstLabel' => 'Appendix-AA',
                'source' => 'catalog.PageLabels.Kids.10 0 R',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageLabels']);
        $t->contains('pdf-byte-page-labels:3', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageLabels']);
    },

    'fake runner extracts bounded pdf document info and catalog language metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/metadata.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Title (PDF Review Packet) /Author <FEFF004D006900670072006100740069006F006E0020004400650073006B> /Subject (Migration review) /Keywords (wordpress, migration) /Creator (Pandoc native handoff) /Producer (LuaHBTeX) /CreationDate (D:20260605050300Z) /ModDate (D:20260605050400Z) /Trapped /False >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Info 8 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/metadata.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/metadata.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same([
            'Title' => 'PDF Review Packet',
            'Author' => 'Migration Desk',
            'Subject' => 'Migration review',
            'Keywords' => 'wordpress, migration',
            'Creator' => 'Pandoc native handoff',
            'Producer' => 'LuaHBTeX',
            'CreationDate' => 'D:20260605050300Z',
            'ModDate' => 'D:20260605050400Z',
            'Trapped' => 'False',
        ], $result['pdfDocumentInfo']);
        $t->same('en-US', $result['pdfLanguage']);
        $t->contains('pdf-byte-document-info:9', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-language:en-US', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($result['pdfDocumentInfo'], $sequence['finalPdfDocumentInfo']);
        $t->same('en-US', $sequence['finalPdfLanguage']);
    },

    'fake runner extracts bounded pdf xmp metadata and pdfa identification from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/xmp.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">',
            '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">PDF Review Packet</rdf:li></rdf:Alt></dc:title>',
            '<dc:creator><rdf:Seq><rdf:li>Migration Desk</rdf:li><rdf:li>Content Reviewer</rdf:li></rdf:Seq></dc:creator>',
            '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Migration review metadata</rdf:li></rdf:Alt></dc:description>',
            '<dc:format>application/pdf</dc:format>',
            '<xmp:CreatorTool>Pandoc native handoff</xmp:CreatorTool>',
            '<xmp:CreateDate>2026-06-05T07:41:23Z</xmp:CreateDate>',
            '<xmp:ModifyDate>2026-06-05T07:42:00Z</xmp:ModifyDate>',
            '<xmp:MetadataDate>2026-06-05T07:42:10Z</xmp:MetadataDate>',
            '<xmpMM:DocumentID>uuid:pdf-review-packet</xmpMM:DocumentID>',
            '<xmpMM:InstanceID>uuid:pdf-review-packet-v2</xmpMM:InstanceID>',
            '<pdfaid:part>2</pdfaid:part>',
            '<pdfaid:conformance>B</pdfaid:conformance>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R /Lang (en-US) >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . ' >>',
            'stream',
            $xmp,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/xmp.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/xmp.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'packetBytes' => strlen($xmp),
            'packetSha256' => hash('sha256', $xmp),
            'title' => 'PDF Review Packet',
            'description' => 'Migration review metadata',
            'format' => 'application/pdf',
            'creatorTool' => 'Pandoc native handoff',
            'createDate' => '2026-06-05T07:41:23Z',
            'modifyDate' => '2026-06-05T07:42:00Z',
            'metadataDate' => '2026-06-05T07:42:10Z',
            'documentId' => 'uuid:pdf-review-packet',
            'instanceId' => 'uuid:pdf-review-packet-v2',
            'creators' => ['Migration Desk', 'Content Reviewer'],
            'pdfaIdentification' => [
                'part' => '2',
                'conformance' => 'B',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:13', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-pdfa:2:B', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner extracts bounded pdf output intent and profile metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/output-intent.pdf']);
        $iccProfile = "fake sRGB ICC profile bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OutputIntents [8 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (CGATS TR 001) /RegistryName (https://www.color.org) /Info (Print review intent) >>] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /OutputCondition (sRGB display profile) /RegistryName (http://www.color.org) /Info (sRGB review profile) /DestOutputProfile 9 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /N 3 /Alternate /DeviceRGB /Length ' . strlen($iccProfile) . ' >>',
            'stream',
            $iccProfile,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/output-intent.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/output-intent.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'type' => 'OutputIntent',
                'subtype' => 'GTS_PDFA1',
                'outputConditionIdentifier' => 'sRGB IEC61966-2.1',
                'outputCondition' => 'sRGB display profile',
                'registryName' => 'http://www.color.org',
                'info' => 'sRGB review profile',
                'destOutputProfile' => '9 0 R',
                'profileComponents' => 3,
                'profileAlternate' => 'DeviceRGB',
                'profileBytes' => strlen($iccProfile),
                'profileSha256' => hash('sha256', $iccProfile),
                'profileSkipped' => null,
            ],
            [
                'type' => 'OutputIntent',
                'subtype' => 'GTS_PDFX',
                'outputConditionIdentifier' => 'CGATS TR 001',
                'outputCondition' => null,
                'registryName' => 'https://www.color.org',
                'info' => 'Print review intent',
                'destOutputProfile' => null,
                'profileComponents' => null,
                'profileAlternate' => null,
                'profileBytes' => null,
                'profileSha256' => null,
                'profileSkipped' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfOutputIntents']);
        $t->contains('pdf-byte-output-intents:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-output-profiles:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfOutputIntents']);
    },

    'fake runner extracts bounded pdf catalog presentation preferences from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/presentation.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /PageLayout /TwoPageRight /PageMode /UseOutlines /OpenAction [3 0 R /FitH 720] /ViewerPreferences << /DisplayDocTitle true /HideToolbar true /Direction /L2R /PrintScaling /None /NumCopies 2 >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/presentation.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/presentation.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same('TwoPageRight', $result['pdfPageLayout']);
        $t->same('UseOutlines', $result['pdfPageMode']);
        $t->same([
            'type' => 'destination',
            'pageObject' => '3 0 R',
            'fit' => 'FitH',
        ], $result['pdfOpenAction']);
        $t->same([
            'HideToolbar' => true,
            'DisplayDocTitle' => true,
            'Direction' => 'L2R',
            'PrintScaling' => 'None',
            'NumCopies' => 2,
        ], $result['pdfViewerPreferences']);
        $t->contains('pdf-byte-page-layout:TwoPageRight', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-mode:UseOutlines', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-open-action:destination', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-viewer-preferences:5', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same('TwoPageRight', $sequence['finalPdfPageLayout']);
        $t->same('UseOutlines', $sequence['finalPdfPageMode']);
        $t->same($result['pdfOpenAction'], $sequence['finalPdfOpenAction']);
        $t->same($result['pdfViewerPreferences'], $sequence['finalPdfViewerPreferences']);
    },

    'fake runner extracts bounded pdf named destination name trees from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/named-dests.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R /Dests 12 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Dests 9 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Names [(intro) [3 0 R /FitH 720] <FEFF0049006D0070006F0072007400200063006800650063006B006C006900730074> 10 0 R] >>',
            'endobj',
            '10 0 obj',
            '[4 0 R /XYZ 0 792 0]',
            'endobj',
            '11 0 obj',
            '<< /D [4 0 R /FitV 0] >>',
            'endobj',
            '12 0 obj',
            '<< /Review 11 0 R /legacy [3 0 R /Fit] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/named-dests.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/named-dests.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'name' => 'Import checklist',
                'source' => 'catalog.Names.Dests',
                'target' => null,
                'pageObject' => '4 0 R',
                'fit' => 'XYZ',
            ],
            [
                'name' => 'Review',
                'source' => 'catalog.Dests',
                'target' => null,
                'pageObject' => '4 0 R',
                'fit' => 'FitV',
            ],
            [
                'name' => 'intro',
                'source' => 'catalog.Names.Dests',
                'target' => null,
                'pageObject' => '3 0 R',
                'fit' => 'FitH',
            ],
            [
                'name' => 'legacy',
                'source' => 'catalog.Dests',
                'target' => null,
                'pageObject' => '3 0 R',
                'fit' => 'Fit',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfNamedDestinations']);
        $t->contains('pdf-byte-named-destinations:4', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfNamedDestinations']);
    },

    'fake runner extracts bounded pdf tagging and structure-root metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/tagged.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo 8 0 R /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Marked true /UserProperties true /Suspects false >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /RoleMap << /H1 /H /Aside /Sect /Figure /Figure >> /ParentTree 12 0 R /ParentTreeNextKey 5 /IDTree 13 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /H1 /P 9 0 R /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /P /P 9 0 R /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /Nums [0 [10 0 R] 1 [11 0 R]] >>',
            'endobj',
            '13 0 obj',
            '<< /Names [(intro) 10 0 R (body) 11 0 R] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/tagged.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/tagged.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'marked' => true,
            'userProperties' => true,
            'suspects' => false,
            'structTreeRoot' => '9 0 R',
            'roleMap' => [
                'Aside' => 'Sect',
                'Figure' => 'Figure',
                'H1' => 'H',
            ],
            'structureChildren' => 2,
            'parentTree' => '12 0 R',
            'parentTreeNextKey' => 5,
            'idTree' => '13 0 R',
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfTaggingMetadata']);
        $t->contains('pdf-byte-tagging-metadata:9', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-tagged', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-root:9 0 R', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-role-map:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-children:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfTaggingMetadata']);
    },

    'fake runner extracts bounded pdf annotation links and embedded file names from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/review.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names [(review-assets.zip) 6 0 R] >> >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Link /A << /S /URI /URI (https://example.test/review?id=7) >> >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /FileAttachment /FS 6 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Filespec /F (review-assets.zip) /UF <FEFF007200650076006900650077002D006100730073006500740073002E007A00690070> /EF << /F 7 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fzip >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/review.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/review.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(['FileAttachment' => 1, 'Link' => 1], $result['pdfAnnotationTypes']);
        $t->same(['https://example.test/review?id=7'], $result['pdfLinkTargets']);
        $t->same(['review-assets.zip'], $result['pdfEmbeddedFileNames']);
        $t->contains('pdf-byte-annotations:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-link-targets:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-files:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same(['FileAttachment' => 1, 'Link' => 1], $sequence['finalPdfAnnotationTypes']);
        $t->same(['https://example.test/review?id=7'], $sequence['finalPdfLinkTargets']);
        $t->same(['review-assets.zip'], $sequence['finalPdfEmbeddedFileNames']);
    },

    'fake runner extracts bounded pdf embedded file metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/embedded-files.pdf']);
        $attachmentBytes = "fake embedded review assets\n";
        $filteredBytes = "compressed review summary";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names [(review-assets.zip) 6 0 R] >> >> /AF [8 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [5 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /FileAttachment /FS 6 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Filespec /F (review-assets.zip) /UF <FEFF007200650076006900650077002D006100730073006500740073002E007A00690070> /Desc (Review attachment package) /AFRelationship /Data /EF << /F 7 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fzip /Params << /Size ' . strlen($attachmentBytes) . ' /ModDate (D:20260605120000Z) /CheckSum <00112233445566778899aabbccddeeff> >> /Length ' . strlen($attachmentBytes) . ' >>',
            'stream',
            $attachmentBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /Filespec /F (review-summary.txt) /Desc (Plain summary copy) /AFRelationship /Source /EF << /F 9 0 R >> >>',
            'endobj',
            '9 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fplain /Filter /FlateDecode /Params << /Size 14 >> /Length ' . strlen($filteredBytes) . ' >>',
            'stream',
            $filteredBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/embedded-files.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/embedded-files.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'name' => 'review-assets.zip',
                'unicodeName' => 'review-assets.zip',
                'description' => 'Review attachment package',
                'afRelationship' => 'Data',
                'filespec' => '6 0 R',
                'embeddedFile' => '7 0 R',
                'subtype' => 'application/zip',
                'size' => strlen($attachmentBytes),
                'modDate' => 'D:20260605120000Z',
                'checksum' => '00112233445566778899AABBCCDDEEFF',
                'streamBytes' => strlen($attachmentBytes),
                'streamSha256' => hash('sha256', $attachmentBytes),
                'streamSkipped' => null,
                'source' => 'catalog.Names.EmbeddedFiles',
            ],
            [
                'name' => 'review-summary.txt',
                'unicodeName' => null,
                'description' => 'Plain summary copy',
                'afRelationship' => 'Source',
                'filespec' => '8 0 R',
                'embeddedFile' => '9 0 R',
                'subtype' => 'text/plain',
                'size' => 14,
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => strlen($filteredBytes),
                'streamSha256' => null,
                'streamSkipped' => 'filtered',
                'source' => 'catalog.AF',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same(['review-assets.zip', 'review-summary.txt'], $result['pdfEmbeddedFileNames']);
        $t->same($expected, $result['pdfEmbeddedFiles']);
        $t->contains('pdf-byte-embedded-files:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-streams:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfEmbeddedFiles']);
    },

    'fake runner extracts bounded pdf acroform field metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/forms.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R 6 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /TU (Reviewer name) /TM (reviewer_name) /V <FEFF004D006900670072006100740069006F006E0020004400650073006B> /DV () /Ff 4098 >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Btn /T (approved) /V /Yes /DV /Off /Ff 2 >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Ch /T (routing.queue) /V (Archive) /Opt [(Migration) (Archive)] /Ff 131072 >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R 6 0 R] /NeedAppearances true >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/forms.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/forms.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(['button' => 1, 'choice' => 1, 'text' => 1], $result['pdfFormFieldTypes']);
        $t->same([
            [
                'name' => 'approved',
                'type' => 'Btn',
                'typeLabel' => 'button',
                'alternateName' => null,
                'mappingName' => null,
                'value' => 'Yes',
                'defaultValue' => 'Off',
                'flags' => 2,
                'flagNames' => ['required'],
                'options' => [],
            ],
            [
                'name' => 'reviewer.name',
                'type' => 'Tx',
                'typeLabel' => 'text',
                'alternateName' => 'Reviewer name',
                'mappingName' => 'reviewer_name',
                'value' => 'Migration Desk',
                'defaultValue' => null,
                'flags' => 4098,
                'flagNames' => ['required', 'multiline'],
                'options' => [],
            ],
            [
                'name' => 'routing.queue',
                'type' => 'Ch',
                'typeLabel' => 'choice',
                'alternateName' => null,
                'mappingName' => null,
                'value' => 'Archive',
                'defaultValue' => null,
                'flags' => 131072,
                'flagNames' => ['combo'],
                'options' => ['Migration', 'Archive'],
            ],
        ], $result['pdfFormFields']);
        $t->contains('pdf-byte-form-fields:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-types:3', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($result['pdfFormFields'], $sequence['finalPdfFormFields']);
        $t->same($result['pdfFormFieldTypes'], $sequence['finalPdfFormFieldTypes']);
    },

    'fake runner extracts bounded pdf active actions and javascript hashes from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/active.pdf']);
        $catalogScript = 'app.alert("Review packet requires active-content review")';
        $pageScript = 'this.print({bUI:false});';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R /Names << /JavaScript << /Names [(ReviewOpen) 9 0 R] >> >> /AA << /WC 10 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA << /O 11 0 R >> /Annots [12 0 R] >>',
            'endobj',
            '8 0 obj',
            '<< /S /Named /N /Print >>',
            'endobj',
            '9 0 obj',
            '<< /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $catalogScript) . ') >>',
            'endobj',
            '10 0 obj',
            '<< /S /Launch /F (review-helper.exe) >>',
            'endobj',
            '11 0 obj',
            '<< /S /JavaScript /JS <' . strtoupper(bin2hex($pageScript)) . '> >>',
            'endobj',
            '12 0 obj',
            '<< /Type /Annot /Subtype /Screen /A << /S /Rendition /OP 4 >> /AA << /PO << /S /SubmitForm /F (https://example.test/review/submit) >> >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/active.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/active.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'annotation:12 0 R.A',
                'type' => 'Rendition',
                'target' => null,
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'annotation:12 0 R.AA.PO',
                'type' => 'SubmitForm',
                'target' => 'https://example.test/review/submit',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.AA.WC',
                'type' => 'Launch',
                'target' => 'review-helper.exe',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.Names.JavaScript.ReviewOpen',
                'type' => 'JavaScript',
                'target' => 'ReviewOpen',
                'scriptBytes' => strlen($catalogScript),
                'scriptSha256' => hash('sha256', $catalogScript),
            ],
            [
                'source' => 'catalog.OpenAction',
                'type' => 'Named',
                'target' => 'Print',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'page:3 0 R.AA.O',
                'type' => 'JavaScript',
                'target' => null,
                'scriptBytes' => strlen($pageScript),
                'scriptSha256' => hash('sha256', $pageScript),
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfActiveActions']);
        $t->same([
            'JavaScript' => 2,
            'Launch' => 1,
            'Named' => 1,
            'Rendition' => 1,
            'SubmitForm' => 1,
        ], $result['pdfActiveActionTypes']);
        $t->contains('pdf-byte-active-actions:6', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-types:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-type:JavaScript:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfActiveActions']);
        $t->same($result['pdfActiveActionTypes'], $sequence['finalPdfActiveActionTypes']);
    },

    'fake runner flags encrypted pdf output permission dictionaries without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/protected.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Filter /Standard /V 4 /R 4 /Length 128 /P -44 /EncryptMetadata false >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Encrypt 8 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/protected.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/protected.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(false, $result['ok']);
        $t->same('pdf-output-encrypted', $result['reason']);
        $t->same(true, $result['pdfEncrypted']);
        $t->same('Standard', $result['pdfEncryptionFilter']);
        $t->same(4, $result['pdfEncryptionVersion']);
        $t->same(4, $result['pdfEncryptionRevision']);
        $t->same(128, $result['pdfEncryptionLength']);
        $t->same(-44, $result['pdfPermissionInteger']);
        $t->same(false, $result['pdfEncryptMetadata']);
        $t->same([
            'printLowQuality' => true,
            'modify' => false,
            'copy' => true,
            'annotate' => false,
            'fillForms' => true,
            'extractAccessibility' => true,
            'assemble' => true,
            'printHighQuality' => true,
        ], $result['pdfPermissionFlags']);
        $t->contains('pdf-output-encrypted', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-filter:Standard', implode(',', $result['diagnostics']));
        $t->contains('pdf-permission-flags:8', implode(',', $result['diagnostics']));
        $t->same(false, $sequence['ok']);
        $t->same(true, $sequence['finalPdfEncrypted']);
        $t->same('Standard', $sequence['finalPdfEncryptionFilter']);
        $t->same($result['pdfPermissionFlags'], $sequence['finalPdfPermissionFlags']);
    },

    'fake runner rejects truncated stale or mismatched pdf output artifacts' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'review.pdf']);
        $completePdf = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $staleBytes = strlen($completePdf) + 7;

        $truncated = $handoff->fakeRun($plan, [
            'files' => [
                'review.pdf' => "%PDF-1.7\n% missing trailer\n",
            ],
        ]);
        $stale = $handoff->fakeRun($plan, [
            'files' => [
                'review.log' => 'Output written on review.pdf (1 page, ' . $staleBytes . " bytes).\n",
                'review.pdf' => $completePdf,
            ],
        ]);
        $wrongPath = $handoff->fakeRun($plan, [
            'files' => [
                'review.log' => 'Output written on other.pdf (1 page, ' . strlen($completePdf) . " bytes).\n",
                'review.pdf' => $completePdf,
            ],
        ]);

        $t->same(false, $truncated['ok']);
        $t->same('truncated-pdf-output', $truncated['reason']);
        $t->same(false, $truncated['pdfTrailerComplete']);
        $t->contains('pdf-output-truncated', implode(',', $truncated['diagnostics']));
        $t->same(false, $stale['ok']);
        $t->same('pdf-output-byte-mismatch', $stale['reason']);
        $t->same($staleBytes, $stale['declaredOutputBytes']);
        $t->contains('engine-output-byte-mismatch:' . $staleBytes . ':' . strlen($completePdf), implode(',', $stale['diagnostics']));
        $t->same(false, $wrongPath['ok']);
        $t->same('pdf-output-file-mismatch', $wrongPath['reason']);
        $t->same('other.pdf', $wrongPath['declaredOutputFile']);
        $t->contains('engine-output-file-mismatch:other.pdf:review.pdf', implode(',', $wrongPath['diagnostics']));
    },

    'fake runner fails when engine log records a fatal renderer error' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'pdflatex', 'outputPath' => 'review.pdf']);
        $result = $handoff->fakeRun($plan, [
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.log' => "! LaTeX Error: File `missing.sty' not found.\nFatal error occurred, no output PDF file produced!\n",
                'review.pdf' => "%PDF-1.7\n%%EOF\n",
            ],
        ]);

        $t->same(false, $result['ok']);
        $t->same('failed', $result['status']);
        $t->same('engine-log-error', $result['reason']);
        $t->contains("File `missing.sty' not found", implode("\n", $result['engineErrors']));
        $t->contains('engine-log-errors:2', implode(',', $result['diagnostics']));
    },

    'fake runner records missing pdf engine executable diagnostics without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => '/opt/texlive/bin/xelatex',
            'outputPath' => 'review.pdf',
        ]);
        $missing = $handoff->fakeRun($plan, [
            'exitCode' => 127,
            'stderr' => "sh: 1: /opt/texlive/bin/xelatex: not found\n",
        ]);
        $explicit = $handoff->fakeRun($plan, [
            'missingProgram' => 'xelatex',
        ]);
        $notMissing = $handoff->fakeRun($plan, [
            'exitCode' => 2,
            'stderr' => "xelatex exited after reading review.tex\n",
        ]);

        $t->same(false, $missing['ok']);
        $t->same('engine-program-missing', $missing['reason']);
        $t->same(true, $missing['engineMissingProgram']);
        $t->same('xelatex', $missing['engineMissingProgramName']);
        $t->contains('engine-program-missing:xelatex', implode(',', $missing['diagnostics']));
        $t->same('engine-exit-2', $notMissing['reason']);
        $t->same(false, $notMissing['engineMissingProgram']);
        $t->same(false, $explicit['ok']);
        $t->same('engine-program-missing', $explicit['reason']);
        $t->same(true, $explicit['engineMissingProgram']);
        $t->same('xelatex', $explicit['engineMissingProgramName']);
    },

    'fake runner sequence records multipass rerun clearing without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'review.pdf']);
        $firstPdf = "%PDF-1.7\n% fake first pass\n%%EOF\n";
        $finalPdf = "%PDF-1.7\n% fake final pass after rerun\n%%EOF\n";
        $firstLog = implode("\n", [
            'This is XeTeX, Version 3.141592653',
            'LaTeX Warning: Reference `fig:packet\' on page 1 undefined on input line 8.',
            'LaTeX Warning: Label(s) may have changed. Rerun to get cross-references right.',
            'Output written on review.pdf (1 page, ' . strlen($firstPdf) . ' bytes).',
            '',
        ]);
        $finalLog = 'Output written on review.pdf (1 page, ' . strlen($finalPdf) . " bytes).\n";

        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'review.aux' => "\\relax\n",
                    'review.log' => $firstLog,
                    'review.pdf' => $firstPdf,
                ],
            ],
            [
                'files' => [
                    'review.aux' => "\\relax\n\\newlabel{fig:packet}{{1}{1}}\n",
                    'review.log' => $finalLog,
                    'review.pdf' => $finalPdf,
                ],
            ],
        ]);

        $t->same(true, $sequence['ok']);
        $t->same('ok', $sequence['status']);
        $t->same(null, $sequence['reason']);
        $t->same(2, $sequence['attempts']);
        $t->same(2, $sequence['successfulAttempts']);
        $t->same(false, $sequence['rerunNeeded']);
        $t->same(2, $sequence['finalRunIndex']);
        $t->same(strlen($finalPdf), $sequence['finalBytes']);
        $t->same(hash('sha256', $finalPdf), $sequence['finalPdfSha256']);
        $t->same(1, $sequence['finalDeclaredOutputPages']);
        $t->same(false, $sequence['runs'][1]['rerunNeeded']);
        $t->contains('Reference `fig:packet\' on page 1 undefined', implode("\n", $sequence['engineWarnings']));
        $t->contains('fake-runner-attempts:2', implode(',', $sequence['diagnostics']));
        $t->contains('fake-runner-attempt-rerun-needed:1', implode(',', $sequence['diagnostics']));
        $t->contains('fake-runner-final-rerun-cleared', implode(',', $sequence['diagnostics']));
    },

    'fake runner sequence reports failed attempts and final rerun-needed state' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'lualatex', 'outputPath' => 'review.pdf']);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $rerunLog = implode("\n", [
            'LaTeX Warning: Citation `packet\' on page 1 undefined on input line 4.',
            'LaTeX Warning: Label(s) may have changed. Rerun to get cross-references right.',
            'Output written on review.pdf (1 page, ' . strlen($pdfBytes) . ' bytes).',
            '',
        ]);

        $stillNeedsRerun = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'review.log' => $rerunLog,
                    'review.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $failedAttempt = $handoff->fakeRunSequence($plan, [
            ['exitCode' => 1, 'stderr' => 'engine failed before pdf output'],
            [
                'files' => [
                    'review.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(false, $stillNeedsRerun['ok']);
        $t->same('failed', $stillNeedsRerun['status']);
        $t->same('rerun-still-needed', $stillNeedsRerun['reason']);
        $t->same(true, $stillNeedsRerun['rerunNeeded']);
        $t->contains('fake-runner-rerun-still-needed', implode(',', $stillNeedsRerun['diagnostics']));
        $t->same(false, $failedAttempt['ok']);
        $t->same('attempt-1-engine-exit-1', $failedAttempt['reason']);
        $t->same(1, $failedAttempt['successfulAttempts']);
        $t->contains('fake-runner-attempt-failed:1:engine-exit-1', implode(',', $failedAttempt['diagnostics']));
    },

    'fake runner reports missing output non pdf bytes source mismatch and engine failures' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'pdflatex', 'outputPath' => 'review.pdf']);

        $missing = $handoff->fakeRun($plan);
        $nonPdf = $handoff->fakeRun($plan, ['files' => ['review.pdf' => '<html>not a pdf</html>']]);
        $mismatch = $handoff->fakeRun($plan, [
            'files' => [
                'review.tex' => 'changed source',
                'review.pdf' => "%PDF-1.7\n%%EOF\n",
            ],
        ]);
        $engineFailure = $handoff->fakeRun($plan, [
            'exitCode' => 2,
            'stderr' => 'missing font',
            'files' => ['review.tex' => (string) $plan['sourceBytes']],
        ]);

        $t->same(false, $missing['ok']);
        $t->same('missing-pdf-output', $missing['reason']);
        $t->same('non-pdf-output', $nonPdf['reason']);
        $t->same('source-mismatch', $mismatch['reason']);
        $t->same('engine-exit-2', $engineFailure['reason']);
        $t->same('missing font', $engineFailure['stderr']);
    },

    'rejects unsafe pdf handoff engine path and option inputs' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();

        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['engine' => 'unknown-pdf-engine']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['outputPath' => '/tmp/review.pdf']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['sourcePath' => '../review.tex']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['engineOptions' => ["bad\0option"]]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['templatePath' => '../templates/review.tex']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['includeInHeader' => ['/tmp/header.tex']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['resourcePaths' => ['../media']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['resourceFiles' => ['../media/cover.png']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['resourceFiles' => ['https://example.test/cover.png']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['variables' => ['bad variable' => 'value']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['variables' => ['mainfont' => "bad\0font"]]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan(new AstNode('paragraph')));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->fakeRunSequence($handoff->plan($document()), []));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->fakeRunSequence($handoff->plan($document()), array_fill(0, 9, [])));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->fakeRunSequence($handoff->plan($document()), ['not a result']));
    },
];
