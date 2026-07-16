# markerPDF Metadata Catalog NameTree Current Base

Session: `port-dev-markerpdf-meta38pdf-20260602T1829Z`

Micro-slice: `metadata-catalog-nametree-currentbase`

Base accepted HEAD: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

The local upstream cache path referenced by the lane manifest was absent in this isolated worktree. I used the pinned upstream GitHub source instead:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/README.md`

Upstream Marker delegates PDF text extraction to PDF parser/page iteration (`pdftext.dictionary_output()` and PDFium text pages) before model and Markdown stages, and emits conversion metadata separately from Markdown output. This native PHP slice stays within that parser boundary: catalog `/Names` trees for `/EmbeddedFiles` and `/Dests` are review metadata and must not leak stale attachment payloads or destination labels into visible WordPress paragraphs.

PDF name-tree nodes can declare `/Limits` for the least and greatest keys in a subtree. The native parser now applies sane limits when a node contains at least one key matching its declared bounds, filtering mixed stale/out-of-range rows while preserving existing tolerant behavior for wholly inconsistent fixture nodes.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result before fix: failed in `bounds current xref-selected catalog name-tree metadata by node limits`; expected `2` embedded-file rows, actual `3`. The stale out-of-limits `z-stale-source.xml` FileSpec was accepted from the current catalog name tree.

## Implementation

`PdfMetadataExtractor` now shares name-tree limit helpers between destination and embedded-file walkers:

- resolves `/Limits` through direct or indirect string operands;
- intersects child limits with inherited parent bounds;
- filters current node `Names` pairs when a sane bound matches at least one key;
- keeps cycle/depth protection and current xref-selected object ownership intact.

The WordPress smoke `examples/wordpress-pdf-metadata-catalog-nametree-currentbase.php` proves current xref-selected catalog metadata wins while stale appended catalog, Info, attachment, and destination objects remain excluded.

## Verification

Red-first command before source change:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Failed: `1 test files, 724 assertions, 1 failures`.

After fix:

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-catalog-nametree-currentbase.php
```

Passed: no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `1 test files, 743 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed: `3 test files, 1651 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-metadata-catalog-nametree-currentbase.php
```

Passed: emitted `embedded_name_tree_files=["current-source.xml","review-bundle.pdf"]`, `destination_names=["Current Start","Review Summary"]`, `stale_out_of_limits_rows_excluded=true`, and visible text `Current Catalog NameTree Limits Body`.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, xref stream object selection, dictionary/value parser, catalog metadata extractor, embedded-file review metadata, destination review metadata, and text extractor. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, and live app/server workflows.

## Non-Overlap

This does not repeat direct catalog XMP extraction, PDF/A OutputIntent metadata, DSS validation-stream review, FileSpec payload hashing, Portfolio collection metadata, current xref-selected `/Metadata`/`/OutputIntents`/`/AF`, parser stream owner boundaries, outline name-tree action/transition review, or embedded-file name-tree attachment extraction. The bounded behavior is current xref-selected catalog `/Names` `/EmbeddedFiles` and `/Dests` limit filtering for review-only metadata.
