2026-06-11 plib-dpflq

Scope: Pandoc JSON/native AST constructor completeness, under `lanes/pandoc` only.

Current-base blocker slice: JSON and native readers handled modern document
objects and legacy `[meta, blocks]` tuples, but did not accept a tagged top-level
`Pandoc` document constructor. Review packets using that constructor could not be
inspected through the shared AST without falling outside native PHP parsing.

Change: `PandocJsonReader` and `NativeReader` now normalize tagged top-level
`Pandoc` constructors into canonical document objects, preserve
`documentConstructor`/`documentNative` provenance on the AST, and keep writer
output canonical. The native reader accepts both legacy `unMeta` and tagged
`MetaMap` metadata inside that constructor.

Guardrail: the focused fixture uses only in-memory PHP arrays and JSON strings.
It does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Post-rebase verification on current main `282d4fe1`: `php -l` passed for the
touched source and test files; focused `PandocJsonNativeAstTest.php` passed 1
file, 1103 assertions, 0 failures; full `lanes/pandoc/tests` passed 44 files,
66126 assertions, 0 failures.
