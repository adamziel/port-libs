# Image XObject Type3 non-stream CharProc boundary current base

- Lane: markerpdf
- Micro-slice: markerpdf-type3-charprocs-boundary-current-base-20260608T164425Z
- Session: port-dev-markerpdf-type3-charprocs-20260608T164425Z
- Accepted base: 9bb70f8a6d3c3f42d85c8157e5be92a12e648aba

## Source Truth

Upstream markerPDF reaches Type3 glyph programs through the PDF content/parser boundary before OCR or model work. PDF Type3 `/CharProcs` entries are glyph program streams; non-stream objects must not be executed or reviewed as CharProc content. Under the no-GPU PHP scope, this means a non-stream CharProc object containing `/Do`-looking tokens cannot create review-only Image XObject rows.

## Red-First Evidence

A throwaway current-base probe showed `extractImageXObjectBoundaryReview()` counting one invoked `Glyph Image` row from a non-stream CharProc object while visible text remained `A`.

## Change

`PdfTextExtractor::decodedType3CharProcContent()` now returns `null` for non-stream Type3 CharProc object bodies. This matches the existing Type3 width parser boundary and prevents non-stream dictionary/plain bodies from feeding the Image XObject review walker.

## Verification

Focused after-fix command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcNonStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects non-stream Type3 CharProc objects before Image XObject review on current base

1 test files, 14 assertions, 0 failures
```

Adjacent Type3 image/non-stream checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcNonStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcRepeatBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcResourceTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNonStreamObjectBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 131 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-type3-charproc-non-stream-currentbase.php
```

The smoke exits 0 and emits `visible_text_preserved=true`, `non_stream_charproc_image_review_rejected=true`, `non_stream_charproc_payload_excluded=true`, `image_payload_not_promoted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, Type3 CharProc resolver, stream dictionary decoder, Image XObject review walker, and WordPress smoke renderer. Python, PDFium/pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser services, live providers, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted Type3 CharProc image review for valid streams, repeated glyph paints, CharProc resource-tail fallback, non-stream CharProc width rejection, fallback payload privacy, direct/indirect `/CharProcs` dictionary tail rejection, glyph-entry tail rejection, array-wrapped glyph values, stream-filter decoding, image filter metadata, xref repair, metadata, annotations, forms, security preflight, OCR/model work, or supplied table/equation handoffs. The bounded behavior is only non-stream Type3 CharProc rejection before review-only Image XObject traversal.
