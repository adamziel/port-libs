# Metadata XMP Lang MarkInfo Catalog Review Current Base

- Session: `port-dev-markerpdf-meta60-20260602T212815Z`
- Micro-slice: `metadata-xmp-lang-markinfo-catalog-review-currentbase`
- Base accepted HEAD: `c3b759a859020b8775e124d837d858198d98558e`

## Source Truth

Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible PDF extraction separate from conversion metadata: `marker/pdf/extract_text.py::get_text_blocks` delegates text-block extraction to `pdftext.extraction.dictionary_output`, while `convert.py::convert_single_pdf` and `output.py::save_markdown` carry conversion metadata separately from Markdown body text.

PDF-side source truth for this bounded slice is catalog `/MarkInfo`: the standard dictionary exposes tagged-PDF review booleans `/Marked`, `/UserProperties`, and `/Suspects`. Those flags are document accessibility/review metadata. They must not become visible WordPress paragraph text and must not override current xref-selected XMP title, trailer Info fallback authors, catalog `/Lang`, or viewer-preference metadata.

## Red Baseline

Before the source change, the focused current-base test failed because document metadata did not expose catalog `/MarkInfo`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpLangMarkInfoCatalogCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reviews current xref XMP language and catalog MarkInfo without visible text leakage
Expected: array (... mark_info ...)
Actual: NULL
1 test files, 8 assertions, 1 failures
```

## Implementation

- Added document-level catalog `/MarkInfo` extraction to `PdfMetadataExtractor`.
- Resolved direct or indirect `/MarkInfo` dictionaries from the current xref-selected catalog.
- Preserved only `/Marked`, `/UserProperties`, and `/Suspects` booleans as `mark_info` review metadata with `visible_text_source: false`.
- Lifted catalog `mark_info` into merged document metadata beside `language`, `viewer_preferences`, and other catalog review fields.
- Added a WordPress smoke that emits visible paragraph text plus review-only MarkInfo comments while excluding stale appended catalog/Info/XMP objects and XMP packet text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpLangMarkInfoCatalogCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews current xref XMP language and catalog MarkInfo without visible text leakage
1 test files, 17 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1100 assertions, 0 failures
```

Final verification also ran PHP lint for changed PHP files, JSON validation for lane status and manifest, the WordPress example smoke, and `git diff --check -- lanes/markerpdf`.

## Status Delta

- Focused behavior tests: `849 -> 850` pass / `0` fail.
- WordPress scenarios: `849 -> 850`.
- Mapped semantics: `596 -> 597 / 78`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, latest `startxref` xref-stream resolver, dictionary/string/name parser, stream decoder, XMP parser, viewer-preference extractor, and the existing WordPress smoke path. No Python, pdftext, pypdfium, Surya/OCR, PIL, or external PDF tools execute.

## Non-Overlap

This slice is document-level catalog `/MarkInfo` metadata merged with current XMP, Info, `/Lang`, and viewer preferences. It does not repeat the accepted page-level MarkInfo/page `/AF` associated-file slice, catalog associated-file metadata, OutputIntent/language metadata, StructureTree metadata, page property extraction, annotation/action review, or name-tree metadata review.

