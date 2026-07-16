# markerPDF Named Destinations Internal Node Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T074224Z`
Session: `port-dev-markerpdf-named-destinations-20260605T074224Z`
Base accepted HEAD: `77f7b54408a215b8868ef1c3927a9ab284ffa262`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream marker keeps PDF navigation metadata separate from extracted page text before any OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF `/Names /Dests` name-tree boundary.
- PDF name-tree `/Limits` are subtree bounds. A malformed internal node that contains both local `/Names` and descendant `/Kids` must not let stale local rows loosen the effective child range.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, live service, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor::collectNameTreeEntries()` now resolves `/Kids` before local `/Names` and keeps effective internal-node limits when recursing into children.
- Leaf fallback behavior for malformed `/Limits` remains intact: a leaf whose valid names only match the inherited range can still recover those names.
- Mixed internal nodes no longer widen children to an inherited parent range just because stale local `/Names` entries miss the node's own valid limits.
- `PdfMetadataExtractor::collectDestinationNameTreeEntries()` now applies the same boundary for `document_destinations` review metadata.
- Added a WordPress smoke that proves stale internal-node destination labels stay out of visible paragraph text and metadata review rows.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInternalNodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps mixed internal name-tree nodes from widening child destination limits
PASS keeps stale mixed-node destination labels out of WordPress text and review metadata

1 test files, 22 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php
Focused test run: 15 selected test files (root lock skipped)
15 test files, 349 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 891 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-internal-node-boundary-currentbase.php
```

The smoke emits `child_limits_preserved=true`, `metadata_child_limits_preserved=true`, `stale_parent_decoy_excluded=true`, `stale_child_decoy_excluded=true`, `stale_metadata_decoys_excluded=true`, `visible_text_excludes_destination_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint passed for:

- `lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
- `lanes/markerpdf/src/PdfMetadataExtractor.php`
- `lanes/markerpdf/tests/PdfNamedDestinationInternalNodeBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-named-destination-internal-node-boundary-currentbase.php`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1584 -> 1586`.
- `wordpressScenarios`: `1470 -> 1471`.
- New focused file: `PdfNamedDestinationInternalNodeBoundaryCurrentBaseTest.php` adds 2 TestRunner PASS cases and 22 assertions.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, tokenizer, name-tree resolver, page-tree indexer, destination normalizer, document metadata extractor, visible text extractor, and WordPress smoke renderer. Full upstream Python/model/pdftext/pypdfium/Surya/Texify/Torch benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf `/Limits` fallback, malformed intermediate `/Kids` node limit recovery, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, PDF name-key rejection, page-only destinations, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only mixed internal `/Names` plus `/Kids` destination-node limit preservation before child traversal and metadata review.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: annotations, forms, security preflight, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
