# markerPDF inline image tokenizer graphics-state stray EI boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T103005Z`

Base accepted HEAD: `17084c137d0018e6cf17e49bcac91c3e1cb47745`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` sends searchable PDF text through parser-backed extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while later content-stream text inside balanced graphics-state `q`/`Q` wrappers remains visible document text.

This no-GPU native slice covers the tokenizer edge where a preview-only inline image fallback is followed by a line-separated closed `BT ... ET` text object wrapped in `q`/`Q`, then a later stray bare `EI` operator. The tokenizer must close at the real inline-image fallback, not at the later stray `EI`.

## Red First

A current-base probe before the source edit returned:

```text
array (
  0 => 'Before Q Wrapped Stray',
  1 => 'Visible After Q Wrapped Stray',
)
```

The missing line was `Visible Q Wrapped Before Stray`; it had been swallowed into the preview-only inline image fallback because the fallback segment checker only recognized bare line-separated `BT ... ET` text objects.

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts balanced top-level `q` and `Q` graphics-state wrappers around the already supported closed text-object segment. It still requires line separation, at least one text-showing operator in a closed text object, and balanced graphics-state depth, so payload-noise segments with extra bytes remain closed until the safer fallback.

`PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` adds the q/Q-wrapped stray-EI fixture, and `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` now emits `preview_only_q_wrapped_stray_ei_text_preserved_after_safe_boundary=true`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 227 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

All syntax checks reported no errors.

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits the q/Q-wrapped stray-EI flag as `true`, with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, tight `ID`, comment-bounded `ID`, tight `EI`, compact slash-delimited dictionaries, nested dictionary decoys, JBIG2/CCITT/unsupported-filter payload closure, visible literal/TJ-array/marked-content `EI` recovery, post-terminator comment `EI`, slash-delimited ActualText, plain line-separated stray `EI`, ASCIIHex/RunLength/Flate/LZW decode boundaries, DCT/JPX preview framing, object-stream inline-image repair, or inline image review metadata.

The bounded behavior is specifically graphics-state wrapped text between a preview-only inline image fallback and a later stray bare `EI` token.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only filter boundary logic, graphics-state-aware text extraction path, focused lane test, and WordPress smoke. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
