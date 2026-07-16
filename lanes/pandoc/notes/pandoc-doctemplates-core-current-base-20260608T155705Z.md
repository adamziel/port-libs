# Pandoc doctemplates core current-base extension-qualified partial fallback

Slice: `pandoc-doctemplates-core-current-base-20260608T155705Z`
Base: `88918a69038ea1f5dab678b0be595fb89790e664`
Lane: `pandoc`

## Source Truth

- Hackage `doctemplates` documents Pandoc-style templates with partials,
  applied partials, literal separators, no automatic escaping, and partial
  lookup based on the original template path and extension.
- The accepted `pandoc-doctemplates-core` extension-qualified output-format
  slice already maps exact custom resources such as `review.html+smart` before
  base-writer custom/default fallback resources.
- This slice extends that bounded native resource contract to sibling partial
  discovery: when an exact extension-qualified template is selected, exact
  extension partials such as `header.html+smart` win first, while base-writer
  partials such as `warning-row.html` remain available if no exact partial
  exists.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
  template engine, browser renderer, online service, live provider test, or
  live-service provider test was executed.

## Implementation

- `DocTemplate::partialResourcesForTemplateResource()` now registers partials
  in priority passes per search directory: exact selected extension first,
  then the base extension for extension-qualified resource names.
- Added focused coverage for a custom `templates/review.html+smart` resource
  that uses both an exact `components/header.html+smart` partial and a base
  `components/warning-row.html` applied partial.
- Updated the WordPress doctemplate review-packet smoke with the same
  extension-qualified partial fallback path.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 774 assertions, 0 failures`.
- Red-first focused command after adding the new test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  failed with `1 test files, 774 assertions, 1 failures`; the new case failed
  on `Missing doctemplate partial components/warning-row`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 776 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.
- Focused delta: `+1` named PHP PASS case and `+2` focused assertions.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free or parameterized pipes, map-pairs
ordering, missing lookup pipe behavior, partial inclusion, partial final-newline
handling, partial recursion guards, path-style partial lookup, applied-partial
variable rebinding, braced separator parsing, default Markdown/CommonMark/man/
ms/Beamer/DocBook fallbacks, filesystem resource loading, source-location
diagnostics, colon-qualified metadata names, false boolean interpolation, or
extension-qualified top-level template lookup.

It only owns the missing base-extension sibling partial fallback for already
selected extension-qualified custom template resources.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, partial discovery, focused `DocTemplateTest.php` coverage,
and the WordPress doctemplate review-packet example. Full upstream
Pandoc/Haskell doctemplates runner parity, external template engines, browser
renderers, online services, live provider tests, and live-service provider
tests remain out of scope.

Root harness: not run - isolated micro-slice.
