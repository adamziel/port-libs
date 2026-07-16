# upstream-suite-evidence-current-source-next149-156

This slice prepares the direct follow-on current-source upstream suite evidence
octet after merged next141-148. It admits only next149 through next156 phase rows,
requires lane-local note artifacts, current-source-only flags, zero runner errors,
fresh focused PASS-line admission, and keeps release/all parity unclaimed.

Countability guard: preserved next141-148 anchor rows remain preserved and do not
increase `mapped_delta`; already counted next149-156 rows are preserved without
mapped-count inflation.

Focused validation:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext149156Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext149156Test.php`

Non-overlap: avoids merged next141-148 suite evidence, previous upstream suite
evidence windows, exact-shard next148, veryquick-shard rows, accepted behavior
clusters, queued runner/jsonvt blockers, and release/all parity.
