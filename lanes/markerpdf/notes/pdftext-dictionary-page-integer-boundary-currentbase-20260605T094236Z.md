# markerPDF pdftext dictionary page integer boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T094236Z`

Base accepted HEAD: `9105b370e55e2df798ab302c88fab91716c37a45`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, calls `pdftext.extraction.dictionary_output(...)`, then converts each selected page dictionary with `pdftext_format_to_blocks()`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext==0.3.18` `dictionary_output()` keeps page-level metadata such as `page`, `bbox`, `width`, `height`, and `rotation` while pruning block and line dictionaries and scaling child bboxes before Marker receives the page dictionaries: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextBlockConverter` now validates the top-level pdftext dictionary `page` value through the existing integer metadata guard before it emits Marker `pnum` and `pdftext_source.page`.

This prevents malformed supplied dictionaries such as `page: 12.75` from silently truncating to `12` in WordPress page metadata. Integral floats such as `12.0` still normalize to integer `12` for adapter compatibility.

## Red-First Evidence

Before the source fix, the new focused case failed:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `Expected exception InvalidArgumentException was not thrown` for `rejects fractional pdftext page numbers before WordPress page metadata`.

## Verification

```sh
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-page-currentbase.php
```

Result: all three syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 107 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files, 327 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-page-currentbase.php
```

Result: emitted `fractional_page_rejected:true`, `source_dimensions_preserved:true`, `rotation_bbox_separated:true`, `executes_python_pdftext:false`, and `executes_models_or_external_pdf_tools:false`, followed by the expected Gutenberg paragraph.

```sh
php -r '$p="lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
```

Result: `manifest json ok`.

```sh
git diff --check -- lanes/markerpdf
```

Result: passed.

## Non-Overlap

This does not repeat accepted pdftext keep_chars sanitation, ref integer metadata, span/char index validation, script flag preservation, normalized bbox scaling, optional dictionary sort, blank page preservation, or layout-order artifact selection. The bounded behavior is only the top-level source page identifier before Marker/WordPress page metadata is emitted.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, integer metadata guard, and WordPress smoke path. Live pdftext/pypdfium execution, Surya/Torch OCR/layout models, Texify, table recognition models, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
