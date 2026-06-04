# markerPDF Type3 CharProcs dictionary generation current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260604T233334Z`

## Source Truth

The local upstream markerPDF clone is not available in this isolated worktree,
so this slice uses the pinned markerPDF manifest, accepted Type3 parser notes,
and PDF indirect-object semantics as source truth. In the no-GPU markerPDF
scope, searchable PDF text extraction must treat Type3 `/CharProcs` as glyph
program dictionaries, not page text. An indirect reference such as
`/CharProcs 21 0 R` selects both object number and generation; a same-number
`21 1 obj` dictionary must not supply widths or fallback exclusion rows.

## Red Check

Before the parser change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'Thin Text')

Expected: array (0 => 'ABCD')
Actual:   array (0 => 'GHOST', 1 => 'ABCD')
```

That proved the native parser was resolving the `/CharProcs` dictionary by
object number only, so a stale same-number generation could split WordPress
text and leave the actual glyph program stream visible to stream-only fallback.

## Implementation

`PdfTextExtractor::charProcsDictionaryBody()` now parses the full
`/CharProcs <object> <generation> R` operand and resolves it with the existing
exact-generation object lookup. Direct inline `/CharProcs << ... >>`
dictionaries still use the same token-aware dictionary reader.

The new focused fixture proves:

- page-tree extraction uses the exact generation-zero `/CharProcs` dictionary
  widths to keep `WideBlock` joined while preserving `Thin Text`;
- stream-only fallback excludes streams named by the exact referenced
  dictionary and does not leak `GHOST`;
- CharProc payload text remains excluded from visible WordPress paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php
```

Result: `1 test files, 12 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `10 test files, 705 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-generation-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`exact_charprocs_dictionary_generation_resolved=true`,
`stale_dictionary_widths_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation direct-object inventory, Type3 `/CharProcs` parser, stream
decoder, and text-advance grouping path. No Python, PDFium, pypdfium2, Surya,
Texify, Torch, OCR, GPU/model execution, browser service, or external PDF tool
was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
stream-only direct CharProc fallback exclusion, same-number CharProc stream
generation selection, indirect Type3 Encoding Differences, named/base
Encoding color glyph widths, Type3 CMap/CIDSet grouping, Type3 glyph-name
Unicode recovery, Type0 CID widths, or xref/object-stream generation repair.
The new boundary is specifically the generation on an indirect `/CharProcs`
dictionary object before Type3 width selection and fallback stream exclusion.
