# Type3 CharProcs Direct Dictionary Tail Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T063245Z`

Accepted base: `74e9c7a38f792b3dac3dd9f5166146ec34bf15d3`

## Source truth

The pinned markerPDF conversion path delegates searchable PDF text extraction to PDFium/pdftext before layout, OCR, or model stages. In the native no-GPU PHP lane, Type3 `/CharProcs` remain font glyph programs: valid `d0`/`d1` metrics may affect text advance grouping, but malformed CharProcs dictionaries must not be accepted as glyph maps and CharProc payload streams must not become WordPress paragraphs.

## Red-first evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDirectDictionaryTailBoundaryCurrentBaseTest.php`

Result before source edit: `1 test files, 7 assertions, 1 failures`.

Failure: a direct `/CharProcs <<...>> 99 0 R` value was accepted as the valid dictionary prefix, so the Type3 `1000 0 d0` CharProc width collapsed `Bad Path` into `BadPath`.

## Patch

- `PdfTextExtractor::charProcsDictionaryBody()` now rejects direct `/CharProcs` dictionary values with non-name trailing top-level tokens before Type3 width and Unicode fallback parsing.
- `PdfTextExtractor::charProcObjectReferencesForFallbackExclusion()` still reads the leading malformed direct dictionary only for fallback-private stream exclusion, so glyph program streams named by the malformed map stay out of stream-only WordPress text.

## Verification

Focused direct test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDirectDictionaryTailBoundaryCurrentBaseTest.php`

Result: `1 test files, 12 assertions, 0 failures`.

Adjacent Type3/font family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php`

Result: `41 test files, 401 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-direct-dictionary-tail-currentbase.php`

Result: emitted `charprocs_direct_dictionary_tail_rejected=true`, `fallback_widths_preserve_word_gap=true`, `charproc_payload_visible_text_excluded=true`, `fallback_content_preserved=true`, `malformed_dictionary_glyph_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted Type3 CharProc fallback exclusion, exact stream generation selection, indirect `/CharProcs` dictionary generation selection, malformed indirect `/CharProcs` stream-object rejection, duplicate `/CharProcs` key precedence, nested/top-level dictionary parsing, comment-split references, Type3 FontMatrix width-vector behavior, metric operand validation, marked-content/graphics-state/path setup boundaries, resource fallback exclusion, CMap/CIDSet width behavior, image/filter boundaries, annotations, forms, xref repair, metadata, or supplied table/equation handoffs. The bounded behavior is only malformed direct `/CharProcs` dictionary values whose valid dictionary prefix is followed by non-name top-level tail operands.

## Dependency closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, top-level dictionary parser, Type3 CharProc width parser, fallback stream exclusion inventory, text extractor, and WordPress smoke path. GPU/OCR/model execution and external PDF tools remain intentionally out of scope.
