<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
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
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['variables' => ['bad variable' => 'value']]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['variables' => ['mainfont' => "bad\0font"]]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan(new AstNode('paragraph')));
    },
];
