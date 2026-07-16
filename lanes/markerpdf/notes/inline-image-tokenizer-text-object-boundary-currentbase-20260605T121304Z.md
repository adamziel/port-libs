# markerPDF Inline Image Tokenizer Text Object Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T121304Z`

Base accepted HEAD: `3b99ef994373d3fa0c896d104eac78039d1beb66`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser-backed page text before image, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline images are image operators in page graphics content, not valid operators inside a `BT ... ET` text object. A native tokenizer must therefore not let image-looking `BI` bytes inside a text object swallow visible WordPress text.

## Red First

A current-base one-off probe before the source change returned only:

```text
array (
  0 => 'Before Text Object BIAfter Text Object BI',
)
```

The same fixture expected `Text Object BI Survives` between those phrases. The accepted tokenizer was applying `skipInlineImage()` to a bare `BI` token while still inside `BT ... ET`, so the image-looking `/W /H /CS /BPC ID ... EI` sequence swallowed the intervening text object content.

## Implementation

`PdfTextExtractor::contentTokens()` now tracks `BT`/`ET` lexical state while scanning content streams and only applies inline-image skipping when the tokenizer is outside a text object. Existing valid inline-image boundaries outside text objects still route through the same dictionary parser, sample-floor checks, filter-aware terminator checks, and preview-only fallback logic.

`PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` adds a malformed searchable-PDF fixture with an image-looking `BI ... ID ... EI` sequence inside a text object. The test proves:

- `Text Object BI Survives` is imported as visible text;
- neighboring text before and after the decoy remains ordered;
- image dictionary tokens such as `/W` and `/BitsPerComponent` are not promoted to visible text; and
- existing inline-image tokenizer cases still pass.

The WordPress smoke `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` now emits `text_object_bi_decoy_preserved_as_text_boundary=true` and renders Gutenberg paragraphs for `Before Text Object BI`, `Text Object BI Survives`, and `After Text Object BI`.

## Verification

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

All reported no syntax errors.

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 256 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `text_object_bi_decoy_preserved_as_text_boundary=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery outside text objects, tight `ID`, post-`ID` comments, tight `EI`, compact slash-delimited dictionaries, nested dictionary decoys, JBIG2/raw-JBIG2/CCITT/unsupported-filter open-ended payload boundaries, visible literal `EI`, `TJ` array `EI`, marked-content `/ActualText` `EI`, slash-delimited `EI/name`, ASCIIHex/RunLength/Flate/LZW decode boundaries, DCT/JPX/JBIG2/CCITT preview framing, or inline image review metadata.

The bounded behavior is specifically image-looking `BI ... ID ... EI` decoys encountered inside a `BT ... ET` text object.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, text-object operator scanner, `PdfTextExtractor`, focused lane tests, and WordPress smoke path. Full upstream model/OCR/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this native parser slice.
