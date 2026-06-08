# pandoc-docx-openxml-core-current-base-20260608T204255Z

## Behavior

Mapped one bounded DOCX/OpenXML support case on accepted base
`7f445a777953c574072f47108c6d636ab15622a9`: `DocxReader` now preserves
WordprocessingML `w:subDoc` run references as reviewer-visible placeholder
spans instead of dropping the anchor from the body text.

The implementation follows the OpenXML subdocument relationship contract
documented by Microsoft Learn for `SubDocumentReference`: `w:subDoc` carries an
`r:id` pointing at a `subDocument` relationship, and non-external or missing
targets are non-conformant review issues. The reader records the relationship
id/type/target, external target preflight metadata, missing relationship ids,
missing relationships, unexpected relationship types, and internal package
targets without recursively importing nested office files.

Source truth:
<https://learn.microsoft.com/en-us/dotnet/api/documentformat.openxml.wordprocessing.subdocumentreference?view=openxml-3.0.1>

## Evidence

- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3016 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3097 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  passed with `docx body handoff self-test ok`.
- Expected lane movement: `+1` PHP PASS case, `+81` focused DOCX assertions,
  mapped upstream `2248 -> 2249`, DOCX/OpenXML core cases `33 -> 34`, and
  DOCX/OpenXML core assertions `385 -> 466`.

## Non-Overlap

This does not repeat recent DOCX/OpenXML slices for embedded OLE/package
objects, altChunk imports, document settings, glossary parts, run language/RTL,
tracked formatting changes, deleted OMML, fields, notes/comments, tables,
section geometry, or structured document tag form controls. It is limited to
master-document subdocument anchors and relationship diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `DocxReader`
WordprocessingML parsing, OPC relationship resolution, in-memory `ZipPackage`
fixtures, `AstNode` spans, `MarkdownWriter`, `WordPressBlockWriter`, and the
existing DOCX body handoff example.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runner, external office
tool, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

Next DOCX/OpenXML work should stay bounded to non-overlapping body/style/package
gaps such as richer style inheritance edge cases, additional numbering metadata,
header/footer relationship variants, or package relationship diagnostics not
already covered by altChunk, embedded objects, subdocuments, settings, glossary,
media, notes, comments, fields, revisions, tables, or section geometry.
