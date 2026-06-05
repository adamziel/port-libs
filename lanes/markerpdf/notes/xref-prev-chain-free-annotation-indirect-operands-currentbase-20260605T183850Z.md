# markerPDF xref Prev chain free annotation indirect operands current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T183850Z`
Base: `a2ede600edd63bd6e9a797e57ff08efc0a3e64c7`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable-PDF content and annotation/link review through parser-backed pdftext/PDFium boundaries before model/OCR fallback. In the native no-GPU PHP lane, xref `/Prev` chain object liveness is a parser dependency boundary for WordPress import.

PDF xref streams may store `/W` and `/Index` as indirect integer-array helper objects. Those operands define which current rows are authoritative. If the lightweight free-object map ignores them, stale previous-section annotation dictionaries can survive and be promoted to WordPress link spans even when the latest incremental update freed the annotation object.

## Behavior

`PdfXrefFreeObjectMap` now resolves safe indirect `/W` and `/Index` array helpers that appear before the current xref stream. The resolver is bounded to direct helper objects before the selected xref offset, so post-xref same-number decoys cannot redefine the row layout.

The focused fixture builds a previous xref table with a page annotation pointing at a stale URI, then appends a current xref stream with `/Prev`, indirect `/W 30 0 R`, indirect `/Index 31 0 R`, and a free row for annotation object `7`. The link and annotation extractors now suppress object `7` before WordPress link promotion.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses stale page annotations when current xref-stream free rows use indirect W and Index operands
The lightweight free-object map must resolve indirect xref-stream W and Index operands.

1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS suppresses stale page annotations when current xref-stream free rows use indirect W and Index operands

1 test files, 9 assertions, 0 failures
```

Adjacent xref Prev chain annotation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 483 assertions, 0 failures
```

Adjacent indirect xref operand gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 27 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-annotation-indirect-operands-currentbase.php
free_annotation_row_selected=true
stale_link_promoted=false
stale_annotation_promoted=false
span_link_uri_absent=true
span_link_annotation_object_absent=true
post_xref_operand_decoy_ignored=true
stale_annotation_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the existing metadata/text/attachment indirect `/W` and `/Index` xref-stream coverage, direct `/Prev` annotation free-row suppression, malformed CMap filter boundaries, object-stream member-index repair, xref-stream root/trailer inheritance, or OCR/model work.

The bounded behavior here is specifically the lightweight free-object map used by link and annotation extraction when the latest xref-stream free rows depend on indirect `/W` and `/Index` operands.

## Dependency Closure

No new support component is needed. This slice reuses native PHP direct-object scanning, Flate xref-stream decoding, xref `/Prev` chain walking, link annotation extraction, annotation review extraction, and the WordPress smoke path. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
