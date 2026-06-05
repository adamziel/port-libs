# markerPDF Type3 CharProcs resource subtype boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T032453Z`

Base accepted HEAD: `b3f4a458caf974825db7d13e0547615ffa201d28`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR, layout, table, or equation model work. In this native no-GPU PHP
scope, the relevant parser boundary is PDF text extraction: Type3
`/CharProcs` streams are glyph programs, and their `d0`/`d1` operators supply
glyph advance widths before WordPress paragraph grouping. Image XObject streams
remain review-only image payloads, but a nested image resource dictionary inside
a Type3 CharProc stream dictionary is not the CharProc stream's own subtype.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Thin Text',
)
Actual: array (
  0 => 'Wide Block',
  1 => 'Thin Text',
)
```

That proved the native image-stream guard matched nested
`/Resources /XObject ... /Subtype /Image` metadata inside a CharProc stream
dictionary and rejected a valid `1000 0 d0` glyph program before width grouping.

## Implementation

`PdfTextExtractor::isImageStreamDictionary()` now treats `/Subtype /Image` as
image-like only when `/Subtype` is a top-level stream dictionary key. Existing
top-level `/Width`, `/Height`, `/BitsPerComponent`, `/ImageMask`, and
`/ColorSpace` image heuristics remain unchanged.

The focused fixture proves:

- a Type3 CharProc stream with nested image-resource subtype metadata still
  decodes as a glyph program and keeps `WideBlock` joined;
- a normal thin `d1` CharProc still preserves `Thin Text`;
- CharProc payload text remains excluded from visible WordPress paragraphs;
- adjacent image XObject and inline-image review boundaries still pass.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
```

Result: `16 test files, 136 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php
```

Result: `6 test files, 347 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-subtype-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`nested_image_subtype_decoy_ignored=true`,
`thin_width_boundary_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Additional local checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-subtype-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all passed; `git diff --check -- lanes/markerpdf` produced no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
top-level dictionary scanner, stream dictionary/payload parser, stream decoder,
Type3 CharProc width parser, ToUnicode CMap parser, image-stream review guards,
and WordPress smoke path. No Python, PDFium, pypdfium2, Surya, Texify, Torch,
OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc
fallback exclusion, same-number CharProc stream generation selection, indirect
CharProcs dictionary exact-generation selection, top-level `/CharProcs` lookup,
nested CharProcs dictionary parsing, Type3 subtype gating, Type3 FontMatrix
normalization, filtered CharProc fail-closed decoding, private glyph fallback,
Type3 Encoding Differences, named/base Encoding color glyph widths, Type3
CMap/CIDSet grouping, Type3 descriptor `MissingWidth`, Type0 CID widths, or
xref/object-stream repair. The new boundary is specifically top-level
image-stream subtype classification when a Type3 CharProc stream dictionary
contains nested image-resource subtype metadata.
