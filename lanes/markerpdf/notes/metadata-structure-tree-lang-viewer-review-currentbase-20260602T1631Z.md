# markerpdf metadata structure-tree language/viewer review current base

Slice: `metadata-structure-tree-lang-viewer-review-currentbase-20260602T1631Z`

## Source truth

- Upstream `sddai/markerPDF` pinned by the lane manifest (`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes structured PDF page extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which gathers `pdf_toc` and delegates page block dictionaries to `pdftext.extraction.dictionary_output(...)`; `marker/convert.py::convert_single_pdf()` then carries supplied `metadata["languages"]`, `pdf_toc`, and page counts into output metadata before OCR/layout/model stages.
- Relevant source URLs checked for this slice:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`
- PDF parser source truth: tagged PDF `/StructTreeRoot` and `/StructElem` dictionaries can carry role maps, namespace dictionaries, `/Lang`, `/Alt`, `/ActualText`, `/E`, titles, IDs, classes, revisions, page references, and MCID links. The native PHP lane should expose those as review metadata while visible WordPress paragraphs remain page-text output.

## Change

- `PdfMetadataExtractor` now adds a `structure_tree` catalog metadata block.
- The block resolves indirect `/StructTreeRoot`, `/RoleMap`, `/Namespaces`, and `/StructElem` dictionaries and emits:
  - root object/language and catalog-language fallback state;
  - role maps and namespace role maps;
  - structure element role, mapped role, page number, MCIDs, language inheritance, `/T`, `/ID`, `/Alt`, `/ActualText`, `/E`, `/C`, and `/R`;
  - `review_only=true` and `visible_text_source=false`.
- `PdfMetadataExtractorTest` adds a focused fixture proving structure element review strings do not enter `PdfTextExtractor` paragraph output.
- `examples/wordpress-pdf-structure-lang-viewer-review-currentbase.php` emits Gutenberg paragraph text plus separate `markerpdf:structure-element-review` comments for WordPress import review.

## Evidence

- Baseline before patch:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  - Result: `1 test files, 308 assertions, 0 failures`
- Focused after patch:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  - Result: `1 test files, 356 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-structure-lang-viewer-review-currentbase.php`
  - Result included `review_text_leaked_to_paragraphs=false`, `catalog_language=en-US`, roles `Sect,P`, and structure languages `fr-CA,en-US`.

## Non-overlap

This does not repeat accepted catalog `/Lang` and `/ViewerPreferences` extraction, StructTreeRoot MCID reading-order replay, StructTreeRoot RoleMap tagged-content text extraction, MarkInfo `/UserProperties`, page `/AF` review metadata, catalog OpenAction, PageLabels, outline transition/action navigation, or thread-bead reading-order fallback. The new behavior is limited to catalog structure-element review metadata in `PdfMetadataExtractor`.

## Dependency Closure

No new support component is required. The implementation reuses the existing native PDF dictionary/string/object parser helpers and does not execute Python, pdftext, pypdfium, Streamlit, PIL, OCR/model code, viewer actions, JavaScript, or external PDF tools.
