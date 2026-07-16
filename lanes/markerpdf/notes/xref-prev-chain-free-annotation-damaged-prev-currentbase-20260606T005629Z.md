# markerPDF xref Prev damaged free annotation current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T005629Z`

Base accepted HEAD: `ff7d31e1397095949e33524eafeb5b7160ae8790`

## Source truth

Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path relies on native PDF parser behavior before any model/OCR stage. Under the current no-GPU markerPDF scope, the PHP lane owns xref `/Prev` traversal, stale-object suppression, and WordPress link/annotation promotion boundaries.

PDF incremental updates may contain a damaged `/Prev` operand in the latest xref stream while the real previous xref section is still the nearest valid xref section before the latest update. The full text/metadata/attachment xref parser already repaired this class of boundary. The lightweight `PdfXrefFreeObjectMap` used by link and annotation extractors did not validate `/Prev` targets, so a stale annotation freed in the middle xref section could be revived into WordPress link metadata when the latest `/Prev` pointed a few bytes inside the middle section.

## Implementation

`PdfXrefFreeObjectMap` now validates declared `/Prev` targets before recursing. If the declared target points forward or does not resolve to a real xref table/xref-stream section, it falls back to the nearest valid xref table or xref-stream object before the current section. The repair is bounded to documents that actually declare a numeric or resolved numeric `/Prev`; xref sections without `/Prev` still stop normally.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs damaged xref-stream Prev to suppress stale page annotations before WordPress link promotion
The lightweight free-object map must repair damaged /Prev before link promotion.

1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs damaged xref-stream Prev to suppress stale page annotations before WordPress link promotion

1 test files, 10 assertions, 0 failures
```

Adjacent free-annotation xref group:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 PASS cases

4 test files, 36 assertions, 0 failures
```

Original incremental-update focused file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
27 PASS cases

1 test files, 500 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-annotation-damaged-prev-currentbase.php
```

Result: emits `free_annotation_object_detected=true`, `stale_link_suppressed=true`, `stale_payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the existing native PHP direct object scanner, xref table/xref-stream readers, Flate decoder, free-object map, and WordPress link/annotation extractors. GPU/model/OCR execution remains intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat accepted same-generation text/metadata/attachment `/Prev` repair, direct `/Prev` helper free annotations, indirect W/Index xref-stream free rows, compressed `/Prev` helper free annotations, classic xref table free-row suppression, trailer `/Info null`, trailer `/Root` free suppression, or image/font/table/metadata slices. The bounded behavior is only damaged xref-stream `/Prev` offset repair in the lightweight free-object map before stale annotation link promotion.
