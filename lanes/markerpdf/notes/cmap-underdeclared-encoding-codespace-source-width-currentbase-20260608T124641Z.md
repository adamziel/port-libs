# markerpdf-cmap-source-width-fallback-current-base-20260608T124641Z

## Scope

This isolated markerPDF patch keeps to the no-GPU native searchable-PDF parser scope. It covers Type0 font source-width fallback when a custom `/Encoding` CID CMap has an underdeclared `begincodespacerange` block.

Upstream source truth remains `sddai/markerPDF` searchable-PDF text extraction through `marker/pdf/extract_text.py` and pdftext `dictionary_output`; this PHP lane ports the native PDF text/font/CMap behavior needed before WordPress import without invoking Python, OCR, Surya, Texify, Torch, raster rendering, or external PDF tools.

## Behavior

`PdfTextExtractor::parseCidCMap()` now mirrors the existing ToUnicode CMap fail-closed boundary for underdeclared codespace blocks. Local CID mapping blocks from that malformed Encoding CMap are ignored, while inherited/predefined base CMap data remains available. This prevents decoy local `begincidchar`/`begincidrange` rows from driving descendant CIDFont `/W` lookup and shrinking searchable text spans.

The focused fixture uses valid `/ToUnicode` source bytes for `Wide Thin`, raw descendant CID widths for those source bytes, and a malformed local Encoding CMap that declares two codespace rows but supplies one before a decoy `begincidrange <10> <13> 60`. Before the fix, the first span used CID 60-63 widths and measured `[0, 0, 12, 12]`. After the fix, source-width fallback uses the valid ToUnicode/raw source keys and measures `[0, 0, 48, 12]`.

## Evidence

Red-first before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredEncodingCodespaceSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores underdeclared Encoding CMap CID ranges for source-width fallback on current base
Values are not identical
Expected: array (... 48.0 ...)
Actual: array (... 12.0 ...)
1 test files, 6 assertions, 1 failures
```

Focused after source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredEncodingCodespaceSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores underdeclared Encoding CMap CID ranges for source-width fallback on current base

1 test files, 12 assertions, 0 failures
```

Adjacent CMap/source-width regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredEncodingCodespaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapDecoyCidMapBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php
6 test files, 561 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-underdeclared-encoding-codespace-source-width-currentbase.php
```

The smoke exits 0 and emits `plain_text_preserved=true`, `runs_preserved=true`, `underdeclared_encoding_cid_widths_rejected=true`, `false_join_excluded=true`, `encoding_cmap_program_visible_text_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ToUnicode underdeclared codespace/row fail-closed behavior, malformed declared-count CID row-slot consumption, decoy unrelated CID map rows, UseCMap inheritance, WMode handling, overlong/short source-width fallback, lazy CID ranges, sparse code-space ordering, notdef ranges, or filtered CMap operand boundaries. The new boundary is specifically a local Type0 Encoding CID CMap whose underdeclared codespace block must prevent its local CID mapping rows from affecting source-width geometry.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP CMap parser, CIDFont width lookup, styled text geometry, and WordPress smoke path under `pdf-text-dictionary-core`.

Root harness not run - isolated micro-slice.
