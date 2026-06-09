<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DeflateStream;
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

    'plans tex jobname and output-directory sidecar artifacts without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'handoff/review.pdf',
            'engineOptions' => ['-file-line-error', '-jobname=review-final', '-output-directory', 'build/pdf', '-recorder', '-synctex=1'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff with redirected sidecars\n%%EOF\n";
        $fls = implode("\n", [
            'PWD /tmp/pandoc-pdf-work',
            'INPUT handoff/review.tex',
            'INPUT chapters/source-note.tex',
            'OUTPUT build/pdf/review-final.aux',
            'OUTPUT build/pdf/review-final.pdf',
            '',
        ]);
        $bcf = '<bcf version="3.9" />';
        $result = $handoff->fakeRun($plan, [
            'files' => [
                'chapters/source-note.tex' => '\input{review}',
                'build/pdf/review-final.log' => "This is XeTeX, Version 3.141592653\n",
                'build/pdf/review-final.fls' => $fls,
                'build/pdf/review-final.bcf' => $bcf,
                'handoff/review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same('build/pdf/review-final', $plan['engineArtifactStem']);
        $t->same('build/pdf/review-final.log', $plan['engineLogFile']);
        $t->same([
            'build/pdf/review-final.log',
            'build/pdf/review-final.aux',
            'build/pdf/review-final.out',
            'build/pdf/review-final.toc',
            'build/pdf/review-final.fls',
            'build/pdf/review-final.synctex.gz',
        ], $plan['expectedEngineArtifacts']);
        $t->contains('pdf-engine-artifact-stem:build/pdf/review-final', implode(',', $plan['diagnostics']));
        $t->contains('pdf-engine-recorder:build/pdf/review-final.fls', implode(',', $plan['diagnostics']));
        $t->contains('pdf-source-map:build/pdf/review-final.synctex.gz', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same(['build/pdf/review-final.log'], $result['engineLogFiles']);
        $t->same(['build/pdf/review-final.fls' => hash('sha256', $fls)], $result['engineDependencyArtifactsSha256']);
        $t->same(['build/pdf/review-final.bcf' => hash('sha256', $bcf)], $result['bibliographyArtifactsSha256']);
        $t->same(['chapters/source-note.tex', 'handoff/review.tex'], $result['engineInputFiles']);
        $t->same(['build/pdf/review-final.aux', 'build/pdf/review-final.pdf'], $result['engineOutputFiles']);
        $t->contains('produced-engine-artifacts:3', implode(',', $result['diagnostics']));
        $t->contains('engine-dependency-artifacts:1', implode(',', $result['diagnostics']));
        $t->contains('bibliography-sidecars:1', implode(',', $result['diagnostics']));
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

    'fake runner extracts bounded pdf xref stream and object stream preflight metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/modern.pdf']);
        $objectStreamMemberOne = "<< /Title (Compressed outline) >>\n";
        $objectStreamMemberTwo = "<< /Type /Annot /Subtype /Link >>\n";
        $objectStreamHeader = '8 0 9 ' . strlen($objectStreamMemberOne) . "\n";
        $objectStreamPayload = $objectStreamHeader . $objectStreamMemberOne . $objectStreamMemberTwo;
        $xrefStreamPayload = "fake compressed xref stream bytes";
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
            '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Extends 7 0 R /Length ' . strlen($objectStreamPayload) . ' >>',
            'stream',
            $objectStreamPayload,
            'endstream',
            'endobj',
            '5 0 obj',
            '<< /Type /XRef /Size 10 /Root 1 0 R /Info 6 0 R /Prev 128 /Index [0 6 8 2] /W [1 2 1] /Filter /FlateDecode /Length ' . strlen($xrefStreamPayload) . ' >>',
            'stream',
            $xrefStreamPayload,
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /Title (Modern export) >>',
            'endobj',
            'startxref',
            '384',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/modern.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/modern.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same([
            [
                'object' => '5 0 R',
                'size' => 10,
                'root' => '1 0 R',
                'info' => '6 0 R',
                'encrypt' => null,
                'prev' => 128,
                'index' => [0, 6, 8, 2],
                'w' => [1, 2, 1],
                'filters' => ['FlateDecode'],
                'streamBytes' => strlen($xrefStreamPayload),
                'streamSha256' => null,
                'streamSkipped' => 'filtered',
            ],
        ], $result['pdfXrefStreams']);
        $t->same(['FlateDecode' => 1], $result['pdfXrefStreamFilters']);
        $t->same([
            [
                'object' => '4 0 R',
                'objectCount' => 2,
                'firstByteOffset' => strlen($objectStreamHeader),
                'extends' => '7 0 R',
                'objectNumbers' => [8, 9],
                'filters' => [],
                'streamBytes' => strlen($objectStreamPayload),
                'streamSha256' => hash('sha256', $objectStreamPayload),
                'streamSkipped' => null,
            ],
        ], $result['pdfObjectStreams']);
        $t->same([
            [
                'objectStream' => '4 0 R',
                'objectNumber' => 8,
                'memberIndex' => 1,
                'declaredOffset' => 0,
                'streamOffset' => strlen($objectStreamHeader),
                'memberBytes' => strlen($objectStreamMemberOne),
                'memberSha256' => hash('sha256', $objectStreamMemberOne),
                'valueKind' => 'dictionary',
                'dictionaryKeys' => ['Title'],
                'type' => null,
                'subtype' => null,
                'title' => 'Compressed outline',
            ],
            [
                'objectStream' => '4 0 R',
                'objectNumber' => 9,
                'memberIndex' => 2,
                'declaredOffset' => strlen($objectStreamMemberOne),
                'streamOffset' => strlen($objectStreamHeader) + strlen($objectStreamMemberOne),
                'memberBytes' => strlen($objectStreamMemberTwo),
                'memberSha256' => hash('sha256', $objectStreamMemberTwo),
                'valueKind' => 'dictionary',
                'dictionaryKeys' => ['Subtype', 'Type'],
                'type' => 'Annot',
                'subtype' => 'Link',
                'title' => null,
            ],
        ], $result['pdfObjectStreamMembers']);
        $t->same([], $result['pdfObjectStreamFilters']);
        $t->contains('pdf-byte-xref-streams:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xref-stream-filter:FlateDecode:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xref-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-streams:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-objects:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-members:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-member-dictionaries:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($result['pdfXrefStreams'], $sequence['finalPdfXrefStreams']);
        $t->same($result['pdfXrefStreamFilters'], $sequence['finalPdfXrefStreamFilters']);
        $t->same($result['pdfObjectStreams'], $sequence['finalPdfObjectStreams']);
        $t->same($result['pdfObjectStreamMembers'], $sequence['finalPdfObjectStreamMembers']);
        $t->same($result['pdfObjectStreamFilters'], $sequence['finalPdfObjectStreamFilters']);
    },

    'fake runner extracts bounded pdf object stream member provenance from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['outputPath' => 'packets/object-stream-members.pdf']);
        $memberOne = "<< /Type /Metadata /Title (Compressed review metadata) >>\n";
        $memberTwo = "<< /Type /Annot /Subtype /Link /Contents (Compressed link) >>\n";
        $header = '40 0 41 ' . strlen($memberOne) . "\n";
        $payload = $header . $memberOne . $memberTwo;
        $filteredPayload = "filtered compressed object stream bytes\n";
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
            '<< /Type /ObjStm /N 2 /First ' . strlen($header) . ' /Length ' . strlen($payload) . ' >>',
            'stream',
            $payload,
            'endstream',
            'endobj',
            '5 0 obj',
            '<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ' . strlen($filteredPayload) . ' >>',
            'stream',
            $filteredPayload,
            'endstream',
            'endobj',
            'trailer',
            '<< /Size 42 /Root 1 0 R >>',
            'startxref',
            '512',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/object-stream-members.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/object-stream-members.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'objectStream' => '4 0 R',
                'objectNumber' => 40,
                'memberIndex' => 1,
                'declaredOffset' => 0,
                'streamOffset' => strlen($header),
                'memberBytes' => strlen($memberOne),
                'memberSha256' => hash('sha256', $memberOne),
                'valueKind' => 'dictionary',
                'dictionaryKeys' => ['Title', 'Type'],
                'type' => 'Metadata',
                'subtype' => null,
                'title' => 'Compressed review metadata',
            ],
            [
                'objectStream' => '4 0 R',
                'objectNumber' => 41,
                'memberIndex' => 2,
                'declaredOffset' => strlen($memberOne),
                'streamOffset' => strlen($header) + strlen($memberOne),
                'memberBytes' => strlen($memberTwo),
                'memberSha256' => hash('sha256', $memberTwo),
                'valueKind' => 'dictionary',
                'dictionaryKeys' => ['Contents', 'Subtype', 'Type'],
                'type' => 'Annot',
                'subtype' => 'Link',
                'title' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfObjectStreamMembers']);
        $t->same(['FlateDecode' => 1], $result['pdfObjectStreamFilters']);
        $t->contains('pdf-byte-object-streams:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-members:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-member-dictionaries:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfObjectStreamMembers']);
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

    'fake runner extracts bounded pdf outline tree metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/outlines.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Outlines 8 0 R >>',
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
            '<< /Type /Outlines /First 9 0 R /Last 11 0 R /Count 2 >>',
            'endobj',
            '9 0 obj',
            '<< /Title (Packet overview) /Parent 8 0 R /Dest [3 0 R /FitH 720] /Next 11 0 R /First 10 0 R /Last 10 0 R /Count 1 >>',
            'endobj',
            '10 0 obj',
            '<< /Title (Reviewer notes) /Parent 9 0 R /A << /S /URI /URI (https://example.test/review/notes) >> /Count 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Title <FEFF0041007000700065006E006400690078> /Parent 8 0 R /Dest 12 0 R /Prev 9 0 R /Count -2 >>',
            'endobj',
            '12 0 obj',
            '[4 0 R /XYZ 0 792 0]',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/outlines.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/outlines.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '9 0 R',
                'title' => 'Packet overview',
                'parent' => '8 0 R',
                'prev' => null,
                'next' => '11 0 R',
                'first' => '10 0 R',
                'last' => '10 0 R',
                'count' => 1,
                'open' => true,
                'destPageObject' => '3 0 R',
                'destFit' => 'FitH',
                'actionType' => null,
                'actionTarget' => null,
            ],
            [
                'object' => '10 0 R',
                'title' => 'Reviewer notes',
                'parent' => '9 0 R',
                'prev' => null,
                'next' => null,
                'first' => null,
                'last' => null,
                'count' => 0,
                'open' => true,
                'destPageObject' => null,
                'destFit' => null,
                'actionType' => 'URI',
                'actionTarget' => 'https://example.test/review/notes',
            ],
            [
                'object' => '11 0 R',
                'title' => 'Appendix',
                'parent' => '8 0 R',
                'prev' => '9 0 R',
                'next' => null,
                'first' => null,
                'last' => null,
                'count' => -2,
                'open' => false,
                'destPageObject' => '4 0 R',
                'destFit' => 'XYZ',
                'actionType' => null,
                'actionTarget' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfOutlines']);
        $t->contains('pdf-byte-outline-metadata:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-open:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-closed:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-destinations:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-actions:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfOutlines']);
    },

    'fake runner extracts bounded pdf outline display metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/outline-display.pdf']);
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
            '<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>',
            'endobj',
            '6 0 obj',
            '<< /Title (Review packet) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 7 0 R /C [0 0.2 0.8] /F 2 >>',
            'endobj',
            '7 0 obj',
            '<< /Title (Plain appendix) /Parent 5 0 R /Dest [4 0 R /Fit] /Prev 6 0 R /Next 8 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Title <FEFF0049006D0070006F0072007400200063006800650063006B006C006900730074> /Parent 5 0 R /A << /S /URI /URI (https://example.test/import-checklist) >> /Prev 7 0 R /C [0.6 0 0] /F 3 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/outline-display.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/outline-display.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '6 0 R',
                'title' => 'Review packet',
                'color' => [0.0, 0.2, 0.8],
                'flags' => 2,
                'flagNames' => ['bold'],
            ],
            [
                'object' => '8 0 R',
                'title' => 'Import checklist',
                'color' => [0.6, 0.0, 0.0],
                'flags' => 3,
                'flagNames' => ['italic', 'bold'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfOutlineDisplayMetadata']);
        $t->contains('pdf-byte-outline-display-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-display-colors:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-display-flags:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-display-bold:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-outline-display-italic:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfOutlineDisplayMetadata']);
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

    'fake runner extracts bounded pdf page production print metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/print-production.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /TrimBox [36 36 576 756] /BleedBox [9 9 603 783] /BoxColorInfo << /TrimBox << /C [1 0 0] /W 0.5 /S /D >> /BleedBox << /C [0 0.2 0.8] /W 1 /S /S >> >> /SeparationInfo 5 0 R /PresSteps << /S /NA /Next 8 0 R >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /BoxColorInfo 6 0 R /PresSteps 7 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Pages [3 0 R 4 0 R] /DeviceColorant /PANTONE#20123#20C /ColorSpace /DeviceCMYK >>',
            'endobj',
            '6 0 obj',
            '<< /CropBox << /C [0 1 0] /W 0.25 /S /S >> /ArtBox << /C [0.1 0.2 0.3] /W 0.75 /S /D >> >>',
            'endobj',
            '7 0 obj',
            '<< /S /Render /Next [8 0 R 9 0 R] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/print-production.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/print-production.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'boxColorInfoObject' => 'inline',
                'boxColorInfo' => [
                    [
                        'box' => 'BleedBox',
                        'color' => [0.0, 0.2, 0.8],
                        'width' => 1.0,
                        'style' => 'S',
                    ],
                    [
                        'box' => 'TrimBox',
                        'color' => [1.0, 0.0, 0.0],
                        'width' => 0.5,
                        'style' => 'D',
                    ],
                ],
                'separationInfoObject' => '5 0 R',
                'separationPages' => ['3 0 R', '4 0 R'],
                'separationDeviceColorant' => 'PANTONE 123 C',
                'separationColorSpace' => 'DeviceCMYK',
                'presStepsObject' => 'inline',
                'presStepsSubtype' => 'NA',
                'presStepsNext' => ['8 0 R'],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'boxColorInfoObject' => '6 0 R',
                'boxColorInfo' => [
                    [
                        'box' => 'ArtBox',
                        'color' => [0.1, 0.2, 0.3],
                        'width' => 0.75,
                        'style' => 'D',
                    ],
                    [
                        'box' => 'CropBox',
                        'color' => [0.0, 1.0, 0.0],
                        'width' => 0.25,
                        'style' => 'S',
                    ],
                ],
                'separationInfoObject' => null,
                'separationPages' => [],
                'separationDeviceColorant' => null,
                'separationColorSpace' => null,
                'presStepsObject' => '7 0 R',
                'presStepsSubtype' => 'Render',
                'presStepsNext' => ['8 0 R', '9 0 R'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageProductionMetadata'] ?? null);
        $t->contains('pdf-byte-page-production-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-box-color-info:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-separation-info:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-presentation-steps:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageProductionMetadata'] ?? null);
    },

    'fake runner extracts bounded pdf page display metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/display.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /UserUnit 2 /Tabs /S /Group << /S /Transparency /CS /DeviceRGB /I true /K false >> /Thumb 5 0 R /LastModified (D:20260606093000Z) >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Tabs /R /Group 6 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 16 /Height 16 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /S /Transparency /CS [/ICCBased 7 0 R] /I false /K true >>',
            'endobj',
            '7 0 obj',
            '<< /N 3 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/display.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/display.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'userUnit' => 2.0,
                'tabOrder' => 'S',
                'groupSubtype' => 'Transparency',
                'groupColorSpace' => 'DeviceRGB',
                'groupIsolated' => true,
                'groupKnockout' => false,
                'thumbnailObject' => '5 0 R',
                'lastModified' => 'D:20260606093000Z',
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'userUnit' => null,
                'tabOrder' => 'R',
                'groupSubtype' => 'Transparency',
                'groupColorSpace' => 'ICCBased',
                'groupIsolated' => false,
                'groupKnockout' => true,
                'thumbnailObject' => null,
                'lastModified' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageDisplayMetadata']);
        $t->contains('pdf-byte-page-display-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-user-units:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-tab-orders:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-tab-order:R:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-tab-order:S:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-groups:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-thumbnails:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-last-modified:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageDisplayMetadata']);
    },

    'fake runner extracts bounded pdf page durations and transitions from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/slides.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /PageMode /FullScreen >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Dur 6.5 /Trans << /Type /Trans /S /Fade /D 1.25 >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Trans 5 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Trans /S /Wipe /D 0.75 /Di 90 /Dm /H /M /I /SS 0.8 /B true >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/slides.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/slides.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'duration' => 6.5,
                'transitionType' => 'Fade',
                'transitionDuration' => 1.25,
                'direction' => null,
                'directionLabel' => null,
                'dimension' => null,
                'motion' => null,
                'scale' => null,
                'background' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'duration' => null,
                'transitionType' => 'Wipe',
                'transitionDuration' => 0.75,
                'direction' => '90',
                'directionLabel' => 'bottom-to-top',
                'dimension' => 'H',
                'motion' => 'I',
                'scale' => 0.8,
                'background' => true,
            ],
        ];
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'pageCount' => 2,
            'timingCount' => 2,
            'durationCount' => 1,
            'transitionCount' => 2,
            'pagesWithTiming' => [1, 2],
            'durationPages' => [1],
            'transitionPages' => [1, 2],
            'transitionTypes' => [
                'Fade' => 1,
                'Wipe' => 1,
            ],
            'directionLabels' => [
                'bottom-to-top' => 1,
            ],
            'maxDuration' => 6.5,
            'maxTransitionDuration' => 1.25,
            'issues' => [
                'auto-advance-duration',
                'page-transition-effects',
                'transition-background-fill',
                'transition-direction-overrides',
                'transition-motion-overrides',
                'transition-scale-overrides',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same('FullScreen', $result['pdfPageMode']);
        $t->same($expected, $result['pdfPageTimings']);
        $t->same($expectedPolicy, $result['pdfPageTimingPolicy']);
        $t->contains('pdf-byte-page-mode:FullScreen', $diagnostics);
        $t->contains('pdf-byte-page-timings:2', $diagnostics);
        $t->contains('pdf-byte-page-durations:1', $diagnostics);
        $t->contains('pdf-byte-page-transitions:2', $diagnostics);
        $t->contains('pdf-byte-page-transition-type:Fade:1', $diagnostics);
        $t->contains('pdf-byte-page-transition-type:Wipe:1', $diagnostics);
        $t->contains('pdf-byte-page-transition-direction:bottom-to-top:1', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy:review', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-durations:1', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-transitions:2', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-transition-type:Fade:1', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-direction:bottom-to-top:1', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-issues:6', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-issue:auto-advance-duration:1', $diagnostics);
        $t->contains('pdf-byte-page-timing-policy-issue:transition-scale-overrides:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageTimings']);
        $t->same($expectedPolicy, $sequence['finalPdfPageTimingPolicy']);
    },

    'fake runner labels bounded pdf page transition directions from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/directions.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 3 /Kids [3 0 R 4 0 R 5 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Trans << /Type /Trans /S /Fly /Di /None >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Trans << /Type /Trans /S /Glitter /Di 315 >> >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Page /Parent 2 0 R /Trans << /Type /Trans /S /Push /Di /Right >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/directions.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/directions.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'duration' => null,
                'transitionType' => 'Fly',
                'transitionDuration' => null,
                'direction' => 'None',
                'directionLabel' => 'none',
                'dimension' => null,
                'motion' => null,
                'scale' => null,
                'background' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'duration' => null,
                'transitionType' => 'Glitter',
                'transitionDuration' => null,
                'direction' => '315',
                'directionLabel' => 'top-left-to-bottom-right',
                'dimension' => null,
                'motion' => null,
                'scale' => null,
                'background' => null,
            ],
            [
                'page' => 3,
                'pageObject' => '5 0 R',
                'duration' => null,
                'transitionType' => 'Push',
                'transitionDuration' => null,
                'direction' => 'Right',
                'directionLabel' => 'right',
                'dimension' => null,
                'motion' => null,
                'scale' => null,
                'background' => null,
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageTimings']);
        $t->contains('pdf-byte-page-transition-direction:none:1', $diagnostics);
        $t->contains('pdf-byte-page-transition-direction:right:1', $diagnostics);
        $t->contains('pdf-byte-page-transition-direction:top-left-to-bottom-right:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageTimings']);
    },

    'fake runner extracts bounded pdf page viewport measure metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/viewports.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /VP [8 0 R << /Type /Viewport /Name (Inset crop) /BBox [72 72 288 216] /Measure << /Type /Measure /Subtype /RL /R (1 cm = 10 m) /X [<< /Type /NumberFormat /U (cm) /C 0.01 /F /D >>] /D [<< /U (m) /C 1 >>] /A [<< /U (sq m) /C 1 >>] /T [<< /U (deg) /C 1 /F /D >>] >> >>] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /VP 9 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Viewport /Name (Reviewer map) /BBox [0 0 612 792] /Measure 10 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Viewport /Name <FEFF005300690074006500200070006C0061006E> /BBox [10 20 300 400] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Measure /Subtype /RL /R (1 in = 25 ft) /X [<< /Type /NumberFormat /U (in) /C 1 /F /D >> << /Type /NumberFormat /U (ft) /C 25 /F /F >>] /Y [<< /U (in) /C 1 >>] /D [<< /U (ft) /C 25 >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/viewports.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/viewports.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'viewportObject' => '8 0 R',
                'source' => 'page:3 0 R.VP[0]',
                'name' => 'Reviewer map',
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'measureSubtype' => 'RL',
                'scaleRatio' => '1 in = 25 ft',
                'xUnits' => [
                    ['unit' => 'in', 'conversionFactor' => 1.0, 'fractionalDisplay' => 'D'],
                    ['unit' => 'ft', 'conversionFactor' => 25.0, 'fractionalDisplay' => 'F'],
                ],
                'yUnits' => [
                    ['unit' => 'in', 'conversionFactor' => 1.0, 'fractionalDisplay' => null],
                ],
                'distanceUnits' => [
                    ['unit' => 'ft', 'conversionFactor' => 25.0, 'fractionalDisplay' => null],
                ],
                'areaUnits' => [],
                'angleUnits' => [],
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'viewportObject' => null,
                'source' => 'page:3 0 R.VP[1]',
                'name' => 'Inset crop',
                'bbox' => [72.0, 72.0, 288.0, 216.0],
                'measureSubtype' => 'RL',
                'scaleRatio' => '1 cm = 10 m',
                'xUnits' => [
                    ['unit' => 'cm', 'conversionFactor' => 0.01, 'fractionalDisplay' => 'D'],
                ],
                'yUnits' => [],
                'distanceUnits' => [
                    ['unit' => 'm', 'conversionFactor' => 1.0, 'fractionalDisplay' => null],
                ],
                'areaUnits' => [
                    ['unit' => 'sq m', 'conversionFactor' => 1.0, 'fractionalDisplay' => null],
                ],
                'angleUnits' => [
                    ['unit' => 'deg', 'conversionFactor' => 1.0, 'fractionalDisplay' => 'D'],
                ],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'viewportObject' => '9 0 R',
                'source' => 'page:4 0 R.VP',
                'name' => 'Site plan',
                'bbox' => [10.0, 20.0, 300.0, 400.0],
                'measureSubtype' => null,
                'scaleRatio' => null,
                'xUnits' => [],
                'yUnits' => [],
                'distanceUnits' => [],
                'areaUnits' => [],
                'angleUnits' => [],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageViewports'] ?? null);
        $t->contains('pdf-byte-page-viewports:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-viewport-measures:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-viewport-unit-formats:8', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageViewports'] ?? null);
    },

    'fake runner extracts bounded pdf font resources and embedded font streams from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/fonts.pdf']);
        $embeddedFont = "fake Source Serif font bytes\n";
        $filteredFont = "compressed fake logo font bytes";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Resources << /Font << /FBase 7 0 R /FBody 8 0 R >> >> /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources 12 0 R >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEE+SourceSerif4-Regular /Encoding /Identity-H /DescendantFonts [9 0 R] /ToUnicode 11 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ABCDEE+SourceSerif4-Regular /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 10 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /FontDescriptor /FontName /ABCDEE+SourceSerif4-Regular /FontFamily (Source Serif 4) /Flags 4 /FontWeight 400 /ItalicAngle 0 /FontFile2 14 0 R >>',
            'endobj',
            '11 0 obj',
            '<< /Length 24 >>',
            'stream',
            '/CIDInit /ProcSet findresource',
            'endstream',
            'endobj',
            '12 0 obj',
            '<< /Font << /FLogo 13 0 R >> >>',
            'endobj',
            '13 0 obj',
            '<< /Type /Font /Subtype /TrueType /BaseFont /LogoSans-Bold /Encoding /WinAnsiEncoding /FontDescriptor 15 0 R >>',
            'endobj',
            '14 0 obj',
            '<< /Length ' . strlen($embeddedFont) . ' >>',
            'stream',
            $embeddedFont,
            'endstream',
            'endobj',
            '15 0 obj',
            '<< /Type /FontDescriptor /FontName /LogoSans-Bold /FontFamily (Logo Sans) /Flags 32 /ItalicAngle 0 /FontFile2 16 0 R >>',
            'endobj',
            '16 0 obj',
            '<< /Length ' . strlen($filteredFont) . ' /Filter /FlateDecode >>',
            'stream',
            $filteredFont,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/fonts.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/fonts.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'FBase',
                'fontObject' => '7 0 R',
                'inherited' => true,
                'subtype' => 'Type1',
                'baseFont' => 'Helvetica',
                'encoding' => 'WinAnsiEncoding',
                'toUnicode' => null,
                'descendantFonts' => [],
                'descriptor' => null,
                'descriptorFontName' => null,
                'descriptorFontFamily' => null,
                'descriptorFlags' => null,
                'descriptorItalicAngle' => null,
                'descriptorFontWeight' => null,
                'embedded' => false,
                'embeddedFile' => null,
                'embeddedFileKind' => null,
                'embeddedFileSubtype' => null,
                'embeddedFileBytes' => null,
                'embeddedFileSha256' => null,
                'embeddedFileSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'FBody',
                'fontObject' => '8 0 R',
                'inherited' => true,
                'subtype' => 'Type0',
                'baseFont' => 'ABCDEE+SourceSerif4-Regular',
                'encoding' => 'Identity-H',
                'toUnicode' => '11 0 R',
                'descendantFonts' => ['9 0 R'],
                'descriptor' => '10 0 R',
                'descriptorFontName' => 'ABCDEE+SourceSerif4-Regular',
                'descriptorFontFamily' => 'Source Serif 4',
                'descriptorFlags' => 4,
                'descriptorItalicAngle' => 0.0,
                'descriptorFontWeight' => 400,
                'embedded' => true,
                'embeddedFile' => '14 0 R',
                'embeddedFileKind' => 'FontFile2',
                'embeddedFileSubtype' => null,
                'embeddedFileBytes' => strlen($embeddedFont),
                'embeddedFileSha256' => hash('sha256', $embeddedFont),
                'embeddedFileSkipped' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'FLogo',
                'fontObject' => '13 0 R',
                'inherited' => false,
                'subtype' => 'TrueType',
                'baseFont' => 'LogoSans-Bold',
                'encoding' => 'WinAnsiEncoding',
                'toUnicode' => null,
                'descendantFonts' => [],
                'descriptor' => '15 0 R',
                'descriptorFontName' => 'LogoSans-Bold',
                'descriptorFontFamily' => 'Logo Sans',
                'descriptorFlags' => 32,
                'descriptorItalicAngle' => 0.0,
                'descriptorFontWeight' => null,
                'embedded' => true,
                'embeddedFile' => '16 0 R',
                'embeddedFileKind' => 'FontFile2',
                'embeddedFileSubtype' => null,
                'embeddedFileBytes' => strlen($filteredFont),
                'embeddedFileSha256' => null,
                'embeddedFileSkipped' => 'filtered',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfFonts']);
        $t->same(['TrueType' => 1, 'Type0' => 1, 'Type1' => 1], $result['pdfFontSubtypes']);
        $t->contains('pdf-byte-font-resources:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-font-subtypes:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-fonts:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-font-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfFonts']);
        $t->same(['TrueType' => 1, 'Type0' => 1, 'Type1' => 1], $sequence['finalPdfFontSubtypes']);
    },

    'fake runner extracts bounded pdf image xobject metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/images.pdf']);
        $heroBytes = 'fake compressed hero image bytes';
        $logoBytes = 'fake filtered logo image bytes';
        $ignoredFormBytes = 'form xobject bytes';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] /Resources << /XObject << /ImHero 8 0 R >> >> >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImLogo 9 0 R /IgnoredForm 11 0 R >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 640 /Height 360 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Filter /DCTDecode /Interpolate true /SMask 10 0 R /Length ' . strlen($heroBytes) . ' >>',
            'stream',
            $heroBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 120 /Height 80 /BitsPerComponent 8 /ColorSpace [/ICCBased 12 0 R] /Filter [/FlateDecode /DCTDecode] /ImageMask false /Length ' . strlen($logoBytes) . ' >>',
            'stream',
            $logoBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 640 /Height 360 /BitsPerComponent 8 /ColorSpace /DeviceGray /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Length ' . strlen($ignoredFormBytes) . ' >>',
            'stream',
            $ignoredFormBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/images.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/images.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'ImHero',
                'imageObject' => '8 0 R',
                'inherited' => true,
                'width' => 640,
                'height' => 360,
                'bitsPerComponent' => 8,
                'colorSpace' => 'DeviceRGB',
                'filters' => ['DCTDecode'],
                'interpolate' => true,
                'imageMask' => null,
                'softMask' => '10 0 R',
                'streamBytes' => strlen($heroBytes),
                'streamSha256' => hash('sha256', $heroBytes),
                'streamSkipped' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'ImLogo',
                'imageObject' => '9 0 R',
                'inherited' => false,
                'width' => 120,
                'height' => 80,
                'bitsPerComponent' => 8,
                'colorSpace' => 'ICCBased',
                'filters' => ['DCTDecode', 'FlateDecode'],
                'interpolate' => null,
                'imageMask' => false,
                'softMask' => null,
                'streamBytes' => strlen($logoBytes),
                'streamSha256' => hash('sha256', $logoBytes),
                'streamSkipped' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfImages']);
        $t->same(['DeviceRGB' => 1, 'ICCBased' => 1], $result['pdfImageColorSpaces']);
        $t->same(['DCTDecode' => 2, 'FlateDecode' => 1], $result['pdfImageFilters']);
        $t->contains('pdf-byte-image-xobjects:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-image-streams:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-image-color-spaces:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-image-filters:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-image-filter:DCTDecode:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-image-filter:FlateDecode:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfImages']);
        $t->same(['DeviceRGB' => 1, 'ICCBased' => 1], $sequence['finalPdfImageColorSpaces']);
        $t->same(['DCTDecode' => 2, 'FlateDecode' => 1], $sequence['finalPdfImageFilters']);
    },

    'fake runner extracts bounded pdf color space resources from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/color-spaces.pdf']);
        $profileBytes = "fake CMYK profile bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Resources << /ColorSpace << /CSBase /DeviceRGB /CSPrint [/ICCBased 8 0 R] >> >> /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /ColorSpace << /CSSpot [/Separation /PANTONE#20123#20C /DeviceCMYK 9 0 R] /CSDeviceN [/DeviceN [/Spot#201 /Spot#202] /DeviceCMYK 10 0 R] >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /N 4 /Alternate /DeviceCMYK /Length ' . strlen($profileBytes) . ' >>',
            'stream',
            $profileBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /FunctionType 2 /Domain [0 1] /C0 [0 0 0 0] /C1 [0 0.4 1 0] /N 1 >>',
            'endobj',
            '10 0 obj',
            '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/color-spaces.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/color-spaces.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'CSBase',
                'colorSpaceObject' => null,
                'inherited' => true,
                'family' => 'DeviceRGB',
                'colorantNames' => [],
                'alternateColorSpace' => null,
                'profileComponents' => null,
                'profileAlternate' => null,
                'profileBytes' => null,
                'profileSha256' => null,
                'profileSkipped' => null,
                'tintTransform' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'CSPrint',
                'colorSpaceObject' => null,
                'inherited' => true,
                'family' => 'ICCBased',
                'colorantNames' => [],
                'alternateColorSpace' => 'DeviceCMYK',
                'profileComponents' => 4,
                'profileAlternate' => 'DeviceCMYK',
                'profileBytes' => strlen($profileBytes),
                'profileSha256' => hash('sha256', $profileBytes),
                'profileSkipped' => null,
                'tintTransform' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'CSDeviceN',
                'colorSpaceObject' => null,
                'inherited' => false,
                'family' => 'DeviceN',
                'colorantNames' => ['Spot 1', 'Spot 2'],
                'alternateColorSpace' => 'DeviceCMYK',
                'profileComponents' => null,
                'profileAlternate' => null,
                'profileBytes' => null,
                'profileSha256' => null,
                'profileSkipped' => null,
                'tintTransform' => '10 0 R',
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'CSSpot',
                'colorSpaceObject' => null,
                'inherited' => false,
                'family' => 'Separation',
                'colorantNames' => ['PANTONE 123 C'],
                'alternateColorSpace' => 'DeviceCMYK',
                'profileComponents' => null,
                'profileAlternate' => null,
                'profileBytes' => null,
                'profileSha256' => null,
                'profileSkipped' => null,
                'tintTransform' => '9 0 R',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfColorSpaces']);
        $t->same(['DeviceN' => 1, 'DeviceRGB' => 1, 'ICCBased' => 1, 'Separation' => 1], $result['pdfColorSpaceFamilies']);
        $t->contains('pdf-byte-color-spaces:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-color-space-families:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-color-space-family:ICCBased:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-color-space-profiles:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-color-space-tint-transforms:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfColorSpaces']);
        $t->same(['DeviceN' => 1, 'DeviceRGB' => 1, 'ICCBased' => 1, 'Separation' => 1], $sequence['finalPdfColorSpaceFamilies']);
    },

    'fake runner extracts bounded pdf form xobject metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/forms-xobject.pdf']);
        $badgeBytes = "q 1 0 0 1 0 0 cm /ImBadge Do Q\n";
        $overlayBytes = "q /GSReview gs 0 0 100 100 re f Q\n";
        $imageBytes = 'fake raster bytes';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Resources << /XObject << /FxBadge 8 0 R /ImHero 11 0 R >> >> /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /FxOverlay 9 0 R >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 200 80] /Matrix [1 0 0 1 12 24] /Resources << /ProcSet [/PDF /Text] >> /Group << /S /Transparency /CS /DeviceRGB /I true /K false >> /Length ' . strlen($badgeBytes) . ' >>',
            'stream',
            $badgeBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Group 10 0 R /Filter /FlateDecode /Length ' . strlen($overlayBytes) . ' >>',
            'stream',
            $overlayBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /S /Transparency /CS /DeviceCMYK /I false /K true >>',
            'endobj',
            '11 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 20 /Height 20 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Length ' . strlen($imageBytes) . ' >>',
            'stream',
            $imageBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/forms-xobject.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/forms-xobject.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'FxBadge',
                'formObject' => '8 0 R',
                'inherited' => true,
                'bbox' => [0.0, 0.0, 200.0, 80.0],
                'matrix' => [1.0, 0.0, 0.0, 1.0, 12.0, 24.0],
                'resourcesPresent' => true,
                'groupSubtype' => 'Transparency',
                'groupColorSpace' => 'DeviceRGB',
                'groupIsolated' => true,
                'groupKnockout' => false,
                'filters' => [],
                'streamBytes' => strlen($badgeBytes),
                'streamSha256' => hash('sha256', $badgeBytes),
                'streamSkipped' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'FxOverlay',
                'formObject' => '9 0 R',
                'inherited' => false,
                'bbox' => [0.0, 0.0, 100.0, 100.0],
                'matrix' => null,
                'resourcesPresent' => false,
                'groupSubtype' => 'Transparency',
                'groupColorSpace' => 'DeviceCMYK',
                'groupIsolated' => false,
                'groupKnockout' => true,
                'filters' => ['FlateDecode'],
                'streamBytes' => strlen($overlayBytes),
                'streamSha256' => hash('sha256', $overlayBytes),
                'streamSkipped' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfFormXObjects']);
        $t->same(['FlateDecode' => 1], $result['pdfFormXObjectFilters']);
        $t->contains('pdf-byte-form-xobjects:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-xobject-streams:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-xobject-groups:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-xobject-filters:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-xobject-filter:FlateDecode:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfFormXObjects']);
        $t->same(['FlateDecode' => 1], $sequence['finalPdfFormXObjectFilters']);
    },

    'fake runner extracts bounded pdf extended graphics state resources from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/graphics-state.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Resources << /ExtGState << /GSBase 6 0 R >> >> /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /ExtGState << /GSWatermark << /Type /ExtGState /ca 0.35 /BM /Multiply /AIS true /TK false /SMask /None >> /GSReview 7 0 R >> >> >>',
            'endobj',
            '6 0 obj',
            '<< /Type /ExtGState /CA 0.8 /ca 0.6 /BM [/Normal /Screen] /OP true /op false /OPM 1 /SA true >>',
            'endobj',
            '7 0 obj',
            '<< /Type /ExtGState /CA 1 /ca 0.92 /BM 8 0 R /SMask 9 0 R /AIS false /TK true >>',
            'endobj',
            '8 0 obj',
            '[/Overlay /SoftLight]',
            'endobj',
            '9 0 obj',
            '<< /Type /Mask /S /Luminosity >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/graphics-state.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/graphics-state.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceName' => 'GSBase',
                'graphicsStateObject' => '6 0 R',
                'inherited' => true,
                'strokingAlpha' => 0.8,
                'nonstrokingAlpha' => 0.6,
                'blendModes' => ['Normal', 'Screen'],
                'overprintStroking' => true,
                'overprintNonstroking' => false,
                'overprintMode' => 1,
                'alphaSource' => null,
                'textKnockout' => null,
                'softMask' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'GSReview',
                'graphicsStateObject' => '7 0 R',
                'inherited' => false,
                'strokingAlpha' => 1.0,
                'nonstrokingAlpha' => 0.92,
                'blendModes' => ['Overlay', 'SoftLight'],
                'overprintStroking' => null,
                'overprintNonstroking' => null,
                'overprintMode' => null,
                'alphaSource' => false,
                'textKnockout' => true,
                'softMask' => '9 0 R',
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceName' => 'GSWatermark',
                'graphicsStateObject' => null,
                'inherited' => false,
                'strokingAlpha' => null,
                'nonstrokingAlpha' => 0.35,
                'blendModes' => ['Multiply'],
                'overprintStroking' => null,
                'overprintNonstroking' => null,
                'overprintMode' => null,
                'alphaSource' => true,
                'textKnockout' => false,
                'softMask' => 'None',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfGraphicsStates'] ?? null);
        $t->same(['Multiply' => 1, 'Normal' => 1, 'Overlay' => 1, 'Screen' => 1, 'SoftLight' => 1], $result['pdfGraphicsStateBlendModes'] ?? null);
        $t->contains('pdf-byte-graphics-states:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-graphics-state-alpha:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-graphics-state-blend-modes:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-graphics-state-blend-mode:Multiply:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-graphics-state-soft-masks:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-graphics-state-overprint:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfGraphicsStates'] ?? null);
        $t->same(['Multiply' => 1, 'Normal' => 1, 'Overlay' => 1, 'Screen' => 1, 'SoftLight' => 1], $sequence['finalPdfGraphicsStateBlendModes'] ?? null);
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

    'fake runner summarizes bounded pdf page label number tree policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-label-policy.pdf']);
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
            '<< /Kids [9 0 R 10 0 R 11 0 R 99 0 R] /Limits [0 4] >>',
            'endobj',
            '9 0 obj',
            '<< /Limits [0 2] /Nums [0 << /S /r /P (front-) /St 3 >> 2 << /S /D /P (Chapter ) /St 1 >>] >>',
            'endobj',
            '10 0 obj',
            '<< /Limits [2 4] /Nums [4 << /S /A /P (Appendix-) /St 27 >> 3 << /S /D /P (Late ) >>] >>',
            'endobj',
            '11 0 obj',
            '<< /Limits [6 7] /Nums [6 << /S /D /P (Overflow-) >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-label-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-label-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'source' => 'catalog.PageLabels',
            'object' => '8 0 R',
            'reviewStatus' => 'review',
            'pageCount' => 5,
            'entryCount' => 5,
            'kidCount' => 4,
            'limits' => [0, 4, 2, 6, 7],
            'issues' => [
                'kid-limits-outside-parent',
                'kid-limits-overlap-or-unsorted',
                'kid-reference-missing',
                'nums-out-of-order',
                'page-index-out-of-range',
            ],
            'nodes' => [
                [
                    'source' => 'catalog.PageLabels',
                    'object' => '8 0 R',
                    'kind' => 'root',
                    'entryCount' => 0,
                    'pageIndexes' => [],
                    'kidCount' => 4,
                    'limits' => [0, 4],
                    'reviewStatus' => 'review',
                    'issues' => [
                        'kid-limits-outside-parent',
                        'kid-limits-overlap-or-unsorted',
                        'kid-reference-missing',
                    ],
                ],
                [
                    'source' => 'catalog.PageLabels.Kids.9 0 R',
                    'object' => '9 0 R',
                    'kind' => 'kid',
                    'entryCount' => 2,
                    'pageIndexes' => [0, 2],
                    'kidCount' => 0,
                    'limits' => [0, 2],
                    'reviewStatus' => 'ok',
                    'issues' => [],
                ],
                [
                    'source' => 'catalog.PageLabels.Kids.10 0 R',
                    'object' => '10 0 R',
                    'kind' => 'kid',
                    'entryCount' => 2,
                    'pageIndexes' => [4, 3],
                    'kidCount' => 0,
                    'limits' => [2, 4],
                    'reviewStatus' => 'review',
                    'issues' => ['nums-out-of-order'],
                ],
                [
                    'source' => 'catalog.PageLabels.Kids.11 0 R',
                    'object' => '11 0 R',
                    'kind' => 'kid',
                    'entryCount' => 1,
                    'pageIndexes' => [6],
                    'kidCount' => 0,
                    'limits' => [6, 7],
                    'reviewStatus' => 'review',
                    'issues' => ['page-index-out-of-range'],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageLabelPolicy']);
        $diagnostics = implode(',', $result['diagnostics']);
        $t->contains('pdf-byte-page-label-policy:review', $diagnostics);
        $t->contains('pdf-byte-page-label-policy-nodes:4', $diagnostics);
        $t->contains('pdf-byte-page-label-policy-review-nodes:3', $diagnostics);
        $t->contains('pdf-byte-page-label-policy-issues:5', $diagnostics);
        $t->contains('pdf-byte-page-label-policy-issue:kid-reference-missing:1', $diagnostics);
        $t->contains('pdf-byte-page-label-policy-issue:page-index-out-of-range:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageLabelPolicy']);
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

    'fake runner normalizes bounded pdf document info date metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/dates.pdf']);
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
            "<< /CreationDate (D:20260605050300Z) /ModDate (D:20260605050430-05'30') >>",
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Info 8 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/dates.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/dates.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'key' => 'CreationDate',
                'source' => 'Info.CreationDate',
                'raw' => 'D:20260605050300Z',
                'normalized' => '2026-06-05T05:03:00Z',
                'precision' => 'second',
                'timezone' => 'Z',
                'timezoneOffsetMinutes' => 0,
                'year' => 2026,
                'month' => 6,
                'day' => 5,
                'hour' => 5,
                'minute' => 3,
                'second' => 0,
                'valid' => true,
            ],
            [
                'key' => 'ModDate',
                'source' => 'Info.ModDate',
                'raw' => "D:20260605050430-05'30'",
                'normalized' => '2026-06-05T05:04:30-05:30',
                'precision' => 'second',
                'timezone' => '-05:30',
                'timezoneOffsetMinutes' => -330,
                'year' => 2026,
                'month' => 6,
                'day' => 5,
                'hour' => 5,
                'minute' => 4,
                'second' => 30,
                'valid' => true,
            ],
        ];
        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfDocumentInfoDateMetadata']);
        $t->contains('pdf-byte-document-info-dates:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-document-info-date-normalized:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-document-info-date-timezones:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfDocumentInfoDateMetadata']);

        $invalidPdfBytes = implode("\n", [
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
            '<< /CreationDate (D:20261305050300Z) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Info 8 0 R >>',
            '%%EOF',
            '',
        ]);
        $invalidResult = $handoff->fakeRun($plan, [
            'files' => [
                'packets/dates.pdf' => $invalidPdfBytes,
            ],
        ]);
        $t->same(true, $invalidResult['ok']);
        $t->same(null, $invalidResult['pdfDocumentInfoDateMetadata'][0]['normalized']);
        $t->same(false, $invalidResult['pdfDocumentInfoDateMetadata'][0]['valid']);
        $t->contains('pdf-byte-document-info-date-invalid:1', implode(',', $invalidResult['diagnostics']));
    },

    'fake runner extracts bounded pdf xmp metadata and pdfa pdfua identification from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/xmp.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" xmlns:ua="http://www.aiim.org/pdfua/ns/id/">',
            '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">PDF Review Packet</rdf:li></rdf:Alt></dc:title>',
            '<dc:creator><rdf:Seq><rdf:li>Migration Desk</rdf:li><rdf:li>Content Reviewer</rdf:li></rdf:Seq></dc:creator>',
            '<dc:subject><rdf:Bag><rdf:li>wordpress-import</rdf:li><rdf:li>pdf-review</rdf:li></rdf:Bag></dc:subject>',
            '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Migration review metadata</rdf:li></rdf:Alt></dc:description>',
            '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">CC BY-SA review packet</rdf:li></rdf:Alt></dc:rights>',
            '<dc:format>application/pdf</dc:format>',
            '<dc:language><rdf:Bag><rdf:li>en-US</rdf:li><rdf:li>es</rdf:li></rdf:Bag></dc:language>',
            '<dc:relation><rdf:Bag><rdf:li>urn:wordpress:post:42</rdf:li><rdf:li>https://example.test/source</rdf:li></rdf:Bag></dc:relation>',
            '<dc:source>legacy-pdf-review-packet</dc:source>',
            '<xmp:CreatorTool>Pandoc native handoff</xmp:CreatorTool>',
            '<xmp:CreateDate>2026-06-05T07:41:23Z</xmp:CreateDate>',
            '<xmp:ModifyDate>2026-06-05T07:42:00Z</xmp:ModifyDate>',
            '<xmp:MetadataDate>2026-06-05T07:42:10Z</xmp:MetadataDate>',
            '<xmpMM:DocumentID>uuid:pdf-review-packet</xmpMM:DocumentID>',
            '<xmpMM:InstanceID>uuid:pdf-review-packet-v2</xmpMM:InstanceID>',
            '<pdfaid:part>2</pdfaid:part>',
            '<pdfaid:conformance>B</pdfaid:conformance>',
            '<ua:part>1</ua:part>',
            '<ua:amd>2024</ua:amd>',
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
            'rights' => 'CC BY-SA review packet',
            'format' => 'application/pdf',
            'source' => 'legacy-pdf-review-packet',
            'creatorTool' => 'Pandoc native handoff',
            'createDate' => '2026-06-05T07:41:23Z',
            'modifyDate' => '2026-06-05T07:42:00Z',
            'metadataDate' => '2026-06-05T07:42:10Z',
            'documentId' => 'uuid:pdf-review-packet',
            'instanceId' => 'uuid:pdf-review-packet-v2',
            'creators' => ['Migration Desk', 'Content Reviewer'],
            'subjects' => ['wordpress-import', 'pdf-review'],
            'languages' => ['en-US', 'es'],
            'relations' => ['urn:wordpress:post:42', 'https://example.test/source'],
            'pdfaIdentification' => [
                'part' => '2',
                'conformance' => 'B',
            ],
            'pdfuaIdentification' => [
                'part' => '1',
                'amendment' => '2024',
                'corrigendum' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:19', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-subjects:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-languages:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-relations:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-rights', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-source', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-pdfa:2:B', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-pdfua:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-pdfua-amendment:2024', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner extracts bounded xmp media management provenance from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/xmp-mm.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/" xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#" xmlns:stEvt="http://ns.adobe.com/xap/1.0/sType/ResourceEvent#" xmpMM:DocumentID="uuid:review-output" xmpMM:InstanceID="uuid:review-output-v2" xmpMM:OriginalDocumentID="uuid:legacy-source" xmpMM:RenditionClass="proof:pdf" xmpMM:RenditionParams="engine=xelatex;profile=wordpress-review" xmpMM:VersionID="2">',
            '<xmpMM:DerivedFrom rdf:parseType="Resource">',
            '<stRef:documentID>uuid:legacy-source</stRef:documentID>',
            '<stRef:instanceID>uuid:legacy-source-v5</stRef:instanceID>',
            '<stRef:originalDocumentID>uuid:legacy-original</stRef:originalDocumentID>',
            '<stRef:renditionClass>draft:docx</stRef:renditionClass>',
            '<stRef:renditionParams>source=import-review</stRef:renditionParams>',
            '<stRef:manager>WordPress importer</stRef:manager>',
            '<stRef:managerVariant>block-review</stRef:managerVariant>',
            '<stRef:managerTo>wp:post:42</stRef:managerTo>',
            '<stRef:managerUI>review-panel</stRef:managerUI>',
            '</xmpMM:DerivedFrom>',
            '<xmpMM:History>',
            '<rdf:Seq>',
            '<rdf:li rdf:parseType="Resource"><stEvt:action>converted</stEvt:action><stEvt:instanceID>uuid:review-output-v1</stEvt:instanceID><stEvt:when>2026-06-09T01:49:07Z</stEvt:when><stEvt:softwareAgent>Pandoc PHP fake runner</stEvt:softwareAgent><stEvt:changed>/metadata /pages</stEvt:changed><stEvt:parameters>engine=xelatex</stEvt:parameters></rdf:li>',
            '<rdf:li stEvt:action="saved" stEvt:instanceID="uuid:review-output-v2" stEvt:when="2026-06-09T01:50:12Z" stEvt:softwareAgent="WordPress review queue" stEvt:changed="/status" stEvt:parameters="no-execute=true" />',
            '</rdf:Seq>',
            '</xmpMM:History>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R >>',
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
                'packets/xmp-mm.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/xmp-mm.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'packetBytes' => strlen($xmp),
            'packetSha256' => hash('sha256', $xmp),
            'documentId' => 'uuid:review-output',
            'instanceId' => 'uuid:review-output-v2',
            'originalDocumentId' => 'uuid:legacy-source',
            'renditionClass' => 'proof:pdf',
            'renditionParams' => 'engine=xelatex;profile=wordpress-review',
            'versionId' => '2',
            'derivedFrom' => [
                'documentId' => 'uuid:legacy-source',
                'instanceId' => 'uuid:legacy-source-v5',
                'originalDocumentId' => 'uuid:legacy-original',
                'renditionClass' => 'draft:docx',
                'renditionParams' => 'source=import-review',
                'manager' => 'WordPress importer',
                'managerVariant' => 'block-review',
                'managerTo' => 'wp:post:42',
                'managerUi' => 'review-panel',
            ],
            'history' => [
                [
                    'action' => 'converted',
                    'instanceId' => 'uuid:review-output-v1',
                    'when' => '2026-06-09T01:49:07Z',
                    'softwareAgent' => 'Pandoc PHP fake runner',
                    'changed' => '/metadata /pages',
                    'parameters' => 'engine=xelatex',
                ],
                [
                    'action' => 'saved',
                    'instanceId' => 'uuid:review-output-v2',
                    'when' => '2026-06-09T01:50:12Z',
                    'softwareAgent' => 'WordPress review queue',
                    'changed' => '/status',
                    'parameters' => 'no-execute=true',
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:10', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-original-document-id', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-rendition-class:proof:pdf', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-derived-from', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xmp-history:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner extracts bounded pdfx xmp identification from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/pdfx-xmp.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:pdfxid="http://www.npes.org/pdfx/ns/id/">',
            '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">PDF/X Review Packet</rdf:li></rdf:Alt></dc:title>',
            '<pdfxid:GTS_PDFXVersion>PDF/X-4</pdfxid:GTS_PDFXVersion>',
            '<pdfxid:GTS_PDFXConformance>PDF/X-4p</pdfxid:GTS_PDFXConformance>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R >>',
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
                'packets/pdfx-xmp.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/pdfx-xmp.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'packetBytes' => strlen($xmp),
            'packetSha256' => hash('sha256', $xmp),
            'title' => 'PDF/X Review Packet',
            'pdfxIdentification' => [
                'version' => 'PDF/X-4',
                'conformance' => 'PDF/X-4p',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:4', $diagnostics);
        $t->contains('pdf-byte-pdfx:PDF/X-4:PDF/X-4p', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner decodes bounded flatedecode pdf xmp metadata streams without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/compressed-xmp.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">',
            '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Compressed PDF Review Packet</rdf:li></rdf:Alt></dc:title>',
            '<dc:format>application/pdf</dc:format>',
            '<xmp:CreatorTool>Pandoc native compressed metadata handoff</xmp:CreatorTool>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $compressedXmp = DeflateStream::build($xmp);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . ' >>',
            'stream',
            $compressedXmp,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/compressed-xmp.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/compressed-xmp.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'packetBytes' => strlen($xmp),
            'packetSha256' => hash('sha256', $xmp),
            'decodedFilter' => 'FlateDecode',
            'compressedBytes' => strlen($compressedXmp),
            'title' => 'Compressed PDF Review Packet',
            'format' => 'application/pdf',
            'creatorTool' => 'Pandoc native compressed metadata handoff',
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:7', $diagnostics);
        $t->contains('pdf-byte-xmp-metadata-decoded:FlateDecode', $diagnostics);
        $t->contains('pdf-byte-xmp-metadata-compressed-bytes:' . strlen($compressedXmp), $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner extracts bounded pdfa xmp extension schemas from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/xmp-extension-schema.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">',
            '<pdfaExtension:schemas>',
            '<rdf:Bag>',
            '<rdf:li rdf:parseType="Resource">',
            '<pdfaSchema:schema>WordPress Review Metadata</pdfaSchema:schema>',
            '<pdfaSchema:namespaceURI>https://example.test/ns/wp-review/1.0/</pdfaSchema:namespaceURI>',
            '<pdfaSchema:prefix>wpreview</pdfaSchema:prefix>',
            '<pdfaSchema:property>',
            '<rdf:Seq>',
            '<rdf:li rdf:parseType="Resource">',
            '<pdfaProperty:name>sourceSlug</pdfaProperty:name>',
            '<pdfaProperty:valueType>Text</pdfaProperty:valueType>',
            '<pdfaProperty:category>external</pdfaProperty:category>',
            '<pdfaProperty:description>Original WordPress source slug</pdfaProperty:description>',
            '</rdf:li>',
            '<rdf:li rdf:parseType="Resource">',
            '<pdfaProperty:name>reviewerRole</pdfaProperty:name>',
            '<pdfaProperty:valueType>Text</pdfaProperty:valueType>',
            '<pdfaProperty:category>external</pdfaProperty:category>',
            '<pdfaProperty:description>Reviewer role imported from handoff packet</pdfaProperty:description>',
            '</rdf:li>',
            '</rdf:Seq>',
            '</pdfaSchema:property>',
            '</rdf:li>',
            '</rdf:Bag>',
            '</pdfaExtension:schemas>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R >>',
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
                'packets/xmp-extension-schema.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/xmp-extension-schema.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'packetBytes' => strlen($xmp),
            'packetSha256' => hash('sha256', $xmp),
            'pdfaExtensionSchemas' => [
                [
                    'schema' => 'WordPress Review Metadata',
                    'namespaceUri' => 'https://example.test/ns/wp-review/1.0/',
                    'prefix' => 'wpreview',
                    'properties' => [
                        [
                            'name' => 'reviewerRole',
                            'valueType' => 'Text',
                            'category' => 'external',
                            'description' => 'Reviewer role imported from handoff packet',
                        ],
                        [
                            'name' => 'sourceSlug',
                            'valueType' => 'Text',
                            'category' => 'external',
                            'description' => 'Original WordPress source slug',
                        ],
                    ],
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfXmpMetadata']);
        $t->contains('pdf-byte-xmp-metadata:3', $diagnostics);
        $t->contains('pdf-byte-pdfa-extension-schemas:1', $diagnostics);
        $t->contains('pdf-byte-pdfa-extension-properties:2', $diagnostics);
        $t->contains('pdf-byte-pdfa-extension-prefix:wpreview', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfXmpMetadata']);
    },

    'fake runner extracts bounded pdf page metadata streams from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-metadata.pdf']);
        $pageXmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">',
            '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Page 1 Review Packet</rdf:li></rdf:Alt></dc:title>',
            '<dc:creator><rdf:Seq><rdf:li>Migration Desk</rdf:li></rdf:Seq></dc:creator>',
            '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Page metadata handoff</rdf:li></rdf:Alt></dc:description>',
            '<xmp:CreatorTool>Pandoc page renderer</xmp:CreatorTool>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $filteredPageXmp = "<?xpacket begin=\"\"?>\n<rdf:RDF><rdf:Description /></rdf:RDF>\n<?xpacket end=\"w\"?>";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Metadata 10 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Metadata 11 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($pageXmp) . ' >>',
            'stream',
            $pageXmp,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($filteredPageXmp) . ' >>',
            'stream',
            $filteredPageXmp,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-metadata.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-metadata.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'metadataObject' => '10 0 R',
                'packetBytes' => strlen($pageXmp),
                'packetSha256' => hash('sha256', $pageXmp),
                'title' => 'Page 1 Review Packet',
                'description' => 'Page metadata handoff',
                'creatorTool' => 'Pandoc page renderer',
                'creators' => ['Migration Desk'],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'metadataObject' => '11 0 R',
                'packetBytes' => strlen($filteredPageXmp),
                'skipped' => 'filtered',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageMetadata']);
        $t->contains('pdf-byte-page-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-metadata-skipped:filtered', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-metadata-titles:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageMetadata']);
    },

    'fake runner extracts bounded pdf page piece info private metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/piece-info.pdf']);
        $privateStream = "review source path=handoff/piece-info.tex\n";
        $filteredPrivateStream = "compressed private page metadata\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /PieceInfo << /PandocHandoff 8 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /PieceInfo << /TeXSource << /LastModified (D:20260605123500Z) /Private 9 0 R >> >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /PieceInfo << /FilteredProducer << /Private 10 0 R >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /LastModified (D:20260605123000Z) /Private << /Source (pandoc-native) /Pipeline /pdf-engine-handoff /Revision 2 /Imported true /Cleared null >> >>',
            'endobj',
            '9 0 obj',
            '<< /Producer (XeTeX) /SourceFile (handoff/piece-info.tex) /Review true /Pass 2 /Length ' . strlen($privateStream) . ' >>',
            'stream',
            $privateStream,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Filter /FlateDecode /Stage (compressed) /Length ' . strlen($filteredPrivateStream) . ' >>',
            'stream',
            $filteredPrivateStream,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/piece-info.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/piece-info.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'catalog.PieceInfo',
                'page' => null,
                'pageObject' => null,
                'application' => 'PandocHandoff',
                'pieceObject' => '8 0 R',
                'lastModified' => 'D:20260605123000Z',
                'privateObject' => 'inline',
                'privateKeys' => ['Cleared', 'Imported', 'Pipeline', 'Revision', 'Source'],
                'privateValues' => [
                    'Cleared' => null,
                    'Imported' => true,
                    'Pipeline' => 'pdf-engine-handoff',
                    'Revision' => 2,
                    'Source' => 'pandoc-native',
                ],
                'privateStreamBytes' => null,
                'privateStreamSha256' => null,
                'privateStreamSkipped' => null,
            ],
            [
                'source' => 'page:3 0 R.PieceInfo',
                'page' => 1,
                'pageObject' => '3 0 R',
                'application' => 'TeXSource',
                'pieceObject' => 'inline',
                'lastModified' => 'D:20260605123500Z',
                'privateObject' => '9 0 R',
                'privateKeys' => ['Pass', 'Producer', 'Review', 'SourceFile'],
                'privateValues' => [
                    'Pass' => 2,
                    'Producer' => 'XeTeX',
                    'Review' => true,
                    'SourceFile' => 'handoff/piece-info.tex',
                ],
                'privateStreamBytes' => strlen($privateStream),
                'privateStreamSha256' => hash('sha256', $privateStream),
                'privateStreamSkipped' => null,
            ],
            [
                'source' => 'page:4 0 R.PieceInfo',
                'page' => 2,
                'pageObject' => '4 0 R',
                'application' => 'FilteredProducer',
                'pieceObject' => 'inline',
                'lastModified' => null,
                'privateObject' => '10 0 R',
                'privateKeys' => ['Stage'],
                'privateValues' => [
                    'Stage' => 'compressed',
                ],
                'privateStreamBytes' => strlen($filteredPrivateStream),
                'privateStreamSha256' => null,
                'privateStreamSkipped' => 'filtered',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPieceInfo']);
        $t->contains('pdf-byte-piece-info:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-piece-info-pages:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-piece-info-private-streams:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-piece-info-private-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPieceInfo']);
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

    'fake runner extracts bounded page-level pdf output intents from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-output-intent.pdf']);
        $iccProfile = "fake page proof ICC profile bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /OutputIntents [8 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (FOGRA39) /RegistryName (https://www.color.org) /Info (Page proof intent) >>] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB page proof) /OutputCondition (Page-local display proof) /RegistryName (http://www.color.org) /Info (Page-level sRGB profile) /DestOutputProfile 9 0 R >>',
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
                'packets/page-output-intent.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-output-intent.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'source' => 'page:3 0 R.OutputIntents',
                'type' => 'OutputIntent',
                'subtype' => 'GTS_PDFA1',
                'outputConditionIdentifier' => 'sRGB page proof',
                'outputCondition' => 'Page-local display proof',
                'registryName' => 'http://www.color.org',
                'info' => 'Page-level sRGB profile',
                'destOutputProfile' => '9 0 R',
                'profileComponents' => 3,
                'profileAlternate' => 'DeviceRGB',
                'profileBytes' => strlen($iccProfile),
                'profileSha256' => hash('sha256', $iccProfile),
                'profileSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'source' => 'page:3 0 R.OutputIntents',
                'type' => 'OutputIntent',
                'subtype' => 'GTS_PDFX',
                'outputConditionIdentifier' => 'FOGRA39',
                'outputCondition' => null,
                'registryName' => 'https://www.color.org',
                'info' => 'Page proof intent',
                'destOutputProfile' => null,
                'profileComponents' => null,
                'profileAlternate' => null,
                'profileBytes' => null,
                'profileSha256' => null,
                'profileSkipped' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same([], $result['pdfOutputIntents']);
        $t->same($expected, $result['pdfPageOutputIntents']);
        $t->contains('pdf-byte-page-output-intents:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-output-profiles:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageOutputIntents']);
    },

    'fake runner summarizes pdfx xmp and output intent consistency policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $okPlan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/pdfx-policy-ok.pdf']);
        $reviewPlan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/pdfx-policy-review.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:pdfxid="http://www.npes.org/pdfx/ns/id/">',
            '<pdfxid:GTS_PDFXVersion>PDF/X-4</pdfxid:GTS_PDFXVersion>',
            '<pdfxid:GTS_PDFXConformance>PDF/X-4p</pdfxid:GTS_PDFXConformance>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $iccProfile = "fake CMYK output profile bytes\n";
        $okPdf = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 8 0 R /OutputIntents [9 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . ' >>',
            'stream',
            $xmp,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (FOGRA39) /OutputCondition (FOGRA39 coated proof) /RegistryName (https://www.color.org) /Info (Document proof intent) /DestOutputProfile 10 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /N 4 /Alternate /DeviceCMYK /Length ' . strlen($iccProfile) . ' >>',
            'stream',
            $iccProfile,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);
        $reviewPdf = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /OutputIntents [9 0 R] >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . ' >>',
            'stream',
            $xmp,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFX /RegistryName (https://www.color.org) /Info (Page-only proof intent) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $ok = $handoff->fakeRun($okPlan, [
            'files' => [
                'packets/pdfx-policy-ok.pdf' => $okPdf,
            ],
        ]);
        $review = $handoff->fakeRun($reviewPlan, [
            'files' => [
                'packets/pdfx-policy-review.pdf' => $reviewPdf,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($okPlan, [
            [
                'files' => [
                    'packets/pdfx-policy-ok.pdf' => $okPdf,
                ],
            ],
        ]);

        $expectedOk = [
            'reviewStatus' => 'ok',
            'pdfxVersion' => 'PDF/X-4',
            'pdfxConformance' => 'PDF/X-4p',
            'pdfxIntentCount' => 1,
            'documentPdfxIntentCount' => 1,
            'pagePdfxIntentCount' => 0,
            'embeddedProfileCount' => 1,
            'issues' => [],
            'intents' => [
                [
                    'scope' => 'document',
                    'page' => null,
                    'source' => 'catalog.OutputIntents[0]',
                    'subtype' => 'GTS_PDFX',
                    'outputConditionIdentifier' => 'FOGRA39',
                    'registryName' => 'https://www.color.org',
                    'hasDestOutputProfile' => true,
                    'profileComponents' => 4,
                    'profileSkipped' => null,
                    'pdfxIntent' => true,
                ],
            ],
        ];
        $expectedReview = [
            'reviewStatus' => 'review',
            'pdfxVersion' => 'PDF/X-4',
            'pdfxConformance' => 'PDF/X-4p',
            'pdfxIntentCount' => 1,
            'documentPdfxIntentCount' => 0,
            'pagePdfxIntentCount' => 1,
            'embeddedProfileCount' => 0,
            'issues' => [
                'pdfx-output-intent-page-scoped',
                'missing-output-condition-identifier',
                'missing-dest-output-profile',
            ],
            'intents' => [
                [
                    'scope' => 'page',
                    'page' => 1,
                    'source' => 'page:3 0 R.OutputIntents',
                    'subtype' => 'GTS_PDFX',
                    'outputConditionIdentifier' => null,
                    'registryName' => 'https://www.color.org',
                    'hasDestOutputProfile' => false,
                    'profileComponents' => null,
                    'profileSkipped' => null,
                    'pdfxIntent' => true,
                ],
            ],
        ];

        $t->same(true, $ok['ok']);
        $t->same($expectedOk, $ok['pdfOutputIntentPolicy']);
        $t->contains('pdf-byte-output-intent-policy:ok', implode(',', $ok['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-document-pdfx-intents:1', implode(',', $ok['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-embedded-profiles:1', implode(',', $ok['diagnostics']));
        $t->same(true, $review['ok']);
        $t->same($expectedReview, $review['pdfOutputIntentPolicy']);
        $t->contains('pdf-byte-output-intent-policy:review', implode(',', $review['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-page-pdfx-intents:1', implode(',', $review['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-issues:3', implode(',', $review['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-issue:missing-dest-output-profile:1', implode(',', $review['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-issue:missing-output-condition-identifier:1', implode(',', $review['diagnostics']));
        $t->contains('pdf-byte-output-intent-policy-issue:pdfx-output-intent-page-scoped:1', implode(',', $review['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedOk, $sequence['finalPdfOutputIntentPolicy']);
    },

    'fake runner extracts bounded pdf catalog presentation preferences from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/presentation.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /PageLayout /TwoPageRight /PageMode /UseOutlines /OpenAction [3 0 R /FitH 720] /ViewerPreferences << /DisplayDocTitle true /HideToolbar true /PickTrayByPDFSize true /Direction /L2R /PrintScaling /None /Duplex /DuplexFlipLongEdge /NumCopies 2 /PrintPageRange [1 2 4 4] /Enforce [/PrintScaling /Duplex] >> >>',
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
            'PickTrayByPDFSize' => true,
            'Direction' => 'L2R',
            'PrintScaling' => 'None',
            'Duplex' => 'DuplexFlipLongEdge',
            'NumCopies' => 2,
            'PrintPageRange' => [1, 2, 4, 4],
            'Enforce' => ['PrintScaling', 'Duplex'],
        ], $result['pdfViewerPreferences']);
        $t->contains('pdf-byte-page-layout:TwoPageRight', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-mode:UseOutlines', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-open-action:destination', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-viewer-preferences:9', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-viewer-print-page-ranges:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-viewer-enforced-preferences:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same('TwoPageRight', $sequence['finalPdfPageLayout']);
        $t->same('UseOutlines', $sequence['finalPdfPageMode']);
        $t->same($result['pdfOpenAction'], $sequence['finalPdfOpenAction']);
        $t->same($result['pdfViewerPreferences'], $sequence['finalPdfViewerPreferences']);
    },

    'fake runner extracts bounded pdf viewer print policy arrays from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/print-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /PrintScaling /None /Duplex /DuplexFlipShortEdge /PrintPageRange 8 0 R /Enforce 9 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '[1 1 3 5 8 8]',
            'endobj',
            '9 0 obj',
            '[/PrintScaling /Duplex]',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/print-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/print-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'PrintScaling' => 'None',
            'Duplex' => 'DuplexFlipShortEdge',
            'PrintPageRange' => [1, 1, 3, 5, 8, 8],
            'Enforce' => ['PrintScaling', 'Duplex'],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfViewerPreferences']);
        $t->contains('pdf-byte-viewer-preferences:4', $diagnostics);
        $t->contains('pdf-byte-viewer-print-page-ranges:3', $diagnostics);
        $t->contains('pdf-byte-viewer-enforced-preferences:2', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfViewerPreferences']);
    },

    'fake runner summarizes bounded pdf viewer preference review policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/viewer-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /HideToolbar true /DisplayDocTitle true /PickTrayByPDFSize true /Direction /R2L /PrintScaling /None /Duplex /DuplexFlipShortEdge /NumCopies 3 /PrintPageRange [1 2 5 5] /Enforce [/PrintScaling /Duplex /HideToolbar] >> >>',
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
                'packets/viewer-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/viewer-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'reviewStatus' => 'review',
            'preferenceCount' => 9,
            'uiPreferences' => [
                'HideToolbar' => true,
                'DisplayDocTitle' => true,
                'Direction' => 'R2L',
            ],
            'printPreferences' => [
                'PickTrayByPDFSize' => true,
                'PrintScaling' => 'None',
                'Duplex' => 'DuplexFlipShortEdge',
                'NumCopies' => 3,
                'PrintPageRange' => [1, 2, 5, 5],
            ],
            'enforcedPreferences' => ['PrintScaling', 'Duplex', 'HideToolbar'],
            'enforcedUiPreferences' => ['HideToolbar'],
            'enforcedPrintPreferences' => ['PrintScaling', 'Duplex'],
            'printPageRangePairs' => 2,
            'printPageRanges' => [
                ['start' => 1, 'end' => 2],
                ['start' => 5, 'end' => 5],
            ],
            'issues' => [
                'bounded-print-page-range',
                'duplex-printing-requested',
                'enforces-viewer-preferences',
                'hides-viewer-ui',
                'multiple-print-copies',
                'non-default-print-scaling',
                'print-tray-by-pdf-size',
                'right-to-left-viewer-direction',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfViewerPreferencePolicy']);
        $t->contains('pdf-byte-viewer-preference-policy:review', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-ui:3', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-print:5', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-enforced:3', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-print-ranges:2', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-issues:8', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-issue:hides-viewer-ui:1', $diagnostics);
        $t->contains('pdf-byte-viewer-preference-policy-issue:non-default-print-scaling:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfViewerPreferencePolicy']);
    },

    'fake runner extracts bounded pdf catalog requirements and rendering policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/requirements.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /NeedsRendering true /Requirements [8 0 R << /Type /Requirement /S /EnableJavaScripts /RH << /C (ReviewApp) /V (1.0) /Name (Script review handler) >> >>] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Requirement /S /3D /RH 9 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /RequirementHandler /C (Acrobat) /V (9.0) /Name (3D Handler) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/requirements.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/requirements.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '8 0 R',
                'type' => 'Requirement',
                'subtype' => '3D',
                'handlerObject' => '9 0 R',
                'handlerType' => 'RequirementHandler',
                'handlerName' => '3D Handler',
                'handlerCode' => 'Acrobat',
                'handlerVersion' => '9.0',
                'keys' => ['RH', 'S', 'Type'],
            ],
            [
                'object' => null,
                'type' => 'Requirement',
                'subtype' => 'EnableJavaScripts',
                'handlerObject' => 'inline',
                'handlerType' => null,
                'handlerName' => 'Script review handler',
                'handlerCode' => 'ReviewApp',
                'handlerVersion' => '1.0',
                'keys' => ['RH', 'S', 'Type'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same(true, $result['pdfNeedsRendering'] ?? null);
        $t->same($expected, $result['pdfCatalogRequirements'] ?? null);
        $diagnostics = implode(',', $result['diagnostics']);
        $t->contains('pdf-byte-needs-rendering:true', $diagnostics);
        $t->contains('pdf-byte-catalog-requirements:2', $diagnostics);
        $t->contains('pdf-byte-catalog-requirement:3D:1', $diagnostics);
        $t->contains('pdf-byte-catalog-requirement:EnableJavaScripts:1', $diagnostics);
        $t->contains('pdf-byte-catalog-requirement-handlers:2', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same(true, $sequence['finalPdfNeedsRendering'] ?? null);
        $t->same($expected, $sequence['finalPdfCatalogRequirements'] ?? null);
    },

    'fake runner extracts bounded pdf catalog uri base from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/uri-base.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /URI 6 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Link /Rect [72 640 360 672] /A << /S /URI /URI (assets/review-chart.png) >> >>',
            'endobj',
            '6 0 obj',
            '<< /Base <FEFF00680074007400700073003A002F002F006500780061006D0070006C0065002E0074006500730074002F007200650076006900650077002F> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/uri-base.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/uri-base.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same('https://example.test/review/', $result['pdfUriBase']);
        $t->same(['assets/review-chart.png'], $result['pdfLinkTargets']);
        $t->contains('pdf-byte-uri-base:https://example.test/review/', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same('https://example.test/review/', $sequence['finalPdfUriBase']);
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

    'fake runner inventories bounded pdf catalog name tree categories from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/name-trees.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R >>',
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
            '<< /Dests 9 0 R /EmbeddedFiles 13 0 R /JavaScript << /Names [(OpenReview) 17 0 R] >> /Templates << /Kids [18 0 R 19 0 R] /Limits [(cover) (summary)] >> >>',
            'endobj',
            '9 0 obj',
            '<< /Kids [10 0 R 11 0 R] /Limits [(appendix) (intro)] >>',
            'endobj',
            '10 0 obj',
            '<< /Limits [(appendix) (appendix)] /Names [(appendix) [4 0 R /Fit]] >>',
            'endobj',
            '11 0 obj',
            '<< /Limits [(intro) (intro)] /Names [(intro) 12 0 R] >>',
            'endobj',
            '12 0 obj',
            '[3 0 R /FitH 720]',
            'endobj',
            '13 0 obj',
            '<< /Names [(review-assets.zip) 14 0 R] >>',
            'endobj',
            '14 0 obj',
            '<< /Type /Filespec /F (review-assets.zip) >>',
            'endobj',
            '17 0 obj',
            '<< /S /JavaScript /JS (app.alert("review")) >>',
            'endobj',
            '18 0 obj',
            '<< /Limits [(cover) (cover)] /Names [(cover) 20 0 R] >>',
            'endobj',
            '19 0 obj',
            '<< /Limits [(summary) (summary)] /Names [(summary) 21 0 R] >>',
            'endobj',
            '20 0 obj',
            '<< /Type /Template /Name (cover) >>',
            'endobj',
            '21 0 obj',
            '<< /Type /Template /Name (summary) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/name-trees.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/name-trees.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'category' => 'Dests',
                'source' => 'catalog.Names.Dests',
                'entryCount' => 2,
                'names' => ['appendix', 'intro'],
                'valueKinds' => ['array' => 1, 'reference' => 1],
                'valueReferences' => ['12 0 R'],
                'kidCount' => 2,
                'limits' => ['appendix', 'intro'],
            ],
            [
                'category' => 'EmbeddedFiles',
                'source' => 'catalog.Names.EmbeddedFiles',
                'entryCount' => 1,
                'names' => ['review-assets.zip'],
                'valueKinds' => ['reference' => 1],
                'valueReferences' => ['14 0 R'],
                'kidCount' => 0,
                'limits' => [],
            ],
            [
                'category' => 'JavaScript',
                'source' => 'catalog.Names.JavaScript',
                'entryCount' => 1,
                'names' => ['OpenReview'],
                'valueKinds' => ['reference' => 1],
                'valueReferences' => ['17 0 R'],
                'kidCount' => 0,
                'limits' => [],
            ],
            [
                'category' => 'Templates',
                'source' => 'catalog.Names.Templates',
                'entryCount' => 2,
                'names' => ['cover', 'summary'],
                'valueKinds' => ['reference' => 2],
                'valueReferences' => ['20 0 R', '21 0 R'],
                'kidCount' => 2,
                'limits' => ['cover', 'summary'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfNameTrees']);
        $diagnostics = implode(',', $result['diagnostics']);
        $t->contains('pdf-byte-name-trees:4', $diagnostics);
        $t->contains('pdf-byte-name-tree:Dests:2', $diagnostics);
        $t->contains('pdf-byte-name-tree:EmbeddedFiles:1', $diagnostics);
        $t->contains('pdf-byte-name-tree:JavaScript:1', $diagnostics);
        $t->contains('pdf-byte-name-tree:Templates:2', $diagnostics);
        $t->contains('pdf-byte-name-tree-kids:4', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfNameTrees']);
    },

    'fake runner summarizes bounded pdf name tree limits policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/name-tree-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R >>',
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
            '<< /Dests 9 0 R /Templates 18 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Kids [10 0 R 11 0 R] /Limits [(appendix) (intro)] >>',
            'endobj',
            '10 0 obj',
            '<< /Limits [(appendix) (appendix)] /Names [(appendix) [4 0 R /Fit]] >>',
            'endobj',
            '11 0 obj',
            '<< /Limits [(intro) (intro)] /Names [(intro) 12 0 R] >>',
            'endobj',
            '12 0 obj',
            '[3 0 R /FitH 720]',
            'endobj',
            '18 0 obj',
            '<< /Kids [19 0 R 20 0 R] /Limits [(cover) (summary)] >>',
            'endobj',
            '19 0 obj',
            '<< /Limits [(cover) (cover)] /Names [(summary) 21 0 R] >>',
            'endobj',
            '20 0 obj',
            '<< /Limits [(appendix) (summary)] /Names [(appendix) 22 0 R] >>',
            'endobj',
            '21 0 obj',
            '<< /Type /Template /Name (summary) >>',
            'endobj',
            '22 0 obj',
            '<< /Type /Template /Name (appendix) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/name-tree-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/name-tree-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'category' => 'Dests',
                'source' => 'catalog.Names.Dests',
                'reviewStatus' => 'ok',
                'entryCount' => 2,
                'kidCount' => 2,
                'limits' => ['appendix', 'intro'],
                'issues' => [],
                'nodes' => [
                    [
                        'source' => 'catalog.Names.Dests',
                        'object' => '9 0 R',
                        'kind' => 'root',
                        'entryCount' => 0,
                        'names' => [],
                        'kidCount' => 2,
                        'limits' => ['appendix', 'intro'],
                        'reviewStatus' => 'ok',
                        'issues' => [],
                    ],
                    [
                        'source' => 'catalog.Names.Dests.Kids.10 0 R',
                        'object' => '10 0 R',
                        'kind' => 'kid',
                        'entryCount' => 1,
                        'names' => ['appendix'],
                        'kidCount' => 0,
                        'limits' => ['appendix', 'appendix'],
                        'reviewStatus' => 'ok',
                        'issues' => [],
                    ],
                    [
                        'source' => 'catalog.Names.Dests.Kids.11 0 R',
                        'object' => '11 0 R',
                        'kind' => 'kid',
                        'entryCount' => 1,
                        'names' => ['intro'],
                        'kidCount' => 0,
                        'limits' => ['intro', 'intro'],
                        'reviewStatus' => 'ok',
                        'issues' => [],
                    ],
                ],
            ],
            [
                'category' => 'Templates',
                'source' => 'catalog.Names.Templates',
                'reviewStatus' => 'review',
                'entryCount' => 2,
                'kidCount' => 2,
                'limits' => ['cover', 'summary', 'appendix'],
                'issues' => [
                    'kid-limits-outside-parent',
                    'kid-limits-overlap-or-unsorted',
                    'names-outside-limits',
                ],
                'nodes' => [
                    [
                        'source' => 'catalog.Names.Templates',
                        'object' => '18 0 R',
                        'kind' => 'root',
                        'entryCount' => 0,
                        'names' => [],
                        'kidCount' => 2,
                        'limits' => ['cover', 'summary'],
                        'reviewStatus' => 'review',
                        'issues' => [
                            'kid-limits-outside-parent',
                            'kid-limits-overlap-or-unsorted',
                        ],
                    ],
                    [
                        'source' => 'catalog.Names.Templates.Kids.19 0 R',
                        'object' => '19 0 R',
                        'kind' => 'kid',
                        'entryCount' => 1,
                        'names' => ['summary'],
                        'kidCount' => 0,
                        'limits' => ['cover', 'cover'],
                        'reviewStatus' => 'review',
                        'issues' => ['names-outside-limits'],
                    ],
                    [
                        'source' => 'catalog.Names.Templates.Kids.20 0 R',
                        'object' => '20 0 R',
                        'kind' => 'kid',
                        'entryCount' => 1,
                        'names' => ['appendix'],
                        'kidCount' => 0,
                        'limits' => ['appendix', 'summary'],
                        'reviewStatus' => 'ok',
                        'issues' => [],
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfNameTreePolicies']);
        $diagnostics = implode(',', $result['diagnostics']);
        $t->contains('pdf-byte-name-tree-policies:2', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy:Dests:ok', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy:Templates:review', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-nodes:6', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-review:1', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-issues:3', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-issue:kid-limits-outside-parent:1', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-issue:kid-limits-overlap-or-unsorted:1', $diagnostics);
        $t->contains('pdf-byte-name-tree-policy-issue:names-outside-limits:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfNameTreePolicies']);
    },

    'fake runner extracts bounded pdf destination fit option metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/dest-options.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /XYZ 72 720 1.25] /Outlines 9 0 R /Names << /Dests 8 0 R >> /Dests << /legacy [4 0 R /FitR 10 20 300 500] >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [6 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Link /Rect [72 640 360 672] /Dest [4 0 R /FitBH 640] >>',
            'endobj',
            '8 0 obj',
            '<< /Names [(intro) [3 0 R /FitH 700] (named) (chapter-two) (zoomed) 11 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>',
            'endobj',
            '10 0 obj',
            '<< /Title (Appendix) /Parent 9 0 R /Dest [4 0 R /FitV 144] >>',
            'endobj',
            '11 0 obj',
            '<< /D [4 0 R /XYZ null 512 2] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/dest-options.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/dest-options.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'annotation:6 0 R.Dest',
                'name' => null,
                'pageObject' => '4 0 R',
                'target' => null,
                'fit' => 'FitBH',
                'arguments' => [640.0],
                'left' => null,
                'top' => 640.0,
                'right' => null,
                'bottom' => null,
                'zoom' => null,
            ],
            [
                'source' => 'catalog.Dests',
                'name' => 'legacy',
                'pageObject' => '4 0 R',
                'target' => null,
                'fit' => 'FitR',
                'arguments' => [10.0, 20.0, 300.0, 500.0],
                'left' => 10.0,
                'top' => 500.0,
                'right' => 300.0,
                'bottom' => 20.0,
                'zoom' => null,
            ],
            [
                'source' => 'catalog.Names.Dests',
                'name' => 'intro',
                'pageObject' => '3 0 R',
                'target' => null,
                'fit' => 'FitH',
                'arguments' => [700.0],
                'left' => null,
                'top' => 700.0,
                'right' => null,
                'bottom' => null,
                'zoom' => null,
            ],
            [
                'source' => 'catalog.Names.Dests',
                'name' => 'named',
                'pageObject' => null,
                'target' => 'chapter-two',
                'fit' => null,
                'arguments' => [],
                'left' => null,
                'top' => null,
                'right' => null,
                'bottom' => null,
                'zoom' => null,
            ],
            [
                'source' => 'catalog.Names.Dests',
                'name' => 'zoomed',
                'pageObject' => '4 0 R',
                'target' => null,
                'fit' => 'XYZ',
                'arguments' => [null, 512.0, 2.0],
                'left' => null,
                'top' => 512.0,
                'right' => null,
                'bottom' => null,
                'zoom' => 2.0,
            ],
            [
                'source' => 'catalog.OpenAction',
                'name' => null,
                'pageObject' => '3 0 R',
                'target' => null,
                'fit' => 'XYZ',
                'arguments' => [72.0, 720.0, 1.25],
                'left' => 72.0,
                'top' => 720.0,
                'right' => null,
                'bottom' => null,
                'zoom' => 1.25,
            ],
            [
                'source' => 'outline:10 0 R.Dest',
                'name' => null,
                'pageObject' => '4 0 R',
                'target' => null,
                'fit' => 'FitV',
                'arguments' => [144.0],
                'left' => 144.0,
                'top' => null,
                'right' => null,
                'bottom' => null,
                'zoom' => null,
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfDestinationOptions']);
        $t->contains('pdf-byte-destination-options:7', $diagnostics);
        $t->contains('pdf-byte-destination-fit-arguments:6', $diagnostics);
        $t->contains('pdf-byte-destination-named-targets:1', $diagnostics);
        $t->contains('pdf-byte-destination-fit:XYZ:2', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfDestinationOptions']);
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

    'fake runner summarizes bounded pdf structure role map usage from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/role-map.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R 12 0 R 13 0 R] /RoleMap << /Chapter /Sect /ReviewFigure /Figure /CustomAside /ReviewAside /LoopA /LoopB /LoopB /LoopA >> >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Chapter /P 9 0 R /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /ReviewFigure /P 9 0 R /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /Type /StructElem /S /CustomAside /P 9 0 R /K 2 >>',
            'endobj',
            '13 0 obj',
            '<< /Type /StructElem /S /LoopA /P 9 0 R /K 3 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/role-map.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/role-map.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '10 0 R',
                'type' => 'Chapter',
                'mappedType' => 'Sect',
                'roleChain' => ['Chapter', 'Sect'],
                'mappedByRoleMap' => true,
                'standardRole' => true,
                'cycle' => false,
                'issues' => [],
            ],
            [
                'object' => '11 0 R',
                'type' => 'ReviewFigure',
                'mappedType' => 'Figure',
                'roleChain' => ['ReviewFigure', 'Figure'],
                'mappedByRoleMap' => true,
                'standardRole' => true,
                'cycle' => false,
                'issues' => [],
            ],
            [
                'object' => '12 0 R',
                'type' => 'CustomAside',
                'mappedType' => 'ReviewAside',
                'roleChain' => ['CustomAside', 'ReviewAside'],
                'mappedByRoleMap' => true,
                'standardRole' => false,
                'cycle' => false,
                'issues' => ['role-map-nonstandard-terminal'],
            ],
            [
                'object' => '13 0 R',
                'type' => 'LoopA',
                'mappedType' => 'LoopA',
                'roleChain' => ['LoopA', 'LoopB', 'LoopA'],
                'mappedByRoleMap' => true,
                'standardRole' => false,
                'cycle' => true,
                'issues' => ['role-map-cycle'],
            ],
        ];

        $diagnostics = implode(',', $result['diagnostics']);
        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureRoleMapUsage']);
        $t->contains('pdf-byte-structure-role-map:5', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-usage:4', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-mapped:4', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-standard:2', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-cycles:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-terminal:Figure:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-terminal:LoopA:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-terminal:ReviewAside:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-terminal:Sect:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-issues:2', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-issue:role-map-cycle:1', $diagnostics);
        $t->contains('pdf-byte-structure-role-map-issue:role-map-nonstandard-terminal:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureRoleMapUsage']);
    },

    'fake runner extracts bounded pdf structure parent tree number mappings from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/parent-tree.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /ParentTree 12 0 R /ParentTreeNextKey 4 >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /H1 /P 9 0 R /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /K [1 2] /Alt (Review figure) >>',
            'endobj',
            '12 0 obj',
            '<< /Kids [14 0 R 15 0 R] /Limits [0 3] >>',
            'endobj',
            '14 0 obj',
            '<< /Nums [0 10 0 R 1 [10 0 R 11 0 R]] /Limits [0 1] >>',
            'endobj',
            '15 0 obj',
            '<< /Nums [2 null 3 99 0 R] /Limits [2 3] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/parent-tree.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/parent-tree.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'source' => 'structTreeRoot.ParentTree.Kids.14 0 R',
                'nodeObject' => '14 0 R',
                'mcid' => 0,
                'valueKind' => 'reference',
                'valueObject' => '10 0 R',
                'arrayCount' => null,
                'structureReferences' => ['10 0 R'],
                'missingReferences' => [],
                'limits' => [0, 1],
            ],
            [
                'source' => 'structTreeRoot.ParentTree.Kids.14 0 R',
                'nodeObject' => '14 0 R',
                'mcid' => 1,
                'valueKind' => 'array',
                'valueObject' => null,
                'arrayCount' => 2,
                'structureReferences' => ['10 0 R', '11 0 R'],
                'missingReferences' => [],
                'limits' => [0, 1],
            ],
            [
                'source' => 'structTreeRoot.ParentTree.Kids.15 0 R',
                'nodeObject' => '15 0 R',
                'mcid' => 2,
                'valueKind' => 'null',
                'valueObject' => null,
                'arrayCount' => null,
                'structureReferences' => [],
                'missingReferences' => [],
                'limits' => [2, 3],
            ],
            [
                'source' => 'structTreeRoot.ParentTree.Kids.15 0 R',
                'nodeObject' => '15 0 R',
                'mcid' => 3,
                'valueKind' => 'reference',
                'valueObject' => '99 0 R',
                'arrayCount' => null,
                'structureReferences' => ['99 0 R'],
                'missingReferences' => ['99 0 R'],
                'limits' => [2, 3],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureParentTree'] ?? null);
        $t->contains('pdf-byte-structure-parent-tree:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-parent-tree-arrays:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-parent-tree-null:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-parent-tree-missing:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureParentTree'] ?? null);
    },

    'fake runner summarizes bounded pdf structure parent tree integrity policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/parent-policy.pdf']);
        $contentBytes = implode("\n", [
            '/Span << /MCID 0 >> BDC',
            'BT (Title) Tj ET',
            'EMC',
            '/Figure << /MCID 5 >> BDC',
            'BT (Figure) Tj ET',
            'EMC',
            '/Artifact << /Type /Pagination /MCID 7 >> BDC',
            'BT (Header artifact) Tj ET',
            'EMC',
            '',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /Properties << /Figure 21 0 R >> >> /Contents 20 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /ParentTree 12 0 R /ParentTreeNextKey 6 >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /H1 /P 9 0 R /Pg 3 0 R /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /K 5 /Alt (Figure) >>',
            'endobj',
            '12 0 obj',
            '<< /Nums [0 10 0 R 0 11 0 R 2 99 0 R 4 13 0 R] /Limits [0 3] >>',
            'endobj',
            '13 0 obj',
            '<< /Type /Annot /Subtype /Text >>',
            'endobj',
            '20 0 obj',
            '<< /Length ' . strlen($contentBytes) . ' >>',
            'stream',
            $contentBytes,
            'endstream',
            'endobj',
            '21 0 obj',
            '<< /MCID 5 /Alt (Figure property metadata) >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/parent-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/parent-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'source' => 'structure-parent-tree',
            'reviewStatus' => 'review',
            'parentTreeEntryCount' => 4,
            'structureElementCount' => 2,
            'markedContentMcidCount' => 2,
            'referencedStructureObjects' => ['10 0 R', '11 0 R', '13 0 R', '99 0 R'],
            'missingStructureReferences' => ['99 0 R'],
            'nonStructureReferences' => ['13 0 R'],
            'duplicateMcids' => [0],
            'outOfLimitMcids' => [4],
            'markedContentMcids' => [0, 5],
            'missingMarkedContentMcids' => [5],
            'issues' => [
                'duplicate-parent-mcid',
                'marked-content-mcid-missing-parent',
                'missing-structure-reference',
                'non-structure-reference',
                'parent-mcid-outside-limits',
            ],
            'entries' => [
                [
                    'source' => 'structTreeRoot.ParentTree',
                    'nodeObject' => '12 0 R',
                    'mcid' => 0,
                    'valueKind' => 'reference',
                    'structureReferences' => ['10 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['duplicate-parent-mcid'],
                ],
                [
                    'source' => 'structTreeRoot.ParentTree',
                    'nodeObject' => '12 0 R',
                    'mcid' => 0,
                    'valueKind' => 'reference',
                    'structureReferences' => ['11 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['duplicate-parent-mcid'],
                ],
                [
                    'source' => 'structTreeRoot.ParentTree',
                    'nodeObject' => '12 0 R',
                    'mcid' => 2,
                    'valueKind' => 'reference',
                    'structureReferences' => ['99 0 R'],
                    'missingReferences' => ['99 0 R'],
                    'reviewStatus' => 'review',
                    'issues' => ['missing-structure-reference'],
                ],
                [
                    'source' => 'structTreeRoot.ParentTree',
                    'nodeObject' => '12 0 R',
                    'mcid' => 4,
                    'valueKind' => 'reference',
                    'structureReferences' => ['13 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['non-structure-reference', 'parent-mcid-outside-limits'],
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureParentTreePolicy'] ?? null);
        $t->contains('pdf-byte-structure-parent-tree-policy:review', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-entries:4', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-marked-mcids:2', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-missing-references:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-non-structure-references:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-duplicate-mcids:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-out-of-limit-mcids:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-missing-marked-content-mcids:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issues:5', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issue:duplicate-parent-mcid:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issue:marked-content-mcid-missing-parent:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issue:missing-structure-reference:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issue:non-structure-reference:1', $diagnostics);
        $t->contains('pdf-byte-structure-parent-tree-policy-issue:parent-mcid-outside-limits:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureParentTreePolicy'] ?? null);
    },

    'fake runner extracts bounded pdf structure element accessibility metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-elements.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /K [0 1] /Alt (Migration chart thumbnail) /ActualText <FEFF00430068006100720074002000730075006D006D006100720079> /Lang (en-US) /T (review-figure) >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /P /P 9 0 R /Pg 3 0 R /K 2 /ActualText (Reviewer note paragraph) /Lang /en-GB >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-elements.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-elements.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'parent' => '9 0 R',
                'pageObject' => '3 0 R',
                'id' => null,
                'alt' => 'Migration chart thumbnail',
                'actualText' => 'Chart summary',
                'language' => 'en-US',
                'title' => 'review-figure',
                'childCount' => 2,
            ],
            [
                'object' => '11 0 R',
                'type' => 'P',
                'parent' => '9 0 R',
                'pageObject' => '3 0 R',
                'id' => null,
                'alt' => null,
                'actualText' => 'Reviewer note paragraph',
                'language' => 'en-GB',
                'title' => null,
                'childCount' => 1,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureElements']);
        $t->contains('pdf-byte-structure-elements:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-alt-text:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-actual-text:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-languages:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureElements']);
    },

    'fake runner extracts bounded pdf structure attribute metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-attributes.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /A [12 0 R << /O /Table /RowSpan 2 /ColSpan 3 /Scope /Both /Headers [14 0 R 15 0 R] >>] /K 0 /Alt (Migration chart) >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /LBody /P 9 0 R /Pg 3 0 R /A << /O /List /ListNumbering /LowerRoman >> /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /O /Layout /Placement /Block /WritingMode /LrTb /TextAlign /Center /BlockAlign /Middle /InlineAlign /Center /BBox [72 648 540 720] /R 4 >>',
            'endobj',
            '14 0 obj',
            '<< /Type /StructElem /S /TH /P 9 0 R >>',
            'endobj',
            '15 0 obj',
            '<< /Type /StructElem /S /TH /P 9 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-attributes.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-attributes.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => '12 0 R',
                'owner' => 'Layout',
                'revision' => 4,
                'placement' => 'Block',
                'writingMode' => 'LrTb',
                'textAlign' => 'Center',
                'blockAlign' => 'Middle',
                'inlineAlign' => 'Center',
                'listNumbering' => null,
                'bbox' => [72.0, 648.0, 540.0, 720.0],
                'rowSpan' => null,
                'colSpan' => null,
                'scope' => null,
                'headers' => [],
            ],
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => 'inline',
                'owner' => 'Table',
                'revision' => null,
                'placement' => null,
                'writingMode' => null,
                'textAlign' => null,
                'blockAlign' => null,
                'inlineAlign' => null,
                'listNumbering' => null,
                'bbox' => null,
                'rowSpan' => 2,
                'colSpan' => 3,
                'scope' => 'Both',
                'headers' => ['14 0 R', '15 0 R'],
            ],
            [
                'object' => '11 0 R',
                'type' => 'LBody',
                'attributeObject' => 'inline',
                'owner' => 'List',
                'revision' => null,
                'placement' => null,
                'writingMode' => null,
                'textAlign' => null,
                'blockAlign' => null,
                'inlineAlign' => null,
                'listNumbering' => 'LowerRoman',
                'bbox' => null,
                'rowSpan' => null,
                'colSpan' => null,
                'scope' => null,
                'headers' => [],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureAttributes']);
        $t->contains('pdf-byte-structure-attributes:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-attribute-owner:Layout:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-attribute-owner:List:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-attribute-owner:Table:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-attribute-bbox:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-attribute-table-cells:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureAttributes']);
    },

    'fake runner extracts bounded pdf structure user properties from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-user-properties.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /K 0 /A [12 0 R << /O /UserProperties /P [<< /N (review-status) /V (ready) /F (Ready for review) /H false >> << /N /confidence /V 0.875 /H true >> << /N (approved) /V true >>] >>] >>',
            'endobj',
            '12 0 obj',
            '<< /O /UserProperties /P [13 0 R << /N (source-id) /V /legacy-packet /F (Legacy packet) >>] >>',
            'endobj',
            '13 0 obj',
            '<< /N (word-count) /V 42 /F (42 words) /H false >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-user-properties.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-user-properties.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => '12 0 R',
                'propertyName' => 'source-id',
                'value' => 'legacy-packet',
                'valueType' => 'name',
                'formatted' => 'Legacy packet',
                'hidden' => null,
            ],
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => '12 0 R',
                'propertyName' => 'word-count',
                'value' => 42,
                'valueType' => 'integer',
                'formatted' => '42 words',
                'hidden' => false,
            ],
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => 'inline',
                'propertyName' => 'approved',
                'value' => true,
                'valueType' => 'boolean',
                'formatted' => null,
                'hidden' => null,
            ],
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => 'inline',
                'propertyName' => 'confidence',
                'value' => 0.875,
                'valueType' => 'number',
                'formatted' => null,
                'hidden' => true,
            ],
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'attributeObject' => 'inline',
                'propertyName' => 'review-status',
                'value' => 'ready',
                'valueType' => 'string',
                'formatted' => 'Ready for review',
                'hidden' => false,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureUserProperties']);
        $t->same([], $result['pdfStructureAttributes']);
        $t->contains('pdf-byte-structure-user-properties:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-type:boolean:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-type:integer:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-type:name:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-type:number:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-type:string:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-structure-user-property-hidden:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureUserProperties']);
    },

    'fake runner extracts bounded pdf structure class map metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-class-map.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R] /ClassMap << /ReviewFigure 12 0 R /ReviewCell [13 0 R << /O /Table /RowSpan 2 /ColSpan 3 /Scope /Column /Headers [14 0 R] >>] /ReviewList << /O /List /ListNumbering /UpperAlpha >> >> >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /C [/ReviewFigure /ReviewCell] /K 0 >>',
            'endobj',
            '12 0 obj',
            '<< /O /Layout /Placement /Block /WritingMode /LrTb /TextAlign /Center /BlockAlign /Middle /InlineAlign /Center /BBox [72 648 540 720] /R 4 >>',
            'endobj',
            '13 0 obj',
            '<< /O /Layout /Placement /Inline /TextAlign /End /BBox [72 600 540 620] /R 2 >>',
            'endobj',
            '14 0 obj',
            '<< /Type /StructElem /S /TH /P 9 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-class-map.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-class-map.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'className' => 'ReviewCell',
                'attributeObject' => '13 0 R',
                'owner' => 'Layout',
                'revision' => 2,
                'placement' => 'Inline',
                'writingMode' => null,
                'textAlign' => 'End',
                'blockAlign' => null,
                'inlineAlign' => null,
                'listNumbering' => null,
                'bbox' => [72.0, 600.0, 540.0, 620.0],
                'rowSpan' => null,
                'colSpan' => null,
                'scope' => null,
                'headers' => [],
            ],
            [
                'className' => 'ReviewCell',
                'attributeObject' => 'inline',
                'owner' => 'Table',
                'revision' => null,
                'placement' => null,
                'writingMode' => null,
                'textAlign' => null,
                'blockAlign' => null,
                'inlineAlign' => null,
                'listNumbering' => null,
                'bbox' => null,
                'rowSpan' => 2,
                'colSpan' => 3,
                'scope' => 'Column',
                'headers' => ['14 0 R'],
            ],
            [
                'className' => 'ReviewFigure',
                'attributeObject' => '12 0 R',
                'owner' => 'Layout',
                'revision' => 4,
                'placement' => 'Block',
                'writingMode' => 'LrTb',
                'textAlign' => 'Center',
                'blockAlign' => 'Middle',
                'inlineAlign' => 'Center',
                'listNumbering' => null,
                'bbox' => [72.0, 648.0, 540.0, 720.0],
                'rowSpan' => null,
                'colSpan' => null,
                'scope' => null,
                'headers' => [],
            ],
            [
                'className' => 'ReviewList',
                'attributeObject' => 'inline',
                'owner' => 'List',
                'revision' => null,
                'placement' => null,
                'writingMode' => null,
                'textAlign' => null,
                'blockAlign' => null,
                'inlineAlign' => null,
                'listNumbering' => 'UpperAlpha',
                'bbox' => null,
                'rowSpan' => null,
                'colSpan' => null,
                'scope' => null,
                'headers' => [],
            ],
        ];

        $diagnostics = implode(',', $result['diagnostics']);
        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureClassMap']);
        $t->contains('pdf-byte-structure-class-map:4', $diagnostics);
        $t->contains('pdf-byte-structure-class:ReviewCell', $diagnostics);
        $t->contains('pdf-byte-structure-class:ReviewFigure', $diagnostics);
        $t->contains('pdf-byte-structure-class:ReviewList', $diagnostics);
        $t->contains('pdf-byte-structure-class-map-owner:Layout:2', $diagnostics);
        $t->contains('pdf-byte-structure-class-map-owner:List:1', $diagnostics);
        $t->contains('pdf-byte-structure-class-map-owner:Table:1', $diagnostics);
        $t->contains('pdf-byte-structure-class-map-table-cells:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureClassMap']);
    },

    'fake runner extracts bounded pdf structure class usage metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-class-usage.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /ClassMap << /ReviewFigure 12 0 R /ReviewCell [13 0 R << /O /Table /RowSpan 2 /ColSpan 3 /Scope /Column /Headers [14 0 R] >>] /ReviewList << /O /List /ListNumbering /UpperAlpha >> >> >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /C [/ReviewFigure /ReviewCell /MissingClass] /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /LBody /P 9 0 R /C /ReviewList /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /O /Layout /Placement /Block /WritingMode /LrTb /TextAlign /Center /BlockAlign /Middle /InlineAlign /Center /BBox [72 648 540 720] /R 4 >>',
            'endobj',
            '13 0 obj',
            '<< /O /Layout /Placement /Inline /TextAlign /End /BBox [72 600 540 620] /R 2 >>',
            'endobj',
            '14 0 obj',
            '<< /Type /StructElem /S /TH /P 9 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-class-usage.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-class-usage.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '10 0 R',
                'type' => 'Figure',
                'classNames' => ['ReviewFigure', 'ReviewCell', 'MissingClass'],
                'missingClasses' => ['MissingClass'],
                'mappedAttributeCounts' => [
                    'ReviewCell' => 2,
                    'ReviewFigure' => 1,
                ],
            ],
            [
                'object' => '11 0 R',
                'type' => 'LBody',
                'classNames' => ['ReviewList'],
                'missingClasses' => [],
                'mappedAttributeCounts' => [
                    'ReviewList' => 1,
                ],
            ],
        ];

        $diagnostics = implode(',', $result['diagnostics']);
        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureClassUsage']);
        $t->contains('pdf-byte-structure-class-usage:2', $diagnostics);
        $t->contains('pdf-byte-structure-class-used:MissingClass', $diagnostics);
        $t->contains('pdf-byte-structure-class-used:ReviewCell', $diagnostics);
        $t->contains('pdf-byte-structure-class-used:ReviewFigure', $diagnostics);
        $t->contains('pdf-byte-structure-class-used:ReviewList', $diagnostics);
        $t->contains('pdf-byte-structure-class-missing:1', $diagnostics);
        $t->contains('pdf-byte-structure-class-attributes:4', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureClassUsage']);
    },

    'fake runner extracts bounded pdf structure id tree metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-id-tree.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /IDTree 12 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /H1 /P 9 0 R /Pg 3 0 R /ID (packet-title) /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /ID <FEFF006600690067007500720065002D0031> /Alt (Figure one) /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /Kids [13 0 R] /Limits [(figure-1) (packet-title)] >>',
            'endobj',
            '13 0 obj',
            '<< /Names [(packet-title) 10 0 R <FEFF006600690067007500720065002D0031> 11 0 R (missing) 99 0 R] /Limits [(figure-1) (packet-title)] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-id-tree.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-id-tree.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                'nodeObject' => '13 0 R',
                'id' => 'figure-1',
                'valueKind' => 'reference',
                'valueObject' => '11 0 R',
                'structureReferences' => ['11 0 R'],
                'missingReferences' => [],
                'limits' => ['figure-1', 'packet-title'],
            ],
            [
                'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                'nodeObject' => '13 0 R',
                'id' => 'missing',
                'valueKind' => 'reference',
                'valueObject' => '99 0 R',
                'structureReferences' => ['99 0 R'],
                'missingReferences' => ['99 0 R'],
                'limits' => ['figure-1', 'packet-title'],
            ],
            [
                'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                'nodeObject' => '13 0 R',
                'id' => 'packet-title',
                'valueKind' => 'reference',
                'valueObject' => '10 0 R',
                'structureReferences' => ['10 0 R'],
                'missingReferences' => [],
                'limits' => ['figure-1', 'packet-title'],
            ],
        ];
        $expectedPolicy = [
            'source' => 'structure-id-tree',
            'reviewStatus' => 'review',
            'idTreeEntryCount' => 3,
            'structureElementCount' => 2,
            'structureElementIdCount' => 2,
            'referencedStructureObjects' => ['10 0 R', '11 0 R', '99 0 R'],
            'missingStructureReferences' => ['99 0 R'],
            'nonStructureReferences' => [],
            'duplicateIds' => [],
            'outOfLimitIds' => [],
            'structureElementIds' => ['figure-1', 'packet-title'],
            'missingStructureElementIds' => [],
            'issues' => ['missing-structure-reference'],
            'entries' => [
                [
                    'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                    'nodeObject' => '13 0 R',
                    'id' => 'figure-1',
                    'valueKind' => 'reference',
                    'structureReferences' => ['11 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'ok',
                    'issues' => [],
                ],
                [
                    'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                    'nodeObject' => '13 0 R',
                    'id' => 'missing',
                    'valueKind' => 'reference',
                    'structureReferences' => ['99 0 R'],
                    'missingReferences' => ['99 0 R'],
                    'reviewStatus' => 'review',
                    'issues' => ['missing-structure-reference'],
                ],
                [
                    'source' => 'structTreeRoot.IDTree.Kids.13 0 R',
                    'nodeObject' => '13 0 R',
                    'id' => 'packet-title',
                    'valueKind' => 'reference',
                    'structureReferences' => ['10 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'ok',
                    'issues' => [],
                ],
            ],
        ];

        $diagnostics = implode(',', $result['diagnostics']);
        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureIdTree']);
        $t->same($expectedPolicy, $result['pdfStructureIdTreePolicy']);
        $t->contains('pdf-byte-structure-id-tree:3', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-ids:3', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-kids:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-references:3', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-missing:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy:review', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-entries:3', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-structure-ids:2', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-missing-references:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issues:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:missing-structure-reference:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureIdTree']);
        $t->same($expectedPolicy, $sequence['finalPdfStructureIdTreePolicy']);
    },

    'fake runner reviews bounded pdf structure id tree policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/struct-id-tree-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructTreeRoot /K [10 0 R 11 0 R] /IDTree 12 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /StructElem /S /H1 /P 9 0 R /Pg 3 0 R /ID (intro) /K 0 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /StructElem /S /Figure /P 9 0 R /Pg 3 0 R /ID (figure) /Alt (Figure one) /K 1 >>',
            'endobj',
            '12 0 obj',
            '<< /Names [(intro) 10 0 R (intro) 11 0 R (missing) 99 0 R (z-out) 14 0 R] /Limits [(figure) (missing)] >>',
            'endobj',
            '14 0 obj',
            '<< /Type /Annot /Subtype /Link >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/struct-id-tree-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/struct-id-tree-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'source' => 'structure-id-tree',
            'reviewStatus' => 'review',
            'idTreeEntryCount' => 4,
            'structureElementCount' => 2,
            'structureElementIdCount' => 2,
            'referencedStructureObjects' => ['10 0 R', '11 0 R', '14 0 R', '99 0 R'],
            'missingStructureReferences' => ['99 0 R'],
            'nonStructureReferences' => ['14 0 R'],
            'duplicateIds' => ['intro'],
            'outOfLimitIds' => ['z-out'],
            'structureElementIds' => ['figure', 'intro'],
            'missingStructureElementIds' => ['figure'],
            'issues' => [
                'duplicate-id',
                'id-outside-limits',
                'missing-structure-reference',
                'non-structure-reference',
                'structure-element-id-missing-id-tree',
            ],
            'entries' => [
                [
                    'source' => 'structTreeRoot.IDTree',
                    'nodeObject' => '12 0 R',
                    'id' => 'intro',
                    'valueKind' => 'reference',
                    'structureReferences' => ['10 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['duplicate-id'],
                ],
                [
                    'source' => 'structTreeRoot.IDTree',
                    'nodeObject' => '12 0 R',
                    'id' => 'intro',
                    'valueKind' => 'reference',
                    'structureReferences' => ['11 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['duplicate-id'],
                ],
                [
                    'source' => 'structTreeRoot.IDTree',
                    'nodeObject' => '12 0 R',
                    'id' => 'missing',
                    'valueKind' => 'reference',
                    'structureReferences' => ['99 0 R'],
                    'missingReferences' => ['99 0 R'],
                    'reviewStatus' => 'review',
                    'issues' => ['missing-structure-reference'],
                ],
                [
                    'source' => 'structTreeRoot.IDTree',
                    'nodeObject' => '12 0 R',
                    'id' => 'z-out',
                    'valueKind' => 'reference',
                    'structureReferences' => ['14 0 R'],
                    'missingReferences' => [],
                    'reviewStatus' => 'review',
                    'issues' => ['id-outside-limits', 'non-structure-reference'],
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStructureIdTreePolicy']);
        $t->contains('pdf-byte-structure-id-tree-policy:review', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-entries:4', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-structure-ids:2', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-missing-references:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-non-structure-references:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-duplicate-ids:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-out-of-limit-ids:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-missing-structure-element-ids:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issues:5', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:duplicate-id:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:id-outside-limits:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:missing-structure-reference:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:non-structure-reference:1', $diagnostics);
        $t->contains('pdf-byte-structure-id-tree-policy-issue:structure-element-id-missing-id-tree:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStructureIdTreePolicy']);
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

    'fake runner extracts bounded pdf annotation detail metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/annotation-details.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [5 0 R 6 0 R 8 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots 7 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Link /Rect [72 640 288 672] /F 4 /C [0 0 1] /Border [0 0 0] /A << /S /URI /URI (https://example.test/review?id=7) >> >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Highlight /Rect [72 600 360 620] /QuadPoints [72 620 360 620 72 600 360 600] /Contents (Reviewer highlight) /T (Migration Desk) /NM (annot-42) /M (D:20260606051200Z) /F 516 /C [1 0.92 0.2] /Dest /review-highlight >>',
            'endobj',
            '7 0 obj',
            '[<< /Type /Annot /Subtype /Text /Rect [72 540 96 564] /Contents <FEFF0050006100670065002000320020006E006F00740065> /Name /Comment /F 32 /Dest [4 0 R /FitH 540] >>]',
            'endobj',
            '8 0 obj',
            '<< /Type /Annot /Subtype /Text /Rect [72 568 96 592] /Contents (Reviewer accepted highlight) /T (Content Reviewer) /NM (annot-reply-1) /IRT 6 0 R /RT /Group /State (Accepted) /StateModel /Review /F 4 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/annotation-details.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/annotation-details.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '5 0 R',
                'subtype' => 'Link',
                'rect' => [72.0, 640.0, 288.0, 672.0],
                'quadPoints' => null,
                'contents' => null,
                'title' => null,
                'name' => null,
                'modified' => null,
                'iconName' => null,
                'replyTo' => null,
                'replyType' => null,
                'state' => null,
                'stateModel' => null,
                'flags' => 4,
                'flagNames' => ['print'],
                'color' => [0.0, 0.0, 1.0],
                'border' => [0.0, 0.0, 0.0],
                'actionType' => 'URI',
                'actionTarget' => 'https://example.test/review?id=7',
                'destPageObject' => null,
                'destFit' => null,
                'destTarget' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '6 0 R',
                'subtype' => 'Highlight',
                'rect' => [72.0, 600.0, 360.0, 620.0],
                'quadPoints' => [72.0, 620.0, 360.0, 620.0, 72.0, 600.0, 360.0, 600.0],
                'contents' => 'Reviewer highlight',
                'title' => 'Migration Desk',
                'name' => 'annot-42',
                'modified' => 'D:20260606051200Z',
                'iconName' => null,
                'replyTo' => null,
                'replyType' => null,
                'state' => null,
                'stateModel' => null,
                'flags' => 516,
                'flagNames' => ['print', 'lockedContents'],
                'color' => [1.0, 0.92, 0.2],
                'border' => null,
                'actionType' => null,
                'actionTarget' => null,
                'destPageObject' => null,
                'destFit' => null,
                'destTarget' => 'review-highlight',
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '8 0 R',
                'subtype' => 'Text',
                'rect' => [72.0, 568.0, 96.0, 592.0],
                'quadPoints' => null,
                'contents' => 'Reviewer accepted highlight',
                'title' => 'Content Reviewer',
                'name' => 'annot-reply-1',
                'modified' => null,
                'iconName' => null,
                'replyTo' => '6 0 R',
                'replyType' => 'Group',
                'state' => 'Accepted',
                'stateModel' => 'Review',
                'flags' => 4,
                'flagNames' => ['print'],
                'color' => null,
                'border' => null,
                'actionType' => null,
                'actionTarget' => null,
                'destPageObject' => null,
                'destFit' => null,
                'destTarget' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'annotationObject' => 'inline',
                'subtype' => 'Text',
                'rect' => [72.0, 540.0, 96.0, 564.0],
                'quadPoints' => null,
                'contents' => 'Page 2 note',
                'title' => null,
                'name' => null,
                'modified' => null,
                'iconName' => 'Comment',
                'replyTo' => null,
                'replyType' => null,
                'state' => null,
                'stateModel' => null,
                'flags' => 32,
                'flagNames' => ['noView'],
                'color' => null,
                'border' => null,
                'actionType' => null,
                'actionTarget' => null,
                'destPageObject' => '4 0 R',
                'destFit' => 'FitH',
                'destTarget' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAnnotations']);
        $t->contains('pdf-byte-annotation-metadata:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-contents:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-actions:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-destinations:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-flags:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-replies:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-review-states:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfAnnotations']);
    },

    'fake runner extracts bounded pdf annotation border styles and popups from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/annotation-review.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [5 0 R 6 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /FreeText /Rect [72 640 288 700] /Contents (Style review) /BS << /S /D /W 2 /D [3 2] >> /Popup 7 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Link /Rect [72 600 288 624] /BS 8 0 R /A << /S /URI /URI (https://example.test/review) >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Annot /Subtype /Popup /Rect [100 500 260 560] /Open true /Parent 5 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /S /U /W 1.5 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/annotation-review.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/annotation-review.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '5 0 R',
                'subtype' => 'FreeText',
                'borderStyle' => 'D',
                'borderStyleLabel' => 'dashed',
                'borderWidth' => 2.0,
                'borderDashPattern' => [3.0, 2.0],
                'popupObject' => '7 0 R',
                'popupRect' => [100.0, 500.0, 260.0, 560.0],
                'popupOpen' => true,
                'popupParent' => '5 0 R',
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '6 0 R',
                'subtype' => 'Link',
                'borderStyle' => 'U',
                'borderStyleLabel' => 'underline',
                'borderWidth' => 1.5,
                'borderDashPattern' => null,
                'popupObject' => null,
                'popupRect' => null,
                'popupOpen' => null,
                'popupParent' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAnnotationReviewMetadata']);
        $t->contains('pdf-byte-annotation-review-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-border-styles:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-popup-links:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-popup-open:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfAnnotationReviewMetadata']);
    },

    'fake runner extracts bounded pdf annotation appearance streams from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/annotation-appearances.pdf']);
        $normalBytes = "q BT /Helv 10 Tf (Migration Desk) Tj ET Q\n";
        $checkedBytes = "q 1 0 0 1 0 0 cm /Check Do Q\n";
        $downBytes = "q 0.9 0.9 0.9 rg 0 0 120 24 re f Q\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [5 0 R 6 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /Rect [72 640 300 664] /AS /Normal /AP << /N 8 0 R /D 10 0 R >> >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Btn /T (review.approved) /Rect [72 600 96 624] /AS /Yes /AP << /N << /Off 9 0 R /Yes 11 0 R >> /R << /Yes 12 0 R >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 228 24] /Matrix [1 0 0 1 0 0] /Resources << /Font << /Helv 13 0 R >> >> /Length ' . strlen($normalBytes) . ' >>',
            'stream',
            $normalBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 228 24] /Filter /FlateDecode /Length ' . strlen($downBytes) . ' >>',
            'stream',
            $downBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length ' . strlen($checkedBytes) . ' >>',
            'stream',
            $checkedBytes,
            'endstream',
            'endobj',
            '12 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Group << /S /Transparency /CS /DeviceRGB /I true /K false >> /Length ' . strlen($checkedBytes) . ' >>',
            'stream',
            $checkedBytes,
            'endstream',
            'endobj',
            '13 0 obj',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/annotation-appearances.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/annotation-appearances.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '5 0 R',
                'subtype' => 'Widget',
                'fieldName' => 'reviewer.name',
                'selectedState' => 'Normal',
                'appearance' => 'N',
                'stateName' => null,
                'appearanceObject' => '8 0 R',
                'source' => 'annotation:5 0 R.AP.N',
                'bbox' => [0.0, 0.0, 228.0, 24.0],
                'matrix' => [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
                'resourcesPresent' => true,
                'groupSubtype' => null,
                'groupColorSpace' => null,
                'groupIsolated' => null,
                'groupKnockout' => null,
                'filters' => [],
                'streamBytes' => strlen($normalBytes),
                'streamSha256' => hash('sha256', $normalBytes),
                'streamSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '5 0 R',
                'subtype' => 'Widget',
                'fieldName' => 'reviewer.name',
                'selectedState' => 'Normal',
                'appearance' => 'D',
                'stateName' => null,
                'appearanceObject' => '10 0 R',
                'source' => 'annotation:5 0 R.AP.D',
                'bbox' => [0.0, 0.0, 228.0, 24.0],
                'matrix' => null,
                'resourcesPresent' => false,
                'groupSubtype' => null,
                'groupColorSpace' => null,
                'groupIsolated' => null,
                'groupKnockout' => null,
                'filters' => ['FlateDecode'],
                'streamBytes' => strlen($downBytes),
                'streamSha256' => hash('sha256', $downBytes),
                'streamSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '6 0 R',
                'subtype' => 'Widget',
                'fieldName' => 'review.approved',
                'selectedState' => 'Yes',
                'appearance' => 'N',
                'stateName' => 'Off',
                'appearanceObject' => '9 0 R',
                'source' => 'annotation:6 0 R.AP.N.Off',
                'bbox' => [0.0, 0.0, 24.0, 24.0],
                'matrix' => null,
                'resourcesPresent' => false,
                'groupSubtype' => null,
                'groupColorSpace' => null,
                'groupIsolated' => null,
                'groupKnockout' => null,
                'filters' => [],
                'streamBytes' => 0,
                'streamSha256' => hash('sha256', ''),
                'streamSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '6 0 R',
                'subtype' => 'Widget',
                'fieldName' => 'review.approved',
                'selectedState' => 'Yes',
                'appearance' => 'N',
                'stateName' => 'Yes',
                'appearanceObject' => '11 0 R',
                'source' => 'annotation:6 0 R.AP.N.Yes',
                'bbox' => [0.0, 0.0, 24.0, 24.0],
                'matrix' => null,
                'resourcesPresent' => false,
                'groupSubtype' => null,
                'groupColorSpace' => null,
                'groupIsolated' => null,
                'groupKnockout' => null,
                'filters' => [],
                'streamBytes' => strlen($checkedBytes),
                'streamSha256' => hash('sha256', $checkedBytes),
                'streamSkipped' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '6 0 R',
                'subtype' => 'Widget',
                'fieldName' => 'review.approved',
                'selectedState' => 'Yes',
                'appearance' => 'R',
                'stateName' => 'Yes',
                'appearanceObject' => '12 0 R',
                'source' => 'annotation:6 0 R.AP.R.Yes',
                'bbox' => [0.0, 0.0, 24.0, 24.0],
                'matrix' => null,
                'resourcesPresent' => false,
                'groupSubtype' => 'Transparency',
                'groupColorSpace' => 'DeviceRGB',
                'groupIsolated' => true,
                'groupKnockout' => false,
                'filters' => [],
                'streamBytes' => strlen($checkedBytes),
                'streamSha256' => hash('sha256', $checkedBytes),
                'streamSkipped' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAnnotationAppearances']);
        $t->contains('pdf-byte-annotation-appearances:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-appearance-streams:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-appearance-states:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-appearance-groups:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-annotation-appearance-filter:FlateDecode:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfAnnotationAppearances']);
    },

    'fake runner summarizes bounded pdf stream filter policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/stream-filters.pdf']);
        $contentBytes = "filtered page content bytes\n";
        $imageBytes = "jpeg image bytes\n";
        $formBytes = "filtered form xobject bytes\n";
        $appearanceBytes = "jpx appearance bytes\n";
        $embeddedBytes = "encrypted reviewer attachment bytes\n";
        $xrefBytes = "filtered xref stream bytes\n";
        $objectStreamHeader = "20 0\n";
        $objectStreamBytes = $objectStreamHeader . "<< /Title (Compressed object) >>\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AF [13 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImFiltered 8 0 R /FxFiltered 9 0 R >> >> /Contents 6 0 R /Annots [5 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /T (review.status) /AP << /N 10 0 R >> >>',
            'endobj',
            '6 0 obj',
            '<< /Filter [/ASCII85Decode /FlateDecode] /Length ' . strlen($contentBytes) . ' >>',
            'stream',
            $contentBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 100 /Height 60 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Filter /DCTDecode /Length ' . strlen($imageBytes) . ' >>',
            'stream',
            $imageBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 100 60] /Filter /FlateDecode /Length ' . strlen($formBytes) . ' >>',
            'stream',
            $formBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Filter /JPXDecode /Length ' . strlen($appearanceBytes) . ' >>',
            'stream',
            $appearanceBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /XRef /Size 12 /Root 1 0 R /Index [0 12] /W [1 2 1] /Filter /FlateDecode /Length ' . strlen($xrefBytes) . ' >>',
            'stream',
            $xrefBytes,
            'endstream',
            'endobj',
            '12 0 obj',
            '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /LZWDecode /Length ' . strlen($objectStreamBytes) . ' >>',
            'stream',
            $objectStreamBytes,
            'endstream',
            'endobj',
            '13 0 obj',
            '<< /Type /Filespec /F (review-source.bin) /AFRelationship /Source /EF << /F 14 0 R >> >>',
            'endobj',
            '14 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fzip /Filter /Crypt /Length ' . strlen($embeddedBytes) . ' >>',
            'stream',
            $embeddedBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '512',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/stream-filters.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/stream-filters.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expectedStreams = [
            [
                'surface' => 'xref-stream',
                'source' => 'xref:11 0 R',
                'object' => '11 0 R',
                'filters' => ['FlateDecode'],
                'action' => 'deferred-decode',
                'streamBytes' => strlen($xrefBytes),
                'streamSkipped' => 'filtered',
            ],
            [
                'surface' => 'object-stream',
                'source' => 'object-stream:12 0 R',
                'object' => '12 0 R',
                'filters' => ['LZWDecode'],
                'action' => 'deferred-decode',
                'streamBytes' => strlen($objectStreamBytes),
                'streamSkipped' => 'filtered',
            ],
            [
                'surface' => 'page-content',
                'source' => 'page:3 0 R.Contents',
                'object' => '6 0 R',
                'filters' => ['ASCII85Decode', 'FlateDecode'],
                'action' => 'deferred-decode',
                'streamBytes' => strlen($contentBytes),
                'streamSkipped' => 'filtered',
            ],
            [
                'surface' => 'image-xobject',
                'source' => 'page:3 0 R.XObject.ImFiltered',
                'object' => '8 0 R',
                'filters' => ['DCTDecode'],
                'action' => 'image-codec-review',
                'streamBytes' => strlen($imageBytes),
                'streamSkipped' => null,
            ],
            [
                'surface' => 'form-xobject',
                'source' => 'page:3 0 R.XObject.FxFiltered',
                'object' => '9 0 R',
                'filters' => ['FlateDecode'],
                'action' => 'deferred-decode',
                'streamBytes' => strlen($formBytes),
                'streamSkipped' => null,
            ],
            [
                'surface' => 'annotation-appearance',
                'source' => 'annotation:5 0 R.AP.N',
                'object' => '10 0 R',
                'filters' => ['JPXDecode'],
                'action' => 'image-codec-review',
                'streamBytes' => strlen($appearanceBytes),
                'streamSkipped' => null,
            ],
            [
                'surface' => 'embedded-file',
                'source' => 'embedded-file:catalog.AF:review-source.bin',
                'object' => '14 0 R',
                'filters' => ['Crypt'],
                'action' => 'requires-decryption',
                'streamBytes' => strlen($embeddedBytes),
                'streamSkipped' => 'filtered',
            ],
        ];
        $expected = [
            'streamCount' => 7,
            'filterCount' => 8,
            'filters' => [
                'ASCII85Decode' => 1,
                'Crypt' => 1,
                'DCTDecode' => 1,
                'FlateDecode' => 3,
                'JPXDecode' => 1,
                'LZWDecode' => 1,
            ],
            'surfaces' => [
                'annotation-appearance' => 1,
                'embedded-file' => 1,
                'form-xobject' => 1,
                'image-xobject' => 1,
                'object-stream' => 1,
                'page-content' => 1,
                'xref-stream' => 1,
            ],
            'actions' => [
                'deferred-decode' => 4,
                'image-codec-review' => 2,
                'requires-decryption' => 1,
            ],
            'streams' => $expectedStreams,
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStreamFilterPolicy'] ?? null);
        $t->contains('pdf-byte-stream-filter-policy:7', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter:Crypt:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter:FlateDecode:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter-action:deferred-decode:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter-action:image-codec-review:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter-action:requires-decryption:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter-surface:embedded-file:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter-surface:page-content:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStreamFilterPolicy'] ?? null);
    },

    'fake runner normalizes abbreviated pdf stream filter names from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/abbreviated-filters.pdf']);
        $contentBytes = "abbreviated filtered page bytes\n";
        $dctBytes = "jpeg reviewer image bytes\n";
        $ccittBytes = "ccitt reviewer mask bytes\n";
        $objectStreamHeader = "20 0\n";
        $objectStreamBytes = $objectStreamHeader . "<< /Title (Abbreviated filter member) >>\n";
        $xrefBytes = "abbreviated xref stream bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImDct 8 0 R /ImCcf 9 0 R >> >> /Contents 6 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Filter [/AHx /A85 /Fl /RL] /DP [null null << /Predictor 12 /Columns 8 >> null] /Length ' . strlen($contentBytes) . ' >>',
            'stream',
            $contentBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 16 /Height 16 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Filter /DCT /Length ' . strlen($dctBytes) . ' >>',
            'stream',
            $dctBytes,
            'endstream',
            'endobj',
            '9 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 16 /Height 16 /BitsPerComponent 1 /ColorSpace /DeviceGray /Filter /CCF /Length ' . strlen($ccittBytes) . ' >>',
            'stream',
            $ccittBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /LZW /Length ' . strlen($objectStreamBytes) . ' >>',
            'stream',
            $objectStreamBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /XRef /Size 12 /Root 1 0 R /Index [0 12] /W [1 2 1] /Filter /Fl /DecodeParms << /Predictor 12 /Columns 5 >> /Length ' . strlen($xrefBytes) . ' >>',
            'stream',
            $xrefBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '384',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/abbreviated-filters.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/abbreviated-filters.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same(['FlateDecode' => 1], $result['pdfXrefStreamFilters']);
        $t->same(['LZWDecode' => 1], $result['pdfObjectStreamFilters']);
        $t->same(['CCITTFaxDecode' => 1, 'DCTDecode' => 1], $result['pdfImageFilters']);
        $t->same(['ASCII85Decode', 'ASCIIHexDecode', 'FlateDecode', 'RunLengthDecode'], $result['pdfPageContentStreams'][0]['filters']);
        $t->same([
            ['CCITTFaxDecode'],
            ['DCTDecode'],
        ], array_column($result['pdfImages'], 'filters'));
        $t->same([
            'ASCII85Decode' => 1,
            'ASCIIHexDecode' => 1,
            'CCITTFaxDecode' => 1,
            'DCTDecode' => 1,
            'FlateDecode' => 2,
            'LZWDecode' => 1,
            'RunLengthDecode' => 1,
        ], $result['pdfStreamFilterPolicy']['filters'] ?? null);
        $t->same([
            'deferred-decode' => 3,
            'image-codec-review' => 2,
        ], $result['pdfStreamFilterPolicy']['actions'] ?? null);
        $t->same([
            'FlateDecode' => 2,
        ], $result['pdfStreamDecodeParameters']['filters'] ?? null);
        $t->same([
            'png-up' => 2,
        ], $result['pdfStreamDecodeParameters']['predictors'] ?? null);
        $t->contains('pdf-byte-stream-filter:ASCII85Decode:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-filter:CCITTFaxDecode:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-xref-stream-filter:FlateDecode:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-object-stream-filter:LZWDecode:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($result['pdfStreamFilterPolicy'], $sequence['finalPdfStreamFilterPolicy'] ?? null);
        $t->same($result['pdfStreamDecodeParameters'], $sequence['finalPdfStreamDecodeParameters'] ?? null);
    },

    'fake runner extracts bounded pdf stream decode parameter metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/decode-parms.pdf']);
        $xrefBytes = "xref bytes with row predictors\n";
        $contentBytes = "compressed page content bytes\n";
        $imageBytes = "predictor image bytes\n";
        $appearanceBytes = "ccitt annotation appearance bytes\n";
        $embeddedBytes = "encrypted embedded source bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AF [14 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImPredictor 8 0 R >> >> /Contents 6 0 R /Annots [5 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /Rect [72 72 160 96] /AP << /N 10 0 R >> >>',
            'endobj',
            '6 0 obj',
            '<< /Length ' . strlen($contentBytes) . ' /Filter /FlateDecode /DP << /Predictor 12 /Columns 24 /Colors 1 /BitsPerComponent 8 >> >>',
            'stream',
            $contentBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 320 /Height 180 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns 320 >> /Length ' . strlen($imageBytes) . ' >>',
            'stream',
            $imageBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 88 24] /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 1728 /Rows 22 /BlackIs1 true /EncodedByteAlign true /EndOfLine false /EndOfBlock true /DamagedRowsBeforeError 2 >> /Length ' . strlen($appearanceBytes) . ' >>',
            'stream',
            $appearanceBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Type /XRef /Size 15 /Root 1 0 R /Index [0 15] /W [1 2 1] /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 5 >> /Length ' . strlen($xrefBytes) . ' >>',
            'stream',
            $xrefBytes,
            'endstream',
            'endobj',
            '14 0 obj',
            '<< /Type /Filespec /F (encrypted-source.bin) /AFRelationship /Source /EF << /F 15 0 R >> >>',
            'endobj',
            '15 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter /Crypt /DecodeParms << /Type /CryptFilterDecodeParms /Name /Identity >> /Length ' . strlen($embeddedBytes) . ' >>',
            'stream',
            $embeddedBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '256',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/decode-parms.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/decode-parms.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'streamCount' => 5,
            'parameterSetCount' => 5,
            'filters' => [
                'CCITTFaxDecode' => 1,
                'Crypt' => 1,
                'FlateDecode' => 3,
            ],
            'surfaces' => [
                'annotation-appearance' => 1,
                'embedded-file' => 1,
                'image-xobject' => 1,
                'page-content' => 1,
                'xref-stream' => 1,
            ],
            'predictors' => [
                'png-optimum' => 1,
                'png-up' => 2,
            ],
            'streams' => [
                [
                    'surface' => 'xref-stream',
                    'source' => 'xref:11 0 R',
                    'object' => '11 0 R',
                    'filters' => ['FlateDecode'],
                    'parameterSets' => [
                        [
                            'filter' => 'FlateDecode',
                            'parameterSource' => 'DecodeParms',
                            'predictor' => 12,
                            'predictorLabel' => 'png-up',
                            'colors' => null,
                            'bitsPerComponent' => null,
                            'columns' => 5,
                            'earlyChange' => null,
                            'k' => null,
                            'rows' => null,
                            'blackIs1' => null,
                            'encodedByteAlign' => null,
                            'endOfLine' => null,
                            'endOfBlock' => null,
                            'damagedRowsBeforeError' => null,
                            'jbig2Globals' => null,
                            'cryptName' => null,
                            'cryptType' => null,
                            'rawKeys' => ['Columns', 'Predictor'],
                        ],
                    ],
                ],
                [
                    'surface' => 'page-content',
                    'source' => 'page:3 0 R.Contents',
                    'object' => '6 0 R',
                    'filters' => ['FlateDecode'],
                    'parameterSets' => [
                        [
                            'filter' => 'FlateDecode',
                            'parameterSource' => 'DP',
                            'predictor' => 12,
                            'predictorLabel' => 'png-up',
                            'colors' => 1,
                            'bitsPerComponent' => 8,
                            'columns' => 24,
                            'earlyChange' => null,
                            'k' => null,
                            'rows' => null,
                            'blackIs1' => null,
                            'encodedByteAlign' => null,
                            'endOfLine' => null,
                            'endOfBlock' => null,
                            'damagedRowsBeforeError' => null,
                            'jbig2Globals' => null,
                            'cryptName' => null,
                            'cryptType' => null,
                            'rawKeys' => ['BitsPerComponent', 'Colors', 'Columns', 'Predictor'],
                        ],
                    ],
                ],
                [
                    'surface' => 'image-xobject',
                    'source' => 'page:3 0 R.XObject.ImPredictor',
                    'object' => '8 0 R',
                    'filters' => ['FlateDecode'],
                    'parameterSets' => [
                        [
                            'filter' => 'FlateDecode',
                            'parameterSource' => 'DecodeParms',
                            'predictor' => 15,
                            'predictorLabel' => 'png-optimum',
                            'colors' => 3,
                            'bitsPerComponent' => 8,
                            'columns' => 320,
                            'earlyChange' => null,
                            'k' => null,
                            'rows' => null,
                            'blackIs1' => null,
                            'encodedByteAlign' => null,
                            'endOfLine' => null,
                            'endOfBlock' => null,
                            'damagedRowsBeforeError' => null,
                            'jbig2Globals' => null,
                            'cryptName' => null,
                            'cryptType' => null,
                            'rawKeys' => ['BitsPerComponent', 'Colors', 'Columns', 'Predictor'],
                        ],
                    ],
                ],
                [
                    'surface' => 'annotation-appearance',
                    'source' => 'annotation:5 0 R.AP.N',
                    'object' => '10 0 R',
                    'filters' => ['CCITTFaxDecode'],
                    'parameterSets' => [
                        [
                            'filter' => 'CCITTFaxDecode',
                            'parameterSource' => 'DecodeParms',
                            'predictor' => null,
                            'predictorLabel' => null,
                            'colors' => null,
                            'bitsPerComponent' => null,
                            'columns' => 1728,
                            'earlyChange' => null,
                            'k' => -1,
                            'rows' => 22,
                            'blackIs1' => true,
                            'encodedByteAlign' => true,
                            'endOfLine' => false,
                            'endOfBlock' => true,
                            'damagedRowsBeforeError' => 2,
                            'jbig2Globals' => null,
                            'cryptName' => null,
                            'cryptType' => null,
                            'rawKeys' => ['BlackIs1', 'Columns', 'DamagedRowsBeforeError', 'EncodedByteAlign', 'EndOfBlock', 'EndOfLine', 'K', 'Rows'],
                        ],
                    ],
                ],
                [
                    'surface' => 'embedded-file',
                    'source' => 'embedded-file:catalog.AF:encrypted-source.bin',
                    'object' => '15 0 R',
                    'filters' => ['Crypt'],
                    'parameterSets' => [
                        [
                            'filter' => 'Crypt',
                            'parameterSource' => 'DecodeParms',
                            'predictor' => null,
                            'predictorLabel' => null,
                            'colors' => null,
                            'bitsPerComponent' => null,
                            'columns' => null,
                            'earlyChange' => null,
                            'k' => null,
                            'rows' => null,
                            'blackIs1' => null,
                            'encodedByteAlign' => null,
                            'endOfLine' => null,
                            'endOfBlock' => null,
                            'damagedRowsBeforeError' => null,
                            'jbig2Globals' => null,
                            'cryptName' => 'Identity',
                            'cryptType' => 'CryptFilterDecodeParms',
                            'rawKeys' => ['Name', 'Type'],
                        ],
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfStreamDecodeParameters'] ?? null);
        $t->contains('pdf-byte-stream-decode-parameters:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-parameter-sets:5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-filter:FlateDecode:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-filter:Crypt:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-surface:embedded-file:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-predictor:png-up:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-stream-decode-predictor:png-optimum:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfStreamDecodeParameters'] ?? null);
    },

    'fake runner extracts bounded pdf rich media annotation metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/rich-media.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /RichMedia /Rect [72 320 540 560] /Contents (Reviewer walkthrough video) /RichMediaContent 5 0 R /RichMediaSettings 6 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Assets << /Names [(review-video.mp4) 7 0 R (captions.vtt) 8 0 R] >> /Configurations [9 0 R] >>',
            'endobj',
            '6 0 obj',
            '<< /Activation << /Condition /PO /Presentation << /Style /Embedded /Transparent true /Toolbar false /NavigationPane false /PassContextClick true >> >> /Deactivation << /Condition /PC >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Filespec /F (review-video.mp4) >>',
            'endobj',
            '8 0 obj',
            '<< /Type /Filespec /F (captions.vtt) >>',
            'endobj',
            '9 0 obj',
            '<< /Subtype /Video /Name (Review video configuration) /Instances [10 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Subtype /Video /Asset 7 0 R /Params << /Binding /Foreground /FlashVars (autoplay=false) >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/rich-media.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/rich-media.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '4 0 R',
                'rect' => [72.0, 320.0, 540.0, 560.0],
                'contents' => 'Reviewer walkthrough video',
                'contentObject' => '5 0 R',
                'settingsObject' => '6 0 R',
                'assetNames' => ['captions.vtt', 'review-video.mp4'],
                'activationCondition' => 'PO',
                'deactivationCondition' => 'PC',
                'presentationStyle' => 'Embedded',
                'presentationTransparent' => true,
                'presentationToolbar' => false,
                'presentationNavigationPane' => false,
                'presentationPassContextClick' => true,
                'configurations' => [
                    [
                        'object' => '9 0 R',
                        'subtype' => 'Video',
                        'name' => 'Review video configuration',
                        'instanceCount' => 1,
                        'assetReferences' => ['7 0 R'],
                        'assetNames' => ['review-video.mp4'],
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same(['RichMedia' => 1], $result['pdfAnnotationTypes']);
        $t->same($expected, $result['pdfRichMediaAnnotations'] ?? null);
        $t->same(['PO' => 1], $result['pdfRichMediaActivationModes'] ?? null);
        $t->contains('pdf-byte-rich-media-annotations:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-rich-media-assets:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-rich-media-configurations:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-rich-media-activation:PO:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-rich-media-deactivation:PC:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfRichMediaAnnotations'] ?? null);
        $t->same(['PO' => 1], $sequence['finalPdfRichMediaActivationModes'] ?? null);
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
                'collectionItems' => [],
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
                'collectionItems' => [],
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

    'fake runner preserves bounded pdf page and structure associated file sources from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/associated-files.pdf']);
        $pageAttachmentBytes = "page reviewer notes\n";
        $structureAttachmentBytes = '{"source":"chart"}';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AF [6 0 R] >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Filespec /F (page-note.txt) /Desc (Page review note) /AFRelationship /Supplement /EF << /F 7 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size ' . strlen($pageAttachmentBytes) . ' >> /Length ' . strlen($pageAttachmentBytes) . ' >>',
            'stream',
            $pageAttachmentBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /StructTreeRoot /K [9 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Type /StructElem /S /Figure /P 8 0 R /Pg 3 0 R /AF [10 0 R] /Alt (Review chart) >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Filespec /F (figure-source.json) /Desc (Figure source data) /AFRelationship /Source /EF << /F 11 0 R >> >>',
            'endobj',
            '11 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($structureAttachmentBytes) . ' /ModDate (D:20260605211500Z) >> /Length ' . strlen($structureAttachmentBytes) . ' >>',
            'stream',
            $structureAttachmentBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/associated-files.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/associated-files.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'name' => 'figure-source.json',
                'unicodeName' => null,
                'description' => 'Figure source data',
                'afRelationship' => 'Source',
                'filespec' => '10 0 R',
                'embeddedFile' => '11 0 R',
                'subtype' => 'application/json',
                'size' => strlen($structureAttachmentBytes),
                'modDate' => 'D:20260605211500Z',
                'checksum' => null,
                'streamBytes' => strlen($structureAttachmentBytes),
                'streamSha256' => hash('sha256', $structureAttachmentBytes),
                'streamSkipped' => null,
                'collectionItems' => [],
                'source' => 'structure:9 0 R.AF',
            ],
            [
                'name' => 'page-note.txt',
                'unicodeName' => null,
                'description' => 'Page review note',
                'afRelationship' => 'Supplement',
                'filespec' => '6 0 R',
                'embeddedFile' => '7 0 R',
                'subtype' => 'text/plain',
                'size' => strlen($pageAttachmentBytes),
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => strlen($pageAttachmentBytes),
                'streamSha256' => hash('sha256', $pageAttachmentBytes),
                'streamSkipped' => null,
                'collectionItems' => [],
                'source' => 'page:3 0 R.AF',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same(['figure-source.json', 'page-note.txt'], $result['pdfEmbeddedFileNames']);
        $t->same($expected, $result['pdfEmbeddedFiles']);
        $t->contains('pdf-byte-embedded-files:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-metadata:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-streams:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfEmbeddedFiles']);
    },

    'fake runner summarizes pdfa associated file review policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/pdfa-associated-files.pdf']);
        $xmpPart2 = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">',
            '<pdfaid:part>2</pdfaid:part>',
            '<pdfaid:conformance>B</pdfaid:conformance>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $xmpPart3 = str_replace('<pdfaid:part>2</pdfaid:part>', '<pdfaid:part>3</pdfaid:part>', $xmpPart2);
        $missingRelationshipBytes = "missing relationship attachment\n";
        $chartSourceBytes = '{"chart":true}';
        $looseAttachmentBytes = "loose name-tree attachment\n";
        $pdfa2Bytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /Names << /EmbeddedFiles << /Names [(loose.txt) 10 0 R] >> >> /AF [6 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AF [8 0 R] >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmpPart2) . ' >>',
            'stream',
            $xmpPart2,
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /Type /Filespec /F (missing-relation.txt) /Desc (Missing relationship) /EF << /F 7 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size ' . strlen($missingRelationshipBytes) . ' >> /Length ' . strlen($missingRelationshipBytes) . ' >>',
            'stream',
            $missingRelationshipBytes,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Type /Filespec /F (chart-source.json) /Desc (Chart source) /AFRelationship /Source /EF << /F 9 0 R >> >>',
            'endobj',
            '9 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($chartSourceBytes) . ' >> /Length ' . strlen($chartSourceBytes) . ' >>',
            'stream',
            $chartSourceBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /Filespec /F (loose.txt) /Desc (Name tree attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>',
            'endobj',
            '11 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size ' . strlen($looseAttachmentBytes) . ' >> /Length ' . strlen($looseAttachmentBytes) . ' >>',
            'stream',
            $looseAttachmentBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);
        $pdfa3Bytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /AF [6 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmpPart3) . ' >>',
            'stream',
            $xmpPart3,
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /Type /Filespec /F (review-data.csv) /Desc (Review data) /AFRelationship /Data /EF << /F 7 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size 12 >> /Length 12 >>',
            'stream',
            "id,status\n1",
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/pdfa-associated-files.pdf' => $pdfa2Bytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/pdfa-associated-files.pdf' => $pdfa2Bytes,
                ],
            ],
        ]);
        $ok = $handoff->fakeRun($plan, [
            'files' => [
                'packets/pdfa-associated-files.pdf' => $pdfa3Bytes,
            ],
        ]);
        $expected = [
            'reviewStatus' => 'review',
            'pdfaClaimed' => true,
            'pdfaPart' => '2',
            'pdfaConformance' => 'B',
            'embeddedFileCount' => 3,
            'associatedFileCount' => 2,
            'unassociatedFileCount' => 1,
            'missingRelationshipCount' => 1,
            'relationshipCounts' => ['Source' => 1],
            'issues' => [
                'associated-file-missing-afrelationship',
                'pdfa-associated-files-require-pdfa-3',
                'pdfa-embedded-file-not-associated',
            ],
            'files' => [
                [
                    'name' => 'chart-source.json',
                    'source' => 'page:3 0 R.AF',
                    'filespec' => '8 0 R',
                    'embeddedFile' => '9 0 R',
                    'afRelationship' => 'Source',
                    'associated' => true,
                    'issues' => ['pdfa-associated-files-require-pdfa-3'],
                ],
                [
                    'name' => 'loose.txt',
                    'source' => 'catalog.Names.EmbeddedFiles',
                    'filespec' => '10 0 R',
                    'embeddedFile' => '11 0 R',
                    'afRelationship' => 'Data',
                    'associated' => false,
                    'issues' => ['pdfa-embedded-file-not-associated'],
                ],
                [
                    'name' => 'missing-relation.txt',
                    'source' => 'catalog.AF',
                    'filespec' => '6 0 R',
                    'embeddedFile' => '7 0 R',
                    'afRelationship' => null,
                    'associated' => true,
                    'issues' => ['associated-file-missing-afrelationship', 'pdfa-associated-files-require-pdfa-3'],
                ],
            ],
        ];
        $expectedOk = [
            'reviewStatus' => 'ok',
            'pdfaClaimed' => true,
            'pdfaPart' => '3',
            'pdfaConformance' => 'B',
            'embeddedFileCount' => 1,
            'associatedFileCount' => 1,
            'unassociatedFileCount' => 0,
            'missingRelationshipCount' => 0,
            'relationshipCounts' => ['Data' => 1],
            'issues' => [],
            'files' => [
                [
                    'name' => 'review-data.csv',
                    'source' => 'catalog.AF',
                    'filespec' => '6 0 R',
                    'embeddedFile' => '7 0 R',
                    'afRelationship' => 'Data',
                    'associated' => true,
                    'issues' => [],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAssociatedFilePolicy']);
        $t->contains('pdf-byte-associated-file-policy:review', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-files:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-unassociated-embedded-files:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-file-missing-relationships:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-file-relationship:Source:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-file-policy-issue:pdfa-associated-files-require-pdfa-3:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-file-policy-issue:associated-file-missing-afrelationship:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-associated-file-policy-issue:pdfa-embedded-file-not-associated:1', implode(',', $result['diagnostics']));
        $t->same($expected, $sequence['finalPdfAssociatedFilePolicy']);
        $t->same($expectedOk, $ok['pdfAssociatedFilePolicy']);
        $t->contains('pdf-byte-associated-file-policy:ok', implode(',', $ok['diagnostics']));
    },

    'fake runner extracts bounded pdf marked content property associated files from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/marked-content.pdf']);
        $figureSourceBytes = '{"series":[1,2,3]}';
        $inlineNoteBytes = "inline reviewer note\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /Properties << /MCFigure 6 0 R /MCInline << /MCID 8 /Lang (fr-FR) /Alt (Inline note) /AF [14 0 R] >> >> >> >>',
            'endobj',
            '6 0 obj',
            '<< /MCID 7 /Lang (en-US) /Alt (Review chart source data) /ActualText (Chart source) /E (Expanded chart source) /AF [10 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Filespec /F (figure-source.json) /Desc (Figure source data) /AFRelationship /Source /EF << /F 11 0 R >> >>',
            'endobj',
            '11 0 obj',
            '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($figureSourceBytes) . ' >> /Length ' . strlen($figureSourceBytes) . ' >>',
            'stream',
            $figureSourceBytes,
            'endstream',
            'endobj',
            '14 0 obj',
            '<< /Type /Filespec /F (inline-note.txt) /Desc (Inline marked-content note) /AFRelationship /Supplement /EF << /F 15 0 R >> >>',
            'endobj',
            '15 0 obj',
            '<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size ' . strlen($inlineNoteBytes) . ' >> /Length ' . strlen($inlineNoteBytes) . ' >>',
            'stream',
            $inlineNoteBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/marked-content.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/marked-content.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedProperties = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'propertyName' => 'MCFigure',
                'propertyObject' => '6 0 R',
                'inherited' => false,
                'mcid' => 7,
                'language' => 'en-US',
                'alt' => 'Review chart source data',
                'actualText' => 'Chart source',
                'expanded' => 'Expanded chart source',
                'associatedFiles' => ['10 0 R'],
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'propertyName' => 'MCInline',
                'propertyObject' => 'inline',
                'inherited' => false,
                'mcid' => 8,
                'language' => 'fr-FR',
                'alt' => 'Inline note',
                'actualText' => null,
                'expanded' => null,
                'associatedFiles' => ['14 0 R'],
            ],
        ];
        $expectedFiles = [
            [
                'name' => 'figure-source.json',
                'unicodeName' => null,
                'description' => 'Figure source data',
                'afRelationship' => 'Source',
                'filespec' => '10 0 R',
                'embeddedFile' => '11 0 R',
                'subtype' => 'application/json',
                'size' => strlen($figureSourceBytes),
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => strlen($figureSourceBytes),
                'streamSha256' => hash('sha256', $figureSourceBytes),
                'streamSkipped' => null,
                'collectionItems' => [],
                'source' => 'marked-content:3 0 R.Properties.MCFigure.AF',
            ],
            [
                'name' => 'inline-note.txt',
                'unicodeName' => null,
                'description' => 'Inline marked-content note',
                'afRelationship' => 'Supplement',
                'filespec' => '14 0 R',
                'embeddedFile' => '15 0 R',
                'subtype' => 'text/plain',
                'size' => strlen($inlineNoteBytes),
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => strlen($inlineNoteBytes),
                'streamSha256' => hash('sha256', $inlineNoteBytes),
                'streamSkipped' => null,
                'collectionItems' => [],
                'source' => 'marked-content:3 0 R.Properties.MCInline.AF',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expectedProperties, $result['pdfMarkedContentProperties'] ?? null);
        $t->same(['figure-source.json', 'inline-note.txt'], $result['pdfEmbeddedFileNames']);
        $t->same($expectedFiles, $result['pdfEmbeddedFiles']);
        $t->contains('pdf-byte-marked-content-properties:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-marked-content-associated-files:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedProperties, $sequence['finalPdfMarkedContentProperties'] ?? null);
        $t->same($expectedFiles, $sequence['finalPdfEmbeddedFiles']);
    },

    'fake runner extracts bounded pdf artifact marked content metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/artifacts.pdf']);
        $contentBytes = implode("\n", [
            'q',
            '/Artifact << /Type /Pagination /Subtype /Header /BBox [0 750 612 792] /Attached [/Top] /MCID 19 >> BDC',
            'BT /F1 10 Tf (Draft header) Tj ET',
            'EMC',
            '/Artifact BMC',
            'q 1 0 0 1 0 0 cm Q',
            'EMC',
            '/Figure << /MCID 20 >> BDC',
            'EMC',
            'Q',
            '',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Length ' . strlen($contentBytes) . ' >>',
            'stream',
            $contentBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/artifacts.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/artifacts.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedArtifacts = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'contentObject' => '6 0 R',
                'source' => 'page:3 0 R.Contents.Artifact[0]',
                'operator' => 'BDC',
                'type' => 'Pagination',
                'subtype' => 'Header',
                'bbox' => [0.0, 750.0, 612.0, 792.0],
                'attached' => ['Top'],
                'mcid' => 19,
                'propertyName' => null,
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'contentObject' => '6 0 R',
                'source' => 'page:3 0 R.Contents.Artifact[1]',
                'operator' => 'BMC',
                'type' => null,
                'subtype' => null,
                'bbox' => null,
                'attached' => [],
                'mcid' => null,
                'propertyName' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expectedArtifacts, $result['pdfMarkedContentArtifacts'] ?? null);
        $t->same([19, 20], $result['pdfPageContentStreams'][0]['mcidValues'] ?? null);
        $t->contains('pdf-byte-marked-content-artifacts:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-marked-content-artifact-attached:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-marked-content-artifact-type:Pagination:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-marked-content-artifact-subtype:Header:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedArtifacts, $sequence['finalPdfMarkedContentArtifacts'] ?? null);
    },

    'fake runner extracts bounded pdf page content stream operator metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-content.pdf']);
        $contentOne = implode("\n", [
            'q',
            '/Span << /MCID 4 >> BDC',
            'BT /F1 12 Tf (Review) Tj ET',
            '/ImChart Do',
            'EMC',
            'Q',
            '',
        ]);
        $contentTwo = "q /FxOverlay Do Q\n";
        $filteredContent = "compressed content bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImChart 4 0 R /FxOverlay 5 0 R >> >> /Contents [6 0 R 7 0 R 8 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /XObject /Subtype /Image /Width 320 /Height 180 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '5 0 obj',
            '<< /Type /XObject /Subtype /Form /BBox [0 0 144 72] /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /Length ' . strlen($contentOne) . ' >>',
            'stream',
            $contentOne,
            'endstream',
            'endobj',
            '7 0 obj',
            '<< /Length ' . strlen($contentTwo) . ' >>',
            'stream',
            $contentTwo,
            'endstream',
            'endobj',
            '8 0 obj',
            '<< /Length ' . strlen($filteredContent) . ' /Filter /FlateDecode >>',
            'stream',
            $filteredContent,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-content.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-content.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'contentObject' => '6 0 R',
                'source' => 'page:3 0 R.Contents[0]',
                'filters' => [],
                'streamBytes' => strlen($contentOne),
                'streamSha256' => hash('sha256', $contentOne),
                'streamSkipped' => null,
                'textObjectCount' => 1,
                'imagePaintCount' => 1,
                'formPaintCount' => 0,
                'markedContentBeginCount' => 1,
                'markedContentEndCount' => 1,
                'mcidValues' => [4],
                'propertyNames' => ['Span'],
                'resourceNames' => ['ImChart'],
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'contentObject' => '7 0 R',
                'source' => 'page:3 0 R.Contents[1]',
                'filters' => [],
                'streamBytes' => strlen($contentTwo),
                'streamSha256' => hash('sha256', $contentTwo),
                'streamSkipped' => null,
                'textObjectCount' => 0,
                'imagePaintCount' => 0,
                'formPaintCount' => 1,
                'markedContentBeginCount' => 0,
                'markedContentEndCount' => 0,
                'mcidValues' => [],
                'propertyNames' => [],
                'resourceNames' => ['FxOverlay'],
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'contentObject' => '8 0 R',
                'source' => 'page:3 0 R.Contents[2]',
                'filters' => ['FlateDecode'],
                'streamBytes' => strlen($filteredContent),
                'streamSha256' => null,
                'streamSkipped' => 'filtered',
                'textObjectCount' => 0,
                'imagePaintCount' => 0,
                'formPaintCount' => 0,
                'markedContentBeginCount' => 0,
                'markedContentEndCount' => 0,
                'mcidValues' => [],
                'propertyNames' => [],
                'resourceNames' => [],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageContentStreams'] ?? null);
        $t->same(['FxOverlay' => 1, 'ImChart' => 1], $result['pdfPageContentResourceUsage'] ?? null);
        $t->contains('pdf-byte-page-content-streams:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-text-objects:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-image-paints:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-form-paints:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-marked-begins:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-stream-skipped:filtered', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-content-resource:ImChart:1', implode(',', $result['diagnostics']));
        $t->same($expected, $sequence['finalPdfPageContentStreams'] ?? null);
        $t->same(['FxOverlay' => 1, 'ImChart' => 1], $sequence['finalPdfPageContentResourceUsage'] ?? null);
    },

    'fake runner records inherited pdf page resource source metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/resource-sources.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] /Resources << /ProcSet [/PDF /Text /ImageC] /Font << /FInherited 8 0 R >> /XObject << /ImInherited 9 0 R /FxInherited 10 0 R >> /ColorSpace << /CSInherited /DeviceRGB >> /ExtGState << /GSInherited 11 0 R >> /Pattern << /PInherited 15 0 R >> /Shading << /ShInherited 16 0 R >> /Properties << /MCInherited << /MCID 12 /Alt (Inherited source) >> >> >> >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /ProcSet 17 0 R /Font << /FPage 12 0 R >> /XObject << /ImPage 13 0 R >> /Pattern << /PPage << /PatternType 1 >> >> /Shading << /ShPage << /ShadingType 2 /ColorSpace /DeviceRGB >> >> /Properties << /MCPage << /MCID 14 /ActualText (Page source) >> >> >> /Contents 7 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '7 0 obj',
            '<< /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            '15 0 obj',
            '<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 16 16] /XStep 16 /YStep 16 >>',
            'endobj',
            '16 0 obj',
            '<< /ShadingType 3 /ColorSpace /DeviceRGB >>',
            'endobj',
            '17 0 obj',
            '[/PDF /Text /ImageI]',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/resource-sources.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/resource-sources.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceSourceObject' => '2 0 R',
                'inherited' => true,
                'categories' => ['ColorSpace', 'ExtGState', 'Font', 'Pattern', 'ProcSet', 'Properties', 'Shading', 'XObject'],
                'procSetNames' => ['ImageC', 'PDF', 'Text'],
                'fontNames' => ['FInherited'],
                'xobjectNames' => ['FxInherited', 'ImInherited'],
                'colorSpaceNames' => ['CSInherited'],
                'graphicsStateNames' => ['GSInherited'],
                'patternNames' => ['PInherited'],
                'shadingNames' => ['ShInherited'],
                'propertyNames' => ['MCInherited'],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'resourceSourceObject' => '4 0 R',
                'inherited' => false,
                'categories' => ['Font', 'Pattern', 'ProcSet', 'Properties', 'Shading', 'XObject'],
                'procSetNames' => ['ImageI', 'PDF', 'Text'],
                'fontNames' => ['FPage'],
                'xobjectNames' => ['ImPage'],
                'colorSpaceNames' => [],
                'graphicsStateNames' => [],
                'patternNames' => ['PPage'],
                'shadingNames' => ['ShPage'],
                'propertyNames' => ['MCPage'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageResourceSources'] ?? null);
        $t->contains('pdf-byte-page-resource-sources:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-inherited:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Font:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Pattern:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:ProcSet:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Properties:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Shading:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:ColorSpace:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageResourceSources'] ?? null);
    },

    'fake runner records pdf procset pattern and shading resource metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/resource-classes.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /ProcSet 8 0 R /Pattern << /PStripe 9 0 R /PInline << /PatternType 1 >> >> /Shading << /ShAxial 10 0 R /ShInline << /ShadingType 3 /ColorSpace /DeviceRGB >> >> >> >>',
            'endobj',
            '8 0 obj',
            '[/PDF /Text /ImageB]',
            'endobj',
            '9 0 obj',
            '<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 >>',
            'endobj',
            '10 0 obj',
            '<< /ShadingType 2 /ColorSpace /DeviceRGB >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/resource-classes.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/resource-classes.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'resourceSourceObject' => '3 0 R',
                'inherited' => false,
                'categories' => ['Pattern', 'ProcSet', 'Shading'],
                'procSetNames' => ['ImageB', 'PDF', 'Text'],
                'fontNames' => [],
                'xobjectNames' => [],
                'colorSpaceNames' => [],
                'graphicsStateNames' => [],
                'patternNames' => ['PInline', 'PStripe'],
                'shadingNames' => ['ShAxial', 'ShInline'],
                'propertyNames' => [],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageResourceSources'] ?? null);
        $t->contains('pdf-byte-page-resource-sources:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:ProcSet:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Pattern:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-resource-category:Shading:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageResourceSources'] ?? null);
    },

    'fake runner extracts bounded pdf collection portfolio metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/portfolio.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Collection 20 0 R /Names << /EmbeddedFiles << /Names [(review-assets.zip) 22 0 R] >> >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '20 0 obj',
            '<< /Type /Collection /View /T /D (review-assets.zip) /Schema << /Title 21 0 R /Size << /Type /CollectionField /Subtype /Size /N (Attachment size) /O 2 /V false /E false >> >> /Sort << /S [/Title /Size] /A [true false] >> >>',
            'endobj',
            '21 0 obj',
            '<< /Type /CollectionField /Subtype /S /N (Review title) /O 1 /V true /E true >>',
            'endobj',
            '22 0 obj',
            '<< /Type /Filespec /F (review-assets.zip) /Desc (Portfolio source package) /AFRelationship /Data /CI << /Title (Review source package) /Size 4096 /Modified (D:20260606120000Z) /Reviewed true /Score 98.5 /Stage /Final /Missing null >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/portfolio.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/portfolio.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'type' => 'Collection',
            'view' => 'T',
            'defaultDocument' => 'review-assets.zip',
            'schemaFields' => [
                [
                    'name' => 'Title',
                    'subtype' => 'S',
                    'title' => 'Review title',
                    'order' => 1,
                    'visible' => true,
                    'editable' => true,
                ],
                [
                    'name' => 'Size',
                    'subtype' => 'Size',
                    'title' => 'Attachment size',
                    'order' => 2,
                    'visible' => false,
                    'editable' => false,
                ],
            ],
            'sort' => [
                'fields' => ['Title', 'Size'],
                'ascending' => [true, false],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfCollectionMetadata']);
        $t->contains('pdf-byte-collection', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-collection-view:T', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-collection-default:review-assets.zip', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-collection-schema-fields:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-collection-sort-fields:2', implode(',', $result['diagnostics']));
        $t->same(['review-assets.zip'], $result['pdfEmbeddedFileNames']);
        $expectedFiles = [
            [
                'name' => 'review-assets.zip',
                'unicodeName' => null,
                'description' => 'Portfolio source package',
                'afRelationship' => 'Data',
                'filespec' => '22 0 R',
                'embeddedFile' => null,
                'subtype' => null,
                'size' => null,
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => null,
                'streamSha256' => null,
                'streamSkipped' => null,
                'collectionItems' => [
                    ['name' => 'Missing', 'value' => null, 'valueType' => 'null'],
                    ['name' => 'Modified', 'value' => 'D:20260606120000Z', 'valueType' => 'string'],
                    ['name' => 'Reviewed', 'value' => true, 'valueType' => 'boolean'],
                    ['name' => 'Score', 'value' => 98.5, 'valueType' => 'number'],
                    ['name' => 'Size', 'value' => 4096, 'valueType' => 'integer'],
                    ['name' => 'Stage', 'value' => 'Final', 'valueType' => 'name'],
                    ['name' => 'Title', 'value' => 'Review source package', 'valueType' => 'string'],
                ],
                'source' => 'catalog.Names.EmbeddedFiles',
            ],
        ];
        $t->same($expectedFiles, $result['pdfEmbeddedFiles']);
        $t->contains('pdf-byte-embedded-files:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-metadata:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-collection-items:7', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfCollectionMetadata']);
        $t->same($expectedFiles, $sequence['finalPdfEmbeddedFiles']);
    },

    'fake runner extracts bounded pdf collection item metadata from embedded file specs' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/portfolio-items.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Collection 20 0 R /Names << /EmbeddedFiles << /Names [(dataset.csv) 22 0 R] >> >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '20 0 obj',
            '<< /Type /Collection /View /D /D (dataset.csv) >>',
            'endobj',
            '22 0 obj',
            '<< /Type /Filespec /F (dataset.csv) /Desc (Portfolio dataset) /AFRelationship /Data /CI 23 0 R >>',
            'endobj',
            '23 0 obj',
            '<< /Type /CollectionItem /Title (Dataset export) /Rank 3 /Included false /Tags [/source /review] /Stats << /Pages 2 /Tables 1 >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/portfolio-items.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/portfolio-items.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedFiles = [
            [
                'name' => 'dataset.csv',
                'unicodeName' => null,
                'description' => 'Portfolio dataset',
                'afRelationship' => 'Data',
                'filespec' => '22 0 R',
                'embeddedFile' => null,
                'subtype' => null,
                'size' => null,
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => null,
                'streamSha256' => null,
                'streamSkipped' => null,
                'collectionItems' => [
                    ['name' => 'Included', 'value' => false, 'valueType' => 'boolean'],
                    ['name' => 'Rank', 'value' => 3, 'valueType' => 'integer'],
                    ['name' => 'Stats', 'value' => 2, 'valueType' => 'dictionary'],
                    ['name' => 'Tags', 'value' => 2, 'valueType' => 'array'],
                    ['name' => 'Title', 'value' => 'Dataset export', 'valueType' => 'string'],
                ],
                'source' => 'catalog.Names.EmbeddedFiles',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same(['dataset.csv'], $result['pdfEmbeddedFileNames']);
        $t->same($expectedFiles, $result['pdfEmbeddedFiles']);
        $t->contains('pdf-byte-collection-view:D', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-embedded-file-collection-items:5', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedFiles, $sequence['finalPdfEmbeddedFiles']);
    },

    'fake runner extracts bounded pdf article thread bead metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/threaded.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Threads [8 0 R 12 0 R] >>',
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
            '<< /Type /Thread /F 9 0 R /I 11 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Bead /T 8 0 R /P 3 0 R /R [72 648 540 720] /N 10 0 R /V 10 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Bead /T 8 0 R /P 4 0 R /R [72 96 540 144] /N 9 0 R /V 9 0 R >>',
            'endobj',
            '11 0 obj',
            '<< /Title (Review reading order) /Author (Migration Desk) /Subject <FEFF0050004400460020007400680072006500610064> >>',
            'endobj',
            '12 0 obj',
            '<< /Type /Thread /F 13 0 R /I << /Title (Sidebar notes) >> >>',
            'endobj',
            '13 0 obj',
            '<< /Type /Bead /T 12 0 R /P 3 0 R /R [36 36 180 120] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/threaded.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/threaded.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'object' => '8 0 R',
                'infoTitle' => 'Review reading order',
                'infoAuthor' => 'Migration Desk',
                'infoSubject' => 'PDF thread',
                'firstBead' => '9 0 R',
                'beadCount' => 2,
                'beads' => [
                    [
                        'object' => '9 0 R',
                        'pageObject' => '3 0 R',
                        'rect' => [72.0, 648.0, 540.0, 720.0],
                        'next' => '10 0 R',
                        'prev' => '10 0 R',
                    ],
                    [
                        'object' => '10 0 R',
                        'pageObject' => '4 0 R',
                        'rect' => [72.0, 96.0, 540.0, 144.0],
                        'next' => '9 0 R',
                        'prev' => '9 0 R',
                    ],
                ],
            ],
            [
                'object' => '12 0 R',
                'infoTitle' => 'Sidebar notes',
                'infoAuthor' => null,
                'infoSubject' => null,
                'firstBead' => '13 0 R',
                'beadCount' => 1,
                'beads' => [
                    [
                        'object' => '13 0 R',
                        'pageObject' => '3 0 R',
                        'rect' => [36.0, 36.0, 180.0, 120.0],
                        'next' => null,
                        'prev' => null,
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfThreads']);
        $t->contains('pdf-byte-threads:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-thread-beads:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-thread-info-titles:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfThreads']);
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

    'fake runner extracts bounded pdf acroform field actions from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/form-actions.pdf']);
        $fieldScript = 'app.alert("reviewer name changed")';
        $queueScript = 'app.alert("queue focus")';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 6 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /TU (Reviewer name) /A << /S /SubmitForm /F (https://example.test/review/form-submit) >> /AA << /K << /S /JavaScript /JS <' . strtoupper(bin2hex($fieldScript)) . '> >> /V << /S /ResetForm /T (reviewer.name) >> >> >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Widget /T (queue) /AA << /Fo 9 0 R >> >>',
            'endobj',
            '7 0 obj',
            '<< /FT /Ch /T (routing) /Kids [6 0 R] >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 7 0 R] /NeedAppearances true >>',
            'endobj',
            '9 0 obj',
            '<< /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $queueScript) . ') >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/form-actions.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/form-actions.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'fieldName' => 'reviewer.name',
                'fieldObject' => '4 0 R',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'trigger' => 'A',
                'source' => 'field:4 0 R.A',
                'actionType' => 'SubmitForm',
                'actionTarget' => 'https://example.test/review/form-submit',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'fieldName' => 'reviewer.name',
                'fieldObject' => '4 0 R',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'trigger' => 'AA.K',
                'source' => 'field:4 0 R.AA.K',
                'actionType' => 'JavaScript',
                'actionTarget' => null,
                'scriptBytes' => strlen($fieldScript),
                'scriptSha256' => hash('sha256', $fieldScript),
            ],
            [
                'fieldName' => 'reviewer.name',
                'fieldObject' => '4 0 R',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'trigger' => 'AA.V',
                'source' => 'field:4 0 R.AA.V',
                'actionType' => 'ResetForm',
                'actionTarget' => null,
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'fieldName' => 'routing.queue',
                'fieldObject' => '6 0 R',
                'fieldType' => 'Ch',
                'fieldTypeLabel' => 'choice',
                'trigger' => 'AA.Fo',
                'source' => 'field:6 0 R.AA.Fo',
                'actionType' => 'JavaScript',
                'actionTarget' => null,
                'scriptBytes' => strlen($queueScript),
                'scriptSha256' => hash('sha256', $queueScript),
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfFormFieldActions']);
        $t->same([
            'JavaScript' => 2,
            'ResetForm' => 1,
            'SubmitForm' => 1,
        ], $result['pdfFormFieldActionTypes']);
        $t->contains('pdf-byte-form-field-actions:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-trigger:A:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-trigger:AA.K:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-type:JavaScript:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfFormFieldActions']);
        $t->same($result['pdfFormFieldActionTypes'], $sequence['finalPdfFormFieldActionTypes']);
    },

    'fake runner extracts bounded pdf acroform action target lists from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/form-action-targets.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R 7 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /A << /S /SubmitForm /F (https://example.test/review/form-submit) /Fields [(reviewer.name) 5 0 R] /Flags 34 >> >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Btn /T (approved) /V /Yes >>',
            'endobj',
            '6 0 obj',
            '<< /FT /Ch /T (routing) /Kids [7 0 R] >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Annot /Subtype /Widget /T (queue) /AA << /Fo << /S /ResetForm /Fields [4 0 R (approved)] /Flags 1 >> >> >>',
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
                'packets/form-action-targets.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/form-action-targets.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'fieldName' => 'reviewer.name',
                'fieldObject' => '4 0 R',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'trigger' => 'A',
                'source' => 'field:4 0 R.A',
                'actionType' => 'SubmitForm',
                'actionTarget' => 'https://example.test/review/form-submit',
                'flags' => 34,
                'flagNames' => ['includeNoValueFields', 'xfdf'],
                'fieldNames' => ['reviewer.name', 'approved'],
                'fieldSelection' => 'include-listed',
            ],
            [
                'fieldName' => 'routing.queue',
                'fieldObject' => '7 0 R',
                'fieldType' => 'Ch',
                'fieldTypeLabel' => 'choice',
                'trigger' => 'AA.Fo',
                'source' => 'field:7 0 R.AA.Fo',
                'actionType' => 'ResetForm',
                'actionTarget' => null,
                'flags' => 1,
                'flagNames' => ['excludeListedFields'],
                'fieldNames' => ['reviewer.name', 'approved'],
                'fieldSelection' => 'exclude-listed',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfFormFieldActionTargets']);
        $t->contains('pdf-byte-form-field-action-targets:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-fields:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-flags:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-type:ResetForm:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-type:SubmitForm:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-selection:exclude-listed:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-target-selection:include-listed:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfFormFieldActionTargets']);
    },

    'fake runner summarizes bounded pdf acroform submit export policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/form-submit-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R 7 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /A << /S /SubmitForm /F (https://example.test/review/form-submit) /Flags 256 >> >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Btn /T (approved) /AA << /Fo << /S /ImportData /F (imports/review.fdf) >> >> /V /Yes >>',
            'endobj',
            '6 0 obj',
            '<< /FT /Ch /T (routing) /Kids [7 0 R] >>',
            'endobj',
            '7 0 obj',
            '<< /Type /Annot /Subtype /Widget /T (queue) /AA << /Fo << /S /ResetForm /Fields [4 0 R (approved)] /Flags 1 >> >> >>',
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
                'packets/form-submit-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/form-submit-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'fieldName' => 'approved',
                'fieldObject' => '5 0 R',
                'fieldType' => 'Btn',
                'fieldTypeLabel' => 'button',
                'trigger' => 'AA.Fo',
                'source' => 'field:5 0 R.AA.Fo',
                'actionType' => 'ImportData',
                'actionTarget' => 'imports/review.fdf',
                'targetScheme' => null,
                'targetIsRemote' => false,
                'flags' => null,
                'flagNames' => [],
                'fieldNames' => [],
                'fieldSelection' => 'all-fields',
                'fieldCount' => 0,
                'reviewStatus' => 'review',
                'issues' => ['import-data-action'],
            ],
            [
                'fieldName' => 'reviewer.name',
                'fieldObject' => '4 0 R',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'trigger' => 'A',
                'source' => 'field:4 0 R.A',
                'actionType' => 'SubmitForm',
                'actionTarget' => 'https://example.test/review/form-submit',
                'targetScheme' => 'https',
                'targetIsRemote' => true,
                'flags' => 256,
                'flagNames' => ['submitPdf'],
                'fieldNames' => [],
                'fieldSelection' => 'all-fields',
                'fieldCount' => 0,
                'reviewStatus' => 'review',
                'issues' => ['remote-submit-target', 'submit-pdf-payload', 'submit-all-fields'],
            ],
            [
                'fieldName' => 'routing.queue',
                'fieldObject' => '7 0 R',
                'fieldType' => 'Ch',
                'fieldTypeLabel' => 'choice',
                'trigger' => 'AA.Fo',
                'source' => 'field:7 0 R.AA.Fo',
                'actionType' => 'ResetForm',
                'actionTarget' => null,
                'targetScheme' => null,
                'targetIsRemote' => false,
                'flags' => 1,
                'flagNames' => ['excludeListedFields'],
                'fieldNames' => ['reviewer.name', 'approved'],
                'fieldSelection' => 'exclude-listed',
                'fieldCount' => 2,
                'reviewStatus' => 'review',
                'issues' => ['reset-excludes-listed-fields'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfFormFieldActionPolicy']);
        $t->contains('pdf-byte-form-field-action-policy:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-status:review:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-remote-targets:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-fields:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-type:ImportData:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-type:SubmitForm:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-type:ResetForm:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-issue:import-data-action:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-issue:remote-submit-target:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-issue:submit-pdf-payload:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-issue:submit-all-fields:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-form-field-action-policy-issue:reset-excludes-listed-fields:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfFormFieldActionPolicy']);
    },

    'fake runner extracts bounded pdf acroform dictionary metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/acroform-dictionary.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (reviewer.name) /V (Migration Desk) >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R] /NeedAppearances true /SigFlags 3 /DR << /Font << /Helv 10 0 R >> >> /DA (/Helv 10 Tf 0 g) /Q 2 /CO [5 0 R] /XFA [(template) 11 0 R (datasets) 12 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            'endobj',
            '11 0 obj',
            '<< /Length 18 >>',
            'stream',
            '<template />',
            'endstream',
            'endobj',
            '12 0 obj',
            '<< /Length 18 >>',
            'stream',
            '<datasets />',
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/acroform-dictionary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/acroform-dictionary.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'fieldReferences' => ['4 0 R', '5 0 R'],
            'fieldCount' => 2,
            'needAppearances' => true,
            'sigFlags' => 3,
            'sigFlagNames' => ['signaturesExist', 'appendOnly'],
            'defaultResourcesPresent' => true,
            'defaultAppearance' => '/Helv 10 Tf 0 g',
            'quadding' => 2,
            'calculationOrder' => ['5 0 R'],
            'xfaPresent' => true,
            'xfaPacketNames' => ['template', 'datasets'],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAcroFormMetadata']);
        $t->contains('pdf-byte-acroform', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-fields:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-need-appearances', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-sigflags:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-sigflag-names:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-default-resources', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-default-appearance', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-quadding:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-calculation-order:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-xfa', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-acroform-xfa-packets:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfAcroFormMetadata']);
    },

    'fake runner resolves bounded pdf acroform calculation order fields from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/acroform-calculation-order.pdf']);
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
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (review.subtotal) /TM (subtotal) /V (42) /Ff 1 >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (review.total) /TU (Review total) /TM (review_total) /V (50) /Ff 4097 >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Btn /T (review.approved) /V /Off >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R 6 0 R] /CO [5 0 R 99 0 R 4 0 R] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/acroform-calculation-order.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/acroform-calculation-order.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'order' => 1,
                'fieldObject' => '5 0 R',
                'fieldName' => 'review.total',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'alternateName' => 'Review total',
                'mappingName' => 'review_total',
                'flags' => 4097,
                'flagNames' => ['readOnly', 'multiline'],
                'missing' => false,
            ],
            [
                'order' => 2,
                'fieldObject' => '99 0 R',
                'fieldName' => null,
                'fieldType' => null,
                'fieldTypeLabel' => null,
                'alternateName' => null,
                'mappingName' => null,
                'flags' => null,
                'flagNames' => [],
                'missing' => true,
            ],
            [
                'order' => 3,
                'fieldObject' => '4 0 R',
                'fieldName' => 'review.subtotal',
                'fieldType' => 'Tx',
                'fieldTypeLabel' => 'text',
                'alternateName' => null,
                'mappingName' => 'subtotal',
                'flags' => 1,
                'flagNames' => ['readOnly'],
                'missing' => false,
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfAcroFormCalculationOrder']);
        $t->contains('pdf-byte-acroform-calculation-order:3', $diagnostics);
        $t->contains('pdf-byte-acroform-calculation-order-fields:2', $diagnostics);
        $t->contains('pdf-byte-acroform-calculation-order-missing:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfAcroFormCalculationOrder']);
    },

    'fake runner extracts bounded pdf digital signature metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signed.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) /TU (Reviewer signature) /V 9 0 R /Ff 3 >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R] /SigFlags 3 >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /Reason (Review packet approval) /Location <FEFF00520065006D006F007400650020007200650076006900650077> /ContactInfo (review@example.test) /M (D:20260605121500Z) /ByteRange [0 123 456 789] /Contents <3082010A0282010100AABBCC> /Reference [<< /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >> << /TransformMethod /FieldMDP /TransformParams << /Action /Include /Fields [(reviewer.name) (approved)] >> >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signed.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signed.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'fieldName' => 'review.signature',
                'fieldObject' => '4 0 R',
                'signatureObject' => '9 0 R',
                'filter' => 'Adobe.PPKLite',
                'subFilter' => 'ETSI.CAdES.detached',
                'name' => 'Migration Desk',
                'reason' => 'Review packet approval',
                'location' => 'Remote review',
                'contactInfo' => 'review@example.test',
                'signingTime' => 'D:20260605121500Z',
                'byteRange' => [0, 123, 456, 789],
                'byteRangeSegmentCount' => 2,
                'coveredBytes' => 912,
                'contentsBytes' => strlen($signatureBytes),
                'contentsSha256' => hash('sha256', $signatureBytes),
                'contentsSkipped' => null,
                'referenceTransforms' => [
                    [
                        'transformMethod' => 'DocMDP',
                        'transformParamsType' => 'TransformParams',
                        'permissions' => 2,
                        'action' => null,
                        'fields' => [],
                    ],
                    [
                        'transformMethod' => 'FieldMDP',
                        'transformParamsType' => null,
                        'permissions' => null,
                        'action' => 'Include',
                        'fields' => ['reviewer.name', 'approved'],
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatures']);
        $t->same(['ETSI.CAdES.detached' => 1], $result['pdfSignatureSubFilters']);
        $t->contains('pdf-byte-signatures:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-signature-subfilters:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-signature-byte-ranges:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-signature-reference-transforms:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfSignatures']);
        $t->same(['ETSI.CAdES.detached' => 1], $sequence['finalPdfSignatureSubFilters']);
    },

    'fake runner extracts bounded pdf signature seed value constraints from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signature-seed-values.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.approval) /TU (Reviewer approval) /SV 11 0 R /Ff 3 >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.witness) /SV << /Ff 2 /SubFilter /adbe.pkcs7.sha1 /TimeStamp << /URL (https://tsa.example.test/witness) >> >> >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R] /SigFlags 3 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /SV /Ff 127 /Filter [/Adobe.PPKLite /Entrust.PPKEF] /SubFilter [/adbe.pkcs7.detached /ETSI.CAdES.detached] /DigestMethod [/SHA256 /SHA512] /V 2.0 /Reasons [(Approved) (Migration review)] /LegalAttestation [(Reviewed preservation)] /MDP << /P 2 >> /TimeStamp << /URL (https://tsa.example.test/review) /Ff 1 >> /AddRevInfo true >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signature-seed-values.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signature-seed-values.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'fieldName' => 'review.approval',
                'fieldObject' => '4 0 R',
                'seedValueObject' => '11 0 R',
                'flags' => 127,
                'flagNames' => ['filter', 'subFilter', 'minimumVersion', 'reasons', 'legalAttestation', 'addRevInfo', 'digestMethod'],
                'filters' => ['Adobe.PPKLite', 'Entrust.PPKEF'],
                'subFilters' => ['adbe.pkcs7.detached', 'ETSI.CAdES.detached'],
                'digestMethods' => ['SHA256', 'SHA512'],
                'minimumVersion' => 2.0,
                'reasons' => ['Approved', 'Migration review'],
                'legalAttestations' => ['Reviewed preservation'],
                'mdpPermissions' => 2,
                'timestampUrl' => 'https://tsa.example.test/review',
                'timestampRequired' => true,
                'addRevInfo' => true,
            ],
            [
                'fieldName' => 'review.witness',
                'fieldObject' => '5 0 R',
                'seedValueObject' => 'inline',
                'flags' => 2,
                'flagNames' => ['subFilter'],
                'filters' => [],
                'subFilters' => ['adbe.pkcs7.sha1'],
                'digestMethods' => [],
                'minimumVersion' => null,
                'reasons' => [],
                'legalAttestations' => [],
                'mdpPermissions' => null,
                'timestampUrl' => 'https://tsa.example.test/witness',
                'timestampRequired' => false,
                'addRevInfo' => null,
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureSeedValues']);
        $t->contains('pdf-byte-signature-seed-values:2', $diagnostics);
        $t->contains('pdf-byte-signature-seed-required-flags:129', $diagnostics);
        $t->contains('pdf-byte-signature-seed-filter:Adobe.PPKLite:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-filter:Entrust.PPKEF:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-subfilter:ETSI.CAdES.detached:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-subfilter:adbe.pkcs7.detached:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-digest-method:SHA256:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-digest-method:SHA512:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-reasons:2', $diagnostics);
        $t->contains('pdf-byte-signature-seed-legal-attestations:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-mdp-permissions:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-timestamp-required:1', $diagnostics);
        $t->contains('pdf-byte-signature-seed-add-rev-info:1', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureSeedValues']);
    },

    'fake runner extracts bounded pdf signature lock policy metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signature-lock-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.approval) /SV 11 0 R /Lock 12 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.witness) /SV << /LockDocument /FormFillingAndAnnotations >> /Lock << /Type /SigFieldLock /Action /All >> >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R] /SigFlags 3 >>',
            'endobj',
            '11 0 obj',
            '<< /Type /SV /Ff 4 /LockDocument /NoChanges >>',
            'endobj',
            '12 0 obj',
            '<< /Type /SigFieldLock /Action /Include /Fields [(review.total) (reviewer.name)] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signature-lock-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signature-lock-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'fieldName' => 'review.approval',
                'fieldObject' => '4 0 R',
                'seedValueObject' => '11 0 R',
                'seedLockDocument' => 'NoChanges',
                'fieldLockObject' => '12 0 R',
                'fieldLockType' => 'SigFieldLock',
                'fieldLockAction' => 'Include',
                'fieldLockFields' => ['review.total', 'reviewer.name'],
                'reviewStatus' => 'review',
                'issues' => ['seed-lock-no-changes-overrides-field-list'],
            ],
            [
                'fieldName' => 'review.witness',
                'fieldObject' => '5 0 R',
                'seedValueObject' => 'inline',
                'seedLockDocument' => 'FormFillingAndAnnotations',
                'fieldLockObject' => 'inline',
                'fieldLockType' => 'SigFieldLock',
                'fieldLockAction' => 'All',
                'fieldLockFields' => [],
                'reviewStatus' => 'ok',
                'issues' => [],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureLockPolicies']);
        $t->contains('pdf-byte-signature-lock-policies:2', $diagnostics);
        $t->contains('pdf-byte-signature-lock-seed:NoChanges:1', $diagnostics);
        $t->contains('pdf-byte-signature-lock-seed:FormFillingAndAnnotations:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-lock-action:All:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-lock-action:Include:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-lock-fields:2', $diagnostics);
        $t->contains('pdf-byte-signature-lock-policy-status:ok:1', $diagnostics);
        $t->contains('pdf-byte-signature-lock-policy-status:review:1', $diagnostics);
        $t->contains('pdf-byte-signature-lock-policy-issue:seed-lock-no-changes-overrides-field-list:1', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureLockPolicies']);
    },

    'fake runner cross checks pdf field mdp transforms against signature field locks' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/field-mdp-locks.pdf']);
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
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.approval) /V 9 0 R /Lock 12 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.editor) /V 10 0 R /Lock << /Type /SigFieldLock /Action /Exclude /Fields [(review.total)] >> >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.orphan) /V 11 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Fields [4 0 R 5 0 R 6 0 R] /SigFlags 3 >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /Reference [<< /TransformMethod /FieldMDP /TransformParams << /Action /Include /Fields [(approved) (reviewer.name)] >> >>] >>',
            'endobj',
            '10 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /Reference [<< /TransformMethod /FieldMDP /TransformParams << /Action /Include /Fields [(review.total) (review.extra)] >> >>] >>',
            'endobj',
            '11 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /Reference [<< /TransformMethod /FieldMDP /TransformParams << /Action /Include /Fields [(missing.lock)] >> >>] >>',
            'endobj',
            '12 0 obj',
            '<< /Type /SigFieldLock /Action /Include /Fields [(reviewer.name) (approved)] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/field-mdp-locks.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/field-mdp-locks.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.approval',
                'fieldObject' => '4 0 R',
                'signatureObject' => '9 0 R',
                'transformAction' => 'Include',
                'transformFields' => ['approved', 'reviewer.name'],
                'fieldLockObject' => '12 0 R',
                'fieldLockAction' => 'Include',
                'fieldLockFields' => ['reviewer.name', 'approved'],
                'matchedFieldLock' => true,
                'reviewStatus' => 'ok',
                'issues' => [],
            ],
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.editor',
                'fieldObject' => '5 0 R',
                'signatureObject' => '10 0 R',
                'transformAction' => 'Include',
                'transformFields' => ['review.total', 'review.extra'],
                'fieldLockObject' => 'inline',
                'fieldLockAction' => 'Exclude',
                'fieldLockFields' => ['review.total'],
                'matchedFieldLock' => true,
                'reviewStatus' => 'review',
                'issues' => ['field-mdp-field-lock-action-mismatch', 'field-mdp-field-lock-fields-mismatch'],
            ],
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.orphan',
                'fieldObject' => '6 0 R',
                'signatureObject' => '11 0 R',
                'transformAction' => 'Include',
                'transformFields' => ['missing.lock'],
                'fieldLockObject' => null,
                'fieldLockAction' => null,
                'fieldLockFields' => [],
                'matchedFieldLock' => false,
                'reviewStatus' => 'review',
                'issues' => ['field-mdp-missing-field-lock'],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureFieldMdpPolicies']);
        $t->contains('pdf-byte-signature-field-mdp-policies:3', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-transform-action:Include:3', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-transform-fields:5', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-lock-fields:3', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-matched-locks:2', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-status:ok:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-status:review:2', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-issue:field-mdp-field-lock-action-mismatch:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-issue:field-mdp-field-lock-fields-mismatch:1', $diagnostics);
        $t->contains('pdf-byte-signature-field-mdp-issue:field-mdp-missing-field-lock:1', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureFieldMdpPolicies']);
    },

    'fake runner summarizes bounded pdf signature byte range policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signed-byte-ranges.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $signatureHex = strtoupper(bin2hex($signatureBytes));
        $invalidRange = [20, 40, 30, 2000];
        $buildPdf = static function (array $validRange) use ($signatureHex, $invalidRange): string {
            return implode("\n", [
                '%PDF-1.7',
                '1 0 obj',
                '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R >>',
                'endobj',
                '2 0 obj',
                '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
                'endobj',
                '3 0 obj',
                '<< /Type /Page /Parent 2 0 R /Annots [4 0 R 5 0 R] >>',
                'endobj',
                '4 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) /V 9 0 R >>',
                'endobj',
                '5 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.invalid) /V 10 0 R >>',
                'endobj',
                '8 0 obj',
                '<< /Fields [4 0 R 5 0 R] /SigFlags 3 >>',
                'endobj',
                '9 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /ByteRange [' . implode(' ', $validRange) . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                '10 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Bad Review) /ByteRange [' . implode(' ', $invalidRange) . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                str_repeat('%', 120),
                'trailer',
                '<< /Root 1 0 R >>',
                '%%EOF',
                '',
            ]);
        };
        $pdfBytes = $buildPdf([0, 30, 50, 0]);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $next = $buildPdf([0, 30, 50, max(0, strlen($pdfBytes) - 50)]);
            if (strlen($next) === strlen($pdfBytes)) {
                $pdfBytes = $next;
                break;
            }
            $pdfBytes = $next;
        }
        $validRange = [0, 30, 50, strlen($pdfBytes) - 50];
        $pdfBytes = $buildPdf($validRange);
        $fileBytes = strlen($pdfBytes);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signed-byte-ranges.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signed-byte-ranges.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.invalid',
                'fieldObject' => '5 0 R',
                'signatureObject' => '10 0 R',
                'byteRange' => $invalidRange,
                'segmentCount' => 2,
                'coveredBytes' => 2040,
                'fileBytes' => $fileBytes,
                'gapCount' => 0,
                'gapBytes' => 0,
                'firstGapOffset' => null,
                'firstGapLength' => null,
                'startsAtZero' => false,
                'coversToEnd' => false,
                'ordered' => true,
                'nonOverlapping' => false,
                'fitsFile' => false,
                'contentsBytes' => strlen($signatureBytes),
                'contentsFitsFirstGap' => null,
                'reviewStatus' => 'invalid',
                'issues' => ['does-not-start-at-zero', 'out-of-bounds', 'overlapping-ranges', 'missing-contents-gap', 'does-not-cover-to-end'],
            ],
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.signature',
                'fieldObject' => '4 0 R',
                'signatureObject' => '9 0 R',
                'byteRange' => $validRange,
                'segmentCount' => 2,
                'coveredBytes' => $validRange[1] + $validRange[3],
                'fileBytes' => $fileBytes,
                'gapCount' => 1,
                'gapBytes' => 20,
                'firstGapOffset' => 30,
                'firstGapLength' => 20,
                'startsAtZero' => true,
                'coversToEnd' => true,
                'ordered' => true,
                'nonOverlapping' => true,
                'fitsFile' => true,
                'contentsBytes' => strlen($signatureBytes),
                'contentsFitsFirstGap' => true,
                'reviewStatus' => 'ok',
                'issues' => [],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureByteRangePolicy']);
        $t->contains('pdf-byte-signature-byte-range-policy:2', $diagnostics);
        $t->contains('pdf-byte-signature-byte-range-status:invalid:1', $diagnostics);
        $t->contains('pdf-byte-signature-byte-range-status:ok:1', $diagnostics);
        $t->contains('pdf-byte-signature-byte-range-issue:out-of-bounds:1', $diagnostics);
        $t->contains('pdf-byte-signature-byte-range-issue:overlapping-ranges:1', $diagnostics);
        $t->contains('pdf-byte-signature-byte-range-contents-fit:1', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureByteRangePolicy']);
    },

    'fake runner maps pdf signature appearance byte ranges from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signature-appearance.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $signatureHex = strtoupper(bin2hex($signatureBytes));
        $appearanceBytes = "q 0 0 1 rg /SigMark Do Q\n";
        $buildPdf = static function (int $byteRangeLength) use ($signatureHex, $appearanceBytes): string {
            return implode("\n", [
                '%PDF-1.7',
                '1 0 obj',
                '<< /Type /Catalog /Pages 2 0 R /AcroForm 7 0 R >>',
                'endobj',
                '2 0 obj',
                '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
                'endobj',
                '3 0 obj',
                '<< /Type /Page /Parent 2 0 R /Annots [8 0 R] >>',
                'endobj',
                '7 0 obj',
                '<< /Fields [8 0 R] /SigFlags 3 >>',
                'endobj',
                '8 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) /V 9 0 R /AS /Signed /AP << /N 10 0 R >> >>',
                'endobj',
                '9 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /ByteRange [0 ' . $byteRangeLength . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                '10 0 obj',
                '<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Resources << /XObject << /SigMark 11 0 R >> >> /Length ' . strlen($appearanceBytes) . ' >>',
                'stream',
                $appearanceBytes,
                'endstream',
                'endobj',
                '11 0 obj',
                '<< /Type /XObject /Subtype /Image /Width 16 /Height 16 /BitsPerComponent 8 /ColorSpace /DeviceGray /Length 0 >>',
                'stream',
                '',
                'endstream',
                'endobj',
                'trailer',
                '<< /Root 1 0 R >>',
                'startxref',
                '2048',
                '%%EOF',
                '',
            ]);
        };
        $pdfBytes = $buildPdf(0);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $next = $buildPdf(strlen($pdfBytes));
            if (strlen($next) === strlen($pdfBytes)) {
                $pdfBytes = $next;
                break;
            }
            $pdfBytes = $next;
        }

        $appearanceObjectOffset = strpos($pdfBytes, '10 0 obj');
        $t->true($appearanceObjectOffset !== false, 'signature appearance object offset is present');
        $appearanceObjectEnd = strpos($pdfBytes, 'endobj', (int) $appearanceObjectOffset);
        $t->true($appearanceObjectEnd !== false, 'signature appearance object end is present');
        $appearanceStreamOffset = strpos($pdfBytes, "stream\n", (int) $appearanceObjectOffset);
        $t->true($appearanceStreamOffset !== false, 'signature appearance stream offset is present');
        $appearanceStreamOffset = (int) $appearanceStreamOffset + strlen("stream\n");
        $appearanceObjectBytes = (int) $appearanceObjectEnd + strlen('endobj') - (int) $appearanceObjectOffset;

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signature-appearance.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signature-appearance.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'fieldName' => 'review.signature',
                'fieldObject' => '8 0 R',
                'signatureObject' => '9 0 R',
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '8 0 R',
                'appearance' => 'N',
                'stateName' => null,
                'appearanceObject' => '10 0 R',
                'appearanceObjectOffset' => (int) $appearanceObjectOffset,
                'appearanceObjectBytes' => $appearanceObjectBytes,
                'appearanceStreamOffset' => $appearanceStreamOffset,
                'appearanceStreamBytes' => strlen($appearanceBytes),
                'objectCoveredBySignature' => true,
                'streamCoveredBySignature' => true,
                'reviewStatus' => 'covered',
                'issues' => [],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureAppearanceByteRanges']);
        $t->contains('pdf-byte-signature-appearance-byte-ranges:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-byte-range-status:covered:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-covered-objects:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-covered-streams:1', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureAppearanceByteRanges']);
    },

    'fake runner summarizes pdf visual signature appearance policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/signature-appearance-policy.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $signatureHex = strtoupper(bin2hex($signatureBytes));
        $visibleAppearanceBytes = "q 0 0 1 rg 12 12 84 24 re f Q\n";
        $unselectedAppearanceBytes = "q 1 0 0 rg 12 12 84 24 re f Q\n";
        $buildPdf = static function (int $byteRangeLength) use ($signatureHex, $visibleAppearanceBytes, $unselectedAppearanceBytes): string {
            return implode("\n", [
                '%PDF-1.7',
                '1 0 obj',
                '<< /Type /Catalog /Pages 2 0 R /AcroForm 7 0 R >>',
                'endobj',
                '2 0 obj',
                '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
                'endobj',
                '3 0 obj',
                '<< /Type /Page /Parent 2 0 R /Annots [8 0 R 9 0 R 12 0 R] >>',
                'endobj',
                '7 0 obj',
                '<< /Fields [8 0 R 9 0 R 12 0 R] /SigFlags 3 >>',
                'endobj',
                '8 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.visible) /V 18 0 R /AS /Signed /AP << /N << /Signed 10 0 R >> >> >>',
                'endobj',
                '9 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.unselected) /V 19 0 R /AP << /N << /Signed 11 0 R >> >> >>',
                'endobj',
                '10 0 obj',
                '<< /Type /XObject /Subtype /Form /BBox [0 0 108 48] /Length ' . strlen($visibleAppearanceBytes) . ' >>',
                'stream',
                $visibleAppearanceBytes,
                'endstream',
                'endobj',
                '11 0 obj',
                '<< /Type /XObject /Subtype /Form /BBox [0 0 108 48] /Length ' . strlen($unselectedAppearanceBytes) . ' >>',
                'stream',
                $unselectedAppearanceBytes,
                'endstream',
                'endobj',
                '12 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.invisible) /V 20 0 R >>',
                'endobj',
                '18 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /ByteRange [0 ' . $byteRangeLength . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                '19 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /ByteRange [0 ' . $byteRangeLength . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                '20 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /ByteRange [0 ' . $byteRangeLength . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                'trailer',
                '<< /Root 1 0 R >>',
                'startxref',
                '2048',
                '%%EOF',
                '',
            ]);
        };
        $pdfBytes = $buildPdf(0);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $next = $buildPdf(strlen($pdfBytes));
            if (strlen($next) === strlen($pdfBytes)) {
                $pdfBytes = $next;
                break;
            }
            $pdfBytes = $next;
        }

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signature-appearance-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signature-appearance-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'fieldName' => 'review.invisible',
                'fieldObject' => '12 0 R',
                'signatureObject' => '20 0 R',
                'page' => null,
                'pageObject' => null,
                'annotationObject' => null,
                'selectedState' => null,
                'normalAppearanceCount' => 0,
                'appearanceStates' => [],
                'appearanceObjects' => [],
                'streamBytes' => 0,
                'coveredAppearanceCount' => 0,
                'reviewStatus' => 'missing',
                'issues' => ['missing-signature-widget-appearance'],
            ],
            [
                'fieldName' => 'review.unselected',
                'fieldObject' => '9 0 R',
                'signatureObject' => '19 0 R',
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '9 0 R',
                'selectedState' => null,
                'normalAppearanceCount' => 1,
                'appearanceStates' => ['Signed'],
                'appearanceObjects' => ['11 0 R'],
                'streamBytes' => strlen($unselectedAppearanceBytes),
                'coveredAppearanceCount' => 1,
                'reviewStatus' => 'review',
                'issues' => ['missing-selected-state'],
            ],
            [
                'fieldName' => 'review.visible',
                'fieldObject' => '8 0 R',
                'signatureObject' => '18 0 R',
                'page' => 1,
                'pageObject' => '3 0 R',
                'annotationObject' => '8 0 R',
                'selectedState' => 'Signed',
                'normalAppearanceCount' => 1,
                'appearanceStates' => ['Signed'],
                'appearanceObjects' => ['10 0 R'],
                'streamBytes' => strlen($visibleAppearanceBytes),
                'coveredAppearanceCount' => 1,
                'reviewStatus' => 'ok',
                'issues' => [],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureAppearancePolicy']);
        $t->contains('pdf-byte-signature-appearance-policy:3', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-status:missing:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-status:ok:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-status:review:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-issue:missing-selected-state:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-issue:missing-signature-widget-appearance:1', $diagnostics);
        $t->contains('pdf-byte-signature-appearance-policy-covered:2', $diagnostics);
        $t->same($expected, $sequence['finalPdfSignatureAppearancePolicy']);
    },

    'fake runner maps pdf signatures to incremental trailer revisions' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/incremental-signature.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $signatureHex = strtoupper(bin2hex($signatureBytes));
        $buildPdf = static function (array $byteRange) use ($signatureHex): string {
            return implode("\n", [
                '%PDF-1.7',
                '1 0 obj',
                '<< /Type /Catalog /Pages 2 0 R /AcroForm 8 0 R /Perms << /DocMDP 9 0 R >> >>',
                'endobj',
                '2 0 obj',
                '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
                'endobj',
                '3 0 obj',
                '<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>',
                'endobj',
                '4 0 obj',
                '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) /V 9 0 R >>',
                'endobj',
                '8 0 obj',
                '<< /Fields [4 0 R] /SigFlags 3 >>',
                'endobj',
                '9 0 obj',
                '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /M (D:20260608231000Z) /ByteRange [' . implode(' ', $byteRange) . '] /Contents <' . $signatureHex . '> >>',
                'endobj',
                'trailer',
                '<< /Size 10 /Root 1 0 R >>',
                'startxref',
                '512',
                '%%EOF',
                '12 0 obj',
                '<< /Producer (WordPress review appender) >>',
                'endobj',
                'trailer',
                '<< /Size 13 /Root 1 0 R /Info 12 0 R /Prev 512 >>',
                'startxref',
                '1024',
                '%%EOF',
                '',
            ]);
        };

        $pdfBytes = $buildPdf([0, 30, 50, 0]);
        $byteRange = [0, 30, 50, 0];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $firstEofOffset = strpos($pdfBytes, '%%EOF');
            $t->true($firstEofOffset !== false, 'First EOF marker is present');
            $firstRevisionEnd = (int) $firstEofOffset + strlen('%%EOF');
            $nextRange = [0, 30, 50, max(0, $firstRevisionEnd - 50)];
            $nextPdf = $buildPdf($nextRange);
            if ($nextRange === $byteRange && strlen($nextPdf) === strlen($pdfBytes)) {
                break;
            }
            $byteRange = $nextRange;
            $pdfBytes = $nextPdf;
        }

        $firstEofOffset = strpos($pdfBytes, '%%EOF');
        $t->true($firstEofOffset !== false, 'First EOF marker is present after ByteRange stabilization');
        $firstRevisionEnd = (int) $firstEofOffset + strlen('%%EOF');
        $byteRange = [0, 30, 50, max(0, $firstRevisionEnd - 50)];
        $pdfBytes = $buildPdf($byteRange);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/incremental-signature.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/incremental-signature.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'catalog-permission',
                'permission' => 'DocMDP',
                'fieldName' => null,
                'fieldObject' => null,
                'signatureObject' => '9 0 R',
                'signingTime' => 'D:20260608231000Z',
                'byteRangeEnd' => $firstRevisionEnd,
                'revision' => 1,
                'revisionStartXref' => 512,
                'revisionPrev' => null,
                'revisionRoot' => '1 0 R',
                'revisionInfo' => null,
                'revisionEncrypt' => null,
                'revisionByteEnd' => $firstRevisionEnd,
                'coversRevisionEnd' => true,
                'latestRevision' => 2,
                'laterRevisions' => 1,
                'reviewStatus' => 'superseded-by-incremental-update',
            ],
            [
                'source' => 'signature',
                'permission' => null,
                'fieldName' => 'review.signature',
                'fieldObject' => '4 0 R',
                'signatureObject' => '9 0 R',
                'signingTime' => 'D:20260608231000Z',
                'byteRangeEnd' => $firstRevisionEnd,
                'revision' => 1,
                'revisionStartXref' => 512,
                'revisionPrev' => null,
                'revisionRoot' => '1 0 R',
                'revisionInfo' => null,
                'revisionEncrypt' => null,
                'revisionByteEnd' => $firstRevisionEnd,
                'coversRevisionEnd' => true,
                'latestRevision' => 2,
                'laterRevisions' => 1,
                'reviewStatus' => 'superseded-by-incremental-update',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfSignatureRevisionMetadata']);
        $t->contains('pdf-byte-signature-revisions:2', $diagnostics);
        $t->contains('pdf-byte-signature-revision-status:superseded-by-incremental-update:2', $diagnostics);
        $t->contains('pdf-byte-signature-revision-superseded:2', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfSignatureRevisionMetadata']);
    },

    'fake runner extracts bounded pdf catalog permission signatures from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/permissions.pdf']);
        $docMdpBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $usageRightsBytes = hex2bin('3082010A0282010100DDEEFF') ?: '';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Perms 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /DocMDP 9 0 R /UR3 << /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Migration Desk) /Reason (Usage rights review) /M (D:20260605121600Z) /ByteRange [0 45 90 135] /Contents <3082010A0282010100DDEEFF> /Reference [<< /TransformMethod /UR3 /TransformParams << /Type /TransformParams /P 3 /V /2.2 >> >>] >> >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Migration Desk) /Reason (Certified review packet) /Location (Remote review) /ContactInfo (review@example.test) /M (D:20260605121500Z) /ByteRange [0 123 456 789] /Contents <3082010A0282010100AABBCC> /Reference [<< /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/permissions.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/permissions.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'permission' => 'DocMDP',
                'signatureObject' => '9 0 R',
                'filter' => 'Adobe.PPKLite',
                'subFilter' => 'ETSI.CAdES.detached',
                'name' => 'Migration Desk',
                'reason' => 'Certified review packet',
                'location' => 'Remote review',
                'contactInfo' => 'review@example.test',
                'signingTime' => 'D:20260605121500Z',
                'byteRange' => [0, 123, 456, 789],
                'byteRangeSegmentCount' => 2,
                'coveredBytes' => 912,
                'contentsBytes' => strlen($docMdpBytes),
                'contentsSha256' => hash('sha256', $docMdpBytes),
                'contentsSkipped' => null,
                'referenceTransforms' => [
                    [
                        'transformMethod' => 'DocMDP',
                        'transformParamsType' => 'TransformParams',
                        'permissions' => 2,
                        'action' => null,
                        'fields' => [],
                    ],
                ],
            ],
            [
                'permission' => 'UR3',
                'signatureObject' => 'inline',
                'filter' => 'Adobe.PPKLite',
                'subFilter' => 'adbe.pkcs7.detached',
                'name' => 'Migration Desk',
                'reason' => 'Usage rights review',
                'location' => null,
                'contactInfo' => null,
                'signingTime' => 'D:20260605121600Z',
                'byteRange' => [0, 45, 90, 135],
                'byteRangeSegmentCount' => 2,
                'coveredBytes' => 180,
                'contentsBytes' => strlen($usageRightsBytes),
                'contentsSha256' => hash('sha256', $usageRightsBytes),
                'contentsSkipped' => null,
                'referenceTransforms' => [
                    [
                        'transformMethod' => 'UR3',
                        'transformParamsType' => 'TransformParams',
                        'permissions' => 3,
                        'action' => null,
                        'fields' => [],
                    ],
                ],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfCatalogPermissions']);
        $t->contains('pdf-byte-catalog-permissions:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-catalog-permission-byte-ranges:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-catalog-permission-reference-transforms:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-catalog-permission-subfilter:ETSI.CAdES.detached:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-catalog-permission-subfilter:adbe.pkcs7.detached:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfCatalogPermissions']);
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

    'fake runner extracts bounded pdf page lifecycle actions from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-actions.pdf']);
        $pageScript = 'this.pageNum = 0;';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA << /O << /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $pageScript) . ') >> /C 8 0 R >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA 9 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /S /Named /N /NextPage >>',
            'endobj',
            '9 0 obj',
            '<< /O << /S /Launch /F (tools/page-review-helper.exe) >> /C << /S /SubmitForm /F (https://example.test/review/page-close) >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-actions.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-actions.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'trigger' => 'O',
                'triggerLabel' => 'page-open',
                'source' => 'page:3 0 R.AA.O',
                'actionType' => 'JavaScript',
                'actionTarget' => null,
                'scriptBytes' => strlen($pageScript),
                'scriptSha256' => hash('sha256', $pageScript),
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'trigger' => 'C',
                'triggerLabel' => 'page-close',
                'source' => 'page:3 0 R.AA.C',
                'actionType' => 'Named',
                'actionTarget' => 'NextPage',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'trigger' => 'O',
                'triggerLabel' => 'page-open',
                'source' => 'page:4 0 R.AA.O',
                'actionType' => 'Launch',
                'actionTarget' => 'tools/page-review-helper.exe',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'trigger' => 'C',
                'triggerLabel' => 'page-close',
                'source' => 'page:4 0 R.AA.C',
                'actionType' => 'SubmitForm',
                'actionTarget' => 'https://example.test/review/page-close',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
        ];
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'pageCount' => 2,
            'actionCount' => 4,
            'pagesWithActions' => [1, 2],
            'openActionPages' => [1, 2],
            'closeActionPages' => [1, 2],
            'triggerCounts' => ['C' => 2, 'O' => 2],
            'actionTypes' => [
                'JavaScript' => 1,
                'Launch' => 1,
                'Named' => 1,
                'SubmitForm' => 1,
            ],
            'scriptActionCount' => 1,
            'remoteTargetCount' => 1,
            'launchActionCount' => 1,
            'issues' => [
                'launch-action',
                'page-close-action',
                'page-open-action',
                'remote-action-target',
                'script-action',
                'submit-form-action',
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageActions'] ?? null);
        $t->same($expectedPolicy, $result['pdfPageActionPolicy'] ?? null);
        $t->contains('pdf-byte-page-actions:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-action-trigger:O:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-action-trigger:C:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-action-type:JavaScript:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-action-type:Launch:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-page-action-scripts:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfPageActions'] ?? null);
        $t->same($expectedPolicy, $sequence['finalPdfPageActionPolicy'] ?? null);
    },

    'fake runner summarizes bounded pdf page lifecycle action review policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/page-action-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 3 /Kids [3 0 R 4 0 R 5 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA << /O << /S /Named /N /Print >> >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA << /C << /S /SubmitForm /F (mailto:review@example.test) >> >> >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/page-action-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/page-action-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            'reviewStatus' => 'review',
            'pageCount' => 3,
            'actionCount' => 2,
            'pagesWithActions' => [1, 2],
            'openActionPages' => [1],
            'closeActionPages' => [2],
            'triggerCounts' => ['C' => 1, 'O' => 1],
            'actionTypes' => ['Named' => 1, 'SubmitForm' => 1],
            'scriptActionCount' => 0,
            'remoteTargetCount' => 1,
            'launchActionCount' => 0,
            'issues' => [
                'page-close-action',
                'page-open-action',
                'remote-action-target',
                'submit-form-action',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfPageActionPolicy'] ?? null);
        $t->same($expected, $sequence['finalPdfPageActionPolicy'] ?? null);
        $t->contains('pdf-byte-page-action-policy:review', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-actions:2', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-remote-targets:1', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-trigger:C:1', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-trigger:O:1', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-type:SubmitForm:1', $diagnostics);
        $t->contains('pdf-byte-page-action-policy-issue:remote-action-target:1', $diagnostics);
    },

    'fake runner extracts bounded pdf platform launch action targets from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/platform-launch.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OpenAction << /S /Launch /Win << /F (tools/open-review.exe) /D (C:/review) /O (open) /P (--packet packets/platform-launch.pdf) >> >> /AA << /WS 8 0 R /DS 9 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /S /Launch /Win << /F (tools/review-helper.exe) /D (C:/review) /O (open) /P (--packet packets/platform-launch.pdf) >> >>',
            'endobj',
            '9 0 obj',
            '<< /S /Launch /Unix << /F (/usr/bin/xdg-open) /P (packets/platform-launch.pdf) >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/platform-launch.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/platform-launch.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expectedActions = [
            [
                'source' => 'catalog.AA.DS',
                'type' => 'Launch',
                'target' => 'Unix:F=/usr/bin/xdg-open;P=packets/platform-launch.pdf',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.AA.WS',
                'type' => 'Launch',
                'target' => 'Win:F=tools/review-helper.exe;D=C:/review;O=open;P=--packet packets/platform-launch.pdf',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.OpenAction',
                'type' => 'Launch',
                'target' => 'Win:F=tools/open-review.exe;D=C:/review;O=open;P=--packet packets/platform-launch.pdf',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same([
            'type' => 'Launch',
            'target' => 'Win:F=tools/open-review.exe;D=C:/review;O=open;P=--packet packets/platform-launch.pdf',
        ], $result['pdfOpenAction']);
        $t->same($expectedActions, $result['pdfActiveActions']);
        $t->same(['Launch' => 3], $result['pdfActiveActionTypes']);
        $t->contains('pdf-byte-active-actions:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-type:Launch:3', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedActions, $sequence['finalPdfActiveActions']);
        $t->same($result['pdfOpenAction'], $sequence['finalPdfOpenAction']);
    },

    'fake runner expands bounded pdf chained next actions from active dictionaries' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/action-chain.pdf']);
        $catalogScript = 'app.alert("review open")';
        $nextScript = 'app.alert("follow-up review")';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /AA << /O 12 0 R >> /Annots [14 0 R] >>',
            'endobj',
            '8 0 obj',
            '<< /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $catalogScript) . ') /Next [9 0 R << /S /Named /N /NextPage >>] >>',
            'endobj',
            '9 0 obj',
            '<< /S /Launch /F (tools/review-helper.exe) /Next 10 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /S /SubmitForm /F (https://example.test/review/submit) /Next [11 0 R 9 0 R] >>',
            'endobj',
            '11 0 obj',
            '<< /S /Hide /T (review-overlay) >>',
            'endobj',
            '12 0 obj',
            '<< /S /Named /N /Print /Next 13 0 R >>',
            'endobj',
            '13 0 obj',
            '<< /S /JavaScript /JS <' . strtoupper(bin2hex($nextScript)) . '> >>',
            'endobj',
            '14 0 obj',
            '<< /Type /Annot /Subtype /Screen /A << /S /Rendition /OP 4 /Next << /S /ResetForm /T (review-form) >> >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/action-chain.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/action-chain.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'source' => 'annotation:14 0 R.A',
                'type' => 'Rendition',
                'target' => null,
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'annotation:14 0 R.A.Next',
                'type' => 'ResetForm',
                'target' => null,
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.OpenAction',
                'type' => 'JavaScript',
                'target' => null,
                'scriptBytes' => strlen($catalogScript),
                'scriptSha256' => hash('sha256', $catalogScript),
            ],
            [
                'source' => 'catalog.OpenAction.Next[0]',
                'type' => 'Launch',
                'target' => 'tools/review-helper.exe',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.OpenAction.Next[0].Next',
                'type' => 'SubmitForm',
                'target' => 'https://example.test/review/submit',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.OpenAction.Next[0].Next.Next[0]',
                'type' => 'Hide',
                'target' => 'review-overlay',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.OpenAction.Next[1]',
                'type' => 'Named',
                'target' => 'NextPage',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'page:3 0 R.AA.O',
                'type' => 'Named',
                'target' => 'Print',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'page:3 0 R.AA.O.Next',
                'type' => 'JavaScript',
                'target' => null,
                'scriptBytes' => strlen($nextScript),
                'scriptSha256' => hash('sha256', $nextScript),
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfActiveActions']);
        $t->same([
            'Hide' => 1,
            'JavaScript' => 2,
            'Launch' => 1,
            'Named' => 2,
            'Rendition' => 1,
            'ResetForm' => 1,
            'SubmitForm' => 1,
        ], $result['pdfActiveActionTypes']);
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'actionCount' => 9,
            'sourceCount' => 9,
            'sourceCategories' => [
                'annotation' => 2,
                'catalog' => 5,
                'page' => 2,
            ],
            'actionTypes' => [
                'Hide' => 1,
                'JavaScript' => 2,
                'Launch' => 1,
                'Named' => 2,
                'Rendition' => 1,
                'ResetForm' => 1,
                'SubmitForm' => 1,
            ],
            'chainedActionCount' => 6,
            'maxNextDepth' => 3,
            'scriptActionCount' => 2,
            'remoteTargetCount' => 1,
            'launchActionCount' => 1,
            'formActionCount' => 2,
            'issues' => [
                'deep-next-action-chain',
                'launch-action',
                'next-action-chain',
                'remote-action-target',
                'reset-form-action',
                'script-action',
                'submit-form-action',
            ],
        ];
        $t->contains('pdf-byte-active-actions:9', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-type:Hide:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-type:ResetForm:1', implode(',', $result['diagnostics']));
        $t->same($expectedPolicy, $result['pdfActiveActionPolicy'] ?? null);
        $t->contains('pdf-byte-active-action-policy:review', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-policy-chained-actions:6', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-policy-chain-depth:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-policy-issue:deep-next-action-chain:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfActiveActions']);
        $t->same($result['pdfActiveActionTypes'], $sequence['finalPdfActiveActionTypes']);
        $t->same($expectedPolicy, $sequence['finalPdfActiveActionPolicy'] ?? null);
    },

    'fake runner extracts bounded pdf javascript name tree kids from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/javascript-name-tree.pdf']);
        $catalogScript = 'app.alert("Review name tree action")';
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 8 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Kids [9 0 R 10 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Limits [(ReviewOpen) (ReviewOpen)] /Names [(ReviewOpen) 11 0 R] >>',
            'endobj',
            '10 0 obj',
            '<< /Kids [12 0 R] >>',
            'endobj',
            '11 0 obj',
            '<< /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $catalogScript) . ') >>',
            'endobj',
            '12 0 obj',
            '<< /Limits [(ReviewSubmit) (ReviewSubmit)] /Names [(ReviewSubmit) << /S /SubmitForm /F (https://example.test/review/submit) >>] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/javascript-name-tree.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/javascript-name-tree.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expected = [
            [
                'source' => 'catalog.Names.JavaScript.Kids.10 0 R.Kids.12 0 R.ReviewSubmit',
                'type' => 'SubmitForm',
                'target' => 'https://example.test/review/submit',
                'scriptBytes' => null,
                'scriptSha256' => null,
            ],
            [
                'source' => 'catalog.Names.JavaScript.Kids.9 0 R.ReviewOpen',
                'type' => 'JavaScript',
                'target' => 'ReviewOpen',
                'scriptBytes' => strlen($catalogScript),
                'scriptSha256' => hash('sha256', $catalogScript),
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfActiveActions']);
        $t->same([
            'JavaScript' => 1,
            'SubmitForm' => 1,
        ], $result['pdfActiveActionTypes']);
        $t->contains('pdf-byte-active-actions:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-active-action-types:2', implode(',', $result['diagnostics']));
        $t->same($expected, $sequence['finalPdfActiveActions']);
        $t->same($result['pdfActiveActionTypes'], $sequence['finalPdfActiveActionTypes']);
    },

    'fake runner extracts bounded pdf optional content layer metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/layers.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OCProperties 9 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /OCGs [10 0 R 11 0 R] /D << /Name (Review layer config) /Creator (Pandoc native handoff) /BaseState /ON /ON [10 0 R] /OFF [11 0 R] /Order [(Reviewer overlays) 10 0 R [11 0 R]] /ListMode /VisiblePages >> >>',
            'endobj',
            '10 0 obj',
            '<< /Type /OCG /Name (Reviewer notes) /Intent [/View /Design] /Usage << /View << /ViewState /ON >> /Print << /PrintState /OFF >> /Export << /ExportState /OFF >> /CreatorInfo << /Creator (layer package) /Subtype /Artwork >> /Language << /Lang (en-US) /Preferred true >> /Zoom << /min 0.5 /max 2.0 >> >> >>',
            'endobj',
            '11 0 obj',
            '<< /Type /OCG /Name <FEFF005000720069006E00740020006D00610072006B0073> /Intent /View /Usage << /Print << /PrintState /ON >> /Export << /ExportState /ON >> >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/layers.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/layers.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expectedGroups = [
            [
                'object' => '10 0 R',
                'name' => 'Reviewer notes',
                'intent' => ['View', 'Design'],
                'usageViewState' => 'ON',
                'usagePrintState' => 'OFF',
                'usageExportState' => 'OFF',
                'usageCreator' => 'layer package',
                'usageCreatorSubtype' => 'Artwork',
                'usageLanguage' => 'en-US',
                'usageLanguagePreferred' => true,
                'usageZoomMin' => 0.5,
                'usageZoomMax' => 2.0,
            ],
            [
                'object' => '11 0 R',
                'name' => 'Print marks',
                'intent' => ['View'],
                'usageViewState' => null,
                'usagePrintState' => 'ON',
                'usageExportState' => 'ON',
                'usageCreator' => null,
                'usageCreatorSubtype' => null,
                'usageLanguage' => null,
                'usageLanguagePreferred' => null,
                'usageZoomMin' => null,
                'usageZoomMax' => null,
            ],
        ];
        $expectedConfig = [
            'name' => 'Review layer config',
            'creator' => 'Pandoc native handoff',
            'baseState' => 'ON',
            'listMode' => 'VisiblePages',
            'on' => ['10 0 R'],
            'off' => ['11 0 R'],
            'order' => ['10 0 R', '11 0 R'],
            'orderLabels' => ['Reviewer overlays'],
        ];

        $t->same(true, $result['ok']);
        $t->same($expectedGroups, $result['pdfOptionalContentGroups']);
        $t->same($expectedConfig, $result['pdfOptionalContentConfig']);
        $t->contains('pdf-byte-optional-content-groups:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-config', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-off:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedGroups, $sequence['finalPdfOptionalContentGroups']);
        $t->same($expectedConfig, $sequence['finalPdfOptionalContentConfig']);
    },

    'fake runner extracts bounded pdf optional content lock and radio group policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/layer-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [10 0 R 11 0 R] /D 12 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /OCG /Name (Reviewer notes) /Intent /View >>',
            'endobj',
            '11 0 obj',
            '<< /Type /OCG /Name (Print marks) /Intent /Design >>',
            'endobj',
            '12 0 obj',
            '<< /Name (Layer review policy) /Creator (Pandoc native handoff) /BaseState /ON /ON [10 0 R] /Order [10 0 R 11 0 R] /ListMode /AllPages /Locked [11 0 R] /RBGroups 13 0 R >>',
            'endobj',
            '13 0 obj',
            '[[10 0 R 11 0 R] [11 0 R]]',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/layer-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/layer-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expectedConfig = [
            'name' => 'Layer review policy',
            'creator' => 'Pandoc native handoff',
            'baseState' => 'ON',
            'listMode' => 'AllPages',
            'on' => ['10 0 R'],
            'off' => [],
            'order' => ['10 0 R', '11 0 R'],
            'orderLabels' => [],
            'locked' => ['11 0 R'],
            'radioButtonGroups' => [
                ['10 0 R', '11 0 R'],
                ['11 0 R'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same($expectedConfig, $result['pdfOptionalContentConfig']);
        $t->contains('pdf-byte-optional-content-config', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-locked:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-radio-button-groups:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-radio-button-members:3', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedConfig, $sequence['finalPdfOptionalContentConfig']);
    },

    'fake runner extracts bounded pdf optional content membership metadata from page resources' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/layer-membership.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [10 0 R 11 0 R] >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Resources << /Properties << /InheritedLayer 13 0 R >> >> /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Resources << /Properties << /ReviewLayer 12 0 R /InlineLayer << /Type /OCMD /OCGs 10 0 R /P /AllOn >> >> >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '10 0 obj',
            '<< /Type /OCG /Name (Reviewer notes) >>',
            'endobj',
            '11 0 obj',
            '<< /Type /OCG /Name (Print marks) >>',
            'endobj',
            '12 0 obj',
            '<< /Type /OCMD /OCGs [10 0 R 11 0 R] /P /AnyOn /VE [/And 10 0 R [/Not 11 0 R]] >>',
            'endobj',
            '13 0 obj',
            '<< /Type /OCMD /OCGs [11 0 R] /P /AllOff /VE [/Or 11 0 R] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/layer-membership.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/layer-membership.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'propertyName' => 'InlineLayer',
                'propertyObject' => 'inline',
                'inherited' => false,
                'type' => 'OCMD',
                'groups' => ['10 0 R'],
                'policy' => 'AllOn',
                'visibilityExpressionOperators' => [],
                'visibilityExpressionGroups' => [],
            ],
            [
                'page' => 1,
                'pageObject' => '3 0 R',
                'propertyName' => 'ReviewLayer',
                'propertyObject' => '12 0 R',
                'inherited' => false,
                'type' => 'OCMD',
                'groups' => ['10 0 R', '11 0 R'],
                'policy' => 'AnyOn',
                'visibilityExpressionOperators' => ['And', 'Not'],
                'visibilityExpressionGroups' => ['10 0 R', '11 0 R'],
            ],
            [
                'page' => 2,
                'pageObject' => '4 0 R',
                'propertyName' => 'InheritedLayer',
                'propertyObject' => '13 0 R',
                'inherited' => true,
                'type' => 'OCMD',
                'groups' => ['11 0 R'],
                'policy' => 'AllOff',
                'visibilityExpressionOperators' => ['Or'],
                'visibilityExpressionGroups' => ['11 0 R'],
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same([], $result['pdfMarkedContentProperties']);
        $t->same($expected, $result['pdfOptionalContentMemberships'] ?? null);
        $t->contains('pdf-byte-optional-content-memberships:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-membership-groups:4', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-optional-content-membership-expressions:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfOptionalContentMemberships'] ?? null);
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

    'fake runner extracts bounded pdf crypt filter encryption metadata without decrypting output' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/crypt-filters.pdf']);
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
            '<< /Filter /Standard /V 4 /R 4 /Length 128 /P -44 /EncryptMetadata true /StmF /StdCF /StrF /Identity /EFF /EmbeddedCF /CF << /StdCF << /CFM /AESV3 /Length 32 /AuthEvent /DocOpen /Recipients [<001122> <AABBCC>] >> /EmbeddedCF << /Type /CryptFilter /CFM /AESV2 /Length 16 /AuthEvent /EFOpen >> /Identity << /CFM /None >> >> >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Encrypt 8 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/crypt-filters.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/crypt-filters.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedFilters = [
            'stream' => 'StdCF',
            'string' => 'Identity',
            'embeddedFile' => 'EmbeddedCF',
        ];
        $expectedCryptFilters = [
            [
                'name' => 'EmbeddedCF',
                'object' => null,
                'cryptFilterMethod' => 'AESV2',
                'length' => 16,
                'authEvent' => 'EFOpen',
                'recipients' => 0,
                'rawKeys' => ['AuthEvent', 'CFM', 'Length', 'Type'],
            ],
            [
                'name' => 'Identity',
                'object' => null,
                'cryptFilterMethod' => 'None',
                'length' => null,
                'authEvent' => null,
                'recipients' => 0,
                'rawKeys' => ['CFM'],
            ],
            [
                'name' => 'StdCF',
                'object' => null,
                'cryptFilterMethod' => 'AESV3',
                'length' => 32,
                'authEvent' => 'DocOpen',
                'recipients' => 2,
                'rawKeys' => ['AuthEvent', 'CFM', 'Length', 'Recipients'],
            ],
        ];

        $t->same(false, $result['ok']);
        $t->same('pdf-output-encrypted', $result['reason']);
        $t->same($expectedFilters, $result['pdfEncryptionDefaultFilters']);
        $t->same($expectedCryptFilters, $result['pdfEncryptionCryptFilters']);
        $t->contains('pdf-encryption-default-filters:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-default-filter:stream:StdCF', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-crypt-filters:3', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-crypt-filter-method:AESV3:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-crypt-filter-auth-event:EFOpen:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-encryption-crypt-filter-recipients:2', implode(',', $result['diagnostics']));
        $t->same($expectedFilters, $sequence['finalPdfEncryptionDefaultFilters']);
        $t->same($expectedCryptFilters, $sequence['finalPdfEncryptionCryptFilters']);
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

    'fake runner extracts engine missing dependency diagnostics without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'review.pdf']);
        $log = implode("\n", [
            "! LaTeX Error: File `review-style.sty' not found.",
            'Package fontspec Error: The font "Source Serif 4" cannot be found.',
            'kpathsea: Running mktextfm SourceSerif4-Regular',
            '! Font TU/SourceSerif4(0)/m/n/10="Source Serif 4" at 10.0pt not loadable: Metric (TFM) file or installed font not found.',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'exitCode' => 1,
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.log' => $log,
            ],
        ]);

        $expected = [
            [
                'kind' => 'tex-file',
                'name' => 'review-style.sty',
                'message' => "! LaTeX Error: File `review-style.sty' not found.",
            ],
            [
                'kind' => 'font',
                'name' => 'Source Serif 4',
                'message' => 'Package fontspec Error: The font "Source Serif 4" cannot be found.',
            ],
            [
                'kind' => 'font-metric',
                'name' => 'SourceSerif4-Regular',
                'message' => 'kpathsea: Running mktextfm SourceSerif4-Regular',
            ],
        ];

        $t->same(false, $result['ok']);
        $t->same('engine-log-error', $result['reason']);
        $t->same($expected, $result['engineMissingDependencies']);
        $t->same(['font' => 1, 'font-metric' => 1, 'tex-file' => 1], $result['engineMissingDependencyKinds']);
        $t->contains('engine-missing-dependencies:3', implode(',', $result['diagnostics']));
        $t->contains('engine-missing-dependency-kind:tex-file:1', implode(',', $result['diagnostics']));
        $t->contains('engine-missing-dependency-kind:font:1', implode(',', $result['diagnostics']));
        $t->contains('engine-missing-dependency-kind:font-metric:1', implode(',', $result['diagnostics']));
        $t->contains('engine-missing-dependency:tex-file:review-style.sty', implode(',', $result['diagnostics']));
        $t->contains('engine-missing-dependency:font:Source Serif 4', implode(',', $result['diagnostics']));
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

    'fake runner extracts bounded pdf header catalog version and extensions metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/versioned.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.5',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Version /1.7 /Extensions << /ADBE << /BaseVersion /1.7 /ExtensionLevel 8 >> /ESIC 8 0 R >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /BaseVersion /2.0 /ExtensionLevel 32000 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/versioned.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/versioned.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedExtensions = [
            [
                'prefix' => 'ADBE',
                'baseVersion' => '1.7',
                'extensionLevel' => 8,
            ],
            [
                'prefix' => 'ESIC',
                'baseVersion' => '2.0',
                'extensionLevel' => 32000,
            ],
        ];

        $t->same(true, $result['ok']);
        $t->same('1.5', $result['pdfHeaderVersion']);
        $t->same('1.7', $result['pdfCatalogVersion']);
        $t->same('1.7', $result['pdfEffectiveVersion']);
        $t->same($expectedExtensions, $result['pdfExtensionMetadata']);
        $t->contains('pdf-byte-header-version:1.5', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-catalog-version:1.7', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-effective-version:1.7', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-extension-metadata:2', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same('1.5', $sequence['finalPdfHeaderVersion']);
        $t->same('1.7', $sequence['finalPdfCatalogVersion']);
        $t->same('1.7', $sequence['finalPdfEffectiveVersion']);
        $t->same($expectedExtensions, $sequence['finalPdfExtensionMetadata']);
    },

    'fake runner extracts bounded pdf linearization dictionary metadata' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/linearized.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '% linearized PDFs may include a binary comment before the first object',
            '42 0 obj',
            '<< /Linearized 1.0 /L 9999 /H [128 256 512 64] /O 7 /E 4096 /N 3 /T 8192 >>',
            'endobj',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 3 /Kids [3 0 R 4 0 R 5 0 R] >>',
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
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/linearized.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/linearized.pdf' => $pdfBytes,
                ],
            ],
        ]);

        $expectedLinearization = [
            'object' => '42 0 R',
            'linearizedVersion' => 1.0,
            'fileLength' => 9999,
            'primaryHintOffset' => 128,
            'primaryHintLength' => 256,
            'firstPageObject' => 7,
            'firstPageEndOffset' => 4096,
            'pageCount' => 3,
            'mainXrefOffset' => 8192,
            'hintTables' => [
                ['offset' => 128, 'length' => 256],
                ['offset' => 512, 'length' => 64],
            ],
            'lengthMatches' => false,
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expectedLinearization, $result['pdfLinearization']);
        $t->contains('pdf-byte-linearized', $diagnostics);
        $t->contains('pdf-byte-linearized-version:1', $diagnostics);
        $t->contains('pdf-byte-linearized-page-count:3', $diagnostics);
        $t->contains('pdf-byte-linearized-hint-tables:2', $diagnostics);
        $t->contains('pdf-byte-linearized-length-mismatch:9999:' . strlen($pdfBytes), $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expectedLinearization, $sequence['finalPdfLinearization']);
        $t->same(3, $sequence['finalPdfLinearization']['pageCount']);
    },

    'fake runner extracts bounded pdf web capture spiderinfo metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/web-capture.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /SpiderInfo 5 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /SpiderInfo << /V 1.0 /C [8 0 R] >> >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /V 1.2 /C [6 0 R << /URL (https://example.test/review/checklist.html) /Title (Review checklist) /CT /Capture /ID (cap-inline) /TS (D:20260608010245Z) /L 2 /F 5 /Pages [3 0 R 4 0 R] >>] >>',
            'endobj',
            '6 0 obj',
            '<< /URL (https://example.test/review/) /Title (Review source home) /S /Complete /L 0 /F 1 /Page 3 0 R /Next 7 0 R >>',
            'endobj',
            '7 0 obj',
            '<< /URL (https://example.test/review/next.html) >>',
            'endobj',
            '8 0 obj',
            '<< /URL (https://example.test/review/page-1.html) /T (Captured page one) /N (page-source) /L 1 /F 2 /Page 3 0 R /P 6 0 R >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/web-capture.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/web-capture.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            [
                'source' => 'catalog.SpiderInfo',
                'page' => null,
                'pageObject' => null,
                'spiderInfoObject' => '5 0 R',
                'version' => 1.2,
                'commandCount' => 2,
                'sourceUrls' => [
                    'https://example.test/review/',
                    'https://example.test/review/checklist.html',
                ],
                'captures' => [
                    [
                        'commandObject' => '6 0 R',
                        'sourceUrl' => 'https://example.test/review/',
                        'sourceTitle' => 'Review source home',
                        'commandName' => null,
                        'commandType' => 'Complete',
                        'identifier' => null,
                        'timestamp' => null,
                        'flags' => 1,
                        'depth' => 0,
                        'pageReferences' => ['3 0 R'],
                        'parentCommand' => null,
                        'nextCommand' => '7 0 R',
                    ],
                    [
                        'commandObject' => 'inline',
                        'sourceUrl' => 'https://example.test/review/checklist.html',
                        'sourceTitle' => 'Review checklist',
                        'commandName' => null,
                        'commandType' => 'Capture',
                        'identifier' => 'cap-inline',
                        'timestamp' => 'D:20260608010245Z',
                        'flags' => 5,
                        'depth' => 2,
                        'pageReferences' => ['3 0 R', '4 0 R'],
                        'parentCommand' => null,
                        'nextCommand' => null,
                    ],
                ],
            ],
            [
                'source' => 'page:3 0 R.SpiderInfo',
                'page' => 1,
                'pageObject' => '3 0 R',
                'spiderInfoObject' => 'inline',
                'version' => 1.0,
                'commandCount' => 1,
                'sourceUrls' => ['https://example.test/review/page-1.html'],
                'captures' => [
                    [
                        'commandObject' => '8 0 R',
                        'sourceUrl' => 'https://example.test/review/page-1.html',
                        'sourceTitle' => 'Captured page one',
                        'commandName' => 'page-source',
                        'commandType' => null,
                        'identifier' => null,
                        'timestamp' => null,
                        'flags' => 2,
                        'depth' => 1,
                        'pageReferences' => ['3 0 R'],
                        'parentCommand' => '6 0 R',
                        'nextCommand' => null,
                    ],
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfWebCaptureMetadata']);
        $t->contains('pdf-byte-web-capture:2', $diagnostics);
        $t->contains('pdf-byte-web-capture-commands:3', $diagnostics);
        $t->contains('pdf-byte-web-capture-pages:1', $diagnostics);
        $t->contains('pdf-byte-web-capture-urls:3', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfWebCaptureMetadata']);
        $t->same('https://example.test/review/page-1.html', $sequence['finalPdfWebCaptureMetadata'][1]['sourceUrls'][0]);
    },

    'fake runner extracts pdf legal attestation metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['outputPath' => 'packets/legal-attestation.pdf']);
        $attestationBytes = 'Reviewer attests that the WordPress handoff packet preserves legal notices.';
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /LegalAttestation 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /LegalAttestation /Lang (en-US) /Statement 9 0 R /Status /Accepted /Jurisdiction (US) /AF [10 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Length ' . strlen($attestationBytes) . ' >>',
            'stream',
            $attestationBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /Filespec /F (legal-review.txt) /Desc (Legal review note) /AFRelationship /Supplement /EF << /F 11 0 R >> >>',
            'endobj',
            '11 0 obj',
            '<< /Length 0 >>',
            'stream',
            '',
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '321',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/legal-attestation.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/legal-attestation.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'object' => '8 0 R',
            'type' => 'LegalAttestation',
            'language' => 'en-US',
            'status' => 'Accepted',
            'jurisdiction' => 'US',
            'attestation' => null,
            'attestationObject' => '9 0 R',
            'attestationBytes' => strlen($attestationBytes),
            'attestationSha256' => hash('sha256', $attestationBytes),
            'attestationSkipped' => null,
            'associatedFiles' => ['10 0 R'],
            'keys' => ['AF', 'Jurisdiction', 'Lang', 'Statement', 'Status', 'Type'],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfLegalAttestationMetadata']);
        $t->contains('pdf-byte-legal-attestation', $diagnostics);
        $t->contains('pdf-byte-legal-attestation-status:Accepted', $diagnostics);
        $t->contains('pdf-byte-legal-attestation-stream-bytes:' . strlen($attestationBytes), $diagnostics);
        $t->contains('pdf-byte-legal-attestation-associated-files:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfLegalAttestationMetadata']);
    },

    'fake runner extracts pdf document security store metadata from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['outputPath' => 'packets/signed-review.pdf']);
        $certBytes = "reviewer certificate bytes\n";
        $ocspBytes = "reviewer ocsp response bytes\n";
        $crlBytes = "reviewer issuer crl bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /DSS 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '8 0 obj',
            '<< /Type /DSS /Certs [9 0 R] /OCSPs [10 0 R] /CRLs [11 0 R] /VRI << /AABBCCDDEEFF << /Type /VRI /Cert [9 0 R] /OCSP [10 0 R] /CRL [11 0 R] /TU (D:20260608173000Z) >> >> >>',
            'endobj',
            '9 0 obj',
            '<< /Length ' . strlen($certBytes) . ' >>',
            'stream',
            $certBytes,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Length ' . strlen($ocspBytes) . ' >>',
            'stream',
            $ocspBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Length ' . strlen($crlBytes) . ' >>',
            'stream',
            $crlBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '2048',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/signed-review.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/signed-review.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'object' => '8 0 R',
            'certs' => [
                [
                    'object' => '9 0 R',
                    'bytes' => strlen($certBytes),
                    'sha256' => hash('sha256', $certBytes),
                    'skipped' => null,
                ],
            ],
            'ocsp' => [
                [
                    'object' => '10 0 R',
                    'bytes' => strlen($ocspBytes),
                    'sha256' => hash('sha256', $ocspBytes),
                    'skipped' => null,
                ],
            ],
            'crls' => [
                [
                    'object' => '11 0 R',
                    'bytes' => strlen($crlBytes),
                    'sha256' => hash('sha256', $crlBytes),
                    'skipped' => null,
                ],
            ],
            'vri' => [
                [
                    'name' => 'AABBCCDDEEFF',
                    'object' => 'inline',
                    'type' => 'VRI',
                    'timestamp' => 'D:20260608173000Z',
                    'certs' => ['9 0 R'],
                    'ocsp' => ['10 0 R'],
                    'crls' => ['11 0 R'],
                    'keys' => ['CRL', 'Cert', 'OCSP', 'TU', 'Type'],
                ],
            ],
            'keys' => ['CRLs', 'Certs', 'OCSPs', 'Type', 'VRI'],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfDocumentSecurityStore']);
        $t->contains('pdf-byte-dss', $diagnostics);
        $t->contains('pdf-byte-dss-certs:1', $diagnostics);
        $t->contains('pdf-byte-dss-ocsp:1', $diagnostics);
        $t->contains('pdf-byte-dss-crls:1', $diagnostics);
        $t->contains('pdf-byte-dss-vri:1', $diagnostics);
        $t->contains('pdf-byte-dss-streams:3', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfDocumentSecurityStore']);
    },

    'fake runner summarizes pdf dss vri reference consistency policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['outputPath' => 'packets/dss-vri-policy.pdf']);
        $signatureBytes = hex2bin('3082010A0282010100AABBCC') ?: '';
        $signatureHex = strtoupper(bin2hex($signatureBytes));
        $signatureDigestName = strtoupper(hash('sha256', $signatureBytes));
        $certBytes = "reviewer certificate bytes\n";
        $ocspBytes = "reviewer ocsp response bytes\n";
        $crlBytes = "reviewer issuer crl bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 7 0 R /DSS 8 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Annot /Subtype /Widget /FT /Sig /T (review.signature) /V 9 0 R >>',
            'endobj',
            '7 0 obj',
            '<< /Fields [4 0 R] /SigFlags 3 >>',
            'endobj',
            '8 0 obj',
            '<< /Type /DSS /Certs [10 0 R] /OCSPs [11 0 R] /CRLs [12 0 R] /VRI << /' . $signatureDigestName . ' << /Type /VRI /Cert [10 0 R] /OCSP [11 0 R] /CRL [12 0 R] /TU (D:20260608173000Z) >> /ZZZZUNMATCHED << /Type /VRI /Cert [42 0 R] /OCSP [11 0 R] /CRL [43 0 R] >> >> >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /ByteRange [0 120 180 60] /Contents <' . $signatureHex . '> >>',
            'endobj',
            '10 0 obj',
            '<< /Length ' . strlen($certBytes) . ' >>',
            'stream',
            $certBytes,
            'endstream',
            'endobj',
            '11 0 obj',
            '<< /Length ' . strlen($ocspBytes) . ' >>',
            'stream',
            $ocspBytes,
            'endstream',
            'endobj',
            '12 0 obj',
            '<< /Length ' . strlen($crlBytes) . ' >>',
            'stream',
            $crlBytes,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            'startxref',
            '2048',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/dss-vri-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/dss-vri-policy.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'object' => '8 0 R',
            'reviewStatus' => 'review',
            'certStoreCount' => 1,
            'ocspStoreCount' => 1,
            'crlStoreCount' => 1,
            'vriCount' => 2,
            'signatureDigestCount' => 1,
            'matchedVriCount' => 1,
            'certObjects' => ['10 0 R'],
            'ocspObjects' => ['11 0 R'],
            'crlObjects' => ['12 0 R'],
            'issues' => [
                'vri-cert-not-in-dss-certs',
                'vri-crl-not-in-dss-crls',
                'vri-name-not-matched-to-signature-contents',
            ],
            'vri' => [
                [
                    'name' => $signatureDigestName,
                    'matchedSignatureObject' => '9 0 R',
                    'matchedFieldName' => 'review.signature',
                    'certs' => ['10 0 R'],
                    'ocsp' => ['11 0 R'],
                    'crls' => ['12 0 R'],
                    'missingCerts' => [],
                    'missingOcsp' => [],
                    'missingCrls' => [],
                    'reviewStatus' => 'ok',
                    'issues' => [],
                ],
                [
                    'name' => 'ZZZZUNMATCHED',
                    'matchedSignatureObject' => null,
                    'matchedFieldName' => null,
                    'certs' => ['42 0 R'],
                    'ocsp' => ['11 0 R'],
                    'crls' => ['43 0 R'],
                    'missingCerts' => ['42 0 R'],
                    'missingOcsp' => [],
                    'missingCrls' => ['43 0 R'],
                    'reviewStatus' => 'review',
                    'issues' => [
                        'vri-name-not-matched-to-signature-contents',
                        'vri-cert-not-in-dss-certs',
                        'vri-crl-not-in-dss-crls',
                    ],
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expected, $result['pdfDocumentSecurityStorePolicy']);
        $t->contains('pdf-byte-dss-policy:review', $diagnostics);
        $t->contains('pdf-byte-dss-policy-vri:2', $diagnostics);
        $t->contains('pdf-byte-dss-policy-vri-matched:1', $diagnostics);
        $t->contains('pdf-byte-dss-policy-vri-status:ok:1', $diagnostics);
        $t->contains('pdf-byte-dss-policy-vri-status:review:1', $diagnostics);
        $t->contains('pdf-byte-dss-policy-issue:vri-name-not-matched-to-signature-contents:1', $diagnostics);
        $t->contains('pdf-byte-dss-policy-issue:vri-cert-not-in-dss-certs:1', $diagnostics);
        $t->contains('pdf-byte-dss-policy-issue:vri-crl-not-in-dss-crls:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expected, $sequence['finalPdfDocumentSecurityStorePolicy']);
    },

    'fake runner summarizes pdfa and pdfua conformance review policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['outputPath' => 'packets/claimed-conformance.pdf']);
        $xmp = implode("\n", [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '<rdf:Description xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/">',
            '<pdfaid:part>2</pdfaid:part>',
            '<pdfaid:conformance>B</pdfaid:conformance>',
            '<pdfuaid:part>1</pdfuaid:part>',
            '</rdf:Description>',
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '5 0 obj',
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . ' >>',
            'stream',
            $xmp,
            'endstream',
            'endobj',
            '6 0 obj',
            '<< /Filter /Standard /V 4 /R 4 /Length 128 /P -44 >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Encrypt 6 0 R >>',
            'startxref',
            '2048',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/claimed-conformance.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [
            [
                'files' => [
                    'packets/claimed-conformance.pdf' => $pdfBytes,
                ],
            ],
        ]);
        $expected = [
            'reviewStatus' => 'review',
            'pdfaClaimed' => true,
            'pdfaPart' => '2',
            'pdfaConformance' => 'B',
            'pdfuaClaimed' => true,
            'pdfuaPart' => '1',
            'pdfuaAmendment' => null,
            'pdfuaCorrigendum' => null,
            'encrypted' => true,
            'language' => null,
            'tagged' => null,
            'structTreeRoot' => null,
            'outputIntentCount' => 0,
            'issues' => [
                'pdfa-output-encrypted',
                'pdfa-missing-output-intent',
                'pdfua-not-marked',
                'pdfua-missing-structure-tree',
                'pdfua-missing-language',
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(false, $result['ok']);
        $t->same('pdf-output-encrypted', $result['reason']);
        $t->same($expected, $result['pdfConformancePolicy']);
        $t->contains('pdf-byte-pdfa:2:B', $diagnostics);
        $t->contains('pdf-byte-pdfua:1', $diagnostics);
        $t->contains('pdf-byte-conformance-policy:review', $diagnostics);
        $t->contains('pdf-byte-conformance-policy-pdfa:2:B', $diagnostics);
        $t->contains('pdf-byte-conformance-policy-pdfua:1', $diagnostics);
        $t->contains('pdf-byte-conformance-policy-issues:5', $diagnostics);
        $t->contains('pdf-byte-conformance-policy-issue:pdfa-output-encrypted:1', $diagnostics);
        $t->contains('pdf-byte-conformance-policy-issue:pdfua-missing-structure-tree:1', $diagnostics);
        $t->same(false, $sequence['ok']);
        $t->same('attempt-1-pdf-output-encrypted', $sequence['reason']);
        $t->same($expected, $sequence['finalPdfConformancePolicy']);
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
