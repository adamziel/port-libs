# markerPDF Outline Root Metadata Summary Boundary

## Source Truth

- Upstream markerPDF surfaces searchable PDF structure and document metadata before any OCR/model fallback. Under the current no-GPU scope, outline `/Metadata` remains native PDF parser review metadata and must not be promoted into visible WordPress paragraph text or document XMP roots.
- PDF outline root `/Metadata` uses the same trust boundary as outline item `/Metadata`: it is root-local review data, accepts only an indirect metadata stream reference, and duplicate keys select the last top-level value while exposing provenance review.

## Implementation

- `PdfMetadataExtractor::documentOutlineMetadata()` now adds compact `root_metadata_stream_*` summary fields beside the existing nested `metadata_stream_review`.
- The summary is payload-free and review-only. It copies bounded status/provenance fields such as selected duplicate entry index, operand shape, object/type/filter labels when present, and stale-object exclusion when the selected duplicate value is direct.
- Existing nested review status strings and item metadata summaries are preserved.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes valid outline root Metadata streams at the document outline boundary (lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: NULL
FAIL summarizes selected direct outline root Metadata operands without stale stream provenance (lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: NULL

1 test files, 25 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes valid outline root Metadata streams at the document outline boundary
PASS summarizes selected direct outline root Metadata operands without stale stream provenance

1 test files, 54 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*CurrentBaseTest.php
Focused test run: 80 selected test files (root lock skipped)
80 test files, 3569 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-root-metadata-summary-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-metadata-summary-currentbase.php
exits 0 with root_metadata_stream_count=1, rejected_non_indirect_metadata_reference for selected direct root metadata, stale_root_metadata_object_excluded=true, navigation_root_review_preserved=true, executes_python_or_models=false, and executes_external_pdf_tools=false
```

```text
git diff --check -- lanes/markerpdf
no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `+2`
- Focused assertions: `+54`
- WordPress scenarios: `+1`
- `lane-status.json`: `phpPass` `3472 -> 3474`, `wordpressScenarios` `2815 -> 2816`
- Mapped upstream denominator: unchanged; this is additive inside the already mapped native outline metadata boundary cluster.

## Non-Overlap

This does not repeat item metadata summary rows, root metadata stream nested review, root selected null handling, duplicate item metadata selection, direct root metadata stream review, malformed stream type/length/filter/decodeparms operand boundaries, or outline visible-text exclusion. The bounded behavior is only the document-level summary of the already computed root `/Metadata` review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary tokenizer, outline tree traversal, metadata stream boundary review, and WordPress block smoke path. Full upstream markerPDF OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
