# markerPDF xref Prev chain free annotation indirect Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T191712Z`
Base: `01de5c3716ba59eaffcd34fabe429d0c6186cfb1`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text, annotations, and link review through parser-backed pdftext/PDFium boundaries before OCR/model fallback. In this no-GPU native PHP lane, xref `/Prev` chain object liveness is a parser dependency boundary for WordPress import.

PDF incremental updates may store the previous xref section offset as an indirect numeric helper. The lightweight free-object map used by link and annotation extraction already handled direct `/Prev` integers plus indirect `/W` and `/Index` helpers, but it parsed `/Prev 30 0 R` as the direct integer offset `30`. That skipped the middle free-row section and allowed stale previous-section annotation dictionaries to survive.

## Behavior

`PdfXrefFreeObjectMap` now resolves `/Prev` as a reference first. When `/Prev` is an indirect reference, it accepts only a direct helper object before the selected xref section whose whole body is an integer. Post-xref same-number decoys are ignored.

The focused fixture has:

- a base xref table with a live URI link annotation object `7`;
- a middle xref table that frees object `7`;
- a latest xref stream whose `/Prev 30 0 R` points at object `30`, a numeric helper containing the middle xref offset;
- a post-xref decoy `30 0 obj` with a different value.

WordPress link promotion now sees object `7` as free, so the stale URI annotation is not promoted to a span and is not emitted as annotation review metadata.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses stale page annotations when latest xref-stream Prev is an indirect numeric helper
The lightweight free-object map must follow indirect /Prev helpers before link promotion.

1 test files, 1 assertions, 1 failures
```

Focused green after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS suppresses stale page annotations when latest xref-stream Prev is an indirect numeric helper

1 test files, 9 assertions, 0 failures
```

Adjacent xref annotation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 492 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-annotation-indirect-prev-currentbase.php
indirect_prev_helper_followed=true
stale_link_promoted=false
stale_annotation_promoted=false
span_link_uri_absent=true
span_link_annotation_object_absent=true
post_xref_prev_decoy_ignored=true
stale_annotation_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the prior indirect `/W` and `/Index` free-annotation slice, direct xref table free-row suppression, metadata/text/attachment xref `/Prev` repair, xref-stream object-stream generation repair, hybrid `/XRefStm` precedence, stream-filter work, or live OCR/model behavior.

The bounded behavior here is specifically lightweight link/annotation free-object traversal when the latest xref stream reaches the freeing section through a safe indirect numeric `/Prev` helper.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, Flate xref-stream decoding, xref table/stream `/Prev` chain walking, link annotation extraction, annotation review extraction, and the WordPress smoke path. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
