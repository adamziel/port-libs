# Pandoc doctemplates core current-base 2026-06-05T17:35:13Z

## Slice

- Added a bounded native default-template data-file fallback to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', [], ..., 'html')` now resolves the
  upstream default-template writer alias to a built-in `templates/default.html5`
  resource when no in-memory resource-map override exists.
- `templates/default.html5` exact requests also resolve to the built-in
  bounded HTML5 template, while explicit `templates/default.html5` resources
  still take precedence over the built-in fallback.
- The built-in template covers the WordPress review-packet handoff surface:
  language, title, CSS links, header includes, include-before/include-after,
  and unescaped body content.
- Updated the WordPress doctemplate review-packet smoke with a default HTML
  fallback check.

## Source Truth

- Pandoc's template guide documents default templates for standalone output,
  `pandoc -D FORMAT`, and user data overrides at `templates/default.FORMAT`:
  https://pandoc.org/demo/example33/6-templates.html
- Pinned upstream `Text.Pandoc.Templates.getDefaultTemplate` maps `html` to
  `html5` and otherwise reads `templates/default.<format>` from data files:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Pinned upstream `data/templates/default.html5` is the source default HTML5
  template. This slice ports only a bounded native subset needed by review
  packet handoff, not the full upstream default-template corpus:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.html5
- No Pandoc binary, Cabal build, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, online conversion service, or
  live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 73 assertions, 0 failures`.
- Red-first focused command after adding the default-template expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 73 assertions, 1 failures`.
  - Failure: `Missing doctemplate resource templates/default`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 82 assertions, 0 failures`.
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

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partial
variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone
partial line swallowing, `chomp` traversal, breakable-space wrapping, dedented
nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, or unclosed dollar
diagnostics.

It only adds bounded built-in default HTML5 template fallback and the upstream
`html` to `html5` default-template alias for the existing in-memory resource
renderer.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and in-memory resource map. Full upstream
default-template and partial data files, filesystem/HTTP-backed template
discovery, richer source-location diagnostics, full doclayout value modeling,
and full upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
