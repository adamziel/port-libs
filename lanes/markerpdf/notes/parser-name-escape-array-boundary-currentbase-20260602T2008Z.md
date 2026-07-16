# markerPDF Parser Name Escape Array Boundary Current Base

Micro-slice: `parser-name-escape-array-boundary-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches PDF text through `marker/pdf/extract_text.py::naive_get_text()` / `get_text_blocks()` and the pdftext/pypdfium parsing boundary. The reduced PHP parser must therefore expose parsed page text, not raw PDF operator or hidden-layer bytes.
- PDF name objects are delimited by raw delimiter characters. `#hh` escapes are decoded after tokenization, so `/Hidden#4Cayer` is one name object, while `/OC/Hidden#4Cayer` is two adjacent name objects even without whitespace.
- Optional-content `/ON[...]` and `/OFF[...]` arrays decide whether marked-content text reaches visible page text. A hidden OCG referenced by an escaped resource name must stay out of WordPress paragraphs.

## Implementation

- `PdfTextExtractor` now reads PDF name tokens explicitly in content streams, inline-image dictionaries, and generic PDF array-item parsing.
- The name-token reader treats raw `/` as a delimiter after the leading slash while retaining `#hh` escape bytes in the raw token for later `decodePdfName()` handling.
- The focused fixture uses compact catalog `/OCProperties` arrays and escaped page `/Resources /Properties` names to prove hidden optional-content text is filtered before text extraction.

## Red/Green Evidence

Before the source change, an in-memory probe using `/OC/Hidden#4Cayer BDC` emitted:

```text
Hidden Adjacent Leak
Visible Text
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS splits adjacent escaped PDF names at array boundaries before optional-content text extraction

1 test files, 10 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php
6 test files, 652 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-name-escape-array-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-parser-name-escape-array-boundary-currentbase.php
```

The smoke emits only `Visible escaped layer` and records `escaped_hidden_property_resolved=true`, `escaped_visible_property_resolved=true`, `ocg_on_off_arrays_without_whitespace=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `758 -> 759` pass / `0` fail.
- Mapped parser semantics: `540 -> 541 / 78`.
- WordPress scenario: hidden optional-content review layers addressed through escaped, adjacent PDF names no longer leak into Gutenberg paragraphs.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, optional-content state parser, content-stream tokenizer, name decoder, stream boundary handling, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted escaped stream dictionary keys, indirect `/Filter` name arrays, stream-filter fail-closed boundaries, inline-image filter-array abbreviations, optional-content visibility basics, stream token owner boundaries, or Type0/font resource name escape coverage. The new behavior is specifically PDF name token splitting at raw slash delimiters while preserving `#hh` escapes, proven through compact optional-content arrays before WordPress text import.
