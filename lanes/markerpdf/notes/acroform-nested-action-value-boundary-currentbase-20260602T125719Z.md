# markerPDF AcroForm Nested Action Value Boundary

Micro-slice: `acroform-appearance-action-value-boundary-currentbase-20260602T125719Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps low-level PDF parsing, text extraction, and page/metadata review separate from conversion cleanup. This PHP slice preserves that boundary for AcroForm review metadata without executing PDF actions, JavaScript, Python models, or external PDF tools.
- PDF action dictionaries can carry `/Next` as either one action or an array of actions. AcroForm field/widget `/A` and `/AA` actions must be inventoried for review, but not executed during WordPress import.
- PDF AcroForm `/V` and `/DV` remain field values/defaults. Widget `/AP /N` streams selected by `/AS` are appearance review metadata only and must not replace the imported field value or leak stream text into WordPress blocks.

## Implementation

- `PdfAcroFormExtractor` now walks nested field/widget action `/Next` chains through both direct action dictionaries and arrays of action dictionaries.
- The walker carries a bounded depth limit and object-cycle guard, so cyclic `/Next` references are blocked while nested review rows such as URI -> Launch -> Hide -> JavaScript remain visible to import tooling.
- Widget appearance review remains non-executing and field `/V` stays authoritative for the import value.

## Verification

- Red-first focused check after adding the new fixture failed as expected:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`
  reported `1 test files, 547 assertions, 1 failures`; the nested `Hide` action under a `/Next` array member was missing.
- Post-change focused check passed:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`
  reported `1 test files, 569 assertions, 0 failures`.
- WordPress smoke passed:
  `php lanes/markerpdf/examples/wordpress-pdf-acroform-nested-action-value-boundary.php`
  emitted field action types `URI`, `Launch`, `Hide`, and `JavaScript`, widget action types `Named` and `GoTo`, `cycle_blocked=true`, `field_value=https://example.test/final`, `selected_appearance_imports_visible_text=false`, and all action/model/external-tool execution flags false.
- PHP lint passed for `lanes/markerpdf/src/PdfAcroFormExtractor.php`, `lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-acroform-nested-action-value-boundary.php`.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted AcroForm selected widget appearance stream review, current/default value-state extraction, SubmitForm/ResetForm metadata, non-JavaScript field action parsing, field/widget JavaScript action payload hashing, catalog OpenAction `/Next` review, JavaScript inspector cycle/depth inventory, or rich-media action target boundaries. The new behavior is specifically recursive AcroForm field/widget `/Next` array walking while preserving the field value and appearance review boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array parser, AcroForm field/widget traversal, action metadata path, field-name mapping, and existing non-executing review metadata. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
