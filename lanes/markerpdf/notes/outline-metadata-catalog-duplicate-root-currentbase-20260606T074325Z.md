# markerPDF outline metadata catalog duplicate root boundary

- Session: `port-dev-markerpdf-outline-meta-20260606T074325Z`
- Base accepted HEAD: `14b06837c7204bb9dfbc7b1b9cd2c689fde1b931`
- Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T074325Z`

## Source Truth

Upstream markerPDF receives searchable PDF outline/bookmark metadata from PDF parser dependencies before OCR/layout/model handoff. Under the current no-GPU scope, catalog `/Outlines` is native document-navigation metadata and must stay separate from page text. A malformed catalog can contain duplicate top-level `/Outlines` keys, including escaped names such as `/Out#6Cines`; the selected top-level operand owns the outline tree, while unselected roots and their action targets remain review-only provenance.

## Implementation

- `PdfMetadataExtractor` now records duplicate catalog `/Outlines` root operands on `document_outline` as payload-free review metadata.
- The selected outline root behavior remains stable: the final top-level `/Outlines` entry supplies document outline rows, TOC titles, navigation metadata, and resolved destinations.
- Unselected duplicate root titles and URI/action targets are excluded from document metadata text values, navigation review rows, and visible WordPress paragraphs.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogDuplicateRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records duplicate catalog Outlines root selection as review-only document metadata
Values are not identical
Expected: 2
Actual: NULL
PASS keeps unselected duplicate catalog Outlines roots out of navigation and visible text

1 test files, 24 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogDuplicateRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records duplicate catalog Outlines root selection as review-only document metadata
PASS keeps unselected duplicate catalog Outlines roots out of navigation and visible text

1 test files, 55 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
39 test files, 1834 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-catalog-duplicate-root-currentbase.php
```

The smoke emits `duplicate_root_entry_count=2`, `duplicate_root_objects=[8,5]`, `selected_root_object=5`, `selected_entry_index=1`, `duplicate_root_review_only=true`, `duplicate_root_payload_included=false`, `stale_root_excluded_from_metadata=true`, `stale_root_excluded_from_navigation=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted outline root type/count/stream validation, direct root parent boundaries, item duplicate `/Title`/`/Dest`/`/A`/structure keys, item `/Metadata` duplicate/operand/stream boundaries, `/Last` and `/Prev` traversal bounds, xref/trailer root selection, named destinations, page labels, action-chain review, annotations, forms, security, image/filter, font/CMap, or supplied table/equation behavior. The bounded behavior is only duplicate top-level catalog `/Outlines` root operands and escaped root-key provenance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, name-escape decoder, metadata extractor, outline extractor, text extractor, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch execution, PDFium rendering, Python runners, and external PDF tools remain intentionally out of scope.
