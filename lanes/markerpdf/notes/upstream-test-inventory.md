# markerPDF Upstream Test Inventory

Inventory source: shallow clone of `https://github.com/sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` in `.upstream-cache/markerpdf`.

## Counted Denominator

- Committed Python unit tests: 0 found.
- CI benchmark workflow: 1 integration workflow, `.github/workflows/tests.yml`.
- Published benchmark documents in the README accuracy table: 6 (`multicolcnn.pdf`, `switch_trans.pdf`, `thinkpython.pdf`, `thinkos.pdf`, `thinkdsp.pdf`, `crowd.pdf`).
- CI score assertions: 2 marker thresholds in `scripts/verify_benchmark_scores.py` (`multicolcnn.pdf > 0.34`, `switch_trans.pdf > 0.40`).
- Committed markdown examples: 4 marker outputs and 4 nougat outputs under `data/examples`.

## Runner Status

The full upstream runner was not executed. The workflow downloads `benchmark_data` from Google Drive and then installs Poetry dependencies including `torch`, `surya-ocr`, `pdftext`, `pypdfium2`, and `tabled-pdf`; the shallow clone contains no benchmark PDFs or references. Under the lane resource constraints, the defensible denominator for now is the cloned static inventory above, not a local benchmark run.
