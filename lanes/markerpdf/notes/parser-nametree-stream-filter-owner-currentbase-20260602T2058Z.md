# markerPDF parser name-tree stream-filter owner current-base

Micro-slice: `parser-name-tree-stream-filter-owner-currentbase`

Base: `dc17119479f92562b7d16aa7377f5088a0295935`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF object, xref, stream, and action payload handling to `pdftext` and PDFium/pypdfium. The native PHP lane therefore has to keep catalog name-tree review metadata non-executing while still binding indirect stream operands to the current xref-selected PDF object owner.

This slice covers catalog `/Names` JavaScript name-tree entries whose `/JS` value is an indirect filtered stream. Filtered payload bytes can contain fake `endstream`, `endobj`, and object headers. The metadata review path now scans direct objects and stream payloads token-aware enough to keep those bytes inside the stream owner, records review-only stream source metadata, and does not expose decoded JavaScript bytes or fake object markers to WordPress output.

## Patch

- `PdfMetadataExtractor` now reads direct object bodies through a token-aware object-end scan instead of the previous regex body capture.
- Metadata stream decoding now parses the stream dictionary and uses declared length or a verified filter terminator before decoding.
- Catalog name-tree JavaScript/URI payload source review now records stream `bytes`, `sha256`, and `filters` while keeping `payload_included=false`.
- Added a focused fixture where a current xref-selected JavaScript name-tree payload stream is `/FlateDecode` encoded and contains fake `endstream/endobj/99 0 obj` bytes plus stale detached appended objects after `%%EOF`.
- Added a WordPress smoke that emits one paragraph, `Current name tree stream filter owner page`, and review-only stream source metadata.

## Non-overlap

This does not repeat accepted catalog name-tree limits, PDF/A associated EmbeddedFiles name-tree metadata, EmbeddedFiles payload extraction, object-stream filter-owner exclusion, xref-stream filter/length operand review, stream dictionary owner text extraction, filter-array dictionary rejection, or xref offset-owner fallback scanning. The new behavior is specifically catalog name-tree action payload streams in `PdfMetadataExtractor` where filtered payload bytes contain fake object/stream owner tokens.

## Verification

Focused:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameTreeStreamFilterOwnerCurrentBaseTest.php
```

Result: `1 test files, 20 assertions, 0 failures`.

Adjacent metadata/parser owner gate:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameTreeStreamFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result: `6 test files, 937 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-parser-nametree-stream-filter-owner-currentbase.php
```

Result: emitted `source_type=stream`, `object=41`, `filters=["FlateDecode"]`, `payload_included=false`, `fake_stream_owner_excluded=true`, `stale_detached_name_tree_excluded=true`, and one paragraph with current page text.

Syntax:

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfParserNameTreeStreamFilterOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-nametree-stream-filter-owner-currentbase.php
```

Result: no syntax errors.

Whitespace:

```sh
git diff --check -- lanes/markerpdf
```

Result: passed.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF metadata extractor, xref-selected object map, stream-filter decoder, catalog name-tree review walker, and WordPress smoke renderer. Full upstream parity remains dependency-gated by live `pdftext`, PDFium/pypdfium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI, benchmark/model downloads, and external OCR/rendering tools.
