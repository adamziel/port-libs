# PDF page production policy

Bead: `plib-1i7hh`

## Scope

Added a bounded policy summary for produced-PDF page production metadata in `PdfEngineHandoff`.

The existing fake runner already extracted page-level `/BoxColorInfo`, `/SeparationInfo`, and `/PresSteps` metadata. This slice adds `pdfPageProductionPolicy` and `finalPdfPageProductionPolicy` so importer/review code can see stable counts and review issues without scanning raw per-page rows.

The policy records:

- pages with production metadata
- box-color info counts, box names, and box styles
- missing box color/width/style counts
- separation info counts, referenced page counts, missing page references, device colorants, and color spaces
- presentation-step counts, `/Next` chain counts, and step subtypes
- review issue classes for print-production controls that need downstream review

No renderer, Typst, TeX engine, external PDF validator, file fetch, or PDF decryption path is introduced.

## Tests

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryMatrixSummaryTest.php lanes/pandoc/tests/PdfEngineHandoffTest.php lanes/pandoc/tests/PdfReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc`
- Conflict-marker scan of changed files

Results:

- 1 file, 3,729 assertions, 0 failures
- 3 files, 5,262 assertions, 0 failures

## Ledger

Added manifest counters:

- `mappedPdfPageProductionPolicyCases = 1`
- `pdfPageProductionPolicyAssertions = 14`
