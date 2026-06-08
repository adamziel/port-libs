# markerpdf cmap sparse array bfrange source-width current base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T202301Z`

Base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Behavior

Some searchable PDFs declare sparse CMap codespace windows, then provide a
`beginbfrange` target array whose length matches the dense declared source
range rather than the compact count of valid source codes. The native parser
now keeps that ToUnicode row when the dense target count is exact, indexes
targets by raw source offset, and still filters source keys through the valid
codespace windows. CID width fallback remains compact, so invalid gap entries
do not leak into visible text while valid source keys preserve word-spacing
geometry.

This is distinct from the prior delayed multi-code-space scalar range slice:
that slice handled scalar target incrementation; this one handles array target
offsets that contain unused gap targets.

## Evidence

Red-first focused run before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSparseArrayBfrangeSourceWidthCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`; expected `['ABCD']`, actual
empty line extraction because the dense target-array `bfrange` row was
discarded.

Focused green run after the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSparseArrayBfrangeSourceWidthCurrentBaseTest.php`

Result: `1 test files, 11 assertions, 0 failures`.

Adjacent CMap regression run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSparseArrayBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php`

Result: `5 test files, 441 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-sparse-array-bfrange-source-width-currentbase.php`

Result: exits `0` with `sparse_array_bfrange_resolved=true`,
`dense_gap_targets_excluded=true`,
`first_codespace_source_width_word_spacing_applied=true`,
`second_codespace_uses_dense_array_source_offset=true`,
`visible_text_clean=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The behavior reuses the existing native PHP
CMap parser, ToUnicode lazy range storage, CID codespace validation, and font
width extraction. OCR/model execution, Surya/Texify/Torch, and external PDF
tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

Avoids the accepted xref `/Prev` array helper slice and earlier CMap clusters
for zero padding, Identity/UCS2 defaults, partial metric misses, TJ
adjustments, vertical W2, odd hex padding, UseCMap order, explicit high/low CID
ranges, notdef rows, single-window delayed starts, scalar delayed
multi-code-space ranges, sparse ranges whose CMap range starts inside the first
valid window, invalid later CID ranges, bytewise membership, short target
arrays, and lazy large ToUnicode array ranges. This patch only accepts exact
dense target arrays whose extra entries correspond to invalid sparse-code-space
gaps.
