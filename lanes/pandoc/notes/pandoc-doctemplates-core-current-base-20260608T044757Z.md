# Pandoc doctemplates core current-base 2026-06-08T04:47:57Z

## Slice

- Added bounded `DocTemplate` support for Pandoc output formats with extension
  toggles such as `html+smart-native_divs`,
  `markdown_strict+emoji-hard_line_breaks`, `gfm+emoji`, and `docx+styles`.
- Resource lookup now validates extension-qualified format strings, preserves
  exact extension-specific resources such as `templates/review.html+smart`,
  then falls back to the base writer resource such as `templates/review.html`
  or the existing built-in default-template alias.
- Updated the WordPress doctemplate review-packet smoke with custom HTML and
  Markdown default-template extension-qualified fallback checks.

## Source Truth

- Pandoc output formats can include `+extension` and `-extension` toggles while
  default templates are selected by the base writer format.
- Existing lane notes already pin Pandoc `Text.Pandoc.Templates` and default
  data-template behavior at upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- The local Pandoc upstream checkout was not hydrated in
  `/home/claude/port-libs/.upstream-cache/pandoc`, matching earlier
  upstream-runner dependency audit notes, so this slice reused the lane-local
  pinned manifest/notes and native PHP renderer evidence.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
  template engine, browser renderer, online service, live provider test, or
  live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 630 assertions, 0 failures`.
- Red-first probes before implementation:
  - `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/default", [], ["body" => "Extension format body"], null, "html+smart");'`
    failed with `InvalidArgumentException: Invalid doctemplate output format`.
  - `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/review", ["templates/review.html" => "<p>$" . "body$</p>"], ["body" => "Custom extension body"], null, "html+smart");'`
    failed with `InvalidArgumentException: Invalid doctemplate output format`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 641 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, pipes, map-pairs ordering, partial inclusion,
path-style partial lookup, applied-partial variable rebinding, default HTML5,
Markdown/CommonMark, AsciiDoc, LaTeX, Beamer, Reveal.js, ConTeXt, man, ms,
OpenXML, OpenDocument, EPUB3, ICML, DocBook5, JATS, Typst default resources,
default partial fallback resources, filesystem resource loading,
source-location diagnostics, boolean false rendering, Unicode identifiers, or
colon-qualified metadata keys.

It only owns extension-qualified output-format resource selection for the
existing native `DocTemplate` renderer.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, existing built-in default-template resources, focused
`DocTemplateTest.php` coverage, and the WordPress doctemplate review-packet
example. Full upstream Pandoc runner parity, exact Haskell doctemplates parser
parity, external template engines, browser renderers, online services, live
provider tests, and live-service provider tests remain out of scope.

## Next

Continue doctemplate closure with a non-overlapping native template-resource
gap such as remaining bounded default writer resources, default partial
diagnostics, or parser validation behavior while preserving the no-external
runner boundary.
