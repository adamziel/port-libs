# markerPDF Upstream Test Inventory

Inventory source: shallow clone of `https://github.com/sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` in `.upstream-cache/markerpdf`.

## Counted Denominator

- Repository files inspected with `git ls-tree`: 78.
- Python files: 47, including 39 files under `marker/`.
- Committed Python unit tests: 0 found.
- CI benchmark workflow: 1 integration workflow, `.github/workflows/tests.yml`.
- Benchmark/scoring scripts inspected: `benchmarks/overall.py`, `marker/benchmark/scoring.py`, `marker/benchmark/table.py`, and `scripts/verify_benchmark_scores.py`.
- Benchmark scoring functions inspected: 6 (`chunk_text`, `overlap_score`, `score_text`, `split_to_cells`, `align_rows`, and `score_table`).
- Cleaner/postprocessor/source utilities inspected: 7 cleaner modules with 17 functions under `marker/cleaners/`, 8 functions in `marker/postprocessors/markdown.py`, `marker/layout/order.py`, `marker/pdf/utils.py`, `marker/ocr/heuristics.py`, and `marker/ocr/utils.py`.
- Cleaner functions mapped: `marker/cleaners/bullets.py::replace_bullets`, `marker/cleaners/text.py::cleanup_text`, `marker/cleaners/fontstyle.py::find_bold_italic`, five focused functions from `marker/cleaners/headers.py` (`filter_common_elements`, `filter_header_footer`, `replace_leading_trailing_digits`, `find_overlap_elements`, and `filter_common_titles`), four focused functions from `marker/cleaners/code.py` (`is_code_linelen`, `comment_count`, `identify_code_blocks`, and `indent_blocks`), all three functions from `marker/cleaners/headings.py` (`split_heading_blocks`, `bucket_headings`, and `infer_heading_levels`), and `marker/cleaners/toc.py::compute_toc`.
- Markdown postprocessor functions mapped: 7 of 8 (`escape_markdown`, `surround_text`, `block_surround`, `line_separator`, `block_separator`, `merge_lines`, and `get_full_text`). The styled-span branch of `merge_spans` is represented by `FontStyleCleaner`, but the full page-level wrapper is still only partially mapped.
- Layout/order utilities mapped: `marker/pdf/utils.py::sort_block_group` and `marker/layout/order.py::sort_blocks_in_reading_order`, including order-position assignment by maximum bbox overlap, vertical-bucket/horizontal row sorting, fallback ordering for unpositioned blocks, and header/footer pinning.
- OCR heuristic utilities mapped: all four focused functions from `marker/ocr/heuristics.py` (`should_ocr_page`, `detect_bad_ocr`, `no_text_found`, and `detected_line_coverage`) plus `marker/ocr/utils.py::alphanum_ratio`.
- Image insertion helpers mapped from `marker/images/save.py`, `marker/images/extract.py`, `marker/schema/block.py`, and `marker/schema/bbox.py`: deterministic image filenames, page image dictionary export, nearest-block fallback insertion, Figure/Picture layout-region matching, intersecting text-span clearing, and Markdown image-span insertion. Native PHP does not raster-render PDF crops yet; upstream uses `pypdfium2` and PIL for that part.
- Published benchmark documents in the README accuracy table: 6 (`multicolcnn.pdf`, `switch_trans.pdf`, `thinkpython.pdf`, `thinkos.pdf`, `thinkdsp.pdf`, `crowd.pdf`).
- CI score assertions: 3 thresholds in `scripts/verify_benchmark_scores.py` (`multicolcnn.pdf > 0.34`, `switch_trans.pdf > 0.40`, and average table report score `>= 0.7`).
- Committed markdown examples: 4 marker outputs and 4 nougat outputs under `data/examples`.

The lane manifest counts 35 inspected benchmark/test/cleanup/layout-order/OCR/image artifacts for dashboard purposes: 1 CI workflow, 1 benchmark runner, 2 benchmark scoring modules, 1 verifier, 6 benchmark documents, 8 committed reference-like markdown outputs, 7 cleaner modules, 1 Markdown postprocessor, 1 layout ordering module, 1 PDF utility module, 2 OCR heuristic/helper modules, and 4 image insertion/schema helper artifacts. This is a static inventory, not upstream pass parity.

## Mapped Surrogate Pairs

- `fixtures/upstream-multicolcnn-surrogate.php` records a small excerpt pair from the README-linked committed outputs for `multicolcnn.pdf`: `data/examples/marker/multicolcnn.md` as Marker output and `data/examples/nougat/multicolcnn.md` as a reference-like surrogate.
- `fixtures/upstream-switch-transformers-surrogate.php` records a small excerpt pair from the README-linked committed outputs for `switch_trans.pdf`: `data/examples/marker/switch_transformers.md` as Marker output and `data/examples/nougat/switch_transformers.md` as a reference-like surrogate.
- `fixtures/upstream-thinkpython-surrogate.php` records a small excerpt pair from the README-linked committed outputs for `thinkpython.pdf`: `data/examples/marker/thinkpython.md` as Marker output and `data/examples/nougat/thinkpython.md` as a reference-like surrogate.
- `fixtures/upstream-thinkos-surrogate.php` records a focused Preface excerpt pair from the README-linked committed outputs for `thinkos.pdf`: `data/examples/marker/thinkos.md` as Marker output and `data/examples/nougat/thinkos.md` as a reference-like surrogate.
- `examples/upstream-surrogate-score.php` scores all four pairs with the native `BenchmarkScorer`. Current scores are about `0.978` for `multicolcnn.pdf` against a `0.80` threshold, `0.954` for `switch_trans.pdf` against a `0.75` surrogate threshold while also clearing the upstream CI `switch_trans.pdf > 0.40` boundary, `0.844` for `thinkpython.pdf` against a `0.78` surrogate threshold, and `0.986` for the focused `thinkos.pdf` Preface excerpt against a `0.95` surrogate threshold.
- These are intentionally labeled surrogates. They are not the external benchmark PDF/reference archive used by upstream CI, but they map four committed upstream document-output pairs through the native scoring path.

## Mapped Native Semantics

- PDF content stream text operators feed Marker-style Markdown conversion.
- PDF text movement operators define block-ready line boundaries before WordPress import.
- `marker/postprocessors/markdown.py` hyphenated line dewrapping is mapped by `MarkdownPostProcessor::mergeLines`.
- `marker/postprocessors/markdown.py` heading/list/text wrapping and hash escaping are mapped by `MarkdownPostProcessor::surroundBlock`.
- `marker/postprocessors/markdown.py` block merging, block separators, and full-text assembly are mapped by `MarkdownPostProcessor::mergeBlocks`, `MarkdownPostProcessor::blockSeparator`, and `MarkdownPostProcessor::getFullText`.
- `marker/postprocessors/markdown.py` continuation geometry is mapped by `MarkdownPostProcessor::mergeBlocks` for equal-height, same-x lines whose vertical gap is below the upstream `max_block_gap`.
- `marker/pdf/utils.py` vertical-bucket and horizontal-row sorting is mapped by `LayoutOrderer::sortBlockGroup`.
- `marker/layout/order.py` reading-order assignment is mapped by `LayoutOrderer::sortBlocksInReadingOrder`, including maximum bbox-overlap position assignment, fallback positions for unordered blocks, `sort_block_group` tie sorting, and Page-header/Page-footer/Footnote pinning.
- `marker/ocr/utils.py` alphanumeric quality scoring is mapped by `OcrHeuristics::alphanumRatio`, including the upstream behavior that removes spaces and newlines before counting alphanumeric characters.
- `marker/ocr/heuristics.py` bad-OCR detection is mapped by `OcrHeuristics::detectBadOcr`, including empty text, whitespace/newline runs, garbled low-alphanumeric text, and repeated replacement-character checks.
- `marker/ocr/heuristics.py` whole-document no-text detection is mapped by `OcrHeuristics::noTextFound`.
- `marker/ocr/heuristics.py` detected-line coverage is mapped by `OcrHeuristics::detectedLineCoverage`, including Surya image-bbox to page-bbox rescaling and extracted line intersection coverage.
- `marker/ocr/heuristics.py` OCR fallback triage is mapped by `OcrHeuristics::shouldOcrPage`, including no-text, bad-OCR, line-coverage, force-OCR, and zero-detected-line behavior.
- `marker/images/save.py` deterministic image filename and image dictionary helpers are mapped by `ImageExtractor::getImageFilename` and `ImageExtractor::imagesToDict`.
- `marker/schema/block.py` nearest-block fallback insertion is mapped by `ImageExtractor::findInsertBlock`.
- `marker/images/extract.py` Figure/Picture region discovery is mapped by `ImageExtractor::findImageBlocks`, including layout image-bbox to page-bbox rescaling.
- `marker/images/extract.py` image placeholder insertion is mapped by `ImageExtractor::insertImagePlaceholders`, including intersecting text-span clearing, existing-line insertion, newly-created image lines, and upstream Markdown image syntax. Raster image rendering remains unported because upstream uses `pypdfium2` and PIL.
- `marker/benchmark/scoring.py` chunking, local-window overlap, and text score aggregation are mapped by `BenchmarkScorer`.
- `marker/benchmark/table.py` pipe-cell splitting, best-row fuzzy alignment, and aggregate table scoring are mapped by `TableScorer`.
- `scripts/verify_benchmark_scores.py` table-score threshold is covered by `examples/wordpress-table-score.php`, which scores an OCR-noisy WordPress table import above `0.7`.
- `marker/cleaners/bullets.py` bullet glyph normalization is mapped by `TextCleaner::replaceBullets`.
- `marker/cleaners/text.py` excessive whitespace and non-breaking-space cleanup is mapped by `TextCleaner::cleanupText`.
- `marker/cleaners/fontstyle.py` font-name and font-weight emphasis detection is mapped by `FontStyleCleaner::markBoldItalicSpans`.
- `marker/postprocessors/markdown.py` `surround_text` and the styled-span branch of `merge_spans` are mapped by `FontStyleCleaner::mergeStyledLine`, including the upstream first/last-span guard and Markdown `*`/`**` markers.
- `marker/cleaners/headers.py` repeated edge-line detection is mapped by `HeaderFooterCleaner::findCommonEdgeLines` and `HeaderFooterCleaner::removeCommonEdgeLines`, including the upstream minimum of three pages before common elements are filtered.
- `marker/cleaners/headers.py` repeated title filtering is mapped by `HeaderFooterCleaner::replaceLeadingTrailingDigits`, `HeaderFooterCleaner::findOverlapElements`, and `HeaderFooterCleaner::filterCommonTitles`.
- `marker/cleaners/code.py` line-length and comment-prefix detection are mapped by `CodeBlockDetector::isCodeLineLength` and `CodeBlockDetector::commentCount`.
- `marker/cleaners/code.py` code block classification is mapped by `CodeBlockDetector::isCodeBlock` and `CodeBlockDetector::identifyCodeBlocks`, including the upstream `> 3` line minimum, indentation/comment majority threshold, and optional small-font checks.
- `marker/cleaners/code.py` indentation reconstruction is mapped by `CodeBlockDetector::indentBlock`, including line-geometry-derived prefixes and repeated blank-line suppression.
- `marker/cleaners/headings.py` heading line splitting is mapped by `HeadingCleaner::splitHeadingBlocks`, including the upstream `Title`/`Section-header` labels and the `0.7` bounding-box intersection threshold.
- `marker/cleaners/headings.py` line-height bucketing and heading-level inference are mapped by `HeadingCleaner::bucketHeadings` and `HeadingCleaner::inferHeadingLevels`, including the upstream four-level/default-level behavior.
- `marker/cleaners/toc.py` computed table-of-contents metadata is mapped by `HeadingCleaner::computeToc`, emitting title, level, and page for `Title` and `Section-header` blocks.
- README-linked committed Marker/Nougat `multicolcnn`, `switch_transformers`, `thinkpython`, and `thinkos` markdown outputs are mapped as upstream-derived surrogate benchmark pairs.
- `examples/wordpress-paginated-import.php` uses the full block-merge path to preserve upstream-style page-start markers as reviewable Gutenberg separators while emitting headings, paragraphs, and list blocks.
- `examples/wordpress-ocr-triage.php` maps upstream OCR heuristics into a WordPress import preflight that sends garbled/scanned pages to OCR before block rendering while leaving clean extracted text alone.
- `examples/wordpress-image-import.php` maps upstream image insertion metadata into a Gutenberg image-block import path, preserving Marker-style `![page_image](page_image.png)` filenames while avoiding a Python/pypdfium raster-render dependency.

## Runner Status

The full upstream runner was not executed. The workflow downloads `benchmark_data` from Google Drive and then installs Poetry dependencies including `torch`, `surya-ocr`, `pdftext`, `pypdfium2`, and `tabled-pdf`; the shallow clone contains no benchmark PDFs or references. Under the lane resource constraints, the defensible denominator for now is the cloned static inventory above, the natively ported benchmark scoring/cleaner/postprocessor/heading/TOC/reading-order/OCR/image-insertion functions, and the four committed README-linked surrogate pairs, not a local benchmark run.
