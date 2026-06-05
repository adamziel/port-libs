# markerPDF xref Prev compressed free annotation current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T230020Z`

Base accepted HEAD: `13d069769033a9b5e2cc2577f3200aec1f8fed06`

## Source truth

Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path delegates low-level PDF text/page extraction to `pdftext`/PDFium-style parsing before model/OCR work. In this no-GPU PHP lane, xref `/Prev` chain selection and stale annotation suppression are native parser boundaries because WordPress link promotion must not revive freed annotations from earlier incremental updates.

PDF incremental updates may store a `/Prev` numeric helper as a compressed object-stream member. The heavier text/metadata extractors already handled compressed `/Prev` helpers, but the lightweight `PdfXrefFreeObjectMap` used by link/annotation promotion only followed direct helper objects. That left stale annotations from an older xref section promotable when the current xref-stream pointed to a compressed helper that led to an intermediate free-row section.

## Implementation

`PdfXrefFreeObjectMap` now resolves a safe scalar `/Prev` helper from decoded `/ObjStm` members before the latest xref stream when a direct helper is unavailable or unsafe. The helper is bounded to generation-zero compressed members before the current xref offset, uses the existing native stream decoder, requires a numeric-only payload, and ignores later direct-object decoys.

## Verification

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php`

Result before source edit: `1 test files, 1 assertions, 1 failures`; failure: `The lightweight free-object map must follow compressed /Prev helpers before link promotion.`

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

Adjacent free-annotation xref group:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php`

Result: `3 test files, 26 assertions, 0 failures`.

Original incremental-update focused file:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 481 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-compressed-free-annotation-currentbase.php`

Result: emits `free_map_follows_compressed_prev_helper=true`, `stale_annotation_link_excluded=true`, `stale_annotation_review_excluded=true`, `stale_span_not_promoted=true`, `post_xref_direct_prev_decoy_ignored=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP xref free-object map, direct object scanner, object-stream stream decoder, and WordPress link/annotation promotion paths. GPU/model/OCR execution remains intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat accepted xref-stream/current metadata selection, xref-stream compressed `/Prev` metadata/attachment repair, direct `/Prev` free-annotation helpers, indirect W/Index free rows, classic xref table free-row suppression, trailer `/Info null`, trailer `/Root` free suppression, or table/metadata/image/font/OCR slices. The bounded behavior is only lightweight free-object map traversal through a compressed object-stream `/Prev` numeric helper before WordPress link promotion.
