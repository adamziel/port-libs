# markerPDF Named-Destination Action Alias Cycle Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T001907Z`
Session: `port-dev-markerpdf-named-destinations-20260606T001907Z`
Base accepted HEAD: `f52b2d5079c4d5ea31714d32add9d4f1c34a68d9`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF navigation metadata to the PDF parser boundary before OCR/model execution. Under the no-GPU markerPDF scope, this slice maps the native PDF catalog destination behavior for `/Names /Dests` and legacy `/Dests` entries whose destination values are `/S /GoTo /D` action dictionaries.

PDF named destinations may resolve through string/name aliases and through GoTo destination dictionaries, but malformed action dictionaries that reference themselves or form object-reference cycles must fail closed. They should not hang extraction, become WordPress navigation rows, or leak action payload strings into visible paragraph text.

No Python, pdftext, pypdfium/PDFium, OCR, Surya, Texify, Torch, Streamlit/FastAPI model worker, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now unwraps chained `/S /GoTo /D` destination dictionaries with object-generation cycle and depth guards.
- `PdfMetadataExtractor` now applies the same guard in both destination-map admission and document-destination row resolution.
- Valid chained GoTo aliases still resolve to the target named destination or legacy destination.
- Self-referential and mutually recursive GoTo action dictionaries are excluded from review rows and visible WordPress text.
- Non-GoTo action dictionaries remain rejected instead of being treated as named destinations.

## Red-First Evidence

After adding `PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php`, the accepted base did not complete the focused run:

```text
timeout 5s php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
exit code 124
```

The first standalone extractor fix then exposed the same recursive boundary in `PdfMetadataExtractor`; the focused run failed with PHP memory exhaustion inside `documentDestinationValueAllowedForMap()` before the metadata guard was added.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves chained GoTo aliases and rejects action-dictionary cycles before WordPress named-destination metadata
PASS keeps cyclic GoTo action alias operands out of visible WordPress text and review rows
1 test files, 28 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 33 selected test files (root lock skipped)
33 test files, 916 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfMetadata.*Destination|PdfOutline.*Destination|PdfLinkAnnotation.*Destination).*Test\.php$' | sort)
Focused test run: 15 selected test files (root lock skipped)
15 test files, 905 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-action-alias-cycle-boundary-currentbase.php
```

The smoke emits `destination_count=5`, `chained_action_alias_resolved=true`, `direct_action_alias_resolved=true`, `cross_source_action_alias_resolved=true`, `cyclic_action_aliases_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2293 -> 2295`.
- `wordpressScenarios`: `1970 -> 1971`.
- New focused file: `PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php` adds 2 PASS cases and 28 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-action-alias-cycle-boundary-currentbase.php`.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, byte-string limits, indirect page operands, indirect page-tree Kids, generation-exact references, object-stream/xref repair, stream-keyword dictionaries, name-key rejection, view-mode filtering, coordinate validation, plain alias cycles, non-GoTo action rejection, outline destination alias review, annotation link promotion, PageLabels, metadata stream review, attachments, fonts/CMaps, images, xref ownership, or supplied table/equation behavior. The bounded behavior is only chained GoTo destination dictionary alias unwrapping plus object-generation cycle rejection in standalone destination metadata and document metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, object/reference resolver, page indexer, destination map normalizer, metadata extractor, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally outside the current markerPDF no-GPU lane.

## Next Task

Continue non-overlapping native markerPDF work around searchable-PDF fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
