# AcroForm Field Action Submit Reset Resource Current Base

Session: `port-dev-markerpdf-form45-20260602T2029Z`

Base accepted HEAD: `2cc329862f18193df66dc35657634b327e4fe881`

Lane source base: markerPDF status source `6700a9e42fc260ab77a394eda2db806f24903c9b`

## Source Truth

- Upstream marker currently routes PDF conversion through `PdfConverter` and `PdfProvider`, which initialize/flatten PDF forms via PDFium and return extracted text/layout rather than executing AcroForm actions or returning embedded action payloads.
- PDF form actions are source-truth PDF dictionaries: ISO 32000 form actions include submit-form and reset-form actions, and PDF 2.0 errata tracks submit-form and reset-form action dictionary tables plus file-specification dictionaries.
- Existing lane behavior already parsed AcroForm `/SubmitForm`, `/ResetForm`, `/Fields`, `/Flags`, `/F` FileSpec, and `/DA` `/DR` resource metadata. This slice bridges those into one field-level action review boundary for WordPress import consumers.

## Behavior Added

- Added `action_resource_review` to AcroForm `SubmitForm` and `ResetForm` actions after field-value review annotation.
- The review summarizes:
  - action source, trigger, source/action objects, and field selection mode;
  - selected, submitted, reset, no-export, defaulted, and cleared field names;
  - field and widget default-appearance font resource names/base fonts/descriptors;
  - SubmitForm FileSpec target object, relationship, embedded file objects, and target scheme.
- Review remains non-executing:
  - `executes_action`, `executes_javascript`, `renders_appearances`, and `executes_appearance_streams` are false;
  - field values and FileSpec embedded payload text are not exposed;
  - SubmitForm does not submit data/import PDF payloads and ResetForm does not mutate imported values.

## Focused Evidence

Red-first check before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php
1 test files, 36 assertions, 1 failures
Expected action_resource_review source; actual NULL
```

Passing checks after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php
1 test files, 100 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php$')
17 test files, 1718 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-field-action-submit-reset-resource-currentbase.php
passed and printed review-only WordPress block output
```

Syntax checks run:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-field-action-submit-reset-resource-currentbase.php
```

## Non-Overlap

- This does not repeat accepted widget-only submit/reset rich-text resource reviews.
- This does not repeat accepted FileSpec action parsing for SubmitForm, ImportData, Launch, or GoToE.
- This slice specifically covers field-level additional actions (`/AA`) where the selected `/Fields`, current/default field values, no-export omissions, default appearance resources, and SubmitForm FileSpec target need one bounded current-base import review.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP PDF object parser, AcroForm field/action extraction, default appearance resource resolver, and FileSpec review helpers. Full upstream runner parity remains blocked by the existing Python/PDFium/model dependency surface and is not required for this isolated micro-slice.
