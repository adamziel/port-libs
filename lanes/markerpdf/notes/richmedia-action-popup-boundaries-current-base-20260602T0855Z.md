# markerPDF RichMedia Action Popup Boundaries

Slice: `annotation-popup-richmedia-action-review-boundaries-20260602T083417Z`

Base accepted HEAD: `17e6bf5421f0c3a71bc3f00203712efda3852a5e`

## Source Truth

- Upstream marker/markerPDF keeps PDF conversion as extracted document blocks/metadata rather than executing PDF viewer actions; its current docs describe converter output as markdown/json/html/chunks with metadata, images, and page/block trees, while the heavy Python/model/PDF renderer stack remains dependency-gated for this PHP lane: https://github.com/datalab-to/marker
- PDF annotation/action source truth maps the boundary keys used here: pop-up annotations carry `/Parent` and `/Open`, movie/screen annotations carry `/A` and `/AA`, common action dictionaries carry `/S` and `/Next`, and annotation additional-actions carry events such as `/PV` and `/PI`: https://pdf-raku.github.io/PDF-ISO_32000-raku/
- The native import policy remains non-executing: WordPress receives review rows for interactive media, JavaScript, Launch, URI, and RichMediaExecute actions while visible paragraph extraction excludes popup/script payload text.

## Implementation

- `PdfRichMediaAnnotationExtractor` now preserves structured action review rows for rich-media/screen/movie/sound annotation `/A`, `/AA`, and chained `/Next` dictionaries.
- Action rows include source/event labels, chain indexes, cycle/depth safety counters, safety labels, URI safety, Launch file/operation, JavaScript preview/hash metadata, and RichMediaExecute command/target metadata.
- Rich-media annotations now carry direct or reverse-linked Popup review metadata without emitting the Popup annotation as a separate media annotation.
- Existing flat `action_types` and `action_uris` remain for current callers.

## Evidence

- First focused run with the new fixture exposed that chained Launch files are part of the annotation review inventory:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php`
  failed with `1 test files, 85 assertions, 1 failures`.
- After updating the expectation, focused verification passed:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php`
  `1 test files, 137 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-richmedia-action-popup-review.php`
  emitted `review_annotation_count=1`, `review_action_count=7`, `popup_review_present=true`, `cycle_edges_blocked=1`, `unsafe_uri_blocked=true`, and `popup_and_script_text_excluded=true`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, annotation traversal, dictionary/array tokenizer, string/name decoding, and existing visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.

## Non-Overlap

This does not repeat standalone rich media/screen/sound/movie metadata, standalone annotation border/popup rows, standalone document JavaScript action-chain inspection, page `/AA` review rows, or link-annotation URI promotion. The slice is limited to media annotation popup relationships plus structured non-executing action review metadata on the `PdfRichMediaAnnotationExtractor` boundary.
