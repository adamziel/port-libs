# Image XObject Type3 CharProc Boundary Current Base

- Lane: markerpdf
- Micro-slice: markerpdf-image-xobject-boundary-current-base-20260605T204702Z
- Session: port-dev-markerpdf-image-xobject-20260605T204702Z
- Accepted base: 627856fecb6c375f49d0287135d6ea760a6f7f42

## Source Truth

Upstream markerPDF routes searchable PDF text through pdftext/PDF parser behavior and treats rendered images as media/review handoff data rather than paragraph text. Under the current no-GPU scope, this slice maps the native PDF boundary where a used Type3 glyph CharProc paints an Image XObject with `/Do`: the image must be visible to review metadata, while glyph-program payload text and image bytes stay out of WordPress paragraph output.

## Implementation

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now reuses decoded page content streams and augments normal page/Form Image XObject review with used Type3 CharProc image review entries.
- Type3 font resources are resolved from page resources, used glyph names are collected from text-showing operators, and exact CharProc streams are decoded through the existing stream decoder.
- Existing Image XObject review walking is reused for CharProc resource dictionaries so the review row preserves resource name/path, invocation CTM, bbox, dimensions, filter/color metadata, generation, and hashes.
- Type3 provenance fields are attached to each CharProc image row: font resource, font object/generation, glyph name, CharProc object/generation, and a `type3_charproc_image_review` marker.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php
1 test files, 3 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php
1 test files, 39 assertions, 0 failures
```

Focused image/Type3 regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php
7 test files, 1142 assertions, 0 failures
```

Broader image/Type3 current-base glob:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
42 test files, 1453 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-type3-charproc-currentbase.php
```

The smoke emitted one invoked `Glyph Image` review row with `type3_font_resource=Ft3`, `type3_glyph_name=A`, visible text `A`, no unused image promotion, and all Python/model/external-tool execution flags set to false.

## Non-Overlap

This does not repeat page/Form XObject placement, `cm` operand boundaries, optional content, malformed `Do`, `Do` inside text objects, compatibility sections, clipping/page boxes/ExtGState, ImageMask/pattern behavior, encrypted fail-closed image review, or Type3 fallback text exclusion. The new behavior is only the review-only image handoff for Image XObjects invoked from used Type3 CharProc glyph programs.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object table, page resource resolver, Type3 CharProc resolver, content tokenizer, stream decoder, and Image XObject review walker. Live OCR, Surya/Texify/Torch, pypdfium/PIL raster execution, external PDF tools, and model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
