# Consolidate Remaining Numbered Encoding Classes

Session: `port-dev-sqlite-yield-consol-encoding-b`
Timestamp: `2026-05-29T10:59:52Z`

This consolidation removes generated numeric helper and evidence identifiers from the canonical encoding current-source families touched by the lane: UTF-16 LIKE/GLOB/RTRIM/NOCASE helpers, malformed-byte affinity LIKE helpers, and related focused tests. Numeric production helper prefixes such as `next232_` are now stable worded variant identifiers such as `nextTwoThreeTwo_`, preserving behavior while avoiding generated numeric suffix names in production source.

Focused verification:

- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l`: all changed PHP files reported no syntax errors.
- `php tools/run-tests.php $(git diff --name-only -- lanes/libsqlite/tests | tr '\n' ' ')`: `121 test files, 9167 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.
- `rg -n "CurrentSourceNext[0-9]+|CurrentNext[0-9]+|Next[0-9]+|next[0-9]+|current-source-next[0-9]+" $(git diff --name-only -- lanes/libsqlite/src | tr '\n' ' ')`: no matches.

Dependency closure: no new support component is needed; this is a readable PHP source consolidation that reuses the existing encoding, collation, affinity, LIKE/GLOB, and current-source invalidation helpers.

Non-overlap: this patch does not add new functional coverage and does not touch WAL, B-tree, JSON, VFS, or SQL executor behavior. It is limited to encoding-family production names and direct focused tests.
