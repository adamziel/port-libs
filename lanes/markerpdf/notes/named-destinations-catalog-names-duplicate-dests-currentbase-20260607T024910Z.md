# markerPDF named destinations duplicate catalog Names Dests boundary

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260607T024910Z`
- Accepted base: `daddb71fc75dfb1aeafa7cb832e2daaad4824205`
- Behavior cluster: duplicate decoded `/Dests` keys inside the catalog `/Names` dictionary now fail closed for catalog name-tree destinations while preserving legacy catalog `/Dests` fallback rows.

## Source Truth

Upstream markerPDF carries searchable PDF navigation metadata through the PDF parser boundary before any OCR/model handoff. Under the current no-GPU scope, this lane maps native PDF catalog named destinations and link promotion without executing PDF actions, OCR, CUDA, Surya, Texify, Torch, PDFium, Poppler, Ghostscript, or external PDF tools.

PDF dictionaries can encode the same decoded key more than once, including escaped names such as `/#44ests` for `/Dests`. The accepted native boundary for similar malformed catalogs is to avoid trusting ambiguous last-key-wins navigation roots. This slice applies that boundary specifically to catalog `/Names /Dests` name trees so stale duplicate name-tree roots cannot promote stale WordPress links or document-destination metadata. Legacy catalog `/Dests` remains usable because it is an independent fallback source.

## Implementation

- `PdfNamedDestinationExtractor` now detects duplicate decoded keys in an indirect catalog `/Names` dictionary before traversing `/Dests` name trees.
- `PdfMetadataExtractor` now uses its raw top-level decoded-key scanner to skip document destination name-tree metadata when `/Names` declares duplicate `/Dests`.
- `PdfActionReviewExtractor` now reuses parsed duplicate-key review metadata before building the local destination map used by annotations and link promotion.
- Added a focused fixture where `/#44ests 20 0 R /Dests 21 0 R` previously selected the stale tree, while legacy `/Dests << /LegacyOk ... >>` is still valid.
- Added a WordPress smoke proving only `LegacyOk` and the safe URI link are promoted, while `Current Tree`, `Stale Tree`, `/FitH`, and `/XYZ` name-tree payloads remain hidden from review and visible text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationCatalogNamesDuplicateDestsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate catalog Names Dests keys before WordPress destination metadata
FAIL keeps duplicate catalog Names Dests rows out of annotation promotion and visible WordPress text

1 test files, 4 assertions, 2 failures
```

The pre-fix extractor exposed `Stale Tree` from the duplicate `/Names /Dests` entry and promoted the stale local destination link.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationCatalogNamesDuplicateDestsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate catalog Names Dests keys before WordPress destination metadata
PASS keeps duplicate catalog Names Dests rows out of annotation promotion and visible WordPress text

1 test files, 42 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationCatalogNamesDuplicateDestsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
24 PASS cases

6 test files, 313 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-catalog-names-duplicate-dests-currentbase.php
Result: exits 0 and emits destination_names=["LegacyOk"], annotation_actions=[[],[],["local-destination"],["review-uri"]], promoted_link_objects=[9,10], duplicate_name_tree_hidden=true, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationCatalogNamesDuplicateDestsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-catalog-names-duplicate-dests-currentbase.php
Result: no syntax errors detected.
```

```text
git diff --check -- lanes/markerpdf
Result: no output.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object parser, named-destination extractor, metadata raw dictionary scanner, action-review duplicate-key metadata, annotation/link promotion, Markdown post-processor, and text extractor. No GPU, model, OCR, subprocess PDF engine, network service, or live provider dependency is introduced.

## Non-Overlap

This does not repeat accepted named-destination direct extraction, legacy `/Dests` fallback, duplicate name-tree row precedence, `/Limits` pruning/fallback/order, indirect `/Kids`/`/Names` arrays, PDFDocEncoding name keys, action dictionary boundaries, page operand validation, destination view-mode normalization, generation-exact destinations, object-stream/xref repair, outline destination action context, PageLabels, EmbeddedFiles name trees, annotation rectangle promotion, URI action review, table/equation handoffs, or OCR/model surfaces. The bounded behavior is only duplicate decoded `/Dests` keys inside the catalog `/Names` dictionary before name-tree metadata/link promotion.

## Next Task

Continue non-overlapping native searchable-PDF parser work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
