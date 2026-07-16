<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxParityCorpusAudit;
use PortLibs\Pandoc\DocxWriter;
use PortLibs\Pandoc\DocxWriterGoldenManifest;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\ZipPackage;

$makeTempRoot = static function (): string {
    $root = sys_get_temp_dir() . '/pandoc-docx-parity-audit-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temporary audit root');
    }

    return $root;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory');
    }

    file_put_contents($path, $contents);
};

$minimalDocxDocumentXml = static function (string $text): string {
    $xmlText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>' . $xmlText . '</w:t></w:r></w:p></w:body></w:document>';
};

$minimalDocx = static function (string $text) use ($minimalDocxDocumentXml): string {
    return ZipPackage::build([
        [
            'name' => 'word/document.xml',
            'data' => $minimalDocxDocumentXml($text),
        ],
    ]);
};

$featureDocx = static function (bool $withTargetFeatures): string {
    $document = $withTargetFeatures
        ? <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <w:body>
    <w:sdt><w:sdtPr/><w:sdtContent><w:p><w:pPr><w:pStyle w:val="TableCaption"/></w:pPr><w:r><w:t>Caption</w:t></w:r></w:p></w:sdtContent></w:sdt>
    <w:tbl><w:tblPr><w:tblCaption w:val="Data table"/></w:tblPr><w:tblGrid/><w:tr><w:tc><w:p><w:r><w:drawing><wp:inline><a:graphic><pic:pic/><a:blip r:embed="rId9"/></a:graphic></wp:inline></w:drawing></w:r></w:p></w:tc></w:tr></w:tbl>
    <w:p><w:r><w:pict><v:shape><v:textbox><w:txbxContent><w:p><w:r><w:t>Box</w:t></w:r></w:p></w:txbxContent></v:textbox><v:imagedata r:id="rId10"/></v:shape></w:pict></w:r><w:r><wps:wsp><wps:txbx/></wps:wsp></w:r></w:p>
  </w:body>
</w:document>
XML
        : <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Plain</w:t></w:r></w:p></w:body></w:document>
XML;

    return ZipPackage::build([
        [
            'name' => 'word/document.xml',
            'data' => $document,
        ],
    ]);
};

$semanticDocx = static function (string $text, bool $variant = false): string {
    $xmlText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $contentTypes = $variant
        ? <<<'XML'
<?xml version="1.0"?>
<ct:Types xmlns:ct="http://schemas.openxmlformats.org/package/2006/content-types">
  <ct:Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml" PartName="/word/document.xml"/>
  <ct:Default ContentType="image/png" Extension="png"/>
  <ct:Default ContentType="application/vnd.openxmlformats-package.relationships+xml" Extension="rels"/>
  <ct:Default ContentType="application/xml" Extension="xml"/>
</ct:Types>
XML
        : <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>
XML;
    $rootRelationships = $variant
        ? <<<'XML'
<?xml version="1.0"?>
<rel:Relationships xmlns:rel="http://schemas.openxmlformats.org/package/2006/relationships">
  <rel:Relationship Target="word/document.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Id="rDoc"/>
</rel:Relationships>
XML
        : <<<'XML'
<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/word/document.xml"/></Relationships>
XML;
    $documentRelationships = $variant
        ? <<<'XML'
<?xml version="1.0"?>
<pr:Relationships xmlns:pr="http://schemas.openxmlformats.org/package/2006/relationships">
  <pr:Relationship Target="media/image1.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Id="rImage"/>
  <pr:Relationship TargetMode="External" Target="https://example.test/review" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Id="rLink"/>
</pr:Relationships>
XML
        : <<<'XML'
<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/><Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>
XML;
    $document = $variant
        ? '<?xml version="1.0"?><x:document xmlns:x="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:rel="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><x:body>' . "\n" . '  <x:p><x:hyperlink rel:id="rLink"><x:r><x:t>' . $xmlText . '</x:t></x:r></x:hyperlink></x:p>' . "\n" . '</x:body></x:document>'
        : '<?xml version="1.0"?><w:document xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:hyperlink r:id="rLink"><w:r><w:t>' . $xmlText . '</w:t></w:r></w:hyperlink></w:p></w:body></w:document>';

    $parts = [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes],
        ['name' => '_rels/.rels', 'data' => $rootRelationships],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationships],
        ['name' => 'word/document.xml', 'data' => $document],
        ['name' => 'word/media/image1.png', 'data' => "png-bytes\n"],
    ];
    if ($variant) {
        $parts = array_reverse($parts);
    }

    return ZipPackage::build($parts, $variant ? 'generated comment ignored by semantic comparison' : '');
};

$diagnosticDocx = static function (bool $generated = false): string {
    $contentTypes = $generated
        ? <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/></Types>
XML
        : <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>
XML;
    $documentRelationships = $generated
        ? <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/></Relationships>
XML
        : <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>
XML;
    $documentText = $generated ? 'Generated diagnostic' : 'Golden diagnostic';
    $parts = [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes],
        ['name' => '_rels/.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>'],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationships],
        ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>' . $documentText . '</w:t></w:r></w:p></w:body></w:document>'],
        ['name' => $generated ? 'word/numbering.xml' : 'word/styles.xml', 'data' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ];

    return ZipPackage::build($parts);
};

$writerDocxFromNative = static function (string $native): string {
    $document = (new NativeReader())->read($native);

    return (new DocxWriter())->write($document);
};

$findRowByGoldenFile = static function (array $rows, string $goldenFile): array {
    foreach ($rows as $row) {
        if (is_array($row) && ($row['goldenFile'] ?? null) === $goldenFile) {
            return $row;
        }
    }

    throw new RuntimeException("Missing generation row for {$goldenFile}");
};

return [
    'skips cleanly when local upstream docx cache is absent' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $root = $makeTempRoot();
        try {
            $report = (new DocxParityCorpusAudit($root))->report();
            $text = DocxParityCorpusAudit::formatTextReport($report);

            $t->same(DocxParityCorpusAudit::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('parser-acceptance-only', $report['evidenceKind']);
            $t->true(in_array('local PHP DocxReader parser acceptance for audited .docx fixtures', $report['verificationScope']['asserts'], true));
            $t->true(in_array('local DOCX output registry status and expected DocxWriter class/file presence', $report['verificationScope']['asserts'], true));
            $t->true(in_array('full DOCX/OpenXML semantic parity', $report['verificationScope']['doesNotAssert'], true));
            $t->true(in_array('DOCX writer support merely because upstream golden packages are inventoried', $report['verificationScope']['doesNotAssert'], true));
            $t->same(false, $report['sourceDirectoryPresent']);
            $t->same(DocxWriterGoldenManifest::EVIDENCE_KIND, $report['writerGoldenEvidenceKind']);
            $t->same(DocxWriterGoldenManifest::COMPARISON_NOT_RECORDED_REASON, $report['docxWriterUnsupportedReason']);
            $t->same(false, $report['writerGoldenPackageComparisonRun']);
            $t->same(DocxWriterGoldenManifest::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY, $report['writerGoldenEvidence']['status']);
            $t->same('implementation-present-or-registered', $report['writerGoldenEvidence']['localWriter']['status']);
            $t->same(true, $report['writerGoldenEvidence']['localWriter']['classExists']);
            $t->same(false, $report['writerGoldenEvidence']['localWriter']['fileExists']);
            $t->same('partial', $report['writerGoldenEvidence']['localWriter']['registryStatus']);
            $t->same(false, $report['writerGoldenEvidence']['packageComparison']['run']);
            $t->same(DocxWriterGoldenManifest::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON, $report['writerGoldenEvidence']['packageComparison']['reason']);
            $t->same(0, $report['auditedPairCount']);
            $t->same(0, $report['bothParsedCount']);
            $t->same(null, $report['bothParserCoveragePercent']);
            $t->same(DocxParityCorpusAudit::PARSER_ACCEPTANCE_BASELINE_NAME, $report['parserAcceptanceBaseline']['baselineName']);
            $t->same(74, $report['parserAcceptanceBaseline']['pairedDocxNativeArtifacts']);
            $plan = $report['upstreamDocxRunnerEvidencePlan'];
            $t->same(DocxParityCorpusAudit::DOCX_RUNNER_PLAN_STATUS, $plan['status']);
            $t->same('runner-entry-fixture-command-plan-only', $plan['evidenceKind']);
            $t->same(false, $plan['resultRecorded']);
            $t->same(false, $plan['runnerExecuted']);
            $t->same('test:test-pandoc', $plan['runnerTarget']);
            $t->same('test/test-pandoc.hs', $plan['runnerEntryPoint']['entryFile']);
            $t->same('test/Tests/Readers/Docx.hs', $plan['docxReaderEntryPoint']['sourceFile']);
            $t->same('Tests.Readers.Docx.tests', $plan['docxReaderEntryPoint']['entryPointSnippet']);
            $t->same('test/Tests/Writers/Docx.hs', $plan['docxWriterEntryPoint']['sourceFile']);
            $t->same('Tests.Writers.Docx.tests', $plan['docxWriterEntryPoint']['entryPointSnippet']);
            $t->same(['test/docx/*.docx', 'test/docx/*.native'], $plan['fixtureClosure']['readerFixtureGlobs']);
            $t->same(['test/docx/golden/*.docx'], $plan['fixtureClosure']['writerGoldenFixtureGlobs']);
            $t->same(233, $plan['fixtureClosure']['pinnedInventoryCounts']['docxDirectoryArtifacts']);
            $t->contains('cabal v2-build --offline --project-dir=.', $plan['nonMutatingDryRunPlanCommand']['commandLine']);
            $t->contains('--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run', $plan['nonMutatingDryRunPlanCommand']['commandLine']);
            $t->contains('--dry-run', $plan['nonMutatingDryRunPlanCommand']['commandLine']);
            $t->same('.port-libs/pandoc-runner/cabal-build/docx-targeted-run', $plan['nonMutatingDryRunPlanCommand']['buildDirectory']);
            $t->same('.port-libs/pandoc-runner/cabal-build/docx-targeted-run', $plan['nonMutatingDryRunPlanCommand']['workspaceBuildDirectory']);
            $t->same('.port-libs/pandoc-runner/logs/runner-test-dependencies.txt', $plan['nonMutatingDryRunPlanCommand']['transcriptFile']);
            $t->same('descriptor-only; do not execute from this isolated PHP lane', $plan['nonMutatingDryRunPlanCommand']['executionPolicy']);
            $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $plan['futureTargetedRunCommand']['arguments'][7]);
            $t->contains('not executed by this audit', $plan['futureTargetedRunCommand']['executionPolicy']);
            $t->same(false, $report['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $report['parserAcceptanceRegression']['regressed']);
            $t->same('not-evaluated-source-directory-unavailable', $report['parserAcceptanceRegression']['reason']);
            $t->same('upstream-docx-runner-results', $report['orderedRemainingGaps'][0]['id']);
            $t->contains('descriptor-only Cabal dry-run command', $report['orderedRemainingGaps'][0]['currentEvidence']);
            $t->same('docx-native-ast-equality', $report['orderedRemainingGaps'][1]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][3]['status']);
            $t->contains('Result: skipped', $text);
            $t->contains('DOCX writer implementation: implementation-present-or-registered', $text);
            $t->contains('registryStatus=partial', $text);
            $t->contains('DOCX writer golden package comparison: not run; reason=' . DocxWriterGoldenManifest::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON, $text);
            $t->contains('No DOCX parity is asserted.', $text);
            $t->contains('Upstream DOCX runner plan: open-no-targeted-runner-result; result recorded=no; runner executed=no', $text);
            $t->contains('DOCX runner entry points: reader test/Tests/Readers/Docx.hs -> Tests.Readers.Docx.tests; writer test/Tests/Writers/Docx.hs -> Tests.Writers.Docx.tests', $text);
            $t->contains('Ordered remaining full DOCX parity gaps:', $text);
            $t->contains('2. docx-native-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports paired upstream docx native parser coverage without asserting parity' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx, $minimalDocxDocumentXml): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/sample.docx", $minimalDocx('Hello audit'));
            $writeFile($root, "{$docxRoot}/sample.native", '[ Para [ Str "Hello" , Space , Str "audit" ] ]');
            $writeFile($root, "{$docxRoot}/broken.docx", 'not a zip package');
            $writeFile($root, "{$docxRoot}/broken.native", '[ Para [ Str "unterminated ]');
            $writeFile($root, "{$docxRoot}/orphan-docx.docx", $minimalDocx('Orphan DOCX'));
            $writeFile($root, "{$docxRoot}/orphan-native.native", '[ Para [ Str "Orphan" ] ]');
            $writeFile($root, "{$docxRoot}/golden/writer-output.docx", $minimalDocx('Writer inventory'));

            $report = (new DocxParityCorpusAudit($root))->report();
            $text = DocxParityCorpusAudit::formatTextReport($report);

            $t->same(DocxParityCorpusAudit::STATUS_REPORTED, $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(DocxParityCorpusAudit::VERDICT, $report['verdict']);
            $t->same('parser-acceptance-only', $report['evidenceKind']);
            $t->true(in_array('strict regression guard against the recorded 74/74 parser-acceptance baseline when the optional cache is present', $report['verificationScope']['asserts'], true));
            $t->true(in_array('static upstream DOCX reader/writer runner entry point, fixture closure, and descriptor-only dry-run command plan', $report['verificationScope']['asserts'], true));
            $t->true(in_array('DOCX writer golden package round-trip parity', $report['verificationScope']['doesNotAssert'], true));
            $t->true(in_array('execution of the future targeted DOCX Tasty runner command', $report['verificationScope']['doesNotAssert'], true));
            $t->same(6, $report['rootDirectoryArtifactCount']);
            $t->same(3, $report['rootDocxPackageArtifacts']);
            $t->same(3, $report['rootNativeExpectedArtifacts']);
            $t->same(1, $report['goldenDocxPackageArtifacts']);
            $t->same(DocxWriterGoldenManifest::EVIDENCE_KIND, $report['writerGoldenEvidenceKind']);
            $t->same(DocxWriterGoldenManifest::COMPARISON_NOT_RECORDED_REASON, $report['docxWriterUnsupportedReason']);
            $t->same(false, $report['writerGoldenPackageComparisonRun']);
            $t->same(DocxWriterGoldenManifest::STATUS_REPORTED, $report['writerGoldenEvidence']['status']);
            $t->same(1, $report['writerGoldenEvidence']['goldenPackageCount']);
            $t->same(1, $report['writerGoldenEvidence']['readableGoldenPackageCount']);
            $t->same(1, $report['writerGoldenEvidence']['packagePartCount']);
            $t->same(1, $report['writerGoldenEvidence']['readablePackagePartCount']);
            $t->same('implementation-present-or-registered', $report['writerGoldenEvidence']['localWriter']['status']);
            $t->same('partial', $report['writerGoldenEvidence']['localWriter']['registryStatus']);
            $t->same(true, $report['writerGoldenEvidence']['localWriter']['classExists']);
            $t->same(false, $report['writerGoldenEvidence']['localWriter']['fileExists']);
            $t->same(false, $report['writerGoldenEvidence']['packageComparison']['run']);
            $t->same('writer-output.docx', $report['writerGoldenEvidence']['packageRows'][0]['fileName']);
            $t->same('test/docx/golden/writer-output.docx', $report['writerGoldenEvidence']['packageRows'][0]['expectedUpstreamGoldenReference']);
            $t->same(['word/document.xml'], $report['writerGoldenEvidence']['packageRows'][0]['partNames']);
            $t->same('word/document.xml', $report['writerGoldenEvidence']['packageRows'][0]['partRows'][0]['name']);
            $t->same(hash('sha256', $minimalDocxDocumentXml('Writer inventory')), $report['writerGoldenEvidence']['packageRows'][0]['partRows'][0]['sha256']);
            $t->same(strlen($minimalDocxDocumentXml('Writer inventory')), $report['writerGoldenEvidence']['packageRows'][0]['partRows'][0]['uncompressedBytes']);
            $shape = $report['writerGoldenEvidence']['goldenPackageCommonShape'];
            $t->same(1, $shape['packageCount']);
            $t->same(1, $shape['readablePackageCount']);
            $t->same(['word/document.xml'], $shape['commonPartNames']);
            $t->same([], $shape['optionalPartNameRows']);
            $t->same(0, $shape['commonContentTypeRecordCount']);
            $t->same(0, $shape['commonRelationshipRecordCount']);
            $t->same('src/Text/Pandoc/Writers/Docx.hs', $report['writerGoldenEvidence']['expectedUpstreamWriterSourceReferences'][0]['path']);
            $t->same(2, $report['pairedDocxNativeArtifacts']);
            $t->same(1, $report['unpairedDocxPackageArtifacts']);
            $t->same(1, $report['unpairedNativeExpectedArtifacts']);
            $t->same(['orphan-docx'], $report['docxWithoutNativeSamples']);
            $t->same(['orphan-native'], $report['nativeWithoutDocxSamples']);
            $t->same(2, $report['auditedPairCount']);
            $t->same(2, $report['docxParsedCount']);
            $t->same(0, $report['docxFailedCount']);
            $t->same(1, $report['nativeParsedCount']);
            $t->same(1, $report['nativeFailedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['bothFailedOrPartialCount']);
            $t->same(50.0, $report['bothParserCoveragePercent']);
            $t->same('broken', $report['pairRows'][0]['name']);
            $t->same('parsed', $report['pairRows'][0]['docxParse']['status']);
            $t->same('failed', $report['pairRows'][0]['nativeParse']['status']);
            $t->same('sample', $report['pairRows'][1]['name']);
            $t->same('parsed', $report['pairRows'][1]['docxParse']['status']);
            $t->same('parsed', $report['pairRows'][1]['nativeParse']['status']);
            $t->same(1, count($report['failureRows']));
            $t->same(DocxParityCorpusAudit::PARSER_ACCEPTANCE_BASELINE_NAME, $report['parserAcceptanceBaseline']['baselineName']);
            $t->same(true, $report['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $report['parserAcceptanceRegression']['passed']);
            $t->same(true, $report['parserAcceptanceRegression']['regressed']);
            $t->true(in_array('paired-docx-native-artifact-count-below-baseline', $report['parserAcceptanceRegression']['failureReasons'], true));
            $t->true(in_array('native-parse-failures-present', $report['parserAcceptanceRegression']['failureReasons'], true));
            $t->true(DocxParityCorpusAudit::hasParserAcceptanceRegression($report));
            $t->same(['upstream-docx-runner-results', 'docx-native-ast-equality', 'writer-golden-docx-package-parity', 'parser-failure-zero-tolerance', 'checked-in-pinned-docx-package-corpus'], array_map(
                static fn (array $gap): string => (string) $gap['id'],
                $report['orderedRemainingGaps']
            ));
            $t->contains('local DOCX writer status=implementation-present-or-registered', $report['orderedRemainingGaps'][2]['currentEvidence']);
            $t->contains('docx output registry=partial', $report['orderedRemainingGaps'][2]['currentEvidence']);
            $t->contains('generated package comparison run=no', $report['orderedRemainingGaps'][2]['currentEvidence']);
            $t->contains(DocxWriterGoldenManifest::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON, $report['orderedRemainingGaps'][2]['currentEvidence']);
            $t->same('open', $report['orderedRemainingGaps'][3]['status']);
            $t->contains('docx failures=0; native failures=1; partial-or-failed pairs=1', $report['orderedRemainingGaps'][3]['currentEvidence']);
            $t->contains('DOCX writer implementation: implementation-present-or-registered', $text);
            $t->contains('Writer golden package parts inventoried: 1/1 hashed readable parts', $text);
            $t->contains('Both parsers accepted: 1/2 (50.00%)', $text);
            $t->contains('Parser acceptance regression guard: failed', $text);
            $t->contains('DOCX fixture closure: test/docx/*.docx, test/docx/*.native, test/docx/golden/*.docx', $text);
            $t->contains('Non-mutating Cabal dry-run plan command: cabal v2-build --offline --project-dir=.', $text);
            $t->contains('Ordered remaining full DOCX parity gaps:', $text);
            $t->contains('3. writer-golden-docx-package-parity [open]', $text);
            $t->contains('No AST equality, upstream Haskell runner, or DOCX writer golden package parity is asserted.', $text);
        } finally {
            $removeTree($root);
        }
    },

    'respects pair audit limits and emits cli json for absent cache' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/a.docx", $minimalDocx('A'));
            $writeFile($root, "{$docxRoot}/a.native", '[ Para [ Str "A" ] ]');
            $writeFile($root, "{$docxRoot}/b.docx", $minimalDocx('B'));
            $writeFile($root, "{$docxRoot}/b.native", '[ Para [ Str "B" ] ]');

            $report = (new DocxParityCorpusAudit($root))->report(1);
            $t->same(2, $report['pairedDocxNativeArtifacts']);
            $t->same(1, $report['auditedPairCount']);
            $t->same(1, $report['unauditedPairCount']);
            $t->same(1, $report['bothParsedCount']);

            $missingRoot = $makeTempRoot();
            try {
                $command = escapeshellarg(PHP_BINARY)
                    . ' '
                    . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-parity-audit.php')
                    . ' --repo-root='
                    . escapeshellarg($missingRoot)
                    . ' --json'
                    . ' --fail-on-regression';
                $output = [];
                $exitCode = 0;
                exec($command, $output, $exitCode);
                $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

                $t->same(0, $exitCode);
                $t->same(DocxParityCorpusAudit::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
                $t->same(true, $decoded['skipped']);
                $t->same(false, $decoded['parserAcceptanceRegression']['evaluated']);
                $t->same(false, $decoded['parserAcceptanceRegression']['regressed']);
            } finally {
                $removeTree($missingRoot);
            }
        } finally {
            $removeTree($root);
        }
    },

    'cli fail-on-regression exits nonzero when parser acceptance is below baseline' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/sample.docx", $minimalDocx('Hello audit'));
            $writeFile($root, "{$docxRoot}/sample.native", '[ Para [ Str "Hello" , Space , Str "audit" ] ]');

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-parity-audit.php')
                . ' --repo-root='
                . escapeshellarg($root)
                . ' --json'
                . ' --fail-on-regression';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(DocxParityCorpusAudit::STATUS_REPORTED, $decoded['status']);
            $t->same(true, $decoded['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $decoded['parserAcceptanceRegression']['passed']);
            $t->same(true, $decoded['parserAcceptanceRegression']['regressed']);
            $t->same(1, $decoded['parserAcceptanceRegression']['actualBothParsedCount']);
            $t->true(in_array('paired-docx-native-artifact-count-below-baseline', $decoded['parserAcceptanceRegression']['failureReasons'], true));
        } finally {
            $removeTree($root);
        }
    },

    'writer golden cli reports package hashes and present writer status' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx, $minimalDocxDocumentXml): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/golden/writer-output.docx", $minimalDocx('Writer inventory'));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($root)
                . ' --json';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(DocxWriterGoldenManifest::STATUS_REPORTED, $decoded['status']);
            $t->same(DocxWriterGoldenManifest::EVIDENCE_KIND, $decoded['evidenceKind']);
            $t->same('implementation-present-or-registered', $decoded['localWriter']['status']);
            $t->same(true, $decoded['localWriter']['classExists']);
            $t->same(false, $decoded['localWriter']['fileExists']);
            $t->same('partial', $decoded['localWriter']['registryStatus']);
            $t->same(false, $decoded['packageComparison']['run']);
            $t->same(DocxWriterGoldenManifest::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON, $decoded['packageComparison']['reason']);
            $t->same(1, $decoded['packageComparison']['expectedGoldenPackageCount']);
            $t->same(0, $decoded['packageComparison']['comparedPackageCount']);
            $t->same(1, $decoded['packageComparison']['missingGeneratedPackageCount']);
            $t->true(in_array('raw ZIP package byte equality', $decoded['packageComparison']['stableComparisonContract']['ignores'], true));
            $t->same(1, $decoded['goldenPackageCount']);
            $t->same(1, $decoded['packagePartCount']);
            $t->same('test/docx/golden/writer-output.docx', $decoded['packageRows'][0]['expectedUpstreamGoldenReference']);
            $t->same(['word/document.xml'], $decoded['packageRows'][0]['partNames']);
            $t->same(hash('sha256', $minimalDocxDocumentXml('Writer inventory')), $decoded['packageRows'][0]['partRows'][0]['sha256']);
            $t->same(1, $decoded['packageRows'][0]['stableSemantics']['xmlPartCount']);
            $shape = $decoded['goldenPackageCommonShape'];
            $t->same(1, $shape['packageCount']);
            $t->same(1, $shape['readablePackageCount']);
            $t->same(['word/document.xml'], $shape['commonPartNames']);
            $t->same([], $shape['optionalPartNameRows']);
            $t->same(0, $shape['commonContentTypeRecordCount']);
            $t->same(0, $shape['commonRelationshipRecordCount']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden stable semantics records targeted xml feature summaries' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $featureDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/golden/features.docx", $featureDocx(true));
            $writeFile($root, 'generated-docx/features.docx', $featureDocx(false));

            $report = (new DocxWriterGoldenManifest($root, DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR, 8, 'generated-docx'))->report();
            $semantics = $report['packageRows'][0]['stableSemantics'];
            $featureSummary = $semantics['xmlFeatureSummary'];
            $totals = $featureSummary['totals'];

            $t->same(1, $semantics['xmlFeaturePartCount']);
            $t->same('word/document.xml', $featureSummary['partRows'][0]['partName']);
            $t->same(1, $totals['wordTable']);
            $t->same(1, $totals['wordTableCaption']);
            $t->same(1, $totals['wordParagraphTableCaptionStyle']);
            $t->same(1, $totals['wordDrawing']);
            $t->same(1, $totals['drawingInline']);
            $t->same(1, $totals['drawingBlip']);
            $t->same(1, $totals['drawingRelationshipEmbed']);
            $t->same(1, $totals['drawingPicture']);
            $t->same(1, $totals['vmlShape']);
            $t->same(1, $totals['vmlTextBox']);
            $t->same(1, $totals['vmlImageData']);
            $t->same(1, $totals['wordTextBoxContent']);
            $t->same(1, $totals['wordprocessingShape']);
            $t->same(1, $totals['wordprocessingShapeTextBox']);
            $t->same(1, $totals['wordSdt']);
            $t->same(1, $totals['wordSdtPr']);
            $t->same(1, $totals['wordSdtContent']);
            $t->true(is_string($semantics['xmlFeatureSummarySha256']) && $semantics['xmlFeatureSummarySha256'] !== '');

            $changed = $report['packageComparison']['comparisonRows'][0]['mismatchDetails']['xmlPartDeltas']['changedXmlParts'][0];
            $deltasByFeature = [];
            foreach ($changed['xmlFeatureDeltas'] as $delta) {
                $deltasByFeature[$delta['feature']] = $delta;
            }

            $t->same('word/document.xml', $changed['partName']);
            $t->same(1, $deltasByFeature['wordTable']['goldenCount']);
            $t->same(0, $deltasByFeature['wordTable']['generatedCount']);
            $t->same(1, $deltasByFeature['wordParagraphTableCaptionStyle']['goldenCount']);
            $t->same(0, $deltasByFeature['wordParagraphTableCaptionStyle']['generatedCount']);
            $t->same(1, $deltasByFeature['wordSdt']['goldenCount']);
            $t->same(0, $deltasByFeature['wordSdt']['generatedCount']);
            $t->same(1, $deltasByFeature['vmlTextBox']['goldenCount']);
            $t->same(0, $deltasByFeature['vmlTextBox']['generatedCount']);
            $t->same(1, $deltasByFeature['drawingBlip']['goldenCount']);
            $t->same(0, $deltasByFeature['drawingBlip']['generatedCount']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden cli filters inventory by golden package name' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/golden/writer-output.docx", $minimalDocx('Writer inventory'));
            $writeFile($root, "{$docxRoot}/golden/other-output.docx", $minimalDocx('Other inventory'));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($root)
                . ' --json'
                . ' --golden=writer-output';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(true, $decoded['caseFilter']['active']);
            $t->same(['writer-output'], $decoded['caseFilter']['values']);
            $t->same(0, $decoded['caseFilter']['selectedPinnedGoldenCaseCount']);
            $t->same(2, $decoded['caseFilter']['unfilteredGoldenPackageCount']);
            $t->same(1, $decoded['caseFilter']['matchingGoldenPackageCount']);
            $t->same(['writer-output.docx'], $decoded['caseFilter']['matchingGoldenPackageFiles']);
            $t->same(2, $decoded['unfilteredGoldenPackageCount']);
            $t->same(1, $decoded['goldenPackageCount']);
            $t->same('writer-output.docx', $decoded['packageRows'][0]['fileName']);
            $t->same(1, $decoded['packageComparison']['expectedGoldenPackageCount']);
            $t->same(1, $decoded['packageComparison']['missingGeneratedPackageCount']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden comparison matches generated docx packages by stable package semantics' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $semanticDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $golden = $semanticDocx('Stable package');
            $generated = $semanticDocx('Stable package', true);
            $writeFile($root, "{$docxRoot}/golden/writer-output.docx", $golden);
            $writeFile($root, 'generated-docx/writer-output.docx', $generated);

            $report = (new DocxWriterGoldenManifest($root, DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR, 8, 'generated-docx'))->report();
            $text = DocxWriterGoldenManifest::formatTextReport($report);

            $t->same(DocxWriterGoldenManifest::EVIDENCE_KIND_GENERATED_COMPARISON, $report['evidenceKind']);
            $t->same(true, $report['packageComparison']['run']);
            $t->same('matched-stable-package-semantics', $report['packageComparison']['status']);
            $t->same(1, $report['packageComparison']['expectedGoldenPackageCount']);
            $t->same(1, $report['packageComparison']['generatedPackageCount']);
            $t->same(1, $report['packageComparison']['comparedPackageCount']);
            $t->same(1, $report['packageComparison']['matchedPackageCount']);
            $t->same(0, $report['packageComparison']['mismatchedPackageCount']);
            $t->same(0, $report['packageComparison']['missingGeneratedPackageCount']);
            $t->same(0, $report['packageComparison']['unexpectedGeneratedPackageCount']);
            $t->same(100.0, $report['packageComparison']['comparisonCoveragePercent']);
            $t->same(true, $report['packageComparison']['allStableSemanticsMatch']);
            $t->same('stable-match', $report['packageComparison']['comparisonRows'][0]['status']);
            $t->same([], $report['packageComparison']['comparisonRows'][0]['mismatchKinds']);
            $t->true(
                $report['packageComparison']['comparisonRows'][0]['goldenPackageSha256'] !== $report['packageComparison']['comparisonRows'][0]['generatedPackageSha256'],
                'raw ZIP package bytes should be allowed to differ'
            );
            $t->same(
                $report['packageComparison']['comparisonRows'][0]['goldenStablePackageSha256'],
                $report['packageComparison']['comparisonRows'][0]['generatedStablePackageSha256']
            );
            $shape = $report['goldenPackageCommonShape'];
            $t->same(1, $shape['packageCount']);
            $t->same(1, $shape['readablePackageCount']);
            $t->same(5, $shape['commonPartNameCount']);
            $t->same(0, $shape['optionalPartNameCount']);
            $t->same(4, $shape['commonContentTypeRecordCount']);
            $t->same(3, $shape['commonRelationshipRecordCount']);
            $t->same(['/_rels/.rels', '/word/_rels/document.xml.rels'], $shape['commonRelationshipPartNames']);
            $t->contains('word/media/image1.png', implode(',', $shape['commonPartNames']));
            $t->contains('Golden package common shape: common parts=5; optional parts=0', $text);
            $t->contains('Generated package comparison: run; compared=1/1; matched=1', $text);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden comparison reports generated coverage gaps and stable mismatches' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $semanticDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/golden/a.docx", $semanticDocx('Golden A'));
            $writeFile($root, "{$docxRoot}/golden/b.docx", $semanticDocx('Golden B'));
            $writeFile($root, 'generated-docx/a.docx', $semanticDocx('Changed A', true));
            $writeFile($root, 'generated-docx/c.docx', $semanticDocx('Extra C', true));

            $report = (new DocxWriterGoldenManifest($root, DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR, 8, 'generated-docx'))->report();
            $rowsByName = [];
            foreach ($report['packageComparison']['comparisonRows'] as $row) {
                $rowsByName[$row['fileName']] = $row;
            }

            $t->same(true, $report['packageComparison']['run']);
            $t->same('mismatched-stable-package-semantics', $report['packageComparison']['status']);
            $t->same(DocxWriterGoldenManifest::GENERATED_COMPARISON_MISMATCH_REASON, $report['packageComparison']['reason']);
            $t->same(2, $report['packageComparison']['expectedGoldenPackageCount']);
            $t->same(2, $report['packageComparison']['generatedPackageCount']);
            $t->same(1, $report['packageComparison']['comparedPackageCount']);
            $t->same(0, $report['packageComparison']['matchedPackageCount']);
            $t->same(1, $report['packageComparison']['mismatchedPackageCount']);
            $t->same(1, $report['packageComparison']['missingGeneratedPackageCount']);
            $t->same(1, $report['packageComparison']['unexpectedGeneratedPackageCount']);
            $t->same(50.0, $report['packageComparison']['comparisonCoveragePercent']);
            $t->same(false, $report['packageComparison']['allStableSemanticsMatch']);
            $t->same('stable-mismatch', $rowsByName['a.docx']['status']);
            $t->true(in_array('xml-part-semantics', $rowsByName['a.docx']['mismatchKinds'], true));
            $t->same(1, $report['packageComparison']['mismatchDiagnostics']['stableMismatchPackageCount']);
            $t->same(1, $report['packageComparison']['mismatchDiagnostics']['mismatchKindCounts']['xml-part-semantics']);
            $t->same(1, $report['packageComparison']['mismatchDiagnostics']['xmlPartDeltas']['packagesWithChangedXmlParts']);
            $t->same(1, $rowsByName['a.docx']['mismatchDetails']['xmlPartDeltas']['changedXmlPartCount']);
            $t->same('word/document.xml', $rowsByName['a.docx']['mismatchDetails']['xmlPartDeltas']['changedXmlParts'][0]['partName']);
            $t->same('missing-generated', $rowsByName['b.docx']['status']);
            $t->same('unexpected-generated', $rowsByName['c.docx']['status']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden cli required stable matches gates skips and mismatches' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $semanticDocx): void {
        $missingRoot = $makeTempRoot();
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($missingRoot)
                . ' --json'
                . ' --generate-supported-dir=generated-docx'
                . ' --require-generated-stable-matches=38';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(DocxWriterGoldenManifest::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY, $decoded['status']);
            $t->same(false, DocxWriterGoldenManifest::hasRequiredGeneratedStableMatches($decoded, 38));
        } finally {
            $removeTree($missingRoot);
        }

        $mismatchRoot = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($mismatchRoot, "{$docxRoot}/golden/a.docx", $semanticDocx('Golden A'));
            $writeFile($mismatchRoot, 'generated-docx/a.docx', $semanticDocx('Changed A', true));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($mismatchRoot)
                . ' --json'
                . ' --generated-dir=generated-docx'
                . ' --require-generated-stable-matches=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('mismatched-stable-package-semantics', $decoded['packageComparison']['status']);
            $t->same(0, $decoded['packageComparison']['matchedPackageCount']);
            $t->same(1, $decoded['packageComparison']['mismatchedPackageCount']);
            $t->same(false, DocxWriterGoldenManifest::hasRequiredGeneratedStableMatches($decoded, 1));
        } finally {
            $removeTree($mismatchRoot);
        }

        $matchRoot = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($matchRoot, "{$docxRoot}/golden/a.docx", $semanticDocx('Stable A'));
            $writeFile($matchRoot, 'generated-docx/a.docx', $semanticDocx('Stable A', true));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($matchRoot)
                . ' --json'
                . ' --generated-dir=generated-docx'
                . ' --require-generated-stable-matches=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('matched-stable-package-semantics', $decoded['packageComparison']['status']);
            $t->same(1, $decoded['packageComparison']['matchedPackageCount']);
            $t->same(true, DocxWriterGoldenManifest::hasRequiredGeneratedStableMatches($decoded, 1));
        } finally {
            $removeTree($matchRoot);
        }
    },

    'writer golden comparison summarizes stable mismatch diagnostics' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $diagnosticDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/golden/diagnostic.docx", $diagnosticDocx(false));
            $writeFile($root, 'generated-docx/diagnostic.docx', $diagnosticDocx(true));

            $report = (new DocxWriterGoldenManifest($root, DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR, 8, 'generated-docx'))->report();
            $diagnostics = $report['packageComparison']['mismatchDiagnostics'];

            $t->same(true, $report['packageComparison']['run']);
            $t->same(1, $diagnostics['stableMismatchPackageCount']);
            $t->same(1, $diagnostics['partNameDeltas']['packagesWithMissingParts']);
            $t->same(1, $diagnostics['partNameDeltas']['packagesWithExtraParts']);
            $t->same('word/styles.xml', $diagnostics['partNameDeltas']['missingPartNameCounts'][0]['partName']);
            $t->same('word/numbering.xml', $diagnostics['partNameDeltas']['extraPartNameCounts'][0]['partName']);
            $t->same(1, $diagnostics['contentTypeDeltas']['packagesWithMissingRecords']);
            $t->same(1, $diagnostics['contentTypeDeltas']['packagesWithExtraRecords']);
            $t->same('/word/styles.xml', $diagnostics['contentTypeDeltas']['missingRecordCounts'][0]['record']['partName']);
            $t->same('/word/numbering.xml', $diagnostics['contentTypeDeltas']['extraRecordCounts'][0]['record']['partName']);
            $t->same(1, $diagnostics['relationshipDeltas']['packagesWithMissingRecords']);
            $t->same(1, $diagnostics['relationshipDeltas']['packagesWithExtraRecords']);
            $t->contains('/relationships/styles', $diagnostics['relationshipDeltas']['missingRecordCounts'][0]['record']['relationshipType']);
            $t->contains('/relationships/numbering', $diagnostics['relationshipDeltas']['extraRecordCounts'][0]['record']['relationshipType']);
            $t->same(1, $diagnostics['xmlPartDeltas']['packagesWithMissingXmlParts']);
            $t->same(1, $diagnostics['xmlPartDeltas']['packagesWithExtraXmlParts']);
            $t->same(1, $diagnostics['xmlPartDeltas']['packagesWithChangedXmlParts']);
            $t->same('word/document.xml', $diagnostics['xmlPartDeltas']['changedXmlPartCounts'][0]['partName']);
            $details = $report['packageComparison']['comparisonRows'][0]['mismatchDetails'];
            $t->same(1, $details['partNameDeltas']['missingPartCount']);
            $t->same(1, $details['partNameDeltas']['extraPartCount']);
            $t->same('word/styles.xml', $details['partNameDeltas']['missingPartNames'][0]);
            $t->same('word/numbering.xml', $details['partNameDeltas']['extraPartNames'][0]);
            $t->same(1, $details['contentTypeDeltas']['missingRecordCount']);
            $t->same(1, $details['contentTypeDeltas']['extraRecordCount']);
            $t->same('/word/styles.xml', $details['contentTypeDeltas']['missingRecords'][0]['partName']);
            $t->same('/word/numbering.xml', $details['contentTypeDeltas']['extraRecords'][0]['partName']);
            $t->same(1, $details['relationshipDeltas']['missingRecordCount']);
            $t->same(1, $details['relationshipDeltas']['extraRecordCount']);
            $t->same(1, $details['xmlPartDeltas']['missingXmlPartCount']);
            $t->same(1, $details['xmlPartDeltas']['extraXmlPartCount']);
            $t->same(1, $details['xmlPartDeltas']['changedXmlPartCount']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden generation records absent upstream source blocker' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $root = $makeTempRoot();
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-writer-golden-audit.php')
                . ' --repo-root='
                . escapeshellarg($root)
                . ' --json'
                . ' --generate-supported-dir=generated-docx';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(DocxWriterGoldenManifest::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY, $decoded['status']);
            $t->same(false, $decoded['generation']['run']);
            $t->same('not-run-upstream-docx-source-directory-missing', $decoded['generation']['status']);
            $t->same(DocxWriterGoldenManifest::GENERATION_SOURCE_DIRECTORY_MISSING_REASON, $decoded['generation']['reason']);
            $t->same(true, $decoded['generation']['outputDirectoryConfigured']);
            $t->same(38, $decoded['generation']['expectedGoldenCaseCount']);
            $t->same(0, $decoded['generation']['generatedPackageCount']);
            $t->same(38, $decoded['generation']['skippedCaseCount']);
            $t->same(0, $decoded['generation']['failedCaseCount']);
            $t->same(1, $decoded['generation']['blockerCounts'][DocxWriterGoldenManifest::GENERATION_SOURCE_DIRECTORY_MISSING_REASON]);
            $t->same(false, $decoded['packageComparison']['run']);
            $t->same('not-run-golden-directory-missing', $decoded['packageComparison']['status']);
            $t->same(DocxWriterGoldenManifest::GOLDEN_DIRECTORY_MISSING_REASON, $decoded['packageComparison']['reason']);
            $t->same(38, $decoded['packageComparison']['missingGeneratedPackageCount']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden generation writes supported subset and compares stable package semantics' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $writerDocxFromNative, $findRowByGoldenFile): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $native = '[ Para [ Str "Generated" , Space , Str "subset" ] ]';
            $writeFile($root, "{$docxRoot}/comments.native", $native);
            $writeFile($root, "{$docxRoot}/golden/comments.docx", $writerDocxFromNative($native));

            $report = (new DocxWriterGoldenManifest(
                $root,
                DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR,
                8,
                null,
                'generated-docx'
            ))->report();
            $text = DocxWriterGoldenManifest::formatTextReport($report);
            $commentsGeneration = $findRowByGoldenFile($report['generation']['caseRows'], 'comments.docx');

            $t->same(DocxWriterGoldenManifest::EVIDENCE_KIND_GENERATED_COMPARISON, $report['evidenceKind']);
            $t->same(true, $report['generation']['run']);
            $t->same('generated-supported-writer-golden-subset', $report['generation']['status']);
            $t->same(38, $report['generation']['expectedGoldenCaseCount']);
            $t->same(1, $report['generation']['attemptedCaseCount']);
            $t->same(1, $report['generation']['generatedPackageCount']);
            $t->same(37, $report['generation']['skippedCaseCount']);
            $t->same(0, $report['generation']['failedCaseCount']);
            $t->same('generated', $commentsGeneration['status']);
            $t->same(true, is_file($root . '/generated-docx/comments.docx'));
            $t->same(true, $report['packageComparison']['run']);
            $t->same('matched-stable-package-semantics', $report['packageComparison']['status']);
            $t->same(1, $report['packageComparison']['expectedGoldenPackageCount']);
            $t->same(1, $report['packageComparison']['generatedPackageCount']);
            $t->same(1, $report['packageComparison']['matchedPackageCount']);
            $t->same(0, $report['packageComparison']['missingGeneratedPackageCount']);
            $t->same(true, $report['packageComparison']['allStableSemanticsMatch']);
            $t->contains('Generated package production: run; generated=1/38', $text);
            $t->contains('Generated package comparison: run; compared=1/1; matched=1', $text);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden generation and comparison can focus a single case' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $writerDocxFromNative, $minimalDocx, $findRowByGoldenFile): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $native = '[ Para [ Str "Focused" , Space , Str "case" ] ]';
            $writeFile($root, "{$docxRoot}/comments.native", $native);
            $writeFile($root, "{$docxRoot}/golden/comments.docx", $writerDocxFromNative($native));
            $writeFile($root, "{$docxRoot}/golden/codeblock.docx", $minimalDocx('Unselected golden'));
            $writeFile($root, 'generated-docx/codeblock.docx', $minimalDocx('Unselected generated'));

            $report = (new DocxWriterGoldenManifest(
                $root,
                DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR,
                8,
                null,
                'generated-docx',
                ['comments']
            ))->report();
            $text = DocxWriterGoldenManifest::formatTextReport($report);
            $commentsGeneration = $findRowByGoldenFile($report['generation']['caseRows'], 'comments.docx');

            $t->same(true, $report['caseFilter']['active']);
            $t->same(['comments'], $report['caseFilter']['values']);
            $t->same(1, $report['caseFilter']['selectedPinnedGoldenCaseCount']);
            $t->same(['comments.docx'], $report['caseFilter']['matchingGoldenPackageFiles']);
            $t->same(2, $report['unfilteredGoldenPackageCount']);
            $t->same(1, $report['goldenPackageCount']);
            $t->same('comments.docx', $report['packageRows'][0]['fileName']);
            $t->same(true, $report['generation']['caseFilterActive']);
            $t->same(1, $report['generation']['expectedGoldenCaseCount']);
            $t->same(1, $report['generation']['attemptedCaseCount']);
            $t->same(1, $report['generation']['generatedPackageCount']);
            $t->same(0, $report['generation']['skippedCaseCount']);
            $t->same('generated', $commentsGeneration['status']);
            $t->same(true, $report['packageComparison']['caseFilterActive']);
            $t->same(['comments'], $report['packageComparison']['caseFilterValues']);
            $t->same(1, $report['packageComparison']['expectedGoldenPackageCount']);
            $t->same(1, $report['packageComparison']['generatedPackageCount']);
            $t->same(1, $report['packageComparison']['matchedPackageCount']);
            $t->same(0, $report['packageComparison']['unexpectedGeneratedPackageCount']);
            $t->contains('Case filter: active; values=comments; selected pinned cases=1/38; matching golden packages=1/2', $text);
            $t->contains('Generated package production: run; generated=1/1', $text);
            $t->contains('Generated package comparison: run; compared=1/1; matched=1', $text);
        } finally {
            $removeTree($root);
        }
    },
];
