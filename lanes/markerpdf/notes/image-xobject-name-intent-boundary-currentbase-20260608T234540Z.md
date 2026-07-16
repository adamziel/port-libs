# Image XObject /Intent and /Name Boundary Current Base

- Session: `port-dev-markerpdf-image-xobject-20260608T234540Z`
- Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T234540Z`
- Accepted base: `04878c2d5c57d16172dcae66b4ced2d6a4447658`
- Scope: native no-GPU markerPDF PDF parser/review behavior only.

## Source Truth

PDF Image XObject `/Intent` and `/Name` are image stream dictionary metadata hints. They should be surfaced to WordPress media review only when they are unambiguous top-level image stream dictionary name operands. Nested private dictionaries, tailed operands, and duplicate declarations are fail-closed review metadata and must not leak into public media-review fields.

This follows the existing markerPDF image-XObject boundary behavior for top-level `/Type`, `/Subtype`, dimensions, masks, metadata, references, and filter operands. It does not run OCR, Surya/Texify/Torch, GPU/model workers, or external PDF tools.

## Change

- `PdfTextExtractor::imageXObjectBoundaryEntry()` now resolves public `rendering_intent` and `image_name` through a strict top-level metadata helper.
- The helper rejects duplicate top-level declarations and direct-name operands followed by extra top-level operands, then uses exact object-generation name resolution.
- Valid top-level `/Intent /Saturation` and `/Name /Valid#20Review#20Name` still appear in review metadata.

## Evidence

Red-first probe before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectNameIntentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps image XObject Intent and Name metadata on strict top-level operands only
Values are not identical
Expected: NULL
Actual: 'AbsoluteColorimetric'
1 test files, 54 assertions, 1 failures
```

Focused checks after the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectNameIntentBoundaryCurrentBaseTest.php
PASS keeps image XObject Intent and Name metadata on strict top-level operands only
1 test files, 84 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
48 PASS cases
1 test files, 1260 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-image-xobject-name-intent-boundary-currentbase.php
exits 0; metadata reports private_metadata_null=true, tailed_metadata_null=true,
duplicate_metadata_null=true, valid_rendering_intent=Saturation, and
valid_image_name="Valid Review Name".
```

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectNameIntentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-name-intent-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary/name parser and existing FlateDecode stream handling. Live OCR, model/GPU execution, multiprocessing model workers, PDFium/PIL, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-Overlap

This avoids the existing accepted image-XObject boundaries for resource entry tails, duplicate `/Type` or `/Subtype`, malformed numeric operands, masks, alternates, OPI, optional content, color spaces, and filter/decode behavior. The patch only tightens public Image XObject `/Intent` and `/Name` review metadata operand boundaries.
