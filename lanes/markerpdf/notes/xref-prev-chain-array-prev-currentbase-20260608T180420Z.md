# markerpdf xref Prev chain array-wrapped helper current base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T180420Z`

Accepted base: `1d10c26783e331f072073a9dc0eef297e722aedb`

## Scope

This patch keeps markerPDF inside the native no-GPU searchable-PDF parser scope. It covers malformed incremental-update xref sections whose `/Prev` operand is a single-item array around an otherwise supported numeric helper, for example `/Prev [30 0 R]` where object `30 0` contains the previous xref offset.

The implementation accepts only a single top-level array item for `/Prev`; multi-item arrays and non-integer/non-helper values remain invalid and fall through to the existing repair paths.

## Behavior

- `PdfActionReviewExtractor` now unwraps a single-item `/Prev` array before resolving direct integers or bounded indirect helper objects, so stale action rows in the latest xref stream can be repaired to current appended action objects.
- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` apply the same `/Prev`-specific single-item array handling in their duplicated xref chain helpers.
- A new focused fixture keeps current page text, XMP/Info/catalog metadata, EmbeddedFiles payloads, attachment summaries, and link-action review on the current incremental update while excluding stale previous-section rows.

## Evidence

Red-first before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainArrayPrevOperandCurrentBaseTest.php`

Result: `1 test files, 37 assertions, 1 failures`; stale primary action URI was selected instead of `https://example.com/current-array-prev-action`.

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainArrayPrevOperandCurrentBaseTest.php`

Result: `1 test files, 47 assertions, 0 failures`.

Adjacent regression checks:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 612 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 0 failures`.

## WordPress Path

`lanes/markerpdf/examples/wordpress-pdf-xref-array-prev-action-currentbase.php` demonstrates a WordPress paragraph/link import path where array-wrapped `/Prev` repair selects the current URI action and additional mailto action before span promotion. The smoke explicitly reports `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref parser, direct object definition inventory, link annotation extractor, and markdown post-processor. OCR/model execution, external PDF tools, and upstream model benchmark parity remain intentionally excluded by the current markerPDF no-GPU scope.

## Next

Continue with non-overlapping native parser behavior around xref repair, stream filters, CMaps, font encodings, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
