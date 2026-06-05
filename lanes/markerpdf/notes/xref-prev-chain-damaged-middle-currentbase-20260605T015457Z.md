# Xref Prev Chain Damaged Middle Current Base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T015457Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T015457Z`
Base accepted HEAD: `d08cac00333b4576903cf5223e57290c6b98686a`

## Behavior

This slice repairs a damaged middle-section `/Prev` pointer while following an incremental xref chain. When a latest xref stream points to a middle classic xref table and that middle table has an explicit `/Prev` value that lands inside, but not at, the earlier base xref section, the native PHP parser now falls back to the nearest earlier valid xref table or xref-stream section before the current section.

The repair is shared across:

- text/page object selection in `PdfTextExtractor`;
- XMP, Info, and catalog metadata selection in `PdfMetadataExtractor`;
- EmbeddedFiles name-tree attachment selection in `PdfEmbeddedFileExtractor`.

The focused fixture appends post-xref direct-object decoys for catalog, page text, XMP, Info, and EmbeddedFiles before the final `startxref`. The repaired chain selects the earlier base xref entries and excludes those post-xref decoys.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php

FAIL repairs damaged middle Prev pointers to the earlier base xref before post-xref decoys
Expected: ['Current damaged middle Prev page','Base xref repaired before decoys']
Actual: ['Post xref damaged middle Prev decoy page']
1 test files, 183 assertions, 1 failures
```

## Focused Verification

After the implementation change:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-damaged-middle-currentbase.php
```

All changed PHP files reported `No syntax errors detected`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 200 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfXref*Test.php' -o -name 'PdfParserXref*Test.php' \) | sort)
55 test files, 1131 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-damaged-middle-currentbase.php
```

The WordPress smoke emitted `current_xmp_title_selected=true`, `current_info_title_selected=true`, `current_catalog_language_selected=true`, `current_page_text_selected=true`, `current_attachment_selected=true`, `post_xref_decoy_metadata_excluded=true`, `post_xref_decoy_text_excluded=true`, `post_xref_decoy_attachment_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This is not a repeat of indirect/compressed `/Prev` operands, same-generation damaged/stale/wrong offsets, classic xref damaged row repair, xref stream `/W` or `/Index` helpers, hybrid trailer-size repair, object-stream/free-entry handling, classic rebuild boundaries, or post-EOF/comment-bounded rebuild behavior. The bounded behavior is specifically invalid middle `/Prev` pointer repair to an earlier valid xref section while excluding post-xref direct-object decoys.

## Dependency Closure

No new support component is needed. The patch reuses native PHP object-definition scanning, token-aware xref table scanning, xref-stream decoding, existing `/Prev` chain merging, text extraction, metadata extraction, and EmbeddedFiles extraction. OCR, Surya/Texify/Torch, PDFium, Python model execution, and external PDF tools remain intentionally outside the current no-GPU markerPDF scope.
