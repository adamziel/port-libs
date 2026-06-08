# Pandoc doctemplates core current-base PDF default template alias

Slice: `pandoc-doctemplates-core-current-base-20260608T220010Z`
Base: `497cd3a8c38ad3b9434a204962c1bfed3b7521ec`
Lane: `pandoc`

## Source Truth

- Pandoc's template documentation states that PDF output customizes the
  `default.latex` template, unless a different intermediate writer such as
  ConTeXt, ms, or HTML is explicitly selected:
  https://pandoc.org/demo/example33/6-templates.html
- This slice keeps that bounded native contract for default-template lookup:
  `templates/default` with output format `pdf` or an extension-qualified
  `pdf+...` format resolves through the bundled `templates/default.latex`
  resource.
- Exact caller-provided resources still win before the alias fallback, so
  `templates/default.pdf` and `templates/default.latex` custom resources keep
  precedence over the built-in LaTeX default.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
  template engine, TeX/PDF engine, browser renderer, online service, live
  provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate::canonicalDefaultTemplateFormat()` now canonicalizes default
  `pdf` template lookup to the existing bounded `latex` default resource.
- Added focused tests covering:
  - `templates/default` fallback for `pdf`;
  - extension-qualified `pdf+smart` fallback;
  - caller override precedence through `templates/default.latex`.
- Updated the WordPress doctemplate review-packet smoke with a PDF default
  template check that renders LaTeX handoff text without running a PDF engine.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1055 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1066 assertions, 0 failures`.
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
- Focused delta:
  - `+11` focused assertions inside an existing PHP PASS case
  - `phpPass` unchanged at `1900` because no new named PHP PASS case was added
  - mapped denominator moves from `2321` to `2322`

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, pipes, map-pairs ordering, partial inclusion,
path-style partial lookup, applied-partial variable rebinding,
extension-qualified format parsing, default HTML5 template serialization,
Markdown/CommonMark, AsciiDoc, LaTeX direct format lookup, Beamer, Reveal.js,
ConTeXt, man, ms, OpenXML, OpenDocument, EPUB3, ICML, DocBook5, JATS, Typst
default resources, default partial fallback resources, filesystem resource
loading, source-location diagnostics, boolean false rendering, Unicode
identifiers, colon-qualified metadata keys, or extension-qualified partial
fallback.

It only owns the missing `pdf` writer alias for existing LaTeX
default-template resource selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, bundled `templates/default.latex` resource, focused
`DocTemplateTest.php` coverage, and the WordPress doctemplate review-packet
example. Full upstream Pandoc runner parity, exact Haskell doctemplates parser
parity, external template engines, TeX/PDF engines, browser renderers, online
services, live provider tests, and live-service provider tests remain out of
scope.

## Next

Continue doctemplate closure with a non-overlapping native template-resource
gap such as remaining default-template alias drift, user-data source-location
diagnostics, or parser validation behavior while preserving the no-external
runner boundary.

Root harness: not run - isolated micro-slice.
