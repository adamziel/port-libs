# RichMedia GoToE Attachment Action Review Boundary

Slice: `richmedia-attachment-action-review-boundary-currentbase-20260602T112130Z`
Base accepted HEAD: `7687f6843f222401790ea71d927032dc7fd58a25`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `pdftext.extraction.dictionary_output()` and pypdfium2 page text extraction in `marker/pdf/extract_text.py`; this lane keeps embedded-file bytes outside visible text and does not execute Python/model/external PDF tooling.
- PDF GoToE action dictionaries are embedded-document navigation actions with `/F`, `/D`, `/NewWindow`, `/T`, and `/Next` fields. This slice maps those dictionaries into non-executing review metadata for RichMedia annotation `/A` and `/AA` actions.

## Implementation

- `PdfRichMediaAnnotationExtractor` now walks RichMedia annotation GoToE action contexts, resolves top-level Filespec `/F` references, records `/EF` embedded-file object numbers and metadata, decodes embedded-file MIME subtype names, records `/D` destination view parameters, records `/T` embedded target dictionaries, preserves `/NewWindow`, and labels the action as `embedded-document-review`.
- GoToE file and target metadata is added to the annotation action review rows and annotation `file_names`, but catalog-only stale embedded files and embedded payload stream text are not promoted into the current RichMedia row or visible text.
- Chained `/Next` JavaScript actions remain represented as blocked review rows.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 212 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 243 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-richmedia-attachment-action-boundary.php | sed -n '1,80p'
emitted embedded_action_count=2, go_to_e_files=["review-pack.pdf"], target_names=["review-pack.pdf","chapter-notes.pdf"], attachment_embedded_file_objects=[21], stale_attachment_not_promoted=true, attachment_payload_text_excluded=true, javascript_action_blocked=true, executes_media=false, executes_javascript=false, executes_python_or_models=false, executes_external_pdf_tools=false

php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 977 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfRichMediaAnnotationExtractor.php
No syntax errors detected

php -l lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
No syntax errors detected

php -l lanes/markerpdf/examples/wordpress-pdf-richmedia-attachment-action-boundary.php
No syntax errors detected
```

Final metadata/diff gates:

```text
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
passed

git diff --check -- lanes/markerpdf
passed
```

## Status Delta

- `lane-status.json` `phpPass`: `483 -> 484`
- `UPSTREAM_TEST_MANIFEST.json` mapped semantics: `331 -> 332`
- New WordPress smoke: `wordpress-pdf-richmedia-attachment-action-boundary.php`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice extends the accepted rich-media action target boundary by adding GoToE embedded-document Filespec, destination, and target dictionary review rows. It does not repeat current xref stream object boundaries, rich-media popup/target-only stale promotion checks, media playback boundaries, JavaScript execution safety, embedded-file name-tree/PieceInfo extraction, or Filespec payload text exclusion slices except as focused adjacent guards.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF object parser, RichMedia action walker, Filespec dictionary decoding, embedded-file metadata extraction patterns, and text extraction boundary checks. Full upstream markerPDF Python/pdftext/pypdfium/model parity remains dependency-gated and was not executed.
