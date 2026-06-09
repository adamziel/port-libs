# Pandoc Upstream Runner Dependency Audit 2026-06-09

## Scope

This slice is the explicit `pandoc-upstream-runner-deps-current-base` audit. It
does not implement new conversion behavior and does not shell out to Pandoc,
Cabal-built test binaries, Word, LibreOffice, zip/unzip, TeX/PDF engines,
online services, or external template engines.

## Current Base Evidence

- Accepted base for this isolated worktree: `329b990b1079e0c81d2c156d545b769dc66d69c3`.
- Lane manifest remains a static upstream inventory at Pandoc commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- Manifest denominator remains `2,276` inspected upstream test/data/benchmark
  files and artifacts, with `659` mapped Markdown/HTML/WordPress checks.
- Native PHP focused status remains `238` passing Pandoc checks and `0`
  failures; this audit adds no new PHP pass credit.

## Runner Dependency Closure

The full upstream runner is still not safely runnable from this isolated slice.
The lane-local inventory records the upstream runner shape as:

- `pandoc.cabal` test suite `test-pandoc`, implemented as the Haskell Tasty
  executable `test/test-pandoc.hs`;
- `pandoc-lua-engine` test coverage under `pandoc-lua-engine/test/`, with the
  Lua-engine suite requiring its own Haskell test executable;
- command/golden, HUnit, QuickCheck, reader/writer, Lua, data, and benchmark
  artifacts that require a hydrated upstream checkout and Cabal dependency
  build before runner parity can be claimed.

This host has `ghc 9.10.3` and `cabal 3.12.1.0` available, and `stack` is not
available. The previously recorded upstream cache path
`/home/claude/port-libs/.upstream-cache/pandoc` is absent in the current
worktree environment, and no `pandoc.cabal` file is present under
`/home/claude/port-libs` at the searched depth. Because hydrating the upstream
checkout and downloading/building the broad Pandoc dependency graph is outside
this bounded PHP lane slice, the honest runner state remains static inventory
plus focused PHP parity checks, not upstream-runner parity.

## Dependency Closure Note

No new native PHP support component is needed or activated by this audit. The
existing gated Pandoc support rows still cover DOCX/OpenXML package handling,
legacy DOC/CFB, EPUB, ODT/OpenDocument, PDF handoff, doctemplates, citations,
math, tables, ZIP/package primitives, XML/HTML, Unicode/charset, JSON/YAML
metadata, syntax highlighting, and archive/compression. None is a prerequisite
for this runner-dependency audit because the blocker is upstream checkout and
Cabal dependency hydration, not a missing PHP conversion support library.

## Verification

- `ghc --numeric-version` -> `9.10.3`
- `cabal --numeric-version` -> `3.12.1.0`
- `command -v stack` -> not found
- `find /home/claude/port-libs/.upstream-cache -maxdepth 2 -type d -iname '*pandoc*' -print` -> no rows
- `find /home/claude/port-libs -maxdepth 4 -type f -name 'pandoc.cabal' -print` -> no rows
- `jq empty lanes/pandoc/lane-status.json` -> passed
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` ->
  `1 test files, 2315 assertions, 0 failures`
- `git diff --check -- lanes/pandoc` -> passed

No PHP files or examples were changed, so PHP lint and example smoke checks are
not applicable for this audit-only slice.

## Next Task

Before claiming upstream runner parity, restore or hydrate a pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, capture a Cabal dry-run
or equivalent dependency-plan artifact without executing the Haskell test
binaries, and keep any broad dependency download/build step behind an explicit
supervisor-approved runner slice. Native PHP conversion work should continue to
map bounded Markdown/HTML writer/reader behavior using the existing static
inventory until that runner gate is opened.
