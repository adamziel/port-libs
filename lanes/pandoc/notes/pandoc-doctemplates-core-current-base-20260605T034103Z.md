# Pandoc doctemplates core current-base 2026-06-05T03:41:03Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` partial parsing to accept
  upstream-style path partial names such as `${ components/review-header() }`.
- Resource-map rendering now exposes nested partial resources relative to the
  main template directory, so `review-packets/components/warning-row.html` can
  be included as `components/warning-row()`.
- Applied partials now parse a full piped variable expression before the colon,
  so `${ warnings/rest/first:components/next-warning()/uppercase }` works like
  upstream `pInterpolate` parsing instead of treating `first:components` as a
  pipe name.
- Updated the WordPress doctemplate review-packet smoke to compose nested
  component partials and render the next-warning preview through a piped
  applied partial.

## Source Truth

- Hackage `doctemplates-0.11.0.1` README: partial names are resolved relative
  to the original template path; explicit partial extensions are kept, and
  extensionless partial names inherit the original template extension.
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` parses `pVar` before the optional
  `:partial()` branch, so variable pipes are part of the applied-partial
  variable. Its `pPartialName` accepts alphanumeric names plus `_`, `-`, `.`,
  `/`, and `\`.
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, online sanitizer, or online conversion service
  was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 42 assertions, 0 failures.
- Red-first parser probes before implementation:
  `${ components/header() }` failed with
  `Unsupported doctemplate pipe header()`, and
  `${ warnings/rest/first:components/row() }` failed with
  `Unsupported doctemplate pipe first:components`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 43 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "OK\n";'`
  passed.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` breakable-space
markers, parameter-free pipes, parameterized pipes, enumeration pipes,
Unicode display-width padding, inline partial arrays, same-directory
resource-map partial discovery, applied partial rendering without variable
pipes, partial final-newline handling, partial recursion guards, alpha
overflow labels, braced directive tokenizer behavior, or loop item scoping. It
only extends the parser/resource-map path needed for path-style partials and
piped variables before `:partial()`.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and in-memory resource map. Full
doclayout line wrapping for `$~$`, filesystem-backed template discovery beyond
the existing resource map, writer-extension template selection, default-template
parity, and full upstream Pandoc runner parity remain separate activation
slices.

Root harness: not run - isolated micro-slice.
