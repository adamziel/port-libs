# Pandoc doctemplates core current-base HTML4 default alias

Slice: `pandoc-doctemplates-core-current-base-20260608T052519Z`
Base: `c162e5af21915b05e444923d010d6e56dffee14f`
Lane: `pandoc`

## Source Truth

- Earlier accepted doctemplate notes pin Pandoc
  `Text.Pandoc.Templates.getDefaultTemplate` at upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, where legacy HTML writer
  aliases are resolved through the default HTML5 resource instead of requiring
  a separate local template file.
- This slice keeps that bounded native contract for `html4` and
  extension-qualified `html4+...` output formats: exact caller-provided
  `templates/default.html4` resources win first, then the existing
  `templates/default.html5` resource/default fallback is used.
- The local upstream Pandoc checkout was not hydrated at
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream files were
  read beyond the accepted lane manifest and notes for this slice.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
  template engine, browser renderer, online service, live provider test, or
  live-service provider test was executed.

## Implementation

- `DocTemplate::canonicalDefaultTemplateFormat()` now canonicalizes `html4`
  to the existing bounded `html5` default template resource.
- Added focused tests covering:
  - `templates/default` fallback for `html4`;
  - extension-qualified `html4+smart`;
  - exact caller override precedence for `templates/default.html4`;
  - fallback to caller-provided `templates/default.html5` when no exact HTML4
    resource is present.
- Updated the WordPress doctemplate review-packet smoke with an `html4+smart`
  default-template alias check.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 641 assertions, 0 failures`.
- Red-first focused command after adding the test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  failed with `1 test files, 641 assertions, 1 failures`; the new case failed
  with `Missing doctemplate resource templates/default`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 648 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PASS/assertion delta:
  - `+1` PHP PASS case
  - `+7` focused assertions

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, pipes, map-pairs ordering, partial inclusion,
path-style partial lookup, applied-partial variable rebinding,
extension-qualified format parsing, default HTML5 template serialization,
Markdown/CommonMark, AsciiDoc, LaTeX, Beamer, Reveal.js, ConTeXt, man, ms,
OpenXML, OpenDocument, EPUB3, ICML, DocBook5, JATS, Typst default resources,
default partial fallback resources, filesystem resource loading,
source-location diagnostics, boolean false rendering, Unicode identifiers, or
colon-qualified metadata keys.

It only owns the legacy `html4` writer alias for existing default-template
resource selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, existing built-in `templates/default.html5` fallback,
focused `DocTemplateTest.php` coverage, and the WordPress doctemplate
review-packet example. Full upstream Pandoc runner parity, exact Haskell
doctemplates parser parity, external template engines, browser renderers,
online services, live provider tests, and live-service provider tests remain
out of scope.

## Next

Continue doctemplate closure with a non-overlapping native template-resource
gap such as slide-specific default resources, default partial diagnostics, or
parser validation behavior while preserving the no-external-runner boundary.

Root harness: not run - isolated micro-slice.
