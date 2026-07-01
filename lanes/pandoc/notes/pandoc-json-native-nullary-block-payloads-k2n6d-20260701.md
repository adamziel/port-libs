# Pandoc JSON/native nullary block payload coverage

Slice: `plib-k2n6d`
Date: 2026-07-01

Current `origin/main` already contains the nullary block payload repair that
supersedes the stale `polecat/885/plib-k2n6d@mqc33a3j` branch. Reapplying that
branch would regress current JSON/native constructor work, so this follow-up
adds standalone focused coverage for the shipped behavior.

The focused test covers JSON-reader and native-reader inputs where `HorizontalRule`
and `Null` carry stale or empty `c` members at top level, inside `BlockQuote`, and
inside `MetaBlocks`. Readers retain the original payloads as review provenance,
while both JSON and Native writers regenerate current sidecar-free nullary block
constructors and drop stale wrapper sidecars.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, Node
tooling, online services, live providers, or external validators were invoked.
