# Consolidate Final Numbered Upstream-Suite Methods

Consolidated the final upstream veryquick shard production wrappers in
`SQLiteUpstreamSuiteEvidence` into the stable
`upstreamVeryquickShardCurrentSourceShard()` entrypoint. Direct tests for the
final single-shard methods and generated range tests now pass the shard id to
the stable method instead of calling generated `CurrentSourceNextNN` methods.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `git diff --name-only -- lanes/libsqlite/tests | xargs -r -n1 php -l`
- `php tools/run-tests.php $(git diff --name-only -- lanes/libsqlite/tests | tr '\n' ' ')`
  - `37 test files, 21847 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production
method-name consolidation over existing lane-local upstream-suite evidence
logic.
