# Pandoc doctemplates core current-base 2026-06-04T12:35:47Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` with bounded native Pandoc
  doctemplate partial rendering.
- Added map-backed partial calls for `name()` and extension-qualified names
  such as `name.html()`.
- Added nested partial rendering with a recursion/depth guard and Pandoc-style
  final-newline stripping for included partial output.
- Added variable-applied partials such as `articles:review-card()[---]`, with
  literal separators and parameter-free pipes applied to rendered partial
  output.
- Updated the WordPress review-packet example so header, warning-list, and body
  sections are composed through native partials instead of one monolithic
  template.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Partials`: partial calls use the
  partial name followed by `()`, may use the full name including file
  extension, may be applied to variables with `:`, may use a literal separator
  in square brackets, omit final newlines, and may include other partials.
- The local upstream Pandoc cache path recorded in the manifest was unavailable
  in this isolated worker, so no Haskell source checkout or runner was used.
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, or online service was executed.

## Evidence

- Red-first probes before implementation:
  `php -r 'require "tools/bootstrap.php"; $renderer = new PortLibs\Pandoc\DocTemplate(); echo $renderer->render("\$review-header()\$", [], ["review-header" => "Header"]);'`
  failed with `Unsupported doctemplate directive review-header()`.
- Red-first loop partial probe:
  `php -r 'require "tools/bootstrap.php"; $renderer = new PortLibs\Pandoc\DocTemplate(); echo $renderer->render("\$for(warnings)\$\$warning-row()\$\$endfor\$", ["warnings" => [["source" => "media"]]], ["warning-row" => "- \$it.source\$"]);'`
  failed with `Unsupported doctemplate directive warning-row()`.
- `php -l lanes/pandoc/src/DocTemplate.php` passed.
- `php -l lanes/pandoc/tests/DocTemplateTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed:
  1 test file, 21 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 10 test files, 3105
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, conditionals, loops, separators, `$it$`, `$^$`,
automatic multiline nesting, or parameter-free variable pipes. It does not
touch ZIP/OPC package primitives, YAML metadata, Citation/CSL, Markdown/HTML
readers, Markdown/WordPress writers, DOCX/ODT/legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, or upstream-runner dependency
audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` support row and extends the lane-local renderer
with a bounded in-memory partial map. File-system template discovery, inferred
partial extension lookup, breakable-space wrapping, and parameterized pipes
remain separate activation slices. Full upstream Pandoc runner parity remains
out of scope for this isolated micro-slice because the hydrated upstream
checkout is not available locally and running Cabal would require rebuilding
the broader Pandoc dependency graph.

Root harness: not run - isolated micro-slice.
