# markerPDF Inline Image Tokenizer Named Property ActualText Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T170657Z`

Base accepted HEAD: `4cc4c34e199d77834513eab45aee0fc3c1d75619`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser-backed page text before image extraction, OCR, and model stages. At this native no-GPU boundary, inline image bytes from `BI ... ID ... EI` are raster payload and must not become WordPress paragraph text. Marked-content replacement text remains searchable text, including named property resources declared under page `/Resources /Properties`.

This slice locks the token adjacency where a real inline image terminator is immediately followed by a slash-delimited marked-content tag and named property resource:

```text
BI /W 1 /H 1 /CS /G /BPC 8 ID
x
EI/Span /PActual BDC ... EMC

/Resources << /Properties << /PActual << /ActualText (...) >> >> >>
```

The current native tokenizer and marked-content resolver already handle this boundary on the accepted base, so the patch adds focused coverage and WordPress smoke evidence without changing parser source.

## Implementation

`PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` now covers `EI/Span /PActual BDC` with no whitespace after the inline-image `EI`. The assertion verifies that:

- inline image payload text remains excluded;
- the hidden marked-content glyph text is not emitted;
- named `/Properties` `/ActualText` replacement text is emitted;
- the `/PActual` resource name and `EI/Span` tokenizer boundary do not leak into visible text.

The WordPress smoke `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` now emits `slash_after_inline_ei_named_property_actualtext_preserved=true` and renders Gutenberg paragraphs for `Named Property EI ActualText` and `After Named Property EI`.

## Verification

Baseline before this coverage patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 321 assertions, 0 failures
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 331 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `slash_after_inline_ei_named_property_actualtext_preserved=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, slash-delimited compact dictionaries, nested dictionary decoys, text-object `BI` decoys, tight `ID`, comments after `ID`, tight `EI`, DCT/JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, unsupported `/Crypt` filter boundaries, named ColorSpace fallback, visible literal/TJ `EI` recovery, inline dictionary `/ActualText` after `EI/Span`, sample-floor marked-content recovery, post-terminator comment `EI`, later stray `EI` operators, or graphics-state/path/color wrapped stray `EI` recovery.

The bounded behavior is specifically a named marked-content property resource (`/PActual`) immediately after an inline-image `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image boundary skipper, page resource `/Properties` resolver, marked-content replacement extractor, `PdfTextExtractor`, and the existing WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
