# pdftext dictionary visible text unicode repair current-base slice

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T230825Z`

Accepted base: `9bc98b158862d33e28ef4bd037982fe4f403bc3b`

## Source truth

Upstream `sddai/markerPDF` routes searchable-PDF text through `pdftext.extraction.dictionary_output`, then constructs Marker `Span` objects. Upstream `pdftext` postprocesses dictionary rows before handoff, while Marker `Span` text is normalized with `ftfy.fix_text`. This patch ports that visible-text boundary in native PHP for common UTF-8 bytes decoded as Windows-1252/Latin-1 mojibake.

## Behavior

- `PdfTextBlockConverter` now repairs common visible-span mojibake such as e-acute and right single quote double-decoding artifacts.
- The repair only applies to rendered Marker spans after existing pdftext dictionary postprocessing.
- Sanitized `char_blocks` keep the pdftext dictionary source text for review, so source-faithful per-character metadata is not silently rewritten.
- Non-core pdftext payload keys remain excluded from both rendered output and review metadata.

## Evidence

Red-first focused run before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: expected repaired visible span text, actual mojibake visible span text; `1 test files / 191 assertions / 1 failures`.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files / 198 assertions / 0 failures`.

Adjacent focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`

Result: `4 test files / 814 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdftext-dictionary-unicode-repair-currentbase.php`

Result: emitted a repaired Gutenberg paragraph, preserved mojibake source text in `char_blocks`, excluded hidden raw payload keys, and reported `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks passed for the changed PHP source, test, and example. `git diff --check -- lanes/markerpdf` passed after the lane metadata update.

## Dependency closure

No new support component is needed. The existing native pdftext dictionary core boundary is reused. No Python pdftext runtime, pypdfium/PDFium, Poppler, Ghostscript, OCR, Surya, Texify, Torch, model workers, or online services were invoked.

## Non-overlap

This slice does not touch live OCR/model behavior, supplied layout/order matching, stream filters, CMaps, xref repair, annotations, forms, attachments, or image payload handling. It is limited to visible text normalization after supplied pdftext dictionary rows have already crossed the core boundary.
