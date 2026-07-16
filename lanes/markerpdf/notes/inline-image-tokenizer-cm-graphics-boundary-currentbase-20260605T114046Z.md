# markerPDF inline image tokenizer cm graphics-state boundary current-base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T114046Z`

Base accepted HEAD: `4043733c6470ad824b27c09a5a5f192858e694ef`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to parser-backed
pdftext/PDFium before OCR/model fallback. In the native no-GPU lane, the
equivalent boundary is the PDF content-stream tokenizer: `BI ... ID ... EI`
inline image bytes remain raster payload, while valid text content after the
image terminator remains importable.

This slice covers a preview-only inline image payload followed by visible text
inside a balanced graphics-state transform:

```text
EI
q
1 0 0 1 24 0 cm
BT ... Tj ET
Q
EI
```

The tokenizer must close the image at the real fallback `EI`, preserve the
line-separated transformed text, and ignore the later stray `EI` operator.

## Red First

After adding the focused fixture and before changing source, the focused run
failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
FAIL closes preview-only fallback before cm-transformed graphics-state text followed by stray EI operator
Expected: array (
  0 => 'Before CM Wrapped Stray',
  1 => 'Visible CM Wrapped Before Stray',
  2 => 'Visible After CM Wrapped Stray',
)
Actual: array (
  0 => 'Before CM Wrapped Stray',
  1 => 'Visible After CM Wrapped Stray',
)
1 test files, 238 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts
exactly six numeric operands followed by `cm` while deciding whether the
post-fallback segment is valid text content. The boundary remains conservative:
stray numeric operands still reject the segment, `q`/`Q` must balance, and the
text object must close before the fallback boundary is accepted.

## Evidence

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 247 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke metadata emits
`preview_only_cm_wrapped_stray_ei_text_preserved_after_safe_boundary=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, tight `ID`,
comment-bounded `ID`, tight `EI`, compact slash-delimited dictionaries, nested
dictionary decoys, JBIG2/CCITT/unsupported-filter payload closure, visible
literal/TJ-array/marked-content `EI` recovery, post-terminator comment `EI`,
slash-delimited ActualText, plain stray `EI`, q/Q-only graphics-state wrapping,
ASCIIHex/RunLength/Flate/LZW decode boundaries, DCT/JPX preview framing,
object-stream inline-image repair, image review metadata, xref, font, metadata,
or OCR/model work.

## Dependency Closure

No new support component is needed. This reuses native PHP content tokenization,
inline-image dictionary/sample-floor detection, graphics-state-aware text
extraction, focused lane tests, and the existing WordPress smoke. Live OCR,
Surya/Texify/Torch, pypdfium/PDFium runtime execution, Streamlit/FastAPI model
workers, external PDF tools, and GPU/model benchmark parity remain intentionally
out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior: stream
filters, xref repair, metadata, annotations, forms, fonts/CMaps, page geometry,
image/filter metadata, and supplied-boundary table/equation handoffs.
