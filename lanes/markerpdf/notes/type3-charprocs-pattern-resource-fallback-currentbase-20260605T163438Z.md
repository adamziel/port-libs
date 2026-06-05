# Type3 CharProcs Pattern Resource Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T163438Z`

Accepted base: `f8c365b5b1fd87bac0411884c446464b5c9c15f7`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to native PDF text boundaries rather than treating glyph paint programs as document paragraphs. For Type3 fonts, CharProc streams and their private paint resources are font glyph programs; tiling Pattern streams referenced from Type3 `/Resources /Pattern` must remain glyph-private during stream-only fallback extraction, the same way current native coverage excludes Type3 CharProc streams and Type3-owned Form XObject resources.

## Red-First Boundary

A page-less stream-only fallback fixture with:

- a Type3 font `/CharProcs` glyph stream;
- Type3 `/Resources << /Pattern << /GlyphPattern 30 0 R >> >>`;
- a tiling Pattern stream containing text;
- a nested Form XObject inside the Pattern stream also containing text;
- a separate visible fallback content stream.

Before the patch, `PdfTextExtractor::extractTextLines()` returned `Visible fallback content` plus `Type3 pattern resource text leak`.

## Implementation

`PdfTextExtractor` now walks Type3 private resource streams through both `/XObject` and `/Pattern` resource dictionaries, with the existing object-generation cycle guard. Pattern streams and nested Form XObjects reached from those Pattern resources are excluded from fallback visible text.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php`  
  `1 test files / 9 assertions / 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php`  
  `38 test files / 335 assertions / 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-pattern-resource-fallback-currentbase.php`  
  emits `fallback_content_preserved=true`, `pattern_resource_payload_excluded=true`, and `nested_pattern_xobject_payload_excluded=true`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF tokenizer, resource dictionary resolver, Pattern resource reference parser, and stream fallback exclusion path. GPU/model OCR, PDFium, PIL, Python, and external PDF tools remain intentionally out of scope.
