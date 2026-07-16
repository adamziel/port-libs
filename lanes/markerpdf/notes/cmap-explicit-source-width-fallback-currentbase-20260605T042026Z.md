# markerPDF CMap explicit source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T042026Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T042026Z`
Base accepted HEAD: `a4eb702f7ee7d99c8c98d4d754371b79ebaa9e9b`

## Source Truth

The pinned upstream `sddai/markerPDF` pipeline routes searchable PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this PHP lane maps the native PDF text-showing, CMap, and font-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. A malformed but recoverable ToUnicode CMap can declare a one-byte codespace while still carrying explicit longer `bfchar` source keys. The parser must not split `<0041>` into decoy `<00>` plus `<41>` when an explicit `<0041>` mapping exists, and the same source key must drive CIDFont width lookup so WordPress span geometry and paragraph gaps remain stable.

## Implementation

`PdfTextExtractor::toUnicodeSourceLength()` now compares the longest explicit mapped source key at the current byte offset with the matching codespace width and chooses the longer valid source boundary. This preserves accepted Identity-H metric fallback behavior where the longer CID codespace is the correct width source, while letting explicit longer ToUnicode/CID keys win over malformed shorter codespace ranges.

The focused fixture adds decoy one-byte mappings for `<00>`, `<41>` through `<48>`, plus authoritative two-byte mappings for `<0041>` through `<0048>`. Before the fix, the native extractor emitted `ZxZyZzZwZqZrZsZt`; after the fix, it emits `ABCD EFGH`, preserves two text runs, and keeps source-width span bboxes at `[0,0,48,12]` and `[48,0,60,12]`.

## Evidence

Red-first focused check after adding the new fixture and before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL prefers explicit longer ToUnicode source keys over narrow codespace before width fallback on current base
Expected: ['ABCD EFGH']
Actual: ['ZxZyZzZwZqZrZsZt']
1 test files, 121 assertions, 1 failures
```

Passing focused check after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
PASS prefers explicit longer ToUnicode source keys over narrow codespace before width fallback on current base
1 test files, 132 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `explicit_long_source_key_precedes_narrow_codespace=true`, `explicit_long_source_runs_preserved=true`, `explicit_long_source_decoy_prefix_excluded=true`, `explicit_long_source_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H fallback, CIDFont default `/DW` fallback, metric-miss ToUnicode fallback, partial CID metric miss repair, horizontal/vertical `TJ` adjustment gap repair, odd hex padding, or one-byte codespace padding fallback. The new boundary is specifically explicit longer CMap source-key precedence over a shorter matching codespace range before font-width extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content-token parser, CMap parser, ToUnicode decoder, CIDFont width metrics, text-run/line/styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
