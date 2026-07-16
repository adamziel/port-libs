# markerPDF xref Prev inherited trailer current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T104807Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260608T104807Z`
Base accepted HEAD: `4af637c3364e3f16eef0a1d2e1a204436022069d`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream delegates searchable-PDF text and metadata extraction to PDF parser dependencies before OCR/layout/model stages. Under the current no-GPU markerPDF scope, xref repair is therefore a native parser dependency boundary for PHP import fidelity.

## Behavior

Incremental PDFs may end with a sparse xref stream whose trailer has `/Prev` but omits `/Root` and `/Info`. The previous trailer references still identify the catalog and Info graph, but current same-generation replacements can exist between the previous xref section and the latest sparse xref stream.

Before this slice, current-update graph repair seeded only from `/Root`, `/Info`, and `/Encrypt` keys present in the latest trailer. That let stale previous catalog, page text, Info dictionary, XMP metadata, and EmbeddedFiles attachments win when the latest sparse xref stream omitted those graph roots.

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now seed current-update graph repair from inherited `/Prev` trailer `/Root`, `/Info`, and `/Encrypt` references when the latest trailer omits those keys. Explicit latest non-reference values such as `/Root null` or `/Info null` remain authoritative and stop inheritance.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainInheritedTrailerCurrentBaseTest.php
```

Failed with `1 test files, 1 assertions, 1 failures`; the stale previous page text was selected.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainInheritedTrailerCurrentBaseTest.php
```

Passed: `1 test files, 29 assertions, 0 failures`.

Focused xref Prev-chain family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChain*CurrentBaseTest.php
```

Passed: `27 test files, 1197 assertions, 0 failures`.

Direct extractor guard:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Passed: `4 test files, 2388 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-inherited-trailer-currentbase.php
```

Passed with current page text, current XMP/Info metadata, current attachment extraction, stale previous rows excluded, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainInheritedTrailerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-inherited-trailer-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat accepted damaged xref-stream row repair, sparse classic offset-owner repair, current trailer `/Root` or `/Info` precedence, current `/Root null` and `/Info null` stop conditions, object-stream carrier repair, action object-stream member-boundary parsing, encrypted trailer metadata, latest xref-stream trailer metadata, outline metadata boundaries, or runtime preflight work.

The new boundary is specifically a latest sparse xref stream that omits trailer graph roots and must inherit `/Root`/`/Info` references from `/Prev` before selecting current same-generation replacements.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref table/stream parser, trailer `/Prev` walker, direct object scanner, metadata/text/attachment extractors, and WordPress smoke path. GPU/OCR/model parity, live Surya/Texify/Torch execution, pypdfium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
