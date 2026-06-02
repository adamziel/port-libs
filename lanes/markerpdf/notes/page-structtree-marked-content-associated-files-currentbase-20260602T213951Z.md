# markerPDF Page StructTree Marked-Content Associated Files Current Base

Micro-slice: `page-structtree-marked-content-associated-files-currentbase`
Session: `port-dev-markerpdf-page64-20260602T213951Z`
Base accepted HEAD: `c3a3b3436899d5af64fa2dad7e137908759c83df`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its `marker/pdf/extract_text.py` gets page-local text through pdftext/PDFium dictionaries, while `marker/output.py` writes document metadata separately from Markdown.
- PDF parser boundary for this slice: page `/StructParents` indexes `/StructTreeRoot /ParentTree` arrays; array values are page-local MCID StructElems; StructElem `/AF` FileSpec rows are associated-file review metadata. They must decorate tagged-content review rows without becoming visible WordPress paragraph text or exposing embedded payload bytes.

## Behavior

- `PdfMetadataExtractor` now inventories StructElems reachable only through page `/StructParents` ParentTree arrays, even when `/StructTreeRoot /K` is empty.
- `PdfTextExtractor::extractTaggedContent()` decorates tagged MCID rows with StructElem metadata keyed by page object and MCID, including `struct_object`, titles, and `associated_files` / `associated_file_count`.
- The WordPress smoke emits only the H2 and paragraph text while keeping FileSpec filename, StructElem titles, page labels, and the XML payload out of visible content.

## Evidence

Focused red-before probe on the same fixture showed `extractTaggedContent()` emitted MCID rows without `associated_file_count` or `associated_files`.

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeMarkedContentAssociatedFilesCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries ParentTree StructElem associated files onto marked-content tagged rows

1 test files, 47 assertions, 0 failures
```

Related family gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructTreeMarkedContentAssociatedFilesCurrentBaseTest.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS carries ParentTree StructElem associated files onto marked-content tagged rows
PASS attaches StructTree associated-file provenance to page article-thread bead review rows

6 test files, 997 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structtree-marked-content-associated-files-currentbase.php
PASS: emitted `Associated heading visible` and `Associated body replacement`; review metadata reported `struct-marked-source.xml`, `Source`, `checksum_matches=true`, `payload_content_exposed=false`, and `visible_text_excludes_review_metadata=true`.
```

Syntax and hygiene:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageStructTreeMarkedContentAssociatedFilesCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structtree-marked-content-associated-files-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Focused behavior tests move `859 -> 860 pass / 0 fail`.
- Mapped markerPDF semantics move `605 -> 606 / 78`.
- Added one WordPress smoke scenario for page StructTree marked-content associated-file provenance.

## Non-Overlap

This does not repeat accepted page `/AF` review metadata, page StructParents MCID reading order, article-thread bead composition, annotation StructParent association, StructElem associated-file attachment to page review rows, PieceInfo private-stream exclusion, catalog associated-file extraction, or marked-content ActualText/Alt replacement alone. The bounded behavior is direct tagged-content rows and ParentTree-only StructElems carrying `/AF` FileSpec provenance.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page tree traversal, StructTree ParentTree traversal, FileSpec/embedded-file review metadata, marked-content token parsing, stream decoding, and WordPress smoke rendering. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.
