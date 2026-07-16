# markerPDF Metadata NameTree PieceInfo OutputIntent Current Base

Session: `port-dev-markerpdf-meta42pdf-20260602T1922Z`

Micro-slice: `metadata-nametree-pieceinfo-outputintent-currentbase`

Base accepted HEAD: `2f7ab5c6c7fa7a5a593e92a06a3c2a9a2e3a8f58`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction as page block output from `pdftext.extraction.dictionary_output(...)` and returns TOC metadata separately from page text: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Upstream `marker/output.py::save_markdown` writes Markdown text and `out_metadata` as separate artifacts, so native PDF review metadata must not leak embedded payloads or private streams into visible WordPress paragraphs: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py>.
- Relevant PDF parser/dependency behavior from pypdf constants: catalog dictionaries carry `/Names`, FileSpec dictionaries carry `/F`, `/UF`, `/EF`, `/RF`, and `/Desc`, and page dictionaries demonstrate `/PieceInfo` plus `/OutputIntents` as review metadata keys rather than text content: <https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html>.

## Behavior

`PdfMetadataExtractor` now applies FileSpec `/PieceInfo` review extraction on the shared FileSpec review helper used by catalog `/Names /EmbeddedFiles`, OutputIntent `/AF`, and StructTree `/AF` rows.

The focused current-base fixture proves:

- the latest xref stream selects the current catalog, Info dictionary, root OutputIntent, name-tree children, and page text before stale appended objects;
- catalog `/Names /EmbeddedFiles` child `/Limits` keep out-of-range FileSpec rows out of review metadata;
- a name-tree FileSpec can carry `/PieceInfo /Private` dictionaries with nested `/OutputIntents` and private stream references as review-only metadata;
- FileSpec-local `/OutputIntents` contribute attachment provenance, while only the root catalog OutputIntent contributes document `pdfa`;
- embedded payload bytes, PieceInfo private stream text, stale name-tree rows, and stale payloads stay out of visible WordPress output.

## Red/Green Evidence

Red before source change, after fixing the fixture bounds:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves name-tree FileSpec PieceInfo and OutputIntent review on current xref catalog (lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php)
Values are not identical
Expected: 'D:20260602192222Z'
Actual: NULL

1 test files, 17 assertions, 1 failures
```

Green after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves name-tree FileSpec PieceInfo and OutputIntent review on current xref catalog

1 test files, 35 assertions, 0 failures
```

Focused metadata regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 873 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-nametree-pieceinfo-outputintent-currentbase.php
```

Passed and emitted `markerpdf-metadata-nametree-pieceinfo-outputintent-currentbase`, a Gutenberg paragraph with `Current NameTree PieceInfo Body`, and review-only metadata for `nt-piece-192222`, `Current NameTree FileSpec sRGB`, and omitted payload content.

Final focused verification on the dirty worktree:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-metadata-nametree-pieceinfo-outputintent-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-metadata-nametree-pieceinfo-outputintent-currentbase.php

php -r '$json = json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "manifest json ok\n";'
manifest json ok

php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited successfully with no output.

## Status Delta

- Behavior tests move `705 -> 706`.
- WordPress scenarios move `705 -> 706`.
- Mapped markerPDF semantics move `508 -> 509 / 78` for the bounded name-tree FileSpec PieceInfo + FileSpec-local OutputIntent review path.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, xref-stream selector, dictionary/value parser, embedded-file stream review, OutputIntent profile hashing, PieceInfo review parser, FileSpec provenance summarizer, and visible-text exclusion paths. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/OCR, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, benchmark workflows, and live Python/model workers; none were executed for this bounded native PHP slice.

## Non-Overlap

This does not repeat accepted catalog `/AF` PieceInfo review, root OutputIntent extraction, OutputIntent-associated FileSpec review, catalog `/Names /EmbeddedFiles` limits without PieceInfo, DSS/name-tree metadata, Portfolio `/Collection`, catalog PieceInfo private metadata roots, FileSpec related-file `/RF`, page `/AF`, StructTree `/AF`, or page PieceInfo review. The new behavior is specifically FileSpec `/PieceInfo` on generic review rows, proven through current xref-selected catalog `/Names /EmbeddedFiles` with FileSpec-local `/OutputIntents`.
