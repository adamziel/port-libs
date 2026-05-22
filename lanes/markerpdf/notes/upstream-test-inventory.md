# markerPDF Upstream Test Inventory

Inventory source: shallow clone of `https://github.com/sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` in `.upstream-cache/markerpdf`.

## Counted Denominator

- Repository files inspected with `git ls-tree`: 78.
- Python files: 47, including 39 files under `marker/`.
- Committed Python unit tests: 0 found.
- CI benchmark workflow: 1 integration workflow, `.github/workflows/tests.yml`.
- Benchmark/scoring scripts inspected: `benchmarks/overall.py`, `marker/benchmark/scoring.py`, `marker/benchmark/table.py`, and `scripts/verify_benchmark_scores.py`.
- Benchmark scoring functions inspected: 6 (`chunk_text`, `overlap_score`, `score_text`, `split_to_cells`, `align_rows`, and `score_table`).
- Cleaner/postprocessor source inspected: 7 cleaner modules with 16 functions under `marker/cleaners/`, plus 8 functions in `marker/postprocessors/markdown.py`.
- Cleaner functions mapped: `marker/cleaners/bullets.py::replace_bullets` and `marker/cleaners/text.py::cleanup_text`.
- Published benchmark documents in the README accuracy table: 6 (`multicolcnn.pdf`, `switch_trans.pdf`, `thinkpython.pdf`, `thinkos.pdf`, `thinkdsp.pdf`, `crowd.pdf`).
- CI score assertions: 3 thresholds in `scripts/verify_benchmark_scores.py` (`multicolcnn.pdf > 0.34`, `switch_trans.pdf > 0.40`, and average table report score `>= 0.7`).
- Committed markdown examples: 4 marker outputs and 4 nougat outputs under `data/examples`.

The lane manifest counts 27 inspected benchmark/test/cleanup artifacts for dashboard purposes: 1 CI workflow, 1 benchmark runner, 2 benchmark scoring modules, 1 verifier, 6 benchmark documents, 8 committed reference-like markdown outputs, 7 cleaner modules, and 1 Markdown postprocessor. This is a static inventory, not upstream pass parity.

## Mapped Surrogate Pair

- `fixtures/upstream-multicolcnn-surrogate.php` records a small excerpt pair from the README-linked committed outputs for `multicolcnn.pdf`: `data/examples/marker/multicolcnn.md` as Marker output and `data/examples/nougat/multicolcnn.md` as a reference-like surrogate.
- `examples/upstream-surrogate-score.php` scores that pair with the native `BenchmarkScorer` and currently reports about `0.978` against a `0.80` threshold.
- This is intentionally labeled a surrogate. It is not the external benchmark PDF/reference archive used by upstream CI, but it does map one committed upstream document-output pair through the native scoring path.

## Mapped Native Semantics

- PDF content stream text operators feed Marker-style Markdown conversion.
- PDF text movement operators define block-ready line boundaries before WordPress import.
- `marker/postprocessors/markdown.py` hyphenated line dewrapping is mapped by `MarkdownPostProcessor::mergeLines`.
- `marker/postprocessors/markdown.py` heading/list/text wrapping and hash escaping are mapped by `MarkdownPostProcessor::surroundBlock`.
- `marker/benchmark/scoring.py` chunking, local-window overlap, and text score aggregation are mapped by `BenchmarkScorer`.
- `marker/benchmark/table.py` pipe-cell splitting, best-row fuzzy alignment, and aggregate table scoring are mapped by `TableScorer`.
- `scripts/verify_benchmark_scores.py` table-score threshold is covered by `examples/wordpress-table-score.php`, which scores an OCR-noisy WordPress table import above `0.7`.
- `marker/cleaners/bullets.py` bullet glyph normalization is mapped by `TextCleaner::replaceBullets`.
- `marker/cleaners/text.py` excessive whitespace and non-breaking-space cleanup is mapped by `TextCleaner::cleanupText`.
- README-linked committed Marker/Nougat `multicolcnn` markdown outputs are mapped as an upstream-derived surrogate benchmark pair.

## Runner Status

The full upstream runner was not executed. The workflow downloads `benchmark_data` from Google Drive and then installs Poetry dependencies including `torch`, `surya-ocr`, `pdftext`, `pypdfium2`, and `tabled-pdf`; the shallow clone contains no benchmark PDFs or references. Under the lane resource constraints, the defensible denominator for now is the cloned static inventory above, the natively ported benchmark scoring functions, and the committed README-linked surrogate pair, not a local benchmark run.
