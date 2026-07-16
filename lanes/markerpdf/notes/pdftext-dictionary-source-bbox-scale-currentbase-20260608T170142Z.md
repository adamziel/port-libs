# markerpdf pdftext dictionary source-bbox scale fallback current-base

Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T170142Z`  
Accepted base: `bd26eb8502d3ef58d14ef2894a441f7f7e2fc910`

## Source Truth

- Upstream markerPDF delegates searchable-PDF text extraction to pdftext dictionary output before converting page dictionaries into Marker blocks:
  `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- Locked pdftext `v0.3.18` scales normalized child geometry in `dictionary_output()` using source page geometry before the Marker conversion boundary:
  `https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py` and
  `https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/pdf/utils.py`
- The native no-GPU adapter already treats the pdftext page `bbox` as authoritative page geometry. When an adapter supplies a valid source page bbox but omits optional `width`/`height`, normalized block/line/span/char bboxes must not remain fractional review coordinates.

## Change

- `PdfTextDocumentExtractor::dictionaryOutputBboxScale()` now computes a source-page bbox scale once.
- Rotated pages keep the existing bbox-preferred behavior.
- Non-rotated pages still prefer explicit `width`/`height` when present and valid, but now fall back to the page bbox extents when those optional fields are absent.
- Added a focused test covering block, line, span, kept-character, and stored `char_blocks` bbox scaling without width/height metadata.
- Added a WordPress smoke showing the Gutenberg paragraph path and review metadata stay no-GPU/no-external-tool while non-core dictionary payloads remain excluded.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionarySourceBboxScaleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL scales normalized pdftext child bboxes from source page bbox when width and height are absent
Expected: [60.0,160.0,300.0,200.0]
Actual: [0.1,0.2,0.5,0.25]
1 test files, 7 assertions, 1 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionarySourceBboxScaleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS scales normalized pdftext child bboxes from source page bbox when width and height are absent
1 test files, 20 assertions, 0 failures
```

Adjacent focused coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionarySourceBboxScaleBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 770 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-bbox-scale-currentbase.php
exits 0; source_bbox_scale_used=true; width_height_absent=true; normalized_block_bbox_scaled=true; normalized_char_bbox_scaled=true; raw_payload_excluded=true
```

## Dependency Closure

No new support component is required. This reuses the existing native PHP pdftext dictionary core-boundary sanitizer/converter. It does not launch Python pdftext, OCR, Surya, Texify, GPU/model code, raster rendering, multiprocessing, or external PDF tools.

## Non-Overlap

This does not repeat accepted normalized-bbox scaling from explicit `width`/`height`, off-page normalized bbox scaling, rotated normalized bbox source-page behavior, page-map/list-entry envelope routing, inline image tokenizer recovery, or model/OCR parity work. The slice is limited to the source page bbox fallback when optional width/height fields are absent.
