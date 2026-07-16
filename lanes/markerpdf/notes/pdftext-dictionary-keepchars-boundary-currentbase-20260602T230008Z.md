# Pdftext Dictionary keep_chars Boundary

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260602T230008Z`

Base accepted HEAD: `1c11c94b45001e6d7041475e1155fe1067d73191`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(..., keep_chars=False, workers=settings.PDFTEXT_CPU_WORKERS, flatten_pdf=settings.FLATTEN_PDF)` from `marker/pdf/extract_text.py::get_text_blocks`, then stores the supplied `page["blocks"]` as page `char_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The locked dependency boundary is pdftext `0.3.18`. Its `dictionary_output` keeps only `lines`/`bbox` on blocks, only `spans`/`bbox` on lines, and `_process_span` removes `span["chars"]` when `keep_chars` is false before returning pages to markerPDF: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextDocumentExtractor` now sanitizes supplied pdftext pages before converting them through `PdfTextBlockConverter`:

- block dictionaries retain only `bbox` and `lines`;
- line dictionaries retain only `bbox` and `spans`;
- span `chars` payloads are removed on the document `get_text_blocks` path to match upstream `keep_chars=false`;
- `char_start_idx`, `char_end_idx`, font metadata, page range metadata, TOC, and Gutenberg-visible text remain preserved.

`PdfTextBlockConverter` also now rejects missing or non-string `span["text"]` at the core dictionary boundary instead of silently casting missing text to an empty string. That mirrors upstream markerPDF/pdftext, where span text is a required string produced by pdftext post-processing.

## Non-overlap

This does not repeat the accepted 2026-06-02 pdftext dictionary-core metadata/options slice. That accepted slice preserved direct converter char metadata and selected-range options. This slice is specifically the document-level `keep_chars=false` sanitation used by markerPDF's `get_text_blocks` path, plus strict span text typing.

## Verification

```sh
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/tests/PdfTextBlockConverterTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php
```

All five syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files, 62 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php
```

Result: emitted a `markerpdf:pdftext-dictionary-core` review comment with `raw_chars_present:false`, `char_blocks_raw_chars_present:false`, core `char_blocks` keys, and a Gutenberg paragraph for the supplied dictionary page.

```sh
git diff --check -- lanes/markerpdf
```

Result: passed.

## Dependency Closure

No new support component is needed. This reuses the existing native supplied-dictionary boundary, `PdfTextBlockConverter`, and Gutenberg paragraph smoke. Full upstream runner parity remains blocked on live Python pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI, benchmark tooling, and external OCR/rendering helpers.
