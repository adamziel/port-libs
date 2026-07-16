# Type3 CharProcs Shading Resource Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T122909Z`

Accepted base: `f15cbc9106adbb92bb890518a310c78c306e1f13`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to native PDF text boundaries rather than treating glyph paint programs as document paragraphs. For Type3 fonts, `/CharProcs` streams and their private paint resources are font glyph programs. Existing native coverage excluded Type3 CharProc streams plus private XObject, Pattern, ExtGState soft-mask, and ColorSpace resources from stream-only fallback extraction. This slice closes the adjacent `/Resources /Shading` boundary so shading streams and nested Function streams reached only through Type3 glyph resources remain review-only and do not become WordPress paragraph text.

## Red-First Boundary

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsShadingResourceFallbackBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'Visible fallback content',
  1 => 'Type3 shading resource text leak',
  2 => 'Type3 shading function text leak',
)

1 test files, 1 assertions, 1 failures
```

The fixture has no page tree, so it exercises stream-only fallback directly. A Type3 font references a glyph CharProc with `/GlyphShade sh /FunctionShade sh`; `/GlyphShade` points at a shading stream and `/FunctionShade` points at a shading dictionary that references a Function stream. Both are Type3-private paint resources and must not leak into visible fallback text.

## Implementation

`PdfTextExtractor::collectType3PrivateResourceStreamGenerations()` now also walks Type3-private `/Shading` resource dictionary values. The previous ColorSpace value scanner was generalized to a resource-category value scanner and reused for both `ColorSpace` and `Shading`. Existing object-generation cycle guards and indirect-reference recursion are preserved, so direct shading streams and nested Function streams are excluded before fallback stream text extraction.

## Verification

Focused test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsShadingResourceFallbackBoundaryCurrentBaseTest.php
```

Result: `1 test files / 9 assertions / 0 failures`.

Adjacent Type3/font family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' -o -name 'PdfFontType3*Test.php' -o -name 'PdfFontSimpleType3*Test.php' -o -name 'PdfFontCMapCidType3*Test.php' -o -name 'PdfFontCidType3*Test.php' \) -printf '%p\n' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `49 test files / 1071 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-shading-resource-fallback-currentbase.php
```

Result: emitted `fallback_content_preserved=true`, `charproc_payload_visible_text_excluded=true`, `shading_resource_payload_excluded=true`, `shading_function_payload_excluded=true`, `shading_resource_names_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, resource dictionary resolver, exact-generation object lookup, Type3 private-resource walker, stream decoder, fallback text extractor, and WordPress smoke path. GPU/OCR/model execution, pypdfium2/PDFium rendering, Surya, Texify, Torch, Python workers, browser services, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, FontMatrix normalization, fallback CharProc stream exclusion, indirect `/CharProcs` dictionary generation selection, top-level/nested CharProcs dictionary parsing, Type3 stream-filter fail-closed behavior, XObject/Form private-resource exclusion, Pattern private-resource exclusion, ExtGState soft-mask exclusion, ColorSpace private-resource exclusion, Type3 image review, pre-metric paint rejection, CMap/CIDSet/ToUnicode width grouping, xref repair, metadata extraction, annotations, forms, image filters, or supplied-boundary table/equation behavior. The bounded behavior is only Type3-private `/Resources /Shading` stream and nested Function stream fallback exclusion before WordPress paragraph rendering.
