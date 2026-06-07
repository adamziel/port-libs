# markerpdf-malformed-cmap-filter-boundary-current-base-20260607T045418Z

Base accepted HEAD: `1ebd09b120327dc07dca6734a51a92e37041d05d`.

## Scope

Native no-GPU markerPDF CMap/filter boundary work. This slice covers a filtered
Type0 ToUnicode CMap whose `beginbfrange` block contains a malformed singleton
hex source row before a later valid top-level range row:

```text
1 beginbfrange
<0009>
<0001> <0002> <0041>
endbfrange
```

Before this patch the bfrange tokenizer paired across the row boundary and
treated `<0009> <0001> <0002>` as the declared row, which was then discarded as
an inverted range. The valid `<0001>..<0002>` row disappeared, so the filtered
ToUnicode CMap produced no extracted Type0 text. The parser now applies the
same line-aware malformed-row recovery shape already used for codespace and
bfchar rows when a malformed bfrange row is present and line-local rows recover
the declared range count.

## Evidence

Red-first PHP probe before the parser fix:

```text
php <<'PHP' ... filtered singleton bfrange fixture ...
string(0) ""
array(0) {
}
NULL
```

Focused run after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers valid filtered ToUnicode bfrange rows after malformed singleton rows before Type0 fallback
1 test files, 40 assertions, 0 failures
```

Adjacent bfrange/codespace/source-width family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 120 assertions, 0 failures
```

Adjacent malformed CMap filter family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 1593 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-bfrange-singleton-filter-boundary-currentbase.php
```

The smoke emits one paragraph containing `AB` and metadata confirming
`safe_text_preserved=true`, `nul_bytes_excluded=true`,
`cmap_program_text_excluded=true`, `to_unicode_cmap_stream_count=1`,
`decoded_cmap_count=1`, `filters=["FlateDecode"]`,
`decoded_with_current_operands=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-bfrange-singleton-filter-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lanes/markerpdf/lane-status.json OK\n";'
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the earlier malformed scalar/array/null `/Filter` tails,
malformed DecodeParms, stale-generation filter operands, unsupported filters,
explicit filter terminators, CMap codespace singleton recovery, bfchar singleton
recovery, nested CMap array decoys, WMode token-boundary recovery, object-valued
UseCMap handling, image XObject geometry, OCR/model execution, or PDFium
execution slices.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object
scanner, Flate stream decoder, CMap operator block parser, Type0 text extractor,
and WordPress smoke path. GPU/model/OCR, PDFium, external PDF tools, Surya,
Texify, Torch, and live model workers remain intentionally out of scope for
this no-GPU markerPDF slice.
