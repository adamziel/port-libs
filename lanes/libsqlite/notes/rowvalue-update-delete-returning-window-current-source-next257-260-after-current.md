# rowvalue-update-delete-returning-window-current-source-next257-260-after-current

Status: focused after-current coverage for row-value `UPDATE`/`DELETE ... RETURNING`
window current-source next257-260.

This slice adds a combined WordPress smoke wrapper over accepted next257, next258,
next259, and next260 row-value RETURNING window candidates. It proves the four
prepared handoffs compose in order: DELETE RETURNING tombstone publication,
transition-token retry admission, current-row frame acknowledgement, and mixed
current-source to next-source boundary release.

WordPress path:
`wordpress-rowvalue-returning-window-current-source-next257-260-after-current.php`
models copied `wp_options` import retries and records the next257 publication
rowids, next258 held/admitted rows, next259 frame counts, and next260 boundary
self-test output.

Validation:

- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next257-260-after-current.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257260AfterCurrentTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next257-260-after-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257260AfterCurrentTest.php`
- `git diff --check`

Dependency closure: no new support component is needed; this reuses native
row-value UPDATE/DELETE RETURNING execution and the existing next257 tombstone
publication, next258 transition-token admission, next259 current-row frame, and
next260 boundary-release metadata.

Non-overlap: avoids changing the accepted individual next257, next258, next259,
and next260 behavior. The narrower surface is the after-current handoff wrapper
that validates those four prepared row-value current-source slices as one
ordered WordPress coverage batch.
