<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$buildDocxReaderPackageBytes = static function (string $documentXml): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary DOCX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary DOCX package');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary DOCX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

/**
 * @param array<string, string> $parts
 * @param array<string, int> $compressionMethodsByName
 */
$buildDocxReaderNativeZipPackageBytes = static function (array $parts, array $compressionMethodsByName = []): string {
    $body = '';
    $central = '';
    $entryCount = 0;

    foreach ($parts as $name => $contents) {
        $method = $compressionMethodsByName[$name] ?? 8;
        $compressed = match ($method) {
            0 => $contents,
            8 => gzdeflate($contents),
            default => $contents,
        };
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate DOCX fixture entry {$name}");
        }

        $crc32 = (int) sprintf('%u', crc32($contents));
        $offset = strlen($body);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset,
        );
        $central .= $name;
        ++$entryCount;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), strlen($body), 0);
};

return [
    'resolves docx tracked revisions by configured revision mode' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Base </w:t></w:r><w:ins w:author="Insert Reviewer" w:date="2026-06-26T12:00:00Z"><w:r><w:t xml:space="preserve">inserted </w:t></w:r></w:ins><w:del w:author="Delete Reviewer" w:date="2026-06-26T12:01:00Z"><w:r><w:delText xml:space="preserve">deleted </w:delText></w:r></w:del><w:moveFrom w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:02:00Z"><w:r><w:delText xml:space="preserve">moved-from </w:delText></w:r></w:moveFrom><w:moveTo w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:03:00Z"><w:r><w:t xml:space="preserve">moved-to </w:t></w:r></w:moveTo><w:r><w:t>tail</w:t></w:r></w:p></w:body></w:document>');

        $preserveDocument = (new DocxReader(['revisionMode' => 'preserve']))->read($bytes);
        $preserve = $preserveDocument->children[0];
        $preserveBlocks = (new WordPressBlockWriter())->write($preserveDocument);
        $t->same('Base inserted deleted moved-from moved-to tail', $preserve->attr('text'));
        $t->same(['insertion'], $preserve->children[1]->attr('classes'));
        $t->same(['deletion'], $preserve->children[2]->attr('classes'));
        $t->same(['deletion', 'move-from'], $preserve->children[3]->attr('classes'));
        $t->same(['insertion', 'move-to'], $preserve->children[4]->attr('classes'));
        $t->contains('<ins class="insertion" data-pandoc-change-author="Insert Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion" data-pandoc-change-author="Delete Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);

        $acceptDocument = (new DocxReader(['revisionMode' => 'accept']))->read($bytes);
        $accept = $acceptDocument->children[0];
        $acceptBlocks = (new WordPressBlockWriter())->write($acceptDocument);
        $t->same('Base inserted moved-to tail', $accept->attr('text'));
        $t->same(1, count($accept->children));
        $t->same('text', $accept->children[0]->type);
        $t->true(!str_contains($acceptBlocks, '<ins'), 'accepted revisions should not preserve insertion spans');
        $t->true(!str_contains($acceptBlocks, '<del'), 'accepted revisions should not preserve deletion spans');
        $t->true(!str_contains($acceptBlocks, 'deleted'), 'accepted revisions should drop deleted text');
        $t->true(!str_contains($acceptBlocks, 'moved-from'), 'accepted revisions should drop moveFrom text');

        $rejectDocument = PandocConverter::read($bytes, 'docx', ['revisionMode' => 'reject']);
        $reject = $rejectDocument->children[0];
        $rejectBlocks = (new WordPressBlockWriter())->write($rejectDocument);
        $t->same('Base deleted moved-from tail', $reject->attr('text'));
        $t->same(1, count($reject->children));
        $t->same('text', $reject->children[0]->type);
        $t->true(!str_contains($rejectBlocks, '<ins'), 'rejected revisions should not preserve insertion spans');
        $t->true(!str_contains($rejectBlocks, '<del'), 'rejected revisions should not preserve deletion spans');
        $t->true(!str_contains($rejectBlocks, 'inserted'), 'rejected revisions should drop inserted text');
        $t->true(!str_contains($rejectBlocks, 'moved-to'), 'rejected revisions should drop moveTo text');

        $t->throws(InvalidArgumentException::class, static fn (): DocxReader => new DocxReader(['revisionMode' => 'merge']));
    },
    'reads docx soft and non-breaking hyphen run markers' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Soft hyphen: [</w:t><w:softHyphen/><w:t>]</w:t></w:r></w:p><w:p><w:r><w:t>Non-breaking hyphen: [</w:t><w:noBreakHyphen/><w:t>]</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $softHyphen = "\u{00AD}";
        $nonBreakingHyphen = "\u{2011}";

        $t->same("Soft hyphen: [{$softHyphen}]", $document->children[0]->attr('text'));
        $t->same("Soft hyphen: [{$softHyphen}]", $document->children[0]->children[0]->attr('text'));
        $t->same("Non-breaking hyphen: [{$nonBreakingHyphen}]", $document->children[1]->attr('text'));
        $t->same("Non-breaking hyphen: [{$nonBreakingHyphen}]", $document->children[1]->children[0]->attr('text'));
    },
    'resolves docx paragraph style numbering and explicit numbering suppression' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ReviewStep">
    <w:name w:val="Review Step"/>
    <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="42"/></w:numPr></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="DerivedReviewStep">
    <w:name w:val="Derived Review Step"/>
    <w:basedOn w:val="ReviewStep"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ChecklistItem">
    <w:name w:val="Checklist Item"/>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="7">
    <w:lvl w:ilvl="0"><w:start w:val="5"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="42"><w:abstractNumId w:val="7"/></w:num>
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:pStyle w:val="ChecklistItem"/><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="43"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/></w:pPr><w:r><w:t>Review source</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/></w:pPr><w:r><w:t>Publish migration</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistItem"/></w:pPr><w:r><w:t>Check footers</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Plain exception</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $ordered = $document->children[0];
        $bullet = $document->children[1];
        $suppressed = $document->children[2];

        $t->same('ordered_list', $ordered->type);
        $t->same('42', $ordered->attr('numId'));
        $t->same(0, $ordered->attr('level'));
        $t->same(5, $ordered->attr('start'));
        $t->same('lower_alpha', $ordered->attr('style'));
        $t->same('one_paren', $ordered->attr('delimiter'));
        $t->same('Review source', $ordered->children[0]->children[0]->attr('text'));
        $t->same('Publish migration', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('43', $bullet->attr('numId'));
        $t->same(0, $bullet->attr('level'));
        $t->same('Check footers', $bullet->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $suppressed->type);
        $t->same('Plain exception', $suppressed->attr('text'));
        $t->contains('<ol start="5" type="a"><li>Review source</li><li>Publish migration</li></ol>', $blocks);
        $t->contains('<ul><li>Check footers</li></ul>', $blocks);
        $t->contains('<p>Plain exception</p>', $blocks);
    },
    'keeps explicitly suppressed same-style numbering as a list item continuation' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ListNumber">
    <w:name w:val="List Number"/>
    <w:pPr><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:pStyle w:val="ListNumber"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="ListNumber"/></w:pPr><w:r><w:t>One</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ListNumber"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Two</w:t></w:r><w:r><w:br/></w:r><w:r><w:br/><w:t>Three</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $native = (new NativeWriter())->write($document);
        $list = $document->children[0];
        $item = $list->children[0];

        $t->same(1, count($document->children));
        $t->same('ordered_list', $list->type);
        $t->same(1, count($list->children));
        $t->same(2, count($item->children));
        $t->same('One', $item->children[0]->attr('text'));
        $t->contains('Para [ Str "Two" , LineBreak , LineBreak , Str "Three" ]', $native);
    },
    'does not continue direct numbering through same-style unnumbered paragraphs' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Bodytext21">
    <w:name w:val="Body Text 2"/>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Foo</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Bar</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Baz</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/></w:pPr><w:r><w:t>Interruption.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Bop</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['ordered_list', 'paragraph', 'ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Foo', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Baz', $document->children[0]->children[2]->children[0]->attr('text'));
        $t->same('Interruption.', $document->children[1]->attr('text'));
        $t->same('Bop', $document->children[2]->children[0]->children[0]->attr('text'));
        $t->contains('<ol><li>Foo</li><li>Bar</li><li>Baz</li></ol>', $blocks);
        $t->contains('<p>Interruption.</p>', $blocks);
        $t->contains('<ol><li>Bop</li></ol>', $blocks);
    },
    'keeps indented explicitly suppressed list paragraphs with the active item' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="◦"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>one</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>four</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="0"/></w:numPr><w:ind w:left="1920"/></w:pPr><w:r><w:t>Sub paragraph</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>two</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $nestedList = $firstItem->children[1];
        $nestedItem = $nestedList->children[0];

        $t->same(['bullet_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(2, count($list->children));
        $t->same('one', $firstItem->children[0]->attr('text'));
        $t->same('bullet_list', $nestedList->type);
        $t->same(2, count($nestedItem->children));
        $t->same('four', $nestedItem->children[0]->attr('text'));
        $t->same('Sub paragraph', $nestedItem->children[1]->attr('text'));
        $t->same('two', $list->children[1]->children[0]->attr('text'));
        $t->true(!str_contains($blocks, '<blockquote>'), 'suppressed indented list continuations should not become blockquotes');
    },
    'preserves docx task-list numbering glyphs and blank bullet continuations' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="990">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="992">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="993">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="994">
    <w:lvl w:ilvl="3"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%4."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="1000"><w:abstractNumId w:val="990"/></w:num>
  <w:num w:numId="1001"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1002"><w:abstractNumId w:val="993"/></w:num>
  <w:num w:numId="1003"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1004"><w:abstractNumId w:val="993"/></w:num>
  <w:num w:numId="1005"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1006"><w:abstractNumId w:val="994"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1001"/></w:numPr></w:pPr><w:r><w:t>Unchecked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1002"/></w:numPr></w:pPr><w:r><w:t>Checked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1000"/></w:numPr></w:pPr><w:r><w:t>with continuation paragraph</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1003"/></w:numPr></w:pPr><w:r><w:t>Unchecked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="1004"/></w:numPr></w:pPr><w:r><w:t>Checked sublist</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="2"/><w:numId w:val="1005"/></w:numPr></w:pPr><w:r><w:t>Unchecked subsublist</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="3"/><w:numId w:val="1006"/></w:numPr></w:pPr><w:r><w:t>Numbered child</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $native = (new NativeWriter())->write($document);
        $list = $document->children[0];
        $checkedItem = $list->children[1];
        $nestedChecked = $list->children[2]->children[1];
        $nestedUnchecked = $nestedChecked->children[0]->children[1];
        $numberedChild = $nestedUnchecked->children[0]->children[1];

        $t->same('bullet_list', $list->type);
        $t->same(3, count($list->children));
        $t->same("☐ Unchecked", $list->children[0]->children[0]->attr('text'));
        $t->same("☒ Checked", $checkedItem->children[0]->attr('text'));
        $t->same('with continuation paragraph', $checkedItem->children[1]->attr('text'));
        $t->same("☐ Unchecked", $list->children[2]->children[0]->attr('text'));
        $t->same('bullet_list', $nestedChecked->type);
        $t->same("☒ Checked sublist", $nestedChecked->children[0]->children[0]->attr('text'));
        $t->same('bullet_list', $nestedUnchecked->type);
        $t->same("☐ Unchecked subsublist", $nestedUnchecked->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $numberedChild->type);
        $t->same('Numbered child', $numberedChild->children[0]->children[0]->attr('text'));
        $t->contains('Str "\\9744" , Space , Str "Unchecked"', $native);
        $t->contains('Str "\\9746" , Space , Str "Checked"', $native);
        $t->contains('Para [ Str "with" , Space , Str "continuation" , Space , Str "paragraph" ]', $native);
        $t->contains('OrderedList ( 1 , Decimal , Period )', $native);
    },
    'reads current upstream docx list restart fixture as restarted lists' => static function (TestRunner $t): void {
        $root = dirname(__DIR__) . '/fixtures/upstream-current-docx';
        $docxPath = $root . '/lists_restart_8367.docx';
        $nativePath = $root . '/lists_restart_8367.native';

        $t->same('82a7d9ef72325b53a2a2de927406f17ed6cc9625e74a339d523c11434d756a0b', hash_file('sha256', $docxPath));
        $t->same('23c7fa0b06905e5702f2cb1f7aa6a619ffea3b4365366c9a2ebfe7531472ba15', hash_file('sha256', $nativePath));

        $document = (new DocxReader())->readDocxFile($docxPath);
        $native = (string) file_get_contents($nativePath);

        $t->same(6, count($document->children));
        $t->same('heading', $document->children[0]->type);
        $t->same('Section 1', $document->children[0]->attr('text'));
        $t->same('section-1', $document->children[0]->attr('id'));
        $t->same('ordered_list', $document->children[1]->type);
        $t->same(3, count($document->children[1]->children));
        $t->same(1, $document->children[1]->attr('start'));
        $t->same('1', $document->children[1]->attr('numId'));
        $t->same(0, $document->children[1]->attr('level'));
        $t->same('Item 1', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Item 3', $document->children[1]->children[2]->children[0]->attr('text'));
        $t->same('Conclusion', $document->children[2]->attr('text'));
        $t->same('Section 2', $document->children[3]->attr('text'));
        $t->same('section-2', $document->children[3]->attr('id'));
        $t->same('ordered_list', $document->children[4]->type);
        $t->same(4, count($document->children[4]->children));
        $t->same(1, $document->children[4]->attr('start'));
        $t->same('2', $document->children[4]->attr('numId'));
        $t->same(0, $document->children[4]->attr('level'));
        $t->same('Item 1', $document->children[4]->children[0]->children[0]->attr('text'));
        $t->same('Item 4', $document->children[4]->children[3]->children[0]->attr('text'));
        $t->same('Conclusion', $document->children[5]->attr('text'));
        $t->contains('OrderedList (1,Decimal,Period)', $native);
    },
    'reads current upstream ns0 prefixed docx reference package' => static function (TestRunner $t): void {
        $path = dirname(__DIR__) . '/fixtures/upstream-current-docx/ns0-reference.docx';

        $t->same('5310462e44b2b601a259d1b0ae83c8407f81a9e966b623d235c9240ce7929d67', hash_file('sha256', $path));

        $document = (new DocxReader())->readDocxFile($path);
        $meta = $document->attr('meta');

        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('ref', $document->children[0]->attr('text'));
        $t->same(5, $meta['docxPackageEntries']);
        $t->same(1, $meta['docxRelationshipCount']);
    },
    'resolves numeric docx style ids without losing inherited properties' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="1">
    <w:name w:val="Numeric Heading Base"/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="2">
    <w:name w:val="Numeric Heading Derived"/>
    <w:basedOn w:val="1"/>
  </w:style>
  <w:style w:type="character" w:styleId="3">
    <w:name w:val="Numeric Strong Base"/>
    <w:rPr><w:b/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="4">
    <w:name w:val="Numeric Strong Derived"/>
    <w:basedOn w:val="3"/>
    <w:rPr><w:i/></w:rPr>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="2"/></w:pPr><w:r><w:t>Numeric style heading</w:t></w:r></w:p>
    <w:p><w:r><w:t xml:space="preserve">A </w:t></w:r><w:r><w:rPr><w:rStyle w:val="4"/></w:rPr><w:t>derived run</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('numeric-style-heading', $document->children[0]->attr('id'));
        $t->same('Numeric style heading', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('A derived run', $document->children[1]->attr('text'));
        $t->same('emph', $document->children[1]->children[1]->type);
        $t->same('strong', $document->children[1]->children[1]->children[0]->type);
        $t->same('derived run', $document->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<h1 id="numeric-style-heading">Numeric style heading</h1>', $blocks);
        $t->contains('A <em><strong>derived run</strong></em>', $blocks);
    },
    'reads docx package body metadata notes headers footers and review spans into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/><Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test" TargetMode="External"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr></w:style><w:style w:type="character" w:styleId="StrongStyle"><w:rPr><w:b/></w:rPr></w:style></w:styles>');
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>DOCX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded DOCX reader smoke.</dc:description><dcterms:created>2026-06-18T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/footnotes.xml', '<?xml version="1.0"?><w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:footnote w:id="1"><w:p><w:r><w:t>Footnote body.</w:t></w:r></w:p></w:footnote></w:footnotes>');
        $zip->addFromString('word/endnotes.xml', '<?xml version="1.0"?><w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:endnote w:id="2"><w:p><w:r><w:t>Endnote body.</w:t></w:r></w:p></w:endnote></w:endnotes>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="3" w:author="Reviewer" w:date="2026-06-18T00:00:00Z"><w:p><w:r><w:t>Comment body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/header1.xml', '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header text.</w:t></w:r></w:p></w:hdr>');
        $zip->addFromString('word/footer1.xml', '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Footer text.</w:t></w:r></w:p></w:ftr>');
        $zip->addFromString('word/media/image1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX Reader Demo</w:t></w:r></w:p><w:p><w:r><w:t>A </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r><w:r><w:t> and </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r><w:r><w:t> run with </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>a link</w:t></w:r></w:hyperlink><w:r><w:t>, a footnote</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r><w:r><w:t>, an endnote</w:t></w:r><w:r><w:endnoteReference w:id="2"/></w:r><w:r><w:t>, and a comment</w:t></w:r><w:r><w:commentReference w:id="3"/></w:r><w:r><w:t>.</w:t></w:r><w:ins w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:t> inserted text</w:t></w:r></w:ins><w:del w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:delText> removed text</w:delText></w:r></w:del></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>First list item</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Second list item</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rId2"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('DOCX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded DOCX reader smoke.', $meta['description']);
        $t->same(1, $meta['docxFootnotes']);
        $t->same(1, $meta['docxEndnotes']);
        $t->same(1, $meta['docxComments']);
        $t->same(1, $meta['docxHeaders']);
        $t->same(1, $meta['docxFooters']);
        $noteParagraph = $document->children[1];
        $notes = array_values(array_filter($noteParagraph->children, static fn ($node): bool => $node->type === 'note'));
        $t->same(3, count($notes));
        $t->same('Footnote body.', $notes[0]->children[0]->attr('text'));
        $t->same('Endnote body.', $notes[1]->children[0]->attr('text'));
        $t->same('Comment body.', $notes[2]->children[0]->attr('text'));
        $t->true(!in_array('superscript', array_map(static fn ($node): string => $node->type, $noteParagraph->children), true), 'DOCX note references should not be wrapped in superscript AST nodes');
        $t->true(!str_contains($blocks, 'class="docx-header"'), 'Header parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'Header text.'), 'Header text should remain metadata-only in normal DOCX reader output');
        $t->true(!str_contains($blocks, 'Footer text.'), 'Footer text should remain metadata-only in normal DOCX reader output');
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<a href="https://example.test">a link</a>', $blocks);
        $t->contains('Footnote body.', $blocks);
        $t->contains('Endnote body.', $blocks);
        $t->contains('Comment body.', $blocks);
        $t->contains('<ins', $blocks);
        $t->contains('<del', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('word/media/image1.png', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'selects section-specific docx header and footer references' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHeader1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rIdFooter1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="rIdHeader2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/><Relationship Id="rIdFooter2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/><Relationship Id="rIdHeaderUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header3.xml"/><Relationship Id="rIdFooterUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer3.xml"/></Relationships>'],
            ['name' => 'word/header1.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header2.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two even header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header3.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/footer1.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one first footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer2.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer3.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Section one body</w:t></w:r><w:pPr><w:sectPr><w:headerReference w:type="default" r:id="rIdHeader1"/><w:footerReference w:type="first" r:id="rIdFooter1"/></w:sectPr></w:pPr></w:p><w:p><w:r><w:t>Section two body</w:t></w:r></w:p><w:sectPr><w:headerReference w:type="even" r:id="rIdHeader2"/><w:footerReference w:type="default" r:id="rIdFooter2"/></w:sectPr></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same(2, $meta['docxHeaders']);
        $t->same(2, $meta['docxFooters']);
        $t->same(3, $meta['docxHeaderPartCount']);
        $t->same(3, $meta['docxFooterPartCount']);
        $t->same(4, $meta['docxSectionReferenceCount']);
        $t->same(['word/header1.xml', 'word/header2.xml'], $meta['docxAppliedHeaderFiles']);
        $t->same(['word/footer1.xml', 'word/footer2.xml'], $meta['docxAppliedFooterFiles']);
        $t->same('default', $meta['docxSectionReferences'][0]['headers'][0]['type']);
        $t->same('first', $meta['docxSectionReferences'][0]['footers'][0]['type']);
        $t->same('even', $meta['docxSectionReferences'][1]['headers'][0]['type']);

        $t->same(2, count($document->children));
        $t->same('Section one body', $document->children[0]->attr('text'));
        $t->same('Section two body', $document->children[1]->attr('text'));

        $t->true(!str_contains($blocks, 'Section one header'), 'Section-referenced header parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'data-docx-section-index='), 'Section header/footer attributes should not be emitted in normal body output');
        $t->true(!str_contains($blocks, 'data-docx-section-reference-type='), 'Section header/footer reference attributes should not be emitted in normal body output');
        $t->true(!str_contains($blocks, 'Section two footer'), 'Section-referenced footer parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'Unreferenced header'), 'Unreferenced header parts should not be emitted when section references are available');
        $t->true(!str_contains($blocks, 'Unreferenced footer'), 'Unreferenced footer parts should not be emitted when section references are available');
    },
    'reads docx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Byte DOCX</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary DOCX package');
            }
            $document = PandocConverter::read($bytes, 'docx');
        } finally {
            @unlink($path);
        }

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Byte DOCX', $document->children[0]->attr('text'));
    },
    'reads native zip docx bytes while leaving unsupported media entries metadata-only' => static function (TestRunner $t) use ($buildDocxReaderNativeZipPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Native ZIP DOCX </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="7" name="Unsupported media" title="Unsupported media title" descr="Unsupported media alt"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;
        $relationshipsXml = <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unsupported.bin"/>
</Relationships>
XML;
        $contentTypesXml = <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
        $bytes = $buildDocxReaderNativeZipPackageBytes(
            [
                '[Content_Types].xml' => $contentTypesXml,
                'word/document.xml' => $documentXml,
                'word/_rels/document.xml.rels' => $relationshipsXml,
                'word/media/unsupported.bin' => 'metadata-only unsupported compression payload',
            ],
            [
                '[Content_Types].xml' => 0,
                'word/media/unsupported.bin' => 12,
            ]
        );

        $packageDocument = (new DocxReader())->readDocument(ZipPackage::fromString($bytes));
        $converterDocument = PandocConverter::read($bytes, 'docx');
        $blocks = (new WordPressBlockWriter())->write($converterDocument);
        $meta = $converterDocument->attr('meta');
        $image = $converterDocument->children[0]->children[1];

        $t->same('Native ZIP DOCX Unsupported media alt', $packageDocument->children[0]->attr('text'));
        $t->same('Native ZIP DOCX Unsupported media alt', $converterDocument->children[0]->attr('text'));
        $t->same(4, $meta['docxPackageEntries']);
        $t->same(['word/media/unsupported.bin'], $meta['docxMediaFiles']);
        $t->same(1, $meta['docxRelationshipCount']);
        $t->same('image', $image->type);
        $t->same('word/media/unsupported.bin', $image->attr('url'));
        $t->same('Unsupported media alt', $image->attr('alt'));
        $t->same('Unsupported media alt', $image->children[0]->attr('text'));
        $t->contains('word/media/unsupported.bin', $blocks);
    },
    'preserves docx numbering levels styles starts and delimiters' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="42"><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="upperRoman"/><w:lvlText w:val="(%2)"/></w:lvl></w:abstractNum><w:num w:numId="7"><w:abstractNumId w:val="42"/></w:num></w:numbering>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha three</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Nested roman</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha four</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
        } finally {
            @unlink($path);
        }

        $list = $document->children[0];
        $firstItem = $list->children[0];
        $nested = $firstItem->children[1];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same('one_paren', $list->attr('delimiter'));
        $t->same('list_item', $firstItem->type);
        $t->same('Alpha three', $firstItem->children[0]->attr('text'));
        $t->same('ordered_list', $nested->type);
        $t->same('upper_roman', $nested->attr('style'));
        $t->same('two_parens', $nested->attr('delimiter'));
        $t->same('Nested roman', $nested->children[0]->children[0]->attr('text'));
        $t->same('Alpha four', $list->children[1]->children[0]->attr('text'));
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a" data-pandoc-list-style="lower_alpha" data-pandoc-list-delimiter="one_paren">', $blocks);
        $t->contains('<ol type="I" data-pandoc-list-style="upper_roman" data-pandoc-list-delimiter="two_parens">', $blocks);
        $t->contains('<ol start="3" type="a"><li>Alpha three<ol type="I"><li>Nested roman</li></ol></li><li>Alpha four</li></ol>', $converterBlocks);
    },
    'reads docx bookmarks reference fields and omml equations into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><w:body><w:p><w:bookmarkStart w:id="7" w:name="_RefEquation"/><w:r><w:t>Equation target</w:t></w:r><w:bookmarkEnd w:id="7"/></w:p><w:p><w:r><w:t>See </w:t></w:r><w:fldSimple w:instr=" REF _RefEquation \h "><w:r><w:t>Equation target</w:t></w:r></w:fldSimple><w:r><w:t>: </w:t></w:r><m:oMath><m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup><m:r><m:t>+y</m:t></m:r></m:oMath></w:p><m:oMathPara><m:oMath><m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>n</m:t></m:r></m:den></m:f></m:oMath></m:oMathPara></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $target = $document->children[0];
        $reference = $document->children[1];
        $display = $document->children[2];

        $t->same('Equation target', $target->attr('text'));
        $t->same('See Equation target: x^{2}+y', $reference->attr('text'));
        $t->same('raw_inline', $target->children[0]->type);
        $t->same('openxml', $target->children[0]->attr('format'));
        $t->contains('w:name="_RefEquation"', $target->children[0]->attr('text'));
        $t->same('raw_inline', $target->children[2]->type);
        $t->same('link', $reference->children[1]->type);
        $t->same('#_RefEquation', $reference->children[1]->attr('url'));
        $t->same('math', $reference->children[3]->type);
        $t->same('x^{2}+y', $reference->children[3]->attr('text'));
        $t->same('plain', $display->type);
        $t->same('math', $display->children[0]->type);
        $t->same(true, $display->children[0]->attr('display'));
        $t->same('\\frac{1}{n}', $display->children[0]->attr('text'));
        $t->contains('class="pandoc-openxml-bookmark-start"', $blocks);
        $t->contains('data-pandoc-bookmark-name="_RefEquation"', $blocks);
        $t->contains('<a href="#_RefEquation"', $blocks);
        $t->contains('>Equation target</a>', $blocks);
        $t->contains('<span class="math inline">\\(x^{2}+y\\)</span>', $blocks);
        $t->contains('<span class="math display">\\[\\frac{1}{n}\\]</span>', $blocks);
    },
    'parses docx hyperlink field instructions into links' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:fldSimple w:instr=' HYPERLINK "https://example.test/simple" \o "Simple title" '>
        <w:r><w:t>Simple field</w:t></w:r>
      </w:fldSimple>
    </w:p>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK "https://example.test/complex" \o "Complex title" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Complex field</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
    </w:p>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK \l "LocalTarget" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Local field</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
    </w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $simple = $document->children[0]->children[0];
        $complex = $document->children[1]->children[0];
        $local = $document->children[2]->children[0];

        $t->same('link', $simple->type);
        $t->same('https://example.test/simple', $simple->attr('url'));
        $t->same('Simple title', $simple->attr('title'));
        $t->same('HYPERLINK "https://example.test/simple" \o "Simple title"', $simple->attr('attributes')['data-docx-field']);
        $t->same('Simple field', $simple->children[0]->attr('text'));

        $t->same('link', $complex->type);
        $t->same('https://example.test/complex', $complex->attr('url'));
        $t->same('Complex title', $complex->attr('title'));
        $t->same('Complex field', $complex->children[0]->attr('text'));

        $t->same('link', $local->type);
        $t->same('#LocalTarget', $local->attr('url'));
        $t->same('Local field', $local->children[0]->attr('text'));

        $t->contains('<a href="https://example.test/simple" title="Simple title"', $blocks);
        $t->contains('data-docx-field="HYPERLINK &quot;https://example.test/complex&quot; \o &quot;Complex title&quot;"', $blocks);
        $t->contains('<a href="#LocalTarget"', $blocks);
    },
    'canonicalizes docx heading bookmark reference targets' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:bookmarkStart w:id="7" w:name="_RefHeading"/>
      <w:bookmarkStart w:id="8" w:name="_GoBack"/>
      <w:bookmarkEnd w:id="8"/>
      <w:r><w:t>Target Heading</w:t></w:r>
      <w:bookmarkEnd w:id="7"/>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Page </w:t></w:r>
      <w:fldSimple w:instr=" PAGEREF _RefHeading \h "><w:r><w:t>2</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> and ref </w:t></w:r>
      <w:fldSimple w:instr=" REF _RefHeading \h "><w:r><w:t>Target Heading</w:t></w:r></w:fldSimple>
    </w:p>
    <w:p>
      <w:hyperlink w:anchor="_RefHeading"><w:r><w:t>Direct heading link</w:t></w:r></w:hyperlink>
    </w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $heading = $document->children[0];
        $fieldParagraph = $document->children[1];
        $pageRef = $fieldParagraph->children[1];
        $crossRef = $fieldParagraph->children[3];
        $directLink = $document->children[2]->children[0];

        $t->same('heading', $heading->type);
        $t->same('target-heading', $heading->attr('id'));
        $t->same('Target Heading', $heading->attr('text'));
        $t->same(1, count($heading->children));
        $t->same('Target Heading', $heading->children[0]->attr('text'));

        $t->same('link', $pageRef->type);
        $t->same('#target-heading', $pageRef->attr('url'));
        $t->same('link', $crossRef->type);
        $t->same('#target-heading', $crossRef->attr('url'));
        $t->same('link', $directLink->type);
        $t->same('#target-heading', $directLink->attr('url'));

        $t->contains('<h1 id="target-heading">Target Heading</h1>', $blocks);
        $t->contains('<a href="#target-heading" data-docx-field="PAGEREF _RefHeading \h">2</a>', $blocks);
        $t->contains('<a href="#target-heading" data-docx-field="REF _RefHeading \h">Target Heading</a>', $blocks);
        $t->contains('<a href="#target-heading">Direct heading link</a>', $blocks);
        $t->true(!str_contains($blocks, '_GoBack'), 'Word _GoBack bookmarks should not leak into rendered output');
        $t->true(!str_contains($blocks, 'pandoc-openxml-bookmark-start'), 'Heading bookmarks should canonicalize to the heading id instead of raw bookmark spans');
    },
    'suppresses generated docx ole link bookmarks without dropping real bookmarks' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:bookmarkStart w:id="4" w:name="KeepMe"/><w:r><w:t>Kept bookmark</w:t></w:r><w:bookmarkEnd w:id="4"/></w:p><w:p><w:bookmarkStart w:id="9" w:name="OLE_LINK12"/><w:r><w:t>Generated bookmark wrapper</w:t></w:r><w:bookmarkEnd w:id="9"/></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('raw_inline', $document->children[0]->children[0]->type);
        $t->contains('w:name="KeepMe"', $document->children[0]->children[0]->attr('text'));
        $t->contains('data-pandoc-bookmark-name="KeepMe"', $blocks);
        $t->true(!str_contains($blocks, 'OLE_LINK12'), 'generated OLE_LINK bookmarks should not leak into rendered output');
        $t->true(!str_contains((new NativeWriter())->write($document), 'OLE_LINK12'), 'generated OLE_LINK bookmarks should not leak into native output');
    },
    'preserves docx hyperlink relationship anchors and tracked change contents' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:hyperlink r:id="rIdExternal" w:anchor="Section_2"><w:r><w:t xml:space="preserve">See </w:t></w:r><w:ins w:author="Anchor Reviewer" w:date="2026-06-30T00:00:00Z"><w:r><w:t>inserted anchor</w:t></w:r></w:ins></w:hyperlink><w:r><w:t> after.</w:t></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];
        $link = $paragraph->children[0];

        $t->same('See inserted anchor after.', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/review#Section_2', $link->attr('url'));
        $t->same('See ', $link->children[0]->attr('text'));
        $t->same('span', $link->children[1]->type);
        $t->same(['insertion'], $link->children[1]->attr('classes'));
        $t->same('Anchor Reviewer', $link->children[1]->attr('attributes')['author']);
        $t->same('2026-06-30T00:00:00Z', $link->children[1]->attr('attributes')['date']);
        $t->contains('<a href="https://example.test/review#Section_2">See <ins class="insertion" data-pandoc-change-author="Anchor Reviewer"', $blocks);
        $t->contains('data-pandoc-change-date="2026-06-30T00:00:00Z"', $blocks);
        $t->contains('>inserted anchor</ins></a> after.', $blocks);
    },
    'reads docx packages whose office document part uses an alternate path' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/word/document2.xml"/></Relationships>'],
            ['name' => '[Content_Types].xml', 'data' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>'],
            ['name' => 'word/_rels/document2.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/alternate" TargetMode="External"/></Relationships>'],
            ['name' => 'word/document2.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Alternate </w:t></w:r><w:hyperlink r:id="rIdExternal"><w:r><w:t>document path</w:t></w:r></w:hyperlink></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $paragraph = $document->children[0];
        $link = $paragraph->children[1];

        $t->same('Alternate document path', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/alternate', $link->attr('url'));
        $t->same('document path', $link->children[0]->attr('text'));
    },
    'normalizes alternate part drawing and vml image relationship targets' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/word/sub/document.xml"/>
</Relationships>
XML],
            ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/sub/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML],
            ['name' => 'word/sub/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/drawing.png"/>
  <Relationship Id="rVml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="..\media\vml-preview.png"/>
</Relationships>
XML],
            ['name' => 'word/sub/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="914400" cy="457200"/>
            <wp:docPr id="11" name="Nested Drawing" descr="Nested drawing alt"/>
            <a:graphic>
              <a:graphicData>
                <pic:pic>
                  <pic:blipFill><a:blip r:embed="rDrawing"/></pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:pict>
          <v:shape id="NestedVml" style="width:36pt;height:18pt">
            <v:imagedata r:id="rVml" o:title="Nested VML"/>
          </v:shape>
        </w:pict>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
            ['name' => 'word/media/drawing.png', 'data' => 'drawing bytes'],
            ['name' => 'word/media/vml-preview.png', 'data' => 'vml bytes'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $drawing = $document->children[0]->children[0];
        $vml = $document->children[1]->children[0];

        $t->same('image', $drawing->type);
        $t->same('word/media/drawing.png', $drawing->attr('url'));
        $t->same('1in', $drawing->attr('width'));
        $t->same('0.5in', $drawing->attr('height'));
        $t->same('Nested drawing alt', $drawing->attr('alt'));
        $t->same('Nested drawing alt', $drawing->children[0]->attr('text'));
        $t->same('rDrawing', $drawing->attr('attributes')['data-docx-image-relationship-id']);

        $t->same('image', $vml->type);
        $t->same('word/media/vml-preview.png', $vml->attr('url'));
        $t->same('36pt', $vml->attr('width'));
        $t->same('18pt', $vml->attr('height'));
        $t->same('Nested VML', $vml->attr('title'));
        $t->same('Nested VML', $vml->children[0]->attr('text'));
        $t->same('rVml', $vml->attr('attributes')['data-docx-image-relationship-id']);

        $t->contains('src="word/media/drawing.png"', $blocks);
        $t->contains('src="word/media/vml-preview.png"', $blocks);
    },
    'preserves drawingml diagram and chart placeholders' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/review-data.xml"/>
  <Relationship Id="rDiagramLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/review-layout.xml"/>
  <Relationship Id="rDiagramStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/review-style.xml"/>
  <Relationship Id="rDiagramColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/review-colors.xml"/>
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/review-chart.xml"/>
</Relationships>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="19" name="Review workflow" descr="Imported workflow diagram" title="Review workflow"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram">
                <dgm:relIds r:dm="rDiagramData" r:lo="rDiagramLayout" r:qs="rDiagramStyle" r:cs="rDiagramColors"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="18" name="Review chart" descr="Imported review chart" title="Review chart"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                <c:chart r:id="rChart"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagram = $document->children[0]->children[0];
        $chart = $document->children[1]->children[0];

        $t->same('[DIAGRAM]', $document->children[0]->attr('text'));
        $t->same('span', $diagram->type);
        $t->same(['diagram'], $diagram->attr('classes'));
        $t->same('[DIAGRAM]', $diagram->children[0]->attr('text'));

        $t->same('[CHART]', $document->children[1]->attr('text'));
        $t->same('span', $chart->type);
        $t->same(['chart'], $chart->attr('classes'));
        $t->same('[CHART]', $chart->children[0]->attr('text'));

        $t->contains('class="diagram" data-pandoc-diagram="unsupported-docx-diagram"', $blocks);
        $t->contains('<span class="chart">[CHART]</span>', $blocks);
    },
    'maps upstream docx paragraph block styles to code quotes and definitions' => static function (TestRunner $t): void {
        $stylesXml = <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="SourceCode"><w:name w:val="Source Code"/></w:style>
  <w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/></w:style>
  <w:style w:type="paragraph" w:styleId="DefinitionTerm"><w:name w:val="Definition Term"/></w:style>
  <w:style w:type="paragraph" w:styleId="Definition"><w:name w:val="Definition"/></w:style>
</w:styles>
XML;
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Before</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr><w:r><w:t>alpha</w:t><w:br/><w:t>beta</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr><w:r><w:t>gamma</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Quote"/></w:pPr><w:r><w:t>Styled quote</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="1440"/></w:pPr><w:r><w:t>Indented quote</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DefinitionTerm"/></w:pPr><w:r><w:t>Term</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Definition"/></w:pPr><w:r><w:t>Definition one</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Definition"/></w:pPr><w:r><w:t>Definition two</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
        $document = (new DocxReader())->readDocument(ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => $stylesXml],
            ['name' => 'word/document.xml', 'data' => $documentXml],
        ]));

        $t->same(['paragraph', 'code_block', 'blockquote', 'definition_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same("alpha\nbeta\n\ngamma", $document->children[1]->attr('text'));
        $t->same(['Styled quote', 'Indented quote'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[2]->children));
        $definitionItem = $document->children[3]->children[0];
        $t->same('Term', $definitionItem->children[0]->attr('text'));
        $t->same(['Definition one', 'Definition two'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $definitionItem->children[1]->children));
    },
    'keeps styled indented docx paragraphs out of quote fallback' => static function (TestRunner $t): void {
        $stylesXml = <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ImagePara"><w:name w:val="Image Paragraph"/><w:pPr><w:ind w:left="2234"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:ind w:left="720"/></w:pPr></w:style>
</w:styles>
XML;
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="ImagePara"/><w:ind w:left="1440"/></w:pPr><w:r><w:t>Styled indent</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:ind w:left="1134"/></w:pPr><w:r><w:t>Relative indent</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="1440"/></w:pPr><w:r><w:t>Plain indent</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $document = (new DocxReader())->readDocument(ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => $stylesXml],
            ['name' => 'word/document.xml', 'data' => $documentXml],
        ]));

        $t->same(['paragraph', 'blockquote'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Styled indent', $document->children[0]->attr('text'));
        $t->same(['Relative indent', 'Plain indent'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[1]->children));
    },
    'unwraps docx smart tags and alternate content fallback in body inline field and table scopes' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"><w:body><w:smartTag w:uri="urn:example" w:element="body"><w:p><w:r><w:t>Wrapped block</w:t></w:r></w:p></w:smartTag><w:p><w:r><w:t>Inline </w:t></w:r><w:smartTag w:uri="urn:example" w:element="inline"><w:r><w:rPr><w:b/></w:rPr><w:t>smart</w:t></w:r></w:smartTag><w:r><w:t> and </w:t></w:r><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:t>choice</w:t></mc:Choice><mc:Fallback><w:t>fallback</w:t></mc:Fallback></mc:AlternateContent></w:r></w:p><w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> REF _SmartTarget \h </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:smartTag w:uri="urn:example" w:element="field-result"><w:r><w:t>Smart target</w:t></w:r></w:smartTag><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p><w:tbl><w:tr><w:tc><w:smartTag w:uri="urn:example" w:element="cell"><w:p><w:r><w:t>Cell smart</w:t></w:r></w:p></w:smartTag></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));
        $t->same('Wrapped block', $document->children[0]->attr('text'));

        $inline = $document->children[1];
        $t->same('Inline smart and fallback', $inline->attr('text'));
        $t->same('strong', $inline->children[1]->type);
        $t->same('smart', $inline->children[1]->children[0]->attr('text'));
        $t->same(' and fallback', $inline->children[2]->attr('text'));

        $field = $document->children[2]->children[0];
        $t->same('link', $field->type);
        $t->same('#_SmartTarget', $field->attr('url'));
        $t->same('Smart target', $field->children[0]->attr('text'));

        $cell = $document->children[3]->children[1]->children[0]->children[0];
        $t->same('Cell smart', $cell->attr('text'));
        $t->same('Cell smart', $cell->children[0]->attr('text'));

        $t->contains('<p>Wrapped block</p>', $blocks);
        $t->contains('Inline <strong>smart</strong> and fallback', $blocks);
        $t->contains('<a href="#_SmartTarget"', $blocks);
        $t->contains('<td><p>Cell smart</p></td>', $blocks);
        $t->true(!str_contains($blocks, 'choice'), 'AlternateContent choice branch should not be emitted when a fallback is available');
    },
    'preserves docx content controls with block inline and table metadata' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:sdt><w:sdtPr><w:alias w:val="Customer Name"/><w:tag w:val="customer.name"/><w:id w:val="42"/><w:lock w:val="sdtContentLocked"/><w:placeholder><w:docPart w:val="CustomerPlaceholder"/></w:placeholder><w:dataBinding w:xpath="/root/customer/name" w:storeItemID="{11111111-1111-1111-1111-111111111111}" w:prefixMappings="xmlns:c=&apos;urn:customer&apos;"/><w:text w:multiLine="1"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Ada Lovelace</w:t></w:r></w:p></w:sdtContent></w:sdt><w:p><w:r><w:t>Status: </w:t></w:r><w:sdt><w:sdtPr><w:alias w:val="Status choice"/><w:tag w:val="status"/><w:id w:val="43"/><w:dropDownList><w:listItem w:displayText="Draft" w:value="draft"/><w:listItem w:displayText="Approved" w:value="approved"/></w:dropDownList></w:sdtPr><w:sdtContent><w:r><w:t>Approved</w:t></w:r></w:sdtContent></w:sdt></w:p><w:tbl><w:tr><w:tc><w:sdt><w:sdtPr><w:alias w:val="Signed date"/><w:tag w:val="signed.date"/><w:id w:val="44"/><w:date w:fullDate="2026-06-26T00:00:00Z"><w:dateFormat w:val="MMMM d, yyyy"/><w:lid w:val="en-US"/></w:date></w:sdtPr><w:sdtContent><w:p><w:r><w:t>June 26, 2026</w:t></w:r></w:p></w:sdtContent></w:sdt></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $blockControl = $document->children[0];
        $blockAttrs = $blockControl->attr('attributes');
        $paragraph = $document->children[1];
        $inlineControl = $paragraph->children[1];
        $inlineAttrs = $inlineControl->attr('attributes');
        $table = $document->children[2];
        $tableCell = $table->children[1]->children[0]->children[0];
        $dateControl = $tableCell->children[0];
        $dateAttrs = $dateControl->attr('attributes');

        $t->same('div', $blockControl->type);
        $t->same(['docx-content-control', 'docx-content-control-block'], $blockControl->attr('classes'));
        $t->same('block', $blockAttrs['data-docx-content-control-display']);
        $t->same('text', $blockAttrs['data-docx-content-control-type']);
        $t->same('Customer Name', $blockAttrs['data-docx-content-control-alias']);
        $t->same('customer.name', $blockAttrs['data-docx-content-control-tag']);
        $t->same('42', $blockAttrs['data-docx-content-control-id']);
        $t->same('sdtContentLocked', $blockAttrs['data-docx-content-control-lock']);
        $t->same('CustomerPlaceholder', $blockAttrs['data-docx-content-control-placeholder-doc-part']);
        $t->same('/root/customer/name', $blockAttrs['data-docx-content-control-binding-xpath']);
        $t->same('{11111111-1111-1111-1111-111111111111}', $blockAttrs['data-docx-content-control-binding-store-item-id']);
        $t->same("xmlns:c='urn:customer'", $blockAttrs['data-docx-content-control-binding-prefix-mappings']);
        $t->same('true', $blockAttrs['data-docx-content-control-text-multiline']);
        $t->same('Ada Lovelace', $blockControl->children[0]->attr('text'));

        $t->same('Status: Approved', $paragraph->attr('text'));
        $t->same('span', $inlineControl->type);
        $t->same(['docx-content-control', 'docx-content-control-inline'], $inlineControl->attr('classes'));
        $t->same('inline', $inlineAttrs['data-docx-content-control-display']);
        $t->same('dropDownList', $inlineAttrs['data-docx-content-control-type']);
        $t->same('Status choice', $inlineAttrs['data-docx-content-control-alias']);
        $t->same('2', $inlineAttrs['data-docx-content-control-list-item-count']);
        $t->same('Draft Approved', $inlineAttrs['data-docx-content-control-list-display-texts']);
        $t->same('draft approved', $inlineAttrs['data-docx-content-control-list-values']);

        $t->same('June 26, 2026', $tableCell->attr('text'));
        $t->same('div', $dateControl->type);
        $t->same('date', $dateAttrs['data-docx-content-control-type']);
        $t->same('Signed date', $dateAttrs['data-docx-content-control-alias']);
        $t->same('2026-06-26T00:00:00Z', $dateAttrs['data-docx-content-control-date-full']);
        $t->same('MMMM d, yyyy', $dateAttrs['data-docx-content-control-date-format']);
        $t->same('en-US', $dateAttrs['data-docx-content-control-date-language-id']);

        $t->contains('<div class="docx-content-control docx-content-control-block" data-docx-content-control-display="block"', $blocks);
        $t->contains('data-docx-content-control-binding-xpath="/root/customer/name"', $blocks);
        $t->contains('<span class="docx-content-control docx-content-control-inline" data-docx-content-control-display="inline"', $blocks);
        $t->contains('data-docx-content-control-list-values="draft approved"', $blocks);
        $t->contains('data-docx-content-control-date-full="2026-06-26T00:00:00Z"', $blocks);
    },
    'places docx table header rows in table head' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:trPr><w:tblHeader/></w:trPr><w:tc><w:p><w:r><w:t>Field</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:trPr><w:tblHeader w:val="0"/></w:trPr><w:tc><w:p><w:r><w:t>Draft flag</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>False header row</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Total</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>12</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];

        $t->same('table', $table->type);
        $t->same('table_head', $head->type);
        $t->same(1, count($head->children));
        $t->same('Field', $head->children[0]->children[0]->attr('text'));
        $t->same('Value', $head->children[0]->children[1]->attr('text'));
        $t->same('table_body', $body->type);
        $t->same(2, count($body->children));
        $t->same('Draft flag', $body->children[0]->children[0]->attr('text'));
        $t->same('Total', $body->children[1]->children[0]->attr('text'));

        $t->contains('<thead><tr><th><p>Field</p></th><th><p>Value</p></th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td><p>Draft flag</p></td><td><p>False header row</p></td></tr><tr><td><p>Total</p></td><td><p>12</p></td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<th><p>Draft flag</p></th>'), 'Explicitly disabled tblHeader rows should stay in the table body');
    },
    'preserves docx table gridBefore omitted leading cells' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:trPr><w:tblHeader/><w:gridBefore w:val="1"/></w:trPr><w:tc><w:p><w:r><w:t>Field</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:trPr><w:gridBefore w:val="2"/></w:trPr><w:tc><w:p><w:r><w:t>North</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>12</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $table = $document->children[0];
        $headRow = $table->children[0]->children[0];
        $bodyRow = $table->children[1]->children[0];

        $t->same(3, count($headRow->children));
        $t->same('', $headRow->children[0]->attr('text'));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $headRow->children[0]->attr('htmlAttributes'));
        $t->same('Field', $headRow->children[1]->attr('text'));
        $t->same('Value', $headRow->children[2]->attr('text'));

        $t->same(4, count($bodyRow->children));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $bodyRow->children[0]->attr('htmlAttributes'));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $bodyRow->children[1]->attr('htmlAttributes'));
        $t->same('North', $bodyRow->children[2]->attr('text'));
        $t->same('12', $bodyRow->children[3]->attr('text'));

        $t->contains('<thead><tr><th data-docx-omitted-cell="gridBefore"></th><th><p>Field</p></th><th><p>Value</p></th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td data-docx-omitted-cell="gridBefore"></td><td data-docx-omitted-cell="gridBefore"></td><td><p>North</p></td><td><p>12</p></td></tr></tbody>', $blocks);
    },
    'reads docx content control wrapped table cells' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Head</w:t></w:r></w:p></w:tc><w:sdt><w:sdtContent><w:tc><w:p><w:r><w:t>Body copy</w:t></w:r></w:p></w:tc></w:sdtContent></w:sdt><w:tc><w:p><w:r><w:t>Tail</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $row = $document->children[0]->children[1]->children[0];

        $t->same(3, count($row->children));
        $t->same('Head', $row->children[0]->attr('text'));
        $t->same('Body copy', $row->children[1]->attr('text'));
        $t->same('Tail', $row->children[2]->attr('text'));
        $t->contains('<td><p>Body copy</p></td>', $blocks);
    },
    'preserves docx comment ranges moves table merges styles and image metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/chart.png"/></Relationships>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="5" w:author="Range Reviewer" w:date="2026-06-26T00:00:00Z"><w:p><w:r><w:t>Range body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/media/chart.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:commentRangeStart w:id="5"/><w:r><w:t>Ranged comment</w:t></w:r><w:commentRangeEnd w:id="5"/><w:r><w:commentReference w:id="5"/></w:r></w:p><w:p><w:moveFrom w:id="8" w:author="Mover" w:date="2026-06-26T00:00:00Z"><w:r><w:delText>old spot</w:delText></w:r></w:moveFrom><w:r><w:t> to </w:t></w:r><w:moveTo w:id="8" w:author="Mover" w:date="2026-06-26T00:01:00Z"><w:r><w:t>new spot</w:t></w:r></w:moveTo><w:r><w:rPr><w:u w:val="single"/><w:strike/><w:vertAlign w:val="superscript"/></w:rPr><w:t> styled</w:t></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="ReviewTable"/></w:tblPr><w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/><w:shd w:fill="FFFF00"/><w:vAlign w:val="center"/></w:tcPr><w:p><w:r><w:t>Group</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Top</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p><w:r><w:t>Skipped continuation</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Bottom</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="914400"/><wp:docPr id="9" name="Chart 1" descr="Chart alt" title="Chart title"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $comment = $document->children[0];
        $move = $document->children[1];
        $table = $document->children[2];
        $image = $document->children[3]->children[0];

        $t->same('span', $comment->children[0]->type);
        $t->same(['comment-start'], $comment->children[0]->attr('classes'));
        $t->same('5', $comment->children[0]->attr('attributes')['id']);
        $t->same('Range Reviewer', $comment->children[0]->attr('attributes')['author']);
        $t->same(['comment-end'], $comment->children[2]->attr('classes'));
        $t->same(['deletion', 'move-from'], $move->children[0]->attr('classes'));
        $t->same(['insertion', 'move-to'], $move->children[2]->attr('classes'));
        $t->same('superscript', $move->children[3]->type);
        $t->same('strikeout', $move->children[3]->children[0]->type);
        $t->same('underline', $move->children[3]->children[0]->children[0]->type);
        $t->same('ReviewTable', $table->attr('htmlAttributes')['data-docx-table-style']);
        $firstCell = $table->children[1]->children[0]->children[0];
        $secondRow = $table->children[1]->children[1];
        $t->same(2, $firstCell->attr('rowspan'));
        $t->same('restart', $firstCell->attr('htmlAttributes')['data-docx-vmerge']);
        $t->same('background-color:#FFFF00; vertical-align:middle', $firstCell->attr('htmlAttributes')['style']);
        $t->same(1, count($secondRow->children));
        $t->same('Bottom', $secondRow->children[0]->attr('text'));
        $t->same('Chart alt', $image->attr('alt'));
        $t->same('Chart title', $image->attr('title'));
        $t->same('2in', $image->attr('width'));
        $t->same('1in', $image->attr('height'));
        $t->same('Chart 1', $image->attr('attributes')['data-docx-image-name']);
        $t->same('9', $image->attr('attributes')['data-docx-image-id']);
        $t->contains('class="comment-start" data-pandoc-comment-id="5" data-pandoc-comment-author="Range Reviewer"', $blocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('<sup><del><u> styled</u></del></sup>', $blocks);
        $t->contains('<table data-docx-table-style="ReviewTable">', $blocks);
        $t->contains('data-docx-vmerge="restart" rowspan="2" style="background-color:#FFFF00; vertical-align:middle"', $blocks);
        $t->contains('<td><p>Bottom</p></td>', $blocks);
        $t->contains('alt="Chart alt" title="Chart title" data-pandoc-width="2in" data-pandoc-height="1in"', $blocks);
        $t->contains('data-docx-image-name="Chart 1"', $blocks);
    },
    'reads docx text boxes vml object images and inherited table style metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdObject" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/object-preview.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="BaseChar"><w:name w:val="Base Character"/><w:rPr><w:b/></w:rPr></w:style><w:style w:type="character" w:styleId="DerivedChar"><w:name w:val="Derived Character"/><w:basedOn w:val="BaseChar"/><w:rPr><w:i/></w:rPr></w:style><w:style w:type="table" w:styleId="BaseTable"><w:name w:val="Base Table"/><w:tblPr><w:shd w:fill="D9EAF7"/><w:jc w:val="center"/></w:tblPr></w:style><w:style w:type="table" w:styleId="DerivedTable"><w:name w:val="Derived Table"/><w:basedOn w:val="BaseTable"/></w:style></w:styles>');
        $zip->addFromString('word/media/object-preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office"><w:body><w:p><w:r><w:t>Before </w:t></w:r><w:r><w:pict><v:shape id="TextBox1" type="#_x0000_t202" style="width:120pt;height:40pt"><v:textbox><w:txbxContent><w:p><w:r><w:t>Boxed </w:t></w:r><w:r><w:rPr><w:rStyle w:val="DerivedChar"/></w:rPr><w:t>strong italic</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r><w:r><w:t> after.</w:t></w:r></w:p><w:p><w:r><w:object><v:shape id="_x0000_i1025" type="#_x0000_t75" style="width:48pt;height:24pt"><v:imagedata r:id="rIdObject" o:title="Object preview"/></v:shape><w:dxaOrig w:val="960"/><w:dyaOrig w:val="480"/></w:object></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="DerivedTable"/></w:tblPr><w:tr><w:tc><w:p><w:r><w:t>Styled cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[0];
        $textBox = $paragraph->children[1];
        $styledText = $textBox->children[1];
        $image = $document->children[1]->children[0];
        $table = $document->children[2];
        $tableAttributes = $table->attr('htmlAttributes');

        $t->same('Before Boxed strong italic after.', $paragraph->attr('text'));
        $t->same('span', $textBox->type);
        $t->same(['docx-textbox'], $textBox->attr('classes'));
        $t->same('vml-pict', $textBox->attr('attributes')['data-docx-textbox-source']);
        $t->same('TextBox1', $textBox->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('emph', $styledText->type);
        $t->same('strong', $styledText->children[0]->type);
        $t->same('strong italic', $styledText->children[0]->children[0]->attr('text'));

        $t->same('image', $image->type);
        $t->same('word/media/object-preview.png', $image->attr('url'));
        $t->same('Object preview', $image->attr('alt'));
        $t->same('Object preview', $image->attr('title'));
        $t->same('48pt', $image->attr('width'));
        $t->same('24pt', $image->attr('height'));
        $t->same('vml-object', $image->attr('attributes')['data-docx-image-source']);
        $t->same('rIdObject', $image->attr('attributes')['data-docx-image-relationship-id']);
        $t->same('_x0000_i1025', $image->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('960', $image->attr('attributes')['data-docx-object-dxa-orig']);
        $t->same('480', $image->attr('attributes')['data-docx-object-dya-orig']);

        $t->same('DerivedTable', $tableAttributes['data-docx-table-style']);
        $t->same('Derived Table', $tableAttributes['data-docx-table-style-name']);
        $t->same('BaseTable', $tableAttributes['data-docx-table-style-based-on']);
        $t->same('BaseTable DerivedTable', $tableAttributes['data-docx-table-style-chain']);
        $t->same('D9EAF7', $tableAttributes['data-docx-table-style-fill']);
        $t->same('center', $tableAttributes['data-docx-table-style-align']);

        $t->contains('<span class="docx-textbox" data-docx-textbox-source="vml-pict"', $blocks);
        $t->contains('Boxed <em><strong>strong italic</strong></em>', $blocks);
        $t->contains('data-docx-image-source="vml-object"', $blocks);
        $t->contains('data-docx-vml-shape-id="_x0000_i1025"', $blocks);
        $t->contains('data-docx-table-style-chain="BaseTable DerivedTable"', $blocks);
        $t->contains('data-docx-table-style-fill="D9EAF7"', $blocks);
    },
];
