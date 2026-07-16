# Type3 CharProcs ColorSpace Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T224913Z`

## Source Truth

The no-GPU markerPDF lane maps searchable-PDF text extraction and review metadata in native PHP before WordPress import. Type3 `/CharProcs` are glyph programs, and resources reachable only from a Type3 font or CharProc resource dictionary are glyph-private drawing resources. ColorSpace profile streams and tint-transform function streams should therefore not be promoted by stream-only fallback text extraction.

This mirrors the same boundary already enforced for Type3 CharProc streams, XObjects, tiling patterns, and ExtGState soft-mask resources without running Python, pypdfium, OCR/model workers, or external PDF tools.

## Red Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php`

Result: `1 test files / 1 assertions / 1 failures`.

The failure showed fallback text lines:

`Visible fallback content`, `Type3 ICC profile stream text leak`, and `Type3 tint transform stream text leak`.

## Implementation

- `PdfTextExtractor::collectType3PrivateResourceStreamGenerations()` now follows Type3-private `/Resources /ColorSpace` values.
- New helpers collect ColorSpace values and recursively mark referenced stream object/generation pairs as private fallback-exclusion streams.
- The behavior is scoped to Type3 font and CharProc resource owners; page-level ColorSpace/image review paths are unchanged.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php`

Result: `1 test files / 9 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-colorspace-fallback-currentbase.php`

Result: emitted `fallback_content_preserved=true`, `charproc_payload_visible_text_excluded=true`, `icc_profile_payload_excluded=true`, `tint_transform_payload_excluded=true`, `colorspace_resource_names_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent Type3 resource gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateSoftMaskBoundaryCurrentBaseTest.php`

Result: `4 test files / 37 assertions / 0 failures`.

Broader Type3 gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php`

Result: `40 test files / 389 assertions / 0 failures`.

## Non-Overlap

This does not repeat accepted Type3 `d0`/`d1` width handling, CharProc fallback exclusion, XObject/pattern/ExtGState private resource exclusion, pattern color operand validation, FontMatrix vector widths, CMap/CIDSet grouping, image ColorSpace preview metadata, xref repair, annotations, forms, metadata, or CCITT Fax filter boundaries. The bounded behavior is specifically Type3-private ColorSpace resource stream exclusion from stream-only fallback WordPress text.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, resource dictionary parser, exact object/generation lookup, stream fallback exclusion path, and WordPress smoke path. GPU/OCR/model execution and external PDF tooling remain intentionally out of scope.
