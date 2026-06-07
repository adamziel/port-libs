# markerPDF CMap indirect UseCMap name filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260607T001243Z`
Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T001243Z`
Base accepted HEAD: `c6e5a0e926a4e4bd2f285c58fcda147c7ce08341`

## Behavior

This patch maps a native searchable-PDF CMap boundary where `/UseCMap` is a dictionary operand pointing to an indirect name object, and the indirect reference contains a PDF comment between the object number and generation:

```pdf
/UseCMap 8 % comment splits the indirect name reference
 0 R
8 0 obj
/CommentSplitUseCMapBase-H
endobj
```

PDF comments are whitespace, so the indirect name object must resolve before a filtered named base CMap can be inherited. Before this patch, the derived ToUnicode stream decoded, but the inherited filtered base CMap mapping was dropped and WordPress-visible text was empty for the inherited CID. After this patch, `PdfTextExtractor` resolves the comment-split indirect name object through the CMap-specific name resolver and records the filtered base stream as `use_cmap` review provenance.

The same slice also keeps the existing fail-closed stale-owner boundary for CMap `/Filter` helpers: if an indirect filter operand resolves only through a stale exact-generation helper while the current xref-selected owner is a different generation, CMap decoding returns null and review reports `filter_resolution_failed`.

## Red-First Evidence

Before the source fix, the new focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php
FAIL resolves comment-split indirect UseCMap name objects before filtered base CMap inheritance
Expected: array (0 => 'Comment Name UseCMap Import')
Actual: array ()
1 test files, 1 assertions, 1 failures
```

An intermediate broader resolver change also exposed the accepted stale-owner boundary in `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`; the final patch scopes name-object resolution to CMap `/UseCMap` and separately rejects unselected indirect CMap filter operands.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-indirect-usecmap-name-filter-currentbase.php
```

All three reported `No syntax errors detected`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 51 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php
```

Result: `2 test files, 1604 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapIndirectUseCMapNameFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php
```

Result: `4 test files, 214 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-indirect-usecmap-name-filter-currentbase.php
```

Smoke reports `comment_split_usecmap_name_resolved=true`, `filtered_base_cmap_inherited=true`, `base_filters=["FlateDecode"]`, `base_usage="use_cmap"`, `base_reference_kind="named_usecmap"`, `use_cmap_stream_count=1`, `decoded_cmap_count=2`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct scalar/array/null malformed `/Filter` tails, malformed DecodeParms, escaped stream dictionary keys, named usecmap inheritance from in-stream operators, object-valued UseCMap malformed stream names, stale post-`endcmap` names, or CMap explicit end-marker failures. The bounded new behavior is only comment-split indirect name-object `/UseCMap` resolution plus matching CMap filter-owner fail-closed enforcement.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, CMap stream decoder, xref-selected owner bookkeeping, Flate stream filter, ToUnicode parser, and WordPress smoke renderer. GPU/model/OCR execution, Surya/Texify/Torch, pypdfium2/PDFium rendering, and external PDF tools were not run and remain out of scope for this markerPDF lane.
