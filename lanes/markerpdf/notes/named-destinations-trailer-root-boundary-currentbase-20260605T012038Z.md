# markerPDF Named Destinations Trailer Root Boundary

Session: `port-dev-markerpdf-named-destinations-20260605T012038Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T012038Z`
Base accepted HEAD: `9a82634fe4736dc40764aa8077c41e4520aeadac`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata from searchable-PDF extraction into document conversion metadata before model/OCR stages. Under the current no-GPU markerPDF scope, this patch maps the native PDF parser boundary for catalog named destinations: the latest `startxref` trailer `/Root` catalog is authoritative for `/Names /Dests` and legacy `/Dests` review metadata.

PDF incremental updates may leave an older catalog body earlier in the file. The native extractor must not choose that stale first `/Type /Catalog` body when the current trailer root points at a later catalog.

## Behavior

`PdfNamedDestinationExtractor` now checks the latest `startxref` section before falling back to a first-catalog scan:

- xref-table trailers are tokenized with comment skipping, including escaped names such as `/Ro#6ft`;
- xref-stream dictionaries at the `startxref` offset can also supply `/Root`;
- a valid trailer-root catalog is used for page indexing, `/Names /Dests`, and legacy `/Dests`;
- minimal PDFs without a usable trailer root keep the previous fallback catalog scan.

## Evidence

Red-first focused run after adding the current-base test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses current trailer Root catalog before stale named-destination catalog bodies
Expected: ["Current Start","Current Appendix","LegacyCurrent"]
Actual: ["Stale Start","Stale Dict","StaleLegacy"]
FAIL keeps stale body catalog destinations out of WordPress review and visible text
1 test files, 5 assertions, 2 failures
```

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current trailer Root catalog before stale named-destination catalog bodies
PASS keeps stale body catalog destinations out of WordPress review and visible text
PASS uses xref-stream Root catalog before stale named-destination catalog bodies
1 test files, 23 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
15 PASS cases
4 test files, 107 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-trailer-root-boundary-currentbase.php
```

Passed and emitted `destination_names=["Current Start","Current Appendix","LegacyCurrent"]`, `current_trailer_root_catalog_selected=true`, `stale_body_catalog_destinations_excluded=true`, `visible_text_uses_current_trailer_root=true`, and no Python/model/external PDF tool execution.

## Status Delta

- Focused markerPDF PHP PASS cases move `1237 -> 1240`.
- WordPress scenarios move `1209 -> 1210`.
- The new focused file contributes 3 PASS cases and 23 assertions.

## Non-Overlap

This does not repeat generation-exact named-destination references, name-tree `/Limits` fallback, ordinary catalog `/Names /Dests` extraction, legacy `/Dests`, PDFDocEncoding destination keys, indirect Fit operands, outline destination action context, metadata document-destination review, PageLabels, xref text extraction, or EmbeddedFiles name-tree behavior. The bounded behavior is only choosing the current trailer `/Root` catalog for named destination review before stale earlier catalog bodies.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, dictionary/value tokenizer, generation-exact reference resolver, page-tree indexer, name-tree walker, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
