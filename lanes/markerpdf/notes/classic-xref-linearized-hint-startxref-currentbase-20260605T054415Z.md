# markerPDF classic xref linearized hint startxref current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T054415Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text, metadata, and embedded-file extraction through parser-backed PDF boundaries before OCR/model fallback. In this no-GPU native PHP lane, classic xref rebuild, linearized hint-table exclusion, trailer metadata, EmbeddedFiles name trees, and WordPress attachment preflight are parser dependency boundaries.

Linearized `/H` hint-table byte ranges are not authoritative trailer bytes for WordPress import. A `startxref` token inside those ranges must not redirect metadata or attachment extraction to a trailing decoy classic xref table.

## Behavior

`PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now skip `startxref` candidates whose token offset falls inside a linearized `/H [start length]` byte range. This matches the existing text extractor behavior and keeps current page text, XMP/Info metadata, EmbeddedFiles, and attachment summaries selected from the same current classic xref table.

The focused fixture builds:

- a current linearized PDF with a valid classic xref table selecting current page text, XMP/Info metadata, and an EmbeddedFiles name-tree source attachment;
- a later decoy classic xref table plus decoy metadata and attachment objects;
- a trailing `startxref` token that points at the decoy table, with that token covered by the first object `/Linearized /H` hint range.

Before the fix, a throwaway red-first fixture showed `PdfTextExtractor` selected `Current hint-bounded xref page`, while `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` selected `Hint Decoy XRef Title` and `decoy-hint-xref.xml`.

## Evidence

Focused classic rebuild test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips linearized hint-range startxref tokens during classic rebuild before WordPress imports

1 test files, 285 assertions, 0 failures
```

Affected extractor check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1893 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-linearized-hint-startxref-currentbase.php
```

The smoke exits `0` and reports `linearized_hint_startxref_skipped=true`, `current_classic_xref_import_kept=true`, `hint_decoy_import_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-classic-xref-linearized-hint-startxref-currentbase.php
git diff --check -- lanes/markerpdf
```

All changed PHP files reported no syntax errors. Diff check reported no whitespace errors.

Exploratory broader xref-family run:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfXref*Test.php' -o -name 'PdfParserXref*Test.php' -o -name 'PdfParserTrailer*CurrentBaseTest.php' \) | sort)
66 test files, 1524 assertions, 3 failures
```

The failing tests are unrelated object-stream text fixtures that also fail individually on this accepted base: `PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php`, `PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php`, and `PdfXrefObjectStreamTrailerBoundaryTest.php`. This slice does not edit `PdfTextExtractor`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted classic rebuild handling for damaged/stale startxref offsets, EOF-bounded trailing xref garbage, comments, arrays, composite tokens, name-token startxref decoys, name-delimited xref pseudo-tables, malformed xref rows, `/Prev` chain repair, xref-stream generation/index repair, object-stream carrier ownership, or table/page-image geometry work.

The bounded behavior here is only non-text extractor alignment with the existing text-side linearized `/H` hint-range `startxref` exclusion before classic xref rebuild.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanners, classic xref table parser, trailer metadata parser, EmbeddedFiles extractor, attachment preflight path, and text extractor. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
