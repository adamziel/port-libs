real-upstream-corpus-json1-jsonb-dynamic-20260530T230604Z-0

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- Ported scenarios: `json105-1.*` `[#]` / `[#-N]` extraction, `json105-2.*` removal order with moving tail indexes, `json105-3.*` insert append paths, `json105-4.*` set append/tail replacement paths, and `json105-5.*` replace append no-op/tail replacement paths.

Patch:
- Added `SQLiteJson105NegativeIndexDynamicCorpusTest.php`.
- The test keeps the literal upstream JSON105 cases and expands the same upstream `[#]`/`[#-N]` behavior over array lengths 1 through 120 for text JSON and JSONB inputs.
- Focused assertion growth: 1,384 assertions / 1,384 PASS lines.
- Non-overlap: this targets JSON105 negative array-index and append-slot mutation behavior, distinct from the existing JSON101/JSON102 dynamic extraction corpus, accepted JSON107 legacy BLOB behavior, JSON109 array-insert coverage, JSON table cursor/source/constraint work, and JSON visible/hidden constraint pushdown.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteJson105NegativeIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJson105NegativeIndexDynamicCorpusTest.php`

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP JSONB, JSON extraction, JSON removal, and JSON mutation components.
