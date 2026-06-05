# markerPDF inline image tokenizer boundary current-base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T110324Z`

Base: `f6dbe30624ad0570d265873814a3f8256148d7bb`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to parser-backed pdftext/PDFium before OCR/model fallback. Under the current no-GPU markerPDF scope, the native PHP lane owns the equivalent content-stream tokenizer boundary for inline images: raster payload bytes stay out of visible text, while valid content immediately after an inline-image `EI` operator remains available for WordPress paragraph and marked-content import.

PDF marked content can wrap visible text with `BMC` or `BDC` ... `EMC`, including `/ActualText` replacement dictionaries. If a preview-only inline image has reached its sample floor before a delimiter-looking `EI`, the tokenizer must be able to close the image at that `EI` when the following segment is balanced marked-content text, not swallow the replacement text as image payload.

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now recognizes balanced marked-content wrappers around the same closed text objects it already trusted for preview-only inline-image recovery. The boundary remains conservative:

- the post-`EI` segment must still be line-separated;
- marked-content operands must be a tag name plus optional property-list name or dictionary;
- `BMC`/`BDC` and `EMC` must balance; and
- the text object, graphics state, and queued operands must all be closed before the fallback boundary is accepted.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
FAIL closes sample-floor preview fallback before marked ActualText spans after EI
Expected: array (
  0 => 'Before Sample Floor ActualText',
  1 => 'Visible Sample Floor ActualText',
  2 => 'After Sample Floor ActualText',
)
Actual: array (
  0 => 'Before Sample Floor ActualText',
  1 => 'After Sample Floor ActualText',
)
1 test files, 228 assertions, 1 failures
```

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 237 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
Preserves Visible Sample Floor ActualText and excludes Hidden Sample Floor Text.
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted LZW post-EOD surplus decode boundary, inline image preview decoding, ASCII85/ASCIIHex/RunLength EOD preview rejection, malformed filter operands, slash-delimited `EI/Name` handling, raw JBIG2/CCITT fallback payload exclusion, JSON/table/model/OCR work, or xref/metadata/font slices.

The bounded new behavior is specifically preview-only inline-image tokenizer fallback when sample-floor data is followed by balanced marked-content `/ActualText` content before a later `EI` token.

## Dependency Closure

No new support component is needed. This reuses native PHP content tokenization, inline-image dictionary/sample-floor detection, marked-content replacement handling, and WordPress smoke rendering. No OCR, Surya, Texify, Torch, Streamlit/FastAPI worker, GPU/model runner, external PDF tool, or online service was used.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior: stream filters, xref repair, metadata, annotations, forms, fonts/CMaps, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
