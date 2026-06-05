# markerPDF Named Destination Name-Key Action Boundary Current Base

Session: `port-dev-markerpdf-named-destinations-20260605T122914Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T122914Z`
Base accepted HEAD: `77bfa9dd28a95036d98d3aece3867d3cc948ad95`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable PDF navigation metadata from the low-level PDF text/TOC boundary into document metadata and conversion review before model/OCR stages. Under the current no-GPU markerPDF scope, this patch maps a native PDF parser boundary: catalog `/Names /Dests` name-tree keys are PDF strings, while legacy catalog `/Dests` dictionary keys are PDF names.

WordPress import must not promote malformed name-tree rows such as `/NameObjectStale [4 0 R /FitH 111]` into document destinations, annotation action rows, or link spans. Valid legacy `/Dests << /LegacyNameKey [...] >>` rows remain reviewable and can still resolve `/Dest /LegacyNameKey` annotation operands.

## Behavior

- `PdfActionReviewExtractor` now reads name-tree keys and `/Limits` through a string-only helper, while preserving the existing string-or-name behavior for action operands and legacy destination names.
- `PdfMetadataExtractor::destinationNameDetailsFromRaw()` now rejects raw PDF name tokens when collecting catalog name-tree rows, including document destinations and name-tree review metadata.
- The new fixture proves standalone named-destination rows, `document_destinations`, annotation review actions, link span promotion, and visible WordPress text all agree on the boundary.

## Evidence

Red-first focused run before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects PDF-name keys in destination name trees before WordPress document metadata
Actual destination names included: ["Current String Key","NameObjectStale","Review Summary","LegacyNameKey"]
FAIL keeps malformed name-tree name keys out of annotation promotion and visible WordPress text
Actual annotation 8 safety included: ["local-destination"]
1 test files, 12 assertions, 2 failures
```

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects PDF-name keys in destination name trees before WordPress document metadata
PASS keeps malformed name-tree name keys out of annotation promotion and visible WordPress text
1 test files, 38 assertions, 0 failures
```

Named-destination/name-tree family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfLinkAnnotationNameTreeLimitsBoundary|PdfOutlineNameTree|PdfOutlineActionNameTree|PdfOutlineNamedDestination|PdfOutlineDestinationAction|PdfMetadata.*NameTree|PdfParserNameTree).*Test\.php$' | sort)
Focused test run: 36 selected test files (root lock skipped)
36 test files, 1259 assertions, 0 failures
```

EmbeddedFiles name-tree regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS preserves name-tree FileSpec PieceInfo and OutputIntent review on current xref catalog
PASS summarizes PDF/A associated EmbeddedFiles name-tree rows on current xref catalog
2 test files, 74 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-name-key-action-boundary-currentbase.php
```

Passed and emitted `destination_names=["Current String Key","Review Summary","LegacyNameKey"]`, `metadata_destination_names=["Current String Key","Review Summary","LegacyNameKey"]`, `promoted_link_objects=[7,9,10]`, `malformed_name_key_promoted=false`, `legacy_name_key_promoted=true`, `visible_text_excludes_destination_names=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused markerPDF PHP tests move `1821 -> 1823` PASS cases.
- WordPress smoke scenarios move `1655 -> 1656`.
- The new focused file contributes 2 PASS cases and 38 assertions.

## Non-Overlap

This does not repeat basic named-destination extraction, legacy `/Dests` extraction, duplicate legacy-name precedence, PDFDocEncoding name-tree string decoding, byte-string `/Limits` comparison, malformed child `/Limits` fallback, indirect arrays, page-only destinations, action-dictionary filtering, unknown view filtering, generation-exact destination references, object-stream/xref repair, Link annotation URI promotion, annotation name-tree `/Limits`, or outline name-tree action context. The bounded behavior is only malformed PDF-name keys inside catalog `/Names /Dests` name trees before document metadata and action/link review.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, dictionary/value parser, name-tree walkers, destination normalizer, action review extractor, metadata extractor, annotation/link promotion, Markdown post-processing, and text extractor. Live OCR, Surya/Texify/Torch models, PDFium/PIL raster execution, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
