# Doctemplates Core Current Base - Pipe Quote Diagnostics

Slice: `pandoc-doctemplates-core-current-base-20260609T040318Z`
Base accepted HEAD: `72a53fe4cb43f993ddc490102ccddab53f4ddfb1`

## Source Truth

- Upstream doctemplates parses template directives with quoted pipe arguments,
  so an unterminated quoted border belongs to the quote span rather than to the
  whole `$...$` or `${...}` directive.
- This slice maps that bounded parser diagnostic behavior in the native PHP
  `DocTemplate` tokenizer. It does not attempt broad upstream runner parity.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner,
  external template engine, Word, LibreOffice, zip/unzip, TeX/PDF engine,
  browser renderer, external converter, online service, live provider test, or
  live-service provider test was executed.

## Implementation

- `DocTemplate` now tracks the opening offset for a quoted pipe argument while
  scanning `$...$` and `${...}` directive bodies.
- If directive scanning reaches EOF while still inside that quote, the
  exception is `Unclosed doctemplate pipe quoted string` at the opening quote
  source location instead of the directive start.
- Added focused PHP test coverage for inline and resource-template failures.
- Updated the WordPress doctemplate review-packet self-test with the bad
  resource-template diagnostic.

## Red-First Evidence

Before implementation, inline `$...$` block-pipe quote input reported the
directive start:

`php -r 'require "tools/bootstrap.php"; try { (new PortLibs\Pandoc\DocTemplate())->render("Intro\nBox: $" . "title/left 8 \"[$", ["title" => "Review"]); } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'`

Output before fix:

`UnexpectedValueException: Unclosed doctemplate $...$ directive at <template>:2:6`

Before implementation, `${...}` resource input also reported the directive
start:

`php -r 'require "tools/bootstrap.php"; try { (new PortLibs\Pandoc\DocTemplate())->renderResource("review-packets/broken.html", ["review-packets/broken.html" => "<p>" . "$" . "{ title/center 8 \"<\" \" }"], ["title" => "Review"]); } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'`

Output before fix:

`UnexpectedValueException: Unclosed doctemplate ${...} directive at review-packets/broken.html:1:4`

After implementation, the probes report:

- `UnexpectedValueException: Unclosed doctemplate pipe quoted string at <template>:2:20`
- `UnexpectedValueException: Unclosed doctemplate pipe quoted string at review-packets/broken.html:2:26`

## Evidence

- Focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1133 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with two assertions.
- `lane-status.json` `phpPass`: `2267 -> 2268`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2671 -> 2672`.
- Added `mappedDoctemplatePipeQuoteDiagnosticCases: 1`.
- Added `doctemplatePipeQuoteDiagnosticAssertions: 2`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`DocTemplate` tokenizer, focused doctemplate tests, and the lane-local
WordPress doctemplate review packet smoke. Full upstream Pandoc/doctemplates
runner parity remains a separate upstream-runner dependency task requiring a
hydrated checkout and Haskell test executables.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
unclosed or malformed separator diagnostics, valid braced/unbraced separator
payload scanning, variable separator ordering after pipes, Unicode diagnostic
columns, variable truthiness, loops, parameterized pipes, partial rebinding,
recursion-limit ordering, explicit nesting blank-line behavior,
applied-partial newline preservation, default-template fallback,
extension-qualified partial aliases, nested breakable-space wrapping, or broad
filesystem loading. A useful follow-up would be another bounded parser/resource
diagnostic or default-resource gap with focused PHP tests.
