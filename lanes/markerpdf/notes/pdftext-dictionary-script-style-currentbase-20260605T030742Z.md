# markerPDF pdftext dictionary script style boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T030742Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T030742Z`
Base accepted HEAD: `9cee22bf103c37b2f933968fe17170dd7125153b`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF page dictionaries to `pdftext.extraction.dictionary_output(...)` over the selected `page_range`, then converts those dictionaries into Marker page/span structures without OCR/model execution: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` `0.3.18` `dictionary_output()` post-processes span text and returns span dictionaries after block/line cleanup; current `pdftext.schema.Span` models `superscript` and `subscript` as boolean span metadata: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/master/pdftext/schema.py
- Current Marker `PdfProvider` reads `span.get("superscript", False)` and `span.get("subscript", False)`, trims script span text, and stores `has_superscript` / `has_subscript` on span metadata: https://raw.githubusercontent.com/datalab-to/marker/master/marker/providers/pdf.py

## Change

- `PdfTextDocumentExtractor` now keeps only boolean `superscript` and `subscript` flags at the supplied pdftext dictionary span boundary and rejects non-boolean adapter values.
- `PdfTextBlockConverter` now trims script span text and maps true `superscript` / `subscript` to Marker-style `has_superscript` / `has_subscript` review metadata on rendered spans.
- Sanitized `char_blocks` preserve the source pdftext boolean script flags while excluding private span payload keys before WordPress review metadata.
- Added a WordPress smoke for the script-style dictionary boundary.

## Red First

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files, 45 assertions, 2 failures`. The new script-style test saw missing `has_superscript`, and the non-boolean `superscript="yes"` fixture did not throw `InvalidArgumentException`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfTextBlockConverter.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-script-style-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed: 1 test file / 54 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/MarkdownPostProcessorTest.php` passed: 4 test files / 283 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-script-style-currentbase.php` passed and emitted `superscript_preserved=true`, `subscript_preserved=true`, `char_blocks_script_flags_preserved=true`, `script_text_trimmed=true`, `script_payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +11 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, Marker-style page/span conversion, Markdown/WordPress rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat accepted pdftext page-range slicing, keep-chars false/true sanitation, character/font core allowlists, page refs, normalized bboxes, text post-processing, optional sort, blank selected pages, sparse layout/order artifact matching, OCR/table supplied-boundary routing, parser/xref repair, font/CMap/width extraction, image/filter review, annotations/forms/security preflight, XMP metadata, or runtime conversion preflights. The bounded behavior is only pdftext span superscript/subscript metadata preservation, script text trimming, and non-boolean script flag rejection at the core dictionary boundary.
