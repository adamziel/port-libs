# markerPDF CMap Defineresource UseCMap Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260606T054116Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T054116Z`
Base accepted HEAD: `5918e02b2644d9134b3cf328783815ce2823b34a`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU markerPDF scope, searchable PDF text extraction must honor PDF CMap inheritance before OCR/model handoff. CMap resources commonly name themselves after `endcmap` with `CMapName currentdict /Name defineresource pop`; that postlude is metadata for resolving later `usecmap` references, not extra text or mapping rows.

## Behavior

`PdfTextExtractor` now recovers named CMap resource names from the full decoded CMap stream before the parser trims the program to the bounded `endcmap` body. The parser still ignores late `usecmap` and late mapping operators after source mapping starts, but named prologue `usecmap` references can now inherit base ToUnicode rows. Source-width grouping then applies descendant CIDFont `/W` metrics to inherited base rows and local derived rows before WordPress paragraph rendering.

## Red Baseline

Before the source change, the adjacent focused extractor test exposed the current-base gap:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL inherits ToUnicode usecmap mappings before WordPress text extraction
Expected: 'Import Blocks'
Actual: '  "'
FAIL guards cyclic ToUnicode usecmap inheritance and codespace counts before WordPress text extraction
Expected: 'Import Blocks! OK'
Actual: 'Import!"! OK'
1 test files, 626 assertions, 2 failures
```

The existing source-width family was otherwise green before this slice at `1 test files, 338 assertions, 0 failures`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 349 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 629 assertions, 0 failures
```

The WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-defineresource-usecmap-source-width-currentbase.php
```

emits `defineresource_name_registered=true`, `base_usecmap_rows_inherited=true`, `derived_rows_preserved=true`, `source_width_spans_applied=true`, `unmapped_source_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2 widths, late malformed `usecmap` rejection, high CID range expansion, lazy large ToUnicode bfrange rows, ToUnicode block-order precedence, CMap comments, malformed CMap filter boundaries, Type3 CharProc widths, page resource inheritance, xref repair, image/filter metadata, annotations/forms/security, OCR, or model execution. The bounded behavior is specifically post-`endcmap` `defineresource` CMap name recovery before prologue `usecmap` inheritance and source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, named-CMap registry, ToUnicode inheritance, CIDFont width metrics, text run/line/styled-span extraction, and WordPress smoke path. Live OCR, Surya/Texify/Torch, raster PDFium/PIL rendering, JavaScript/action execution, and exact upstream GPU/model benchmark parity remain intentionally out of scope.
