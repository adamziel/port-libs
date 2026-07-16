# Pdftext Dictionary Empty Span Boundary

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T100748Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260606T100748Z`
Base accepted HEAD: `1420277bc6031a522c9261ef52aa1ee5c7c3d325`

## Source Truth

Upstream pdftext `dictionary_output()` returns an ordered list of page dictionaries and reduces block/line/span dictionaries before marker consumes them. The upstream marker PDF provider then skips spans with empty `text` before creating Marker `Span`/`Char` objects while preserving non-empty span links.

References used for source truth:

- `https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py`
- `https://raw.githubusercontent.com/datalab-to/marker/master/marker/providers/pdf.py`

## Behavior

`PdfTextDocumentExtractor` now drops supplied pdftext spans whose text normalizes to an empty string before link promotion and before optional keep-chars validation. This prevents empty safe-link spans and empty unsafe-link spans from becoming WordPress link/review metadata or `char_blocks` payloads, while non-empty safe spans still render as WordPress Markdown links.

This stays inside the no-GPU markerPDF scope: no live OCR, Surya/Texify/Torch, pypdfium/PDFium, or external PDF tools are executed.

## Red-First Evidence

After adding the focused test, before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL drops empty pdftext spans before link promotion at the dictionary core boundary
Values are not identical
Expected: 1
Actual: 3
1 test files, 233 assertions, 1 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 242 assertions, 0 failures
```

Adjacent pdftext family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 587 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-empty-span-boundary-currentbase.php
```

The smoke emits `empty_safe_link_excluded=true`, `empty_unsafe_link_excluded=true`, `empty_payload_excluded=true`, `visible_safe_link_preserved=true`, `visible_wordpress_text="[Import guide](https://example.com/import-guide)"`, and no Python/model/external PDF tool execution.

Focused delta: +1 focused PASS case, +10 focused core assertions, and +1 WordPress smoke.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted pdftext page-range slicing, dictionary options metadata, JSON object normalization, page envelope unwrapping, link/ref preservation, disable-links suppression, keep-chars character rows, character index validation, script-style flags, normalized/rotated bbox scaling, finite numeric validation, quote-loosebox metadata, blank-page handling, layout-order artifact alignment, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work.

The bounded behavior is specifically normalized-empty pdftext spans at the supplied dictionary core boundary before WordPress link promotion and review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary sanitizer, `PdfTextBlockConverter`, `MarkdownPostProcessor`, and the WordPress smoke path.
