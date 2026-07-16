# markerPDF Type3 CharProcs top-level boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T000655Z`

## Source truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext/PDFium
before its native pipeline assembles pages, blocks, lines, and spans. In that
boundary, PDF Type3 `/CharProcs` are font-dictionary glyph programs. A nested
resource name such as `/Resources << /XObject << /CharProcs ... >> >>` is not
the font's glyph-program dictionary and must not supply glyph widths or fallback
stream exclusion rows.

## Red check

Before the parser change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'Thin Text')
```

That proved the native parser selected a nested resource `/CharProcs` decoy
before the top-level Type3 font `/CharProcs` dictionary.

## Implementation

`PdfTextExtractor::charProcsDictionaryBody()` now resolves `/CharProcs` through
the existing top-level name scanner. Indirect dictionaries still resolve by
exact object generation at that top-level offset, and direct inline top-level
`/CharProcs << ... >>` dictionaries still use the token-aware dictionary reader.

The focused fixture proves:

- top-level Type3 glyph widths keep `WideBlock` joined while preserving
  `Thin Text`;
- nested resource `/XObject /CharProcs` dictionaries do not drive width
  selection;
- real Type3 CharProc payload streams and nested decoy streams stay out of
  visible WordPress paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php
```

Result: `1 test files, 8 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `11 test files, 713 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-top-level-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`top_level_charprocs_widths_preserved=true`,
`thin_width_spacing_preserved=true`, `nested_resource_charprocs_ignored=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
top-level dictionary scanner, exact-generation object lookup, Type3 CharProc
parser, stream decoder, and text-advance grouping path. No Python, PDFium,
pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser service, or
external PDF tool was run.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, direct
CharProc fallback exclusion, CharProc stream generation selection, indirect
CharProcs dictionary exact-generation selection, named/base Encoding color
glyph widths, Type3 CMap/CIDSet grouping, Type3 glyph-name Unicode recovery,
Type0 CID widths, or xref/object-stream repair. The new boundary is
specifically top-level `/CharProcs` lookup within a Type3 font dictionary when
nested resource dictionaries also contain a `/CharProcs` name.
