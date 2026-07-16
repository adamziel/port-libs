# page-associated-files-marked-content-alt-currentbase

Session: `port-dev-markerpdf-page61-20260602T213035Z`
Base accepted HEAD: `c3b759a859020b8775e124d837d858198d98558e`

## Source Truth

- Upstream markerPDF pinned source: `marker/pdf/extract_text.py` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `pdftext.extraction.dictionary_output(...)` to produce page dictionaries and also exposes a pypdfium bounded-text fallback through `naive_get_text`, so this native slice stays at the PDF page/content-stream boundary rather than executing Python/model workers: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream markerPDF README describes the pipeline as PDF text/OCR extraction, layout detection, cleanup, and Markdown postprocessing, with JSON/metadata output; this slice preserves that visible-text plus review-metadata split for WordPress import: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/README.md
- PDF Association Associated Files guidance describes `/AF` as FileSpec dictionaries that relate embedded content to specific PDF objects, with `AFRelationship` semantics: https://pdfa.org/resource/pdf-2-0-application-note-002-associated-files/
- PDF Association accessibility glossary describes `ActualText` replacement and `Alt` text as tag properties, and marked-content sequences as the page-content connection to document tags: https://pdfa.org/glossary-of-accessibility-terminology-in-pdf/

## Behavior

Native `PdfPagePropertyExtractor` now carries page dictionary `/AF` FileSpec review rows onto each page `structure_marked_content` MCID row as:

- `page_associated_file_count`
- `page_associated_files`
- `page_associated_file_review_only`

The existing text extractor behavior is preserved: marked-content BDC `/Alt` supplies the visible WordPress paragraph, original glyph noise is suppressed, and page-associated attachment payload bytes, filenames, and structure alt review text are not promoted into visible text.

## Evidence

Red-first focused test before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries page associated files onto marked-content Alt review rows without payload promotion
Values are not identical
Expected: 1
Actual: NULL
1 test files, 15 assertions, 1 failures
```

Focused regression after the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
5 test files, 934 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-associated-marked-content-alt-currentbase.php >/tmp/markerpdf-page-associated-alt-smoke.out
6 /tmp/markerpdf-page-associated-alt-smoke.out
```

## Dependency Closure

No new support component is required. This reuses the existing native PDF parser, page review extractor, embedded FileSpec metadata reader, and marked-content `/Alt` replacement path. It does not execute Python, pdftext, pypdfium, models, OCR, or external PDF tools.

## Non-Overlap

This does not repeat catalog `/AF`, structure-element `/AF`, article-thread associated-file propagation, marked-content `/ActualText`/`/Alt` text replacement, or rotated layout ordering. The new behavior is page-owned `/AF` provenance copied to page MCID review rows while preserving the existing visible-text boundary.
