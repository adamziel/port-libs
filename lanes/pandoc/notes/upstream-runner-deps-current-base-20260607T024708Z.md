# Pandoc Upstream Runner Dependency Audit 2026-06-07

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260607T024708Z`.

Accepted base: `c0189ee9c433a90073c4136e67c4f8566a365749`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, benchmark executable, Stack command, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, live provider test, or other external
converter was executed as progress.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records package-level Cabal
`custom-setup` / `setup-depends` closure for both pinned package files:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`

Both packages are expected to remain `build-type: Simple` with no custom setup
hook or setup dependency closure before a non-mutating Cabal solver/build plan
is marked ready. The parser now recognizes `custom-setup` stanzas, merges
`setup-depends` through imported common stanzas, records dependency
constraints, and blocks planning when a hydrated checkout adds setup hooks.
This closes a static dependency-closure gap where a checkout could otherwise
look ready while silently adding a Cabal setup program dependency before the
runner plan is reviewed.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and explicitly authorized Haskell/Cabal build closure,
not by a missing local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with real
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry files, runner
artifacts, benchmark artifacts, package identity/version headers, no
`custom-setup` / `setup-depends` drift, the tested-with GHC matrix, runner and
benchmark dependency constraints, default-extension closure, other-extension
closure, `cpp-options` closure, `autogen-modules` closure,
`reexported-modules` closure, `extra-source-files` closure, native/system
dependency-field closure, and absent runner/benchmark runtime options before
any Cabal solver/build command.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 904 assertions, 0 failures`
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 932 assertions, 0 failures`
  - PASS cases: `43`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+28` assertions.
- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - No syntax errors detected.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - No syntax errors detected.

No example smoke was added or run; this slice is an upstream-runner dependency
audit with no user-visible WordPress conversion path.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real package/project files before any Cabal solver/build command. If
the static audit is ready, record a non-mutating Cabal plan for
`test:test-pandoc`, `test:test-pandoc-lua-engine`, and
`benchmark:benchmark-pandoc`; keep Haskell runner and benchmark execution out
of this dependency audit slice.
