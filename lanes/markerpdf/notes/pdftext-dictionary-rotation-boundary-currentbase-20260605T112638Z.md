# markerPDF pdftext dictionary rotation boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T112638Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T112638Z`
Base accepted HEAD: `f65eba7003570c9efbe63dbdacffb94594eddf89`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and then converts page dictionaries through `pdftext_format_to_blocks()`.
- Locked pdftext `0.3.18` `pdf/pdf/chars.py` records page rotation from PDFium `page.get_rotation()` as page metadata and records per-character `FPDFText_GetCharAngle` values in degrees before dictionary output.
- `pdf/pdf/utils.py` treats page rotation as the PDFium right-angle page transform, while character angle remains text-run metadata.

## Change

- `PdfTextDocumentExtractor` now preserves fractional keep_chars character rotations instead of truncating them to integers. Integral float angles such as `270.0` still normalize to `270` for stable metadata.
- `PdfTextBlockConverter` now validates page `rotation` as an integer right-angle value: `0`, `90`, `180`, or `270`. Fractional page rotations and unsupported values such as `45` fail closed before WordPress page bbox/rotation metadata is emitted.
- Added a WordPress smoke for the supplied pdftext dictionary rotation boundary.

## Verification

- `php -l lanes/markerpdf/src/PdfTextBlockConverter.php` passed.
- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-rotation-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` => `1 test files, 125 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php` => `3 test files, 454 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-rotation-boundary-currentbase.php` emitted `fractional_character_angle_preserved=true`, `integral_float_character_angle_normalized=true`, `page_rotation_accepted=true`, `fractional_page_rotation_rejected=true`, `unsupported_page_rotation_rejected=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -r '$p="lanes/markerpdf/lane-status.json"; $j=json_decode(file_get_contents($p), true); if (!is_array($j)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Non-overlap

This does not repeat accepted pdftext dictionary page number integer validation, finite numeric bbox/font/ref validation, keep_chars minimal dictionaries, char index validation, font flag validation, refs/link sanitation, disable_links, script-style flags, page source geometry preservation, normalized/off-page bbox handling, dictionary sorting, blank-page handling, layout/order artifact alignment, parser/xref recovery, font/CMap/width extraction, image/filter metadata, annotations/forms/security preflight, Type3 CharProc marked-content width boundaries, or OCR/model behavior. The bounded behavior is only the page-rotation versus character-angle split at the pdftext dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, keep_chars review metadata, page geometry conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
