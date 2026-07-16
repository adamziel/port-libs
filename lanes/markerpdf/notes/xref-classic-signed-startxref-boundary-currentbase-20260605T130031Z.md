# Classic xref signed startxref boundary current base

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T130031Z`

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T130031Z`

Accepted base: `7432b93e43b53e78103e7d38c8e49c883684735d`

## Source truth

Upstream markerPDF delegates searchable-PDF extraction through its Python/pdftext/PDFium path, but this lane is currently scoped to native no-GPU PHP parser behavior. PDF numeric tokens may carry a sign. A final `startxref -1` is still a real damaged `startxref` boundary token, so native repair must not ignore it and fall back to an older unsigned `startxref` that can import stale text, metadata, or EmbeddedFiles.

## Behavior implemented

Native `startxref` token scanning now accepts signed numeric operands in the text, metadata, attachment, embedded-file, outline, AcroForm, named-destination, and MarkerApp preview boundary paths. For the rebuild path, a negative signed operand remains invalid as an offset and triggers the existing latest classic xref-table repair. EOF trimming also treats signed `startxref` tokens as the current boundary, so earlier stale trailers do not become authoritative.

The focused fixture appends a stale valid classic xref section and WordPress-visible stale page/XMP/Info/EmbeddedFiles data, then appends current objects, a current classic xref table, and a final damaged `startxref -1`. Before the patch, the older unsigned `startxref` was selected and stale import roots won. After the patch, current page text, metadata, outline count, and attachments win, while stale content remains excluded.

## Verification

Red run before production change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php
```

Result: `1 test files / 3 assertions / 1 failures`.

Focused pass after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php
```

Result: `1 test files / 29 assertions / 0 failures`.

Classic xref family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php
```

Result: `4 test files / 605 assertions / 0 failures`.

Broad xref/trailer family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfXref|PdfParserXref|PdfMetadata.*Trailer|PdfAttachmentTrailer|PdfAcroForm.*Trailer|PdfNamedDestination.*(Xref|Trailer)|PdfOutline.*(Xref|Trailer)|MarkerAppPreviewClassicXref).*Test\.php$' | sort)
```

Result: `89 test files / 2551 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-signed-startxref-currentbase.php
```

Result: emits `signed_startxref_repaired=true`, `current_classic_xref_import_kept=true`, `stale_startxref_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat damaged out-of-file unsigned numeric startxref repair, stale valid pointer repair, post-EOF garbage trimming, comment/composite/name/literal decoys, malformed xref rows, zero-count subsections, wrong-generation explicit offsets, forward `/Prev`, stream-filter length/startxref recovery, or xref-stream security filter errors. The change is bounded to signed numeric `startxref` token recognition and the current EOF boundary used by native classic xref repair and adjacent review extractors.

## Dependency closure

No new support component is needed. The patch reuses the existing native tokenizer, classic xref parser, trailer root selection, metadata extractor, attachment extractors, outline/AcroForm/named-destination review paths, and MarkerApp preview boundary scanners. No Python, OCR, GPU/model execution, PDFium, or external PDF tools are invoked.
