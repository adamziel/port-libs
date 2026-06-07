# markerPDF Named Destinations Legacy Duplicate Key Boundary

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260607T125649Z`
- Accepted base: `33dd90b2d97147e4b87532dfc006637ee405391e`
- Behavior cluster: duplicate decoded keys inside legacy catalog `/Dests` dictionaries are skipped before WordPress destination metadata, outline target context, and link promotion.

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU scope, this lane maps the native PDF parser boundary in PHP without executing PDF actions, OCR, CUDA, Surya, Texify, Torch, PDFium, Poppler, Ghostscript, or external PDF tools.

PDF dictionaries can encode the same decoded key more than once, including escaped names such as `/#4cegacyReview` for `/LegacyReview`. The accepted native boundary already fails closed for duplicate decoded `/Dests` keys inside catalog `/Names`. This slice applies the same ambiguity rule to the older legacy catalog `/Dests` dictionary while preserving non-duplicate legacy rows and normal name-tree destinations.

## Implementation

- `PdfNamedDestinationExtractor` now scans the raw legacy `/Dests` dictionary for duplicate decoded keys and skips those ambiguous destination names.
- `PdfMetadataExtractor` filters duplicate legacy `/Dests` keys before building `document_destinations` and raw destination maps.
- `PdfActionReviewExtractor` skips duplicate legacy destination names in the local destination map used by annotation/link action review.
- `PdfOutlineExtractor` preserves duplicate-key review metadata in parsed dictionaries and skips duplicate legacy destination names for outline target resolution.
- Added a focused fixture where `/LegacyReview ... /#4cegacyReview ...` previously selected the stale last entry, while `UniqueLegacy` and `Tree Target` remain valid.
- Added a WordPress smoke proving annotation object `7` is unpromoted, objects `8`, `9`, and `10` remain promoted, and destination/action review labels stay out of visible text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLegacyDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips duplicate decoded legacy Dests keys before WordPress destination review
FAIL keeps duplicate legacy destinations out of link promotion and visible WordPress text

1 test files, 3 assertions, 2 failures
```

The pre-fix extractor exposed `LegacyReview` from the duplicate escaped legacy key and promoted stale annotation object `7`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLegacyDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips duplicate decoded legacy Dests keys before WordPress destination review
PASS keeps duplicate legacy destinations out of link promotion and visible WordPress text

1 test files, 43 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 46 selected test files (root lock skipped)
92 PASS cases

46 test files, 1369 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-legacy-duplicate-key-currentbase.php
Result: exits 0 and emits duplicate_legacy_key_rejected=true, unique_legacy_key_preserved=true, name_tree_destination_preserved=true, promoted_annotation_objects=[8,9,10], visible_text_excludes_destination_names=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object parser, raw dictionary scanners, named-destination extractor, metadata destination maps, action-review duplicate-key metadata, outline destination maps, link annotation promotion, Markdown post-processing, and text extraction. No GPU/model/OCR runtime, external PDF tool, network service, or live provider dependency is introduced.

## Non-Overlap

This does not repeat accepted duplicate catalog `/Names /Dests` handling, duplicate name-tree row precedence, direct extraction, legacy `/Dests` fallback, `/Limits` pruning/fallback/order, indirect `/Kids`/`/Names` arrays, PDFDocEncoding name keys, action dictionary boundaries, page operand validation, view-mode normalization, generation-exact destinations, object-stream/xref repair, outline destination action context, PageLabels number-tree behavior, annotation rectangle promotion, URI action review, table/equation handoffs, or OCR/model surfaces. The bounded behavior is only duplicate decoded keys inside the legacy catalog `/Dests` dictionary.

## Next Task

Continue non-overlapping native searchable-PDF parser work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
