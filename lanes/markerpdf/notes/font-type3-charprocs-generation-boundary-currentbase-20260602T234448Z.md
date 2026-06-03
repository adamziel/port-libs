# markerPDF Type3 CharProcs generation boundary current base

## Source Truth

The local markerPDF upstream clone recorded by the manifest is not present in
the shared upstream cache, so this slice uses the pinned markerPDF manifest,
accepted Type3/PDF parser lane notes, and PDF indirect-object semantics as
source truth. The relevant upstream no-GPU boundary is PDFium/pdftext-style
native text extraction from searchable PDFs: Type3 `/CharProcs` streams are
glyph programs, `d0`/`d1` declare glyph metrics, and CharProc payload text is
not page-visible WordPress text.

This slice closes the exact-generation boundary for same-number CharProc
streams. A Type3 `/CharProcs` dictionary can reference `3 0 R` for wide glyph
programs and `3 1 R` for thin glyph programs in the same current graph; width
resolution must use the referenced object generation instead of collapsing all
references to whichever body is selected by object-number fallback.

## Red Check

Before the change, a current-base scratch fixture with `/W.wide 3 0 R` and
`/T.thin 3 1 R` returned:

```text
array (
  0 => 'Wide Block',
  1 => 'Thin Text',
)
```

That proved the wide line was using the thin generation's width and inserting a
false WordPress paragraph gap.

## Implementation

`PdfTextExtractor` now keeps direct object bodies by exact object generation for
the current parser run. Type3 `/CharProcs` entries retain both object number and
generation, and `type3CharProcWidths()` resolves the exact referenced stream
before reading `d0`/`d1` metrics.

The new focused fixture proves:

- `3 0 R` wide CharProc streams keep `WideBlock` joined.
- `3 1 R` thin CharProc streams keep `Thin Text` separated.
- CharProc `BT ... Tj ... ET` payload text remains excluded from visible
  WordPress paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php
```

Result: `1 test files, 7 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `8 test files, 665 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-generation-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text` with
`wide_generation_width_preserved=true`,
`thin_generation_width_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object
scanner, direct-object generation inventory, Type3 `/CharProcs` dictionary
parser, stream decoder, and text-advance grouping path. Full upstream runner
parity remains intentionally out of scope under the current no-GPU markerPDF
direction: no Surya/Texify/Torch, OCR/model execution, pypdfium/PDFium runtime,
or external PDF tools were invoked.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, indirect
Type3 Encoding Differences, named/base Encoding color glyph widths, Type3
CMap/CIDSet grouping, Type3 CharProc glyph-name Unicode recovery, Type0 CID
widths, or xref/object-stream generation repair. The new boundary is
specifically same-number Type3 CharProc stream references whose object
generations differ inside one current-base font graph.
