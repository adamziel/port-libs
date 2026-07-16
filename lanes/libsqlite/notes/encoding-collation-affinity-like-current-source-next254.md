# encoding-collation-affinity-like-current-source-next254

Status: focused PHP behavior growth for explicit SQL `ESCAPE NULL` in SQLite
`LIKE` predicates across a current-source to next-source cursor handoff.

The new plan distinguishes omitted `ESCAPE` from explicit SQL `NULL` escape.
Omitted escape runs normal LIKE matching, while `ESCAPE NULL` makes the
predicate UNKNOWN and keeps matched rowsets empty until the next source binds a
real one-character escape. This matters for copied Application `wp_options`
imports that rebind a prepared option-value scan during a source refresh.

Verification:

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Test.php`
- `php -l lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next254.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Test.php`
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next254.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused PHP PASS growth only. Mapped upstream
coverage remains unchanged because this reuses already mapped LIKE, ESCAPE,
text-affinity, and current-source behavior rather than adding a fresh upstream
manifest row.

Non-overlap: avoids accepted next251 prepared pattern storage transitions,
next250 RTRIM residual peers, next238 REAL text-affinity LIKE, next235
malformed-byte NOT LIKE complement, Unicode GLOB ranges, and UTF-16
NOCASE/RTRIM cursor handoffs. The new surface is explicit SQL NULL ESCAPE
nullability versus omitted ESCAPE.

Dependency closure: no new support component is needed. The slice reuses
native LIKE tokenization, scalar text affinity, SQL NULL predicate handling,
and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation,
affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
