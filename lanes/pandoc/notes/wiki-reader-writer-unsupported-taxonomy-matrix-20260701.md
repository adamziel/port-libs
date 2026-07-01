# Wiki Reader/Writer Unsupported Taxonomy Matrix - 2026-07-01

- Added `PandocFormatRegistry::wikiUnsupportedTaxonomyMatrix()` and `wikiFormatReviewPacket()` to expose wiki-family input/output tokens, extension aliases, direction buckets, unsupported reader/writer reason payloads, and native implementation records.
- The matrix keeps extension inference unchanged (`.dokuwiki` and `.wiki` only), records empty native implementation records for unsupported wiki readers/writers, preserves the existing partial Jira reader record, and reports `externalToolFree=true` with direct reader/writer parity unsupported.
- This is explicit registry taxonomy evidence only; no wiki parser/writer, Pandoc subprocess, wiki converter, browser, Node tooling, online service, live provider, or external validator is invoked.
