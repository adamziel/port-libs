# real-upstream-corpus-json1-jsonb-dynamic-20260531T000847Z-0

Base accepted HEAD: `88eb6ac3e2ad25d5a4756e5a167672b605fd3e97`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- companion current-index insert behavior from `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`

Owned behavior:

- `json105-1.10` through `json105-1.110`: current-index extraction, including `[#]`, `[#-N]`, nested reverse indexes, and leading-zero reverse index tokens.
- `json105-2.10` through `json105-2.140`: left-to-right multi-path removal with forward and reverse indexes.
- `json105-3.10` through `json105-3.40`: append insertion through `[#]`, including repeated append pairs.
- `json105-4.10` through `json105-4.80` and `json105-5.10` through `json105-5.80`: `json_set` and `json_replace` reverse-slot mutation.
- `json105-6.10` through `json105-6.50`: malformed current-index path rejection.
- `json109-1.1` through `json109-1.9`: `json_array_insert` current-index companion behavior for before-first, append, reverse, and out-of-range paths.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson105CurrentIndexDynamicCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson105CurrentIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson105CurrentIndexDynamicCorpusTest.php`
  - `1 test files, 6803 assertions, 0 failures`
  - `1021` focused PASS lines.

Expected dashboard movement:

- `phpPass`: `1292330 -> 1293351` if accepted.
- mapped denominator: unchanged at `1589 / 1589`.

Non-overlap:

This slice deliberately avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/object/window work, JSON501/JSON502 escaped-path stress, JSON109 standalone array-insert broad tests, and existing JSONB remove-only coverage. The new corpus focuses on `json105.test` current-index behavior across text and JSONB sources with expected structures computed directly from the generated documents.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP JSON path, JSONB, mutation, remove, extract, and array-insert components.
