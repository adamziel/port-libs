# markerPDF embedded-files attachment mirror current-base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T172105Z`
Session: `port-dev-markerpdf-attachments-20260605T172105Z`
Base accepted HEAD: `c25270a5e1901a85c2c879af6acd80bd7da5ed0e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF structure extraction to pdftext/PDFium before model/OCR stages. Under the current no-GPU markerPDF scope, this lane owns native PHP parser preflight for searchable PDF structure, including EmbeddedFiles, FileSpec dictionaries, associated-file mirrors, and WordPress import safety.

PDF embedded-file name trees map names to FileSpec dictionaries. A direct FileSpec can omit `/F` and `/UF`, in which case the name-tree key is the only stable display/storage filename. The same nameless direct FileSpec stream can also be mirrored from catalog `/AF`; that mirror should enrich the named attachment row rather than create a generated `attachment-N` duplicate.

## Behavior

`PdfAttachmentExtractor` now treats same-stream direct FileSpec rows as mirrors when one side has a generated filename and the other side has a real name source such as `name_tree_key`, `/F`, `/UF`, or a platform key. This preserves the EmbeddedFiles name-tree filename while carrying catalog `/AF` mirror metadata.

`PdfEmbeddedFileExtractor` now records `filename_source` on low-level embedded-file rows and dedupes generated nameless direct FileSpec mirrors after a named same-stream row has already been collected.

The WordPress smoke emits one `wp:file` block for `wp-source.xml`, omits embedded payload bytes from the public attachment summary, and keeps the attachment payload out of visible text.

## Red-First Evidence

After adding the focused test before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL dedupes direct nameless FileSpec mirrors using EmbeddedFiles name-tree filename before attachment import
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS dedupes direct nameless FileSpec mirrors using EmbeddedFiles name-tree filename before attachment import

1 test files, 49 assertions, 0 failures
```

Adjacent extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 924 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-direct-filespec-nametree-mirror-currentbase.php
```

The smoke exits `0` and reports `attachment_count=1`, `embedded_file_count=1`, `filename_source=name_tree_key`, `associated_file_source=catalog_af`, `checksum_matches=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat generationed FileSpec references, indirect name-tree keys, duplicate invalid name-tree keys, platform-matched `/EF` key selection, PDFDocEncoding filenames, related-file `/RF` name pairs, encrypted EFF preflight, decoded-length metadata, stream-filter stacks, object-stream attachment recovery, trailer-root attachment selection, xref Prev-chain attachment repair, portfolio collection metadata, or associated-file PieceInfo checksum review.

The bounded behavior is only same-stream dedupe for direct nameless FileSpec mirrors where the EmbeddedFiles name-tree key supplies the usable filename.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object parser, name-tree traversal, FileSpec/EF stream extraction, checksum metadata, attachment preflight summarizer, embedded-file review extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
