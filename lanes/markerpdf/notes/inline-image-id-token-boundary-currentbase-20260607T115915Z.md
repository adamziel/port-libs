# Inline Image ID Token Boundary Current Base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260607T115915Z`

Accepted base: `a4e5a61b976d13766fd0cce92c076b3e65a81418`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium before model or OCR stages. For native no-GPU parity, inline image handling must preserve the PDF content-stream operator boundary: `BI`, standalone `ID`, image bytes, and `EI` delimit image payloads before text tokenization. A bare operator-like token such as `IDENTITY` after inline-image dictionary operands is not a standalone `ID` data boundary and must fail open to visible text extraction instead of hiding following text as image bytes.

## Change

- `PdfTextExtractor` now rejects operator-like `ID` prefixes such as `IDENTITY` when probing inline-image data starts.
- Existing accepted compact image-data recovery remains intact for tight inline image data such as `IDabc...` and tight `EI` sample-floor boundaries.
- Added focused declared-Length and missing-Length PDF fixtures proving malformed `ID` prefixes preserve WordPress-visible text while a subsequent valid inline image still excludes payload text.
- Added a WordPress smoke example emitting block paragraphs plus review metadata without Python, OCR, model execution, raster decoding, or external PDF tools.

## Evidence

Red-first before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
FAIL rejects ID-prefixed inline image dictionary tokens before WordPress text import
FAIL rejects ID-prefixed inline image tokens while scanning missing-Length content streams
1 test files, 2 assertions, 2 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageIdTokenBoundaryCurrentBaseTest.php
1 test files, 16 assertions, 0 failures
```

Related inline-image regression checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 707 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 914 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-id-token-boundary-currentbase.php
exits 0
```

## Dependency Closure

No new support component is required. This slice reuses the native PHP content-stream tokenizer and PDF fixture helpers under `lanes/markerpdf`; it does not require GPU/model execution, OCR, pypdfium, PIL, Python, or external PDF binaries.
