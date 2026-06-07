# Type3 CharProcs ExtGState Resource Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260607T011719Z`

Base accepted HEAD: `4841a8141eb09153691392303a67ae59443e4510`

## Source Truth

The pinned markerPDF lane manifest records upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, where searchable PDF text is extracted before any OCR/model handoff. In the native no-GPU PHP path, Type3 `/CharProcs` are glyph programs: their metrics and resource effects may influence glyph painting and review metadata, but glyph-private helper streams must not become WordPress-visible fallback paragraphs.

Existing current-base coverage excluded Type3 CharProc streams, XObject resources, Pattern resources, ExtGState soft-mask graphs, ColorSpace resources, Shading resources, Properties resources, and Font descriptor streams from stream-only fallback extraction. The bounded gap covered here is normal Type3-private `/Resources /ExtGState` entries whose dictionaries point at transfer-function, black-generation, halftone, or nested function streams without using `/SMask`.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateResourceFallbackBoundaryCurrentBaseTest.php`

Before the fix, the fallback lines were:

```text
Visible fallback content
Type3 ExtGState transfer function text leak
Type3 ExtGState black generation text leak
Type3 ExtGState halftone stream text leak
Type3 stream ExtGState transfer text leak
```

Result: `1 test files, 1 assertions, 1 failures`.

## Implementation

`PdfTextExtractor::collectType3PrivateResourceStreamGenerations()` now walks Type3-private `/Resources /ExtGState` values with the same recursive private stream-generation collector already used by ColorSpace, Shading, Properties, and Font resources. This marks streams nested below Type3 font-level and CharProc stream-level ExtGState dictionaries as glyph-private before stream-only fallback decoding runs.

The existing ExtGState soft-mask walker remains in place for its more specific `/SMask` form/XObject graph handling; this slice adds the generic resource-category path around it.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateResourceFallbackBoundaryCurrentBaseTest.php`

Result: `1 test files, 11 assertions, 0 failures`.

Type3 CharProc family:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' -o -name 'PdfImageXObjectType3CharProc*CurrentBaseTest.php' \) | sort)`

Result: `49 test files, 520 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-extgstate-resource-fallback-currentbase.php`

Result: emitted `fallback_content_preserved=true`, `charproc_payload_visible_text_excluded=true`, `transfer_function_payload_excluded=true`, `halftone_payload_excluded=true`, `black_generation_payload_excluded=true`, `stream_transfer_payload_excluded=true`, `extgstate_resource_names_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch hygiene:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

Result: `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`.

`php -l lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateResourceFallbackBoundaryCurrentBaseTest.php`

Result: `No syntax errors detected in lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateResourceFallbackBoundaryCurrentBaseTest.php`.

`php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-extgstate-resource-fallback-currentbase.php`

Result: `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-extgstate-resource-fallback-currentbase.php`.

`php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Result: `lane-status json ok`.

`git diff --check -- lanes/markerpdf`

Result: passed with no output.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 11 focused assertions.
- Added 1 WordPress import smoke.
- `lane-status.json` moved `phpPass` from `2730` to `2731` and `wordpressScenarios` from `2300` to `2301`.
- Mapped upstream denominator: unchanged; this extends the already mapped native Type3 CharProc resource-boundary cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, exact-generation object lookup, resource-category parser, Type3 private stream exclusion walker, stream decoder, text fallback extractor, and WordPress smoke renderer. Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser services, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted Type3 direct CharProc payload exclusion, width-vector parsing, FontMatrix normalization, exact-generation CharProcs, indirect CharProcs dictionaries, dictionary-stream fallback, pre-metric operator validation, graphics-state and marked-content balance, XObject/Pattern/ColorSpace/Shading/Properties/Font resource fallback, ExtGState soft-mask fallback, Type3 image review, page resource font inheritance, CMap/font width behavior, xref repair, metadata, attachments, annotations, forms, image filters, table/equation handoffs, OCR, or model execution. The bounded behavior is only generic Type3-private `/Resources /ExtGState` helper streams during stream-only fallback extraction.
