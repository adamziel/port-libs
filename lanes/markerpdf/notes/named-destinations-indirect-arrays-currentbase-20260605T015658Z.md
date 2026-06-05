# markerPDF Named Destinations Indirect Arrays Current Base

Session: `port-dev-markerpdf-named-destinations-20260605T015658Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T015658Z`
Base accepted HEAD: `ecec2b9f3020be56e17c6e2e635bed38cedbf419`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata from searchable-PDF extraction into downstream conversion metadata before OCR/model stages. Under the current no-GPU markerPDF scope, this slice maps the native PDF parser boundary for catalog `/Names /Dests` name trees.

PDF name-tree node operands are ordinary PDF objects. The `/Kids`, `/Names`, and `/Limits` array values can therefore be direct arrays or indirect array objects. WordPress import metadata should recover valid in-range named destinations from indirect arrays while preserving existing stale-range pruning and keeping destination labels out of visible page text.

## Behavior

`PdfNamedDestinationExtractor` now resolves indirect array operands for:

- name-tree node `/Kids`;
- leaf `/Names` key/value arrays;
- node `/Limits` lower/upper bound arrays.

A catalog destination tree with `/Kids 51 0 R`, leaf `/Names 53 0 R`, and `/Limits 50 0 R` now recovers `Alpha Review`, `Current Start`, and `Summary Review`, filters out out-of-range stale rows, and preserves non-duplicate legacy `/Dests` fallback rows.

## Evidence

Red-first focused run before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect name-tree Kids Names and Limits arrays before WordPress named destination review
Expected: ["Alpha Review","Current Start","Summary Review","LegacyReview"]
Actual: ["LegacyReview"]
PASS keeps indirect name-tree destination labels out of visible WordPress text

1 test files, 9 assertions, 1 failures
```

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect name-tree Kids Names and Limits arrays before WordPress named destination review
PASS keeps indirect name-tree destination labels out of visible WordPress text

1 test files, 19 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
17 PASS cases
5 test files, 126 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destinations-indirect-arrays-currentbase.php
```

Passed and emitted `destination_names=["Alpha Review","Current Start","Summary Review","LegacyReview"]`, `indirect_name_tree_arrays_resolved=true`, `indirect_limits_pruned_stale_names=true`, `visible_text_excludes_destination_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused markerPDF PHP PASS cases move `1268 -> 1270`.
- WordPress scenarios move `1233 -> 1234`.
- `pdfNamedDestinationExtractorCurrentBase` mapped behaviors move `3 -> 4`.
- The new focused test file contributes 2 PASS cases and 19 assertions.

## Non-Overlap

This does not repeat generation-exact named-destination references, exact-generation object bodies, current trailer `/Root` catalog selection, name-tree `/Limits` pruning, malformed child `/Limits` fallback, PDFDocEncoding destination keys, indirect destination view operands, outline destination action context, metadata document-destination review, PageLabels, xref text extraction, or EmbeddedFiles name-tree behavior. The bounded behavior is only resolving indirect `/Kids`, `/Names`, and `/Limits` arrays inside the standalone catalog named-destination extractor.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/value tokenizer, generation-exact reference resolver, page-tree indexer, name-tree walker, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
