# Inline Image Tokenizer Uppercase Tight ID Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260607T132202Z`

Base accepted HEAD: `59106df0e35fdecc7b1b35bc2177e020af569a4f`

## Source Truth

- Upstream `sddai/markerPDF` text extraction delegates searchable-PDF content to `pdftext.extraction.dictionary_output(...)`; this no-GPU port slice keeps to native searchable-PDF content stream tokenization and does not invoke OCR, Surya, Texify, Torch, raster rendering, external PDF tools, or model workers.
- PDF inline image payloads may begin immediately after the `ID` operator. The existing tokenizer already admitted lowercase tight data starts such as `IDabc`; this slice adds the missing compact boundary where the first payload bytes look like uppercase content operators.

## Behavior

- `PdfTextExtractor::inlineImageDataBoundaryOffset()` now keeps its malformed-content guard for operator-looking tight `ID` suffixes unless the parsed inline image dictionary provides a tokenizer sample boundary through direct width/height/color/bits metadata.
- Valid compact payloads such as `IDAB EI ...` and `IDBT /F1 ...` are treated as inline image data when the dictionary sample floor is known, so fake text inside the image payload does not leak into WordPress paragraph extraction.
- Malformed `BI` decoys without a computable image sample boundary still fail closed and remain text-tokenizer content.

## Evidence

Red-first probes on the current base leaked `Uppercase Tight ID Inline Payload Noise` and `Uppercase Operator Tight ID Payload Noise` from compact uppercase data after `ID`.

Before adding this slice, the focused tokenizer test passed at `1 test files, 707 assertions, 0 failures`.

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 725 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
uppercase_tight_id_inline_payload_excluded_until_sample_boundary=true
uppercase_operator_tight_id_payload_excluded_until_sample_boundary=true
visible_text_imported=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All completed without errors.

## Non-Overlap

This patch does not repeat the accepted named-destination legacy duplicate-key slice, image XObject review metadata, xref repair, encryption preflight, annotations/forms, page geometry, or prior inline image filter/terminator coverage. It only adjusts compact inline image `ID` data-boundary admission for uppercase/operator-looking sample bytes when the inline image dictionary provides a sample floor.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP PDF content-stream tokenizer, inline image dictionary parsing, and sample-boundary helpers under the current no-GPU markerPDF scope.
