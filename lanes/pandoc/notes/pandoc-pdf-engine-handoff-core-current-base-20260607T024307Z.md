# pandoc-pdf-engine-handoff-core-current-base-20260607T024307Z

Base accepted HEAD: `c0189ee9c433a90073c4136e67c4f8566a365749`

## Behavior

This slice adds bounded produced-PDF `/Resources /ColorSpace` extraction to the native `PdfEngineHandoff` fake-runner path. It records page-local and inherited page-tree color-space resources for WordPress review packets without invoking Pandoc or a PDF renderer.

The handoff now preserves:

- resource name, page object, page number, object reference, and inherited-resource flag
- color-space family counts in `pdfColorSpaceFamilies` and `finalPdfColorSpaceFamilies`
- ICCBased profile metadata: components, alternate color space, byte count, and SHA-256 hash
- Separation and DeviceN colorant names with PDF name hex escapes decoded
- alternate color spaces and tint-transform references
- fake-runner diagnostics for resource counts, ICC profiles, tint transforms, and family totals

The WordPress PDF engine smoke now exposes `pdfColorSpaces`, `pdfColorSpaceFamilies`, `finalPdfColorSpaces`, and `finalPdfColorSpaceFamilies` for review-packet handoff.

## Evidence

- Baseline before the new case: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 696 assertions, 0 failures`.
- Red-first after adding the focused color-space expectations: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` failed with `1 test files, 698 assertions, 1 failures` because `pdfColorSpaces` metadata was absent.
- Final focused result: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 707 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.

## Non-overlap

This avoids already covered PDF-engine clusters such as output intents, image XObject color spaces, graphics states, optional content, active actions, named destinations, document info, XMP/PDF-A, tagged structure, page display metadata, attachments, and URI base metadata. It only adds page resource color-space handoff metadata from produced PDF bytes.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `PdfEngineHandoff` PDF byte scanner, bounded PDF object/dictionary parsing helpers, `MarkdownReader`, `WordPressBlockWriter`, and the focused lane PHP harness.

Full renderer parity remains out of scope unless explicitly authorized: Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript runtimes, online services, live provider tests, and live-service provider tests were not run.
