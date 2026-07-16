# Page Resource Null Whitespace Current Base - 2026-06-07

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260607T030217Z`

Accepted base: `a078a096a4cf93f92c4400252bcd9ac19a5f846a`

## Behavior

PDF NUL bytes are lexical whitespace. This slice makes the native searchable-PDF path treat NUL bytes as whitespace while parsing:

- inherited page-tree `/Resources` indirect references such as `10 0 R`;
- resource subdictionary entries such as `/F1 5 0 R`, `/NullWsForm 7 0 R`, and `/NullActual 8 0 R`;
- page-boundary review metadata for resolved resource owner/object/category names.

The regression fixture proves that a page inheriting resources through NUL-byte separators resolves its Type0 font `/ToUnicode`, invoked Form XObject text, and marked-content `/ActualText`. Raw glyph fallback text and resource names stay out of WordPress visible paragraphs.

## Source Truth

The upstream markerPDF architecture in `UPSTREAM_TEST_MANIFEST.json` maps searchable PDF text through the parser/pdftext layer before OCR or model paths. The relevant PDF-parser behavior is lexical tokenization: NUL is one of the whitespace delimiters, so indirect references remain valid when object/generation/reference tokens are separated by NUL bytes. This no-GPU PHP port keeps that parser boundary native and does not run Surya, Texify, Torch, OCR, pypdfium, or external PDF tools.

## Evidence

Red-first focused command before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceNullWhitespaceCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceNullWhitespaceCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 0 failures`.

Adjacent page-resource/text family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

Result: `32 test files, 1641 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-resource-null-whitespace-currentbase.php`

Result: exit 0; emitted three WordPress paragraph blocks and review metadata with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not touch live OCR, model workers, inline image tokenizer behavior, xref repair, encryption preflight, annotation/form/action review, or image filter metadata. It is limited to native PDF lexical whitespace handling for page resource inheritance and resource-entry indirect references.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object/resource parsers and broad page-resource tests. Remaining model/OCR parity is intentionally out of scope under the no-GPU markerPDF directive.
