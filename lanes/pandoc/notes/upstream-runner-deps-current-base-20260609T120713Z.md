# Pandoc Upstream Runner Dependency Audit 2026-06-09 12:07 UTC

## Scope

This slice is an upstream-runner dependency audit, not a native conversion
implementation slice. It does not run Pandoc, Cabal builds, Haskell test
binaries, external office tools, TeX/PDF engines, ZIP tools, or online
conversion services.

## Current Base Evidence

- Worktree base: `329b990b1079e0c81d2c156d545b769dc66d69c3`.
- Lane manifest upstream commit remains
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- The previously recorded local cache path
  `/home/claude/port-libs/.upstream-cache/pandoc` is absent in this current
  environment. No local upstream checkout can be used for a safe runner
  invocation from this isolated lane.
- The current `.upstream-cache` directory contains other lane caches but no
  Pandoc checkout. This means the existing static inventory stays useful as
  denominator evidence, but it is not currently reproducible by running the
  upstream suite in place.

## Cabal Runner Closure

Static Cabal source checks at the manifest commit show two upstream runner
surfaces relevant to this lane:

- `pandoc.cabal` declares `test-suite test-pandoc` as an
  `exitcode-stdio-1.0` executable with `main-is: test-pandoc.hs`,
  `hs-source-dirs: test`, and Tasty modules spanning command fixtures,
  readers, writers, shared helpers, media bag, and XML tests.
- `test-pandoc` depends on the full `pandoc` package plus direct test deps:
  `Diff`, `Glob`, `bytestring`, `containers`, `directory`, `doctemplates`,
  `filepath`, `mtl`, `pandoc-types`, `process`, `tasty`, `tasty-golden`,
  `tasty-hunit`, `tasty-quickcheck`, `text`, `temporary`, `time`, `xml`, and
  `zip-archive`.
- The `pandoc` library closure is broad and includes document/package support
  libraries such as `aeson`, `attoparsec`, `citeproc`, `commonmark`,
  `crypton`, `doclayout`, `doctemplates`, `gridtables`, `ipynb`,
  `jira-wiki-markup`, `network-uri`, `skylighting`, `tagsoup`, `texmath`,
  `unicode-collation`, `unicode-data`, `unicode-transforms`, `yaml`,
  `libyaml`, `zip-archive`, `zlib`, `xml`, `xml-conduit`, `typst`, `djot`,
  and `asciidoc`; with the default project enabling `+embed_data_files +http`.
- `cabal.project` includes packages `.`, `pandoc-lua-engine`, `pandoc-server`,
  and `pandoc-cli`, plus source-repository-package pins for `doclayout`,
  `typst-symbols`, `typst-hs`, `texmath`, and `citeproc`.
- `pandoc-lua-engine/pandoc-lua-engine.cabal` declares
  `test-suite test-pandoc-lua-engine` as another `exitcode-stdio-1.0`
  executable. It depends on `pandoc-lua-engine`, `pandoc`, `pandoc-types`,
  `hslua`, `tasty`, `tasty-golden`, `tasty-hunit`, `tasty-lua`, and related
  Lua-engine support packages.

Local tool availability is partial: PHP 8.5.6, GHC 9.10.3, and Cabal 3.12.1.0
are on PATH, while `stack` is not. Tool availability alone is insufficient
because the upstream checkout is absent and the Cabal closure would require
hydrating the Pandoc repo, fetching pinned Git packages and Hackage packages,
then building both Haskell test executables.

## Decision

No safe local upstream runner step is available for this micro-slice. The lane
should keep the upstream status as static inventory rather than claiming runner
parity.

## Dependency Closure

No new native PHP support component is activated by this audit. The blocker is
upstream-runner infrastructure: a hydrated Pandoc checkout plus a pinned Cabal
store/build plan for `test-pandoc` and `test-pandoc-lua-engine`. Existing
Pandoc support-library rows for ZIP/OPC, XML/HTML, templates, YAML/JSON
metadata, CSL/BibTeX, TeX/math, DOCX, EPUB, ODT, CFB, syntax highlighting,
Unicode/charset, table geometry, archive streams, and PDF handoff remain the
proper implementation path for native conversion work.

## Verification

- `jq . lanes/pandoc/lane-status.json` passed.
- `jq . lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed:
  1 test file, 2,315 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.

Root harness status: not run - isolated micro-slice.
