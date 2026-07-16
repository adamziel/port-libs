# Pandoc PDF Engine Handoff - Visual Signature Appearance Policy

Slice: `pandoc-pdf-engine-handoff-core-current-base-duplicate-20260609T044849Z`
Base accepted HEAD: `ed0eac2bb60c741dd69063ef3bea95aa86948d6f`
Date: 2026-06-09 UTC

## Behavior

`PdfEngineHandoff` now summarizes signed PDF widget visual appearance policy from fake-produced PDF bytes without invoking Pandoc, TeX/PDF engines, browser renderers, office tools, zip/unzip, external converters, online services, or live providers.

The new `pdfSignatureAppearancePolicy` and `finalPdfSignatureAppearancePolicy` rows report:

- signed field/signature identity;
- matched widget page/object provenance when an appearance exists;
- normal appearance count, state names, appearance XObject references, and stream bytes;
- count of normal appearance streams covered by the signature byte range;
- review status and issues for missing widget appearances, missing normal appearances, missing selected state for state dictionaries, missing streams, skipped streams, selected-state mismatches, and uncovered appearance streams.

This reuses the existing native PHP signature extraction, annotation appearance extraction, and signature appearance byte-range helpers. It does not implement cryptographic signature validation or visual rendering.

## Non-Overlap

This slice does not repeat the accepted PDF signature seed, signature lock, FieldMDP, byte-range, signature appearance byte-range, DSS, associated-file, name-tree, page-label, parent-tree, ID-tree, conformance, or XMP policy slices. It adds the missing visual signature appearance policy layer over existing byte-range evidence.

## Dependency Closure

No new support component is needed. The implementation reuses bounded native PHP PDF object parsing already present in `PdfEngineHandoff`; full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` - no syntax errors
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` - 1 test file, 1255 assertions, 0 failures
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` - `pdf engine handoff self-test ok`
- Root harness: not run - isolated micro-slice

## Next

For PDF engine follow-up, choose a non-overlapping fake-runner policy such as DSS/VRI reference consistency, widget action safety, or output-intent/conformance cross-checks not already covered by existing PDF handoff policies.
