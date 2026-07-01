<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx main document package identity for reviewer handoff' => static function (TestRunner $t): void {
        $cases = [
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'document',
                false,
                false,
                [],
            ],
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml; profile=review-template',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml',
                'template',
                true,
                false,
                [],
            ],
            [
                'application/vnd.ms-word.document.macroEnabled.main+xml',
                'application/vnd.ms-word.document.macroenabled.main+xml',
                'macro-enabled-document',
                false,
                true,
                [],
            ],
            [
                'application/vnd.ms-word.template.macroEnabledTemplate.main+xml; profile=macro-template',
                'application/vnd.ms-word.template.macroenabledtemplate.main+xml',
                'macro-enabled-template',
                true,
                true,
                [],
            ],
            [
                'application/vnd.example.review+xml; profile=wrong-main-document',
                'application/vnd.example.review+xml',
                'unknown',
                false,
                false,
                ['unexpected-main-document-content-type'],
            ],
        ];

        foreach ($cases as [$contentType, $contentTypeBase, $formatKind, $template, $macroEnabled, $issueCodes]) {
            $document = (new DocxOpenXmlReader())->readPackage(docx_package_identity_fixture_parts($contentType));
            $docx = $document->attr('docx');
            $package = $docx['packageProvenance'];
            $identity = $docx['documentPackageIdentity'];
            $summary = $package['summary'];

            $t->same($identity, $package['documentPackageIdentity']);
            $t->same('docx-main-document-package-identity', $identity['reviewPolicy']);
            $t->same('word/document.xml', $identity['partName']);
            $t->same($contentType, $identity['contentType']);
            $t->same($contentTypeBase, $identity['contentTypeBase']);
            $t->same('override', $identity['contentTypeSource']);
            $t->same($formatKind, $identity['formatKind']);
            $t->same($template, $identity['template']);
            $t->same($macroEnabled, $identity['macroEnabled']);
            $t->same($issueCodes === [], $identity['validContentType']);
            $t->same(count($issueCodes), $identity['issueCount']);
            $t->same($issueCodes, $identity['issueCodes']);
            $t->same(str_contains($contentType, ';'), $identity['contentTypeHasParameters']);

            $t->same($identity['reviewPolicy'], $summary['documentPackageIdentityReviewPolicy']);
            $t->same($identity['contentType'], $summary['documentContentType']);
            $t->same($identity['contentTypeBase'], $summary['documentContentTypeBase']);
            $t->same($identity['contentTypeSource'], $summary['documentContentTypeSource']);
            $t->same($identity['contentTypeHasParameters'], $summary['documentContentTypeHasParameters']);
            $t->same($identity['contentTypeParameterCount'], $summary['documentContentTypeParameterCount']);
            $t->same($identity['contentTypeParameterMap'], $summary['documentContentTypeParameterMap']);
            $t->same($identity['formatKind'], $summary['documentFormatKind']);
            $t->same($identity['template'], $summary['documentTemplate']);
            $t->same($identity['macroEnabled'], $summary['documentMacroEnabled']);
            $t->same($identity['validContentType'], $summary['documentContentTypeValid']);
            $t->same($identity['issueCount'], $summary['documentContentTypeIssueCount']);
            $t->same($identity['issueCodes'], $summary['documentContentTypeIssueCodes']);
        }
    },
];

/**
 * @return array<string, string>
 */
function docx_package_identity_fixture_parts(string $documentContentType): array
{
    return [
        '[Content_Types].xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="{$documentContentType}"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package identity fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
    ];
}
