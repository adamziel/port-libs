# CMap High CID Range Source Width Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T090715Z`

Base: `ff5511ebaa7007fb5360709d25981536ab21fcaf`

## Behavior

The native searchable-PDF fallback now expands valid CMap `begincidrange` rows beyond the previous 512-entry guard when deriving source-code-to-CID mappings for Type0 font width lookup. This keeps higher source codes, such as `<0300>`, mapped through the font Encoding CMap before descendant CIDFont `/W` widths are applied.

The guard remains bounded at 4096 range entries to keep malformed or huge CMap streams modest in the no-GPU PHP lane.

## Evidence

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files / 216 assertions / 0 failures`.

The new case adds one PASS case and 10 focused assertions. It proves text `<0300>` through `<0307>` decodes as `ABCD EFGH`, while styled span bboxes use remapped descendant CIDs `1768..1775` from a high `begincidrange` instead of falling back to raw source CID `768` and `/DW`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-high-cidrange-source-width-currentbase.php`

Result: emits `high_cid_range_widths_applied=true`, `text_runs_preserved=true`, `high_range_default_width_excluded=true`, `nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss ToUnicode fallback, partial metric-miss repair, horizontal/vertical `TJ` gaps, odd hex padding, one-byte codespace padding, repeated zero padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined `usecmap`, explicit low CID rows, zero-padded remapped low CID ranges, broad ToUnicode codespace recovery, or Encoding CMap notdef ranges.

The bounded behavior is specifically high-entry `begincidrange` expansion before CIDFont source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping, and WordPress smoke path. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
