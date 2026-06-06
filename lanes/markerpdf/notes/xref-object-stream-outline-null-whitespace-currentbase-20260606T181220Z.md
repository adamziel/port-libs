# markerPDF xref object-stream outline NUL whitespace current-base

Date: 2026-06-06 UTC
Base: e47980bda3ac672a10fc05e8f11f982bb0b3ae43
Slice: markerpdf-object-stream-xref-parser-current-base-20260606T181220Z

## Source Truth

Upstream markerPDF delegates PDF outline/navigation and text extraction to pdftext/PDFium-style parsing before WordPress-ready Markdown output. At this native parser boundary, PDF lexical whitespace includes the NUL byte, so `/ObjStm` header integer tokens and member-body offsets selected by xref-stream type-2 rows must treat `0x00` like ordinary PDF whitespace, not as a hard delimiter failure.

## Behavior

`PdfMetadataExtractor` and `PdfOutlineExtractor` now accept NUL PDF whitespace while parsing compressed object-stream outline metadata:

- `/ObjStm` header integer token delimiters accept NUL after object numbers and offsets.
- compressed outline member offsets can start after a NUL separator.
- document metadata, TOC rows, and non-executing outline action review rows resolve from current xref-selected compressed members.
- outline titles, action operands, and URI review payloads remain out of visible WordPress paragraph text.

## Red First

Before the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL parses NUL PDF whitespace in xref-selected outline object streams before WordPress review
Expected: 'catalog_outlines'
Actual: NULL
1 test files, 2 assertions, 1 failures
```

## Verification

After the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS parses NUL PDF whitespace in xref-selected outline object streams before WordPress review
1 test files, 16 assertions, 0 failures
```

Adjacent object-stream/outline/parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamHeaderCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 107 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-outline-null-whitespace-currentbase.php
```

The smoke emits `outline_count=2`, `document_outline_source=catalog_outlines`, `visible_text_excludes_outline_metadata=true`, and `executes_python_or_models=false`.

Hygiene:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status ok\n";'
lane-status ok

php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest ok\n";'
manifest ok

php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/src/PdfOutlineExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfOutlineExtractor.php

php -l lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-outline-null-whitespace-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-outline-null-whitespace-currentbase.php

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream header comment parsing, plus-signed header integer parsing, skipped header-index alignment, explicit type-2 member-index selection, zero-width member-index recovery, duplicate object-number or duplicate-offset guards, `/First` boundary validation, member offsets inside comments/literals/composites/whitespace, classic xref NUL/form-feed whitespace repair, named-destination object-stream extraction, ordinary compressed outline extraction, metadata XMP root selection, or xref-stream `/Prev` repair.

The bounded behavior here is specifically NUL PDF whitespace in xref-selected compressed outline object-stream header/member parsing before document outline metadata and WordPress TOC/navigation review.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, metadata extractor, outline navigation/action review extractor, text extractor, and WordPress smoke renderer. GPU/OCR/model execution, Surya/Texify/Torch, pypdfium/PDFium runtime execution, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU lane.
