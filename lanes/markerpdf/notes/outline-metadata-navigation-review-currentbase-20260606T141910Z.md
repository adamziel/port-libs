# markerpdf outline metadata navigation review current-base slice

- Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T141910Z`
- Accepted base: `25518d5fb4d3eaf5d13a177b5560194ebcd2afa6`
- Scope: native no-GPU PDF outline metadata. No OCR, Surya/Texify/Torch, Python model workers, or external PDF tools were run.

## Upstream behavior boundary

PDF outline items may carry a `/Metadata` stream. The existing document metadata path already reviewed that stream as outline-item metadata without promoting its decoded payload into document XMP roots or visible text. This slice carries the same payload-safe review summary into WordPress navigation surfaces:

- `getNavigationReviewMetadata()` outline rows now expose `metadata_stream_review`.
- `getOutlineStructureDestinationPageContext()` rows now expose `metadata_stream_review`.
- outline action-review rows now expose the prefixed `outline_metadata_stream_review`.

The metadata payload remains review-only: hashes and byte counts are preserved, but decoded stream contents are excluded from document metadata roots, navigation JSON, and visible Gutenberg paragraph text.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php
1 test files, 12 assertions, 2 failures
```

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php
1 test files, 47 assertions, 0 failures
```

Adjacent outline metadata/navigation set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php
5 test files, 226 assertions, 0 failures
```

Broader outline-family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*.php
75 test files, 3607 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-boundary-currentbase.php
```

The smoke emits `navigation_metadata_stream_status=reviewed_outline_item_metadata_stream`, `outline_action_types=[GoTo,URI]`, excludes the payload from document/navigation metadata and visible text, and records `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Changed PHP files passed `php -l`, `lanes/markerpdf/lane-status.json` decoded with `JSON_THROW_ON_ERROR`, and `git diff --check -- lanes/markerpdf` produced no output.

## Non-overlap

This does not repeat the prior direct trailer `/Encrypt` dictionary preflight slice, outline `/SE` structure propagation, outline root/parent/prev/last/count traversal boundaries, or metadata-stream payload exclusion. It only reuses already-safe document-outline `/Metadata` review rows inside navigation/action review surfaces.

## Dependency closure

No new support component is needed. The patch reuses the native PHP PDF metadata parser, outline extractor, stream-filter decoding, and existing payload-redaction metadata summaries.
