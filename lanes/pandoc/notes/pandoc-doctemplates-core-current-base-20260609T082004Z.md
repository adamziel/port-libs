# Pandoc Doctemplates Core Current Base - User-Data Default Templates

Slice: `pandoc-doctemplates-core-current-base-20260609T082004Z`
Base accepted HEAD: `e8462716baed1244ed5b9f195429af80b17d479b`

## Source Truth

- Pandoc default templates are data resources named `templates/default.<format>`.
- Pandoc user-data templates are expected to override bundled defaults for relative default-template requests, while directly supplied template resources keep highest precedence.
- This slice maps only that bounded resource-lookup behavior in native PHP. It does not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests.

## Implementation

- `DocTemplate::renderResource()` and `renderResourceWrapped()` now pass the user-data directory into template resource resolution.
- `DocTemplate::resolveTemplateResourcePath()` now checks a user-data `templates/<default-basename>` resource before bundled defaults for relative default-template candidates.
- Explicit main resources still win over user-data resources, and absolute template paths still do not use user-data or bundled fallback resources.
- Added focused coverage for extensionless `templates/default` with `html`, extension-qualified `html+smart`, explicit `templates/default.markdown`, main-resource precedence, and absolute-path exclusion.
- Updated the WordPress doctemplate review-packet self-test to cover user-data default-template override and main-resource precedence.

## Evidence

- Rework note check found no current non-stale `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files for this session.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1163 assertions, 0 failures`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1168 assertions, 0 failures`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with five assertions.
- `lane-status.json` `phpPass`: `2524 -> 2525`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2895 -> 2896`.
- `mappedDoctemplateDefaultTemplateCases`: `1 -> 2`.
- `doctemplateDefaultTemplateAssertions`: `9 -> 14`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP doctemplate resource map, default-template registry, partial discovery, focused doctemplate tests, and lane-local WordPress doctemplate review-packet smoke.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming, parser diagnostics, variable truthiness, loops, parameterized pipes, partial rebinding, resource extension inference, extension-qualified partial fallback, default partial fallback, or nested/breakable-space behavior. A useful follow-up would be another bounded upstream doctemplate parser diagnostic or default-resource behavior that does not reuse this user-data default-template precedence path.
