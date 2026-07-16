# Pandoc doctemplates core current-base 2026-06-06T02:07:11Z

## Slice

- Added bounded built-in default Markdown/CommonMark template fallback to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', [], ..., 'markdown_strict')` and the
  related Markdown-family aliases now resolve to `templates/default.markdown`.
- `gfm` and `commonmark_x` now resolve to `templates/default.commonmark`.
- Direct `templates/default.markdown` and `templates/default.commonmark`
  requests use the bounded built-in resource unless the caller supplies an
  explicit resource-map override.
- Updated the WordPress doctemplate review-packet smoke with GFM and
  CommonMark default-template checks for title blocks, header includes, TOC,
  body, and include-before/after fragments.

## Source Truth

- Pinned upstream `Text.Pandoc.Templates.getDefaultTemplate` maps
  `markdown_strict`, `multimarkdown`, `markdown_github`, `markdown_mmd`, and
  `markdown_phpextra` to `markdown`, and maps `gfm` and `commonmark_x` to
  `commonmark`:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Pinned upstream `data/templates/default.markdown` and
  `data/templates/default.commonmark` share the same standalone body wrapper
  shape covered by this native fallback:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.markdown
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.commonmark
- This slice used the lane-local native PHP renderer and in-memory resource
  map only. No Pandoc binary, Cabal build/solver/test command, Haskell runner,
  external template engine, browser renderer, JavaScript, online sanitizer,
  online conversion service, live provider test, or online service was
  executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 140 assertions, 0 failures`.
- Red-first focused command after adding the default Markdown/CommonMark
  fallback expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 140 assertions, 1 failures`;
    failure was `Missing doctemplate resource templates/default`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 148 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering, parameterized
block pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partial
variable rebinding, partial recursion guards, braced pipe quoted-string braces,
braced separator parsing, alpha overflow labels, boolean false output, Unicode
identifier parsing, multiline control boundary newline swallowing, empty
standalone partial line swallowing, `chomp` traversal, breakable-space
rendering/wrapping, dedented nesting termination, final newline stripping for
included partials, extensionless custom-template output-format fallback,
unclosed ordinary-dollar diagnostics, default HTML5 template lookup,
default-template metadata/title block/TOC expansion, default HTML style
partials, unclosed `$~$` breakable-space rejection, or default HTML5 void tag
serialization.

It only adds bounded built-in default Markdown/CommonMark resources and the
upstream writer aliases that select them. It does not touch ZIP/OPC package
primitives, YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML readers,
Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, archive compression, syntax
highlighting, XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency
audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in default-template fallback, in-memory resource
map, and WordPress doctemplate review-packet example. Fuller upstream
default-template data files and partials beyond bounded HTML5/Markdown/
CommonMark, filesystem/HTTP-backed template discovery, richer source-location
diagnostics, full doclayout value modeling, and full upstream Pandoc runner
parity remain separate bounded slices.

Root harness: not run - isolated micro-slice.
