# Pdftext Dictionary Postprocess Boundary

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260603T084007Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes `marker/pdf/extract_text.py::get_text_blocks()` through `pdftext.extraction.dictionary_output(..., keep_chars=False)` before converting pages with `pdftext_format_to_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The locked pdftext dependency boundary is `pdftext` `0.3.18`: `dictionary_output()` calls `_process_span()`, which normalizes special whitespace, removes unsafe control characters, expands common ligatures, and maps pdftext's internal hyphen marker to `-\n` before markerPDF removes that line-hyphen sequence during span conversion: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/postprocessing.py

## Change

`PdfTextDocumentExtractor` now mirrors the pdftext span post-processing step on supplied dictionary pages before handing them to `PdfTextBlockConverter`:

- non-breaking/BOM-style spaces normalize to ASCII spaces;
- unsafe control characters are dropped while pdftext's hyphen sentinel and ordinary whitespace remain valid;
- common Latin ligatures expand to their ASCII text equivalents;
- the pdftext hyphen sentinel becomes `-\n`, so markerPDF's existing converter removes it from Gutenberg-visible paragraph text;
- `char_blocks` retain the upstream dictionary-output text form, while rendered spans receive markerPDF's later `-\n` cleanup.

The WordPress smoke now records normalized visible text, dictionary-output char-block text, and flags proving ligature, special-space, control-byte, and hyphen-sentinel handling without executing Python pdftext, pypdfium, OCR, or model code.

## Verification

```sh
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php
```

All three syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files, 81 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php
```

Result: emitted `normalized_span_text:"Shared hosting fixture document"`, `char_blocks_dictionary_text:"Shared hosting fixture docu-\nment\n"`, and true flags for ligature expansion, special-space normalization, hyphen-sentinel removal from visible text, unsafe-control removal, and no Python/pdftext execution.

```sh
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Both checks passed after this note was added.

## Non-overlap

This does not repeat the accepted pdftext dictionary-core shape/options slice, the keep_chars=false sanitation slice, selected-page layout-order trimming, native PDF parser/xref/font/filter extraction, table/equation supplied-boundary work, runtime preflight slices, or OCR/model paths. The new behavior is specifically pdftext's post-processing of span text at the supplied dictionary boundary before WordPress-visible paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses `PdfTextDocumentExtractor`, `PdfTextBlockConverter`, and the existing WordPress smoke path. Full upstream parity remains gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI workers, benchmark tooling, and external OCR/rendering helpers, which are intentionally out of scope under the current no-GPU directive.
