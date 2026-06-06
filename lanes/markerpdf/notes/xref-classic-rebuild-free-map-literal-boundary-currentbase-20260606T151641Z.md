# markerpdf classic xref rebuild free-map literal boundary current-base

## Scope

Lane: `markerpdf`
Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T151641Z`
Accepted base: `1fe4fabf88654dad0045232c1f7682f4d344b4f4`

This slice stays inside the no-GPU native PDF parser scope. It does not run OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, model workers, external PDF tools, or live services.

## Behavior

Classic xref rebuild for the main text/metadata/attachment paths already treats xref-like bytes inside PDF literals and composites as non-selectable. The lightweight `PdfXrefFreeObjectMap` used by link and annotation review still scanned `xref` candidates with a regex. A damaged final `startxref` could therefore rebuild to a fake classic xref table embedded inside a top-level literal string, lose the current free row for an annotation object, and let stale annotation/link metadata through WordPress review.

`PdfXrefFreeObjectMap` now uses a token-aware classic xref scanner for rebuilt classic tables and `/Prev` fallback candidates. It skips comment lines, literal strings, hex strings, arrays, dictionaries, direct object bodies, and PDF name-token matches such as `/xref` or `/startxref`. `xrefTableSectionAt()` also refuses offsets owned by those syntax containers, so a declared damaged offset cannot directly target a fake literal-owned table.

## Red-first

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
```

Result before source edit: `1 test files, 15 assertions, 1 failures`.

Failure:

`Literal-string xref decoys must not replace the current free-row map.`

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
```

Result after fix: `1 test files, 24 assertions, 0 failures`.

Expanded adjacent xref/free-annotation family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php
```

Result: `9 test files, 783 assertions, 0 failures`.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfXrefFreeObjectMap.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-currentbase.php
```

All report no syntax errors.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-currentbase.php
```

Reports `literal_xref_decoy_after_current=true`, `literal_xref_decoy_ignored_for_free_map=true`, `free_object_map_rebuilt_to_current_classic_xref=true`, `suppresses_stale_link_annotation=true`, `suppresses_stale_review_annotation=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref table/stream parser, direct-object scanner, free-object map, annotation extractor, link extractor, and WordPress smoke path. Remaining upstream model/OCR parity remains outside this slice under the current no-GPU markerPDF directive.

## Non-overlap

This does not repeat accepted main text/metadata/attachment classic xref rebuild boundaries, missing-startxref EOF bounding, plus-signed xref headers, xref-stream filter stack decoding, indirect `/Prev`, indirect `/W` and `/Index`, compressed `/Prev` helpers, or action-review free-object suppression. The bounded behavior here is the lightweight free-object map's classic rebuild scanner before WordPress annotation/link promotion.
