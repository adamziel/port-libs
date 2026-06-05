## markerpdf-cmap-source-width-fallback-current-base-20260605T204602Z

Base accepted HEAD: `a83fc0e4b1554217d0e47f4ce05d9ee21cfcc9ca`

Scope: native no-GPU markerPDF searchable-PDF text extraction. This slice stays
inside Type0 Encoding CMap parsing and descendant CIDFont width selection; it
does not run OCR, Surya, Texify, Torch, Python model workers, external PDF
tools, or raster/model benchmark parity.

Behavior implemented:

- `PdfTextExtractor` now ranks disjoint same-width multi-range CMap code spaces
  for `begincidrange` source offsets before falling back to the old bounded
  sequential scan.
- This preserves sparse valid source codes such as `<100000>` when the CMap
  declares separate valid ranges like `<000000> <000003>` and
  `<100000> <100000>`.
- The far source now maps to the intended remapped descendant CID, so CIDFont
  `/W` width `250` is used instead of falling through to `/DW 500`.
- Malformed, overlapping, mixed-width, or unsupported code-space shapes still
  return to the existing fallback path.

Source-truth evidence:

- Upstream markerPDF relies on native PDF text import boundaries for searchable
  PDF text; this patch ports the PDF CMap dependency behavior needed before
  WordPress paragraph/span geometry conversion.
- The source-truth PDF behavior is that CMap code-space ranges define the valid
  source-code sequence domain for `begincidrange`, not every numeric value
  between sparse high and low source codes.

Red-first evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php`
- Result before source edit: `1 test files, 7 assertions, 1 failures`.
- Failure: the far `<100000>` source used `/DW 500`, producing bbox
  `[48.0, 0.0, 54.0, 12.0]` instead of the CID `/W` bbox
  `[48.0, 0.0, 51.0, 12.0]`.

Verification after source edit:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php`
  passed: `1 test files, 10 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMap*SourceWidth*CurrentBaseTest.php lanes/markerpdf/tests/PdfFont*CMap*Width*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontCid*CMap*Width*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidth*CMap*CurrentBaseTest.php`
  passed: `14 test files, 451 assertions, 0 failures`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  passed with no syntax errors.
- `php -l lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php`
  passed with no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-multirange-sparse-source-width-currentbase.php`
  passed with no syntax errors.
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-multirange-sparse-source-width-currentbase.php`
  emitted a WordPress paragraph for `ABCDE` plus smoke flags showing
  `multi_range_cid_widths_applied=true`,
  `default_width_excluded_for_far_source=true`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.

Non-overlap:

- Avoids annotation/link, xref repair, metadata, stream-filter, object-stream,
  page-box, image-filter, AcroForm, outline, and supplied table/equation
  boundaries already covered by recent markerPDF handoffs.
- This slice owns only Type0 Encoding CMap multi-range source-code ranking
  before descendant CIDFont `/W` source-width selection.

Dependency closure:

- No new support component is needed.
- Existing native PHP CMap parsing, CIDFont width lookup, styled span geometry,
  and WordPress paragraph smoke paths are reused.

Root harness: not run - isolated micro-slice.
