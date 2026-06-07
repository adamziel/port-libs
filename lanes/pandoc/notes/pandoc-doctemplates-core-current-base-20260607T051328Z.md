# Pandoc doctemplates core current-base 2026-06-07T05:13:28Z

## Slice

- Added a bounded native Pandoc `templates/default.ms` fallback resource to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', ..., 'ms')` now resolves through the
  existing default-template lookup path, and direct `templates/default.ms`
  requests use the built-in resource unless the caller supplies an override.
- The fallback covers the groff ms review-packet surface needed locally:
  Pandoc generator comments, highlighting macros, page/font settings,
  hyphenation/adjusting controls, inline-math delimiter setup, PDF metadata
  handoff, title/author/date/abstract blocks, include-before/body/include-after,
  TOC, and final pdfsync marker.
- Updated the WordPress doctemplate review-packet smoke with the same bounded
  `ms` default-template check.

## Source Truth

- Pinned Pandoc `Text.Pandoc.Templates.getDefaultTemplate` maps unknown writer
  names by reading `templates/default.<format>`, with explicit aliases handled
  before that fallback:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Pinned Pandoc `data/templates/default.ms` is the source shape for the bounded
  groff ms metadata/settings/body handoff:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.ms
- Doctemplates documents output-format-agnostic rendering, variables,
  conditionals, loops, partials, breakable spaces, and predefined pipes:
  https://hackage.haskell.org/package/doctemplates

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 344 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 372 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+28` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane manifest/status JSON validation passed.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loops, separators, `$it$`, `$^$`, automatic multiline nesting,
parameter-free or parameterized pipes, Unicode display-width padding, missing
and null pipe handling, resource discovery, filesystem loading, path-style
partials, applied-partial rebinding, partial recursion guards, braced pipe
arguments, braced separators, alpha overflow labels, boolean false output,
Unicode identifier parsing, multiline control newline swallowing, empty
standalone partial line swallowing, `chomp`, breakable-space rendering/wrapping,
dedented nesting termination, final newline stripping, source-location
diagnostics, default HTML5/Markdown/CommonMark/plain/LaTeX/Beamer/man/OpenXML/
OpenDocument/EPUB3/Typst resources, default HTML style partials, built-in
default resources as nested partial fallbacks, or default HTML5 void tag
serialization.

It owns only the bounded `templates/default.ms` fallback resource and the
matching review-packet smoke path.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
renderer, default-resource lookup, in-memory resources, focused lane test
harness, and WordPress doctemplate review-packet example.

Full upstream default-template parity for remaining writer formats, roff
rendering, Pandoc/Cabal/Haskell runner parity, external template engines,
TeX/PDF engines, browser renderers, online services, live provider tests, and
live-service provider tests remain out of scope for this isolated micro-slice.

Root harness: not run - isolated micro-slice.
