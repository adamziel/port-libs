# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T191355Z`.

Accepted base: `6ee64e8398d01c4bd51ef8bc1f2d16d007c2db92`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, benchmark executable, Stack command, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, live provider test, or other external
converter was executed as progress.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records effective Cabal native/system
dependency fields for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

The parser merges these fields through imported Cabal `common` stanzas and
blocks non-mutating Cabal planning when runner or benchmark stanzas add
unexpected native/system inputs such as `c-sources`, `cxx-sources`,
`js-sources`, `pkgconfig-depends`, `extra-libraries`, `frameworks`,
`ld-options`, `cc-options`, or related system-link fields. This closes a
static dependency-closure gap where a hydrated checkout could otherwise look
ready while silently requiring C/C++/JS sources, pkg-config packages, native
libraries, framework paths, or linker/compiler flags outside the bounded
Pandoc runner plan.

The Cabal line-comment normalizer was also tightened so `--` inside an option
token such as `-Wl,--export-dynamic` is preserved while whitespace-prefixed
`--` comments are still ignored.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and explicitly authorized Haskell/Cabal build
closure, not by a missing local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with real
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry files, runner
artifacts, benchmark artifacts, package identity/version headers, the
tested-with GHC matrix, runner and benchmark dependency constraints,
default-extension closure, other-extension closure, `cpp-options` closure,
`autogen-modules` closure, `reexported-modules` closure, and native/system
dependency-field closure before any Cabal solver/build command.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 718 assertions, 2 failures`
  - Failure: `expectedRunnerNativeSystemFields()` was undefined, and the
    fixture with runner and benchmark native/system fields was still marked
    ready for non-mutating Cabal planning.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 812 assertions, 0 failures`
  - PASS cases: `40`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+44` assertions.
- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - No syntax errors detected.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - No syntax errors detected.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc JSON ok\n";'`
  - `pandoc JSON ok`
- `git diff --check -- lanes/pandoc`
  - Passed with no output.

No example smoke was added or run; this slice is an upstream-runner dependency
audit with no user-visible WordPress conversion path.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real package/project files before any Cabal solver/build command. If
the static audit is ready, record a non-mutating Cabal plan for
`test:test-pandoc`, `test:test-pandoc-lua-engine`, and
`benchmark:benchmark-pandoc`; keep Haskell runner and benchmark execution out
of this dependency audit slice.
