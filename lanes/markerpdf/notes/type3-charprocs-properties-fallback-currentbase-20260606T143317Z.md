# Type3 CharProcs Properties Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T143317Z`
Base accepted HEAD: `dbf88db9fb156d2bf6c53c71b80f704c08d7bae2`

## Source Truth

The upstream markerPDF searchable-PDF path gets page text from PDFium/pdftext page extraction, while Type3 glyph programs and their private resources are drawing programs, not document paragraphs. In this no-GPU native PHP lane, stream-only fallback extraction therefore has to exclude Type3 CharProc-private resource streams before WordPress paragraph rendering.

This slice covers the previously missing Type3 `/Resources /Properties` boundary. Marked-content property-list resources reached from a Type3 font or a CharProc stream are glyph-private, including nested helper streams referenced from a property stream dictionary.

## Behavior

- `PdfTextExtractor` now walks Type3-private `/Resources /Properties` entries alongside the existing XObject, Pattern, ExtGState soft-mask, ColorSpace, and Shading resource traversal.
- Private Type3 stream dictionaries now recurse through their dictionary references, so nested property helper streams are excluded from fallback visible text as well.
- The fixture has no page tree to exercise the stream-only fallback path directly, while preserving one ordinary visible fallback content stream.

## Red Probe

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPropertiesResourceFallbackBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 1 assertions, 1 failures`; actual lines included `Type3 font property stream text leak`, `Type3 stream property text leak`, and `nested Type3 property text leak`.

## Verification

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPropertiesResourceFallbackBoundaryCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

Adjacent Type3 private-resource gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPropertiesResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsShadingResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateSoftMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamFallbackBoundaryCurrentBaseTest.php`

Result: `7 test files, 63 assertions, 0 failures`.

Adjacent marked-content property metric gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php`

Result: `6 test files, 64 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-properties-fallback-currentbase.php`

Result: emitted `fallback_content_preserved=true`, `charproc_payload_visible_text_excluded=true`, `font_property_payload_excluded=true`, `stream_property_payload_excluded=true`, `nested_property_payload_excluded=true`, `property_resource_names_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontType3CharProcsPropertiesResourceFallbackBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-properties-fallback-currentbase.php`

All reported no syntax errors. `git diff --check -- lanes/markerpdf` is required before final handoff.

## Non-overlap

This does not repeat accepted Type3 `d0`/`d1` width handling, FontMatrix vector/scalar advance, Encoding/CharProcs comment references, duplicate CharProcs keys, glyph-tail malformed reference rejection, marked-content metric operands, compatibility sections, graphics-state balancing, inline images, XObject/Form resources, Pattern resources, Shading resources, ColorSpace resources, ExtGState soft masks, image review, CMap/font-width behavior, xref repair, metadata, annotations, forms, security preflight, or supplied-boundary table/equation handoffs. The bounded behavior is only Type3-private `/Resources /Properties` streams and nested helper streams in the stream-only fallback path.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object scanner, resource dictionary parser, Type3 CharProc private-resource traversal, stream decoder, fallback text extractor, and WordPress smoke path. GPU/OCR/model execution, PDFium, pypdfium, PIL, Surya, Texify, Torch, and external PDF tools remain intentionally out of scope.
