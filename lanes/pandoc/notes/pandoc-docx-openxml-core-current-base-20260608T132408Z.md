# pandoc-docx-openxml-core-current-base-20260608T132408Z

Accepted base: `f2c68bcb90cae7f8d5c420ad4c2ba78bf326142c`

## Behavior

Ported a bounded DOCX/OpenXML caption handoff into the native PHP reader:

- image-only WordprocessingML drawing paragraphs followed immediately by a paragraph styled as `Caption`, or by a style based on `Caption`, now import as a `figure` AST node;
- the figure keeps `caption`, `captionText`, `captionInlines`, `captionSource`, and `data-docx-caption-*` provenance;
- the grouped image preserves the original relationship/media metadata, alt text, title, target part, and import-report media usage;
- Markdown output uses the DOCX caption as the visible figure label while preserving original alt text as metadata;
- WordPress output renders the existing `wp-block-image` figure with a visible `<figcaption>`.

Upstream source-truth basis: pinned Pandoc `src/Text/Pandoc/Readers/Docx/Parse.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` documents splitting paragraphs that contain drawings from text to aid captioning. This slice ports the smallest safe PHP behavior for the common Word shape: a standalone drawing paragraph followed by a Caption-style paragraph.

## Non-Overlap

This does not duplicate prior DOCX/OpenXML slices for media relationship resolution, VML images, chart/diagram placeholders, table captions, field hyperlinks, bookmarks, comments, tracked formatting changes, deleted OMML math, embedded OLE/package placeholders, document settings, section/header/footer metadata, glossary parts, smart tags, custom XML, content controls, textboxes, or altChunk import.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2614 assertions, 0 failures`.
- Red-first: the focused DOCX test failed with `1 test files, 2615 assertions, 1 failures` because the reader returned the image paragraph and caption paragraph as separate blocks.
- Final focused run before metadata verification: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2651 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-captioned-figure-handoff.php --self-test` passed.

## Counters

- `phpPass`: `1654 -> 1655`
- `benchmarkDenominator.mapped`: `2074 -> 2075`
- `docxOpenXmlCoreCases`: `33 -> 34`
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`
- `docxOpenXmlCoreAssertions`: `385 -> 422`

## Dependency Closure

No new native PHP support component is needed. The slice reuses `ZipPackage`, OPC relationship/media resolution, `DocxReader` style loading, and the existing Markdown/WordPress figure writers. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was run.

## Follow-Up

Non-overlapping DOCX/OpenXML follow-ups remain: DrawingML text extraction, glossary-local relationship resolution, theme font inheritance, and richer Word caption numbering/SEQ-field heuristics.
