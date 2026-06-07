# markerPDF outline root traversal duplicate-key boundary

- Session: `port-dev-markerpdf-outline-meta-20260607T030418Z`
- Base accepted HEAD: `a078a096a4cf93f92c4400252bcd9ac19a5f846a`
- Slice: `markerpdf-outline-metadata-boundary-current-base-20260607T030418Z`

## Source Truth

Upstream markerPDF receives outline/bookmark metadata from PDF parser dependencies before OCR, layout, and model stages. Under the current no-GPU scope, catalog `/Outlines` is native navigation metadata: duplicate selected outline-root traversal keys must be reviewable without letting stale `/First`, `/Last`, `/Count`, item titles, action targets, or outline-local metadata streams become WordPress-visible text.

## Implementation

- `PdfMetadataExtractor` now records duplicate selected outline-root `/First`, `/Last`, and `/Count` keys on `document_outline` as payload-free review metadata.
- The existing last-top-level operand policy still owns outline traversal, TOC rows, and navigation review rows.
- Outline-local `/Metadata` stream review now resolves only the currently selected object generation, so stale-generation stream references remain unresolved review rows instead of decoding stale payloads.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records duplicate selected outline-root traversal keys as review metadata
Values are not identical
Expected: 3
Actual: NULL
PASS keeps unselected duplicate outline-root traversal operands out of navigation and visible text

1 test files, 25 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records duplicate selected outline-root traversal keys as review metadata
PASS keeps unselected duplicate outline-root traversal operands out of navigation and visible text

1 test files, 42 assertions, 0 failures
```

Generation-adjacent outline metadata check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalDuplicateKeyBoundaryCurrentBaseTest.php
2 test files, 89 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
46 test files, 2140 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-traversal-duplicate-keys-currentbase.php
```

The smoke emits `duplicate_root_traversal_key_count=3`, `duplicate_root_traversal_keys=["First","Last","Count"]`, `selected_first_item_object=6`, `selected_last_item_object=7`, `selected_outline_count=2`, `stale_root_operand_excluded_from_metadata=true`, `stale_root_operand_excluded_from_navigation=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted duplicate top-level catalog `/Outlines` root operands, outline item duplicate `/Title`/`/Dest`/`/A`/`/Count`/`/First`/`/Last` keys, duplicate outline item or root `/Metadata` stream operands, root `/Count 0`, root count mismatch review, `/Prev` and `/Last` traversal bounds, named destinations, PageLabels, action-chain review, annotations, forms, security, image/filter, font/CMap, xref repair, or supplied table/equation behavior. The bounded behavior is only duplicate traversal keys inside the selected outline root plus generation-exact outline-local `/Metadata` stream review required by the adjacent family.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, selected object owner/generation tracking, metadata extractor, outline extractor, text extractor, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch execution, PDFium rendering, Python runners, and external PDF tools remain intentionally out of scope.
