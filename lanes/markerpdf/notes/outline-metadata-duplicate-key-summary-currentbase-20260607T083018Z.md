# markerpdf outline metadata duplicate-key summary current-base slice

Session: `port-dev-markerpdf-outline-meta-20260607T083018Z`
Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260607T083018Z`
Base accepted HEAD: `867192b58e262ad4f336e17b1620246d7ff6cad8`

## Behavior

Duplicate top-level outline item `/Metadata` entries are now included in the
document outline duplicate-key review summary, matching existing handling for
duplicate `/Title`, `/Dest`, `/A`, traversal, style, and structure keys. The
selected metadata stream remains the last top-level `/Metadata` entry, stays
review-only, and its decoded payload is not promoted into document metadata,
navigation metadata, or visible WordPress text.

## Red-first evidence

Before the production change, the focused test failed because the outline-wide
duplicate-key summary omitted `/Metadata`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records selected outline Metadata duplicate-key provenance without exposing payloads
FAIL surfaces duplicate outline Metadata keys in the outline-wide duplicate key summary
Values are not identical
Expected: 1
Actual: NULL
PASS keeps duplicate outline Metadata streams out of TOC navigation and visible WordPress text

1 test files, 48 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records selected outline Metadata duplicate-key provenance without exposing payloads
PASS surfaces duplicate outline Metadata keys in the outline-wide duplicate key summary
PASS keeps duplicate outline Metadata streams out of TOC navigation and visible WordPress text

1 test files, 58 assertions, 0 failures
```

WordPress smoke updated:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-duplicate-key-boundary-currentbase.php
```

The smoke exits 0 and emits `duplicate_item_key_count=1`,
`duplicate_item_keys=["Metadata"]`, selected-entry stream provenance, and
payload-exclusion flags without Python, models, OCR, PDF action execution, or
external PDF tools.

Syntax and lane hygiene:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-duplicate-key-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-metadata-duplicate-key-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0 with no output.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF dictionary
token scanner, outline metadata extractor, stream-filter decoder, and existing
WordPress smoke harness. GPU/model/OCR behavior remains intentionally out of
scope for markerPDF under the current no-GPU lane override.

## Non-overlap

This slice does not repeat current trailer-root outline selection, root
metadata stream review, duplicate navigation keys, duplicate structure keys,
or Type3 font width-advance behavior. It only adds `/Metadata` to item-level
duplicate-key review summaries and verifies the existing duplicate Metadata
stream fixture reports that boundary.
