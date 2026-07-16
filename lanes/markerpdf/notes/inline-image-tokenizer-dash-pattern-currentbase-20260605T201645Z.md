# markerPDF Inline Image Dash-Pattern Tokenizer Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T201645Z`

Base accepted HEAD: `b7c69a82698c2416756edbae2bb3a28381b7f166`

## Upstream Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction to parser-backed PDF text extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes remain raster payload, while normal content-stream graphics-state operators before later `BT ... ET` text must not be swallowed by the inline-image tokenizer.

## Behavior

`PdfTextExtractor` now recognizes the standard PDF dash-pattern graphics-state operator `[dash-array] phase d` inside the preview-only inline-image fallback close heuristic. This lets the tokenizer close an incomplete preview-only inline image at the safe fallback boundary when the following content sets a dashed line style before visible text and a later stray `EI` operator.

Before the fix, a red probe extracted only:

```text
Before Dash Stray
Visible After Dash Stray
```

and dropped `Visible Dash Pattern Before Stray`.

After the fix, the focused fixture preserves:

```text
Before Dash Pattern Stray
Visible Dash Pattern Before Stray
Visible After Dash Pattern Stray
```

while excluding `Dash Pattern Payload Noise`, `[3 1] 0 d`, and `rawtail` from visible WordPress text.

## Verification

Focused tokenizer run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 364 assertions, 0 failures
```

The focused file moved from 353 to 364 assertions and adds one PASS case.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_dash_pattern_stray_ei_text_preserved_after_safe_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Additional non-gate observation: an adjacent 12-file inline-image/PdfTextExtractor family run reached `2226 assertions` but failed two existing CMap `usecmap` assertions in `PdfTextExtractorTest.php`; running `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` alone reproduces the same two CMap failures. That is outside this inline-image dash-pattern tokenizer slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85/ASCIIHex/Flate/LZW/RunLength decoded sample-floor boundaries, DCT/JPX/JBIG2/CCITT preview-only framing, inline filter-array null handling, tight `ID`/`EI`, comment/NUL tokenizer separators, slash-delimited `EI`, color-state, pattern-color, shading, clipping-path, XObject, cm, q/Q, or same-line stray-`EI` fallback cases.

The new bounded behavior is specifically post-inline-image dash-pattern graphics-state content before visible text and a later stray `EI` operator.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, PDF array parser, numeric operand handling, `PdfTextExtractor`, and the existing WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
