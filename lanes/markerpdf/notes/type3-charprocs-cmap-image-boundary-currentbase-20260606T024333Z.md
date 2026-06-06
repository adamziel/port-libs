# markerPDF Type3 CharProc CMap image boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T024333Z`

Base accepted HEAD: `b16fe7b8f1a76ae151268ab15841f7714fcf2332`

## Source Truth

The no-GPU markerPDF lane maps searchable-PDF text extraction and image/media
handoff boundaries natively. Type3 CharProc glyph programs can be reached by
CMap-encoded source bytes; their image paints are review metadata, while glyph
program text and image payload bytes must not become WordPress paragraph text.

Existing coverage handled simple one-byte Type3 glyph image review and separate
Type3 CMap text decoding. This slice joins those paths: a page shows Type3 text
with source code `<AB01>`, the font Encoding CMap maps that source to CID 65,
CID 65 resolves to glyph `/A`, and `/A` paints an Image XObject from the Type3
font resource dictionary.

## Red-First Probe

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php`

Result:

`Expected: 1`, `Actual: 0` for `image_xobject_count`, proving the review walker
missed the used CMap-encoded CharProc image even though text extraction decoded
the glyph.

## Implementation

`PdfTextExtractor::type3UsedGlyphNamesByFontResource()` now builds Type3 used
glyphs through the existing CMap source-key and CID mapping helpers when a
Type3 font has an Encoding CMap. It falls back to the prior one-byte glyph
mapping for simple encodings.

The patch is bounded to Type3 CharProc glyph-use detection for image review. It
does not alter text extraction, CharProc width parsing, CMap parsing, image
stream decoding, or payload exposure rules.

## Focused Evidence

New focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php`

Result: `1 test files, 40 assertions, 0 failures`.

Adjacent CMap/Type3 image run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php`

Result: `6 test files, 113 assertions, 0 failures`.

Broader Type3 family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php`

Result: `46 test files, 475 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-type3-charproc-cmap-currentbase.php`

Result: emitted `image_xobject_count=1`, `invoked_image_xobject_count=1`,
`uninvoked_image_xobject_count=0`, `type3_font_resource=Ft3`,
`type3_glyph_name=A`, `type3_resource_path=["Type3 Ft3","A","Glyph Image"]`,
`visible_text=A`, `cmap_source_bytes_excluded_from_text=true`,
`charproc_text_excluded=true`, `glyph_payload_excluded_from_text=true`,
`unused_payload_excluded_from_text=true`, `payload_bytes_excluded_from_review_json=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted simple Type3 CharProc image review, page/Form
image XObject review, image CTM placement, Type3 CharProc payload exclusion,
Type3 CMap text extraction, Type3 CharProc width parsing, Type3 resource
fallback exclusion, ImageMask/SMask/Decode/filter metadata, xref repair,
metadata, annotations, forms, table/equation supplied-boundary handoff, OCR,
model execution, or external PDF/raster tools. The bounded behavior is only
used-glyph discovery for CMap-encoded Type3 CharProc Image XObject review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object
scanner, CMap parser, Type3 CharProc resolver, text operand source-key
segmentation, CID mapping helpers, stream decoder, image XObject review walker,
and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PIL raster
execution, external PDF tools, and exact upstream model benchmark parity remain
intentionally out of scope for this no-GPU markerPDF slice.
