# markerPDF Inline Image Tokenizer Slash ActualText Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T095645Z`

Base accepted HEAD: `7b0f5549743ece5423a911d7a77b4c45652c9c8d`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser-backed page text before image, OCR, and model stages. At that boundary, inline image bytes from `BI ... ID ... EI` are raster payload and must not become WordPress paragraph text, while following marked-content `/ActualText` is accessible replacement text that should survive import.

This no-GPU native slice locks the PDF lexical delimiter case where the inline-image terminator is immediately followed by a slash-delimited marked-content tag:

```text
EI/Span << /ActualText (...) >> BDC ...
```

The current accepted tokenizer already handles this boundary, so the patch adds focused coverage and WordPress smoke evidence rather than changing parser source.

## Implementation

`PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` now covers `EI/Span << /ActualText ... >> BDC` with no whitespace after the inline-image `EI`. The assertion verifies that:

- inline raster payload text is excluded;
- the hidden marked-content source text is not emitted;
- `/ActualText` replacement text is emitted; and
- the slash-delimited `EI/Span` token boundary does not leak into visible text.

The WordPress smoke `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` emits `slash_after_inline_ei_marked_actualtext_preserved=true` and renders Gutenberg paragraphs for `Slash EI ActualText` and `After Slash Marked EI`.

## Verification

Baseline before this coverage patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 208 assertions, 0 failures
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 217 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `slash_after_inline_ei_marked_actualtext_preserved=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted slash-delimited XObject-name boundary, compact `BI/W...` dictionaries, tight `ID`, post-`ID` comments, tight `EI`, nested dictionary decoys, preview-only JBIG2/CCITT/unsupported filters, visible literal/TJ `EI` text, marked-content fallback after a whitespace-delimited terminator, or post-terminator comment `EI`.

The bounded behavior is specifically a slash-delimited marked-content `/ActualText` tag immediately after an inline-image `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image boundary skipper, marked-content replacement extractor, `PdfTextExtractor`, and the existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
