# markerPDF Page StructParents Resources Transition Label Review

Micro-slice: `page-structparents-resources-transition-label-review-currentbase-20260602T171947Z`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page extraction through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates page ranges to `pdftext.extraction.dictionary_output()` and converts each page dictionary into Marker `Page` objects: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` keeps page-local state (`pnum`, `rotation`, `char_blocks`, layout/OCR/order fields) as the downstream block boundary: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- PDFium's page StructTree loader resolves a page `/StructParents` value through `/StructTreeRoot /ParentTree` before adding page structure nodes, which matches markerPDF's PDFium/pdftext dependency boundary: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7421/core/fpdfdoc/cpdf_structtree.cpp

## Behavior

`PdfPagePropertyExtractor` now merges `PdfTextExtractor::extractTaggedContent()` rows into page review metadata when catalog structure metadata does not already expose the MCID rows. This covers page `/StructParents` ParentTree arrays whose StructElem leaves omit `/Pg`, while preserving:

- page labels from `/PageLabels`;
- page transition metadata from `/Dur` and `/Trans`;
- inherited `/Resources /Properties` named marked-content dictionaries used for MCID and ActualText replacement;
- review-only page rows that do not expose visible tagged text payloads.

`PdfTextExtractor` also now uses the inherited page resource stack when mapping named marked-content property dictionaries to MCIDs for StructTree reading order and tagged-content extraction.

## Evidence

Red-first while adding the focused fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
FAIL merges page StructParents ParentTree rows with inherited Resources transition and labels for review
Expected: 2
Actual: 1
1 test files, 177 assertions, 1 failures
```

The failure showed the direct `<< /MCID 0 >>` segment was reviewed, but the named `/BodyProp` segment from inherited page-tree `/Resources` was not bound to MCID `1`.

Focused passing gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
1 test files, 192 assertions, 0 failures
```

Adjacent page/text gate after touching shared text extraction:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
2 test files, 789 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparents-resources-transition-label-review-currentbase.php
```

The smoke emits `page_label=deck-3`, `transition_style=Dissolve`, ParentTree MCIDs `[0,1]`, roles `["H2","P"]`, tagged text `["Deck heading visible","Inherited resource body"]`, and `visible_text_excludes_review_metadata=true`, with execution flags false for Python/models, PDF actions, and external PDF tools.

Status delta:

- Behavior tests move `593 -> 594`.
- Mapped native PDF semantics move `428 -> 429 / 78`.

## Non-Overlap

This does not repeat accepted page `/StructParents` text reading order, standalone page label extraction, standalone page `/Dur`/`/Trans` metadata, page article-thread/PieceInfo/StructTree MCR review rows, page associated-file transition/action review, page resource inheritance for font ToUnicode/width lookup, or outline target page-review transition/thread enrichment. The bounded behavior is page-review composition for ParentTree-only tagged rows when the MCID-bearing marked-content property is resolved from inherited page-tree `/Resources`.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object parsing, page-tree ordering, inherited resource lookup, tagged-content extraction, PageLabels parsing, transition parsing, and WordPress smoke rendering. Full upstream parity remains gated on live pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI paths, and benchmark tooling.
