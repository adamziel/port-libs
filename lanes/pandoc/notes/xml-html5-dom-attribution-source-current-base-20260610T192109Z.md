# XML/HTML5 DOM Attribution Source Current-Base Slice

- Bead: `plib-q84vu` / `20260610T192109Z`
- Scope: Pandoc XML/HTML5 DOM sanitizer and WordPress handoff metadata.
- Change: `Html5DomFragment` converts HTML attribution reporting source attributes into inert `data-pandoc-attribution-src` reviewer metadata for safe `a` and `img` elements.
- Safety: unsafe endpoint tokens and unsupported element placements are stripped from serialized HTML and WordPress blocks while preserving diagnostics for reviewer queues.
- Verification: `php -l` for the touched source/test files; focused `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed `1` file / `2599` assertions / `0` failures; full `php tools/run-tests.php lanes/pandoc/tests` passed `44` files / `61092` assertions / `0` failures.
- External tools: no Pandoc, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.
