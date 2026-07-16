# Pandoc doctemplates core current-base 2026-06-05T18:40:51Z

## Slice

- Expanded the bounded native `PortLibs\Pandoc\DocTemplate`
  `templates/default.html5` fallback toward the pinned upstream Pandoc default
  HTML5 template.
- The built-in fallback now preserves WordPress review-packet metadata and
  title-block variables for:
  - XHTML namespace plus `lang`, `xml:lang`, and `dir` attributes.
  - `pandoc-version` generator metadata and viewport metadata.
  - `author-meta`, `date-meta`, `keywords`, and `description-meta` head tags.
  - `title-prefix` plus `pagetitle`, with `title` as the bounded fallback.
  - `math`, `subtitle`, `author`, `date`, `abstract-title`, `abstract`,
    `toc`, `idprefix`, `toc-title`, and `table-of-contents` handoff.
- Updated the WordPress doctemplate review-packet smoke to assert the same
  default-template metadata path.

## Source Truth

- Pinned upstream Pandoc `data/templates/default.html5` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` contains the head metadata,
  title-block, math, and TOC variables covered here:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.html5
- Pinned upstream `Text.Pandoc.Templates.getDefaultTemplate` maps `html` to the
  default `html5` template resource:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
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
  - Result: `1 test files, 82 assertions, 0 failures`.
- Red-first focused command after adding the default-template metadata
  expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 75 assertions, 1 failures`.
  - Failure: the built-in default template still rendered `<html lang="en">`
    and lacked the expected upstream-style XHTML/html metadata attributes.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 98 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering, parameterized
pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partial
variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone
partial line swallowing, `chomp` traversal, breakable-space wrapping, dedented
nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, unclosed dollar
diagnostics, or initial bounded default-template lookup.

It only expands the existing built-in default HTML5 fallback to cover a bounded
metadata/title-block/TOC cluster from the pinned upstream default template. It
does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in default-template fallback, and in-memory
resource map. Full upstream default-template data-file parity, default partials
such as `styles.html`, filesystem/HTTP-backed template discovery, richer
source-location diagnostics, full doclayout value modeling, and full upstream
Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
