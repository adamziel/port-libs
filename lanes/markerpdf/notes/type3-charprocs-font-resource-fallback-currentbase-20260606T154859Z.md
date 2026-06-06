# Type3 CharProcs Font Resource Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T154859Z`

Base accepted HEAD: `fcc419a73630550abf6ce8bf9772fa5c0f06b701`

## Source Truth

The pinned markerPDF lane manifest records upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, where searchable PDF text is extracted before OCR/model handoff. In the native no-GPU PHP path, Type3 `/CharProcs` are glyph programs: their `d0`/`d1` metrics may affect text advance grouping, but their private resources must not become WordPress-visible fallback paragraphs.

PDF Type3 glyph programs may carry private `/Resources`. Existing current-base coverage excluded Type3-private XObject, Pattern, ExtGState soft-mask, ColorSpace, Shading, and Properties streams from stream-only fallback extraction. The remaining boundary covered here is Type3-private `/Resources /Font` entries whose `/FontDescriptor` points at `/FontFile*` or `/CIDSet` streams.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFontResourceFallbackBoundaryCurrentBaseTest.php`

Before the fix, the fallback lines were:

```text
Visible fallback content
Type3 font resource FontFile text leak
Type3 stream font resource FontFile text leak
Type3 stream font resource CIDSet text leak
```

Result: `1 test files, 1 assertions, 1 failures`.

## Implementation

`PdfTextExtractor::collectType3PrivateResourceStreamGenerations()` now also walks Type3-private `/Resources /Font` values and reuses the existing private stream-generation collector. This marks nested font descriptor streams such as `/FontFile2` and `/CIDSet` as glyph-private when reached from a Type3 font or CharProc resource owner.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFontResourceFallbackBoundaryCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

Type3 CharProc family:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' -o -name 'PdfImageXObjectType3CharProc*CurrentBaseTest.php' \) | sort)`

Result: `48 test files, 509 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-font-resource-fallback-currentbase.php`

Result: emitted `fallback_content_preserved=true`, `charproc_payload_visible_text_excluded=true`, `font_program_payload_excluded=true`, `font_descriptor_private_stream_excluded=true`, `font_resource_names_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch hygiene:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

Result: `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`.

`php -l lanes/markerpdf/tests/PdfFontType3CharProcsFontResourceFallbackBoundaryCurrentBaseTest.php`

Result: `No syntax errors detected in lanes/markerpdf/tests/PdfFontType3CharProcsFontResourceFallbackBoundaryCurrentBaseTest.php`.

`php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-font-resource-fallback-currentbase.php`

Result: `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-font-resource-fallback-currentbase.php`.

`php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Result: `lane-status json ok`.

`git diff --check -- lanes/markerpdf`

Result: passed with no output.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 1 WordPress import smoke.
- Mapped upstream denominator: unchanged; this extends the already mapped native Type3 CharProc/font-resource boundary cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, exact-generation object lookup, resource-category parser, Type3 private stream exclusion walker, stream decoder, text fallback extractor, and WordPress smoke renderer. Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser services, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted Type3 direct CharProc payload exclusion, width-vector parsing, FontMatrix normalization, exact-generation CharProcs, indirect CharProcs dictionaries, dictionary-stream fallback, pre-metric operator validation, graphics-state and marked-content balance, Pattern/ColorSpace/Shading/Properties resource fallback, ExtGState soft-mask fallback, Type3 image review, page resource font inheritance, CMap/font width behavior, xref repair, metadata, attachments, annotations, forms, image filters, table/equation handoffs, OCR, or model execution. The bounded behavior is only Type3-private `/Resources /Font` descriptor streams during stream-only fallback extraction.
