<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\DocxUpstreamFocusedReaderEvidence;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\ZipPackage;

$repoRoot = dirname(__DIR__, 3);

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/pandoc-docx-focused-reader-' . bin2hex(random_bytes(6));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to create temporary directory {$path}");
    }

    return $path;
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
        throw new RuntimeException("Unable to create fixture directory {$directory}");
    }
    file_put_contents($path, $contents);
};

$imageDocxBytes = static function (): string {
    return ZipPackage::build([
        [
            'name' => '[Content_Types].xml',
            'data' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        ],
        [
            'name' => 'word/_rels/document.xml.rels',
            'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>',
        ],
        [
            'name' => 'word/document.xml',
            'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p><w:r><w:t>Image fixture</w:t></w:r></w:p>
    <w:p><w:r><w:drawing><wp:inline><wp:docPr id="1" name="Focused image" descr="Focused media"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/image1.png',
            'data' => "focused image bytes\n",
        ],
    ]);
};

$mendeleyCitationDocxBytes = static function (): string {
    return ZipPackage::build([
        [
            'name' => '[Content_Types].xml',
            'data' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        ],
        [
            'name' => 'word/document.xml',
            'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Mendeley citation </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> ADDIN CSL_CITATION {"citationItems":[{"id":"ITEM-1"}],"mendeley":{"formattedCitation":"(Focused, 2026)"},"properties":{"noteIndex":0}} </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>(Focused, 2026)</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML,
        ],
    ]);
};

$commentDocxBytes = static function (): string {
    return ZipPackage::build([
        [
            'name' => '[Content_Types].xml',
            'data' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/></Types>',
        ],
        [
            'name' => 'word/comments.xml',
            'data' => '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="3" w:author="Author"><w:p><w:r><w:t>Ranged comment body.</w:t></w:r></w:p></w:comment><w:comment w:id="4" w:author="Author"><w:p><w:r><w:t>Point comment body.</w:t></w:r></w:p></w:comment></w:comments>',
        ],
        [
            'name' => 'word/document.xml',
            'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Here is a </w:t></w:r><w:commentRangeStart w:id="3"/><w:r><w:t>document</w:t></w:r><w:commentRangeEnd w:id="3"/><w:r><w:commentReference w:id="3"/></w:r><w:r><w:t xml:space="preserve"> and point</w:t></w:r><w:r><w:commentReference w:id="4"/></w:r><w:r><w:t>.</w:t></w:r></w:p></w:body></w:document>',
        ],
    ]);
};

$flatten = static function (AstNode $node) use (&$flatten): array {
    $nodes = [$node];
    foreach ($node->children as $child) {
        array_push($nodes, ...$flatten($child));
    }

    return $nodes;
};

return [
    'guards checked-in focused reader evidence artifact counts' => static function (TestRunner $t) use ($repoRoot): void {
        $path = $repoRoot . '/' . DocxUpstreamFocusedReaderEvidence::CHECKED_IN_REPORT_PATH;
        $report = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $t->same(DocxUpstreamFocusedReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same('focused-native-php-and-mapped-reader-evidence', $report['evidenceKind']);
        $t->same(36, $report['denominator']['denominatorCaseRows']);
        $t->same(31, $report['focusedCoverage']['coveredCaseCount']);
        $t->same(5, $report['focusedCoverage']['remainingOpenCaseCount']);
        $t->same(27, $report['targetedFixtureChecks']['passedTargetedCaseCount']);
        $t->same(0, $report['targetedFixtureChecks']['failedTargetedCaseCount']);
        $t->same(4, $report['targetedFixtureChecks']['mappedOnlyCaseCount']);
        $t->same('valid-denominator-map', $report['mappingValidation']['status']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
    },

    'maps focused reader evidence against the 36 case upstream denominator' => static function (TestRunner $t) use ($repoRoot, $makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new DocxUpstreamFocusedReaderEvidence($repoRoot, $root . '/missing-docx'))->report();
            $coverage = $report['focusedCoverage'];
            $targeted = $report['targetedFixtureChecks'];
            $caseRows = $report['caseRows'];

            $t->same(1, $report['schemaVersion']);
            $t->same(DocxUpstreamFocusedReaderEvidence::STATUS_REPORTED, $report['status']);
            $t->same('focused-native-php-and-mapped-reader-evidence', $report['evidenceKind']);
            $t->same(36, $report['denominator']['totalCasesNotCoveredByLocal74GateSemantics']);
            $t->same(36, $report['denominator']['denominatorCaseRows']);
            $t->same(36, count($caseRows));
            $t->same(31, $coverage['coveredCaseCount']);
            $t->same(5, $coverage['remainingOpenCaseCount']);
            $t->same('valid-denominator-map', $report['mappingValidation']['status']);
            $t->same([], $report['mappingValidation']['issues']);
            $t->same(DocxUpstreamFocusedReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $targeted['status']);
            $t->same(false, $targeted['sourceDirectoryPresent']);
            $t->same(5, $coverage['coverageKindCounts']['focused-media-bag-native-php-check']);
            $t->same(4, $coverage['coverageKindCounts']['focused-citation-addin-native-php-check']);
            $t->same(12, $coverage['coverageKindCounts']['focused-revision-mode-native-php-check']);
            $t->same(2, $coverage['coverageKindCounts']['focused-comments-native-php-check']);
            $t->same(4, $coverage['coverageKindCounts']['mapped-upstream-native-expectation-evidence']);
            $t->true(in_array('comment warnings (all)', $coverage['remainingOpenLabels'], true));
            $t->true(in_array('comments (accept -- no comments)', $coverage['remainingOpenLabels'], true));
            $t->true(!in_array('comments (reject -- comments)', $coverage['remainingOpenLabels'], true), 'reject no-comments case should be covered by focused evidence');
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'runs optional targeted media checks when a hydrated docx fixture is present' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $imageDocxBytes, $repoRoot): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'image.docx', $imageDocxBytes());
            $report = (new DocxUpstreamFocusedReaderEvidence($repoRoot, $root))->report();
            $targeted = $report['targetedFixtureChecks'];
            $passedLabels = array_values(array_map(
                static fn (array $row): string => (string) $row['label'],
                array_filter($targeted['caseCheckRows'], static fn (array $row): bool => ($row['status'] ?? '') === 'passed')
            ));

            $t->same(DocxUpstreamFocusedReaderEvidence::STATUS_COMPLETED, $targeted['status']);
            $t->same(true, $targeted['sourceDirectoryPresent']);
            $t->same(2, $targeted['passedTargetedCaseCount']);
            $t->same(0, $targeted['failedTargetedCaseCount']);
            $t->true($targeted['skippedTargetedCaseCount'] > 0);
            $t->true(in_array('inline image', $passedLabels, true));
            $t->true(in_array('image extraction', $passedLabels, true));
        } finally {
            $removeTree($root);
        }
    },

    'classifies generic mendeley CSL_CITATION addin payloads from DOCX fields' => static function (TestRunner $t) use ($mendeleyCitationDocxBytes, $flatten): void {
        $document = (new DocxReader())->read($mendeleyCitationDocxBytes());
        $spans = array_values(array_filter(
            $flatten($document),
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $citation = $spans[0] ?? new AstNode('missing');
        $attrs = $citation->attr('attributes', []);

        $t->same('span', $citation->type);
        $t->same([
            'docx-field',
            'docx-field-addin',
            'docx-addin-field',
            'docx-addin-csl-citation',
            'docx-addin-provider-mendeley',
        ], $citation->attr('classes'));
        $t->same('csl-citation', $attrs['data-docx-addin-type']);
        $t->same('mendeley', $attrs['data-docx-addin-provider']);
        $t->same('json', $attrs['data-docx-addin-payload-kind']);
        $t->same('true', $attrs['data-docx-addin-csl-json-valid']);
        $t->same('1', $attrs['data-docx-addin-citation-item-count']);
        $t->same('ITEM-1', $attrs['data-docx-addin-citation-item-ids']);
        $t->same('(Focused, 2026)', $citation->children[0]->attr('text'));
    },

    'omits docx comments by configured comments mode' => static function (TestRunner $t) use ($commentDocxBytes): void {
        $document = (new DocxReader(['commentsMode' => 'omit', 'revisionMode' => 'reject']))->read($commentDocxBytes());
        $paragraph = $document->children[0];
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->same('Here is a document and point.', $paragraph->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(2, $document->attr('meta')['docxComments']);
        $t->true(!str_contains($native, 'comment-start'), 'commentsMode=omit should not emit comment range spans');
        $t->true(!str_contains($native, 'Note'), 'commentsMode=omit should not emit point comment notes');
        $t->true(!str_contains($native, 'comment body'), 'commentsMode=omit should keep comment bodies out of native output');
        $t->throws(InvalidArgumentException::class, static fn (): DocxReader => new DocxReader(['commentsMode' => 'inline']));
    },
];
