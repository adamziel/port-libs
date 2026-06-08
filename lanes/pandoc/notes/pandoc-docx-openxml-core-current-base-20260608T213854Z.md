# DOCX/OpenXML Caption SEQ Metadata

## Behavior

Mapped one bounded DOCX/OpenXML support case for Word caption numbering fields in grouped captioned figures.

- `DocxReader` now scans grouped Caption-style paragraph inlines for the first `SEQ` field span.
- The grouped `figure` keeps caption sequence name, displayed result, normalized instruction, format, and supported switch metadata in `captionSource["sequence"]`.
- The same provenance is emitted as safe `data-docx-caption-*` figure attributes for Markdown image attributes and WordPress `<figure>` handoff.
- Visible caption text remains unchanged: the Word field result still renders as normal caption text.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3151 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed with `1 test files, 3128 assertions, 1 failures` because grouped captioned figures did not expose `data-docx-caption-sequence`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3164 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-docx-captioned-figure-handoff.php --self-test` passed.

Focused delta: one existing DOCX PASS case gains `+13` assertions. Lane `phpPass` moves `1876 -> 1877`; mapped denominator moves `2301 -> 2302`; `mappedDocxOpenXmlCoreCases` moves `33 -> 34`; `docxOpenXmlCoreAssertions` moves `385 -> 398`.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML slices for media relationship resolution, VML images, chart/diagram placeholders, DrawingML text, drawing geometry, picture crop/transform, plain caption grouping, field metadata spans, comments/notes, tracked changes, structured document tags, embedded objects/packages, subdocuments, table geometry, or section metadata. It only lifts already-parsed `SEQ` caption field provenance onto grouped figure metadata and WordPress figure attributes.

## Dependency Closure

No new support component is needed. The slice reuses native `DocxReader` WordprocessingML parsing, existing field-span parsing for `SEQ`, `MarkdownWriter` figure attribute handoff, `WordPressBlockWriter` image figure rendering, in-memory `ZipPackage` fixtures, focused DOCX tests, and the WordPress DOCX captioned-figure handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Good next DOCX/OpenXML gaps include non-overlapping shape metadata, glossary/header-footer relationship edge cases, and remaining style/numbering metadata. Keep them native PHP and external-tool free.
