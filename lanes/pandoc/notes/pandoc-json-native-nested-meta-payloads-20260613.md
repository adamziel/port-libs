# Pandoc JSON/native nested metadata payloads

Bead: `plib-15nnl`
Date: 2026-06-13 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` and `NativeWriter` now use reader-indexed
`metaConstructorProvenance` paths while recursively emitting metadata maps and
lists. When a `MetaMap` or `MetaList` container is edited, unchanged nested
`MetaString` and `MetaBool` native sidecars are preserved, including escaped
metadata keys such as `owner/team`, while edited values regenerate canonical
constructors and stale edited container sidecars are dropped.

This closes one bounded metadata round-trip edge. JSON/native remains partial:
broader upstream fixture parity, unsupported constructors, table edges,
citation edges, and additional metadata shape edges remain open.

## Evidence

- Baseline before this slice after final rebase: focused
  `PandocJsonNativeAstTest.php` passed with `1` file, `2227` assertions, `0`
  failures.
- Initial red regression failed because an unchanged nested `MetaString`
  sidecar under an edited metadata map regenerated without its inert
  `reviewQueue` provenance.
- Final focused run after rebase onto `2dc318a34`: `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed with `1` file, `2267` assertions, `0` failures.
- Full lane run after rebase onto `2dc318a34`: `php tools/run-tests.php lanes/pandoc/tests` passed with `46`
  files, `75640` assertions, `0` failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3356 -> 3357`; `phpFail` remains `0`.
- `mappedJsonNativeNestedMetaPayloadCases`: `1`.
- `jsonNativeNestedMetaPayloadAssertions`: `40`.
- Mapped upstream inventory counter: `3316 -> 3317`.
