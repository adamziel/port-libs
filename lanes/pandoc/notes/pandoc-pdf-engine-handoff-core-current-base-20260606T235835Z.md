# PDF Engine Handoff Core Current Base - Artifact Marked Content

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T235835Z`
Base accepted HEAD: `6d04ff33b7840d32f2f83f995941f5ec6af06983`
Date: 2026-06-06 UTC

## Behavior

`PdfEngineHandoff` now extracts bounded produced-PDF artifact marked-content metadata from fake-runner output bytes:

- `/Artifact ... BMC` and `/Artifact ... BDC` spans in unfiltered page content streams.
- Inline artifact `/Type`, `/Subtype`, `/BBox`, `/Attached`, and `/MCID` values.
- Bare `/Artifact BMC` spans and name-property shorthand without losing the page/content object source.
- `pdfMarkedContentArtifacts` and `finalPdfMarkedContentArtifacts` in single-run and multipass fake-runner summaries.
- Diagnostics for artifact count, attached-edge count, artifact type counts, and artifact subtype counts.

This is a produced-byte metadata handoff only. It does not execute TeX/PDF engines, Typst, browser renderers, roff, JavaScript handlers, Pandoc, Haskell runners, online services, or external PDF validators.

## Evidence

Baseline focused test before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 677 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Final focused test result: `1 test files, 686 assertions, 0 failures`.

## Status Delta

- `lane-status.json` `phpPass`: `1420 -> 1421`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1833 -> 1834`.
- `pdfEngineHandoffCoreCases`: `11 -> 12`.
- `mappedPdfEngineHandoffCoreCases`: `11 -> 12`.
- `pdfEngineHandoffCoreAssertions`: `106 -> 115`.

## Non-Overlap

This slice avoids the recent PDF handoff work for catalog requirements, URI base, tagged structure dictionaries, structure elements, resource-dictionary marked-content properties, optional content memberships, page content operator summaries, annotations, RichMedia, AcroForm, signatures, collection/thread metadata, active actions, encryption preflight, and external renderer diagnostics. It only adds artifact marked-content span extraction from already bounded page content streams.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP `PdfEngineHandoff` produced-byte scanner, page tree traversal, indirect object resolution, PDF value parser, and existing WordPress PDF engine handoff smoke.

Full renderer parity remains outside this slice and would require explicit authorization for TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, or upstream Pandoc/Haskell runner work.
