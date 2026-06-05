# Pandoc doctemplates core current-base 2026-06-05T19:14:10Z

## Slice

- Added bounded built-in default HTML5 style partial resources to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', ..., 'html')` now injects
  `templates/styles.html` and `templates/styles.citations.html` when the
  resolved default template is `templates/default.html5`.
- User-supplied resource-map entries still take precedence over the built-in
  style partials.
- The bounded style partial covers the WordPress review-packet handoff surface:
  document CSS variables, table caption placement, display-math CSS,
  highlighting CSS, and citation CSS including `csl-entry-spacing`.

## Source Truth

- Pinned upstream Pandoc `data/templates/default.html5` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes the
  `$styles.html()$` partial inside the default HTML5 `<style>` block:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.html5
- Pinned upstream Pandoc `data/templates/styles.html` and
  `data/templates/styles.citations.html` define the document, highlighting,
  display-math, and citation CSS variables covered by this native fallback:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/styles.html
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/styles.citations.html
- This slice used the native PHP renderer and in-memory resource map only. No
  Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, browser renderer, JavaScript, online sanitizer, or online
  service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 98 assertions, 0 failures`.
- Red-first probe before implementation:
  - `renderResource('templates/default', [], ..., 'html')` with
    `document-css`, `highlighting-css`, and `csl-css` rendered without the
    upstream `Default styles provided by pandoc` packet.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 123 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.

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
extensionless custom-template output-format fallback, unclosed dollar
diagnostics, initial default-template lookup, or the prior default-template
metadata/title-block/TOC expansion.

It only adds bounded built-in default HTML5 style partial resources and
resource-map override precedence for those partials. It does not touch ZIP/OPC
package primitives, YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML
readers, Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing,
table geometry, math conversion, PDF handoff planning, archive compression,
syntax highlighting, XML/HTML5 DOM, charset/Unicode, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in default-template fallback, in-memory resource
map, and WordPress doctemplate review-packet example. Full upstream
default-template data-file parity, filesystem/HTTP-backed template discovery,
richer source-location diagnostics, full doclayout value modeling, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
