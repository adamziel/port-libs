# Pandoc doctemplates core current-base 2026-06-07T01:14:18Z

## Slice

- Added bounded native `templates/default.plain` fallback support to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', ..., 'plain')` now resolves through the
  same built-in default-template path used by the accepted Markdown, HTML,
  LaTeX, Beamer, office, EPUB, and Typst fallbacks.
- Direct `templates/default.plain` requests use the bounded built-in resource
  unless the caller supplies an explicit override.
- Updated the WordPress doctemplate review-packet smoke with a PlainText
  default-template check.

## Source Truth

- `dependency-backlog.json` lists `pandoc-doctemplates-core` as the bounded
  native renderer needed for Markdown, PlainText, LaTeX, HTML, and WordPress
  review output.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` maps Pandoc writer/template
  evidence against pinned Pandoc `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
  and already records static default-template fallback slices. This slice adds
  the missing bounded PlainText default-template case without executing the
  upstream runner.
- This slice used only lane-local native PHP rendering. No Pandoc binary,
  Cabal solver/build/test command, Haskell runner, external template engine,
  browser renderer, online service, live provider test, or live-service
  provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 328 assertions, 0 failures`.
- Red-first local check before implementation:
  `php <<'PHP' ... renderResource('templates/default', [], ['body' => 'Plain review body'], null, 'plain') ... PHP`
  - Result: `UnexpectedValueException: Missing doctemplate resource templates/default`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 331 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+3` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized block pipes, Unicode display-width padding, missing/null pipe
handling, in-memory or filesystem resource discovery, path-style partial
lookup, applied partial variable rebinding, partial recursion guards, braced
pipe quoted-string braces, braced separator parsing, alpha overflow labels,
boolean false output, Unicode identifier parsing, multiline control boundary
newline swallowing, empty standalone partial line swallowing, `chomp`
traversal, breakable-space rendering/wrapping, dedented nesting termination,
final newline stripping for included partials, extensionless custom-template
output-format fallback, source-location diagnostics, default HTML/Markdown/
CommonMark/LaTeX/Beamer/OpenXML/OpenDocument/EPUB3/Typst resources, default
HTML style partials, unclosed `$~$` breakable-space rejection, or default
HTML5 void tag serialization.

It only adds bounded `templates/default.plain` resource lookup and matching
WordPress review-packet smoke coverage.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in resource lookup, in-memory resources, and the
WordPress doctemplate review-packet example. Fuller upstream default-template
data-file parity, richer source-location diagnostics, full doclayout value
modeling, external template engines, online services, live provider tests, and
full upstream Pandoc runner parity remain separate bounded follow-up work.

Root harness: not run - isolated micro-slice.
