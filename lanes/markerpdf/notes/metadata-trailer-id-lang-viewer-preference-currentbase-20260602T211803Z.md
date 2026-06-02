# markerPDF metadata trailer ID language viewer preference currentbase

Session: `port-dev-markerpdf-meta57-20260602T211803Z`
Micro-slice: `metadata-trailer-id-lang-viewer-preference-currentbase`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction at the pdftext/PDFium boundary in `marker/pdf/extract_text.py`: `get_text_blocks()` delegates page extraction to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` reads page text through `pypdfium2`. The output boundary in `marker/output.py` writes rendered Markdown separately from `out_metadata`.

Source files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

PDF parser behavior for this slice: the document catalog is a dictionary, so `/Lang`, `/PageLayout`, `/PageMode`, and `/ViewerPreferences` are top-level catalog entries. Nested dictionaries such as `/PieceInfo`, name-tree value dictionaries, and review extension dictionaries must not shadow those catalog keys. The latest xref-stream trailer at `startxref` remains authoritative for `/Root` and `/ID`, while stale textual trailers and appended detached objects after EOF stay excluded from document metadata and visible WordPress text.

## Implementation

`PdfMetadataExtractor` now reads catalog language and presentation keys through the existing top-level dictionary parser. `ViewerPreferences` selection also uses the top-level catalog entry, and scalar viewer-preference operands resolve from top-level entries inside the viewer-preference dictionary. This prevents nested decoy `/Lang`, `/Direction`, or `/NumCopies` values from overriding the current catalog review metadata.

The focused regression fixture combines:

- an xref-stream trailer `/ID` selected from latest `startxref`;
- a current catalog with nested stale `/Lang` and nested stale `/ViewerPreferences`;
- top-level current `/Lang`, `/PageLayout`, `/PageMode`, and indirect `/ViewerPreferences`;
- stale appended catalog/content objects after EOF.

The WordPress smoke emits only the current paragraph and review-safe metadata: current trailer fingerprint source, `en-US` language, `TwoPageRight`, `UseOutlines`, `R2L`, `PrintScaling None`, and bounded print-page/copy preferences.

## Verification

Initial probe before the source edit selected `stale-nested-lang` and nested viewer preferences (`Direction=L2R`, `NumCopies=99`) while correctly selecting the current xref-stream trailer ID. After the fix:

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataTrailerIdLangViewerPreferenceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-trailer-id-lang-viewer-preference-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataTrailerIdLangViewerPreferenceCurrentBaseTest.php
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataTrailerIdLangViewerPreferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*.php
php lanes/markerpdf/examples/wordpress-pdf-metadata-trailer-id-lang-viewer-preference-currentbase.php
```

Results:

- focused new test: 1 file, 20 assertions, 0 failures;
- metadata family gate: 2 files, 858 assertions, 0 failures;
- broader metadata glob: 9 files, 1120 assertions, 0 failures;
- smoke emitted `current_id_selected=true`, `nested_catalog_decoy_excluded=true`, `language=en-US`, and the paragraph `Current ID Lang Viewer Body`;
- root harness not run: isolated micro-slice.

## Non-Overlap

This does not repeat the accepted standalone trailer `/ID` fingerprint slice, ordinary catalog language/viewer-preference extraction, xref-stream trailer `/Info`/`/ID` precedence, current xref `/Encrypt` metadata, page-label viewer-preference composition, or catalog structure-tree language review. The new behavior is specifically top-level catalog key selection when current catalog dictionaries contain nested decoy metadata, composed with current xref-stream trailer `/ID` selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct object scanner, xref-stream trailer parser, catalog resolver, top-level dictionary parser, viewer-preference operand resolver, PDF string/name decoder, and WordPress smoke path. Full upstream markerPDF parity remains gated on Python runtime dependencies including `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI paths, benchmark tooling, and OCR/rendering helpers.
