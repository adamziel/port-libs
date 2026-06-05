# markerPDF xref Prev chain page-property selection

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T130334Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF parsing through pdftext/PDFium-backed object selection before page review/import metadata is consumed. Under the current no-GPU markerPDF scope, this lane owns native PHP xref traversal for searchable PDFs and WordPress review-only metadata.

Incremental PDFs can keep the catalog/page tree in a previous xref section, publish current page objects in a latest xref stream, and then carry unreferenced same-generation decoy objects later in the byte stream. Page-scoped `/PieceInfo` and `/AF` review metadata must follow the latest xref stream plus inherited `/Prev` entries, not scan-order object replacement.

## Behavior

`PdfPagePropertyExtractor` now builds its direct object map from the latest xref stream when available, merges inherited `/Prev` xref stream or classic xref table entries, and selects direct object definitions by xref offset/generation. Unlisted direct objects are excluded from the xref-backed map, while PDFs without xref evidence keep the previous scan-order fallback.

The focused fixture keeps stale catalog/page tree objects in a previous classic xref table, appends current page `/PieceInfo`, content, FileSpec, and EmbeddedFile objects in the current update, then places same-generation post-xref decoys before `startxref`. WordPress page review now selects `current-xref-page`, `current-source.xml`, and current page text while excluding stale previous and post-xref decoy metadata.

## Evidence

Red baseline after adding the focused case and before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL selects current page review objects from xref stream Prev chains before post-xref decoys
Values are not identical
Expected: 'D:20260605130334Z'
Actual: 'D:20260605000000Z'

1 test files, 232 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
9 PASS cases

1 test files, 249 assertions, 0 failures
```

Adjacent xref `/Prev` current-base guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
24 PASS cases

1 test files, 411 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-property-xref-prev-chain-currentbase.php
current_page_review_selected=true
current_associated_file_selected=true
associated_payload_review_only=true
post_xref_decoy_excluded=true
previous_page_review_excluded=true
current_text_selected=true
stale_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted metadata-side `/Root` and `/Info` generation repair, same-generation damaged xref row repair in text/metadata/EmbeddedFiles, direct `/Prev` helper repair, classic xref table repair, free-row suppression, object-stream carrier repair, xref stream operand-owner boundaries, or encrypted preflight work.

The bounded behavior here is specifically page-review extraction choosing current page `/PieceInfo` and page `/AF` objects through an xref-stream `/Prev` chain before post-xref same-generation decoys.

## Dependency Closure

No new support component is needed. This slice reuses native PHP direct-object scanning, Flate stream decoding, PDF dictionary/array parsing, page review metadata extraction, text extraction, and the WordPress smoke renderer. Full upstream OCR/model parity remains intentionally out of scope under the no-GPU markerPDF directive; no Python, pdftext, Surya/Torch, Texify, external PDF tools, or live services were executed.
