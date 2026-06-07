# markerpdf annotations links comment-reference boundary current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260607T153316Z`
Base: `ecdae3d672a8d414071d8e7c8995009a528f904e`

## Source Truth

PDF comments are lexical whitespace outside strings and streams. This slice keeps the native no-GPU markerPDF parser aligned with that boundary for page `/Annots` arrays and indirect Link `/A` action operands before WordPress span promotion.

Upstream model/OCR, pypdfium/PIL raster execution, Surya/Texify/Torch, JavaScript, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Behavior

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now parse indirect object references split by comments, for example `7 % comment\n0 R`, as canonical `7 0 R`.
- Missing token separators such as `12 0R` remain invalid and are not promoted as object references.
- Comment-only decoy references inside `/Annots` remain ignored.
- Link action dictionaries reached through comment-split `/A 20 % comment\n0 R` references remain non-executing review metadata and can promote the URI to overlapping supplied pdftext spans.
- Annotation review payload text and decoy action URIs stay out of visible WordPress paragraph text.

Red-first probe before the fix returned `annotations=[]`, `links=[]`, and `markups=[]` for the split-reference fixture.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsCommentReferenceBoundaryCurrentBaseTest.php`  
  Result: `1 test files, 36 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php`  
  Result: `8 test files, 638 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-page-annots-comment-reference-boundary-currentbase.php`  
  Result: exits `0`; emitted `annotation_objects=[7,9,10]`, `promoted_uri=https://example.com/comment-split-action`, `comment_decoy_promoted=false`, `tight_reference_decoy_promoted=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l` changed PHP files  
  Result: no syntax errors.
- `jq empty lanes/markerpdf/lane-status.json` and `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`  
  Result: valid JSON.
- `git diff --check -- lanes/markerpdf`  
  Result: clean.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 1 focused markerPDF behavior test.
- Adds 36 focused assertions.
- Adds 1 WordPress smoke/example.
- Updates lane-local status and manifest counters for the new page Annots comment-reference boundary behavior.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF dictionary/token parsing helpers and extends object-reference tokenization to respect existing comment skipping.

## Non-overlap

This does not repeat the prior xref Prev-chain omitted action-row slice, duplicate action-key/subtype boundary slices, escaped `/Annots` name handling, page Annots tokenization, object-stream annotation review, URI-base handling, generation-boundary handling, or markup geometry/UserUnit slices. It is limited to comment whitespace inside indirect `N G R` references for page annotation and Link action operands.
