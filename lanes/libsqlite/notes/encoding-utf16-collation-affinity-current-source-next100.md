# encoding-utf16-collation-affinity-current-source-next100

- Behavior: adds `SQLiteUtf16CollationAffinitySourceSwitchPlan` for current/next copied `wp_options.option_value` source switches where UTF-16 bytes are decoded before SQLite affinity and collation comparisons. The plan reports source invalidation when rowsets, encodings, raw bytes, decoded values, coerced storage classes, or comparison-to-probe outcomes change across current/next sources.
- Application smoke: `examples/application-utf16-affinity-source-switch-current-source-next100.php` shows copied plugin option priority rows being reparsed after UTF-16LE/UTF-16BE source changes and numeric-affinity text fallback.
- Upstream/current-source mapping: covers a focused encoding/collation/affinity current-source cursor edge without repeating accepted next85 single-source cursor coverage, accepted malformed UTF-16 guard, accepted LIKE/GLOB source switching, or accepted Unicode GLOB range behavior.
- Dependency closure: no new support component is needed; this reuses the existing native UTF-16 decoder and affinity comparison helpers.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinitySourceSwitchCurrentSourceNext100Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
51 PASS lines
```

```text
php lanes/libsqlite/examples/application-utf16-affinity-source-switch-current-source-next100.php
JSON smoke output reports cursorInvalidated=true with source-name, text-encoding, value-bytes, decoded-value, coerced-storage, comparison-to-probe, and matched-rowset invalidation reasons.
```
