# markerPDF supplied PageLabels converter boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T044757Z`

## Source Truth

- Upstream `marker/convert.py::convert_single_pdf()` receives a page list from the PDF text/PDFium boundary before the downstream layout, ordering, table, equation, and finalization stages run.
- The native PHP lane already extracts catalog `/PageLabels` into page-local `page_label` values. This slice preserves those supplied page labels across the no-GPU `CorePdfConverter::convertWithSuppliedPages()` boundary so downstream WordPress import code can render page-break metadata without reparsing the PDF.
- This stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `CorePdfConverter` now normalizes non-empty string `page_label` values from supplied pages into `page_labels` and `page_label_rows`.
- The rows use supplied-page order, preserving the trimmed page boundary as `page_index` and `page_number` for downstream conversion callbacks.
- The normalized labels are present in both output metadata and the pipeline context before the supplied pipeline callback runs.
- Empty and non-string labels are ignored so malformed supplied values do not create invented WordPress page-break metadata.
- Added a focused current-base test for labeled and unlabeled supplied-page conversion.
- Added a WordPress smoke that uses the pipeline context to emit Gutenberg separator metadata for `Front iv`, `Body 4`, and `Appendix-Z` while keeping those labels out of visible paragraph text.

## Evidence

Red-first probe before implementation:

```text
CorePdfConverter supplied pages contained page_label values ["Cover-i","Body 4"].
metadata_page_labels => NULL
context_page_labels => NULL
pipeline_pages_page_labels => ["Cover-i","Body 4"]
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/CorePdfConverterPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries supplied page labels into convert_single_pdf metadata and pipeline context
PASS keeps unlabeled supplied pages free of invented page-label metadata

1 test files, 19 assertions, 0 failures
```

Companion supplied-boundary regression check:

```text
php tools/run-tests.php lanes/markerpdf/tests/CorePdfConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS prepares convert_single_pdf language filetype page and lowres image metadata
PASS short-circuits unsupported files before supplied model pipeline runs
PASS short-circuits encrypted PDFs before supplied model pipeline runs
PASS runs actual CI benchmark excerpts through the core supplied-page boundary
PASS rejects malformed supplied core pipeline conversion payloads

1 test files, 30 assertions, 0 failures
```

PageLabels current-base family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/*PageLabels*CurrentBaseTest.php
Focused test run: 35 selected test files (root lock skipped)
...
35 test files, 691 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-core-pdf-converter-page-labels-currentbase.php
```

The smoke emits `page_labels=["Front iv","Body 4","Appendix-Z"]`, matching `pipeline_page_labels`, includes three `page_label_rows`, reports `labels_excluded_from_visible_paragraph_text=true`, and sets execution flags false.

## Non-Overlap

This does not repeat accepted `/PageLabels` number-tree parsing, kid limit ordering, generation-exact references, prefix decoding, trailer root selection, xref/object-stream repair, or marker-app preview page-label extraction. The bounded behavior is the supplied-page converter boundary carrying already-extracted page labels into metadata and downstream pipeline context.

## Dependency Closure

No new support component is needed. This reuses the existing native supplied-page converter boundary, page-local metadata conventions, filetype/security preflight, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
