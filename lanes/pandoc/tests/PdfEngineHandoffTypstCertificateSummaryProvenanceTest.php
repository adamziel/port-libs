<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst certificate summary packet keeps CLI and environment certificate boundaries reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Certificate Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst certificate summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstCertificateSummaryProvenanceCases'] ?? null);
        $t->same(49, $manifest['typstCertificateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstCertificateSummaryProvenanceCases'] ?? null);
        $t->same(49, $manifest['benchmarkDenominator']['breakdown']['typstCertificateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstCertificateSummaryProvenanceCases'] ?? null);
        $t->same(49, $manifest['benchmarkDenominator']['inventory']['typstCertificateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstCertificateSummaryProvenanceCases'] ?? null);
        $t->same(49, $manifest['inventory']['typstCertificateSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst certificate boundary policy without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/certificate-summary.pdf',
            'source' => '= Typst Certificate Summary',
            'engineOptions' => [
                '--cert=certs/cli-ca.pem',
                '--cert=https://ca.example.invalid/root.pem',
                '--cert',
            ],
            'engineEnvironment' => [
                'TYPST_CERT' => 'https://env-ca.example.invalid/root.pem',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst certificate summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/certificate-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/certificate-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $certificateDetails = $cases['certificate-paths']['details'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(4, $summary['pathEntryCount']);
        $t->same(3, $summary['unsafePathEntryCount']);
        $t->same(3, $summary['certificateCount']);
        $t->same(4, $summary['certificateBoundaryEntryCount']);
        $t->same(1, $summary['safeCertificateCount']);
        $t->same(3, $summary['unsafeCertificateCount']);
        $t->same(1, $summary['relativeCertificateCount']);
        $t->same(0, $summary['workspaceCertificateCount']);
        $t->same(0, $summary['absoluteCertificateCount']);
        $t->same(2, $summary['uriCertificateCount']);
        $t->same(1, $summary['invalidCertificateCount']);
        $t->same(3, $summary['cliCertificateCount']);
        $t->same(1, $summary['environmentCertificateCount']);
        $t->same(1, $summary['certificateEnvironmentVariableCount']);
        $t->same(['TYPST_CERT'], $summary['certificateEnvironmentVariables']);
        $t->same(true, $summary['certificateEnvironmentPresent']);
        $t->same(true, $summary['certificateEnvironmentShadowed']);
        $t->same(3, $summary['certificateIssueCount']);
        $t->same([
            'certificate-empty',
            'certificate-environment-shadowed',
            'certificate-external-boundary',
        ], $summary['certificateIssues']);
        $t->contains('typst-boundary-summary-certificates:4', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-unsafe-certificates:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-certificate-issues:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-certificate-environment-shadowed', implode(',', $plan['diagnostics']));
        $t->contains('typst-certificate-unsafe:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-issues:3', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->same(4, $cases['certificate-paths']['observed']);
        $t->same($summary['safeCertificateCount'], $certificateDetails['safeCertificateCount']);
        $t->same($summary['unsafeCertificateCount'], $certificateDetails['unsafeCertificateCount']);
        $t->same($summary['uriCertificateCount'], $certificateDetails['uriCertificateCount']);
        $t->same($summary['environmentCertificateCount'], $certificateDetails['environmentCertificateCount']);
        $t->same($summary['certificateEnvironmentVariables'], $certificateDetails['environmentVariables']);
        $t->contains('certificate-paths:certificate-empty', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('certificate-paths:certificate-environment-shadowed', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
