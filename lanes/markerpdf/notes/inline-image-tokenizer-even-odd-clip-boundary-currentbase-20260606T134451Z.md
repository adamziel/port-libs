# markerPDF Inline Image Tokenizer Even-Odd Clip Boundary Current Base

## Source Truth

Upstream markerPDF routes searchable PDF text extraction through parser-backed text extraction before image/OCR/model fallback. In the no-GPU native PHP lane, inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload, while valid content-stream operators after the real inline-image terminator remain available for WordPress paragraph import.

PDF clipping paths can use either the nonzero winding operator `W` or the even-odd operator `W*`. This slice records the current-base boundary where a preview-only inline image fallback is followed by `re W* n`, visible text, and then a later stray `EI` operator. The tokenizer must preserve the visible text after the real image boundary while keeping JBIG2 bytes, clipping operators, and payload-looking text out of Gutenberg paragraphs.

## Change

- Added a focused current-base fixture and PASS case to `PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` for `re W* n` after a preview-only inline image fallback.
- Extended `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` with the same WordPress smoke flag:
  `preview_only_even_odd_clip_path_stray_ei_text_preserved_after_safe_boundary=true`.
- Updated markerPDF manifest/status evidence for one new focused behavior case and one WordPress smoke flag.

No production source component changed in this slice; the current tokenizer already had the standard `W*` path-state primitive. The patch makes that upstream PDF operator boundary countable at the current accepted base.

## Verification

`php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

`No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

`No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

`php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`

`lanes/markerpdf/lane-status.json OK`

`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK`

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

`1 test files, 507 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | rg "preview_only_even_odd_clip|executes_python_or_models|executes_external_pdf_tools|Visible Even Odd Clip|Even Odd Clip Payload|W\\* n"`

Targeted smoke output reports `executes_python_or_models=false`, `executes_external_pdf_tools=false`, `preview_only_even_odd_clip_path_stray_ei_text_preserved_after_safe_boundary=true`, and emits `<p>Visible Even Odd Clip Before Stray</p>` while excluding `Even Odd Clip Payload Noise` and `W* n`.

`git diff --check -- lanes/markerpdf`

No whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed `BI` recovery, tight `ID`/`EI`, comments after `ID`, NUL whitespace, slash-delimited `EI`, DCT/JPX/JBIG2/CCITT preview framing, unsupported filters, named ColorSpace fallback, visible literal/TJ/ActualText recovery, post-terminator comments, later stray `EI` text, same-line text, graphics-state wrappers, nonzero `W n` clipping, XObject `Do`, marked-content point operators, color/pattern/shading/dash/text-state operators, compatibility sections, external `Q`/`EMC`/`EX` closures, Type3 metrics, stream filters, image review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, preview-only inline image fallback scanner, existing `W*` path-state operator handling, text extraction, and WordPress smoke harness. Full raster/OCR/model parity remains intentionally out of scope under the current markerPDF no-GPU directive.
