# markerPDF Named Destinations Limits Fallback Current Base

Session: `port-dev-markerpdf-named-destinations-20260604T224103Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260604T224103Z`
Base accepted HEAD: `3b9694d1bdbef3af745fe21f14add747137f6280`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata from PDF text/TOC extraction into conversion metadata before later OCR/model work. Under the current no-GPU markerPDF scope, this slice maps the native searchable-PDF parser boundary: catalog `/Names /Dests` name trees are review/navigation metadata and must not become visible WordPress text.

PDF name-tree `/Limits` lower/upper strings bound the valid key range for descendant nodes. Existing markerPDF PHP metadata and attachment name-tree walkers already use a defensive fallback for malformed child `/Limits`: when a child node declares limits that match none of its own `/Names` keys, entries are evaluated against the inherited parent range rather than dropping the whole leaf.

## Behavior

`PdfNamedDestinationExtractor` now applies the same fallback. A malformed destination leaf like:

```text
<< /Limits [(zz-stale) (zz-stale)]
   /Names [(Current Start) [3 0 R /FitH 700]
           (Review Summary) 11 0 R
           (zz-stale) [4 0 R /Fit]] >>
```

under a parent range `[(Current Start) (Review Summary)]` now recovers `Current Start` and `Review Summary`, prunes `zz-stale`, and keeps legacy `/Dests` fallback rows intact.

## Evidence

Red-first focused run before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back to inherited name-tree limits when a malformed destination leaf matches none of its keys
Expected: ["Current Start","Review Summary","LegacyOnly"]
Actual: ["LegacyOnly"]
FAIL keeps malformed name-tree limit operands out of visible WordPress text
Call to undefined method PortLibs\MarkerPDF\PdfTextExtractor::extractText()

1 test files, 1 assertions, 2 failures
```

The second failure was a test harness typo (`extractText()` vs `extractPlainText()`) fixed before the production change. The first failure is the intended missing parser behavior.

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS falls back to inherited name-tree limits when a malformed destination leaf matches none of its keys
PASS keeps malformed name-tree limit operands out of visible WordPress text

1 test files, 16 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
8 PASS cases
2 test files, 56 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destinations-limits-fallback-currentbase.php
```

Passed and emitted `destination_names=["Current Start","Review Summary","LegacyOnly"]`, `stale_limit_names_filtered=true`, `visible_text_excludes_destination_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Current-base PHP behavior tests move `1098 -> 1100`.
- `PdfNamedDestinationExtractorCurrentBase` mapped behaviors move `3 -> 4`.
- The new focused file contributes 2 PASS cases and 16 assertions.

## Non-Overlap

This does not repeat generation-exact named-destination references, basic `/Names /Dests` extraction, legacy `/Dests` extraction, duplicate legacy-name precedence, ordinary name-tree `/Limits` pruning, outline destination action context, destination Fit operand normalization, PageLabels `/Limits`, metadata document-destination review, or EmbeddedFiles name-tree limits. The bounded behavior is only malformed child destination-name-tree `/Limits` fallback to inherited parent bounds.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, dictionary/value parser, generation-exact reference resolver, page-tree indexer, name-tree walker, and text extractor. Full live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
