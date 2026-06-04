# markerPDF pdftext dictionary page-source boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260604T231724Z`

Base accepted HEAD: `fd0f5327abfd3b58715219a1c13c4c8295941253`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, calls `pdftext.extraction.dictionary_output(...)`, and then converts each page dictionary with `pdftext_format_to_blocks()`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The locked pdftext `0.3.18` dependency boundary leaves page-level `page`, `bbox`, `width`, `height`, and `rotation` on dictionary pages while normalizing block/line/span content: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextBlockConverter` now preserves the pdftext dictionary page source geometry under `pdftext_source`:

- `page`, original source `bbox`, and `rotation` are always recorded;
- optional source `width` and `height` are recorded when present and must be numeric;
- Marker rendered page `bbox` remains derived from the source bbox and rotation, so WordPress preview/render dimensions stay unchanged;
- visible Gutenberg paragraph text and `char_blocks` behavior are unchanged.

The WordPress smoke exposes the source geometry in review metadata while rendering the same paragraph output and recording that no Python pdftext, models, or external PDF tools were executed.

## Verification

```sh
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextBlockConverterTest.php
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-page-currentbase.php
```

All four syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `2 test files, 125 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-page-currentbase.php
```

Result: emitted `source_dimensions_preserved:true`, `rotation_bbox_separated:true`, `executes_python_pdftext:false`, and `executes_models_or_external_pdf_tools:false`, followed by a Gutenberg paragraph for `Source page geometry remains review metadata.`

```sh
git diff --check -- lanes/markerpdf
```

Result: passed.

## Non-overlap

This does not repeat accepted keep_chars=false sanitation, span postprocessing, optional dictionary sort, selected blank-page preservation, selected layout/order artifact slicing, keyed artifact page matching, or source-index collision exclusion. The bounded behavior is only preserving page-level pdftext dictionary geometry separately from Marker's rendered page bbox.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter and WordPress smoke path. Live pdftext/pypdfium execution, Surya/Torch OCR/layout models, Texify, tabled-pdf model inference, Streamlit/FastAPI workers, and exact upstream benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
