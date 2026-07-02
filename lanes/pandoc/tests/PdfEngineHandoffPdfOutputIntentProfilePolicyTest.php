<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('PDF output intent profiles remain metadata-only at the engine boundary.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF Output Intent Profile Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped pdf output intent profile policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfOutputIntentProfilePolicyCases'] ?? null);
        $t->same(32, $manifest['pdfOutputIntentProfilePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfOutputIntentProfilePolicyCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['breakdown']['pdfOutputIntentProfilePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfOutputIntentProfilePolicyCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['inventory']['pdfOutputIntentProfilePolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfOutputIntentProfilePolicyCases'] ?? null);
        $t->same(32, $manifest['inventory']['pdfOutputIntentProfilePolicyAssertions'] ?? null);
    },

    'summarizes pdf output intent profile policy from produced bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/output-profile-policy.pdf']);
        $documentProfile = "fake sRGB output profile bytes\n";
        $filteredProfile = "fake filtered CMYK profile bytes\n";
        $pdfBytes = implode("\n", [
            '%PDF-2.0',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OutputIntents [8 0 R] >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /OutputIntents [10 0 R << /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Page sRGB review) >>] >>',
            'endobj',
            '8 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /RegistryName (http://www.color.org) /Info (Document output profile) /DestOutputProfile 9 0 R >>',
            'endobj',
            '9 0 obj',
            '<< /N 3 /Alternate /DeviceRGB /Length ' . strlen($documentProfile) . ' >>',
            'stream',
            $documentProfile,
            'endstream',
            'endobj',
            '10 0 obj',
            '<< /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (FOGRA39) /RegistryName (https://www.color.org) /Info (Filtered proof profile) /DestOutputProfile 11 0 R >>',
            'endobj',
            '11 0 obj',
            '<< /N 4 /Alternate /DeviceCMYK /Filter /FlateDecode /Length ' . strlen($filteredProfile) . ' >>',
            'stream',
            $filteredProfile,
            'endstream',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/output-profile-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'packets/output-profile-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'intentCount' => 3,
            'pdfaIntentCount' => 2,
            'pdfxIntentCount' => 1,
            'profileIntentCount' => 2,
            'documentProfileIntentCount' => 1,
            'pageProfileIntentCount' => 1,
            'profileHashCount' => 1,
            'profileSkippedCount' => 1,
            'profileByteCount' => strlen($documentProfile) + strlen($filteredProfile),
            'profileComponentCounts' => [
                3 => 1,
                4 => 1,
            ],
            'profileAlternateCounts' => [
                'DeviceCMYK' => 1,
                'DeviceRGB' => 1,
            ],
            'profileSkippedReasons' => [
                'filtered' => 1,
            ],
            'issues' => [
                'output-profile-stream-skipped',
                'pdfa-output-intent-missing-dest-output-profile',
            ],
            'profiles' => [
                [
                    'scope' => 'document',
                    'page' => null,
                    'source' => 'catalog.OutputIntents[0]',
                    'subtype' => 'GTS_PDFA1',
                    'destOutputProfile' => '9 0 R',
                    'profileComponents' => 3,
                    'profileAlternate' => 'DeviceRGB',
                    'profileBytes' => strlen($documentProfile),
                    'profileSha256' => hash('sha256', $documentProfile),
                    'profileSkipped' => null,
                ],
                [
                    'scope' => 'page',
                    'page' => 1,
                    'source' => 'page:3 0 R.OutputIntents',
                    'subtype' => 'GTS_PDFX',
                    'destOutputProfile' => '11 0 R',
                    'profileComponents' => 4,
                    'profileAlternate' => 'DeviceCMYK',
                    'profileBytes' => strlen($filteredProfile),
                    'profileSha256' => null,
                    'profileSkipped' => 'filtered',
                ],
            ],
        ];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same(1, count($result['pdfOutputIntents']));
        $t->same(2, count($result['pdfPageOutputIntents']));
        $t->same($expectedPolicy, $result['pdfOutputIntentProfilePolicy']);
        $t->contains('pdf-byte-output-profile-policy:review', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-intents:3', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-pdfa-intents:2', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-pdfx-intents:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-profiles:2', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-document-profiles:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-page-profiles:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-hashed-profiles:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-skipped-profiles:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-profile-bytes:' . (strlen($documentProfile) + strlen($filteredProfile)), $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-component:3:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-component:4:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-alternate:DeviceCMYK:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-alternate:DeviceRGB:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-skipped:filtered:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-issues:2', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-issue:output-profile-stream-skipped:1', $diagnostics);
        $t->contains('pdf-byte-output-profile-policy-issue:pdfa-output-intent-missing-dest-output-profile:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfOutputIntentProfilePolicy']);
    },
];
