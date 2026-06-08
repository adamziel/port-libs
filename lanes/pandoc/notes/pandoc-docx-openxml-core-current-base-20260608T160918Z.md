# pandoc-docx-openxml-core-current-base-20260608T160918Z

Accepted base: `d4dade701f14fb2b26e0c359f97ad9c5febe3948`

## Behavior

This slice adds bounded native PHP DOCX/OpenXML support for visible DrawingML
shape text:

- `DocxReader` now extracts `a:txBody` text inside `w:drawing` and emits a
  `.docx-drawing-text` reviewer span instead of dropping the visible shape text.
- Drawing text preserves `wp:docPr` id, name, description, and title provenance
  in `data-docx-*` attributes.
- DrawingML `a:br` and separate non-empty `a:p` paragraphs become inline
  linebreaks, so Word callouts and shape notes remain readable in Markdown and
  WordPress output.
- Relationship-backed image, chart, and diagram drawing handling remains
  unchanged; text-only shapes also work when the document has no part-local
  relationships.

Upstream source-truth basis: pinned Pandoc `src/Text/Pandoc/Readers/Docx/Parse.hs`
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` treats drawing-contained
WordprocessingML as visible document content rather than package metadata. This
slice ports the smallest safe native PHP behavior for the common DrawingML shape
text body form, without executing Pandoc or office tools.

## Non-Overlap

This does not duplicate accepted DOCX/OpenXML slices for media relationship
resolution, VML image extraction, chart/diagram placeholders, captioned figures,
table captions, field hyperlinks, bookmarks, comments, tracked formatting
changes, deleted OMML math, embedded OLE/package placeholders, document
settings, section/header/footer metadata, glossary parts, smart tags, custom XML,
content controls, textboxes backed by `w:txbxContent`, symbol/ruby runs,
paragraph/run styling, or altChunk import.

## Evidence

- Baseline focused run before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  -> `1 test files, 2701 assertions, 0 failures`.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  -> `1 test files, 2724 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  -> `docx body handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  all reported no syntax errors.

## Counters

- `phpPass`: `1695 -> 1696`
- `benchmarkDenominator.mapped`: `2115 -> 2116`
- `docxOpenXmlCoreCases`: `33 -> 34`
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`
- `docxOpenXmlCoreAssertions`: `385 -> 408`
- Focused DOCX assertions: `2701 -> 2724`

## Dependency Closure

No new native PHP support component is needed. The slice reuses `ZipPackage`,
OPC package discovery, `DocxReader` WordprocessingML/DrawingML parsing,
`MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Word, LibreOffice,
zip/unzip, Cabal solver/build/test command, Haskell runner, external office
tool, online service, live provider test, or live-service provider test was run.

## Follow-Up

Remaining non-overlapping DOCX/OpenXML follow-ups include glossary-local
relationship resolution, richer theme font inheritance, and Word caption
numbering/SEQ-field heuristics.
