# pandoc-docx-openxml-core-current-base-20260609T070257Z

Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

## Behavior

Bounded DOCX/OpenXML field-code handoff for Word document-information fields:

- `DocxReader` now preserves displayed `FILENAME`, `AUTHOR`, `COMMENTS`, `FILESIZE`, `KEYWORDS`, `LASTSAVEDBY`, `SUBJECT`, `TEMPLATE`, and `TITLE` field results as `docx-field` spans instead of flattening them to plain text.
- `FILENAME \p` records `data-docx-field-path="true"` and a `docx-field-path` class so WordPress import review can distinguish a source path field from a bare file-name field.
- The focused fixture covers both simple fields and a complex field with `w:fldChar`/`w:instrText` result separation.

This deliberately does not evaluate Word fields. It preserves the result text Word stored in the DOCX and exposes the field provenance for review.

## Evidence

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 4319 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 4349 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-docx-document-info-field-handoff.php --self-test` -> `wordpress-docx-document-info-field-handoff self-test passed`.

Expected lane movement: +1 PHP PASS case, +1 mapped DOCX/OpenXML core case, +30 focused assertions. `UPSTREAM_TEST_MANIFEST.json` now reports `mapped` 2852, `mappedDocxOpenXmlCoreCases` 34, and `docxOpenXmlCoreAssertions` 415.

## Non-Overlap

This avoids the latest accepted Citation/CSL names-substitute work and the existing DOCX generated-field, cross-reference, sequence, data-field, form-field, tracked-change, content-control, drawing, section, notes, and package-report clusters. It extends the already accepted DOCX field-code path with document-information field names only.

## Dependency Closure

No new native support component is needed. The slice reuses the existing in-memory `ZipPackage` fixtures, `DocxReader` field parser, Pandoc-like AST, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Follow-Up

A non-overlapping DOCX follow-up could preserve additional Word field switches, link `DOCPROPERTY` values to custom/core properties for reviewer diagnostics, or expose embedded chart workbook metadata. Keep those as metadata handoffs rather than external field evaluation.
