# Pandoc doctemplates core current-base default.plain hooks

Slice: `pandoc-doctemplates-core-current-base-20260609T020811Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Source Truth

- Upstream `jgm/pandoc-templates` `default.plain` includes titleblock,
  header-includes, include-before, table-of-contents, body, and include-after
  hooks: https://raw.githubusercontent.com/jgm/pandoc-templates/master/default.plain
- The Pandoc template documentation records that Pandoc ships default templates
  for output formats and that template syntax uses the doctemplates variable,
  loop, separator, partial, and pipe model:
  https://hackage.haskell.org/package/doctemplates
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser
  renderer, online service, live provider test, or live-service provider test
  was executed.

## Implementation

- Expanded the bounded native `DocTemplate::defaultPlainTemplate()` resource
  from body-only rendering to the upstream plain-template hook structure.
- Preserved direct `templates/default.plain`, `templates/default` with
  `plain`, and caller custom-resource override behavior.
- Added a focused named doctemplate test for the plain title/header/include/TOC
  hooks and adjusted exact plain newline expectations to the upstream-shaped
  template body.
- Updated the WordPress doctemplate review-packet smoke so the user-visible
  plain default fallback exercises the new hooks.

## Evidence

- Rework notes: no current non-stale
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  note targeted this doctemplate slice.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  `1 test files, 1108 assertions, 0 failures`.
- Red-first probe before implementation:
  `php <<'PHP' ... renderResource('templates/default', ..., 'plain') ... PHP`
  failed with `missing Plain Review Packet`, proving the accepted base rendered
  only the body for `default.plain`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  `1 test files, 1114 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with six assertions.
- `lane-status.json` `phpPass`: `2124 -> 2125`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2551 -> 2552`.
- Added manifest inventory keys
  `mappedDoctemplateDefaultPlainHookCases: 1` and
  `doctemplateDefaultPlainHookAssertions: 6`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`DocTemplate` resource resolver, default-template registry, focused
doctemplate tests, and lane-local WordPress doctemplate review-packet smoke.
Full upstream Pandoc/Haskell runner parity remains blocked by the missing
hydrated checkout and Cabal-built Tasty runner dependency closure recorded in
the lane status.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming, variable
truthiness, loops, breakable-space wrapping, parameterized pipes, applied
partials, recursion-limit ordering, explicit nesting, extension-qualified
partial aliases, user-data partial precedence, archive compression, or broad
default-template registration. It owns only the bounded `default.plain`
resource hook drift. Useful follow-up remains another non-overlapping
default-resource drift check or parser/resource diagnostic edge.
