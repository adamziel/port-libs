## OCR Language Triage Confidence Boundary Current Base

Source truth:

- `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, targeted reads from `/tmp/markerpdf-source-truth-da6a2f5/marker/ocr/{lang,heuristics,recognition}.py`.
- `marker.ocr.recognition.run_ocr` selects OCR pages by `no_text_found`, `should_ocr_page`, and `detect_bad_ocr`; it replaces only recognized pages whose `prelim_text` is non-empty and not bad OCR.
- `surya-ocr==0.6.13` source at `/tmp/markerpdf-surya-src/surya/{schema,ocr}.py` exposes OCR `TextLine.confidence` and page `languages`, but markerPDF does not use confidence as the page replacement threshold.

Implementation:

- `OcrRecognition::buildSuryaRecognitionPages()` now carries optional supplied Surya line confidence into line/span review metadata, records page-level confidence summary, and preserves normalized OCR language codes on reconstructed OCR pages.
- `OcrRecognition::runWithSuppliedPages()` remains aligned with upstream markerPDF triage: confidence is review-only, while successful replacement still depends on text quality through `detectBadOcr()`.
- The WordPress smoke proves low-confidence clean OCR is rendered into a block, while high-confidence garbled OCR remains rejected and does not leak into visible Gutenberg text.

Verification:

- `php tools/run-tests.php lanes/markerpdf/tests/OcrLanguageTest.php lanes/markerpdf/tests/OcrHeuristicsTest.php lanes/markerpdf/tests/OcrRecognitionTest.php`
  - Result: `3 test files, 69 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-ocr-language-confidence-triage-currentbase.php`
  - Result: emitted `ocr_pages=2`, `ocr_success=1`, `ocr_failed=1`, languages `["en","es"]`, token IDs `[65555,65557]`, and accepted confidence `0.08`.

Dependency closure:

- No new support component is needed. This slice reuses the existing lane-local supplied OCR result boundary and the already mapped Surya language/tokenizer tables; live Surya, OCRmyPDF, Tesseract, pdftext, pypdfium, model downloads, and raster execution remain outside this isolated micro-slice.

Non-overlap:

- Avoids recent markerPDF PDF parser/xref/font/image/AcroForm/security/current-base clusters and does not repeat existing standalone OCR language, detection, or recognition tests. This adds only the composed language + confidence + replacement triage boundary on the current base.
