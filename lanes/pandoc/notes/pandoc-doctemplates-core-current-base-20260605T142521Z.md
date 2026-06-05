# Pandoc doctemplates core current-base 2026-06-05T14:25:21Z

## Slice

- Added bounded output-format fallback for extensionless doctemplate resources
  in `PortLibs\Pandoc\DocTemplate`.
- `renderResource()` and `renderResourceWrapped()` now accept an optional safe
  output format. When the requested template resource is missing, has no file
  extension, and a format is supplied, the renderer retries `<template>.<format>`.
- Exact resource paths still take precedence over fallback candidates, template
  paths with explicit extensions do not fall back, and invalid output-format
  names are rejected before probing.
- Updated the WordPress doctemplate review-packet smoke to request the
  extensionless `review-packets/review` resource and resolve the stored
  `review-packets/review.html` template through the native renderer.

## Source Truth

- Pandoc User's Guide `Templates` documents that custom templates can be named
  with `--template`, and default templates are selected by output format:
  https://pandoc.org/demo/example33/6-templates.html
- Pandoc User's Guide `Template syntax`, `Partials` documents the same
  directory and user-data template search shape already covered by this PHP
  resource-map renderer:
  https://pandoc.org/demo/example33/6.1-template-syntax.html
- Pinned upstream `Text.Pandoc.App.OutputSettings` retries a custom template
  path with the selected output format when the requested template path has no
  extension and the first lookup fails:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/App/OutputSettings.hs
- Pinned upstream `Text.Pandoc.Templates` records that template lookup falls
  back to Pandoc data templates, while partial lookup can use local/default
  partial search modes:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- This slice uses only the existing native PHP renderer and an in-memory
  resource map. No Pandoc binary, Cabal build, Haskell runner, external
  template engine, browser renderer, JavaScript, online sanitizer, or online
  service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 64 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 69 assertions, 0 failures`.
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
nesting termination, or final newline stripping for included partials. It only
adds extensionless main-template resource fallback by output format.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and the accepted WordPress doctemplate review-packet
example. Default-template data-file parity, full filesystem or HTTP-backed
template discovery, richer source-location diagnostics, full doclayout value
modeling, and full upstream Pandoc runner parity remain separate activation
slices.

Root harness: not run - isolated micro-slice.
