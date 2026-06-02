# markerPDF StructTreeRoot RoleMap Tagged Content

Slice: `markerpdf-structtree-rolemap-tagged-content-current-base-20260602T0750Z`

## Source Truth

- Upstream markerPDF delegates page text and layout extraction to pdftext/pdfium before WordPress-facing Markdown/block conversion; this native slice keeps that boundary local by extracting tagged PDF logical structure without Python, models, pdfium, or external PDF tools.
- PDF 1.7 logical structure uses `/StructTreeRoot` `/K` children to reference marked-content sequences by `/MCID`, and `/RoleMap` maps non-standard structure element names to standard roles such as `/H1`, `/P`, and `/Figure`.
- PDF marked-content sequences use `BDC`/`EMC` property dictionaries or page resource `/Properties`; `/ActualText` takes precedence over glyph text and `/Alt` supplies alternate text when visible glyphs are absent.

## Implementation

- `PdfTextExtractor::extractTaggedContent()` now returns review-only tagged rows with page index/object, MCID, raw structure role, RoleMap-resolved standard role, content tags, and extracted text.
- `PdfTextExtractor` now resolves direct/indirect `/RoleMap` dictionaries, follows chained role aliases with a cycle guard, and decodes escaped PDF names in custom roles.
- StructTree MCID reading-order replay now preserves the original `BDC`/`EMC` operands, so named `/Properties` `/ActualText` and inline `/Alt` replacements still apply after the content stream has been reordered by `/StructTreeRoot`.
- `wordpress-pdf-structtree-rolemap-tagged-content.php` emits a heading, paragraph, and figure review block from mapped `/H1`, `/P`, and `/Figure` roles while excluding an unlisted artifact MCID.

## Non-Overlap

This does not repeat the accepted StructTreeRoot MCID ordering slice, marked-content ActualText/Alt base replacement, optional-content visibility, page-box/UserUnit, linearized hint-table, xref, annotation, or JavaScript review slices. The new behavior is specifically `/RoleMap` standard-role metadata plus preserving tagged-content property replacements after structure-tree ordering.

## Verification

- Baseline focused test before edits: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed at `1 test files, 333 assertions, 0 failures`.
- Focused post-change test: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed at `1 test files, 344 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-structtree-rolemap-tagged-content.php` emitted mapped roles `H1`, `P`, and `Figure`, preserved `/ActualText`, used figure `/Alt`, and excluded artifact/footer glyph noise.
- Changed PHP lint passed for `PdfTextExtractor.php`, `PdfTextExtractorTest.php`, and `wordpress-pdf-structtree-rolemap-tagged-content.php`.
- Full markerPDF lane: `php tools/run-tests.php lanes/markerpdf/tests` passed at `56 test files, 2295 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object, dictionary, content-token, font-map, and marked-content property parsers already present in `PdfTextExtractor`. Full pdfium/pdftext/model parity remains dependency-gated.
