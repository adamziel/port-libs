# markerPDF Type3 CharProcs filter boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T021911Z`

Base accepted HEAD: `a175cabd679ccca994e830f85a8667633082f21c`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR/layout/model work. In the native no-GPU PHP scope, this lane owns
the PDF parser boundary: Type3 `/CharProcs` glyph streams can provide `d0` and
`d1` width metrics, but filtered streams must be decoded successfully before
their bytes are trusted. A CharProc stream that declares `/Filter /FlateDecode`
and fails decoding must not be scanned as raw glyph-program text for fake width
operators.

## Red Behavior

Before the source change, an ad-hoc current-base probe built a Type3 font with:

- a valid `/FlateDecode` wide CharProc stream containing `1000 0 d0`;
- a malformed `/FlateDecode` CharProc stream containing raw `1000 0 d0`;
- a valid unfiltered thin CharProc stream containing `250 ... d1`;
- `/FontDescriptor /MissingWidth 250` for glyphs whose CharProc metrics are
  rejected.

The extractor returned:

```text
array (
  0 => 'ThinJoin',
)
ThinJoin
```

That proved `PdfTextExtractor::type3CharProcDeclaredWidth()` fell back to
scanning the raw stream object body after the filter decoder rejected the
malformed CharProc stream.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidth()` now distinguishes actual
stream objects from non-stream fallback bodies. When a CharProc is a stream, it
reads `d0`/`d1` only from decoded stream bytes. If the stream is image-like or
the declared filter stack fails, width extraction returns `null` and the
existing font default-width path can decide text advance grouping.

The fixture proves:

- valid filtered Type3 CharProc streams still decode and keep `WideBlock`
  joined;
- malformed filtered Type3 CharProc streams fail closed instead of trusting raw
  `d0` bytes;
- descriptor `MissingWidth` remains the fallback for rejected CharProc metrics;
- valid unfiltered `d1` CharProcs still preserve `Thin Join`;
- CharProc payload text remains excluded from visible WordPress paragraphs.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 10 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `15 test files, 746 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-filter-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Join`, with
`valid_filtered_charproc_widths_decoded=true`,
`malformed_filtered_charproc_widths_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Additional local checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-filter-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, stream dictionary/payload parser, stream filter
decoder, Type3 CharProc width parser, descriptor default-width fallback, and
WordPress smoke path. No Python, PDFium, pypdfium2, Surya, Texify, Torch,
OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, top-level
`/CharProcs` lookup, nested CharProcs dictionary parsing, Type3 subtype
gating, Type3 FontMatrix normalization, Type3 Encoding Differences,
named/base Encoding color glyph widths, Type3 CMap/CIDSet grouping, Type3
descriptor `MissingWidth`, Type3 glyph-name Unicode recovery, stream-filter
DecodeParms fail-closed page text behavior, Type0 CID widths, or xref/object
stream repair. The new boundary is specifically fail-closed decoding for
filtered Type3 CharProc glyph streams before WordPress text-advance grouping.
