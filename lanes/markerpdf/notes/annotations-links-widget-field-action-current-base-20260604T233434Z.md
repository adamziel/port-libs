# markerpdf annotations links widget field action current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260604T233434Z`

Accepted base: `95042f4c4d1808e74b08ca1191236b2696e87d6b`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream conversion path treats PDF actions as non-executing import/review metadata; this no-GPU slice preserves that boundary.
- PDF page dictionaries own visible annotation membership through page-local `/Annots`. Split AcroForm Widget annotations can keep link-like activation data on the terminal field while the page-referenced widget leaf owns the current `/Rect`, `/P`, and visibility flags.
- This slice maps the native boundary: promote only page-referenced Widget annotation leaves, inherit missing terminal-field `/A`, `/AA`, or `/Dest` link keys for review/link metadata, keep local widget action values authoritative, and exclude unsafe parent JavaScript plus detached field-only widgets.

## Implementation

- `PdfLinkAnnotationExtractor` now builds an effective Widget link review dictionary by copying missing `/A`, `/AA`, and `/Dest` values from the widget field-parent chain.
- The page annotation leaf still supplies visibility filtering, rectangle geometry, object identity, and span intersection.
- Applied spans now carry inherited widget metadata: inherited keys, field-parent object, field chain, action source, and per-key source objects.
- `PdfPageWidgetFieldActionLinkCurrentBaseTest.php` covers inherited field `/A` plus `/AA`, local widget action override, unsafe inherited JavaScript exclusion, detached field-only widget exclusion, Markdown link rendering, and visible-text isolation.
- `wordpress-pdf-widget-field-action-link-currentbase.php` emits a Gutenberg-oriented smoke showing inherited widget links and excluded unsafe/detached parent field actions.

## Red first

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL inherits page widget link actions from terminal field dictionaries without promoting detached fields
Unsafe inherited JavaScript and detached field widgets are not promoted.
Expected: 2
Actual: 1
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS inherits page widget link actions from terminal field dictionaries without promoting detached fields
1 test files, 46 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
13 PASS cases
6 test files, 270 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-widget-field-action-link-currentbase.php
```

The smoke emitted `link_count=2`, `widget_link_count=2`, `inherited_widget_keys=["A","AA"]`, `field_parent_object=20`, `field_additional_action_events=["U","D"]`, `local_widget_uses_local_action=true`, `unsafe_parent_promoted=false`, `detached_field_widget_promoted=false`, and all PDF action/Python/model/external-tool execution flags false.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-widget-field-action-link-currentbase.php
No syntax errors detected in all changed PHP files
```

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status delta

- `lane-status.json` `phpPass`: `1106 -> 1107`.
- `lane-status.json` `wordpressScenarios`: `1105 -> 1106`.

## Non-overlap

This does not repeat accepted top-level `/Annots` ownership, escaped `/Annots` and escaped Link dictionary keys, direct Widget link promotion, indirect Widget `/Rect` and `/F` operands, rotated/UserUnit link rectangles, annotation appearance/popup/sound review, text-markup QuadPoints geometry, or StructParent action context. The new behavior is specifically terminal-field `/A`/`AA`/`Dest` review inheritance for page-referenced Widget annotation leaves.

## Dependency closure

No new support component is needed. This reuses native object scanning, dictionary token parsing, page `/Annots` traversal, action review, destination name-tree resolution, supplied pdftext span geometry, and Markdown span merging. Full live OCR, Surya/Texify/Torch model execution, PDFium rendering parity, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
