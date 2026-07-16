# markerPDF Type3 CharProcs dictionary-stream fallback boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T140510Z`

## Source Truth

Upstream markerPDF gets searchable PDF text through PDFium/pdftext page text APIs. Type3 `/CharProcs` glyph streams are font programs, not standalone document text, even when a malformed PDF points `/CharProcs` at a stream object instead of a dictionary.

The native stream-only fallback must therefore keep both the invalid `/CharProcs` stream payload and the glyph streams named in that stream dictionary out of WordPress paragraphs, while preserving unrelated fallback content streams.

## Red Check

Before the source change, an in-memory no-page-tree fixture rendered:

```text
GHOST GLYPH LEAK
Visible fallback content
```

The `/CharProcs 21 0 R` object was correctly excluded as a stream object, but its dictionary-owned `/A 3 0 R` glyph stream was not marked as Type3-private for stream-only fallback exclusion.

## Implementation

`PdfTextExtractor` now uses a fallback-only Type3 CharProc reference reader that still rejects invalid `/CharProcs` stream objects for width metrics, but reads the stream dictionary enough to mark referenced glyph streams as font-private payloads for fallback exclusion.

This keeps existing `d0`/`d1` metric behavior unchanged for valid direct and indirect `/CharProcs` dictionaries.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamFallbackBoundaryCurrentBaseTest.php
```

Result: `1 test files, 7 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php
```

Result: `35 test files, 302 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-stream-fallback-boundary-currentbase.php
```

Smoke output includes `fallback_content_preserved=true`, `charprocs_stream_payload_excluded=true`, `charproc_glyph_payload_excluded=true`, `font_program_name_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders only the WordPress paragraph `Visible fallback content`.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object parser, stream-kind detector, Type3 font boundary tracking, content stream tokenizer, and focused PHP runner.

No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted Type3 fallback exclusion for valid `/CharProcs` dictionaries, exact dictionary generation selection, direct dictionary stream rejection for width grouping, comment-split references, top-level dictionary parsing, nested dictionary exclusion, stream-filter metrics, width precedence, marked-content setup, inline-image rejection, resource fallback, private glyphs, or Type3 CMap/CIDSet width behavior.

The new boundary is only invalid indirect `/CharProcs` stream dictionaries on the stream-only fallback path: the invalid stream remains rejected as a glyph map, and glyph streams named in its dictionary are excluded from visible fallback text.
