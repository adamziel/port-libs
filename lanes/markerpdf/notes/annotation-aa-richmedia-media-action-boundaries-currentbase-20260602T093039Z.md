# markerPDF Annotation AA RichMedia Media Action Boundaries

Slice: `annotation-aa-richmedia-media-action-boundaries-currentbase-20260602T093039Z`

Base accepted HEAD: `7b337a762202746fcb71c0f5fb43813e7360eb2c`

## Source Truth

- The pinned markerPDF manifest records the upstream conversion boundary as native document blocks/metadata plus review data, not PDF viewer action execution.
- PDF page dictionaries expose `/Annots` as the page's annotation array; nested `/A`, `/AA`, `/Next`, and RichMediaExecute `/AN` references are action operands and targets, not extra page annotations unless they also appear as top-level page annotation entries.
- The native WordPress import policy remains review-only for RichMedia, Rendition, Launch, JavaScript, URI, Movie, and Sound actions. It must not execute media, JavaScript, launch actions, Python/model code, or external PDF tools, and stale detached media payload names must not leak into visible text or current-page review rows.

## Implementation

- `PdfRichMediaAnnotationExtractor` now parses page `/Annots` arrays one top-level PDF value at a time instead of collecting every indirect object reference inside nested action dictionaries.
- Inline rich-media annotations still emit `/A`, `/AA`, and `/Next` action review rows, including RichMediaExecute `/AN` target object metadata.
- Aggregate `asset_names` and `file_names` now come from the current annotation's own media dictionaries plus the walked action chain, excluding stale detached annotation assets referenced only as action targets.
- Added `wordpress-pdf-richmedia-current-annot-action-boundary.php` as the WordPress smoke for the current-page annotation boundary.

## Evidence

- Red-first focused run:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php`
  failed with `1 test files, 141 assertions, 1 failures`; the stale nested `/AA` RichMediaExecute `/AN` target was promoted as a second page annotation.
- After the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php`
  passed with `1 test files, 162 assertions, 0 failures`.
- PHP lint passed:
  `php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php`
  `php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php`
  `php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-current-annot-action-boundary.php`
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-richmedia-current-annot-action-boundary.php`
  emitted `review_annotation_count=1`, `review_action_count=5`, `target_annotation_object=50`, `stale_target_not_promoted=true`, `stale_media_files_excluded=true`, `popup_and_stale_text_excluded=true`, and all execution flags false.
- JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests: `453 -> 454`.
- Mapped markerPDF semantics: `305 -> 306 / 78`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, annotation traversal, action walker, string/name decoding, and existing visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

## Non-Overlap

This does not repeat accepted rich media/screen/sound/movie annotation metadata, popup/action review rows, catalog OpenAction `/Next` review, page `/AA` metadata, annotation appearance extraction, link annotation URI promotion, or visible text/media payload exclusion. The new behavior is specifically the current page `/Annots` top-level array boundary for inline RichMedia annotations with nested `/A` and `/AA` action targets.
