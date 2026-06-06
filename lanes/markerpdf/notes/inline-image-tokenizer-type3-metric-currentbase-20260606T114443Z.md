# markerPDF Inline Image Tokenizer Type3 Metric Boundary Current Base

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to parser-backed pdftext/PDFium before image/OCR fallback. Inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload, while PDF content after the real `EI` remains visible content. Type3 glyph metric operators `d0` and `d1` are valid PDF content operators in glyph streams and must not force the preview-only inline-image fallback to consume later visible text while looking for a later stray `EI`.

## Behavior

`PdfTextExtractor` now treats `d0` with exactly two numeric operands and `d1` with exactly six numeric operands as valid post-inline-image content while validating preview-only fallback candidate boundaries. The metric operators are accepted only as non-visible content operators; their operands are not imported into WordPress paragraphs.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Before the source edit, the new fixture failed with `1 test files, 484 assertions, 1 failures` because the fallback selected the later stray `EI` and omitted visible d0/d1-adjacent text.

## Verification

Focused tokenizer test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 496 assertions, 0 failures`.

Adjacent inline-image and Type3 boundary family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php`

Result: `5 test files, 1318 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

Result: exit 0 with `preview_only_type3_metric_stray_ei_text_preserved_after_safe_boundary=true`.

Syntax and lane hygiene:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

`php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

Result: no syntax errors detected.

`php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

`php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`

Result: both JSON files decode successfully.

`git diff --check -- lanes/markerpdf`

Result: clean.

## Non-Overlap

This does not repeat malformed standalone `BI` recovery, tight `ID`/`EI` sample-floor behavior, JBIG2/CCITT/DCT/JPX filter boundaries, color/shading/dash/text-state/compatibility/external-close fallback operators, or Type3 CharProc width extraction. The new slice is limited to Type3 `d0`/`d1` metric operators inside preview-only inline-image fallback content validation.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP content tokenizer, numeric operand parser, inline-image fallback validation, and existing no-GPU PDF text extraction path. No Python, OCR, model, raster, PDFium, PIL, or external PDF tool execution was introduced.
