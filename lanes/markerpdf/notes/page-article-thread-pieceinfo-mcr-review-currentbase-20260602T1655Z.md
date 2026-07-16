# markerPDF Page Article Thread PieceInfo MCR Review

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` maps PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `pdftext_format_to_blocks()`, preserving page number, page bbox, rotation, and pdftext character/block dictionaries before Marker block assembly.
- Upstream `marker/schema/page.py` keeps page-scoped state (`pnum`, `rotation`, `char_blocks`, OCR/layout/order fields) as the unit consumed by downstream cleanup/output.
- PDF 1.7 article threads use catalog `/Threads`, thread `/F`, bead `/N` and `/V` links, page `/P`, and bead `/R` rectangles. Tagged PDF structure elements use `/StructTreeRoot` and `/K` marked-content references with `/Type /MCR`, `/Pg`, and `/MCID`. Page/application `/PieceInfo` dictionaries are private review metadata and are not visible page text.

## Behavior

`PdfPagePropertyExtractor` now composes page review rows from:

- page `/PieceInfo`;
- catalog `/Threads` bead metadata grouped by page object;
- StructTreeRoot `/MCR` marked-content rows grouped by page object;
- existing page `/AF`, `/MarkInfo`, `/UserProperties`, and page presentation metadata.

The new rows are review-only. Thread titles, structure titles, `/Alt`, `/ActualText`, and PieceInfo private values remain out of visible WordPress paragraphs.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Result before source change: failed in `composes page PieceInfo with article thread beads and StructTree MCR review metadata`; expected `2` page review rows, actual `1`.

Green focused:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Result after source change: `1 test files, 168 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-article-thread-pieceinfo-mcr-review-currentbase.php
```

Result: emitted `page_review_count=2`, `first_page_article_beads=[21,22]`, `first_page_mcr_mcids=[0,1]`, `second_page_article_beads=[23]`, and `visible_text_excludes_review_metadata=true`.

Counters: focused behavior tests move `577 -> 578`; mapped semantics move `414 -> 415 / 78`.

## Non-Overlap

This does not repeat accepted standalone page `/PieceInfo` plus tagged UserProperties review, page `/AF` checksum review, catalog `/Threads` reading order, outline/OpenAction target article beads, or document-level StructTreeRoot language/role/MCID metadata. The bounded behavior is page-level composition of those existing review surfaces into `PdfPagePropertyExtractor` page rows.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object parsing, page-tree ordering, `PdfOutlineExtractor` article-thread metadata, `PdfMetadataExtractor` structure-tree review rows, and `PdfTextExtractor` page labels/text extraction. It does not execute Python, pdftext, pypdfium, Poppler, Ghostscript, JavaScript, PDF actions, raster engines, OCR/model stacks, or external PDF tools.
