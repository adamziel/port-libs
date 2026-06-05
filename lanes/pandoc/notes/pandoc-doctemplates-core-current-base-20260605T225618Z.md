# Pandoc doctemplates core current-base 2026-06-05T22:56:18Z

## Slice

- Aligned the bounded built-in `templates/default.html5` fallback in
  `PortLibs\Pandoc\DocTemplate` with the pinned upstream default HTML5
  template's XHTML-style void metadata and stylesheet tags.
- The fallback now emits self-closing `<meta ... />` tags for charset,
  generator, viewport, author, date, keywords, and description metadata, plus
  self-closing `<link rel="stylesheet" ... />` tags for CSS resources.
- Caller-provided `header-includes` are still passed through verbatim and are
  not rewritten as XHTML void tags.
- Updated the WordPress doctemplate review-packet smoke to assert the same
  default-template metadata handoff.

## Source Truth

- Pinned upstream Pandoc `data/templates/default.html5` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` uses self-closing metadata and
  stylesheet tags in the default HTML5 template:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.html5
- Pinned upstream `Text.Pandoc.Templates.getDefaultTemplate` maps `html` to the
  default `html5` template resource:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- This slice used the native PHP renderer and in-memory resource map only. No
  Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, browser renderer, JavaScript, online sanitizer, online
  conversion service, or live provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 127 assertions, 0 failures`.
- Red-first focused run after adding the upstream void-tag expectations:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 105 assertions, 1 failures`.
  - Failure: `String does not contain '<meta charset="utf-8" />'`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 139 assertions, 0 failures`.
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
partial line swallowing, `chomp` traversal, breakable-space rendering/wrapping,
dedented nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, unclosed ordinary-dollar
diagnostics, initial default-template lookup, default-template metadata/title
block/TOC expansion, default HTML style partial resources, or unclosed `$~$`
breakable-space rejection.

It only changes the bounded built-in default HTML5 void metadata and stylesheet
tag serialization. It does not touch ZIP/OPC package primitives, YAML
metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML readers,
Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, archive compression, syntax
highlighting, XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency
audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in default-template fallback, in-memory resource
map, and WordPress doctemplate review-packet example. Fuller upstream
default-template data-file parity beyond this metadata/link cluster,
filesystem/HTTP-backed template discovery, richer source-location diagnostics,
full doclayout value modeling, and full upstream Pandoc runner parity remain
separate activation slices.

Root harness: not run - isolated micro-slice.
