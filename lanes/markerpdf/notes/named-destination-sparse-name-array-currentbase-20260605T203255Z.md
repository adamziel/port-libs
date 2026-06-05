# markerPDF named-destination sparse name-array boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T203255Z`

## Source Truth

- Upstream markerPDF receives searchable page text and navigation structures from PDF parser/runtime boundaries. In the native no-GPU PHP scope, catalog `/Names /Dests` and legacy `/Dests` review metadata must be parsed without executing PDF actions, Python, pypdfium/PDFium, models, or external PDF tools.
- PDF destination name trees store alternating string keys and destination values. A malformed stray non-string key token in a leaf `/Names` array must not consume the following valid string key as its value; the parser should resynchronize at the next PDF string key and keep the invalid token review-excluded.

## Implementation

- `PdfNamedDestinationExtractor` now advances one token after invalid name-tree key operands, preserving valid following string-key destination pairs while still consuming well-formed key/value pairs normally.
- `PdfOutlineExtractor` uses the same recovery for named-destination maps and destination-action maps so TOC/navigation review and outline action chains stay aligned with document destination metadata.
- `PdfActionReviewExtractor` uses the same recovery for annotation/link action promotion.
- `PdfMetadataExtractor` uses the same recovery for `document_destinations` summaries and the matching name-tree limit probe.
- Added `PdfNamedDestinationSparseNameArrayBoundaryCurrentBaseTest.php` and `wordpress-pdf-named-destination-sparse-name-array-currentbase.php`.

## Evidence

Red-first after adding the focused test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationSparseNameArrayBoundaryCurrentBaseTest.php
FAIL recovers string-key destination pairs after a stray name-tree token before WordPress metadata
Expected: ["Current Start","Recovered Target","LegacyTarget"]
Actual: ["Current Start","LegacyTarget"]
FAIL keeps stray name-tree tokens out of annotation promotion and visible WordPress text
Expected second annotation safety: ["local-destination"]
Actual second annotation safety: []
1 test files, 4 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationSparseNameArrayBoundaryCurrentBaseTest.php
PASS recovers string-key destination pairs after a stray name-tree token before WordPress metadata
PASS keeps stray name-tree tokens out of annotation promotion and visible WordPress text
1 test files, 39 assertions, 0 failures
```

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*.php lanes/markerpdf/tests/PdfOutlineNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTree*CurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestination*CurrentBaseTest.php
42 test files, 1619 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-sparse-name-array-currentbase.php
destination_names=[Current Start,Recovered Target,LegacyTarget]
promoted_link_objects=[7,8,10]
stray_name_promoted=false
```

## Non-Overlap

This does not repeat accepted named-destination `/Limits`, byte-string comparison, PDF-name key rejection, duplicate-key precedence, alias chains, page-only destinations, indirect page operands, action dictionaries, view-mode/coordinate validation, trailer-root selection, xref/object-stream repair, outline destination action context, or link destination generation boundaries. The bounded behavior is sparse malformed name-tree leaf arrays where a stray non-string key token appears before a valid PDF string-key destination pair.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, name-tree parser, destination normalizer, outline/action review maps, annotation/link promotion, metadata summaries, Markdown block merger, and WordPress smoke path. Live OCR, Surya, Texify, Torch/model execution, PDF rendering, Python markerPDF, and external PDF tooling remain intentionally out of scope for this no-GPU markerPDF slice.
