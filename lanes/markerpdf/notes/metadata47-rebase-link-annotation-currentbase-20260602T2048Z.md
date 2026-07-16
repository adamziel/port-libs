# Metadata47 Rebase Link Annotation Current Base

Micro-slice: `metadata47-rebase-link-annotation-currentbase`
Session: `port-dev-markerpdf-conflict-meta47-rebase-20260602T2048Z`
Base accepted HEAD: `ea6b9c60b46ea0618978b2deaa95900cf2e78648`

## Source Truth

- Upstream Marker keeps rendered body content separate from metadata/images; its public output shape for Markdown conversion carries `markdown`, `metadata`, and `images`: https://github.com/datalab-to/marker
- The native PDF boundary for this rebase is local link annotation navigation review. `/Dest`, `/A`, `/AA`, `/Next`, page `/Dur`/`/Trans`, catalog `/PageLabels`, outlines, and XMP date fields are useful import metadata, but PDF actions and XMP packets must not become visible WordPress paragraphs.

## Behavior

- `PdfLinkAnnotationExtractor` now builds one review context per PDF from existing native extractors:
  - `PdfTextExtractor::extractPageLabels()` for destination page labels;
  - `PdfOutlineExtractor::getNavigationReviewMetadata()` for matching outline rows and target page presentation/action metadata;
  - `PdfMetadataExtractor::extractDocumentMetadata()` for raw and UTC-normalized XMP/Info dates.
- Local destination action rows receive `destination_page_label`, `target_display_duration`, `target_page_transition`, `target_page_actions`, `target_outline_titles`, `target_outline_levels`, and `document_metadata_dates`.
- `applyLinksToPages()` copies those review fields onto overlapping supplied pdftext spans as `link_*` metadata while preserving safe URI rendering behavior and leaving local destinations review-only.
- The current-base rebase preserves the already accepted StructParent/ParentTree context for promoted Link and Widget annotations.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`
  - Passed: `1 test files, 111 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php`
  - Passed: `5 test files, 1385 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-dates-outline-link-currentbase.php`
  - Passed and emitted `destination_page_label="Chapter 4"`, `target_outline_titles=["Chapter One Outline"]`, `target_transition_style="Wipe"`, UTC XMP date fields, and `visible_text_excludes_xmp_and_actions=true`.
- PHP lint passed for:
  - `lanes/markerpdf/src/PdfLinkAnnotationExtractor.php`
  - `lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`
  - `lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-dates-outline-link-currentbase.php`

## Status Delta

- Focused PHP behavior tests: `799 -> 800 pass / 0 fail`.
- WordPress scenarios: `799 -> 800`.
- No root harness run; this is an isolated markerPDF micro-slice.

## Non-Overlap

This does not repeat accepted standalone XMP/Info date normalization, document-level XMP extraction, outline structure destination page context, outline destination action transition context, named-destination outline resolution, page transition/action extraction, basic link annotation URI/local-destination extraction, Widget link promotion, or page ParentTree link-action annotation context. The new current-base behavior is the joined review metadata on local link annotations and supplied spans.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/action parser, XMP/Info metadata normalizer, outline/navigation/page-presentation extractor, PageLabels parser, supplied-span link application path, and Markdown span merger. Full upstream runner parity remains dependency-gated on live Python/pdftext/pypdfium2/Surya/tabled/Texify/model/app/server workflows; none were run for this bounded PHP slice.
