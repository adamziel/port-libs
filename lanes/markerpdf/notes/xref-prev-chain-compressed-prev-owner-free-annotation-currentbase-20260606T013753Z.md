# markerPDF xref Prev compressed-helper owner free annotation

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T013753Z`
Base: `a81844785028d1e754b06f6a3382bda72627fbd0`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF imports through parser-backed PDF loading before OCR/model fallback. In the no-GPU PHP lane, the equivalent native boundary is xref-chain traversal and object liveness before WordPress link/annotation promotion.

PDF incremental updates can store an xref-stream `/Prev` numeric helper in a compressed object-stream member. If an older direct object with the same object number also exists before the current xref stream, the newer compressed helper selected by the current xref rows must win. Otherwise the free-object map can follow a stale base xref and revive annotations that were freed in the middle update.

## Behavior

`PdfXrefFreeObjectMap` now compares safe direct and compressed `/Prev` helper owners by byte position before the current xref section. A newer object-stream helper wins over an older direct helper, while unsafe helper bodies still fail closed.

The focused fixture builds a base xref section with a stale Link annotation, a middle xref section that frees that annotation, then a current xref stream with `/Prev 30 0 R`. Object `30 0` first appears as a stale direct helper pointing to the base xref, and later appears as a compressed object-stream member pointing to the middle xref. The current xref stream selects the compressed helper member.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL prefers newer compressed xref-stream Prev helper over stale direct helper for freed annotations
The free-object map must prefer the newer compressed /Prev helper over a stale direct helper.

1 test files, 1 assertions, 1 failures
```

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS prefers newer compressed xref-stream Prev helper over stale direct helper for freed annotations

1 test files, 10 assertions, 0 failures
```

Adjacent xref free-object annotation family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 72 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-compressed-owner-free-annotation-currentbase.php
compressed_prev_owner_followed=true
compressed_helper_newer_than_direct_helper=true
stale_link_promoted=false
stale_annotation_promoted=false
span_link_uri_absent=true
span_link_annotation_object_absent=true
stale_annotation_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/Prev` helper selection, compressed `/Prev` helper selection without an older direct helper, damaged `/Prev` repair, indirect `/W` and `/Index` operands, current xref free-entry filtering, hybrid free-entry precedence, classic xref table indirect `/Prev`, metadata/catalog xref selection, object-stream member repair, stream filters, OCR/model behavior, or runtime preflight work.

The bounded behavior is specifically the owner precedence between an older direct safe `/Prev` helper and a newer compressed safe `/Prev` helper before free annotation suppression.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref table/stream scanner, object-stream member decoder, free-object map, annotation/link extractors, and WordPress smoke path. Full upstream markerPDF parity remains intentionally out of scope for this no-GPU slice: live OCR, Surya/Torch, Texify, pypdfium/PDFium rendering, Streamlit/FastAPI model workers, and exact model benchmark parity were not executed.
