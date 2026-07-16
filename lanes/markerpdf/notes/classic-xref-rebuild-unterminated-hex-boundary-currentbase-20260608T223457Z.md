# Classic Xref Rebuild Unterminated-Hex Boundary Current Base

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T223457Z`

Base: `a93e698ac06f7885c2a47509237e09731628d097`

## Source Truth

Upstream markerPDF relies on native PDF parser behavior for searchable-PDF text, metadata, and attachment import before any model/OCR work. For the current no-GPU lane scope, the port must keep classic xref repair bounded to selectable top-level PDF syntax and must not let malformed import-tail bytes after the current `%%EOF` replace the active trailer root.

## Behavior Added

Damaged PDFs can contain a top-level malformed hex opener after the current `%%EOF`, followed by raw decoy `xref`, `trailer`, `startxref`, and `%%EOF` tokens. The classic rebuild path now rejects self-pointing classic xref candidates that begin inside that malformed hex import tail, so the current EOF-bounded trailer continues to select page text, XMP/Info metadata, embedded-file attachments, and free-object rows.

The guard is intentionally narrow. It preserves the existing accepted recovery case where a malformed non-hex opener appears immediately before the current classic xref table but the following startxref operand does not point back to that candidate.

## Evidence

Red-first focused run before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnterminatedHexBoundaryCurrentBaseTest.php`

Failed by selecting decoy page text from the dangling hex tail:

`Actual: ['Unterminated hex xref decoy page', 'Hex tail root leak']`

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnterminatedHexBoundaryCurrentBaseTest.php`

Result: `1 test files, 32 assertions, 0 failures`

Regression check for accepted classic rebuild boundaries:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 663 assertions, 0 failures`

Classic xref family:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfXrefClassic*Test.php' | sort)`

Result: `36 test files, 1727 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-unterminated-hex-currentbase.php`

Result: exits `0` with `uses_current_classic_trailer_root=true`, `keeps_current_attachment=true`, `keeps_current_free_row=true`, `excludes_decoy_xref_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat prior classic xref EOF garbage, missing-startxref, malformed startxref operand, comment/name-token/dictionary/literal/stream-owned, free-map, or private-tail boundary slices. The new owned boundary is specifically a self-pointing classic xref table after `%%EOF` whose `xref` token begins inside a malformed top-level unterminated hex import tail.

## Dependency Closure

No new dependency or support component is needed. The patch reuses native PHP xref rebuild, text, metadata, attachment, embedded-file, and free-object-map scanners. No Python, CUDA, OCR, model execution, raster rendering, JavaScript/action execution, external PDF tools, or live services were run.
