# libsqlite Upstream Runner Evidence

## Focused Native Mapping: Bounded Runner Progress Audit

Date: 2026-05-27

This isolated release-suite blocker micro-slice adds
`SQLiteUpstreamSuiteEvidence::boundedRunnerProgressAudit()`. The helper
summarizes already parsed guarded bounded-runner artifacts without admitting
release/all parity: passed and failed artifacts keep parsed test/error totals,
active and timed-out artifacts keep `tcl(done/total)` progress, focused
artifacts remain separated from broad release-like artifacts, and incomplete
records remain explicit blockers.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change removes an opaque interrupted-run
blocker for release/all handoff review: integrators can now see whether a
guarded broad artifact is passed, failed, active, timed out, or incomplete
before deciding whether to wait, rerun, or route it through existing
countability gates.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteBoundedRunnerProgressAuditTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBoundedRunnerProgressAuditTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused PASS-line delta: 3796 to 3832, +36. The new focused test file reports
`1 test files, 151 assertions, 0 failures`.

Dependency closure: no new support component is needed. This composes existing
bounded-runner artifact records and supplied audit/stdout/process snapshot
parsing only; it does not inspect secrets or launch upstream runners.

## Focused Native Mapping: Focused Runner Artifact-Set Admission

Date: 2026-05-27

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::focusedRunnerArtifactSetAdmission()`. The helper
summarizes a batch of already parsed bounded-runner artifacts and counts only
accepted-HEAD, matching SQLite-manifest, zero-error artifacts that include
explicit selected `.test` patterns. Stale-head, wrong-manifest, failed, active,
invalid, and broad release/all artifacts remain blocked and visible. The record
deduplicates selected script names and deliberately keeps
`counts_as_release_parity` false so broad release/all closure still flows
through the existing release countability gates.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change removes a concrete countability gap
for selected-subset audit/log batches: integrators can publish focused Tcl
evidence from a mixed artifact set without accidentally admitting stale
artifacts or broad release/all parity.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteFocusedRunnerArtifactSetAdmissionTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteFocusedRunnerArtifactSetAdmissionTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php lanes/libsqlite/tests/SQLiteFocusedRunnerArtifactSetAdmissionTest.php
git diff --check -- lanes/libsqlite
```

Focused PASS-line delta: 2017 to 2043, +26. The new focused test file reported
`1 test files, 50 assertions, 0 failures`; the combined suite-evidence focused
run reported `2 test files, 862 assertions, 0 failures`.

Dependency closure: no new support component is needed. This composes existing
bounded-runner artifact records, focused artifact admission, accepted HEAD
provenance, and SQLite manifest UUID gates only.

## Focused Native Mapping: Bounded Runner SQLite Source Provenance Gate

Date: 2026-05-27

This isolated upstream-suite micro-slice tightens
`SQLiteUpstreamSuiteEvidence::boundedRunnerAcceptanceGate()` so guarded
bounded-runner artifacts must match the lane's SQLite upstream git commit and
SQLite `VERSION`, in addition to the accepted repository head and manifest
UUID, before they can become countable release/all or focused upstream-suite
evidence.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change removes a concrete countability gap
for stale hydrated SQLite-source artifacts: a zero-error audit/log pair from
the wrong SQLite checkout or wrong `VERSION` now stays blocked instead of
being admitted through manifest UUID alone.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 745 to 812 assertions, +67.

Dependency closure: no new support component is needed. This composes parsed
bounded-runner artifact fields with lane-local SQLite source provenance from
`UPSTREAM_TEST_MANIFEST.json` only.

## Focused Native Mapping: Accepted-HEAD Runner Artifact Provenance Batch

Date: 2026-05-27

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::acceptedHeadArtifactProvenanceBatch()`. The
helper classifies already parsed guarded bounded-runner artifacts against the
current accepted repository head and SQLite manifest UUID, then separates
current accepted-head evidence from stale-head and manifest-mismatched
artifacts. Focused artifacts and release-like artifacts are labeled separately,
and the batch deliberately keeps `counts_as_release_parity` false so release
closure still flows through the existing release countability gates.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change removes a concrete suite admission
blocker for mixed guarded-runner evidence batches: integrators can reject stale
or wrong-manifest artifacts before they inflate focused or release evidence.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 745 to 788 assertions, +43.

Dependency closure: no new support component is needed. This composes parsed
bounded-runner artifacts, accepted repository-head checks, SQLite manifest UUID
checks, and existing focused/release evidence routing only.

## Focused Native Mapping: JSON Table SELECT SQL Sources

Date: 2026-05-27

This isolated JSON table/window micro-slice maps one focused JSON table-valued
behavior row for parser-level `json_each()` / `json_tree()` sources in bounded
SELECT SQL text. The native implementation composes existing JSON table
planning with `SQLiteSelectSql` source parsing, so literal JSON/root arguments
produce virtual rows that can flow through WHERE predicates, joins, grouped
aggregates, ORDER BY, LIMIT, and projection dispatch.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1. No fresh upstream `testfixture`, `make test`, `mptest`, `all`,
or `release` run was started from this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-json-table.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-json-table.php
git diff --check -- lanes/libsqlite
```

Focused assertion delta after bounded replay on current a1baf4ac source: 6457 to 6525 assertions, +68 in this worktree.

Dependency closure: no new support component is needed. This slice reuses
lane-local `SQLiteJsonTablePlan`, JSON path/JSON5/JSONB parsing, SELECT
predicate, projection, join, grouped aggregate, and result-ordering helpers.

## Focused Native Mapping: Focused Tcl Artifact Admission

Date: 2026-05-27

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::focusedRunnerArtifactAdmission()`. The helper
composes existing bounded-runner artifact parsing and provenance gates, then
admits only zero-error Tcl artifacts that have explicit selected `.test`
patterns. Broad release/all artifacts without patterns are routed back to the
release countability gates, and every focused admission keeps
`counts_as_release_parity` false.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change removes a countability ambiguity for
hydrated focused subset artifacts: integrators can count a selected-script
artifact as focused evidence without accidentally closing release/all parity.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local guarded-runner audit parsing, selected-pattern checks, and existing
accepted-HEAD/manifest provenance gates only.

## Focused Native Mapping: SELECT Query Plan Wiring

Date: 2026-05-26

This isolated SQL execution/planner micro-slice adds bounded native SELECT
query-plan wiring over already decoded/copied rows. The new `SQLiteSelectQuery`
helper composes the accepted row-production primitives for FROM rows,
INNER/LEFT/CROSS/USING joins, residual WHERE predicates, SELECT projection
lists, DISTINCT, ORDER BY, LIMIT, and OFFSET. It is intentionally a bounded
execution primitive; it does not claim SQL text parsing, VDBE bytecode, storage
cursor planning, or a fresh upstream `testfixture` run.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationSelectQueryPlanScripts: 1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
started from this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-query-plan-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-query-plan-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 4201 to 4343 assertions, +142.

Dependency closure: no new support component is needed. This slice reuses
lane-local SELECT predicate, projection, result, join, scalar, and pure PHP
row-array helpers.

## Focused Native Mapping: Compound SELECT Row Composition

Date: 2026-05-26

This isolated priority SQL execution/planner micro-slice adds bounded native
compound SELECT row composition for `UNION`, `UNION ALL`, `INTERSECT`, and
`EXCEPT`. The new `SQLiteSelectCompound` helper composes already produced
result rows using SQLite-style duplicate keys over the first SELECT arm's
result columns, including SQL `NULL`, BLOB, text, numeric, and bool/int storage
classes. It can feed the accepted result helper for final `ORDER BY`, `LIMIT`,
and `OFFSET`.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationSelectCompoundScripts: 1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
started from this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectCompound.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-compound-select-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-compound-select-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 3871 to 3945 assertions, +74.

Dependency closure: no new support component is needed. This slice reuses
lane-local BLOB wrappers, SQL result ordering/limit helpers, and pure PHP
row arrays.

## Focused Native Mapping: SELECT Projection Scalar Expressions

Date: 2026-05-26

This isolated SQL execution/planner micro-slice adds bounded native SELECT
projection wiring for row-produced scalar expression columns. The new
`SQLiteSelectProjection` helper evaluates source-column references, literals,
aliases, scalar function calls, and nested scalar arguments over already
produced rows. It is intentionally a projection/result primitive; it does not
claim parser-level SELECT execution, joins, VDBE bytecode, or a fresh upstream
`testfixture` run.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationSelectProjectionScalarScripts: 1`. No
fresh upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run
was started from this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectProjection.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-projection-scalar-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-projection-scalar-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 3787 to 3828 assertions, +41.

Dependency closure: no new support component is needed. This slice reuses
lane-local core scalar dispatch, SQL result ordering, BLOB wrappers, and pure
PHP row arrays.

## Focused Native Mapping: SELECT DISTINCT/ORDER BY/LIMIT Results

Date: 2026-05-26

This isolated SQL execution/planner micro-slice adds a bounded native
result-row helper for SQLite SELECT output semantics after row production:
`DISTINCT`, multi-term `ORDER BY`, `LIMIT`, and `OFFSET`. The helper is
intentionally a result-semantics primitive; it does not claim parser-level
SELECT execution or a fresh upstream `testfixture` run.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedCoreSelectResultScripts: 1`. No fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run was started from
this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectResult.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-order-limit.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-order-limit.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 3410 to 3440 assertions, +30.

Dependency closure: no new support component is needed. This slice reuses
lane-local SQL sort-class comparison, BLOB wrappers, and pure PHP result-array
dispatch.

## Focused Native Mapping: Builtin Window Ranking and Value Helpers

Date: 2026-05-26

This isolated SQL execution/planner micro-slice adds bounded native helpers for
SQLite builtin window functions over already ordered result partitions:
`row_number()`, `rank()`, `dense_rank()`, `percent_rank()`, `cume_dist()`,
`ntile()`, `lag()`, `lead()`, `first_value()`, `last_value()`, and
`nth_value()`. The helper is intentionally a result-semantics primitive; it
does not claim parser-level `OVER (...)` planning or full SELECT execution.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedCoreWindowFunctionScripts: 1`. No fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run was started from
this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-window-option-rankings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-window-option-rankings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 3249 to 3276 assertions, +27.

Dependency closure: no new support component is needed. This slice reuses
lane-local SQL sort-class comparison, BLOB wrappers, and pure PHP result-array
dispatch.

## Focused Native Mapping: GLOB Reversed Bracket Ranges

Date: 2026-05-26

This isolated encoding/collation micro-slice fixes a bounded SQLite GLOB
character-class edge used by copied Application option-name scans. SQLite treats a
reversed bracket range such as `[z-a]` as matching the starting byte (`z`) while
not treating the range hyphen or end byte as literals. The native GLOB matcher
now mirrors that behavior for byte ranges while preserving existing literal
`]`, literal `-`, negated class, UTF-8 character, and embedded-NUL behavior.

Focused mapping:

- Upstream behavior: SQLite `GLOB` bracket class parsing for reversed byte
  ranges, literal `]`, and literal `-` cases from the pattern/collation family.
- Native assertions added: 10 focused assertions in
  `matches sqlite like and glob patterns for application option names`.
- Application smoke: `examples/application-option-name-like-glob.php --self-test`
  now reports `globReversedRangeOptions` for `plugin_[z-a]`, proving that
  copied `wp_options` scans include `plugin_z` and exclude `plugin_a` and
  `plugin_-`.

Verification run for this slice:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
```

Dependency closure: no new support component is needed. This slice reuses the
lane-local decoded text traversal and native SQLite LIKE/GLOB pattern matcher;
it counts no shared support-library progress.

## Focused Native Mapping: Full Freelist Trunk Page-Free Planning

Date: 2026-05-26

This isolated B-tree delete/rebalance micro-slice maps bounded SQLite
`freePage2` behavior for deletion/replacement paths where obsolete pages are
freed while the first freelist trunk is compatibility-full. Native planning now
has focused coverage for promoting the first newly freed page to the freelist
trunk head, linking the old trunk behind it, inserting later freed pages as
leaves, preserving allocation order, and reporting auto-vacuum pointer-map page
updates from `SQLiteFreelistFreePlan::toArray()`.

No fresh upstream `testfixture` run was started from this isolated worktree.
This slice reuses accepted B-tree/delete/freelist evidence for `delete.test`,
`delete2.test`, `delete3.test`, `delete4.test`, and `btree01.test`; broad
release/all runner evidence remains assigned to the upstream-suite lane.

Verification run for this slice:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 selected test file, 3046 assertions, 0 failures.

## Focused Native Mapping: Permutation Readiness Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice fixes
`SQLiteUpstreamSuiteEvidence::fullSuiteReadinessRecord()` so a hydrated,
parsed `test/permutations.test` suite map satisfies the `permutation-suites`
release-tier blocker. Before this gate, readiness could report
`permutation-suite-map` as ready while still carrying a duplicate
`permutation-suites` blocker, which kept full-suite command handoff
permanently blocked even after the permutation denominator was mapped.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The focused assertion builds a temporary
hydrated-cache fixture with 58 declared permutation suites and verifies the
duplicate blocker is absent while unrelated gates remain honest.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local readiness records, release-tier gates, and hydrated SQLite
permutation source parsing only.

## Focused Native Mapping: Bounded Runner Timeout Artifact Classification

Date: 2026-05-26

This isolated upstream-suite micro-slice strengthens
`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()` so guarded
release/all artifacts that exit with timeout code 124 and no `FAILED:` script
diagnostic are classified as `timed-out-incomplete`, not as failed SQLite
suite parity. The record preserves partial `tcl(N/M)` progress counters,
keeps `failure_blockers` empty, and leaves acceptance/countability blocked
until a supervisor-approved rerun produces parsed zero-error release/all
counts with accepted HEAD and SQLite manifest provenance.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice. The change only parses supplied bounded
audit/log text.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local guarded-runner audit/log parsing, progress counters, and existing
provenance/countability gates only.

## Focused Native Mapping: Core Date/Time Modifier Dispatch

Date: 2026-05-26

This isolated SQL execution/planner micro-slice extends bounded UTC date/time
modifier dispatch in `SQLiteCoreScalarFunction`. Native PHP now handles
`start of month`, `start of year`, signed month/year modifiers, and `weekday N`
forward scheduling for copied Application SQLite timestamp diagnostics. It keeps
the existing bounded scope: timezone/localtime, weekday names, and full SQLite
calendar ambiguity policies remain future focused work.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice; the isolated worktree did not contain a hydrated
SQLite upstream checkout.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php datetime '2026-05-26 16:12:34' 'start of month'
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion plus PHP `DateTimeImmutable` for bounded UTC
timestamp diagnostics.

## Focused Native Mapping: Core Date/Time Scalar Dispatch

Date: 2026-05-26

This isolated dependency-suite micro-slice adds bounded UTC
`date()`, `time()`, `datetime()`, `julianday()`, `unixepoch()`, and
`strftime()` dispatch to `SQLiteCoreScalarFunction`. The native helper covers
explicit ISO date/time inputs, numeric Julian day inputs, `unixepoch` input,
`start of day`, and signed day/hour/minute/second modifiers. Unsupported
timezone/localtime/weekday/month/year modifiers remain future focused work.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release`
run was started by this slice; the isolated worktree did not contain a hydrated
SQLite upstream checkout.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php datetime '2026-05-26 16:12:34' '+1 day' 'start of day'
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion plus PHP `DateTimeImmutable` for bounded UTC
timestamp diagnostics.

## Focused Native Mapping: Persistent Release Manifest Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice tightens
`SQLiteUpstreamSuiteEvidence::persistentReleaseRuntimeBlockerGate()`. Repeated
fts5aux sanitizer failures can now become persistent upstream-runtime blocker
evidence only when each failed artifact is a broad `all` or `release` runner
from the lane's expected SQLite manifest UUID. Focused repro records,
`veryquick` artifacts, and mismatched-manifest release artifacts remain blocked
evidence and cannot count as release/all parity.

No broad upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run
was started by this slice.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes existing
lane-local release artifacts, focused repro evidence, and SQLite manifest
provenance only.

## Focused Native Mapping: Persistent Release Runtime Blocker Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::persistentReleaseRuntimeBlockerGate()`. The
helper composes existing bounded release-runner artifact records with the
accepted focused repro gate and classifies the fts5aux sanitizer blocker as
persistent only when at least two guarded release artifacts repeat the same
upstream runtime/environment failure and the exact focused repro has zero
errors.

The gate keeps `counts_as_release_parity` false. It is blocker/exclusion
evidence for supervisor and integrator review, not a substitute for a zero-error
SQLite `release` or `all` artifact. No broad upstream `testfixture`,
`make test`, `mptest`, `all`, or `release` run was started by this slice.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes existing
lane-local release artifacts and focused repro evidence only.

## Focused Native Mapping: Bounded Runner Stdout Summary Recovery

Date: 2026-05-26

This isolated upstream-suite micro-slice strengthens
`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()` so completed
guarded runner artifacts can recover pass/fail counts from the runner log when
the audit file has `Parsed tests: unknown` and `Parsed errors: unknown`.
Final stdout lines such as `0 errors out of 22000 tests` now produce a passed
artifact record when the guarded exit code is zero, moving the evidence to the
existing accepted-HEAD and SQLite manifest provenance gates instead of leaving
it as incomplete.

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started by this slice. The change only parses supplied
bounded audit/log text.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
guarded-runner audit/log parsing and existing provenance/countability gates
only.

## Focused Native Mapping: Core iif()/if() Conditional Scalar Dispatch

This isolated SQL execution/planner micro-slice adds bounded `iif()` and
`if()` dispatch to `SQLiteCoreScalarFunction`. Native PHP now scans
condition/value pairs using SQLite numeric truthiness, returns the first value
whose condition is true, returns the optional odd trailing fallback when no
condition matches, and returns SQL NULL when an even-arity form has no match.

This is a scalar dispatch helper over already-supplied arguments, not a full
VDBE expression engine, so it does not claim lazy branch evaluation.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core conditional scalar evidence row while preserving the
current accepted static SQLite upstream denominator and runner evidence. This
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T14:54Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php iif 0 network 1 site fallback
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion and existing expression-semantics dispatch without
activating shared support-library work.

## Focused Native Mapping: Make-Test Duplicate Runner Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice extends
`SQLiteUpstreamSuiteEvidence::activeFullSuiteRunnerGate()` so process snapshots
for `make -C <build> test` and `make -C <build> mptest` are treated as active
broad SQLite suite runners. These commands are release-tier commands produced by
the lane's own command manifest, so duplicate-runner protection now covers the
make-driven tiers as well as guarded wrapper and direct `testfixture`
`all`/`release`/`mptest` commands.

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. The evidence is a supplied static process snapshot
only; any real broad runner artifact still has to pass the accepted bounded
artifact provenance/countability gates before it can count toward release/all
parity.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local command readiness and supplied active-runner snapshots only.

## Focused Native Mapping: Broad Suite Launch Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::broadSuiteLaunchGate()`. The helper composes the
full-suite command manifest, explicit supervisor approval, and a supplied
process snapshot so a broad `all`, `release`, `mptest`, or `make test` attempt
is blocked with exact evidence when another guarded runner is active.

The current process evidence showed an active guarded release runner:

```text
libsqlite-release-rerun-20260526T131549Z release 2 7200
./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
```

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. The next gate is to wait for the active guarded
artifact/log and count it only through the accepted bounded artifact provenance
gates; another broad launch remains blocked until supervisor approval,
duplicate-runner, and command-manifest gates are all clear.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local command readiness and supplied active-runner snapshots only.

## Focused Native Mapping: Release Rerun Decision Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::releaseRerunDecisionGate()`. The helper composes
the failed guarded release artifact with the passed accepted-HEAD focused
`fts5aux` repro and keeps the result machine-readable:

```text
release artifact: libsqlite-release-notty-runner-20260526T102446Z
failed script: ext/fts5/test/fts5aux.test
failed case: fts5aux-3.1
focused repro: sqlite-fts5aux-repro-20260526T123916Z
focused result: 0 errors out of 1 tests
decision status without approval: blocked-pending-supervisor-decision
counts as release parity: false
```

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. A future broad rerun is allowed only after an
explicit supervisor sanitizer/transient-failure decision and clear
duplicate-runner gates, and it must still pass bounded artifact provenance
before it can count as release/all evidence.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes
lane-local failed-release artifact parsing, focused-repro parsing, and
provenance/duplicate-runner gates only.

## Focused Native Mapping: Focused Repro File Decision

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::focusedFailureReproGateFromFiles()`. The helper
loads a bounded focused repro audit/log artifact from explicit paths, preserves
missing files as blocked evidence, then delegates to the accepted HEAD and
SQLite manifest provenance gate before any focused repro decision is recorded.

The completed focused repro artifact is:

```text
audit: /home/claude/port-libs/audits/sqlite-fts5aux-repro-20260526T123916Z.md
log: /home/claude/port-libs/.tmux-team/logs/sqlite-fts5aux-repro-20260526T123916Z.log
repository HEAD: 8ab0375ac9e72382750dc8fb8f4b96a2913e777a
script: ext/fts5/test/fts5aux.test
result: 0 errors out of 1 tests
```

This does not count as release/all parity. It only changes the next runner
decision from "run or parse the focused repro" to "make a supervisor-approved
sanitizer/transient-failure decision before another guarded release/all attempt
is counted."

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'require "lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php"; $e=PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence::fromManifestPath("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"); $g=$e->focusedFailureReproGateFromFiles(["category"=>"upstream-runtime-environment","script"=>"ext/fts5/test/fts5aux.test","case"=>"fts5aux-3.1"], "8ab0375ac9e72382750dc8fb8f4b96a2913e777a", "/home/claude/port-libs/audits/sqlite-fts5aux-repro-20260526T123916Z.md", "/home/claude/port-libs/.tmux-team/logs/sqlite-fts5aux-repro-20260526T123916Z.log", "/home/claude/port-libs"); echo $g["status"]." tests=".$g["artifact"]["results"]["tests"]." errors=".$g["artifact"]["results"]["errors"]."\n";'
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
guarded audit/log artifact parsing, accepted-HEAD provenance, and SQLite
manifest UUID checks only.

## Focused Native Mapping: Failed-Script Repro Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::focusedFailureReproGate()`. The helper turns a
parsed guarded-runner failure blocker into an exact focused repro plan, then
requires accepted repository HEAD provenance, SQLite manifest UUID provenance,
and the same failed script/case before the result can inform a release/all
rerun decision.

For the current release-runner blocker, the focused script is:

```text
ext/fts5/test/fts5aux.test
case: fts5aux-3.1
category: upstream-runtime-environment
```

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. No accepted focused repro artifact is claimed by
this slice; matching focused sanitizer artifacts stay blocked as upstream
runtime/environment evidence and do not count as release/all parity.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This composes existing
lane-local runner artifact parsing, provenance checks, and selected-script
planning only.

## Focused Native Mapping: Runner Failure Blocker Classification

Date: 2026-05-26

This isolated upstream-suite micro-slice adds machine-readable blocker
classification for parsed guarded-runner failures. `boundedRunnerArtifactRecord()`
now includes `results.failure_blockers[]`, so the completed
`libsqlite-release-notty-runner-20260526T102446Z` artifact is not just a failed
release record; its `ext/fts5/test/fts5aux.test` `fts5aux-3.1`
UndefinedBehaviorSanitizer diagnostic is explicitly categorized as
`upstream-runtime-environment` / `upstream-runtime-sanitizer`.

The artifact remains failed and uncounted for release/all parity. The next gate
is a supervisor-approved sanitizer decision or a focused `fts5aux` repro record
from the accepted integration HEAD before another broad release/all run is
counted.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused upstream-runner blocker-classification evidence row while
preserving the accepted static SQLite denominator and veryquick evidence. No
fresh broad upstream runner was started by this isolated slice.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest` passed
with 1 selected test file, 429 assertions, and 0 failures; manifest/status JSON
validation passed; lane diff check passed.

Dependency closure: no new support component is needed. This reuses lane-local
guarded-runner artifact parsing only; it does not activate shared process,
sanitizer, Tcl, or filesystem support.

## Focused Native Mapping: Guarded Release Failure Diagnostics

Date: 2026-05-26

This isolated upstream-suite micro-slice adds bounded failure-block parsing to
`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()`. Guarded runner
artifacts that contain `FAILED:`, `OUTPUT:`, and sanitizer `SUMMARY:` lines now
retain exact failed script diagnostics in the artifact result record instead of
remaining an exit-1 artifact with only unknown parsed totals.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. The prior guarded release runner has completed:

```text
libsqlite-release-notty-runner-20260526T102446Z complete
exit=1
Parsed summary: unknown
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
case: fts5aux-3.1
diagnostic: UndefinedBehaviorSanitizer applying non-zero offset 1 to a null pointer
source: ext/fts5/fts5_tcl.c:429:59
```

The artifact remains failed/blocked evidence and is not countable release
parity. The next gate is either a supervisor-approved rerun without the
sanitizer environment, or a focused `fts5aux` repro record that explains
whether this is an upstream test-environment blocker before another broad
release/all attempt.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest metadata and guarded audit/log artifacts only; it performs
no upstream runner shell-out and counts no shared support-library progress.

## Focused Native Mapping: Bounded Runner Countability Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::boundedRunnerCountabilityGateFromFiles()`. The
helper composes guarded bounded-runner audit/log parsing, active-runner snapshot
evidence, accepted repository HEAD provenance, and SQLite manifest UUID
matching into one `countable` or `blocked` record. It prevents an active or
incomplete release/all artifact from being counted just because its audit file
exists.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. The process snapshot still showed the active guarded
release runner:

```text
577248       1       11:31 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-notty-runner-20260526T102446Z audits/sqlite-release-notty-runner-20260526T102446Z.md .tmux-team/tmp/sqlite-release-notty-runner-20260526T102446Z .tmux-team/logs/sqlite-release-notty-runner-20260526T102446Z.log release 2 7200
577296  577248       11:30 timeout 7200 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
577297  577296       11:30 testfixture ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
```

The next acceptance gate is to wait for the guarded audit/log to contain parsed
zero-error pass evidence, then run the countability gate against the accepted
integration HEAD and the manifest SQLite UUID before updating suite evidence.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest metadata, guarded audit/log artifacts, and supplied process
snapshots only; it performs no upstream runner shell-out and counts no shared
support-library progress.

## Focused Native Mapping: Active Runner PID/PPID Snapshot Parsing

Date: 2026-05-26

This isolated upstream-suite micro-slice tightens
`SQLiteUpstreamSuiteEvidence::activeFullSuiteRunnerGate()` so it parses both
older `pid elapsed command` snapshots and the prompt-relevant
`pid ppid elapsed command` shape emitted by `ps -eo pid,ppid,etime,command`.
Without this, the duplicate-runner gate could shift the parent PID into the
elapsed field and keep an imprecise command for active `all`/`release`
evidence.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, `mptest`, `all`, or
`release` run was started. The process snapshot showed the active guarded
release runner:

```text
577248       1       02:16 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-notty-runner-20260526T102446Z audits/sqlite-release-notty-runner-20260526T102446Z.md .tmux-team/tmp/sqlite-release-notty-runner-20260526T102446Z .tmux-team/logs/sqlite-release-notty-runner-20260526T102446Z.log release 2 7200
577296  577248       02:14 timeout 7200 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
577297  577296       02:14 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
```

The lane-local gate now reports `blocked-active-runner`, tier `release`, PID
`577248`, elapsed `02:16`, and the wrapper command. The next acceptance gate is
to wait for `/home/claude/port-libs/audits/sqlite-release-notty-runner-20260526T102446Z.md`
and `/home/claude/port-libs/.tmux-team/logs/sqlite-release-notty-runner-20260526T102446Z.log`
to contain parsed pass/fail counts, then run the bounded-runner artifact and
provenance gates against the accepted integration HEAD and SQLite manifest UUID.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice parses a
supplied process snapshot only; it does not inspect secrets, mutate the shared
checkout, or execute upstream tests.

## Focused Native Mapping: Selected Script Inventory

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::selectedScriptInventory()`. The helper composes
accepted concrete `.test` selections with wildcard-expansion evidence, resolves
them against a supplied hydrated `.upstream-cache/libsqlite/test` directory, and
keeps missing cache or missing script files as machine-readable blockers before
full `all`/`release` handoff.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, or `mptest` run was started.
No active broad SQLite runner was visible in the local process snapshot at the
start of this slice, but this work stayed in the assigned evidence/runner
mapping scope rather than launching a fresh broad run. The shared upstream cache
is readable for integrator-side source resolution; lane tests use temp
hydration fixtures and missing-cache fixtures. Prior applicable runner evidence
remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0
errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'require "lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php"; $e=PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence::fromManifestPath("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"); $i=$e->selectedScriptInventory("/home/claude/port-libs"); echo $i["status"]." resolved=".$i["resolved_script_count"]." missing=".$i["missing_script_count"]." wildcard=".$i["wildcard_status"]."\n";'
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

The read-only shared upstream cache smoke reported:

```text
ready resolved=74 missing=0 wildcard=ready
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest evidence plus hydrated SQLite test-source paths only; it
performs no upstream runner shell-out and counts no shared support-library
progress.

## Focused Native Mapping: Bounded Runner Acceptance Provenance Gate

Date: 2026-05-26

This isolated dependency-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::boundedRunnerAcceptanceGate()`. The helper takes a
parsed bounded-runner artifact record and blocks it from counting as accepted
lane evidence unless it has parsed zero-error pass evidence, the artifact
repository HEAD matches the accepted integration HEAD supplied by the
integrator, and the artifact SQLite manifest UUID matches the lane manifest
upstream UUID.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, or `mptest` run was started
from this isolated worktree. This slice only adds the provenance gate that must
be applied after `boundedRunnerArtifactRecordFromFiles()` parses a guarded
audit/log pair. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest metadata and parsed bounded-runner artifact records only;
it performs no upstream runner shell-out and counts no shared support-library
progress.

## Focused Native Mapping: Hydrated Permutation Suite Source Map

Date: 2026-05-26

This isolated upstream-suite micro-slice tightens
`SQLiteUpstreamSuiteEvidence::permutationSuiteMap()` so it parses SQLite's
actual quoted `test_suite "name"` declarations in `test/permutations.test`,
including the dynamic `pcache${discard_rate}` source declaration. The previous
parser only recognized unquoted names, so it kept a hydrated permutation source
as partial/empty even when the upstream file was available.

Focused upstream runner:

No duplicate broad upstream `testfixture`, `make test`, or `mptest` run was
started. Process evidence still showed the shared bounded `all` runner active:

```text
scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-all-runner-20260526T083945Z ... all 2 5400
./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
```

Hydrated source-map evidence from the shared cache:

```text
/home/claude/port-libs/.upstream-cache/libsqlite/test/permutations.test
permutationSuiteMap('/home/claude/port-libs') => status ready, mapped 60, declared 58, unmapped 0
wildcardExpansionPlan('/home/claude/port-libs') => status ready, expanded 6 wildcard patterns to 24 concrete .test scripts
```

The mapped count is intentionally treated as `>= declared` because the upstream
source includes conditional/dynamic declarations; this is a source-map gate, not
fresh release/all pass evidence. The next acceptance gate remains explicit
per-suite testfixture run records with parsed pass/fail counts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest evidence and the read-only hydrated SQLite upstream cache
only; it performs no upstream runner shell-out and counts no shared
support-library progress.

## Focused Native Mapping: Bounded Runner Artifact File Record

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecordFromFiles()`. The
helper reads guarded bounded-runner audit/log artifacts from explicit paths,
keeps missing audit/log files as `blocked-missing-artifact-files`, and delegates
ready artifacts to the existing parsed pass/fail/in-progress record. This gives
the integrator a direct handoff path for artifacts like:

```text
/home/claude/port-libs/audits/sqlite-full-suite-all-runner-20260526T083945Z.md
/home/claude/port-libs/.tmux-team/logs/sqlite-full-suite-all-runner-20260526T083945Z.log
```

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, or `mptest` run was started
from this isolated worktree. Process evidence still showed the shared bounded
`all` runner active:

```text
scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-all-runner-20260526T083945Z ... all 2 5400
./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
```

The shared runner audit existed and recorded repository head
`5daeeb21a5c773aa5ab600e19580a47fafe28202`, SQLite commit
`8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`, manifest UUID
`9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`, testset
`all`, jobs `2`, and timeout `5400`; the log did not yet contain parsed
pass/fail counts during this slice. Prior accepted evidence remains the
complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest evidence and bounded-runner audit/log files only; it
performs no upstream runner shell-out and counts no shared support-library
progress.

## Focused Native Mapping: Bounded Runner Artifact Record

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()`. The helper parses
a guarded SQLite bounded-runner audit note plus stdout/progress text into a
machine-readable artifact record: label, repository HEAD, SQLite commit/version
and manifest UUID, requested testset/jobs/timeout/patterns, parsed exit/test
and error counts, last Tcl progress line, active-runner gate, and
pass/fail/in-progress status.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, or `mptest` run was started
from this isolated worktree. Process evidence already showed an active shared
bounded all runner:

```text
scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-all-runner-20260526T083945Z ... all 2 5400
./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
```

The new record helper is intended for the integrator to parse that guarded
artifact after it exits. Incomplete or active artifacts remain
`active-runner-in-progress`/`running-or-incomplete` and are not counted as full
all/release parity. Prior applicable runner evidence remains the complete
SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest evidence and supplied bounded-runner audit/stdout text
only; it performs no upstream runner shell-out and counts no shared
support-library progress.

## Focused Native Mapping: Active Broad-Runner Gate

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::activeFullSuiteRunnerGate()`. The helper parses a
supplied process snapshot into a machine-readable duplicate-runner gate for
broad SQLite `all`, `release`, `make test`, and `mptest` work. It records
`clear` when no broad runner is present and `blocked-active-runner` with PID,
elapsed time, suite tier, command, and next gate when one is already running.

Focused upstream runner:

No new broad upstream `testfixture`, `make test`, or `mptest` run was started
from this isolated worktree. Process evidence already showed an active shared
bounded all runner:

```text
scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-all-runner-20260526T080745Z ... all 2 1800
./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors. The active all-runner artifact/log
should be parsed after it exits if it maps to the accepted checkout.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses a
supplied process snapshot and lane-local upstream-suite evidence only; it
performs no upstream runner shell-out and counts no shared support-library
progress.

## Focused Native Mapping: Recorded Runner Result Ledger

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::recordedRunnerResultLedger()`. The helper
normalizes accepted upstream runner result history into machine-readable
passed, failed, and not-counted entries, including SQLite's singular
`Tcl script` result wording. The upstream-suite acceptance checklist now
includes recorded runner entry, pass/fail, and parsed test totals alongside
the focused-result ledger.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout or configured
`.upstream-cache/libsqlite-build-port-libsqlite` build directory, so no new
upstream `testfixture`, `make test`, `mptest`, or release/all runner was
started. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest runner evidence only; it performs no shell-out and counts
no shared support-library progress.

## Focused Native Mapping: Full-Suite Readiness Record

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::fullSuiteReadinessRecord()`. The helper composes
the accepted zero-error `veryquick` baseline, focused-result ledger, closure
blocker ids, release-tier matrix, wildcard expansion gate, and permutation
suite-map gate into one machine-readable integrator handoff record. It reports
which full-suite gates are accepted, ready, or blocked, includes exact ready
commands when a local harness tree is hydrated, and keeps missing cache/build
inputs explicit instead of claiming fresh upstream execution.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, configured
`.upstream-cache/libsqlite-build-port-libsqlite` build directory,
`testfixture`, `Makefile`, `mptest` directory, or
`test/permutations.test` source, so no new upstream `testfixture`,
`make test`, `mptest`, or release/all runner was started. Prior applicable
runner evidence remains the complete SQLite `veryquick` run: 1235 scripts,
329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses
lane-local manifest runner evidence, release-tier planning, wildcard expansion
planning, and hydrated SQLite harness readiness checks only; it performs no
shell-out and counts no shared support-library progress.

## Focused Native Mapping: Permutation Suite Map

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::permutationSuiteMap()`. The helper turns the
previous opaque `58` declared permutation-suite denominator into an explicit
map gate: when `.upstream-cache/libsqlite/test/permutations.test` is absent it
records a blocked missing-source result, and when that upstream source is
hydrated it parses concrete suite names before any release/all permutation
coverage can be counted.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite/test/permutations.test` source, so no new upstream
`testfixture`, `make test`, `mptest`, or release/all runner was started. Prior
applicable runner evidence remains the complete SQLite `veryquick` run: 1235
scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local manifest inventory and the hydrated SQLite Tcl harness source when
available; it performs no shell-out and counts no shared support-library
progress.

## Focused Native Mapping: REGEXP-Style Option Name Matching

Date: 2026-05-26

This isolated encoding/collation micro-slice adds bounded SQLite
REGEXP-style matching for decoded `wp_options.option_name` text.
`SQLiteDatabase::regexpMatches()` dispatches to a caller-supplied
application callback, matching SQLite's operator shape where REGEXP semantics
are application-defined. `optionRowsByNameRegexp()` scans all decoded
option rows without the convenience 100-row limit unless an explicit limit is
provided.

Focused upstream runner:

No new upstream `testfixture` run was started from this isolated worktree.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors. The manifest mapped count increases
by 1 for the bounded `focusedRegexpOptionNameScripts` evidence.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-option-name-like-glob.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 file, 2496
assertions, and 0 failures; the Application pattern smoke reported
`regexpOptions` including the late rowid 105 transient fixture; manifest/status
JSON validation passed; lane diff check passed.

Dependency closure: no new shared support component is needed. The slice keeps
REGEXP application-defined and lane-local by requiring a PHP callback, while
reusing existing decoded row traversal and LIKE/GLOB smoke fixture coverage.

## Focused Native Mapping: Release Tier Matrix

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::releaseTierMatrix()`. The helper turns the
remaining SQLite release/all closure tiers into a machine-readable matrix for
`release-all`, declared `permutation-suites`, `make-test`, and `mptest` stress
coverage. Each tier records its known local command, inventory-unit count,
runnable flag, accepted/ready/blocked status, missing cache/build inputs, and
next hydration gate; declared permutation suites stay blocked until concrete
upstream suite commands are mapped instead of inventing a runner mode. It does
not claim fresh upstream execution.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout or configured
`.upstream-cache/libsqlite-build-port-libsqlite` build directory, so no new
upstream `testfixture`, `make test`, or `mptest` run was started. Prior
applicable runner evidence remains the complete SQLite `veryquick` run: 1235
scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local manifest inventory, accepted runner metadata, and SQLite
testfixture/make command planning; it performs no shell-out and counts no
shared support-library progress.

## Focused Native Mapping: Embedded NUL Encoding and Pattern Text

Date: 2026-05-26

This isolated encoding/collation micro-slice adds focused native evidence for
SQLite text values containing U+0000. `SQLiteRecord` already stores SQLite text
as length-delimited fields rather than C strings; the new assertions guard that
UTF-16LE and UTF-16BE record round-trips preserve embedded NUL codepoints, and
that LIKE/GLOB single-character wildcards match the NUL as a character rather
than truncating the value.

Focused upstream runner:

No new upstream `testfixture` run was started from this isolated worktree.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors. The manifest mapped count increases
by 1 for the bounded `focusedUtf16EmbeddedNulTextScripts` evidence.

Native PHP evidence:

```sh
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php -l lanes/libsqlite/src/SQLiteRecord.php
php -l lanes/libsqlite/src/SQLiteDatabase.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 file, 2477
assertions, and 0 failures; the Application UTF-16 smoke reported
`embeddedNulTextRoundTrip=true`; manifest/status JSON validation passed; lane
diff check passed.

Dependency closure: no new shared support component is needed. The slice reuses
existing lane-local record encoding/decoding, UTF-16 native fallback
validation, decoded text pattern splitting, and ASCII LIKE/GLOB helpers.

## Focused Native Mapping: Native UTF-16 Record Conversion Fallback

Date: 2026-05-26

This isolated dependency-suite micro-slice closes a lane-local runtime
dependency gap for UTF-16 SQLite database images. `SQLiteRecord` now keeps
using `mb_convert_encoding()` when it is available, but falls back to native PHP
UTF-16LE/UTF-16BE conversion with surrogate-pair validation when mbstring is
absent. Existing malformed UTF-16 rejection remains active without depending
only on `mb_check_encoding()`.

Focused upstream runner:

No new upstream `testfixture` run was started from this isolated worktree.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors. The manifest mapped count increases
by 1 for the bounded `focusedUtf16NativeFallbackScripts` evidence.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteRecord.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. The slice
removes the hard mbstring activation gate for UTF-16 record conversion by
keeping conversion and validation lane-local, while still using mbstring when
the host provides it.

## Focused Native Mapping: Wildcard Expansion Plan

Date: 2026-05-26

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::wildcardExpansionPlan()`. The helper audits the
accepted runner commands for wildcard `.test` selections such as `btree*.test`
and `pager*.test`, expands them to concrete script filenames only when
`.upstream-cache/libsqlite/test` is hydrated, and otherwise reports the
missing-cache blocker and next gate without counting fresh upstream evidence.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite/test` directory, so no new upstream `testfixture` run
was started and no wildcard expansion was counted. Prior applicable runner
evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670
tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 file, 165
assertions, and 0 failures; manifest/status JSON validation passed; lane diff
check passed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader and accepted runner command evidence; it
performs no shell-out and counts no shared support-library progress.

## Focused Native Mapping: JSON Table Residual Filter Execution

Date: 2026-05-26

This isolated sql-exec/planner micro-slice adds bounded residual predicate
execution for planned `json_each()` and `json_tree()` table-valued scans.
`SQLiteJsonTablePlan::filteredRows()` preserves accepted hidden `json`/`root`
constraint planning, then filters native visible-column rows for `=`, `!=`,
`<>`, `IS`, and `IS NOT` residual constraints. This is a focused query-result
semantics step, not a full virtual-table cursor, join-order, or WHERE
expression executor.

Focused upstream runner:

No new upstream `testfixture` run was started from this isolated worktree.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors. The focused native assertions map the
bounded JSON table planner/execution behavior locally.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 file, 2450
assertions, and 0 failures; the updated Application smoke emitted filtered object
rule rows for strict JSON, JSON5, and JSONB inputs; manifest/status JSON
validation passed; lane diff check passed.

Dependency closure: no new support component is needed. The slice reuses
lane-local JSON table row assembly, hidden-column planning, JSONB wrappers, and
scalar comparison semantics; it performs no shell-out and counts no shared
support-library progress.

## Focused Native Mapping: Upstream Suite Execution Plan

Date: 2026-05-26

This isolated upstream-suite micro-slice adds a machine-readable next-run plan
over the accepted SQLite runner evidence.
`SQLiteUpstreamSuiteEvidence::upstreamSuiteExecutionPlan()` marks the accepted
zero-error `veryquick` baseline separately from the remaining focused reruns,
wildcard `.test` expansion, and full release/all gate. The default focused
groups cover JSON table/window, WAL/rollback/savepoint, B-tree
delete/rebalance, and encoding/collation closure clusters.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout or built `testfixture`, so no new upstream
runner was started. The execution plan reports those focused groups as
`blocked-missing-cache` and records the cache/testfixture hydration gate instead
of counting a skipped run as fresh evidence. Prior applicable runner evidence
remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0
errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 file, 143
assertions, and 0 failures; manifest/status JSON validation passed; lane diff
check passed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader, accepted upstream runner evidence, and
testfixture command planner; it performs no shell-out and counts no shared
support-library progress.

## Focused Native Mapping: Suite Closure Gap Report

Date: 2026-05-26

This isolated upstream-suite micro-slice adds a machine-readable closure gap
report over the accepted SQLite runner evidence.
`SQLiteUpstreamSuiteEvidence::suiteClosureGapReport()` combines the denominator,
zero-error `veryquick` result, focused-result ledger, selected `.test` scripts,
wildcard patterns, and remaining suite tiers into explicit blocker records for
the next acceptance gate. It does not claim new upstream execution; it makes the
remaining full release/all run, reused/skipped focused evidence, and wildcard
expansion gaps auditable without prose interpretation.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; manifest/status JSON validation passed; focused
lane tests passed with 1 file, 118 assertions, and 0 failures; lane diff check
passed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader and runner-evidence helpers, performs no
shell-out, and counts no shared support-library progress.

## Focused Native Mapping: Upstream Suite Acceptance Checklist

Date: 2026-05-26

This isolated upstream-suite micro-slice adds a compact machine-readable
acceptance checklist over the existing SQLite runner evidence.
`SQLiteUpstreamSuiteEvidence::upstreamSuiteAcceptanceChecklist()` combines the
static denominator, inventory-unit total, accepted zero-error `veryquick`
result, focused-result ledger counts, selected `.test` script counts, wildcard
pattern counts, and the remaining unexecuted release/all tiers into one audit
record. The checklist is intended as the next handoff surface for integrators
that need to distinguish bounded runner parity from full SQLite release-suite
closure.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader and runner-evidence helpers, performs no
shell-out, and counts no shared support-library progress.

## Focused Native Mapping: Focused Result Ledger

Date: 2026-05-26

This isolated upstream-suite micro-slice adds a machine-readable ledger over
the accepted focused SQLite runner notes. `SQLiteUpstreamSuiteEvidence` now
parses `runnerStatus.focusedResults` into entry count, pass/fail/not-counted
counts, accumulated parsed test/error totals, unique selected `.test` scripts,
and per-entry cached-evidence or missing-cache skip reasons.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice makes the already accepted focused evidence easier to audit
without treating reused evidence or missing-cache notes as fresh upstream runs.

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader, performs no shell-out, and counts no
shared support-library progress.

## Focused Native Mapping: Upstream Runner Coverage Audit

Date: 2026-05-26

This isolated full-suite micro-slice adds a bounded native audit over the
accepted SQLite upstream runner evidence. `SQLiteUpstreamSuiteEvidence` now
counts recorded runner commands, result entries, and focused-result notes;
extracts concrete selected `.test` scripts and wildcard `.test` patterns from
accepted `testfixture` commands; re-parses the accepted full `veryquick`
result; surfaces declared permutation-suite counters; and reports the remaining
full release/all, multi-configuration make-test, and long-running stress tiers
as explicitly unexecuted.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice improves the local evidence surface around already accepted
runner data rather than claiming fresh upstream execution.

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; manifest/status JSON validation passed; focused
lane tests passed with 1 file, 52 assertions, and 0 failures; lane diff check
passed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader, performs no shell-out, and counts no
shared support-library progress.

## Focused Native Mapping: Upstream Subset Run Records

Date: 2026-05-26

This isolated upstream-suite micro-slice extends the focused upstream evidence
helper with machine-readable run records. `SQLiteUpstreamSuiteEvidence` now
records the exact focused `testfixture` command, selected scripts, job count,
runnable flag, skip reason, optional raw veryquick result line, parsed script
/ test / error counts, and normalized status:

- `skipped` when the local upstream cache, testfixture, or Tcl runner is absent.
- `ready` when the command is runnable but no result line has been supplied.
- `passed` for completed zero-error veryquick result lines.
- `failed` for completed non-zero-error veryquick result lines.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. The new helper keeps that absence explicit in the run record instead
of burying it in prose.

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
jq empty lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; manifest/status JSON validation passed; focused
lane tests passed with 1 file, 50 assertions, and 0 failures; lane diff check
passed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader and upstream runner command planner; it
counts no shared support-library progress.

## Focused Native Mapping: JSON Aggregate FILTER Dispatch

Date: 2026-05-26

This isolated sql-exec/planner micro-slice adds a bounded native mapping for
SQLite aggregate `FILTER` behavior on JSON aggregate rows. Native
`SQLiteJsonAggregate::jsonGroupArrayFilter()` and
`jsonGroupObjectFilter()` accept row inputs carrying a value (or label/value)
plus a SQL-style filter expression, skip NULL and zero filter values, and
reuse the accepted JSON text/JSONB aggregate dispatch paths for
`json_group_array`, `jsonb_group_array`, `json_group_object`, and
`jsonb_group_object`. `SQLiteJsonAggregateState` now records filtered array
and object step rows and finalizes them through the same text/JSONB helpers.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against the existing static focused JSON aggregate
inventory and records one native aggregate FILTER mapping unit. Prior
applicable runner evidence remains the complete SQLite `veryquick` run: 1235
scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonAggregateState.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-aggregate-option-summary.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused lane tests passed with 1 selected file,
2415 assertions, and 0 failures; the Application JSON aggregate smoke emitted
autoload-filtered option arrays and object maps.

Dependency closure: no new support component is needed. The slice reuses
lane-local JSON aggregate dispatch, JSON subtype handling, JSONB encode/decode,
and existing Application option aggregate smoke data; it counts no shared
support-library progress.

## Focused Native Mapping: LIKE/GLOB Late-Row Result Semantics

Date: 2026-05-26

This isolated sql-exec/planner micro-slice fixes a bounded decoded-result
edge. `SQLiteDatabase::optionRowsByNameLike()` and
`optionRowsByNameGlob()` now scan `wp_options` table rows directly
instead of routing through `optionRows()`, whose default limit is 100
rows. Caller-supplied result limits are still honored, but pattern matches
after the first 100 copied option rows are no longer silently hidden.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the accepted LIKE/GLOB and collation/function
cluster evidence while adding a native late-row result semantic fixture.

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-option-name-like-glob.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; the Application LIKE/GLOB smoke reported the late
`_transient_late` row at rowid 105; focused lane tests passed with 2387
assertions and late-row LIKE/GLOB coverage.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local table traversal, decoded Application option rows, UTF-8
pattern splitting, and ASCII case folding; it counts no shared support-library
progress.

## Focused Native Mapping: Malformed UTF-16 Record Text

Date: 2026-05-26

This isolated encoding/collation micro-slice aligns a bounded SQLite record
text edge. `SQLiteRecord::parse()` now validates UTF-16LE and UTF-16BE text
fields before conversion, rejecting odd byte lengths and unpaired surrogate
sequences instead of silently normalizing malformed copied database bytes.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the accepted encoding/file-format cluster evidence:

```sh
enc*.test utf*.test corrupt*.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteRecord.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php lanes/libsqlite/examples/application-utf16-option-insert-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`malformedUtf16Rejected`; focused lane tests passed with malformed UTF-16
record fields covered.

Dependency closure: no new support component is needed. The slice reuses PHP
mbstring, which is already required by the lane-local UTF-16 record
encoder/decoder, and counts no shared support-library progress.

## Focused Native Mapping: Rollback Journal Sector Padding

Date: 2026-05-26

This isolated dependency-closure micro-slice aligns a bounded rollback-journal
format edge. `SQLiteRollbackJournal::parse()` now honors a known journal page
count before checking the remaining bytes, accepts zero-filled trailing sector
padding after declared page records, and rejects non-zero trailing bytes. The
unknown-page-count form still reads records through EOF and continues to reject
truncated records.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the accepted pager/journal cluster evidence:

```sh
wal*.test pager*.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteRollbackJournal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`sectorPaddingBytes`; focused lane tests passed with rollback-journal sector
padding and non-zero trailing-byte rejection covered.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local rollback journal header/page parser, checksum validator,
rollback image overlay, and Application option decoding; it counts no shared
support-library progress.

## Focused Native Mapping: Upstream Subset Matrix Planner

Date: 2026-05-26

This isolated upstream-suite micro-slice adds a bounded native evidence helper
for repeatable SQLite upstream subset runs. `SQLiteUpstreamSuiteEvidence` now
builds a focused subset matrix for named closure clusters, reporting exact
`testfixture` commands, selected script counts, requested job count,
`runnable` status, and an honest skip reason when the hydrated upstream cache,
testfixture, or Tcl runner is absent. Script names are validated as SQLite
`.test` names before command construction.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. The native matrix records the exact commands that would run when the
accepted upstream cache is present:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick json101.test json102.test jsonb01.test
cd .upstream-cache/libsqlite-build-port-libsqlite && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick wal*.test pager*.test
cd .upstream-cache/libsqlite-build-port-libsqlite && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick delete2.test delete3.test btree01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
```

Result: syntax checks passed; focused lane tests passed with the matrix
planner, missing-cache skip reason, and unsafe script-name rejection covered.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local manifest reader and records upstream runner commands only;
it counts no shared support-library progress.

## Focused Native Mapping: WAL Committed Transaction Boundaries

Date: 2026-05-26

This isolated micro-slice adds a bounded native summary for SQLite WAL
transaction boundaries. `SQLiteWal::committedTransactions()` groups parsed
frames into committed batches ending at commit frames, reports the first and
last frame indexes, final database page count, and touched page numbers, and
`uncommittedFrameCount()` reports tail frames after the last commit. Checkpoint
overlay behavior remains unchanged and still ignores uncommitted tail frames.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused SQLite WAL/pager evidence for the
same behavior cluster:

```sh
wal*.test pager*.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`committedTransactions` and `uncommittedFrameCount`; focused lane tests passed
with 1 file and 0 failures.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local WAL header/frame parser, checksum validator, page-image
overlay, and Application option decoding; it counts no shared support-library
progress.

## Focused Native Mapping: `json_tree(X, root)` Selected-Root Rows

Date: 2026-05-26

This isolated micro-slice aligns a bounded SQLite JSON table-valued edge:
`json_tree(X, root)` now reports the selected root row with the selected
node's `key` and parent `path` instead of always treating that row as the
absolute document root. Examples covered include `$.plugin`,
`$.plugin.rules`, JSONB subtrees, and scalar roots such as
`$.plugin.title`. Hidden `json` and `root` columns remain preserved.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused SQLite JSON table evidence for the
same behavior cluster:

```sh
json101.test json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`selectedRootShape`; focused lane tests passed with 1 file, 2319 assertions,
and 0 failures.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path lookup, JSON5 parser, JSONB decoder, table row
shape helpers, hidden-column output, and SQL NULL handling; it counts no
shared support-library progress.

## Focused Native Mapping: `json_group_array(X ORDER BY Y)`

Date: 2026-05-26

This isolated micro-slice adds a bounded native row-ordering helper for
SQLite's aggregate `ORDER BY` boundary on `json_group_array()`. Native
`SQLiteJsonAggregate::jsonGroupArrayOrderBy()` sorts stepped rows by a caller
supplied key before finalization, preserving NULL-low ascending order, stable
ties, SQL NULL value rendering, JSON subtype passthrough, JSONB BLOB
passthrough, and malformed raw BLOB rejection. It does not claim full SQL
planner support for `FILTER`, window frames, multi-term collations, or
two-argument `DISTINCT` aggregates.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused SQLite JSON aggregate evidence for
the same upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonAggregateState.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`nameOrderedOptionValueArrayFromSteps` and
`nameOrderedOptionValueJsonbDecoded`; focused lane tests passed with 1 file,
2260 assertions, and 0 failures.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON aggregate dispatch, JSON constructor value coercion,
JSON subtype handling, JSONB encoder/decoder, BLOB wrapper, and SQL NULL
handling components; it counts no shared support-library progress.

## Focused Native Mapping: `json_group_array(DISTINCT X)`

Date: 2026-05-26

This isolated micro-slice adds a bounded native row de-duplication helper for
SQLite's `json_group_array(DISTINCT X)` aggregate boundary. Native
`SQLiteJsonAggregate::jsonGroupArrayDistinct()` preserves first-seen row order,
SQL NULL collapse, boolean-as-integer JSON rendering, JSON subtype passthrough,
JSONB BLOB passthrough, and malformed raw BLOB rejection without claiming SQL
planner support for `FILTER`, aggregate `ORDER BY`, or two-argument
`DISTINCT` aggregates.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused SQLite JSON aggregate evidence for
the same upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonAggregateState.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: syntax checks passed; the Application smoke reported
`distinctOptionValueArray` and `distinctOptionValueJsonbDecoded`; focused lane
tests passed with 1 file, 2248 assertions, and 0 failures.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON aggregate dispatch, JSON constructor value coercion,
JSON subtype handling, JSONB encoder/decoder, BLOB wrapper, and SQL NULL
handling components; it counts no shared support-library progress.

## Focused Native Mapping: JSON Aggregate Step/Final State

Date: 2026-05-25

This isolated micro-slice adds a bounded native aggregate state for SQLite
JSON aggregate behavior. `SQLiteJsonAggregateState` models ordered aggregate
step rows for `json_group_array()` and `json_group_object()`, then finalizes
through the accepted text/JSONB SQL-dispatch helpers for `json_group_array`,
`jsonb_group_array`, `json_group_object`, and `jsonb_group_object`. The slice
preserves accepted JSON subtype, JSONB BLOB, SQL NULL, empty aggregate,
case-insensitive function-name, and invalid-name behavior without expanding
into SQL planner or aggregate execution scheduling.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON aggregate evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON aggregate dispatch, JSON constructor value coercion,
JSON subtype handling, JSONB encoder/decoder, BLOB wrapper, and SQL NULL
handling components; it counts no shared support-library progress.

## Focused Native Mapping: `json_remove()`/`jsonb_remove()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice rebases the bounded SQLite JSON remove SQL-dispatch
behavior on the current accepted libsqlite JSON stack. Native
`SQLiteJsonRemove` now validates `json_remove` and `jsonb_remove` with
SQLite-style case-insensitive function lookup across direct calls and
SQL-style argument-vector dispatch, preserves text-result versus JSONB-result
typing, propagates SQL NULL, preserves no-path and root-removal behavior, and
rejects invalid arity, invalid function names, invalid JSON argument types, or
non-text path arguments without expanding into planner or expression
evaluation.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON remove evidence for the same
upstream behavior cluster:

```sh
json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path, JSON5 parser, JSONB encoder/decoder, canonical
JSON, BLOB wrapper, and SQL NULL handling components; it counts no shared
support-library progress.

## Focused Native Mapping: JSON Mutation Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQL-dispatch boundary for SQLite
JSON mutation helpers. Native `SQLiteJsonMutation` now validates
`json_insert`, `jsonb_insert`, `json_set`, `jsonb_set`, `json_replace`, and
`jsonb_replace` with SQLite-style case-insensitive function lookup across
direct mutation dispatch and SQL-style argument-vector dispatch. The slice
preserves text-result versus JSONB-result typing, JSON subtype and JSONB input
value handling, SQL NULL input propagation, invalid name rejection, path/value
arity rejection, non-text path rejection, and non-text/non-BLOB JSON input
rejection without expanding into planner or expression evaluation.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON mutation evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path mutation engine, JSON subtype handling, JSONB
encoder/decoder, BLOB wrapper, and SQL NULL handling components; it counts no
shared support-library progress.

## Focused Native Mapping: JSON Aggregate Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQL-dispatch boundary for SQLite
JSON aggregate helpers. Native `SQLiteJsonAggregate` now validates
`json_group_array`, `jsonb_group_array`, `json_group_object`, and
`jsonb_group_object` with SQLite-style case-insensitive function lookup across
direct aggregate dispatch and argument-vector dispatch, preserves text-result
versus JSONB-result typing, preserves JSON subtype/JSONB input value handling,
and rejects invalid aggregate function names or malformed object rows without
expanding into SQL planner or aggregate execution state.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON aggregate evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON constructor value coercion, JSON subtype handling,
JSONB encoder/decoder, BLOB wrapper, and SQL NULL handling components; it
counts no shared support-library progress.

## Focused Native Mapping: `json_patch()`/`jsonb_patch()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice rebases the bounded SQLite JSON merge-patch
SQL-dispatch behavior on the current accepted libsqlite JSON stack. Native
`SQLiteJsonPatch` now validates `json_patch` and `jsonb_patch` with
SQLite-style case-insensitive function lookup across direct calls and
two-argument vector dispatch, preserves text-result versus JSONB-result
typing, propagates SQL NULL, and rejects invalid arity or invalid function
names without expanding into planner or expression evaluation.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON patch evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON canonicalizer, JSON5 parser, JSONB patch engine,
BLOB wrapper, and SQL NULL handling components; it counts no shared
support-library progress.

## Focused Native Mapping: `json_quote()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQL-dispatch boundary for SQLite
JSON SQL-value quoting. Native `SQLiteJsonQuote` now validates `json_quote`
with SQLite-style case-insensitive function lookup across direct calls and
one-argument vector dispatch, keeps the accepted SQL scalar, JSON subtype,
JSONB BLOB, raw BLOB rejection, and SQL NULL behavior, and rejects invalid
arity and invalid function names without expanding into broader SQL
expression evaluation.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON quote evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSONB, JSON subtype, BLOB wrapper, SQL scalar coercion,
and SQL NULL handling components; it counts no shared support-library
progress.

## Focused Native Mapping: `json_type()`/`json_array_length()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQL-dispatch boundary for SQLite
JSON inspection helpers. Native `SQLiteJsonInspection` now validates
`json_type` and `json_array_length` with SQLite-style case-insensitive
function lookup across direct calls and one-or-two argument-vector dispatch,
keeps the accepted scalar result typing, propagates SQL NULL input and NULL
path results, and rejects invalid arity, invalid function names, and non-text
path arguments without expanding into planner or virtual-table behavior.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON inspection evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test json501.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json` after local focused
verification.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path, JSON5, JSONB inspection, BLOB wrapper, and SQL
NULL handling components; it counts no shared support-library progress.

## Focused Native Mapping: `json_pretty()` SQL Function Dispatch

Date: 2026-05-25

This isolated rework rebases the deferred `json_pretty` SQL-dispatch behavior
on top of the accepted `json_extract`/`jsonb_extract` subtype dispatch and
`json_each` table-valued row slices. Native `SQLiteJsonPretty` now validates
the SQL function name `json_pretty` with SQLite-style case-insensitive
function lookup across direct calls and argument-vector dispatch, dispatches
default and caller-supplied indentation through the accepted formatter,
handles one-or-two argument vectors for SQL-style arity validation, accepts
scalar SQL argument-vector values including integers, floats, and booleans,
accepts the same scalar SQL values through the direct SQL-dispatch helper,
preserves whole REAL spelling such as `3.0` through both SQL-dispatch paths,
accepts JSON subtype input, preserves SQL NULL and malformed input propagation,
and rejects invalid function names through both direct and argument-vector dispatch without changing the accepted
`json_pretty(JSON[,INDENT])` formatting boundary.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This rework reuses the prior focused `json_pretty` runner evidence:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json106.test json108.test
```

Prior result: passed 2 selected Tcl scripts, 45,007 tests, and 0 errors in
00:08. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence after rework is recorded in `lane-status.json`.
The latest additive direct-dispatch and argument-vector boolean false scalar
checks passed the Application smoke and focused `SQLiteHeaderTest.php` with 2053
assertions and 0 failures in the clean integrator worktree.
Priority-keeper refresh 2026-05-25T09:58Z adds the missing direct-dispatch
`true` scalar assertion so the native evidence covers both boolean SQL scalar
values through direct and argument-vector dispatch. Focused PHP verification
passed with 1 selected file, 2054 assertions, and 0 failures.
Priority-finisher refresh 2026-05-25T10:13Z adds the missing whole-REAL
`3.0` scalar coercion case through direct and argument-vector SQL dispatch so
the rework preserves SQLite-style REAL text instead of PHP's collapsed float
cast. Focused PHP verification passed with 1 selected file, 2056 assertions,
and 0 failures; the Application smoke reported `scalar_whole_real_settings` as
JSON string `3.0`.
Clean-integrator rebase 2026-05-25T10:17Z also retains the signed-integer and
fractional-float scalar assertions from the priority-keeper handoff through
both SQL-dispatch entry points, plus matching Application smoke values.
Priority-finisher refresh 2026-05-25T10:28Z adds direct SQL-dispatch coverage
for cast text BLOB and JSON subtype custom indentation, matching the already
accepted argument-vector indentation behavior, and updates the Application smoke
to report direct dispatch output alongside argument-vector output. Focused PHP
verification passed with 1 selected file, 2058 assertions, and 0 failures.
Priority-keeper refresh 2026-05-25T10:40Z adds boolean true and fractional
REAL custom-indent coercion through both direct and argument-vector
`json_pretty()` SQL dispatch, plus Application smoke rows for those option
review cases. Focused verification is recorded in `lane-status.json`.
Priority-keeper rework 2026-05-25T10:50Z adds the missing direct-dispatch
boolean false custom-indent assertion and matching Application smoke row while
preserving the accepted argument-vector false-indent case. Focused
verification is recorded in `lane-status.json`.
Priority-rework refill 2026-05-25T11:02Z rebases that same json_pretty
SQL-dispatch cluster on the accepted worktree and adds explicit text-BLOB JSON
input coverage through both direct and argument-vector dispatch, including
custom indentation from the SQL helper. Focused verification is recorded in
`lane-status.json`.
Priority-keeper rework 2026-05-25T11:10Z keeps the accepted SQL-dispatch
cluster and additively covers JSONB option blobs with caller-supplied
indentation through both direct and argument-vector `json_pretty()` dispatch.
Focused verification is recorded in `lane-status.json`.
Priority-keeper rework 2026-05-25T11:27Z fills the matching default-indent
JSONB SQL-dispatch assertion gap, covering JSONB blobs through both direct and
argument-vector `json_pretty()` dispatch when no second SQL argument is
provided. Focused verification is recorded in `lane-status.json`.
Priority-refill rework 2026-05-25T12:13Z adds explicit SQL NULL
first-argument plus custom-indent second-argument coverage through both direct
and argument-vector `json_pretty()` SQL dispatch. Focused verification is
recorded in `lane-status.json`.
Priority libsqlite rework 2026-05-26T02:10Z keeps the accepted JSON and WAL
evidence intact and additively tightens the deferred `json_pretty()`
SQL-dispatch patch with mixed-case `Json_Pretty` direct and argument-vector
coverage. No fresh upstream `testfixture` run was started because this
isolated worktree has no hydrated upstream cache; focused PHP evidence is
recorded in `lane-status.json`.

## Focused Native Mapping: `json_tree()` Recursive Table-Valued Rows

Date: 2026-05-25

This isolated micro-slice maps the bounded recursive row-production boundary
for SQLite JSON1 `json_tree(X[,P])`. Native `SQLiteJsonTree` produces
SQLite-shaped `key`, `value`, `type`, `atom`, `id`, `parent`, `fullkey`, and
`path` columns for strict JSON text, SQLite JSON5 text, JSONB blobs, located
subtrees, scalar roots, missing paths, and SQL NULL inputs. It uses preorder
ids with parent links, quotes object labels that are not bare path labels,
validates malformed paths/input through the existing JSON path/parser stack,
and accepts case-insensitive `json_tree` SQL function dispatch.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON1/JSONB evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported recursive
root/plugin/rules rows for strict JSON, JSON5 text, JSONB blobs, and SQL NULL
inputs, and focused PHP passed 1 selected test file, 2102 assertions, and 0
failures. This worker did not start the root aggregate harness because root
verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path, JSON5, JSONB, canonical encoding, SQL value
typing, and BLOB wrapper components; it counts no shared support-library
progress.

## Focused Native Mapping: JSON Operator Parenthesized Scalar RHS Constants

Date: 2026-05-24

This isolated slice extends bounded SQLite constant-expression folding inside
deterministic `->` / `->>` JSON operator RHS expressions to parenthesized
scalar constants. Native `SQLiteCreateIndex` now maps `('cache')` to
`$.cache`, `(1)` to `$[1]`, `('settings.v1')` to `$."settings.v1"` for `->`
fragment indexes, and nested parenthesized reduced forms such as
`((min('seo','cache')))` to `$.cache`. General SQL expressions such as
`(1 + 1)` remain unsupported.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the prior focused JSON operator/index runner
evidence for the same upstream behavior cluster:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test subtype1.test indexexpr1.test
```

Prior result: passed 4 selected Tcl scripts, 729 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Bounded static evidence:

- Same 5 focused upstream files as the accepted JSON operator RHS slices:
  `src/json.c`, `test/json101.test`, `test/json102.test`,
  `test/subtype1.test`, and `test/indexexpr1.test`.
- The native smoke covers 7 focused RHS outcomes: parenthesized string label,
  parenthesized integer array index, parenthesized `->` fragment label, nested
  parenthesized reduced `min()` expression, and unsupported arithmetic
  expression forms.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteCreateIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-operator-parenthesized-rhs.php
php lanes/libsqlite/examples/application-json-operator-parenthesized-rhs.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported normalized paths
`$.cache`, `$[1]`, `$."settings.v1"`, nested `$.cache`, and unsupported
arithmetic expressions as `null`; focused PHP passed 1 selected test file, 1836
assertions, and 0 failures; `git diff --check -- lanes/libsqlite` passed. This
worker did not start the root aggregate harness because root verification was
not assigned to this lane.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local `SQLiteCreateIndex` literal reader and JSON path
normalization; it does not activate broader SQL expression evaluation or any
shared support-library component.

## Focused Native Mapping: JSON Operator `min()`/`max()` RHS Reduced Constants

Date: 2026-05-24

This isolated slice extends bounded SQLite constant-expression folding inside
deterministic `->` / `->>` JSON operator RHS expressions to reduced
`min()`/`max()` calls over homogeneous literal strings or homogeneous numeric
literals. Native `SQLiteCreateIndex` now maps `min('seo','cache')` to
`$.cache`, `max('plugin.enabled','plugin.disabled')` to
`$."plugin.enabled"`, and `min(2,1)` to `$[1]` for expression-index preflight.
Mixed-type arguments, single-argument calls, SQL NULL, BLOBs, and general SQL
expressions stay unsupported in this reduced slice.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the prior focused JSON operator/index runner
evidence for the same upstream behavior cluster:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test subtype1.test indexexpr1.test
```

Prior result: passed 4 selected Tcl scripts, 729 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Bounded static evidence:

- Same 5 focused upstream files as the accepted JSON operator RHS slice:
  `src/json.c`, `test/json101.test`, `test/json102.test`,
  `test/subtype1.test`, and `test/indexexpr1.test`.
- The native smoke covers 8 focused RHS outcomes: string min/max path labels,
  dotted-label quoting, numeric min/max array indexes, mixed-type rejection,
  and single-argument rejection.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteCreateIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-operator-minmax-rhs.php
php lanes/libsqlite/examples/application-json-operator-minmax-rhs.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported normalized paths
`$.cache`, `$."plugin.enabled"`, and `$[1]`, plus unsupported mixed/single
argument cases as `null`; focused PHP passed 1 selected test file, 1829
assertions, and 0 failures; `git diff --check -- lanes/libsqlite` passed. This
worker did not start the root aggregate harness because root verification was
not assigned to this lane.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local `SQLiteCreateIndex` literal reader and JSON path
normalization; it does not activate or count progress against shared
`json-json5-document-core` or broader SQL expression support.

## Focused Native Mapping: `json_pretty(JSON[, INDENT])`

Date: 2026-05-24

This slice maps SQLite's `json_pretty()` boundary for strict JSON text,
SQLite JSON5 text, cast text BLOB fallback, JSONB BLOB inputs, SQL NULL, and
caller-supplied indentation text. The native `SQLiteJsonPretty` reuses the
lane-local `SQLiteJsonCanonical` and `SQLiteJsonB` behavior, then renders
arrays and objects with SQLite's newline placement, `": "` object separator,
default four-space indentation, SQL NULL/default indent handling, empty
indent strings, tab/custom indent strings, empty container handling, JSON5
number spellings, and malformed JSON rejection. This is local-only formatting
logic, not a SQL parser or SQLite extension wrapper.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json106.test json108.test
```

Result: passed 2 selected Tcl scripts, 45,007 tests, and 0 errors in 00:08.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned the exact pretty-output hex for default
four-space formatting, JSON5 normalization, empty indent, tab indent, custom
`--` indent, empty arrays/objects, JSONB BLOB rendering, SQL NULL
propagation, JSON5 infinity/NaN/decimal/hex number spellings inside pretty
output, and `malformed JSON` for duplicate comma input.

Bounded static evidence:

- 3 hydrated focused files: `src/json.c`, `test/json106.test`, and
  `test/json108.test`.
- 5857 inspected upstream lines across those files.
- 37 focused `json_pretty`/`JsonPretty`/pretty source-test references.
- 7 direct `json_pretty` references in the selected Tcl scripts.
- 23 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-pretty-option-review.php
php lanes/libsqlite/examples/application-json-pretty-option-review.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1812 assertions, and 0
failures. The test file now contains 223 focused libsqlite cases. The new
Application example reports SQLite-style `json_pretty()` output for strict JSON
text, JSON5 plugin settings, custom indentation, cast text BLOBs, JSONB
option blobs, NULL option values, and malformed copied settings. This worker
did not start the root aggregate harness because root verification was not
assigned to this lane.

## Focused Native Mapping: `json(X)` Canonical Text And JSON5 Normalization

Date: 2026-05-24

This slice maps SQLite's `json(X)` one-argument canonicalization boundary for
strict JSON text, SQLite JSON5 text, cast text BLOB fallback, JSONB BLOBs, and
SQL NULL. The native `SQLiteJsonCanonical` removes insignificant whitespace,
quotes JSON5 identifier keys, strips comments, accepts single trailing commas,
normalizes JSON5 decimal forms such as `4.`, `+4.e1`, and `-.5e-1`, maps
infinities and NaN to SQLite's canonical JSON spellings, decimalizes hex
integers, converts single-quoted strings to JSON strings, escapes raw control
characters, dispatches valid JSONB blobs through the existing bounded
`SQLiteJsonB` decoder, and rejects malformed JSON. This is a local-only JSON
canonicalizer, not a SQL parser or SQLite extension wrapper.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test json501.test json502.test
```

Result: passed 4 selected Tcl scripts, 793 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned canonical whitespace-free JSON text,
JSON5 identifier/trailing-comma object and array normalization, `4.0`,
`4.0e1`, `-0.5e-1`, `9e999`, `null`, `11259375`,
`{"x":"a \"b\" c"}`, control-character text as hex
`7B226C6162656C223A226162635C753030303178797A227D`, cast text BLOB
fallback, JSONB BLOB text rendering, SQL NULL propagation, and malformed JSON
errors for bad identifier punctuation and duplicate commas.

Bounded static evidence:

- 5 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json102.test`, `test/json501.test`, and `test/json502.test`.
- 8173 inspected upstream lines across those files.
- 368 focused `json`/`jsonb`/`json_valid`/`json_error_position`/translation
  source-test references.
- 144 direct `json()`/JSONB translation references in source and selected
  Tcl scripts.
- 829 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonCanonical.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-canonical-option-preflight.php
php lanes/libsqlite/examples/application-json-canonical-option-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1800 assertions, and 0
failures. The test file now contains 222 focused libsqlite cases. The new
Application example reports SQLite-style `json(X)` canonicalization for strict
JSON text, JSON5 plugin settings, cast text BLOBs, JSONB option blobs, NULL
option values, and malformed copied settings. This worker did not start the
root aggregate harness because root verification was not assigned to this
lane.

## Focused Native Mapping: `json_array()` And `json_object()` SQL Constructors

Date: 2026-05-24

This slice maps SQLite's JSON constructor SQL-value boundary for
`json_array(VALUE,...)` and `json_object(NAME,VALUE,...)`. The native
`SQLiteJsonConstructor` renders SQL NULL, integer, REAL including infinities,
TEXT, `TRUE`/`FALSE` integer expressions, explicit `SQLiteJsonSubtypeValue`
passthrough, JSONB BLOB values through the existing `SQLiteJsonB` decoder, raw
BLOB rejection, odd `json_object()` arity errors, and non-TEXT object-label
errors. This is a local-only constructor helper, not a SQL parser or SQLite
extension wrapper.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test subtype1.test
```

Result: passed 2 selected Tcl scripts, 305 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned `[1,2.5,null,"hello"]`, copied JSON text
as a quoted string, JSON subtype passthrough inside arrays/objects, JSONB array
passthrough, `TRUE`/`FALSE` as `1,0`, infinities as `9.0e+999` and
`-9.0e+999`, `JSON cannot hold BLOB values` for raw BLOBs,
`json_object() requires an even number of arguments`, `json_object() labels
must be TEXT`, JSON subtype labels as ordinary TEXT, and JSONB labels rejected.

Bounded static evidence:

- 3 hydrated focused files: `src/json.c`, `test/json101.test`, and
  `test/subtype1.test`.
- 7030 inspected upstream lines across those files.
- 95 focused `json_array`/`json_object`/constructor/BLOB/subtype
  source-test references.
- 251 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonConstructor.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-constructor-option-diagnostics.php
php lanes/libsqlite/examples/application-json-constructor-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1782 assertions, and 0
failures. The test file now contains 221 focused libsqlite cases. The new
Application example reports SQLite-style JSON constructor diagnostics for copied
plugin settings, JSON subtype payloads, JSONB migration queues, and raw BLOB
rejection. This worker did not start the root aggregate harness because root
verification was not assigned to this lane.

## Focused Native Mapping: `json_quote()` SQL Values

Date: 2026-05-24

This slice maps SQLite's `json_quote(X)` SQL-value rendering boundary for SQL
`NULL`, INTEGER, REAL, TEXT with quote/control-character escaping, JSONB BLOBs,
raw BLOB rejection, and superficial-only malformed JSONB errors.
`SQLiteJsonQuote` returns SQLite JSON text, maps PHP booleans like SQLite
`TRUE`/`FALSE` integer expressions, preserves explicit `SQLiteJsonSubtypeValue`
inputs, accepts strict JSONB blobs through the existing `SQLiteJsonB` decoder,
and throws SQLite-style errors for raw BLOBs or malformed JSONB.
`SQLiteCreateIndex` now reuses the helper for the existing bounded
`json_quote(NULL/numeric)` JSON operator RHS constant folding without
broadening unsupported text/BLOB RHS paths.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test subtype1.test
```

Result: passed 3 selected Tcl scripts, 622 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned `null`, `12345`, `3.14159`, `100.0`,
`-0.25`, `"abc\"xyz"`, control-character text as hex
`226C696E655C6E7461625C746E756C5C7530303030656E6422`, JSON subtype values
`{"a":1}` and `[1,2]`, `TRUE`/`FALSE` as `1,0`, copied JSON-looking text as a
quoted string, `JSON cannot hold BLOB values` for raw BLOBs, `malformed JSON`
for a superficial-only JSONB BLOB, and upstream arity errors.

Bounded static evidence:

- 4 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json102.test`, and `test/subtype1.test`.
- 7873 inspected upstream lines across those files.
- 89 focused `json_quote`/`jsonQuoteFunc`/`jsonAppendSqlValue`/BLOB/subtype
  source-test references.
- 497 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonQuote.php
php -l lanes/libsqlite/src/SQLiteCreateIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-quote-option-preflight.php
php lanes/libsqlite/examples/application-json-quote-option-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1767 assertions, and 0
failures. The test file now contains 218 focused libsqlite cases. The new
Application example reports SQLite-style `json_quote()` rendering for SQL NULL,
integer, REAL, exponent REAL, copied text settings, control-character text,
valid JSONB blobs, and raw BLOB rejection. This worker did not start the root
aggregate harness because root verification was not assigned to this lane.

## Focused Native Mapping: `json_type()` And `json_array_length()` Text, JSON5, BLOB, And NULL

Date: 2026-05-24

This slice maps SQLite's JSON inspection boundary for `json_type(X[,P])` and
`json_array_length(X[,P])` across strict JSON text, SQLite JSON5 text, cast
text BLOB fallback, JSONB-looking BLOBs, missing paths, scalar paths, and SQL
NULL. `SQLiteJsonInspection` reuses the existing bounded JSON5 parser and
JSONB inspector, returns SQLite type names, returns `0` for non-array scalar
targets, returns `NULL` for missing paths or SQL NULL arguments, and raises on
malformed JSON or malformed paths.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test json501.test
```

Result: passed 3 selected Tcl scripts, 780 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned:

```text
object,array,integer,real,true,false,null,text,NULL,6,0,0,NULL
array,real,2,array,3,object,2,NULL,NULL
```

The values cover strict JSON root/path type names, missing-path SQL NULL,
array length 6, scalar length 0, empty-array length 0, missing-array SQL NULL,
JSON5 path type and length, cast text BLOB fallback, JSONB path inspection,
and SQL NULL propagation.

Bounded static evidence:

- 4 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json102.test`, and `test/json501.test`.
- 8093 inspected upstream lines across those files.
- 450 focused `json_type`/`json_array_length`/`json_valid`/
  `json_error_position`/JSONB/JSON5 source-test references.
- 46 direct `json_type`/`json_array_length` references in the selected Tcl
  scripts.
- 564 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonInspection.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-inspection-preflight.php
php lanes/libsqlite/examples/application-json-inspection-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1749 assertions, and 0
failures. The test file now contains 217 focused libsqlite cases. The new
Application example reports SQLite-style type and array-length inspection for
strict JSON text, JSON5 plugin settings, cast text BLOBs, JSONB blobs, missing
plugin paths, and SQL NULL option values. This worker did not start the root
aggregate harness because root verification was not assigned to this lane.

## Focused Native Mapping: `json_error_position()` Text, JSON5, BLOB, And NULL

Date: 2026-05-24

This slice maps SQLite's `json_error_position(X)` diagnostics across TEXT,
SQLite JSON5, cast text BLOB fallback, JSONB BLOBs, and SQL NULL.
`SQLiteJsonErrorPosition` dispatches BLOBs through SQLite-style superficial
JSONB detection and otherwise uses the bounded JSON5 text parser. Text errors
return 1-based character positions, valid JSON5 returns 0 even when strict
`json_valid(X)` would reject it, JSONB errors return byte positions, and SQL
NULL propagates as NULL.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test json501.test json502.test
```

Result: passed 4 selected Tcl scripts, 793 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned:

```text
0,0,16,16,9,15,1,1,7,0,NULL
0,0,2,1
```

The values cover JSON5 object/array trailing-comma acceptance, duplicate-comma
object/array offsets, nested malformed JSON5 object position 9, a
Application-shaped duplicate-comma settings offset 15, unsupported identifier
and trailing-content position 1, decimal leading-zero offset 7, JSON5 plus,
leading-decimal, and trailing-decimal number acceptance, SQL NULL propagation,
cast text BLOB fallback, valid JSONB, superficial-only corrupt JSONB byte
offset 2, and non-JSONB BLOB text fallback at position 1.

Bounded static evidence:

- 5 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json102.test`, `test/json501.test`, and `test/json502.test`.
- 8173 inspected upstream lines across those files.
- 190 focused `json_error_position`/`json_valid`/JSON5/JSONB source-test
  references.
- 14 direct `json_error_position` references in the selected Tcl scripts.
- 677 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJson5Parser.php
php -l lanes/libsqlite/src/SQLiteJsonB.php
php -l lanes/libsqlite/src/SQLiteJsonErrorPosition.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-error-position-preflight.php
php lanes/libsqlite/examples/application-json-error-position-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1722 assertions, and 0
failures. The test file now contains 216 focused libsqlite cases. The new
Application example reports SQLite-style error positions for JSON5 plugin
settings text, duplicate-comma and nested malformed settings, leading-zero
numbers, cast text BLOBs, valid JSONB option blobs, superficial-only corrupt
JSONB blobs, and SQL NULL option values. This worker did not start the root
aggregate harness because root verification was not assigned to this lane.

## Focused Native Mapping: `json_valid()` Text, JSON5, And BLOB Flags

Date: 2026-05-24

This slice maps SQLite's `json_valid(X, FLAGS)` dispatcher across text, JSON5,
BLOB fallback, JSONB, NULL, and invalid flag boundaries. `SQLiteJsonValidity`
uses strict RFC-8259 validation for the default/flag-1 text path, the existing
bounded `SQLiteJson5Parser` for flag 2, the existing `SQLiteJsonB` superficial
and strict validators for flags 4 and 8, and `SQLiteBlobValue` to preserve the
SQLite distinction between TEXT and BLOB inputs.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json501.test json107.test
```

Result: passed 3 selected Tcl scripts, 479 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke returned:

```text
1,0,1,1,0,1,1,0,1,0,1,1
```

The values cover strict JSON text acceptance, JSON5 default rejection, JSON5
flag-2 acceptance, comments/trailing-comma JSON5 acceptance, strict
control-character rejection, JSON5 control-character acceptance, cast text BLOB
flag-1 acceptance, cast text BLOB flag-4 rejection, superficial-only JSONB
flag-4 acceptance, strict JSONB rejection, combined flag-12 superficial
acceptance, and SQL NULL propagation. Invalid flag 16 raised SQLite's
`FLAGS parameter to json_valid() must be between 1 and 15` error.

Bounded static evidence:

- 4 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json501.test`, and `test/json107.test`.
- 7336 inspected upstream lines across those files.
- 176 focused `json_valid`/JSON5/FLAGS source-test references.
- 518 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonValidity.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-validity-preflight.php
php lanes/libsqlite/examples/application-json-validity-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1707 assertions, and 0
failures. The test file now contains 215 focused libsqlite cases. The new
Application example reports strict JSON text, JSON5 text, malformed text, cast
text BLOB, valid JSONB, superficial-only JSONB, and SQL NULL option-value
checks. This worker did not start the root aggregate harness because root
verification was not assigned to this lane.

## Focused Native Mapping: JSONB `json_valid()` Superficial Flag

Date: 2026-05-24

This slice maps SQLite's BLOB-side `json_valid(X, FLAGS)` distinction for
JSONB inputs. `SQLiteJsonB::isSuperficiallyJsonB()` now mirrors the flag-4
outer-header check used by `jsonArgIsJsonb()`: the element type must be
within SQLite's JSONB type range, the outer payload size must consume the
whole BLOB, scalar null/boolean payloads must be zero length, and small
ambiguous BLOBs whose first byte overlaps JSON text (`{`, `[`, or ASCII
digits) fall back to strict validation. `SQLiteJsonB::isStrictlyWellFormed()`
adds a recursive byte-shape validator for the flag-8 boundary.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json107.test json101.test json102.test jsonb01.test
```

Result: passed 4 selected Tcl scripts, 650 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Targeted upstream SQL smoke:

```tcl
sqlite3 db :memory:
puts [db one {SELECT json_valid(x'8bff00000000000000',4) || ',' ||
                     json_valid(x'8bff00000000000000',8) || ',' ||
                     json_valid(CAST('{"a":35}' AS BLOB),4) || ',' ||
                     json_valid(x'1000',4)}]
```

Result: `1,0,0,0`, confirming the large corrupt outer-array BLOB is
superficially JSONB but not strict JSONB, a cast text JSON BLOB is not accepted
as superficial JSONB at the ambiguous small-BLOB boundary, and a scalar null
header with a non-zero payload is rejected.

Bounded static evidence:

- 5 hydrated focused files: `src/json.c`, `test/json107.test`,
  `test/json101.test`, `test/json102.test`, and `test/jsonb01.test`.
- 7896 inspected upstream lines across those files.
- 590 matching JSONB/json_valid/flag/source-test lines across the focused
  files.
- 732 focused Tcl command/reference lines across the selected scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonB.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-jsonb-validity-preflight.php
php lanes/libsqlite/examples/application-jsonb-validity-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php | rg -c '^PASS'
```

Result: focused PHP passed 1 selected test file, 1678 assertions, and 0
failures. The test file now contains 214 focused libsqlite cases. The new
Application example reports valid settings JSONB as superficial+strict, the
large corrupt BLOB `8bff00000000000000` as superficial-only, cast text JSON
as not JSONB for flag 4, and a bad scalar payload as invalid. This worker did
not start the root aggregate harness because root verification was not
assigned to this lane.

## Focused Native Mapping: JSON Full Path Validation

Date: 2026-05-24

This slice validates full SQLite JSON paths before a copied expression index is
treated as reusable by native preflight code. `SQLiteCreateIndex` now rejects
malformed full paths such as `$.`, `$.plugin[#-]`, `$.plugin[#9]`,
`$.plugin[#+2]`, and unterminated quoted labels for `json_extract()`, `->`,
and `->>` expression-index metadata. It still accepts valid root paths,
quoted empty object labels (`$.""`), `[N]`, `[#]`, and `[#-N]`, and keeps the
existing abbreviated operator RHS normalization for labels and integer array
indexes.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json105.test json102.test indexexpr1.test
```

Result: passed 4 selected Tcl scripts, 755 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Bounded static evidence:

- 5 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json105.test`, `test/json102.test`, and `test/indexexpr1.test`.
- 8560 inspected upstream lines across those files.
- 39 focused JSON path error/source contract references around
  `jsonLookupStep()`, `jsonBadPathError()`, `JSON_ABPATH`, and related path
  validation code.
- 169 focused JSON path/operator/index test references in the selected Tcl
  scripts.
- 954 JSON path/operator/index references across the focused files.
- 738 Tcl command/reference lines across the focused scripts.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPath.php
php -l lanes/libsqlite/src/SQLiteCreateIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-path-validation-preflight.php
php lanes/libsqlite/examples/application-json-path-validation-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: focused PHP passed 1 selected test file, 1665 assertions, and 0
failures. The test file now contains 213 focused libsqlite cases. The new
Application example reports `$.""` as valid, malformed `$.`, `$.plugin[#-]`,
and `$.plugin[#9]` as invalid, resolves root page 3 for the valid empty-label
index, skips malformed copied-schema index root pages, and returns
`plugin_empty_label_settings` without requiring the SQLite extension. This
worker did not start the root aggregate harness because root verification was
not assigned to this lane.

## Focused Native Mapping: JSON Operator json_quote() RHS Forms

Date: 2026-05-24

This slice extends bounded SQLite constant-expression folding inside
deterministic `->` / `->>` JSON operator RHS expressions to direct
`json_quote(VALUE)` calls where SQLite renders SQL `NULL` and numeric SQL
values as JSON text that can be reused as an abbreviated operator path. Native
`SQLiteCreateIndex` now maps `json_quote(NULL)` to `$.null`,
`json_quote(123)` to `$."123"`, and `json_quote(1.25)` to `$."1.25"`.
Direct quoted text output, raw BLOB arguments, invalid arity, and parameters
stay unsupported for reusable paths.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick \
  json101.test json102.test subtype1.test indexexpr1.test
```

Result: passed 4 selected Tcl scripts, 729 tests, and 0 errors in 00:00.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Bounded static evidence:

- 5 hydrated focused files: `src/json.c`, `test/json101.test`,
  `test/json102.test`, `test/subtype1.test`, and `test/indexexpr1.test`.
- 8558 inspected upstream lines across those files.
- 21 focused `json_quote` / `jsonQuoteFunc` source-test references.
- 999 JSON operator and expression-index references around `JSON_ABPATH`,
  `jsonExtractFunc()`, `->`, `->>`, `CREATE INDEX`, and `json_quote()`.
- 682 Tcl command/reference lines across the focused scripts.
- Upstream `testfixture` smoke expressions confirmed 10 selected results:
  `json_quote(NULL)`, integer, REAL, exponent REAL, quoted text rendering,
  raw BLOB and arity errors, and `->>` lookups through `json_quote(NULL)`,
  `json_quote(123)`, and `json_quote(1.25)`.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteCreateIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-operator-json-quote-rhs-forms.php
php lanes/libsqlite/examples/application-json-operator-json-quote-rhs-forms.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: focused PHP passed 1 selected test file, 1636 assertions, and 0
failures. The test file now contains 211 focused libsqlite cases. The new
Application example reports normalized paths `$.null`, `$."123"`, and
`$."1.25"`, uses root pages 3-5 for copied `wp_options` JSON operator
expression indexes, returns the expected `plugin_json_quote_*` rows, and
leaves direct quoted text, raw BLOB, and invalid-arity RHS operands
unsupported. This worker did not start the root aggregate harness because
root verification was not assigned to this lane.

Date: 2026-05-22

Upstream checkout:

- Git mirror commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- Official manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Cache: `.upstream-cache/libsqlite`

## Cache Hydration

The cache was inspected before changes. It was a clean shallow blobless checkout
with only root files materialized through a root-only sparse checkout.

Hydration command:

```sh
git -C .upstream-cache/libsqlite sparse-checkout set src test tool ext autosetup autoconf mptest
```

This materialized the directories required by SQLite's `configure`,
`testfixture`, and Tcl test runner paths without deleting or resetting the
cache.

## Prerequisites

Installed direct build/test prerequisites with passwordless sudo:

```sh
sudo -n dnf install -y tcl tcl-devel gcc make
```

Verified tools:

- `tclsh`: `/usr/bin/tclsh`, Tcl 9.0.2
- `cc`/`gcc`: GCC 16.1.1-2.fc44
- `make`: GNU Make 4.4.1
- Tcl headers: `/usr/include/tcl.h`

## Build And Tests

Configure:

```sh
mkdir -p .upstream-cache/libsqlite-build-port-libsqlite
cd .upstream-cache/libsqlite-build-port-libsqlite
../libsqlite/configure CFLAGS='-O0 -g'
```

Result: passed. Configure detected Tcl via `/usr/bin/tclsh9.0` and
`/usr/lib64/tclConfig.sh`.

Build:

```sh
make -C .upstream-cache/libsqlite-build-port-libsqlite -j2 testfixture
```

Result: passed.

Focused runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  btree*.test pager*.test quick*.test schema*.test rowid*.test table*.test
```

Result: 37 scripts, 0 errors out of 6731 tests in 00:07.

Strongest bounded runner completed in this run:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick
```

Result: 1235 scripts, 0 errors out of 329670 tests in 01:51.

Boundary: SQLite `all` and `release` permutations were not run in this bounded
lane pass because they cover many build configurations and higher-cost suites.
The stale missing `tclsh`/compiler/`make`/Tcl-header blocker is resolved.

## Focused Native Mapping: Varint Encoding

This slice adds the write-side counterpart to the existing SQLite varint
decoder. It maps SQLite's core 1-through-9 byte varint format from
`src/util.c`: values below `2^56` use 7-bit continuation groups, while values
with any high byte set use the ninth byte as a full 8-bit tail.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick varint.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 162 tests in 00:00.

Focused upstream fixture boundary:

- `test/varint.test` drives `btree_varint_test`, which repeatedly writes
  integers through `putVarint()`, reads them with `getVarint()` and
  `getVarint32()`, and verifies round-trip byte counts and values.
- `src/test3.c` documents the same test harness boundary and failure modes.
- `src/util.c` documents the canonical `A`, `BA`, ... `BBBBBBBA`,
  `BBBBBBBBC` byte shapes used by SQLite b-tree and record payload code.

The native PHP tests now cover exact byte encodings at the one-, two-, three-,
four-, eight-, and nine-byte boundaries, PHP integer maximum round-trip
behavior, and negative-value rejection for Application write/preflight helpers.

## Focused Native Mapping: Table Leaf Overflow

The current PHP slice maps SQLite's table leaf overflow payload placement from
`src/btree.c`: `maxLeaf = usableSize - 35`, `minLeaf =
((usableSize - 12) * 32 / 255) - 23`, and overflow pages store a 4-byte
big-endian next-page pointer followed by up to `usableSize - 4` payload bytes.

Focused upstream fixture boundary:

- `test/corrupt3.test` creates a page-size 1024 table row with one overflow
  page, verifies the first overflow pointer location, and checks malformed
  overflow chains.

The native PHP tests now cover local-payload length calculation, single-page
overflow reads, multi-page chained overflow reads, and premature overflow-chain
termination for Application-shaped `wp_options` rows.

## Focused Native Mapping: Rowid Range Table Traversal

This slice maps bounded rowid table b-tree traversal used by SQLite rowid
lookups and range scans. The native reader now exposes rowid lower/upper bounds
for table roots, plus a Application-shaped `wp_options` helper that can read
option rows by `option_id` band without requiring a secondary index. Interior
table-page child intervals are used to avoid unrelated branches before reading
leaf cells.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  rowid.test btree01.test where.test
```

Result: 8 Tcl script/permutation runs, 0 errors out of 790 tests in 00:00.

Focused upstream fixture boundary:

- `test/rowid.test` covers rowid lookup names, rowid comparisons, and rowid
  table behavior.
- `test/btree01.test` exercises table b-tree insert/search boundaries through
  the SQLite b-tree test harness.
- `test/where.test` covers lower/upper range constraints and inclusive/exclusive
  bound behavior used by native rowid-range filtering.

The native PHP tests now cover multi-page `wp_options` rowid-range reads,
inclusive and exclusive upper bounds, open-ended bounds, row limits, empty
ranges, and branch pruning around an out-of-range damaged right-most table
branch.

## Focused Native Mapping: Index B-Tree Option Lookup

The current PHP slice also maps SQLite index b-tree cell layout from
`src/btree.c`: index leaf cells store a payload-length varint followed by the
index record payload; index interior cells prefix the same payload with a
4-byte left-child page pointer; both use `maxLocal = ((usableSize - 12) * 64 /
255) - 23` and `minLocal = ((usableSize - 12) * 32 / 255) - 23`.

Focused upstream fixture boundary:

- `test/index.test` covers `CREATE INDEX` schema records and automatic index
  naming.
- `test/rowid.test` covers rowid lookups joined through an index, including
  `CREATE INDEX idxt1 ON t1(x)` and equality on `rowid`/`_rowid_`/`oid`.

The native PHP tests now cover index leaf and interior cell parsing, in-order
index b-tree traversal that preserves interior index records, index local
payload calculations, explicit `CREATE INDEX ... ON wp_options(option_name)`
schema discovery, option-name index lookup, rowid-backed table retrieval, and
automatic `PRIMARY KEY` index inference for simple first-column lookups.
Expression indexes beyond the later `lower(column)` slice, broader
predicate-aware partial-index use, custom collations, and full composite-key
scans remain unported.

## Focused Native Mapping: Automatic UNIQUE Autoindexes

SQLite stores automatic indexes created by `UNIQUE` and `PRIMARY KEY`
constraints as `sqlite_schema` index rows with `sql` set to `NULL`. The native
PHP reader now infers the first column for automatic `UNIQUE` indexes from the
owning table's `CREATE TABLE` SQL and maps those inferred columns to
`sqlite_autoindex_<table>_<n>` rows in schema order.

Focused upstream fixture boundary:

- `test/index.test` checks the automatic index name convention
  `sqlite_autoindex_<table name>_<integer>` and verifies that automatic indexes
  cannot be dropped.
- `test/schema6.test` checks that inline `b UNIQUE` table declarations produce
  the same database content as an explicit `CREATE UNIQUE INDEX ... ON t1(b)`.
- `test/schema5.test` and `test/index3.test` cover table-level `UNIQUE(...)`
  constraints, quoted constraint columns, and collation/sort-order syntax at
  the table/index boundary.

The native PHP tests cover column-level `option_name text UNIQUE`, table-level
`UNIQUE("slug" COLLATE nocase)` parsing, bracket-quoted column names, ignored
`UNIQUE` text inside `CHECK(...)`, and a Application-shaped
`sqlite_autoindex_wp_options_1` row whose `sql` is `NULL`. The lookup then uses
the automatic index root page, decodes the index record's rowid tail, and reads
the target `wp_options` row through the table b-tree. Full composite-key scans,
custom collations, expression indexes beyond `lower(column)`, and broader
predicate-aware partial-index use remain unported.

## Focused Native Mapping: Automatic PRIMARY KEY Autoindexes

This slice extends automatic index inference from `UNIQUE` constraints to
non-rowid `PRIMARY KEY` constraints. A focused upstream runner was executed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test index3.test schema6.test indexedby.test
```

Result: 8 Tcl script/permutation runs, 0 errors out of 404 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` verifies automatic primary-key index creation, the
  `sqlite_autoindex_<table>_<n>` naming convention, and duplicate
  `UNIQUE`/`PRIMARY KEY` constraint coalescing.
- `test/index3.test` verifies quoted/string identifier compatibility and that
  `INTEGER PRIMARY KEY` table declarations do not create autoindex rows.
- `test/schema6.test` cross-checks `INTEGER PRIMARY KEY`, `PRIMARY KEY(...)`,
  `UNIQUE`, and `WITHOUT ROWID` database-content equivalence.
- `test/indexedby.test` verifies that a primary-key-created automatic index can
  be named by `INDEXED BY sqlite_autoindex_*`.

The native PHP reader now derives automatic index first-column order from
`CREATE TABLE` SQL for both `UNIQUE` and `PRIMARY KEY` constraints. It skips
rowid-alias `INTEGER PRIMARY KEY` constraints, handles the SQLite
`INTEGER PRIMARY KEY DESC` exception as an autoindexed primary key, suppresses
`WITHOUT ROWID` table-primary-key autoindex slots, and preserves earlier
`UNIQUE` autoindex ordinals before a later table-level primary key. A
Application-shaped fixture verifies lookup through `sqlite_autoindex_wp_options_2`
when `sqlite_autoindex_wp_options_1` belongs to an earlier `autoload UNIQUE`
constraint and `PRIMARY KEY(option_name)` backs the option-name lookup.

Optimized composite duplicate scans with secondary constraints, custom
collations, expression indexes beyond `lower(column)`, broader
predicate-aware partial-index use, and full WITHOUT ROWID table reads remain
unported.

## Focused Native Mapping: sqlite_sequence AUTOINCREMENT Metadata And Allocation

This slice maps SQLite's internal `sqlite_sequence` table and the bounded
AUTOINCREMENT allocation state needed by Application import/recovery tooling.
SQLite creates `sqlite_sequence(name,seq)` for AUTOINCREMENT tables, keeps one
row per table that has allocated a sequence value, and allows the table
contents to be manually updated even though the system table itself cannot be
indexed or dropped. The native PHP path now models the rowid counter update
without adding a SQL execution engine or raw b-tree page writer.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick autoinc.test
```

Result: 1 Tcl script, 0 errors out of 88 tests in 00:00. This focused runner
was re-run for the allocation slice.

Focused upstream fixture boundary:

- `test/autoinc.test` verifies creation of `sqlite_sequence`, empty initial
  rows, monotonically tracked maximum sequence values, explicit rowid inserts
  that advance `seq`, generated rowids after deletes, deleted/missing sequence
  rows, independent AUTOINCREMENT table rows, manual invalid `seq` mutation,
  `NULL` name mutation, maximum-rowid failure, and the no-index/no-drop
  protection around `sqlite_sequence`.

The native PHP reader now resolves the `sqlite_sequence` table from
`sqlite_schema`, decodes its rows through the existing table b-tree reader, and
preserves mutable SQLite scalar `name`/`seq` values instead of forcing `seq` to
an integer. Application-oriented recovery tools can inspect post/comment/user
sequence counters from a database image without invoking the SQLite extension.
The new `SQLiteAutoincrementState` builds on those records plus the current
table b-tree reader to pick the next generated rowid, create a missing
sequence row in state, coerce invalid `seq` values the way SQLite's
AUTOINCREMENT VM path does, and advance the counter for explicit Application
import IDs without lowering an existing sequence. Raw SQL execution, b-tree
page writes, malformed schema recovery, attached/temp database sequence
tables, journaling/WAL, and trigger/upsert statement orchestration remain
outside this bounded slice.
The native PHP tests now cover allocation from an existing `sqlite_sequence`
row, missing sequence rows, invalid `seq` values, numeric text coercion, and
explicit Application import IDs advancing the sequence before the next generated
ID is chosen.

## Focused Native Mapping: Automatic Index Collation And DESC Metadata

SQLite automatic indexes created for `UNIQUE` or non-rowid `PRIMARY KEY`
constraints inherit per-column collations from explicit constraint terms or
from the table column declaration. They also preserve `DESC` terms for the
index key order. The native PHP reader now carries that metadata for the first
automatic-index column instead of assuming `BINARY ASC` for every
`sqlite_autoindex_*` row whose `sql` is `NULL`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index3.test collate1.test descidx1.test
```

Result: 5 Tcl script/permutation runs, 0 errors out of 166 tests in 00:00.

Focused upstream fixture boundary:

- `test/index3.test` creates `UNIQUE('b' COLLATE nocase DESC)`, verifies the
  resulting `sqlite_autoindex_*` row, and searches through that autoindex using
  `COLLATE nocase`.
- `test/collate1.test` verifies column default collation precedence, including
  SQLite's compatibility behavior where repeated `COLLATE` clauses leave the
  last collation in effect.
- `src/build.c` stores explicit index-term collations first, otherwise the
  owning column default collation, and records requested sort order for each
  index term.

The native PHP tests now cover parsing automatic-index first-column metadata
from `CREATE TABLE` SQL, repeated column `COLLATE` declarations with last-token
precedence, table-level `UNIQUE(... COLLATE RTRIM DESC)` metadata, and a
Application-shaped `wp_options` recovery lookup through
`sqlite_autoindex_wp_options_1` where `UNIQUE(option_name COLLATE NOCASE DESC)`
requires both case-insensitive comparison and descending b-tree search.
Remaining automatic-index gaps include automatic composite-key range metadata
and custom collation callbacks.

## Focused Native Mapping: Explicit Index Collation And DESC Order

This slice replaces the previous explicit-index regex boundary with a small
`CREATE INDEX` first-column parser. It records the first indexed column,
first-column `COLLATE` clause, first-column `ASC`/`DESC` direction, and whether
the index is partial. Native indexed `wp_options` point lookups now carry that
metadata into the index b-tree binary search.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  collate1.test collate2.test descidx1.test index3.test
```

Result: 6 Tcl script/permutation runs, 0 errors out of 286 tests in 00:00.

Focused upstream fixture boundary:

- `test/collate1.test` and `test/collate2.test` verify built-in collation
  ordering, especially `BINARY` versus `NOCASE` behavior.
- `test/descidx1.test` verifies that descending indexes reverse range and
  order traversal semantics while remaining usable for lookup.
- `test/index3.test` verifies legacy quoted-string index column identifiers and
  `COLLATE nocase DESC` syntax in indexed columns.

The native PHP tests cover parsing quoted first-column index identifiers,
`COLLATE NOCASE`, `DESC`, partial-index detection, expression-index rejection,
case-insensitive lookup through a descending `wp_options(option_name)` index,
and refusal to use unsupported partial `option_name` indexes for unconstrained
lookup. Built-in `RTRIM` comparison is implemented for text point lookups, but
custom collations, broader predicate-aware partial-index use, composite keys,
expression indexes beyond `lower(column)`, and range variants beyond the
bounded first-column slice below remain unported.

## Focused Native Mapping: Lower Expression Indexes

This slice adds a bounded expression-index parser for first-term
`lower(<column>)` indexes. Ordinary column-index discovery still rejects
expression terms, so an index on `lower(option_name)` is not mistaken for a
plain `option_name` index. The native lookup path matches SQLite's stored index
payload shape by searching for the ASCII-lowered expression key, then resolving
the rowid tail through the `wp_options` table b-tree.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test indexexpr3.test
```

Result: 3 Tcl scripts, 0 errors out of 248 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies expression indexes such as
  `substr(...)`, `length(...)`, `lower(...)`, expression collations, and the
  rule that expression indexes are used only for matching expression
  predicates.
- The same file rejects expressions inside `PRIMARY KEY` and `UNIQUE`
  constraints, so this PHP slice keeps automatic table-constraint inference
  column-only.
- The native slice intentionally supports only deterministic first-term
  `lower(column)` expression indexes. Arbitrary expression evaluation,
  multi-term expression prefixes, non-deterministic function rejection, and
  covering-index expression semantics remain unported.

The native PHP tests cover parsing `lower(option_name)` metadata with
`COLLATE`, `DESC`, and safe `WHERE option_name IS NOT NULL` predicates;
rejecting constant and unrelated expression indexes; preserving plain
`option_name` lookup rejection for expression indexes; and Application-shaped
case-folded option recovery lookups through
`CREATE INDEX ... ON wp_options(lower(option_name))`.

## Focused Native Mapping: Lower Expression Custom Collations

This slice extends the `lower(option_name)` expression-index lookup path to
indexes that declare an application-defined collation. Native lookup remains
explicit: callers must name the collation and supply the matching PHP
comparator, so ordinary built-in lookup paths continue to reject unsupported
collations instead of returning misleading rows.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test collate1.test collate2.test
```

Result: 4 Tcl scripts, 0 errors out of 427 tests in 00:00. Re-run on
2026-05-23 for the IN-list/range extension with the same 0 errors out of 427
tests.

Focused upstream fixture boundary:

- `test/indexexpr1.test` and `test/indexexpr2.test` verify expression-index
  collation metadata and use only for matching expression predicates.
- `test/collate1.test` and `test/collate2.test` verify application-defined
  collation callbacks such as numeric, HEX, and BACKWARDS ordering.

The native PHP tests cover Application-shaped
`CREATE INDEX ... ON wp_options(lower(option_name) COLLATE WPSLUG)` fixture
where a supplied comparator treats underscores and hyphens as the same slug
separator. Point, `IN (...)`, and bounded range recovery paths all recheck the
recovered table row against the folded custom-collation key; IN-list recovery
suppresses duplicate RHS-equivalent rows and ignores `NULL` RHS terms, while
range recovery handles open/exclusive or inclusive upper bounds and inverted
ranges under the supplied comparator. The ordinary
`optionRowByIndexedLowercaseName()` path still rejects the unsupported
custom collation.
`examples/application-lowercase-custom-collation-option-lookup.php` maps the
point recovery pattern for plugin/theme option names, and
`examples/application-lowercase-custom-collation-option-name-range.php` maps
custom-collation lower-expression range recovery on hosts without the PHP
SQLite extension.

## Focused Native Mapping: Lower Expression Range Seek Bounds

This slice extends the bounded `lower(option_name)` expression-index reader
from point lookup to range scans. Caller-supplied bounds are ASCII-folded before
the index b-tree is searched, while returned rows are rechecked against the
folded range before being exposed. Only `WHERE option_name IS NOT NULL`
partial predicates are accepted for expression range scans; raw `option_name`
comparison predicates are not treated as implied by `lower(option_name)` bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test indexexpr3.test
```

Result: 3 Tcl scripts, 0 errors out of 248 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies `lower(a)` expression-index use and
  expression-index planner boundaries.
- `test/indexexpr2.test` covers expression-index collation behavior and
  expression terms in indexed searches.
- `test/indexexpr3.test` covers expression terms in multi-column indexes.

The native PHP tests now cover a case-folded transient-style
`lower(option_name)` range scan, limit handling, rejection of ordinary
`option_name` range lookup against expression-only indexes, and a bounded
seek fixture where an out-of-range index branch is intentionally unreadable.
`examples/application-lowercase-option-name-range.php` maps case-folded transient
recovery on hosts without the PHP SQLite extension. Remaining expression-index
work includes arbitrary expressions beyond `lower(column)`, expression
prefixes after ordinary indexed columns, custom-collation IN-list/range
variants, and broader expression `IN (...)` lookups.

## Focused Native Mapping: IS NOT NULL Partial Index Point Lookup

SQLite uses a partial index whose predicate is `a IS NOT NULL` for point
lookups such as `a=5`, because the equality constraint implies the partial
predicate. The native PHP reader now recognizes a `CREATE INDEX` partial
predicate of the form `WHERE <first-column> IS NOT NULL` and allows that index
for non-null point lookups only. Other partial predicates continue to be
rejected for `wp_options` option-name lookup until broader predicate implication
is ported.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` verifies partial-index creation and the planner's use of
  `CREATE INDEX t2a1 ON t2(a) WHERE a IS NOT NULL` for `SELECT * FROM t2
  WHERE a=5`, while refusing to use unrelated partial indexes for queries that
  do not imply the predicate.

The native PHP tests now cover parsing qualified/quoted `IS NOT NULL` partial
predicates, keeping `indexRootPageForColumn()` unconstrained, exposing a
point-lookup root-page helper, resolving a Application-shaped
`wp_options(option_name) WHERE option_name IS NOT NULL` index, and continuing
to reject an unsupported `WHERE autoload='yes'` partial index for generic
option-name point lookup.

## Focused Native Mapping: OR Equality Partial Predicates

SQLite's partial-index planner allows an index whose predicate is an OR
expression when one query WHERE term implies one OR arm. The native PHP reader
now maps that bounded rule for OR predicates made of simple equality terms,
which is enough for Application recovery callers that know a concrete autoload
state and need to use a narrowed `wp_options(option_name)` index.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` verifies OR partial-index usability boundaries with
  `CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200`, including the requirement
  that a query term imply one OR arm before the partial index is usable.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` are the focused source boundaries: AND predicates
  must have every term usable, while OR predicates are usable if one branch is
  implied.

The native PHP tests now parse `WHERE autoload='yes' OR autoload='on'`, expose
the OR predicate tree, use the partial option-name index when the caller
supplies either matching autoload equality, and reject `autoload='no'`.
Comparison OR terms and custom collation-aware predicate comparison remain
outside this slice.

## Focused Native Mapping: AND-Connected Partial Predicates

SQLite uses a partial index with AND-connected WHERE terms only when all terms
are implied by the query. The native PHP reader now maps that bounded rule for
AND predicates composed of simple equality and `IS NOT NULL` terms, which
covers narrowed Application recovery indexes such as
`WHERE autoload='yes' AND option_name IS NOT NULL`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index6.test index7.test indexA.test
```

Result: 6 Tcl script/permutation runs, 0 errors out of 300 tests in 00:00.

Focused upstream fixture boundary:

- `test/index6.test` section `index6-10.*` creates
  `CREATE INDEX t10x ON t10(d) WHERE a=1 AND b=2 AND c=3`, verifies use when
  all equality terms are present, and verifies non-use when a term is missing.
- `test/indexA.test` includes a partial index with `WHERE b='abc' AND i=5`
  used through `INDEXED BY`, anchoring the same conjunction shape in a
  rowid-table scenario.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` are the focused source boundaries: every
  AND-connected partial-index term must be implied before the index is safe.

The native PHP tests now parse AND predicate trees, use a
`wp_options(option_name)` partial index only when both `autoload='yes'` and
`option_name IS NOT NULL` are implied by supplied point constraints, and reject
the same index for `autoload='no'` or unconstrained option-name lookups.
Expression predicates and custom collation-aware predicate comparison remain
outside this slice.

## Focused Native Mapping: Duplicate First-Column Index Scans

SQLite non-unique indexes allow multiple rows with the same first indexed key.
This slice maps the bounded read-side behavior needed by Application recovery:
scan an explicit first-column index for all records whose first key equals the
requested value, decode the rowid stored as the last index-record field, and
load the matching `wp_options` rows through the table b-tree. Composite index
tails are preserved in the index payload but are not yet used for full
multi-column seek bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test where.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 589 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-10.*` verifies that an ordinary index may
  contain more than one entry with the same key and that equality lookup
  returns all matching rows.
- `test/where.test` section `where-6.*` exercises equality constraints against
  indexed first columns and composite-index ordering boundaries.

The native PHP tests now cover a Application-shaped
`CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)`
scan that returns duplicate `autoload='yes'` options in index order, honors a
result limit, returns an empty list for missing first-column values, and also
uses a safe `WHERE autoload IS NOT NULL` partial index for non-null autoload
point scans. Remaining index work includes optimized range seeks instead of
full index traversal, expression indexes beyond `lower(column)`, custom
collations, and composite planner shapes outside equality-prefix plus one
range column.

## Focused Native Mapping: Composite Index Prefix Constraints

SQLite can use a multi-column index for equality constraints across consecutive
leading columns. This slice maps a bounded read-side variant for Application
recovery: parse explicit `CREATE INDEX` column lists, retain per-column
collation metadata for leading columns, and resolve a
`wp_options(autoload, option_name)` index when both prefix values are known.
The current implementation still traverses the bounded native index reader
rather than performing lower/upper b-tree seek bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where4.test whereH.test index8.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 141 tests in 00:00.

Focused upstream fixture boundary:

- `test/where4.test` checks multi-column index constraints such as `w=1 AND x
  IS NULL AND y=3` and verifies that all constrained leading columns affect
  index lookup behavior.
- `test/whereH.test` verifies planner preference for the longer composite index
  when `a=? AND b=?` or deeper leading constraints make it more specific than a
  shorter candidate index.
- `test/index8.test` covers scoring for index scans where later indexed columns
  are relevant to filtering or ordering.

The native PHP tests now cover parsing full explicit index column lists,
rejecting expression-bearing composite lists for this slice, preserving
second-column `COLLATE NOCASE` metadata, accepting an implied
`WHERE autoload IS NOT NULL` partial predicate, and fetching one
Application option through `wp_options(autoload, option_name)` without scanning
the whole `wp_options` table. Remaining index work includes b-tree seek bounds
for broader composite prefixes, expression indexes beyond `lower(column)`, and
custom collations.

## Focused Native Mapping: First-Column Range Constraints

SQLite can use an index for bounded first-column range constraints such as
`a>=100 AND a<300`. This slice maps a native read-side subset for Application:
given an explicit or safe `WHERE option_name IS NOT NULL` partial
`wp_options(option_name)` index, scan decoded index records and return rows
whose first key is greater than or equal to a lower bound and less than an upper
bound under the index collation.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test index6.test index7.test
```

Result: 8 Tcl script/permutation runs, 0 errors out of 415 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` checks indexed first-column text
  comparison boundaries for `>`, `>=`, `<`, and `<=`.
- `test/wherelimit3.test` verifies planner use of `SEARCH ... USING INDEX`
  for lower/upper range constraints such as `a>=100 AND a<300`.
- `test/index6.test` and `test/index7.test` verify that `a IS NOT NULL`
  partial indexes may be used when the query predicate implies non-nullness.

The native PHP tests now cover a Application-shaped transient recovery range
``option_name >= '_transient_' AND option_name < '_transient`'``, result limiting,
empty ranges, rowid resolution back through the table b-tree, and safe use of a
partial `WHERE option_name IS NOT NULL` index for non-null range bounds.
Remaining range work includes true b-tree lower/upper seek bounds instead of
full native index traversal for broader planner shapes, expression indexes
beyond `lower(column)`, and custom collations.

## Focused Native Mapping: Open-Ended And Inclusive Range Variants

SQLite range constraints can be lower-only, upper-only, or inclusive on either
side. This slice keeps the native reader bounded to first-column index records
but extends the Application-facing range helper to support nullable open bounds,
inclusive upper bounds, and explicit range-root discovery when at least one
bound is present. Bounded comparisons now skip `NULL` first-column index keys
so `option_name < 'm'` behaves like SQL comparison semantics instead of
treating `NULL` as a matching low sentinel.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 275 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` covers indexed comparison boundary
  variants including inclusive lower/upper operators.
- `test/wherelimit3.test` covers planner use of indexed lower and upper range
  constraints across bounded queries.

The native PHP tests now cover upper-only ranges, inclusive upper ranges,
lower-only ranges, result limits, explicit range-root lookup, safe use of
`WHERE option_name IS NOT NULL` partial indexes when any non-null bound implies
the predicate, rejection of unconstrained partial ranges, and descending
`wp_options(option_name DESC)` index traversal for inclusive bounded scans.
Remaining range work includes true b-tree lower/upper seek bounds instead of
full native index traversal for broader planner shapes, expression indexes
beyond `lower(column)`, and custom collations.

## Focused Native Mapping: Comparison And BETWEEN Partial Predicates

SQLite can use a partial index when query terms imply comparison predicates in
the partial-index WHERE clause, including bounded comparison terms and
`BETWEEN` ranges. This slice maps a conservative native read-side subset:
parse `<`, `<=`, `>`, `>=`, `!=`, `<>`, and `BETWEEN` predicates in explicit
`CREATE INDEX` statements, preserve AND/OR predicate trees without splitting
the `AND` inside `BETWEEN`, and use the partial index only when supplied point
or range constraints are contained by the parsed predicate.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index6.test index7.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 140 tests in 00:00.

Focused upstream fixture boundary:

- `test/index6.test` creates partial indexes such as
  `CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200` and verifies use only when
  the query includes an implying comparison term.
- `test/index6.test` also creates
  `CREATE INDEX t3b ON t3(b) WHERE xyzzy.t3.b BETWEEN 5 AND 10`, anchoring
  database-qualified `BETWEEN` predicates.
- `test/index7.test` repeats the same partial-index planner boundaries for
  WITHOUT ROWID tables.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` remain the source boundary for safe partial-index
  use: a partial index is an optimization only when the query term implies the
  partial WHERE term.

The native PHP tests now cover parsing comparison and `BETWEEN` partial
predicates, using a Application-shaped
``wp_options(option_name) WHERE option_name >= '_transient_' AND option_name < '_transient`'``
partial index for transient point and range recovery, rejecting the same index
for out-of-range option names, and using an inclusive `BETWEEN` partial index
for bounded transient scans. Remaining work includes optimized b-tree seek
bounds for broader planner shapes, expression indexes beyond `lower(column)`,
and custom collations.

## Focused Native Mapping: Composite Equality-Prefix Range Constraints

SQLite can use a composite index when the left-most indexed column or columns
are constrained by equality and the next indexed column has range bounds. This
slice maps the read-side Application shapes
`wp_options(autoload, option_name)` and
`wp_options(autoload, option_value, option_name)`: constrain the equality
prefix first, then scan bounded `option_name` keys for transient-style recovery
queries without decoding the whole options table.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where.test whereH.test
```

Result rerun on 2026-05-23 for this multi-equality-prefix slice: 2 Tcl
script/permutation runs, 0 errors out of 335 tests in 00:00.

Focused upstream fixture boundary:

- `test/where.test` covers composite-index constraints such as `x=3 AND y<100`,
  `x=3 AND y>=121 AND y<=196`, and ascending/descending ordered range scans on
  the second indexed term.
- `test/whereH.test` covers longer equality prefixes before a range term, such
  as `a=? AND b=? AND c>=?` against a three-column index.

The native PHP tests now cover Application-shaped
`wp_options(autoload, option_name)` and
`wp_options(autoload, option_value, option_name)` indexes for non-autoloaded
transient range recovery, range limits, inclusive and empty same-bound ranges,
safe rejection when no range bound is provided, and a partial
`autoload='no' AND option_name IS NOT NULL` composite index with `NOCASE DESC`
second-column metadata. This run adds bounded composite b-tree traversal:
subtrees whose separator-key intervals cannot contain the requested
equality prefix plus `option_name` range are skipped before their pages are
decoded. Application-shaped fixtures keep matching transient rows in one index
branch and make unrelated branches invalid, proving the native reader does not
need healthy out-of-range index pages for constrained recovery even with two
equality columns. Remaining work includes expression indexes beyond the named
families, custom collations, expression `IN (...)` lookups, and composite range
planner shapes outside equality-prefix plus one range column.

## Focused Native Mapping: Equality Partial Predicates

SQLite can use a partial index with an exact equality predicate when the query
predicate implies the partial-index WHERE clause. This slice maps the bounded
read-side form needed for Application recovery: parse simple partial-index
predicates such as `autoload='yes'`, require callers to supply the matching
equality constraint, and then use the `wp_options(option_name)` partial index
only for constrained autoloaded option lookups.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` section `index7-6.*` creates `CREATE INDEX i4 ON t4(c)
  WHERE d='xyz'` and verifies that `WHERE d='xyz' AND c='def'` searches the
  partial index.
- The same script continues to cover `IS NOT NULL` implication for point
  lookups and range-compatible non-null predicates.

The native PHP tests now cover parsing equality partial predicates, rejecting
the partial index for unconstrained `option_name` lookups, accepting it only
when `autoload='yes'` is supplied as an equality constraint, resolving matching
rowids back through the table b-tree, and refusing to use the index for a
non-implying autoload value. Remaining partial-index work includes
inequality/range implication, richer expression handling, and planner-style
combinations across more query terms.

## Focused Native Mapping: IN-List Option Lookups

SQLite's `IN (...)` operator treats duplicate RHS values as a set for result
rows and does not match `NULL` RHS terms in a `WHERE` predicate. The native PHP
reader now maps that bounded first-column behavior for indexed
`wp_options(option_name)` reads, including built-in collation handling and
limits.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where.test where2.test index6.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 490 tests in 00:00.

Focused upstream fixture boundary:

- `test/where.test` section `where-5.*` covers indexed `IN` lookups with
  reordered RHS values and ascending/descending output.
- `test/where2.test` section `where2-4.6*` verifies duplicate RHS values do
  not duplicate output rows.
- `test/index6.test` covers partial-index planner boundaries for `IS NOT NULL`
  and exact `IN (...)` predicates, including the upstream behavior where a
  partial `IN` predicate is only usable for an exact matching `IN` query shape.

The native PHP tests now cover bulk Application option-name reads through an
explicit `option_name` index, duplicate RHS suppression, `NULL` RHS
non-matching semantics, safe use of `WHERE option_name IS NOT NULL` partial
indexes, and exact-order `WHERE option_name IN ('siteurl','home')` partial
indexes. IN-list scans now also derive conservative first-key intervals for
index interior children, so out-of-range subtrees are skipped before their
pages are parsed. The focused regression fixture uses a Application-shaped
`wp_options(option_name)` lookup for `siteurl` while the unrelated left-hand
index branch is intentionally invalid. The new
`examples/application-options-by-name-list.php` script maps bulk option
preload/recovery workflows on hosts without the PHP SQLite extension.
Remaining work includes expression-index `IN` lookup families beyond
`lower(column)`, custom collations, and broader composite `IN` constraints.

## Focused Native Mapping: Lower Expression IN-List Option Lookups

SQLite can use an expression index for an `IN (...)` predicate when the query
expression matches the indexed expression. The native PHP reader now maps the
Application-oriented `lower(option_name) IN (...)` slice: caller-supplied names
are case-folded with SQLite-style ASCII lowercasing, duplicate RHS names do not
duplicate result rows, `NULL` RHS terms do not match, and safe
`WHERE option_name IS NOT NULL` partial expression indexes can be used.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` sections `indexexpr1-150` and `indexexpr1-250`
  verify expression-index `IN (...)` probes and planner use for
  `substr(a,b,3) IN (...)` on rowid and WITHOUT ROWID tables.
- `test/where2.test` section `where2-4.6*` verifies duplicate RHS values do
  not duplicate output rows for indexed `IN` probes.

The native PHP tests now cover `wp_options(lower(option_name) COLLATE NOCASE)`
IN-list reads for mixed-case `SiteURL`/`HOME` option names, duplicate RHS
suppression, `NULL` RHS non-matching behavior, rejection as a plain
`option_name` index, limit handling, invalid RHS types, and bounded lower-key
seek pruning where an out-of-range index branch is intentionally unreadable.
The new `examples/application-lowercase-options-by-name-list.php` script maps
case-folded bulk option preload/recovery workflows on hosts without the PHP
SQLite extension.

## Focused Native Mapping: Upper Expression Option Lookups

This slice adds first-term `upper(option_name)` expression-index discovery and
lookup. SQLite's built-in `upper()` function is bytewise ASCII-only without the
ICU extension, so the native PHP reader now applies the same ASCII uppercase
mapping to caller-supplied option names and to row verification after the index
points back to the `wp_options` table row. The implementation intentionally
accepts only safe `option_name IS NOT NULL` partial expression indexes for this
new path. It now covers point, `IN (...)`, and bounded range scans over the
stored uppercase expression keys.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  func.test indexexpr1.test
```

Result: 2 Tcl scripts, 0 errors out of 15138 tests in 00:01.

Focused range runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  func.test indexexpr1.test where.test
```

Result: 3 Tcl scripts, 0 errors out of 15456 tests in 00:02.

Focused upstream fixture boundary:

- `src/func.c` implements `upper()` and `lower()` by applying
  `sqlite3Toupper()`/`sqlite3Tolower()` byte by byte.
- `test/func.test` section `func-5.*` verifies `upper()`/`lower()` behavior
  and argument-count boundaries.
- `test/indexexpr1.test` verifies deterministic expression-index lookup and
  planner use for scalar expression keys, including the existing `lower(a)`
  expression-index family.
- `test/where.test` covers lower/upper range constraints, inclusive bounds,
  and index-ordered range traversal boundaries used by this native uppercase
  expression range slice.

The native PHP tests now cover parsing `upper(option_name)` metadata without
mistaking it for an ordinary column index, point lookup through
`wp_options(upper(option_name))`, IN-list lookup with duplicate RHS
suppression and `NULL` non-matching behavior, SQLite-style ASCII-only folding
for a non-ASCII option name such as `café`, bounded uppercase range scans,
out-of-range b-tree branch pruning for range reads, and rejection as a plain
`option_name` index. The new
`examples/application-uppercase-options-by-name-list.php` and
`examples/application-uppercase-option-name-range.php` scripts map bulk and
range-based ASCII-folded option recovery on hosts without the PHP SQLite
extension.

## Focused Native Mapping: First-Column B-Tree Seek Bounds

SQLite range and equality probes over an index move a b-tree cursor to the
bounded key interval rather than decoding unrelated branches. The native PHP
reader now maps that bounded read-side behavior for first-column point and
range scans by deriving conservative first-key intervals for index interior
children. Out-of-range subtrees are skipped before their pages are parsed, while
matching leaf and interior records still use the existing SQLite scalar
comparison rules and rowid resolution.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test where.test
```

Result: 5 Tcl script/permutation runs, 0 errors out of 593 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` covers indexed first-column comparison
  boundaries for `>`, `>=`, `<`, and `<=`.
- `test/wherelimit3.test` records planner output such as
  `SEARCH ... USING INDEX ... (a>? AND a<?)`, anchoring the same lower/upper
  index-search shape used by this native slice.
- `test/where.test` covers equality and range constraints against indexed first
  columns and composite index boundaries.

The native PHP test adds a Application-shaped `wp_options(option_name)` range
lookup where the requested lower bound is in the index root's right-hand
subtree and the left-hand child page is intentionally invalid. The lookup now
returns `siteurl` without reading that out-of-range branch. Remaining seek work
includes expression indexes beyond the first `lower(column)`/`upper(column)` slices and
expression seek bounds.

## Focused Native Mapping: Substr Expression Index Prefixes

SQLite expression indexes can use deterministic scalar expressions such as
`substr(column,start,length)` as the indexed key. The native PHP reader now
parses first-term `substr()`/`substring()` expression-index metadata when the
start and optional length are positive integer literals, preserves built-in
collation and `DESC` metadata, and uses `substr(option_name,1,N)` expression
indexes for Application option-name prefix scans. Partial expression indexes are
accepted only for the safe `option_name IS NOT NULL` predicate family in this
slice.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test
```

Result: 2 Tcl scripts, 0 errors out of 234 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `CREATE INDEX ... ON t1(substr(a,1,12))`,
  equality predicates with the expression on either side, composite
  expression indexes such as `(b, substr(a,2,3), c)`, and expression
  collations such as `substr(b,2,4) COLLATE nocase`.
- `test/indexexpr2.test` covers `substr(a, 2) COLLATE NOCASE` expression
  index ordering and lookup behavior.

The native PHP tests now cover parser rejection for variable `substr()` starts,
expression metadata for qualified and quoted column names, and a
Application-shaped `wp_options(substr(option_name,1,11) COLLATE NOCASE)` index
that returns `_transient_` option buckets without using the SQLite extension.
The `examples/application-option-name-prefix.php` script maps transient/cache
bucket inspection on hosts where only a database image is available. Remaining
expression-index work includes variable-start `substr(a,b,3)`, expression
`IN` lookups beyond the literal-start prefix-list slice, `abs()`,
broader `json_extract()` paths beyond the later strict `$.key` point slice,
arbitrary deterministic expressions, and custom collations.

## Focused Native Mapping: Substr Expression Index Prefix IN Lists

SQLite can probe expression indexes with `IN (...)` constraints. This slice
adds a bounded first-term `substr(option_name,1,N) IN (...)` path to the native
reader. The implementation accepts same-length non-empty prefix values, ignores
`NULL` RHS values for matching, suppresses duplicate RHS row output by scanning
index records once, honors built-in collation and `DESC` metadata, and uses the
existing bounded index traversal so out-of-range expression-index subtrees do
not need to be readable.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies expression-index `IN` probes such as
  `substr(a,b,3) IN ('and','l_t','xyz')` use the expression index and return
  only matching rows.
- `test/where2.test` covers duplicate RHS `IN` values without duplicate output
  rows, which maps the native prefix-list scan behavior.

The native PHP tests now cover a Application-shaped
`wp_options(substr(option_name,1,11) COLLATE NOCASE)` index that reads both
`_transient_` and `_site_trans` buckets from one prefix list, ignores `NULL`
RHS values, rejects mixed prefix lengths, and prunes an intentionally invalid
out-of-range index branch. The new
`examples/application-option-name-prefix-list.php` script maps cache and
site-transient recovery on hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: Negative-Start Substr Expression Index Suffix Buckets

This slice extends the bounded `substr(column,...)` parser from positive
literal starts to SQLite's negative-start expression-index shape. A first-term
`substr(option_name,-N)` index stores the last `N` characters of each option
name. The native PHP reader now preserves the negative start, rejects the
unsupported zero start, and can use the stored suffix key with built-in
collations before resolving the rowid tail through `wp_options`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test
```

Result: 1 Tcl script, 0 errors out of 127 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr2.test` creates `CREATE INDEX i4 ON t4( Substr(a,-2) COLLATE
  nocase )` and verifies the planner can scan that expression index for
  negative-start suffix ordering.
- The same script covers nearby expression-index collation boundaries for
  `substr(a, 2) COLLATE NOCASE`.

The native PHP tests now cover parsing `Substr(option_name,-9) COLLATE NOCASE
DESC`, rejecting start `0`, using a Application-shaped
`wp_options(substr(option_name,-9) COLLATE NOCASE DESC)` index to find
`*_settings` options case-insensitively, limit handling, and continuing to
reject expression indexes as ordinary column indexes. The new
`examples/application-option-name-suffix.php` script maps plugin/theme settings
bucket inspection when only a SQLite database image is available.

## Focused Native Mapping: Length Expression Index Buckets

This slice adds a second bounded scalar expression family beyond
`lower(column)` and positive-start `substr(column,...)`: first-term
`length(column)` expression indexes. The native PHP reader parses
`CREATE INDEX ... ON wp_options(length(option_name))`, preserves `DESC`
metadata, rejects the expression as an ordinary column index, and searches the
stored integer expression key before resolving the rowid tail through the
`wp_options` table b-tree. Partial expression indexes remain limited to the
safe `option_name IS NOT NULL` predicate family for this slice.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test
```

Result: 1 Tcl script, 0 errors out of 107 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` creates `CREATE INDEX t1alen ON t1(length(a))` and
  verifies the expression index can provide covering order for `length(a)`.
- The same file covers expression-index matching boundaries and deterministic
  function restrictions for nearby expression-index cases.

The native PHP tests now cover parsing `length(option_name)` metadata with
qualified/quoted column names, `DESC`, and safe `WHERE option_name IS NOT NULL`
predicates; rejecting constant and unrelated expression terms; and a
Application-shaped exact-length option-name bucket lookup that returns options
such as `home`, `cron`, and UTF-8 text names using SQLite-style character
length without scanning the whole table. The new
`examples/application-option-name-length.php` script maps recovery or audit tools
that bucket suspicious, short, or policy-sensitive Application option names on
hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: Length Expression Index IN Lists

SQLite's `IN (...)` lookup behavior also applies to expression-index keys.
This slice extends the existing first-term `length(option_name)` expression
path from one exact bucket to a bounded integer length list. The native PHP
reader validates non-negative integer RHS values, ignores `NULL` RHS values
for matching, suppresses duplicate RHS output by scanning index records once,
honors `DESC` metadata, and prunes out-of-range index subtrees before page
decoding.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `length(a)` expression-index keys and nearby
  expression-index planner matching.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.

The native PHP tests now cover a Application-shaped
`wp_options(length(option_name) DESC)` index that reads multiple length buckets
such as `4` and `10` in one pass, rejects non-integer and negative RHS values,
ignores `NULL` RHS values, preserves UTF-8 character length behavior for
stored option names, and skips an intentionally invalid out-of-range index
branch. The new `examples/application-option-name-length-list.php` script maps
multi-bucket option-name audits on hosts where the PHP SQLite extension is
unavailable.

## Focused Native Mapping: Length Expression Index Ranges

SQLite indexed range behavior also applies to expression-index keys. This
slice extends the first-term `length(option_name)` expression path from exact
and `IN (...)` buckets to bounded integer ranges. The native PHP reader
accepts nullable lower/upper bounds, optional inclusive upper bounds, rejects
negative length bounds, honors `DESC` metadata, accepts only safe
`option_name IS NOT NULL` partial predicates, and prunes out-of-range index
subtrees before page decoding.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where.test
```

Result: 2 Tcl scripts, 0 errors out of 425 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `length(a)` expression-index keys and nearby
  expression-index planner matching.
- `test/where.test` covers indexed lower/upper range constraints and inclusive
  bound behavior used by the native bounded expression-index range traversal.

The native PHP tests now cover a Application-shaped
`wp_options(length(option_name) DESC)` index that reads medium-length option
names such as `db_version` and `siteurl`, supports exact inclusive single
length ranges, open bounds, limit handling, UTF-8 character length checks, and
skips an intentionally invalid out-of-range index branch. The new
`examples/application-option-name-length-range.php` script maps option-name
length audits on hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: CAST AS INTEGER Expression Indexes

This slice adds a bounded expression-index family for
`CAST(column AS INTEGER)`. The native PHP reader parses first-term
`CAST(option_value AS INTEGER)` expression indexes, keeps `DESC` metadata,
rejects the expression as an ordinary column index, accepts only the safe
`option_value IS NOT NULL` partial-predicate family, and searches stored
integer expression keys before resolving rowids through `wp_options`. It now
supports both exact point lookup and bounded `IN (...)` lookup over integer
cast buckets, plus nullable lower/upper range bounds for integer cast audits.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 219 tests in 00:00.

Focused range runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test where.test
```

Result: 2 Tcl scripts, 0 errors out of 445 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr2.test` creates `CREATE INDEX x1i ON x1( CAST(b AS INTEGER) )`
  and verifies `CAST(b AS INTEGER)=123` matches integer, text, mixed text
  such as `123abc`, and real storage-class values.
- The same file covers nearby expression-index planner boundaries and
  expression collation behavior.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.
- `test/where.test` covers lower/upper range constraints, inclusive bounds,
  and index-ordered range traversal boundaries used by this native cast range
  slice.

The native PHP tests now cover parsing `CAST(option_value AS INTEGER)` with a
qualified/quoted column, `DESC`, and a safe `WHERE option_value IS NOT NULL`
predicate; rejecting `CAST(... AS TEXT)` and constant casts for this bounded
slice; and a Application-shaped numeric option-value lookup that finds
`db_version`-style rows through SQLite's text-prefix integer cast semantics.
The IN-list tests read multiple integer cast buckets in one index pass, ignore
`NULL` RHS values, suppress duplicate RHS output, reject non-integer terms for
this bounded API, and skip an intentionally invalid out-of-range index branch.
The range tests scan `CAST(option_value AS INTEGER)` keys through descending
and ascending expression indexes, handle open and inclusive upper bounds, keep
SQLite-style casts such as `123.9` and non-numeric text, reject unbounded range
calls, and skip an intentionally invalid out-of-range index branch.
The new `examples/application-option-value-integer.php` and
`examples/application-option-value-integer-list.php` scripts map recovery or
audit tools that need one or more numeric option values without a full table
scan or the PHP SQLite extension. The new
`examples/application-option-value-integer-range.php` script maps numeric option
audits such as version/counter ranges through the same native index path.

## Focused Native Mapping: JSON Extract Expression Indexes

This slice adds a bounded expression-index family for
`json_extract(column,'$.key')`. The native PHP reader parses first-term
`json_extract(option_value,'$.key')` expression indexes, preserves collation
and `DESC` metadata, rejects the expression as an ordinary column index,
accepts only safe `option_value IS NOT NULL` partial predicates, and searches
stored JSON scalar expression keys before resolving rowids through
`wp_options`. The verification step evaluates strict JSON option values with
the same simple object-member path before returning a row.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test
```

Result: 1 Tcl script, 0 errors out of 14 tests in 00:00.

Focused IN-list runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 106 tests in 00:00.

Focused range runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test where.test
```

Result: 2 Tcl scripts, 0 errors out of 332 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr3.test` creates
  `CREATE INDEX i1 ON t1( json_extract(j, '$.x') )` and verifies SQLite can
  satisfy `json_extract()` reads from expression-index payloads without
  re-running the function for covered cases.
- The same file creates `CREATE INDEX i1 ON t1( a, json_extract(j, '$.x') )`
  and checks the composite expression-index planner boundary for `a=?`.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.
- `test/where.test` covers lower/upper range constraints, inclusive bounds,
  and index-ordered traversal boundaries used by the native JSON scalar range
  slice.

The native PHP tests now cover parsing `json_extract(option_value,'$.enabled')`
metadata with qualified/quoted column names, literal JSON paths, collation,
`DESC`, and safe partial predicates; rejecting constant JSON arguments and
multi-path calls for this bounded slice; and a Application-shaped plugin settings
lookup that reads boolean/number JSON scalar keys from
`wp_options(option_value)` without scanning the full table. The IN-list tests
read multiple JSON scalar buckets in one index pass, honor `COLLATE NOCASE`,
ignore `NULL` RHS values for matching, suppress duplicate RHS output, reject
unsupported lookup values, and skip an intentionally invalid out-of-range index
branch. The range tests read numeric JSON priority bands with open or inclusive
upper bounds, suppress JSON null/missing expression keys for bounded
comparisons, honor `COLLATE NOCASE` for text ranges, and skip an intentionally
invalid out-of-range index branch. The new
`examples/application-json-option-value.php`,
`examples/application-json-option-value-list.php`, and
`examples/application-json-option-value-range.php` scripts map recovery or audit
tools that need one or more indexed plugin/theme JSON settings such as enabled
flags, mode lists, or priority bands on hosts where the PHP SQLite extension is
unavailable.

## Focused Native Mapping: JSON Array Path Expression Indexes

This slice extends the bounded JSON expression-index path parser from simple
object members to non-negative array indexes. The native PHP reader now treats
paths such as `$.rules[0].enabled`, `$."rules"[0].enabled`, and `$[0]` as
valid supported paths for `json_extract(...)` and compatible `->>` expression
indexes. JSONB, JSON5, append/edit behavior for `[#]`, and full JSON path
mutation behavior remain outside this slice.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test json101.test json102.test
```

Result: 3 Tcl scripts, 0 errors out of 609 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr3.test` keeps the expression-index payload baseline for
  `json_extract(j,'$.x')` and composite expression-index reads.
- `test/json101.test` covers nested array/object paths such as
  `$[1].""[1].hi`.
- `test/json102.test` covers `json_extract(...,'$[N]')` and `->>` array-index
  path behavior, including the distinction between object labels and array
  indexes.

The native PHP tests now cover parsing `json_extract(option_value,
'$.rules[0].enabled')`, matching a quoted object-member request
`$."rules"[0].enabled` to the same stored expression path, and resolving
Application plugin settings where a first rule's `enabled` flag is stored inside
a JSON array. A second Application-shaped fixture covers `option_value ->> '[0]'`
expression indexes for root-array settings such as plugin channel lists. The updated
`examples/application-json-option-arrow.php` accepts bracket and numeric array
operands, and the new `examples/application-json-array-option-value.php` script
documents the array-path recovery flow directly.

## Focused Native Mapping: JSON Reverse Array Path Expression Indexes

This slice maps SQLite's read-side `[#-N]` JSON path extension for expression
indexes. The native PHP reader now treats `$.rules[#-1].enabled`,
`$."rules"[#-000001].enabled`, `option_value ->> '[#-1]'`, and
`option_value ->> -1` as equivalent reverse-array paths for indexed JSON scalar
lookups. `[#]` is parsed as SQLite's append-position marker and returns
not-found/null for extraction, while malformed forms such as `[#-]`, `[#9]`,
and `[#-1x]` remain path errors.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json105.test
```

Result: 1 Tcl script, 0 errors out of 53 tests in 00:00.

Focused upstream fixture boundary:

- `test/json105.test` verifies `json_extract(j,'$.b[#-1]')`,
  `json_extract(j,'$.b[#-2]')`, leading-zero reverse offsets such as
  `[#-02]`, out-of-range reverse offsets returning NULL, nested reverse
  lookups such as `$.b[#-2][#-1]`, multi-path extraction with `[#-1]`, and
  malformed reverse/append path errors.
- `src/json.c` `jsonLookupStep()` counts array entries for `[#]`, subtracts a
  parsed `-N` reverse offset, returns not-found when the offset is outside the
  array, and treats malformed `#` syntax as a path error.
- The `->>` operator path normalization in `src/json.c` maps negative integer
  operands to `$[#-N]`, which is the boundary used by the native
  `option_value ->> -1` expression-index parser.

The native PHP tests now cover reverse JSON path metadata parsing, leading-zero
reverse path equivalence, malformed reverse path rejection, nested Application
plugin rule lookups through `json_extract(option_value,'$.rules[#-1].enabled')`,
and root-array channel recovery through `option_value ->> -1`. The new
`examples/application-json-last-array-option-value.php` script maps plugin/theme
settings that store the active channel, latest migration stage, or last rule in
a JSON array and need indexed recovery without the PHP SQLite extension.

## Focused Native Mapping: JSON `->>` Expression Indexes

This slice extends the bounded JSON expression-index family to SQLite's
`->>` text operator. The native PHP reader now recognizes first-term
`column ->> 'key'` expression indexes as equivalent to the supported simple
object-member path `json_extract(column,'$.key')`, preserves collation and
`DESC` metadata, and still rejects the expression as an ordinary column index.
The lookup path compares parsed JSON path segments, so `$.enabled` and
`$."enabled"` requests can resolve the same supported expression index. String
RHS operands that are not full JSON paths are now treated as object labels, so
numeric-looking or dotted labels such as `'2'` and `'plugin.enabled'` normalize
to quoted JSON path members instead of array indexes or nested paths.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr3.test
```

Result: 2 Tcl scripts, 0 errors out of 121 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` creates expression indexes such as
  `CREATE INDEX t1_one ON t1(a, b->>'one')` and verifies SQLite can use those
  expression-index payloads for covered JSON text-operator reads.
- `test/indexexpr3.test` keeps the existing `json_extract(j,'$.x')`
  expression-index baseline for JSON scalar expression behavior.

The native PHP tests now cover parsing `option_value ->> 'enabled'` metadata
with a qualified/quoted column, `COLLATE NOCASE`, `DESC`, and a safe partial
predicate; dotted and numeric string RHS label normalization; path equivalence
between `$.enabled` and `$."enabled"`; and a Application-shaped plugin settings
lookup that reads boolean JSON keys through an arrow-operator expression index
without scanning `wp_options`. The
`examples/application-json-option-arrow.php` script maps plugin/theme settings
recovery where the available SQLite database uses `option_value ->> 'key'`
instead of a `json_extract(...)` index.

## Focused Native Mapping: JSON `->` Expression Indexes

This slice extends JSON expression-index handling to SQLite's `->` value
operator. Unlike `->>`, the `->` operator returns JSON text: JSON null becomes
the text `null`, strings stay quoted, and object/array fragments remain JSON
text. Missing paths still compare as SQL `NULL`. The native PHP reader now
parses first-term `column -> 'key'` expression indexes separately from `->>`,
normalizes PostgreSQL-style string RHS labels such as `'2'` and
`'plugin.enabled'` to quoted JSON path members, preserves collation, `DESC`,
and safe partial-predicate metadata, and exposes a bounded Application
`wp_options` lookup for JSON fragments.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick json102.test
```

Result: 1 Tcl script, 0 errors out of 317 tests in 00:00.

Focused upstream fixture boundary:

- `test/json102.test` covers `->` and `->>` operator behavior for JSON null,
  integers, reals, strings, arrays, objects, missing paths, array-index RHS
  operands, and string RHS operands that look numeric but must address object
  labels.

The native PHP tests cover parsing `option_value -> 'settings.v1'` as a JSON
value-operator expression index rather than a normal column or `->>` text
operator index; matching dotted and numeric string labels through quoted JSON
path members; seeking a Application-shaped `wp_options` index by JSON object
fragment, JSON string, and JSON null; and keeping missing paths distinct from
JSON null. The `examples/application-json-option-fragment.php` script maps
plugin/theme settings recovery where the available SQLite database indexes a
JSON fragment through `option_value -> 'key'`.

This slice now also covers JSON `->` fragment IN-list and range lookups.
Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json102.test where.test where2.test
```

Result: 3 Tcl scripts, 0 errors out of 727 tests in 00:00.

Focused upstream fixture boundary:

- `test/json102.test` anchors `->` JSON-text result semantics for JSON null,
  strings, arrays, objects, missing paths, and PostgreSQL-style RHS labels.
- `test/where2.test` covers IN-list duplicate RHS behavior used by the native
  JSON fragment list lookup.
- `test/where.test` covers lower/upper indexed range traversal used by the
  native JSON fragment range lookup.

The native PHP tests now cover JSON `->` expression-index IN-list lookups for
object fragments, JSON strings, JSON null, duplicate RHS suppression, and
missing-path exclusion. They also cover JSON-text range scans over stored
fragment keys, inclusive upper bounds, open lower bounds, reversed empty
ranges, wrong-expression rejection, and invalid limit/bound handling. The
`examples/application-json-option-fragment-list.php` and
`examples/application-json-option-fragment-range.php` scripts map plugin/theme
settings recovery for multiple JSON fragment states or bounded JSON-text
channel ranges.

## Focused Native Mapping: `trim()` Expression Indexes

This slice extends the bounded expression-index family to first-term
`trim(column)`, `ltrim(column)`, and `rtrim(column)` expressions with either
SQLite's default space trimming or a literal character-set argument. The native
reader preserves the function name, literal character set, collation, `DESC`,
and safe `IS NOT NULL` partial predicate metadata, rejects these expressions as
ordinary column indexes, and resolves a Application option row through an indexed
trimmed key plus rowid lookup.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  func.test indexexpr1.test
```

Result: 2 Tcl scripts, 0 errors out of 15138 tests in 00:01.

Focused upstream fixture boundary:

- `test/func.test` covers `trim()`, `ltrim()`, and `rtrim()` arity, default
  space trimming, literal trim-character sets, empty trim sets, NULL behavior,
  and UTF-8 character boundaries.
- `test/indexexpr1.test` covers deterministic expression-index planning and
  equality lookups through expression-index payloads.

The native PHP tests now cover parsing `trim(option_name,' _')`,
`ltrim(option_name)`, and `rtrim(option_name,'-')` index metadata, rejecting
constant/non-string trim arguments for this bounded slice, preserving safe
partial predicates, and a Application-shaped `trim(option_name) COLLATE NOCASE`
lookup that finds a stored option named ` SiteURL  ` when the recovery caller
asks for `siteurl`. The new
`examples/application-trimmed-option-name.php` script maps recovery of
whitespace-damaged option names without requiring the PHP SQLite extension or a
full table scan.

## Focused Native Mapping: JSON5 Indexed Option Reads

This slice keeps the existing expression-index lookup boundary but broadens row
verification from strict RFC-8259 JSON to SQLite's JSON5 input subset. The
native reader now falls back to a bounded JSON5 parser when `json_decode()`
rejects an `option_value`: unquoted object keys, single-quoted strings,
single trailing commas, C-style comments, JSON5 whitespace, hexadecimal and
signed numeric forms, `Infinity`, and `NaN` are decoded for
`json_extract(...)`, `->>`, and `->` expression-index verification. Malformed
JSON5 still rejects the row instead of silently trusting the index payload.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json501.test json502.test
```

Result: 2 Tcl scripts, 0 errors out of 198 tests in 00:00.

Focused upstream fixture boundary:

- `test/json501.test` covers JSON5 unquoted IdentifierName object keys,
  trailing object/array commas, single-quoted strings, comments, additional
  whitespace, hex/signed/Infinity/NaN numeric forms, and malformed comma
  rejection.
- `test/json502.test` covers JSON5 nested objects with trailing commas,
  malformed object labels, escaped label names, and JSON path label escaping.

The native PHP tests cover Application-shaped `wp_options` rows whose
`option_value` contains JSON5-style plugin settings with unquoted keys,
single-quoted text, comments, and trailing commas, then read those rows through
`json_extract(option_value,'$.enabled')` and
`json_extract(option_value,'$.rules[#-1].enabled')` expression indexes. A
separate malformed JSON5 fixture verifies that corrupt manually edited plugin
settings are still rejected during indexed row verification. The new
`examples/application-json5-option-value.php` script documents the recovery path
for indexed JSON5-style plugin/theme settings on hosts without the SQLite
extension.

## Focused Native Mapping: JSON5 Non-Finite Normalization

This slice tightens the JSON5 numeric boundary for `+Infinity`, `-Infinity`,
and `NaN`. SQLite accepts those JSON5 inputs, returns SQL infinities from
`->>`/`json_extract(...)`, returns JSON text fragments `9e999`, `-9e999`, or
`null` from `->`, and emits JSONB float payloads with the same normalized
`9e999`/`-9e999` text. The native reader now follows that behavior for
Application `wp_options` expression-index verification and JSONB fixture
generation instead of treating PHP non-finite floats as unencodable.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json501.test json102.test jsonb01.test
```

Result: 3 Tcl scripts, 0 errors out of 541 tests in 00:00.

Targeted upstream SQL probes:

```sql
SELECT hex(jsonb('{limit:+Infinity,disabled:-Inf,missing:NaN}'));
SELECT jsonb('{limit:+Infinity,disabled:-Inf,missing:NaN}')->>'limit';
SELECT jsonb('{limit:+Infinity,disabled:-Inf,missing:NaN}')->'limit';
```

Results: JSONB hex
`CC25576C696D69745539653939398764697361626C6564652D3965393939776D697373696E6700`,
scalar `Inf`, and JSON fragment `9e999`.

The native PHP tests now cover JSON5 decoding of positive/negative infinity and
NaN-as-null, upstream-compatible JSONB hex generation and round-trip decoding,
`json_extract(option_value,'$.limit')` expression-index lookups using SQLite
record float keys, and `option_value -> 'limit'` / `option_value -> 'missing'`
fragment lookups using `9e999`, `-9e999`, and `null` index keys. The
`examples/application-jsonb-option-fixture.php` script now prints non-finite
JSON5 settings safely while generating JSONB option-value fixture bytes.

## Focused Native Mapping: Escaped JSON Path Labels

This slice keeps the bounded JSON expression-index lookup API but broadens path
matching to SQLite's escaped object-label behavior. The native reader now
decodes JSON5-style quoted path labels such as `$."plugin\x5cenabled"`,
supports bare path labels containing embedded double quotes such as
`$.A"Key`, and normalizes `->`/`->>` string RHS label escapes such as
`a\x62c` before matching the expression index against caller-supplied paths.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json502.test
```

Result: 1 Tcl script, 0 errors out of 13 tests in 00:00.

Focused upstream fixture boundary:

- `test/json502.test` verifies escaped JSON labels, `->`/`->>` RHS path-label
  escapes such as `a\x62c`, quoted path labels containing backslash escapes,
  and bare path labels containing embedded double quotes.

The native PHP tests now cover parsing `json_extract(option_value,
'$."a\x62c"')`, `json_extract(option_value,'$.A"Key')`,
`option_value ->> 'a\x62c'`, and `option_value -> 'a\x62c'` expression-index
metadata. A Application-shaped fixture reads plugin setting rows through
expression indexes over keys named `abc`, `A"Key`, and `plugin\enabled`,
including path equivalence between escaped quoted labels and bare embedded
quote labels. The new
`examples/application-json-escaped-label-option-value.php` script documents this
recovery path for plugin/theme settings whose JSON keys come from escaped or
external identifiers.

## Focused Native Mapping: JSONB Indexed Option Reads

This slice keeps the bounded JSON expression-index lookup API but broadens row
verification from text JSON/JSON5 to SQLite JSONB blobs when a decoded
`wp_options.option_value` field came from a SQLite BLOB serial type. The native
decoder maps the JSONB header/payload format for null, booleans, integers,
floats, text, arrays, and objects, then feeds the existing path traversal used
by `json_extract(...)` and `->` expression-index verification.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json102.test jsonb01.test
```

Result: 2 Tcl scripts, 0 errors out of 356 tests in 00:00.

Focused upstream fixture boundary:

- `test/json102.test` covers JSONB input for `json_extract(...)`,
  `jsonb_extract(...)`, JSONB-producing functions such as `jsonb(...)` and
  `jsonb_array(...)`, and JSON type traversal, including a valid JSONB blob
  for `{"a":[2,3.5,true,false,null,"x"]}`.
- `test/jsonb01.test` covers JSONB-specific malformed-input handling.

Targeted upstream SQL probe:

```sh
./.upstream-cache/libsqlite-build-port-libsqlite/testfixture <<'TCL'
sqlite3 db :memory:
puts [db one {SELECT hex(jsonb('{"a":[2,3.5,true,false,null,"x"]}'))}]
TCL
```

Result: `CC0E1761BB133235332E350102001778`, matching SQLite's generated
minimal JSONB header/payload form for the focused object/array/scalar fixture.

The native PHP tests decode the upstream JSONB fixture
`x'cc0f1761cb0b133235332e350102001778'`, generate the upstream-probed minimal
JSONB bytes `x'cc0e1761bb133235332e350102001778'` from a PHP value, reject a
malformed JSONB blob, and read a Application-shaped `wp_options` row whose
`option_value` is a native-generated JSONB BLOB through both
`json_extract(option_value,'$.a[5]')` and `option_value -> '$.a'` expression
indexes. The new `examples/application-jsonb-option-fixture.php` script prepares
Application-oriented JSONB option-value fixture bytes, while
`examples/application-jsonb-option-value.php` documents recovery for plugin
settings stored by SQLite JSONB functions.

## Focused Native Mapping: JSONB Remove Paths

This slice maps a bounded `jsonb_remove()`/`json_remove()` family for native
SQLite JSONB fixture and preflight tooling. It removes object members, array
elements, reverse array indexes, and multiple paths in SQLite argument order;
missing paths and `[#]` append positions are no-ops, and root `$` removal
returns SQL `NULL` as a PHP `null`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json102.test jsonb01.test
```

Result on 2026-05-23: 2 Tcl scripts, 0 errors out of 356 tests in 00:00.

Focused upstream fixture boundary:

- `test/json102.test` covers `json_remove()` and `jsonb_remove()` parity for
  array deletion, missing array indexes, multiple path order, object member
  deletion, no-path calls, and root removal to SQL `NULL`.
- `test/jsonb01.test` covers JSONB object-member deletion, nested
  object-member deletion, array deletion, reverse array indexes such as
  `[#-1]`, missing reverse indexes, and malformed JSONB rejection.

The native PHP tests now cover `SQLiteJsonB::remove()` for object members,
nested object members, array indexes, reverse array indexes, missing path
no-ops, `[#]` no-ops, root removal, multiple path argument order, large
out-of-range indexes, and malformed path rejection. The new
`examples/application-jsonb-remove-option-field.php` script lets Application
recovery or fixture-generation tooling remove obsolete plugin setting paths
from a JSON/JSON5/JSONB `wp_options.option_value` blob and print the resulting
SQLite JSONB bytes without requiring the SQLite extension.

## Focused Native Mapping: JSONB Insert, Set, And Replace Paths

This slice maps a bounded `jsonb_insert()`/`jsonb_set()`/`jsonb_replace()`
family for native SQLite JSONB fixture and preflight tooling. It preserves the
upstream distinction between insert, set, and replace; edits existing object
members and array slots; creates missing object/array substructure where
SQLite does; appends with `[#]`; treats `[#-0]` as an append for set; applies
multiple path/value pairs in SQLite argument order; and keeps SQL string vs
JSON value behavior represented as PHP strings vs PHP arrays/objects.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json101.test json102.test json105.test jsonb01.test
```

Result on 2026-05-23: 4 Tcl scripts, 0 errors out of 687 tests in 00:00.

Focused upstream fixture boundary:

- `test/json101.test` covers JSON value vs SQL text insertion, repeated
  path/value updates, root and null-input boundaries, and created
  substructure for paths such as `$.a.b.c` and `$[3].a[0].b`.
- `test/json102.test` covers `jsonb_insert`, `jsonb_replace`, and
  `jsonb_set` parity for existing and missing object members plus JSON vs
  string value boundaries.
- `test/json105.test` covers `[#]` appends, nested appends, reverse-index
  replacement through `[#-1]`, and multiple append/update argument order.
- `test/jsonb01.test` keeps the malformed JSONB rejection boundary in scope.

The native PHP tests now cover `SQLiteJsonB::insert()`, `set()`, and
`replace()` for existing vs missing members, root-object substructure
creation, array append substructure, `[#-0]` set appends, no-op replace
creation, multiple argument order, string-vs-array value behavior, and a
Application plugin settings preflight fixture. The new
`examples/application-jsonb-mutate-option-field.php` script lets Application
recovery or fixture-generation tooling apply SQLite-style JSONB path edits to
strict JSON, supported JSON5, or JSONB `wp_options.option_value` blobs without
requiring the SQLite extension.

## Focused Native Mapping: JSONB Array Insert Paths

This slice maps SQLite's new `json_array_insert()`/`jsonb_array_insert()`
path behavior for native SQLite JSONB fixture and preflight tooling. It
inserts before existing array elements, appends at `[N]` where `N` is the
array length, appends through `[#]` and `[#-0]`, uses reverse indexes such as
`[#-1]` as insertion points, treats out-of-range and non-array traversal as
no-ops, creates missing object/array substructure only when the path tail ends
in an array element, and rejects paths such as `$.a` that resolve to a value
rather than an array element.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json109.test json102.test jsonb01.test
```

Result on 2026-05-23: 3 Tcl scripts, 0 errors out of 374 tests in 00:00.

Focused upstream fixture boundary:

- `test/json109.test` covers `json_array_insert()` index insertion, prepend,
  append, reverse-index insertion, missing-path substructure creation,
  non-array target no-ops, non-array-element errors, and multiple path/value
  argument order.
- `test/json102.test` keeps adjacent JSONB mutation path and malformed input
  behavior in scope.
- `test/jsonb01.test` keeps malformed JSONB rejection boundaries in scope.

Additional focused upstream SQL probes confirmed `jsonb_array_insert()`
produces `CB121331BC476B696E6457636163686513321333` for inserting
`{"kind":"cache"}` at `$[1]` in `[1,2,3]`, and creates a missing
`$.b[0]` array path as `{"a":[1,2,3],"b":[{"kind":"cache"}]}`.

The native PHP tests now cover `SQLiteJsonB::arrayInsert()` for repeated
prepend order, append indexes, reverse indexes, root no-op behavior, missing
object/array substructures, non-array traversal no-ops, invalid and
non-array-element paths, odd path/value argument rejection, JSONB hex
roundtrip parity, and a Application option/meta migration preflight fixture.
The new `examples/application-jsonb-array-insert-option-field.php` script lets
Application recovery or fixture-generation tooling insert migration queue
entries into JSON/JSON5/JSONB option or meta arrays without requiring the
SQLite extension.

## Focused Native Mapping: JSONB Type And Array Length Inspection

This slice maps SQLite-style `json_type()` and `json_array_length()` behavior
over existing SQLite JSONB bytes. It uses the existing JSON path parser, returns
SQLite type names for root and path targets, reports `null` for missing paths,
and preserves the distinction between a missing path and an existing non-array
target, where `json_array_length()` returns `0`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json102.test jsonb01.test
```

Result on 2026-05-23: 2 Tcl scripts, 0 errors out of 356 tests in 00:00.

Focused upstream fixture boundary:

- `test/json102.test` covers `json_array_length()` and JSONB `json_type()`
  behavior for root arrays, root objects, array path targets, scalar path
  targets, and missing paths.
- `test/jsonb01.test` keeps malformed JSONB rejection boundaries in scope.

Additional focused upstream SQL probes confirmed `json_type()` returns `true`
for a JSONB boolean path, `json_array_length()` returns `2` for a Application
`optionMigrations` JSONB array, returns `0` for an existing text
`legacyMode` target, and returns SQL `NULL` for a missing `postMetaQueue`.

The native PHP tests now cover `SQLiteJsonB::type()` and
`SQLiteJsonB::arrayLength()` for root and path-based object, array, text,
integer, real, true, false, and null targets; missing paths; invalid paths; and
non-array targets. The new
`examples/application-jsonb-inspect-option-arrays.php` script lets Application
import or migration preflight tooling check JSONB option/meta arrays before
array insertion, append, or reorder steps without requiring the SQLite
extension.

## Focused Native Mapping: JSONB Merge Patch

This slice maps a bounded `jsonb_patch()`/`json_patch()` family for native
SQLite JSONB fixture and preflight tooling. It applies SQLite's RFC-7396 merge
patch behavior: object patches merge by member name, patch `null` removes
object members, non-object patches replace the whole target, object patches
against non-object targets first treat the target as `{}`, and arrays are
replaced rather than merged.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json104.test json101.test json502.test
```

Result on 2026-05-23: 3 Tcl scripts, 0 errors out of 325 tests in 00:00.

Focused upstream fixture boundary:

- `test/json104.test` covers the RFC-7396 examples, target non-object
  objectification, object-member deletion with `null`, nested object stripping,
  duplicate patch member order, array replacement, and scalar/null whole-value
  replacement.
- `test/json101.test` covers `json_patch(NULL,...)` SQL-null propagation at
  the broader JSON function boundary; the native `SQLiteJsonB::patch()` API
  remains byte-oriented and models valid JSONB inputs only.
- `test/json502.test` covers JSON5-style escaped object labels used by
  `json_patch(...)` before downstream `->>` extraction.

The native PHP tests now cover `SQLiteJsonB::patch()` for the RFC object merge
example, member deletion, target array to object conversion, empty object
preservation in JSONB bytes, nested object stripping, whole-array replacement,
whole-null/scalar replacement, and a Application plugin settings import preflight
fixture. The new `examples/application-jsonb-patch-option-field.php` script lets
Application recovery or fixture-generation tooling apply SQLite-style merge
patches to strict JSON, supported JSON5, or JSONB `wp_options.option_value`
blobs without requiring the SQLite extension.

## Focused Native Mapping: B-tree Freeblock Inspection

This slice maps SQLite's page-local freeblock chain validation and free-space
accounting from `btreeComputeFreeSpace()` in `src/btree.c`. B-tree page
headers already exposed the first freeblock offset and fragmented-byte count;
the native PHP reader now walks the freeblock linked list, rejects overlapping
or out-of-usable-space entries, and computes total free bytes as SQLite does:
unallocated space before the cell content area, fragmented bytes, and all
freeblock sizes.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  corrupt2.test corruptK.test
```

Result: 3 Tcl script/permutation runs, 0 errors out of 68 tests in 00:00.

Focused upstream fixture boundary:

- `test/corrupt2.test` corrupts freeblock offsets on page 1 and expects
  quick-check to report b-tree free-space corruption.
- `test/corruptK.test` manipulates free-slot sizes and overlapping allocation
  boundaries around `MemPage.nFree` and defragmentation.

The native PHP tests now cover ordered freeblock-chain decoding, SQLite-style
free-space byte accounting, non-ascending/overlapping freeblock rejection,
reserved-byte usable-size boundaries, and corrupt free-space totals. The new
`examples/application-page-freeblocks.php` script reports page-local freeblock
state for Application SQLite database images so recovery/import tooling can
inspect deleted-space and malformed-page clues without loading SQLite.

## Focused Native Mapping: Custom Collation Option Index Lookups

SQLite lets applications register named collation callbacks, and indexed
lookups may use those indexes only when the query comparison uses compatible
collation semantics. The native PHP reader now exposes a bounded
`wp_options(option_name COLLATE X)` lookup where recovery tooling supplies the
collation name plus a PHP comparator. This keeps unsupported collations
rejected by the ordinary built-in lookup path while allowing explicit recovery
from database images that were created with application-defined collations.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  collate1.test collate2.test collate4.test
```

Result: 3 Tcl scripts, 0 errors out of 301 tests in 00:00.

Focused upstream fixture boundary:

- `test/collate1.test` registers application-defined `HEX`, `numeric`, and
  quoted-name collations and verifies that registered callbacks change
  comparison and ordering behavior.
- `test/collate2.test` registers `BACKWARDS` and verifies custom collation
  behavior for `WHERE` comparison operators.
- `test/collate4.test` verifies that index usability depends on matching the
  comparison collation with the index collation.

The native PHP tests now cover Application-shaped
`wp_options(option_name COLLATE WPCASE)` and
`wp_options(option_name COLLATE WPSLUG) WHERE option_name IS NOT NULL`
indexes. The ordinary built-in lookup paths still throw for unsupported
collations, while `optionRowsByIndexedNameWithCollation()` accepts a PHP
case-folding comparator and returns all rows equal under that callback.
`optionRowsByIndexedNameRangeWithCollation()` now applies a supplied PHP
comparator to lower/upper option-name range bounds, handles open/exclusive or
inclusive upper bounds, rejects inverted ranges, validates callback return
types, and allows only the collation-safe `IS NOT NULL` partial-index form for
custom-collation range scans. The
`examples/application-custom-collation-option-lookup.php` and
`examples/application-custom-collation-option-name-range.php` scripts map
custom-collation option recovery on hosts where the PHP SQLite extension is
unavailable.

## Focused Native Mapping: Composite Custom-Collation Range Lookups

SQLite also carries per-column collation metadata through multi-column
indexes. This slice maps the Application-shaped read path
`wp_options(autoload, option_name COLLATE X)`: callers constrain the built-in
`autoload` prefix by equality, then supply the application collation callback
for the bounded `option_name` range.

Focused upstream runner rerun:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  collate1.test collate2.test collate4.test
```

Result on 2026-05-23: 3 Tcl scripts, 0 errors out of 301 tests in 00:00.

Focused upstream fixture boundary:

- `test/collate4.test` verifies multi-column index collation usability,
  including cases where the second indexed term's collation determines whether
  the index order is compatible.
- `test/collate1.test` and `test/collate2.test` provide the
  application-defined comparator boundary for named collation callbacks.

The native PHP tests now cover a Application-shaped partial index
`wp_options(autoload, option_name COLLATE WPSLUG) WHERE autoload='no' AND
option_name IS NOT NULL`. The ordinary composite range path still rejects the
unsupported collation, while
`optionRowsByIndexedNameRangeWithPrefixAndCollation()` accepts the
caller-provided comparator, enforces equality/`IS NOT NULL` partial predicate
safety, handles inclusive/exclusive upper bounds and inverted ranges, and
validates callback return types. The new
`examples/application-custom-collation-autoload-option-name-range.php` script
maps non-autoloaded transient/cache recovery when a site-specific option-name
collation treats underscores, case, or plugin slug separators differently from
SQLite's built-ins.

## Focused Native Mapping: Custom-Collated Equality Prefix Ranges

This slice extends composite range scans to indexes where an equality-prefix
column, not just the range column, uses an application-defined collation. The
native PHP reader now accepts a map of collation callbacks for a bounded
`wp_options(prefix COLLATE X, option_name)` scan, verifies that every
non-built-in collation in the constrained index has a supplied callback, and
uses those callbacks during equality-prefix comparison and b-tree interval
pruning.

Focused upstream runner rerun:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  collate2.test collate4.test index3.test
```

Result on 2026-05-23: 5 Tcl script/permutation runs, 0 errors out of 269
tests in 00:00.

Focused upstream fixture boundary:

- `test/collate2.test` registers the application-defined `BACKWARDS`
  comparator and verifies equality/range comparison behavior.
- `test/collate4.test` verifies multi-column index usability when indexed
  columns carry user-defined collations.
- `test/index3.test` covers collated index metadata at the quoted identifier
  and autoindex boundary.

The native PHP test fixture now covers
`wp_options(option_value COLLATE WPSLUG, option_name) WHERE option_value IS
NOT NULL AND option_name IS NOT NULL`: ordinary composite scans reject the
unsupported collation, while
`optionRowsByIndexedNameRangeWithPrefixCollations()` treats
`Plugin-Core` and `plugin_core` as the same equality prefix under the supplied
callback, applies the `option_name` bounds, and skips an out-of-range index
branch before page decoding. The example
`examples/application-custom-collation-prefix-option-name-range.php` maps this
to plugin/cache recovery where a site-specific grouping column uses slug-like
custom comparison.

## Focused Native Mapping: Record And Table Leaf Assembly

This slice adds the first bounded write-side page primitive beyond varint
preflight. `SQLiteRecord::encode()` maps SQLite record serial-type selection,
header-size varint growth, signed integer widths, floating point payloads,
text payloads, and explicit BLOB payloads. `SQLiteTableLeafCell::encode()`
maps the table-leaf payload-length/rowid prefix, minimum 4-byte cell size, and
local payload plus first-overflow-page pointer boundary. `SQLiteTableLeafPage`
assembles clean table-leaf page headers, cell pointer arrays, and packed cell
content while preserving the 100-byte database header on page 1.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test btree01.test
```

Result on 2026-05-23: 4 Tcl script/permutation runs, 0 errors out of 538
tests in 00:01.

Focused upstream fixture boundary:

- `src/vdbe.c`/`src/vdbeaux.c` document `OP_MakeRecord` serial types,
  integer-width thresholds, and record-header sizing.
- `src/btree.c` `fillInCell()` documents the table-leaf payload-length and
  rowid varint header, minimum local cell size, and local/overflow payload
  split.
- `test/insert.test` verifies persisted inserted rows across common table
  storage classes and defaults.
- `test/btree01.test` stresses b-tree cell construction, update, local payload,
  and overflow-sized payload integrity boundaries.

The native PHP tests now cover record encoding across serial types 0, 1, 2, 3,
4, 5, 6, 7, 8, 9, text, and BLOB, including a record header that grows to a
two-byte size varint; table-leaf cell assembly with padding and overflow
pointer validation; and an assembled two-page Application-shaped `wp_options`
fixture that the existing native reader parses back without the PHP SQLite
extension. The new example
`examples/application-table-leaf-page-assembly.php` exposes that fixture path for
Application repair/preflight tooling.

## Focused Native Mapping: Index Leaf Assembly

This slice adds the index-b-tree write-side counterpart to the existing native
index reader. `SQLiteIndexCell::encode()` maps the index payload-length varint,
optional interior left-child pointer, minimum 4-byte cell size, local payload
selection, and first-overflow-page pointer boundary. `SQLiteIndexLeafPage`
assembles clean index-leaf page headers, cell pointer arrays, and packed index
cell content.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test insert.test btree01.test
```

Result on 2026-05-23: 7 Tcl script/permutation runs, 0 errors out of 809 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` `fillInCell()` documents index payload construction,
  local/overflow payload split, and minimum cell-size behavior.
- `test/index.test` covers index b-tree creation and persisted index content
  boundaries.
- `test/insert.test` covers record payload generation for stored table/index
  values.
- `test/btree01.test` stresses b-tree cell construction, update, local payload,
  and overflow-sized payload integrity boundaries.

The native PHP tests now cover index-cell encoding, overflow pointer
validation, minimum-size padding, generated index-leaf page headers and cell
pointers, and a Application-shaped three-page `wp_options` fixture where a
generated `wp_options(option_name)` index page is parsed back and used for an
indexed `siteurl` lookup without the PHP SQLite extension. The new
`examples/application-index-leaf-page-assembly.php` script exposes that index
fixture path for Application repair/preflight tooling.

## Focused Native Mapping: Index Interior Assembly

This slice adds clean index-interior page assembly for multi-page generated
index fixtures. `SQLiteIndexInteriorPage::assemble()` maps SQLite's 12-byte
index-interior b-tree header, right-most child page pointer, cell pointer
array, and packed cell content area. It pairs with `SQLiteIndexCell::encode()`
for cells that carry a 4-byte left-child page pointer plus the encoded index
record payload.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test insert.test btree01.test
```

Result on 2026-05-23: 7 Tcl script/permutation runs, 0 errors out of 809 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` documents index interior cells as a left-child pointer plus an
  index payload and enforces the same local/overflow payload boundaries through
  `fillInCell()`.
- `test/index.test` covers generated index b-tree content and lookup behavior.
- `test/insert.test` covers record payload generation for stored index values.
- `test/btree01.test` stresses b-tree cell construction and multi-page b-tree
  payload integrity boundaries.

The native PHP tests now cover generated index-interior headers, right-most
child pointers, interior cell pointer arrays, left-child payload records, and a
Application-shaped five-page `wp_options` fixture whose generated
`wp_options(option_name)` root is an index-interior page. The native reader
parses the generated root, walks left child, interior separator, and right
child in index order, then resolves `siteurl` through the indexed rowid without
the PHP SQLite extension. The new
`examples/application-index-interior-page-assembly.php` script exposes that
multi-page index fixture path for Application repair/preflight tooling.

## Focused Native Mapping: Overflow Page Chain Assembly

This slice adds the write-side overflow-page chain primitive that pairs with
the existing native overflow reader. `SQLiteOverflowPage::encodeChain()` emits
SQLite overflow pages with a 4-byte big-endian next-page pointer followed by
up to `usableSize - 4` payload bytes. `SQLiteTableLeafCell` and
`SQLiteIndexCell` now expose `encodeWithOverflowPages()` helpers that split
cell payloads using the same local-payload formulas as the reader, then return
the encoded cell plus sequential overflow pages.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test btree01.test corrupt3.test
```

Result on 2026-05-23: 7 Tcl script/permutation runs, 0 errors out of 547 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` `fillInCell()` writes local payload bytes first, stores the
  first overflow page number at the end of the local cell, and then writes
  each overflow page with its next-page pointer at byte 0 and payload at byte
  4.
- `test/insert.test` keeps record payload generation and persisted insert
  behavior in scope for generated table/index cells.
- `test/btree01.test` stresses b-tree updates with overflow-sized payloads and
  integrity checks.
- `test/corrupt3.test` verifies the on-disk pointer location for a one-page
  overflow chain and malformed overflow-chain boundaries.

The native PHP tests now cover required overflow page counts, next-page
pointer bytes, table-leaf overflow page assembly parsed back through
`optionRows()`, and index-leaf overflow page assembly parsed through
`indexCells()`. The new
`examples/application-overflow-page-assembly.php` script emits a Application-shaped
`wp_options` row whose `option_value` spills to overflow pages, then reads it
back without the PHP SQLite extension.

## Focused Native Mapping: Reusable Overflow Page Numbers

This slice extends overflow-page assembly from sequentially appended pages to
caller-supplied page numbers. SQLite can satisfy overflow allocations from the
database freelist, so overflow chains are not guaranteed to be contiguous. The
new `SQLiteOverflowPage::encodeChainAtPages()` helper writes the same 4-byte
big-endian next-page pointer and `usableSize - 4` payload chunks, but follows
the supplied page-number order and leaves reserved bytes untouched.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  pageropt.test corrupt3.test btree01.test
```

Result on 2026-05-23: 5 Tcl script/permutation runs, 0 errors out of 225 tests
in 00:00.

Focused upstream fixture boundary:

- `test/pageropt.test` explicitly moves pages from the freelist into an
  overflow chain for a large inserted record.
- `src/btree.c` `allocateBtreePage()` documents that pages may be reused from
  the freelist before appending new pages.
- `src/btree.c` `fillInCell()` documents overflow next-page pointer writes and
  the `usableSize - 4` payload chunk boundary.
- `test/corrupt3.test` keeps malformed overflow-chain pointer behavior in
  scope, and `test/btree01.test` keeps b-tree overflow payload integrity in
  scope.

The native PHP tests now cover non-contiguous overflow chains such as
`5 -> 3 -> 7 -> 0`, duplicate/wrong page-number rejection, reserved-byte page
tails, and a Application-shaped `wp_options` fixture with a 12-byte reserved
tail per page that still parses back through `optionRows()` without the
PHP SQLite extension. The new
`examples/application-overflow-page-freelist-reuse.php` script exposes that
fixture path for repair/preflight tooling. The follow-up freelist metadata
slice now chooses reusable page numbers from an actual database image instead
of requiring callers to supply them manually.

## Focused Native Mapping: Freelist Trunk Metadata

This slice maps SQLite's file-level freelist metadata. `SQLiteHeader` now
reads the first freelist trunk page number at header offset 32 and total
freelist page count at offset 36. `SQLiteFreelistTrunkPage` parses trunk pages
where the first big-endian integer points at the next trunk and the second
big-endian integer is the count of leaf page pointers beginning at byte 8.
`SQLiteDatabase::freelistTrunkPages()` walks that chain, rejects loops,
duplicates, out-of-range page numbers, oversized leaf counts, and header-count
mismatches. `freelistAllocationOrder()` models SQLite's ordinary
`BTALLOC_ANY` behavior for planning reusable pages: first leaf, remaining
leaves in last-entry replacement order, then the emptied trunk page.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  pageropt.test corrupt2.test btree01.test
```

Result on 2026-05-23: 4 Tcl script/permutation runs, 0 errors out of 274 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` `allocateBtreePage()` documents header offsets 32 and 36,
  trunk next-page pointers, trunk leaf counts, leaf page extraction, and the
  last-leaf replacement order used when allocating ordinary freelist leaves.
- `src/btree.c` `freePage2()` documents how pages become a new trunk or a leaf
  on the first trunk and why modern SQLite leaves six trunk slots unused for
  old-reader compatibility.
- `test/corrupt2.test` verifies that integrity checks catch a header freelist
  count smaller than the actual freelist chain.
- `test/pageropt.test` keeps freelist-to-overflow reuse in scope for large
  record writes, and `test/btree01.test` keeps overflow payload integrity in
  scope.

The native PHP tests now cover header freelist fields, trunk next-page and
leaf-count parsing, multi-trunk traversal, allocation-order planning,
corrupt-count detection, duplicate page detection, oversized leaf-count
rejection, and a Application-shaped repair preflight where a large
`wp_options.option_value` chooses pages from the parsed freelist, writes a
two-page overflow chain, updates remaining freelist metadata, and parses the
option back through the native reader. The new
`examples/application-freelist-overflow-repair-plan.php` script exposes that
flow for recovery tooling without requiring the PHP SQLite extension.

## Focused Native Mapping: Freelist Allocation Mutation Planning

This slice adds the write-side planning counterpart for ordinary
`BTALLOC_ANY` page allocation. `SQLiteDatabase::planPageAllocation()` validates
the existing freelist chain, consumes requested pages in SQLite's ordinary
allocation order, returns the mutated first-page header bytes, returns any
updated freelist trunk page images, removes emptied trunk pages from the
freelist, and appends new page numbers after freelist depletion when allowed.
The mutation intentionally remains a page-image plan rather than a full pager
or journaling implementation.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  pageropt.test corrupt2.test btree01.test
```

Result on 2026-05-23: 4 Tcl script/permutation runs, 0 errors out of 274 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` `allocateBtreePage()` documents decrementing the header
  freelist count, choosing the first freelist leaf for ordinary allocation,
  replacing that leaf slot with the last leaf pointer, removing empty trunk
  pages, and appending new pages when the freelist is empty.
- `test/pageropt.test` keeps freelist-to-overflow reuse in scope for large
  record writes without reading the old reusable page content.
- `test/corrupt2.test` keeps freelist header-count integrity in scope, and
  `test/btree01.test` keeps b-tree payload integrity in scope after page
  allocation feeds an overflow chain.

The native PHP tests now cover leaf-array mutation across repeated
allocations, stale unused leaf-pointer bytes that remain outside the declared
leaf count, empty-trunk allocation across a trunk chain, append-after-depletion
page numbering, insufficient-freelist rejection when appending is disabled,
and a Application-shaped `wp_options.option_value` repair plan that uses the
allocation plan's returned header/trunk page images before parsing the updated
database image back through the native reader. The existing
`examples/application-freelist-overflow-repair-plan.php` script now reports the
allocation plan and applies its page images directly.

## Focused Native Mapping: Freelist Free Mutation Planning

This slice adds the bounded write-side counterpart for SQLite's `freePage2()`
freelist insertion behavior. `SQLiteDatabase::planPageFreeList()` validates
the existing freelist chain, rejects pages outside the database image or pages
already present on the freelist, increments the first-page freelist count, and
returns page-image mutations. If the freelist is empty, or the first trunk has
already reached SQLite's compatibility insertion limit of `usableSize / 4 - 8`
leaf pointers, the freed page becomes the new first trunk. Otherwise, the page
is appended as a leaf pointer on the current first trunk. The mutation remains
a preflight page-image plan and intentionally does not implement pager
journaling, pointer-map updates for auto-vacuum databases, secure-delete page
zeroing, or full b-tree row replacement.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  pageropt.test corrupt2.test
```

Result on 2026-05-23: 3 Tcl script/permutation runs, 0 errors out of 59 tests
in 00:00.

Focused upstream fixture boundary:

- `src/btree.c` `freePage2()` documents incrementing the header freelist
  count, inserting a freed page as a first-trunk leaf when room exists,
  avoiding the final six trunk entries for old-reader compatibility, and
  making the freed page the new first trunk when insertion is not possible.
- `test/pageropt.test` exercises deleting large rows whose overflow pages are
  returned to the freelist while minimizing reads/writes.
- `test/corrupt2.test` keeps freelist-count integrity checks in scope.

The native PHP tests now cover inserting a freed page into a non-full first
trunk, creating a new trunk for an empty freelist, creating a new trunk when
the first trunk is compatibility-full, updated header/trunk page images, and
the resulting next allocation order. The new
`examples/application-free-obsolete-overflow-pages.php` script rewrites a large
`wp_options` row down to an inline value, returns the old overflow pages to
the freelist plan, and parses the resulting option plus freelist metadata
without requiring the PHP SQLite extension.

## Focused Native Mapping: Bounded wp_options Insert Page Images

This slice integrates the existing record, table-leaf cell, table-leaf page,
overflow-chain, and freelist allocation primitives into
`SQLiteDatabase::planOptionRowInsert()`. The helper is intentionally
bounded: it handles explicit positive rowids for index-free `wp_options`
fixtures whose root is a single table leaf page, returns the complete set of
first-page/table/overflow/freelist page images, keeps table leaf cells sorted
by rowid, rejects duplicate rowids and option names, and rejects indexed
fixtures rather than producing stale secondary indexes. It does not implement
b-tree balancing, index maintenance, AUTOINCREMENT `sqlite_sequence` writes,
journaling, WAL, pointer-map updates, or arbitrary SQL execution.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test btree01.test pageropt.test
```

Result on 2026-05-23: 5 Tcl script/permutation runs, 0 errors out of 539 tests
in 00:01.

Focused upstream fixture boundary:

- `src/btree.c` `fillInCell()` documents table leaf row payload headers,
  minimum four-byte local cells, local payload sizing, overflow page pointer
  placement, and overflow payload chunk writes.
- `src/btree.c` `sqlite3BtreeInsert()` documents rowid table insert behavior,
  duplicate overwrite boundaries, and the point where balancing is required
  for overfull pages.
- `test/insert.test` covers row persistence through INSERT, while
  `test/btree01.test` keeps b-tree insert/balancing regressions in scope and
  `test/pageropt.test` keeps freelist-backed overflow reuse in scope.

The native PHP tests now cover an appended-overflow insert plan, a reusable
freelist-overflow insert plan, page-image application followed by native
`wp_options` reads, and rejection of duplicate/stale-index plans. The new
`examples/application-generated-option-insert-plan.php` script exposes the
preflight flow for Application fixture generation or repair tooling without
requiring the PHP SQLite extension.

## Focused Native Mapping: Bounded wp_options Replacement Page Images

This slice adds `SQLiteDatabase::planOptionRowReplace()` for the bounded
Application repair case where an index-free `wp_options` table root is a single
table leaf page. The helper rewrites one existing option row in place by
`option_name`, preserves rowid order, rejects missing or duplicate option
names, rejects indexed fixtures to avoid stale secondary indexes, rejects
replacement payloads that still need overflow pages in this slice, and returns
obsolete overflow pages through the existing `freePage2`-style
`planPageFreeList()` machinery.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test btree01.test pageropt.test
```

Result on 2026-05-23: 5 Tcl script/permutation runs, 0 errors out of 491 tests
in 00:00.

Focused upstream fixture boundary:

- `test/update.test` keeps row rewrite semantics in scope for ordinary UPDATE
  statements.
- `test/btree01.test` exercises UPDATE statements that move rows across large
  overflow payload boundaries.
- `test/pageropt.test` documents the overflow-chain deletion/free-list reuse
  boundary, including the case where an old overflow page becomes a freelist
  trunk and later allocation can reuse the freed chain.

The native PHP tests now cover large-to-small `wp_options` replacement where
the old overflow chain becomes freelist metadata, inline-to-large replacement
with appended overflow pages, large-to-larger replacement where the new
overflow chain is allocated before the obsolete chain is freed, page-image
application followed by native option reads, and guardrails for missing,
duplicate, indexed, and no-append replacement plans. The
`examples/application-replace-obsolete-overflow-option.php` and
`examples/application-replace-large-overflow-option.php` scripts show both
repair workflows end to end without requiring the PHP SQLite extension.

## Root Harness Coordination

Before starting the root harness for this lane run,
`pgrep -af '^php tools/run-tests\.php( |$)'` returned no active process. This
worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 183 test files, 18644 assertions, 0 failures.

For the freelist allocation mutation slice on 2026-05-23, the focused lane
test passed but the root harness was not started. The required preflight check
returned an active root run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 467834 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 467834
# 467834 claude 03:19 php tools/run-tests.php
```

Per lane instructions, no duplicate root harness was launched. The root result
for this slice is pending supervisor/integrator acceptance of the active run.

For the bounded wp_options insert page-image slice on 2026-05-23, the focused
lane test, focused upstream runner, and Application example passed. The direct
libsqlite harness reported 163 tests, 1068 assertions, and 0 failures. The
Application generated-option insert example ran successfully and reported a
two-page overflow insert plan. Root harness coordination is recorded in the
lane status for this slice.

Before starting the root harness for this slice,
`pgrep -af '^php tools/run-tests\.php( |$)'` returned no active process. This
worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 183 test files, 19126 assertions, 0 failures.

After adding the final invalid-root guard for this slice, the focused lane
test was re-run and passed with 163 tests, 1069 assertions, and 0 failures.
The Application generated-option insert example was also re-run successfully.
The required post-change root preflight then found active root harness
processes, so this worker did not start a duplicate:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 702741 php tools/run-tests.php
# 702753 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 702741,702753
# 702741 claude 00:10 php tools/run-tests.php
# 702753 claude 00:08 php tools/run-tests.php
```

The earlier 19126-assertion root pass predates the final guard, so the
post-change root result is pending for the supervisor/integrator.

For the freelist free mutation slice on 2026-05-23, the focused lane test,
focused upstream runner, and Application example passed, but the root harness was
not started. The required preflight check returned an active root run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 618249 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 618249
# 618249 claude 00:15 php tools/run-tests.php
```

Per lane instructions, no duplicate root harness was launched. The root result
for this slice is pending supervisor/integrator acceptance of the active run.

For the bounded wp_options replacement page-image slice on 2026-05-23, the
focused upstream runner passed `update.test`, `btree01.test`, and
`pageropt.test` with 0 errors out of 491 tests. The direct libsqlite harness
passed 165 tests with 1082 assertions and 0 failures, and
`examples/application-replace-obsolete-overflow-option.php` ran successfully.
Before starting the root harness, the required preflight returned no active
process:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 184 test files, 19365 assertions, 0 failures.

For the large wp_options replacement overflow slice on 2026-05-23, the
focused upstream runner re-ran `update.test`, `btree01.test`, and
`pageropt.test` with 0 errors out of 491 tests. The direct libsqlite harness
passed 167 tests with 1099 assertions and 0 failures, and
`examples/application-replace-large-overflow-option.php` ran successfully.
Before starting the root harness, the required preflight returned no active
process:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 185 test files, 19593 assertions, 0 failures.

For the bounded single-leaf `option_name` index maintenance slice on
2026-05-23, the focused upstream runner passed `insert.test`, `index.test`,
`update.test`, and `btree01.test` with 0 errors out of 1084 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test update.test btree01.test
```

This maps rowid table INSERT persistence, UPDATE row rewrite behavior, index
b-tree payload ordering, and b-tree cell assembly boundaries used by the
native bounded `wp_options` option-name index page-image planner. The direct
libsqlite harness passed 169 tests with 1112 assertions and 0 failures, and
`examples/application-indexed-generated-option-insert-plan.php` ran
successfully, reporting updated table/index page images `[2,3]` and indexed
records `home -> 2`, `siteurl -> 1`.

Before starting a root harness for this slice, the required preflight found an
active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 926388 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 926388
# 926388 claude 00:24 php tools/run-tests.php
```

Per lane instructions, this worker did not start a duplicate root harness. The
post-change root result is pending supervisor/integrator acceptance of the
active run.

## Focused Native Mapping: Table Root Leaf Growth Replacement

For the bounded table-root leaf growth replacement slice on 2026-05-23, the
focused upstream runner passed `update.test`, `insert.test`, `btree01.test`,
and `rowid.test` with 0 errors out of 1070 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test insert.test btree01.test rowid.test
```

This maps UPDATE row rewrite behavior, rowid lookup/comparison boundaries,
table b-tree root growth after a larger row payload, and b-tree page/cell
assembly used by the native bounded `wp_options` replacement planner when a
single table-leaf root must become an interior table root.

The PHP planner now splits the overfull table-root leaf into two newly
allocated table leaves, writes a one-cell table-interior root with the left
leaf's maximum rowid as the separator, updates the returned database page
count through allocation planning, and preserves all `wp_options` rows in
rowid traversal order. It still rejects non-root table/index parent split
propagation, table leaf rebalancing beyond the bounded root-parent cases,
pointer-map/auto-vacuum, journaling, WAL, and general SQL execution.

The direct libsqlite harness passed 191 PHP tests with 1321 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-table-root-split-option-replacement-plan.php`
script ran successfully, reporting updated page images `[1,2,3,4]`, a
`table-interior` root at page 2, split leaf counts 1 and 2, and a rewritten
`blogname` option with `autoload='no'`.

Before starting the root harness, the required duplicate-root preflight first
found an active aggregate PID that exited before owner sampling:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 2482310 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 2482310
# process exited before owner details could be read
```

A second exact preflight returned no active root harness, so this worker ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 199 test files, 22444 assertions, 0 failures.

## Focused Native Mapping: Table Leaf Split During Replacement

For the bounded table-leaf split replacement slice on 2026-05-23, the focused
upstream runner passed `update.test`, `insert.test`, `btree01.test`, and
`rowid.test` with 0 errors out of 1070 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test insert.test btree01.test rowid.test
```

This maps UPDATE row rewrite behavior, rowid lookup/comparison boundaries, and
SQLite b-tree balancing after a larger row payload no longer fits in its
existing leaf. The native PHP slice now assembles table-interior pages and can
rewrite a `wp_options` row below a table-interior root by splitting the target
table leaf, allocating the new leaf page, and updating the root separator keys
and right-most child pointer. The implementation remains intentionally bounded:
non-root table parent split propagation and broader table-leaf rebalancing
remain future slices.

Focused lane verification passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1304 assertions, 0 failures.

The Application example also ran successfully:

```sh
php lanes/libsqlite/examples/application-table-leaf-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,5]`, database page count `5`, root
table separators `(leftChildPage=3,maxRowid=1)` and
`(leftChildPage=5,maxRowid=3)`, right-most page `4`, and the rewritten
`blogname` option with `autoload='no'`.

## Focused Native Mapping: Multi-Page Table Replacement Planning

For the bounded multi-page table-root replacement slice on 2026-05-23, the
focused upstream runner passed `update.test`, `btree01.test`, and
`rowid.test` with 0 errors out of 747 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test btree01.test rowid.test
```

This maps UPDATE row rewrite behavior, rowid lookup/comparison semantics, and
table b-tree page/cell boundaries used by the native bounded
`wp_options` replacement planner when the target row lives in a table leaf
below an interior table root. The native planner now traverses the table
b-tree to locate the writable target leaf, rejects duplicate option names
across leaves, rewrites only that leaf when no table-page split is needed, and
keeps the interior table root unchanged.

The direct libsqlite harness passed 188 PHP tests with 1282 assertions and 0
failures. The new
`examples/application-multipage-table-option-replacement-plan.php` script ran
successfully, reporting updated page `[4]`, an unchanged `table-interior`
root at page 2, and a rewritten `blogname` option with `autoload='no'`.

## Focused Native Mapping: Automatic and Composite Parent-Root Index Splits

For the bounded sqlite_autoindex-backed and composite `autoload, option_name`
parent-root split insert slice on 2026-05-23, the focused upstream runner
passed `insert.test`, `index.test`, `index3.test`, `schema6.test`,
`indexedby.test`, `where.test`, `whereH.test`, and `btree01.test` with 0
errors out of 1277 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test index3.test schema6.test indexedby.test where.test whereH.test btree01.test
```

This maps rowid INSERT persistence, automatic UNIQUE/PRIMARY KEY index
metadata and selection, composite index key ordering, equality-prefix planner
coverage, root-interior balancing after leaf splits, and b-tree cell/page
assembly used by the native bounded `wp_options` parent-root split insert
planner. The direct libsqlite harness passed 185 PHP tests with 1264
assertions and 0 failures. The new
`examples/application-composite-index-parent-root-split-option-insert-plan.php`
script ran successfully, reporting updated page images `[1,2,3,10,11,12,13]`,
an `index-interior` root with 1 cell, two new index-interior pages with 3
cells each, split composite leaves with 3 cells each, 19 index records, and an
indexed lookup of the generated option through
`optionRowByIndexedAutoloadAndName('yes', $optionName)`.

Before starting the root harness, the required duplicate-run preflight returned
no active exact `php tools/run-tests.php` process. This worker then ran
`php tools/run-tests.php`; result on 2026-05-23: 197 test files, 21713
assertions, 0 failures.

## Focused Native Mapping: Parent-Root Secondary Index Split

For the bounded parent-root secondary-index split slice on 2026-05-23, the
focused upstream runner passed `insert.test`, `index.test`, and `btree01.test`
with 0 errors out of 809 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test btree01.test
```

This maps rowid INSERT persistence, index b-tree key ordering,
`sqlite3BtreeInsert()` balancing after a leaf split reaches a full
index-interior root, and b-tree cell/page assembly boundaries used by the
native bounded `wp_options(option_name)` planner. The PHP planner can now
split a full index leaf, detect that the parent root cannot absorb the
promoted divider, allocate two new interior pages, convert the original root
into a one-cell higher-level index-interior root, and keep the inserted option
reachable through the native index lookup. It still rejects non-root parent
propagation, source-leaf rebalancing on replacements, index-overflow cells,
pointer-map/auto-vacuum, journaling, WAL, and general SQL execution.

The direct libsqlite harness passed 183 PHP tests with 1229 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-index-parent-root-split-option-insert-plan.php` script ran
successfully, reporting updated page images `[1,2,3,10,11,12,13]`, an
`index-interior` root with 1 cell, two new index-interior pages with 3 cells
each, split leaf counts 3 and 3, and indexed lookup of the generated option
through the grown `option_name` index.

Before starting a root harness for this slice, the required duplicate-root
preflight found an active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 1908018 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 1908018
# 1908018 claude 00:17 php tools/run-tests.php
```

Per lane instructions, this worker did not start a duplicate root harness. The
post-change root result is pending supervisor/integrator acceptance of the
active run.

## Focused Native Mapping: Same-Depth Replacement Index Leaf Split

For the bounded same-depth composite-index replacement split slice on
2026-05-23, the focused upstream runner passed `update.test`, `index.test`,
`where.test`, `whereH.test`, and `btree01.test` with 0 errors out of 1096
tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test where.test whereH.test btree01.test
```

This maps SQLite UPDATE row rewrite behavior, composite index key ordering,
equality-prefix planner coverage, and b-tree cell/page assembly boundaries
used by the native bounded `wp_options(autoload, option_name)` replacement
planner when an `autoload` change moves the secondary-index entry into a full
leaf. The PHP planner now routes replacement destination leaf writes through
the same bounded same-depth split helper used by inserts, allocates one new
leaf page when the parent can absorb the divider, updates the returned
database page count for index-split allocations, and still rejects parent-page
splits above the bounded root-leaf case, empty-source-leaf rebalancing,
index-overflow cells, pointer-map/auto-vacuum, journaling, WAL, and general
SQL execution.

The direct libsqlite harness passed 181 PHP tests with 1201 assertions and 0
failures. The new
`examples/application-index-split-option-replacement-plan.php` script ran
successfully, reporting updated page images `[1,2,3,4,5,6]`, an index-interior
root with 2 cells, split destination leaf counts 3 and 3, the old source leaf
reduced to 1 cell, and indexed lookup of the replaced option through
`optionRowByIndexedAutoloadAndName('no', $optionName)`.

Before starting a root harness for this slice, the required preflight first
saw this worker's transient focused lane command:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 1534845 php tools/run-tests.php lanes/libsqlite/tests
```

After that focused command exited, the duplicate-root preflight returned no
active aggregate run, so this worker ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 195 test files, 21326 assertions, 1 failure. The failure
was outside libsqlite: `lanes/dolt/tests/DataConflictsResolverTest.php`
requires missing
`lanes/dolt/examples/application-data-conflict-resolve-review.php`. The
libsqlite focused harness remained green, so this is recorded as a repo
integration blocker rather than a libsqlite implementation blocker.

## Focused Native Mapping: Index Root Leaf Growth

For the bounded secondary-index root-growth slice on 2026-05-23, the focused
upstream runner passed `insert.test`, `index.test`, and `btree01.test` with 0
errors out of 809 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test btree01.test
```

This maps rowid INSERT persistence, index b-tree key ordering,
`sqlite3BtreeInsert()` root-leaf growth/balancing boundaries, and b-tree
cell/page assembly used by the native bounded `wp_options(option_name)`
planner. The PHP planner can now split an overfull index root leaf by
allocating two new leaf pages, converting the original root page into an
index-interior page, promoting one divider record into the root, and updating
the returned database page count. It still rejects parent-page splits,
source-leaf rebalancing, index-overflow cells, pointer-map/auto-vacuum,
journaling, WAL, and general SQL execution.

The direct libsqlite harness passed 182 PHP tests with 1213 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-index-root-split-option-insert-plan.php` script
ran successfully, reporting updated page images `[1,2,3,4,5]`, an
`index-interior` root with 1 divider cell, two new index leaves with 3 cells
each, and indexed lookup of the generated option through the grown
`option_name` index.

Before starting a root harness for this slice, the required duplicate-root
preflight found an active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 1566768 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 1566768
# 1566768 claude 00:10 php tools/run-tests.php
```

Per lane instructions, this worker did not start a duplicate root harness. The
post-change root result is pending supervisor/integrator acceptance of the
active run.

For the bounded same-depth secondary index split slice on 2026-05-23, the
focused upstream runner passed `insert.test`, `index.test`, and `btree01.test`
with 0 errors out of 809 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test btree01.test
```

This maps rowid INSERT persistence, index b-tree key ordering,
`sqlite3BtreeInsert()` overflow-cell insertion, `balance_nonroot()` and
`balance_quick()` b-tree split boundaries, and b-tree cell/page assembly used
by the native bounded `wp_options(option_name)` same-depth leaf-split planner.
The direct libsqlite harness passed 180 PHP tests with 1188 assertions and 0
failures. The new `examples/application-index-split-option-insert-plan.php`
script ran successfully, reporting updated page images `[1,2,3,5,6]`, an
`index-interior` root with 2 divider cells, split leaf pages 5 and 6 with 3
cells each, and indexed lookup of the generated option through the split
`option_name` index.

Before starting a root harness for this slice, the required preflight returned
no active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 193 test files, 21075 assertions, 0 failures.

## Focused Native Mapping: UTF-16 Record Encoding

For the bounded UTF-16 record serialization slice on 2026-05-23, the focused
upstream runner passed SQLite's encoding regression coverage with 0 errors out
of 114 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick enc.test
```

This maps the `enc.test` UTF-8 to UTF-16LE/UTF-16BE conversion checks,
UTF-16 database encoding boundaries, and timely `sqlite_schema` encoding
detection. The native PHP record encoder now serializes text fields as UTF-8,
UTF-16LE, or UTF-16BE according to the SQLite database header text encoding,
while preserving byte-length serial types and the existing UTF-16 decoder.

The direct libsqlite harness passed 180 PHP tests with 1178 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-utf16-option-insert-plan.php` script ran
successfully, reporting `textEncoding` 2, updated page image `[2]`, and a
generated `blogdescription` option whose UTF-16LE page bytes decode back to
the expected UTF-8 option value. This maps Application SQLite repair/preflight
for database images that are not UTF-8 encoded but still need bounded native
page-image planning without the SQLite extension.

Before starting a root harness for this slice, the required preflight returned
no active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 192 test files, 20867 assertions, 0 failures.

## Focused Native Mapping: Automatic Index Write Planning

For the bounded `sqlite_autoindex_*` write-planning slice on 2026-05-23, the
focused upstream runner passed automatic-index and write-related SQLite Tcl
coverage with 0 errors out of 1217 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test index3.test schema6.test indexedby.test insert.test update.test btree01.test
```

This maps automatic UNIQUE/PRIMARY KEY autoindex naming and column metadata,
rowid INSERT/UPDATE behavior, index b-tree key ordering, and b-tree cell/page
assembly boundaries. The native PHP planner now infers full automatic-index
column lists from `CREATE TABLE` SQL for `sqlite_autoindex_wp_options_*`
schema rows and maintains bounded single-leaf automatic indexes shaped like
`UNIQUE(option_name)` or `UNIQUE(autoload, option_name)` during generated
`wp_options` inserts and replacements. Unsupported automatic index shapes,
index-overflow cells, multi-page automatic-index writes, page splits,
rebalancing, pointer-map/auto-vacuum, journaling, WAL, and general SQL
execution remain out of scope.

The direct libsqlite harness passed 178 PHP tests with 1165 assertions and 0
failures. The new
`examples/application-automatic-indexed-generated-option-insert-plan.php` script
ran successfully, reporting updated table/autoindex page images `[2,3]`,
automatic index records `home -> 2`, `siteurl -> 1`, and indexed lookup of the
generated `home` row through the inferred `sqlite_autoindex_wp_options_1`.

Before starting a root harness for this slice, the required preflight returned
no active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 190 test files, 20680 assertions, 0 failures.

## Focused Native Mapping: Multi-Page Secondary Index Write Planning

For the bounded multi-page secondary-index write slice on 2026-05-23, the
focused upstream runner passed `insert.test`, `update.test`, `index.test`, and
`btree01.test` with 0 errors out of 1084 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test update.test index.test btree01.test
```

This maps rowid-table INSERT persistence, UPDATE row rewrite behavior, index
b-tree key ordering, and b-tree cell/page assembly boundaries used by the
native bounded `wp_options` write planner. The PHP planner can now maintain
explicit ordinary-column `option_name` and `autoload, option_name` secondary
indexes whose root is an index-interior page, as long as the affected entry is
leaf-resident and the target leaf can accept the new record without overflow,
split, or rebalancing. It still rejects index-overflow records, page splits,
interior-entry deletion, empty-source-leaf rebalancing, automatic indexes,
expression indexes, unsafe partial predicates, pointer-map/auto-vacuum,
journaling, WAL, and general SQL execution.

The direct libsqlite harness passed 176 PHP tests with 1150 assertions and 0
failures. The new
`examples/application-multipage-composite-indexed-option-replacement-plan.php`
script ran successfully, reporting updated table/index leaf page images
`[2,4,5]`, an `index-interior` root, composite records
`[no, cron_lock, 1]`, `[no, siteurl, 3]`, `[yes, home, 2]`,
`[yes, stylesheet, 4]`, and indexed lookup of `siteurl` through
`optionRowByIndexedAutoloadAndName('no', 'siteurl')`.

For the bounded composite `autoload, option_name` replacement maintenance
slice on 2026-05-23, the focused upstream runner passed `update.test`,
`index.test`, `where.test`, `whereH.test`, and `btree01.test` with 0 errors
out of 1096 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test where.test whereH.test btree01.test
```

This maps UPDATE row rewrite behavior, composite index key ordering,
equality-prefix planner coverage, and b-tree cell assembly boundaries used by
the native bounded `wp_options(autoload, option_name)` replacement planner
when an `autoload` change moves the secondary-index entry. The direct
libsqlite harness passed 173 PHP tests with 1138 assertions and 0 failures,
and `examples/application-composite-indexed-option-replacement-plan.php` ran
successfully, reporting updated table/index page images `[2,3]`, composite
index records `[no, siteurl, 1]` and `[no, cron_lock, 2]`, and indexed lookup
of `siteurl` through `optionRowByIndexedAutoloadAndName('no',
'SITEURL')`.

Before starting a root harness for this slice, the required preflight returned
no active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

This worker then ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 188 test files, 20303 assertions, 0 failures.

For the bounded composite `autoload, option_name` index maintenance slice on
2026-05-23, the focused upstream runner passed `insert.test`, `index.test`,
`index7.test`, `where.test`, `whereH.test`, `update.test`, and `btree01.test`
with 0 errors out of 1479 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test index7.test where.test whereH.test update.test btree01.test
```

This maps rowid INSERT persistence, composite index key ordering,
partial-index boundaries, equality-prefix planner coverage, UPDATE row rewrite
behavior, and b-tree cell assembly boundaries used by the native bounded
`wp_options(autoload, option_name)` insert page-image planner. The direct
libsqlite harness passed 172 PHP tests with 1131 assertions and 0 failures,
and `examples/application-composite-indexed-generated-option-insert-plan.php`
ran successfully, reporting updated table/index page images `[2,3]`,
composite index records `[no, cron_lock, 2]`, `[yes, siteurl, 1]`,
`[yes, home, 3]`, and indexed lookup of `home` through
`optionRowByIndexedAutoloadAndName('yes', 'HOME')`.

Before starting a root harness for this slice, the required preflight first
found a short-lived active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 1181706 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 1181706
# process exited before owner details could be read
```

A second preflight immediately afterward returned no active root harness, so
this worker ran:

```sh
php tools/run-tests.php
```

Result on 2026-05-23: 186 test files, 20114 assertions, 2 failures. The
captured visible failure was unrelated to this lane, in
`lanes/difftastic/tests/TokenDifferTest.php` for
`application command env display options wrap tabbed block metadata`. The full
root run output exceeded the tool capture before the second failure context,
so the aggregate result is recorded as a repo integration blocker rather than
a libsqlite implementation blocker.

For the bounded safe partial `option_name` index maintenance slice on
2026-05-23, the focused upstream runner passed `insert.test`, `index.test`,
`index7.test`, `update.test`, and `btree01.test` with 0 errors out of 1144
tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test index7.test update.test btree01.test
```

This maps SQLite partial-index usability boundaries from `index7.test`, rowid
table INSERT persistence, UPDATE row rewrite behavior, index b-tree payload
ordering, and b-tree cell assembly boundaries. The direct libsqlite harness
passed 171 tests with 1124 assertions and 0 failures, and
`examples/application-partial-indexed-generated-option-insert-plan.php` ran
successfully, reporting updated table/index page images `[2,3]` and partial
index records `home -> 2`, `siteurl -> 1`.

Before starting a root harness for this slice, the required preflight found an
active aggregate run plus a focused Syncthing run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 1076399 php tools/run-tests.php
# 1076696 php tools/run-tests.php lanes/syncthing/tests
ps -o pid,user,etime,cmd -p 1076399,1076696
# 1076399 claude 00:06 php tools/run-tests.php
# 1076696 claude 00:05 php tools/run-tests.php lanes/syncthing/tests
```

Per lane instructions, this worker did not start a duplicate root harness. The
post-change root result is pending supervisor/integrator acceptance of the
active run.

## Focused Native Mapping: Composite Parent-Root Replacement Split

For the bounded composite `autoload, option_name` parent-root replacement
split slice on 2026-05-23, the focused upstream runner passed `update.test`,
`index.test`, `where.test`, `whereH.test`, and `btree01.test` with 0 errors
out of 1096 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test where.test whereH.test btree01.test
```

This maps SQLite UPDATE row rewrite behavior, composite index key ordering,
equality-prefix planner coverage, root-interior balancing after replacement
leaf splits, and b-tree cell/page assembly used by the native bounded
`wp_options(autoload, option_name)` replacement planner when an `autoload`
change moves an entry into a full destination leaf and the index-interior root
also has to grow.

The direct libsqlite harness passed 192 PHP tests with 1340 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-composite-index-parent-root-split-option-replacement-plan.php`
script ran successfully, reporting updated page images
`[1,2,3,4,10,11,12,13]`, a grown `index-interior` root with one divider, two
new interior pages with three cells each, split destination composite leaves,
and the rewritten option reachable through
`optionRowByIndexedAutoloadAndName('no', $optionName)`.

## Focused Native Mapping: Non-Root Table Parent Replacement Split

For the bounded table b-tree replacement split below a non-root parent on
2026-05-23, the focused upstream runner passed `update.test`, `insert.test`,
and `btree01.test` with 0 errors out of 813 tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test insert.test btree01.test
```

This maps SQLite UPDATE row rewrite behavior, rowid table INSERT persistence,
`sqlite3BtreeInsert`/`balance_nonroot` behavior when a non-root table parent
can absorb a new divider, and b-tree cell/page assembly boundaries used by
the native bounded `wp_options` replacement planner.

The direct libsqlite harness passed 193 PHP tests with 1357 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-nonroot-table-split-option-replacement-plan.php` script
ran successfully, reporting updated page images `[1,3,5,7]`, an unchanged
table root page 2, lower parent separators `(4,2)` and `(5,3)`, new rightmost
leaf page 7, and the rewritten `blogname` option with `autoload='no'`.

## Focused Native Mapping: Table Root Parent Replacement Split

For the bounded table b-tree replacement split that overflows a full
table-interior root on 2026-05-23, the focused upstream runner passed
`update.test`, `insert.test`, and `btree01.test` with 0 errors out of 813
tests:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test insert.test btree01.test
```

This maps SQLite UPDATE row rewrite behavior, rowid table INSERT persistence,
root table-interior growth after a child leaf split overflows its parent, and
b-tree cell/page assembly boundaries used by the native bounded `wp_options`
replacement planner.

The direct libsqlite harness passed 194 PHP tests with 1379 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-table-parent-root-split-option-replacement-plan.php`
script ran successfully, reporting updated page images `[1,2,36,37,38,39]`,
a grown one-cell table-interior root, two new lower table-interior pages with
16 and 17 cells, a split target leaf pair, and the rewritten `blogname`
option with `autoload='no'`.

## Strengthened Static Inventory And Non-Root Parent Overflow Slice

On 2026-05-23 this lane rechecked the hydrated blobless upstream cache without
hydrating unrelated paths. The checkout remained clean at
`8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`. The strengthened static
inventory counted 2131 hydrated `src`, `test`, `tool`, `ext`, and `mptest`
paths, including 1189 `test/*.test` Tcl scripts, 278 `ext/**/*.test` Tcl
scripts, 32 `test/*.tcl` harness files, 33 `test` C programs, 47
`src/test*.(c|h)` helper files, 6 `mptest` files, and 76 tool-side
test-like C/Tcl/test files. A bounded static Tcl scan counted 68575
test-command-looking lines, including 51981 common `do_*` command lines and
21198 `do_*_test` family command lines. These counts supplement the completed
`veryquick` runner; SQLite `all` and `release` permutations remain unrun.

For the bounded table b-tree replacement slice where a target leaf split also
overflows a full non-root table-interior parent, the focused upstream runner
passed again:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test insert.test btree01.test
```

Result: 0 errors out of 813 tests in 00:00.

This maps SQLite UPDATE row rewrite behavior, rowid table INSERT persistence,
`balance_nonroot` propagation when a full non-root table-interior parent
splits, and b-tree page/cell assembly boundaries. The native PHP planner now
threads table-parent ancestry during writable `wp_options` replacement
planning, rewrites the split target leaf, splits the full non-root parent into
left/right table-interior pages, and promotes the divider into the root when
the root can absorb it. Non-root index parent overflow propagation remains a
future slice.

The direct libsqlite harness passed 195 PHP tests with 1401 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-nonroot-table-parent-split-option-replacement-plan.php`
script ran successfully, reporting updated page images `[1,2,3,37,39,40]`, a
two-cell table-interior root, two split non-root parent pages with 16 and 17
cells, split target leaves, and the rewritten `blogname` option with
`autoload='no'`.

## Focused Native Mapping: Non-Root Index Parent Split

For the bounded secondary-index insert slice where a target index leaf split
also overflows a full non-root index-interior parent, the focused upstream
runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  insert.test index.test btree01.test
```

Result: 0 errors out of 809 tests in 00:00.

This maps SQLite rowid INSERT persistence, index b-tree key ordering,
`balance_nonroot` propagation when a full non-root index-interior parent
splits, and b-tree page/cell assembly boundaries. The native PHP planner now
threads index-parent ancestry during writable `wp_options` insert planning,
rewrites the split target leaf, splits the full non-root index parent into
left/right index-interior pages, and promotes the divider into the root when
the root can absorb it. Replacement source-leaf rebalancing and broader
non-root index replacement propagation remain future slices.

The direct libsqlite harness passed 196 PHP tests with 1421 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-nonroot-index-parent-split-option-insert-plan.php` script
ran successfully, reporting updated page images `[1,2,3,4,11,13,14]`, a
two-cell index-interior root, split non-root parent pages with three cells
each, split target leaves with three cells each, and the inserted option
reachable through `optionRowByIndexedAutoloadAndName('yes',
$optionName)`.

Before starting a root harness for this slice, the required duplicate-root
preflight found an active aggregate run:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
# 2694170 php tools/run-tests.php
ps -o pid,user,etime,cmd -p 2694170
# 2694170 claude 00:16 php tools/run-tests.php
```

Per lane instructions, this worker did not start a duplicate root harness. The
aggregate result is pending supervisor/integrator acceptance of the active
run.

## Focused Native Mapping: Composite Index Root Collapse Replacement

For the bounded composite `autoload, option_name` replacement slice where
changing `autoload` removes the only entry from one child leaf below a
two-child index root, the focused upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test
```

Result: 0 errors out of 761 tests in 00:00.

This maps SQLite UPDATE row rewrite behavior, index b-tree key ordering,
`balance_nonroot` sibling collapse, `balance_shallower` root-depth reduction,
freePage2-style obsolete child page release, and b-tree page/cell assembly
boundaries. The native PHP planner now handles the bounded Application case
where an autoload rewrite moves an index entry from one root child leaf into
its sibling, the source leaf becomes empty, the root can be rebuilt as an
index leaf containing the remaining keys, and the obsolete child pages are
returned to freelist metadata. Broader underfilled-leaf redistribution,
multi-sibling collapse, non-root index replacement propagation, pointer-map
updates, journaling, and WAL remain future slices.

The direct libsqlite harness passed 197 PHP tests with 1433 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-index-root-collapse-option-replacement-plan.php` script
ran successfully, reporting updated page images `[1,2,3,4]`, an index root
rewritten from `index-interior` to `index-leaf`, obsolete child pages `[4,5]`
on the freelist, and the rewritten `siteurl` option reachable through
`optionRowByIndexedAutoloadAndName('no', 'siteurl')`.

## Focused Native Mapping: Auto-Vacuum Pointer Maps

For the auto-vacuum pointer-map metadata slice, the focused upstream runner
passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test autovacuum2.test incrvacuum.test incrvacuum2.test incrvacuum3.test
```

Result: 5 Tcl scripts, 0 errors out of 587 tests in 00:01.

The static focused inventory for those files contains 172 `do_test`/
`do_execsql_test`/`do_catchsql_test` command lines. This maps SQLite
`auto_vacuum` and `incremental_vacuum` header state, pointer-map page
placement from `ptrmapPageno()`, 5-byte pointer-map entries, and the
root/free/btree/first-overflow/overflow-continuation entry types documented in
`src/btreeInt.h`. The native PHP reader now parses the largest-root and
incremental-vacuum header fields, computes pointer-map page/offset locations,
identifies pointer-map pages, and reads validated pointer-map entries.

The direct libsqlite harness passed 198 PHP tests with 1456 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-pointer-map-diagnostics.php` script ran
successfully. It reports an auto-vacuum Application-shaped database with page 2
as the pointer-map page, page 3 as the `wp_options` root, page 4 as a b-tree
child, pages 5 and 6 as an overflow chain, page 7 as a free page, and the
readable `siteurl` option. Pointer-map mutation during page moves, journaling,
and WAL remain future slices.

## Focused Native Mapping: Multi-Sibling Index Leaf Redistribution Replacement

For the bounded composite `autoload, option_name` replacement slice where an
autoload rewrite removes one entry from a source leaf below a multi-sibling
index root, the focused upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test
```

Result: 3 Tcl scripts, 0 errors out of 761 tests in 00:00. A bounded static
scan of those files counted 254 `do_*` test command lines.

This maps SQLite UPDATE row rewrite behavior, index b-tree key ordering,
delete-triggered `balance_nonroot` redistribution when a leaf has more than
two-thirds free space, and b-tree page/cell assembly boundaries. The native
PHP planner now detects a non-empty underfilled source index leaf after moving
a composite `wp_options` entry, redistributes it with an adjacent sibling when
the parent has multiple children, rewrites the parent divider, and then inserts
the replacement key into the updated tree.

The direct libsqlite harness passed 199 PHP tests with 1470 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-index-redistribute-option-replacement-plan.php` script ran
successfully, reporting updated page images `[2,3,4,5,6]`, a two-cell
`index-interior` root, redistributed source/sibling leaves with three cells
each, a destination leaf with four cells, and the rewritten option reachable
through `optionRowByIndexedAutoloadAndName('no', $optionName)`.

## Focused Native Mapping: Root-Parent Index Leaf Merge Replacement

For the bounded composite `autoload, option_name` replacement slice where an
autoload rewrite underfills a source leaf and the remaining source/sibling
cells cannot be redistributed into two legal leaf pages, the focused upstream
runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test delete2.test delete3.test delete4.test
```

Result: 6 named Tcl scripts / 9 runner script-permutation runs, 0 errors out
of 804 tests in 00:00. A bounded static scan of those six files counted 313
`do_*` test command lines. A targeted static scan of `src/btree.c` counted 73
`balance_nonroot`/`balance_shallower`/`freePage2`/related merge-balance lines.

This maps SQLite UPDATE row rewrite behavior, index b-tree key ordering,
delete-triggered `balance_nonroot` sibling-count reduction, parent divider
removal, obsolete-page release through free-list metadata, and b-tree
page/cell assembly boundaries. The native PHP planner now falls back from
redistribution to a root-parent leaf merge: it moves the parent divider into
the merged leaf, removes the divider from the root parent, rewires the root's
child pointer/right-most pointer, frees the obsolete index leaf page, and then
inserts the replacement key into the updated tree.

The direct libsqlite harness passed 200 PHP tests with 1487 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-index-merge-option-replacement-plan.php` script
ran successfully, reporting updated page images `[1,2,3,4,5,6]`, a one-cell
`index-interior` root, merged source/sibling index leaves with page 6 moved
onto the freelist, and the rewritten option reachable through
`optionRowByIndexedAutoloadAndName('no', $optionName)`.

## Focused Native Mapping: Auto-Vacuum Pointer-Map Mutation Planning

For the bounded auto-vacuum pointer-map mutation slice, the focused upstream
runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test
```

Result: 2 Tcl scripts, 0 errors out of 507 tests in 00:00.

The mapped upstream boundary is SQLite's pointer-map contract from
`src/btreeInt.h` and `src/btree.c`: entries are five bytes, page 1 and
pointer-map pages have no pointer-map entry, freed pages are written as
`PTRMAP_FREEPAGE` with parent 0 from `freePage2()`, auto-vacuum allocation
skips pointer-map pages, first overflow pages point back to the owning b-tree
page, later overflow pages point to the previous overflow page, and non-root
b-tree pages point to their parent b-tree page.

The native PHP slice now exposes `SQLiteDatabase::planPointerMapUpdates()` for
standalone pointer-map page-image writes, updates `planPageFreeList()` to mark
freed auto-vacuum pages as `FREE_PAGE`, rejects attempts to free pointer-map
pages, and skips pointer-map pages when appending new pages in auto-vacuum
databases.

The direct libsqlite harness passed 202 PHP tests with 1508 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-pointer-map-mutation-plan.php` script ran
successfully, reporting updated page images `[1,2,6]`, page 6 as the new
freelist trunk, the pointer-map entry for page 6 rewritten to `free-page`, and
the existing `siteurl` row still readable through the native table reader.

Remaining boundaries: non-root index replacement propagation, integrating
pointer-map updates into broader auto-vacuum table/index page moves, secure
delete variants, journaling, and WAL remain future slices.

## Focused Native Mapping: Auto-Vacuum Overflow Insert Pointer Maps

For the bounded auto-vacuum `wp_options` large insert slice, the focused
upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test insert.test corrupt3.test
```

Result: 4 named Tcl scripts / 8 runner script-permutation runs, 0 errors out
of 839 tests in 00:00. A bounded static scan of those Tcl scripts counted 230
`do_*` command lines, and a targeted static scan of `src/btree.c` plus
`src/btreeInt.h` counted 27 pointer-map overflow contract lines.

This maps SQLite's auto-vacuum pointer-map behavior for newly allocated
overflow chains: the first overflow page is stored as `PTRMAP_OVERFLOW1` with
the owning b-tree page as parent, continuation pages are stored as
`PTRMAP_OVERFLOW2` with the previous overflow page as parent, and integrity
checks verify overflow-chain pointer-map consistency.

The native PHP slice now threads those pointer-map page-image updates into
`planOptionRowInsert()` when a large inserted option allocates overflow
pages in an auto-vacuum database.

The direct libsqlite harness passed 203 PHP tests with 1519 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-autovacuum-overflow-option-insert-plan.php` script ran
successfully, reporting updated page images `[1,2,3,4,5,6]`, overflow pages
`[4,5,6]`, pointer-map entries `first-overflow-page -> 3`, `overflow-page ->
4`, and `overflow-page -> 5`, and a readable `theme_mods_twentyfive` option.

## Focused Native Mapping: Auto-Vacuum Overflow Replacement Pointer Maps

For the bounded auto-vacuum `wp_options` large replacement slice, the focused
upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test update.test corrupt3.test
```

Result: 4 named Tcl scripts / 8 runner script-permutation runs, 0 errors out
of 791 tests in 00:01. A bounded static scan of those Tcl scripts counted 293
`do_*` command lines, and a targeted static scan of `src/btree.c` plus
`src/btreeInt.h` counted 221 pointer-map/overflow/update/freePage2 contract
lines.

This maps SQLite's auto-vacuum pointer-map behavior when UPDATE rewrites a
large record: the new overflow chain is allocated before obsolete overflow
pages are returned to the freelist, the first new overflow page is stored as
`PTRMAP_OVERFLOW1` with the owning b-tree page as parent, continuation pages
are stored as `PTRMAP_OVERFLOW2` with the previous overflow page as parent,
and obsolete overflow pages are rewritten as `PTRMAP_FREEPAGE` when freed.

The native PHP slice now threads those pointer-map page-image updates into
`planOptionRowReplace()` for large replacement-created overflow chains.
The owner b-tree page is resolved from the planned table image before writing
pointer-map entries so replacement cells below table splits/growth do not
inherit a stale root-page owner.

The direct libsqlite harness passed 204 PHP tests with 1539 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-autovacuum-overflow-option-replacement-plan.php` script
ran successfully, reporting obsolete overflow pages `[4,5]` as `free-page`
entries, new overflow pages `[6,7,8,9]` with parent links `3,6,7,8`, and a
readable rewritten `theme_mods_twentyfive` option.

## Focused Native Mapping: Non-Root Composite Index Leaf Merge

For the bounded `wp_options(autoload, option_name)` replacement slice where
the source leaf sits below a non-root index-interior parent, the focused
upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test
```

Result: 3 named Tcl scripts / 7 runner script-permutation runs, 0 errors out
of 761 tests in 00:00. A bounded static scan counted 254 `do_*` command lines
in those Tcl scripts and 56 targeted `balance_*`, `dropCell()`,
`insertCell()`, `editPage()`, and `freePage2()` source contract lines in
`src/btree.c`.

This maps SQLite's UPDATE row rewrite behavior, composite index key ordering,
delete-triggered `balance_nonroot()` leaf merge below an index-interior parent,
parent divider removal, right-most pointer reassignment, and obsolete page
release through `freePage2()`.

The native PHP slice now lets a replacement autoload change merge an
underfilled composite-index source leaf below a non-root parent when the lower
parent remains sufficiently populated after the divider is removed. The direct
libsqlite harness passed 205 PHP tests with 1555 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests
```

The new
`examples/application-nonroot-index-merge-option-replacement-plan.php` script
ran successfully, reporting updated page images `[1,2,4,5,8,9]`, lower parent
page 4 with 3 cells and right-most pointer 8, obsolete leaf page 9 on the
freelist, and a readable rewritten option through the composite index.

Remaining boundaries: non-root parent underflow after source-leaf
merge/rebalance, broader table/index page move pointer-map updates,
secure-delete variants, journaling, and WAL remain future slices.

## Focused Native Mapping: Auto-Vacuum B-Tree Pointer Maps For Root Growth

For the bounded auto-vacuum `wp_options` replacement slice where a larger row
payload grows a table-leaf root into a table-interior root with two new child
leaf pages, the focused upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test update.test btree01.test
```

Result: 4 named Tcl scripts / 6 runner script-permutation runs, 0 errors out
of 997 tests in 00:01. A bounded static scan counted 292 `do_*` Tcl command
lines in those scripts and 135 targeted pointer-map, balance, and
auto-vacuum source contract lines in `src/btree.c` and `src/btreeInt.h`.

This maps SQLite's auto-vacuum pointer-map ownership contract for b-tree page
moves: root b-tree pages use `PTRMAP_ROOTPAGE`, and non-root table/index
b-tree children use `PTRMAP_BTREE` with the owning parent page number. The
native PHP planner now derives pointer-map entries from planned table/index
b-tree page images after inserts or replacements have performed leaf splits,
root growth, parent-root splits, or bounded non-root parent propagation.

The direct libsqlite harness passed 206 PHP tests with 1564 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-autovacuum-table-root-split-option-replacement-plan.php`
script ran successfully, reporting updated page images `[1,2,3,4,5]`, a
table-interior `wp_options` root at page 3, child leaf pages 4 and 5, and
`btree-page` pointer-map entries for pages 4 and 5 pointing back to page 3.

Remaining boundaries: non-root parent underflow after source-leaf
merge/rebalance, secure-delete variants, journaling, WAL, and general SQL
execution remain future slices.

## Focused Native Mapping: Secure-Delete Page-Free Clearing

For the bounded page-free slice where a freed page becomes a leaf entry on an
existing freelist trunk, the focused upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  securedel.test securedel2.test delete.test update.test
```

Result: 4 named Tcl scripts / 9 runner script-permutation runs, 0 errors out
of 821 tests in 00:00. A bounded static scan counted 223 `do_*` Tcl command
lines in those scripts and 36 targeted `secure_delete`, `BTS_SECURE_DELETE`,
`BTS_FAST_SECURE`, `freePage2()`, and page-zeroing source contract lines in
`src/btree.c` and `src/btreeInt.h`.

This maps SQLite's `PRAGMA secure_delete=ON` page-free behavior in
`freePage2()`: the freed page image is zeroed before freelist metadata is
updated. In non-secure mode, a page freed as a freelist leaf may avoid writing
that page body after the trunk receives the leaf pointer.

The direct libsqlite harness passed 208 PHP tests with 1578 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-secure-delete-obsolete-overflow-pages.php` script
ran successfully, reporting updated page images `[1,2,3,4]`, obsolete
overflow pages `[3,4]` on the freelist, zeroed obsolete overflow page `[4]`,
and a readable rewritten `obsolete_large_cache` option.

Remaining boundaries: non-root parent underflow after source-leaf
merge/rebalance, cell-level FAST secure-delete freeblock clearing, journaling,
WAL, and general SQL execution remain future slices.

## Focused Native Mapping: Non-Root Composite Index Parent Collapse

For the bounded `wp_options(autoload, option_name)` replacement slice where
the source-leaf merge leaves its non-root index-interior parent underfilled
below a two-child root, the focused upstream runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test delete2.test delete3.test delete4.test
```

Result: 6 named Tcl scripts / 9 runner script-permutation runs, 0 errors out
of 804 tests in 00:00. A bounded static scan counted 293 `do_*` Tcl command
lines in those scripts and 36 targeted `balance_nonroot`,
`balance_shallower`, `dropCell()`, `insertCell()`, `editPage()`, and
`freePage2()` source contract lines in `src/btree.c`.

This maps SQLite's UPDATE row rewrite behavior, composite index key ordering,
delete-triggered `balance_nonroot()` leaf merge that underfills a non-root
parent, root-level parent collapse/`balance_shallower`, parent divider
promotion into the collapsed root, and obsolete leaf/interior-page release
through `freePage2()`.

The native PHP slice now lets a replacement autoload change merge an
underfilled composite-index source leaf below a non-root parent, then collapse
that underfilled parent and its sibling into the two-child root. The direct
libsqlite harness passed 209 PHP tests with 1596 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-index-parent-collapse-option-replacement-plan.php` script
ran successfully, reporting updated page images `[1,2,3,5,6,7]`, a collapsed
index root at page 3 with left children `[5,6,9]` and right-most pointer 10,
merged leaf page 6 with 3 cells, obsolete pages `[7,4,8]` on the freelist,
and a readable rewritten option through the composite index.

Remaining boundaries: non-root parent underflow beyond the current two-child
root collapse shape, cell-level FAST secure-delete freeblock clearing,
journaling, WAL, and general SQL execution remain future slices.

## Focused Native Mapping: Multi-Child Root Composite Index Parent Merge

For the bounded `wp_options(autoload, option_name)` replacement slice where
the source-leaf merge leaves its non-root index-interior parent underfilled
below a root that still has more than two child parents, the focused upstream
runner passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test delete2.test delete3.test delete4.test
```

Result: 6 named Tcl scripts / 9 runner script-permutation runs, 0 errors out
of 804 tests in 00:00. The mapped static boundary is the same bounded
`balance_nonroot`, `balance_shallower`, `dropCell()`, `insertCell()`,
`editPage()`, and `freePage2()` source contract used by the preceding parent
collapse slice: 293 `do_*` Tcl command lines and 36 targeted source contract
lines in `src/btree.c`.

This maps SQLite's UPDATE row rewrite behavior, composite index key ordering,
delete-triggered `balance_nonroot()` leaf merge that underfills a non-root
parent, root divider removal without reducing root height, adjacent
index-interior parent merge, and obsolete leaf/interior-page release through
`freePage2()`.

The native PHP slice now lets a replacement autoload change merge the
underfilled lower index parent with an adjacent sibling parent under a
multi-child root, leaving the root at the same height with one fewer divider.
The direct libsqlite harness passed 210 PHP tests with 1617 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-index-parent-merge-option-replacement-plan.php` script ran
successfully, reporting updated page images `[1,2,3,4,5,6,7]`, an index root
at page 3 with left child `[4]` and right-most pointer 11, merged lower parent
page 4 with left children `[5,6,9]` and right-most pointer 10, obsolete pages
`[7,8]` on the freelist, and a readable rewritten option through the composite
index.

Remaining boundaries: non-root composite-index parent redistribution when the
adjacent interior-parent merge does not fit, cell-level FAST secure-delete
freeblock clearing, journaling, WAL, and general SQL execution remain future
slices.

## Focused Native Mapping: `json_extract()` SQL Result Typing

For the bounded JSON extraction slice, this isolated worktree reused prior
focused upstream JSON evidence because the hydrated `.upstream-cache` checkout
was absent here:

```sh
json101.test json102.test json501.test
json107.test json101.test json102.test jsonb01.test
```

Prior accepted evidence for the first group passed 780 upstream tests with 0
errors and covered JSON path inspection over strict JSON, JSON5 text, cast
text BLOBs, missing paths, scalar paths, and array/object paths. Prior
accepted evidence for the JSONB group passed 650 upstream tests with 0 errors
and covered JSONB validation/path handling boundaries.

The native PHP slice adds `json_extract(X,P...)` SQL-result typing for strict
JSON text, JSON5 text, cast text BLOBs, JSONB blobs, SQL NULL option values,
missing paths, booleans as `1`/`0` for a single path, object/array results as
canonical JSON text, and multi-path JSON array output. The focused libsqlite
harness passed 226 PHP tests with 1850 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-json-extract-option-preflight.php` script ran
successfully, reporting strict JSON, JSON5, JSONB, and SQL NULL
`wp_options.option_value` inputs with SQLite-style extracted enabled flags,
titles, last rule objects, and multi-path summary JSON.

Remaining boundaries: full SQL expression evaluation, `json_extract()` subtype
propagation and all BLOB ambiguity edge cases beyond existing cast text/JSONB
handling, JSON aggregates, table-valued `json_each`/`json_tree`, WAL,
rollback/savepoint, and b-tree delete/rebalance remain future slices.

## Focused Native Mapping: `json_extract()` JSON Subtype Propagation

For the bounded JSON subtype slice, this isolated worktree reused the same
prior focused upstream JSON evidence as the SQL-result typing slice because
the hydrated `.upstream-cache` checkout was absent here:

```sh
json101.test json102.test subtype1.test
json101.test json102.test json501.test
json107.test json101.test json102.test jsonb01.test
```

Prior accepted evidence passed 622 upstream tests with 0 errors for
`json_quote()` and subtype interaction over `json101.test`, `json102.test`,
and `subtype1.test`; 780 upstream tests with 0 errors for JSON path
inspection over strict JSON, JSON5 text, cast text BLOBs, missing paths,
scalar paths, and array/object paths; and 650 upstream tests with 0 errors for
JSONB validation/path handling boundaries.

The native PHP slice adds `SQLiteJsonExtract::extractJsonArgument()` for the
bounded SQLite behavior where `json_extract(X,P...)` object/array and
multi-path results carry the JSON subtype when passed into JSON constructors.
Single-path SQL scalars keep SQLite SQL typing: booleans become `1`/`0`, text
stays text, and missing/null paths become SQL NULL. The focused libsqlite
harness passed 227 PHP tests with 1858 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-json-extract-subtype-option-diagnostics.php` script ran
successfully, reporting copied strict JSON, JSON5, and JSONB
`wp_options.option_value` inputs where extracted object/array and multi-path
values are embedded into JSON constructor diagnostics without being
double-quoted.

Dependency closure: no new support component is needed. This slice reuses the
existing lane-local JSON5 parser, JSONB encoder/decoder, path locator,
canonical JSON encoder, JSON subtype wrapper, and JSON constructor helpers;
it counts no shared support-library progress.

Remaining boundaries: full SQL expression evaluation, subtype propagation
through every SQL operator/function boundary, aggregate JSON functions,
table-valued `json_each`/`json_tree`, broader BLOB ambiguity cases, WAL,
rollback/savepoint, and b-tree delete/rebalance remain future slices.

## Focused Native Mapping: `json_remove()` Text Result Paths

For this bounded `json_remove(X,P...)` text-result slice, this isolated
worktree reused prior focused upstream JSON remove evidence because the
hydrated `.upstream-cache` checkout was absent here:

```sh
json102.test jsonb01.test
```

Prior accepted evidence passed 356 upstream tests with 0 errors for adjacent
`json_remove()`/`jsonb_remove()` path behavior, including object-member
removal, array removal, reverse array indexes, missing-path no-ops, multiple
path argument order, root removal to SQL `NULL`, and malformed JSONB
rejection.

The native PHP slice adds `SQLiteJsonRemove::remove()` for SQLite
`json_remove()` text-result behavior over strict JSON text, supported JSON5
text, cast text BLOBs, SQLite JSONB blobs, SQL NULL input, no-path canonical
text output, multiple path removals in SQLite argument order, and `$` root
removal to SQL NULL. It reuses the existing lane-local JSON5 decoder, JSONB
edit engine, JSON path parser, and canonical JSON encoder. The focused
libsqlite harness passed 228 PHP tests with 1867 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-json-remove-option-preflight.php` script ran
successfully, removing obsolete plugin settings from a local
`wp_options.option_value` JSON fixture and printing canonical JSON text
without requiring the SQLite extension.

Dependency closure: no new support component is needed. This slice reuses
existing bounded lane-local JSON5, JSONB, JSON path, and canonical JSON
components; it counts no shared support-library progress.

Remaining boundaries: `jsonb_remove()` public text/BLOB return selection is
still covered by the existing JSONB helper rather than a SQL dispatcher, and
full SQL expression evaluation, JSON aggregate functions, table-valued
`json_each`/`json_tree`, broader BLOB ambiguity cases, WAL,
rollback/savepoint, and b-tree delete/rebalance remain future slices.

## Focused Native Mapping: `json_remove()`/`jsonb_remove()` SQL Dispatch

For this bounded SQL-function dispatch slice, this isolated worktree reused
prior focused upstream JSON remove evidence because the hydrated
`.upstream-cache` checkout was absent here:

```sh
json102.test jsonb01.test
```

Prior accepted evidence passed 356 upstream tests with 0 errors for adjacent
`json_remove()`/`jsonb_remove()` path behavior, including object-member
removal, array removal, reverse array indexes, missing-path no-ops, multiple
path argument order, root removal to SQL `NULL`, and malformed JSONB
rejection.

The native PHP slice adds `SQLiteJsonRemove::removeSqlFunction()` for the
public SQL boundary where `json_remove()` returns canonical JSON text and
`jsonb_remove()` returns SQLite JSONB blob bytes. It covers strict JSON text,
supported JSON5 text, cast text BLOBs, SQLite JSONB blobs, SQL NULL input,
no-path output, multiple path removals in SQLite argument order, and `$` root
removal to SQL NULL. The focused libsqlite harness passed 229 PHP tests with
1875 assertions and 0 failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new
`examples/application-json-remove-sql-dispatch-preflight.php` script lets
Application migration or repair tooling preflight copied `wp_options` JSON
cleanup while preserving the SQLite result-type distinction between text JSON
and JSONB blobs, without requiring the SQLite extension.

Dependency closure: no new support component is needed. This slice reuses
existing bounded lane-local JSON5, JSONB, JSON path, canonical JSON, and BLOB
wrapper components; it counts no shared support-library progress.

Remaining boundaries: full SQL expression evaluation, broader JSONB BLOB
ambiguity edge cases, aggregate JSON functions, table-valued
`json_each`/`json_tree`, WAL, rollback/savepoint, and b-tree delete/rebalance
remain future slices.

## Focused Native Mapping: `json_patch()`/`jsonb_patch()` SQL Dispatch

For this bounded SQL-function dispatch slice, this isolated worktree reused
prior focused upstream JSON patch evidence because the hydrated
`.upstream-cache` checkout was absent here:

```sh
json104.test json101.test json502.test
```

Prior accepted evidence passed 325 upstream tests with 0 errors for adjacent
`json_patch()`/`jsonb_patch()` merge-patch behavior, including RFC-7396 object
merge examples, object-member deletion with patch `null`, nested object
stripping, target non-object objectification, array/scalar/null whole-value
replacement, SQL NULL propagation boundaries, and escaped object-label inputs.

The native PHP slice adds `SQLiteJsonPatch::patchSqlFunction()` for the public
SQL boundary where `json_patch()` returns canonical JSON text and
`jsonb_patch()` returns SQLite JSONB blob bytes. It covers strict JSON text,
supported JSON5 text, cast text BLOBs, SQLite JSONB blobs, SQL NULL input,
object-member deletion, nested object merge, non-object target objectification,
array/scalar whole-value replacement, and invalid function rejection. The
focused libsqlite harness passed 230 PHP tests with 1887 assertions and 0
failures:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

The new `examples/application-json-patch-sql-dispatch-preflight.php` script lets
Application migration or repair tooling preflight copied `wp_options` JSON
merge-patch updates while preserving the SQLite result-type distinction
between text JSON and JSONB blobs, without requiring the SQLite extension.

Dependency closure: no new support component is needed. This slice reuses
existing bounded lane-local JSON5, JSONB, canonical JSON, and BLOB wrapper
components; it counts no shared support-library progress.

Remaining boundaries: full SQL expression evaluation, broader JSONB BLOB
ambiguity edge cases, aggregate JSON functions, table-valued
`json_each`/`json_tree`, WAL, rollback/savepoint, and b-tree delete/rebalance
remain future slices.

## Current-Base Rebase-Prep: `json_group_array()`/`json_group_object()` Row Aggregation

The current-head rebase-prep marker carries the original isolated JSON aggregate row-boundary slice on top of current `main`. It reuses prior focused upstream JSON evidence (`json101.test`, `json102.test`, and `jsonb01.test`) because this detached rebase-prep worktree has no hydrated upstream cache.

The native PHP slice adds `SQLiteJsonAggregate::jsonGroupArray()` and `SQLiteJsonAggregate::jsonGroupObject()` for ordered aggregate rows, SQL NULLs, booleans as SQLite JSON `1`/`0`, JSON subtype passthrough, JSONB BLOB passthrough, empty groups, malformed raw BLOB rejection, text labels, and aggregate row-shape errors. It intentionally does not claim SQL planner features such as `DISTINCT`, `FILTER`, or aggregate `ORDER BY`.

Dependency closure: no new support component is needed. This slice reuses existing bounded lane-local JSON constructor, JSON subtype, JSONB, and BLOB wrapper components; it counts no shared support-library progress.

## Focused Native Mapping: `jsonb_group_array()`/`jsonb_group_object()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice extends the existing bounded JSON aggregate row
boundary to the SQLite SQL result-type dispatch boundary. Native
`SQLiteJsonAggregate` now exposes `jsonGroupArraySqlFunction()` and
`jsonGroupObjectSqlFunction()` where `json_group_array()` and
`json_group_object()` return canonical JSON text, while `jsonb_group_array()`
and `jsonb_group_object()` return SQLite JSONB blob bytes. Invalid aggregate
function names are rejected. The slice keeps the prior limits: no general SQL
aggregate planner, `DISTINCT`, `FILTER`, aggregate `ORDER BY`, or table-valued
JSON support is claimed.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses the prior focused JSON aggregate/JSONB evidence for
the same upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test
```

Prior result: passed with 0 errors for aggregate JSON constructors, JSONB
input/output boundaries, JSON subtype passthrough, and malformed JSONB
rejection. Prior applicable runner evidence remains the complete SQLite
`veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported JSON text
aggregate output plus decoded/hex JSONB aggregate output, focused PHP passed
1 selected test file, 1904 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON aggregate, JSON constructor, JSON subtype, JSONB, and
BLOB wrapper components; it counts no shared support-library progress.

## Focused Native Mapping: `json_insert()`/`json_set()`/`json_replace()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL result-type dispatch
boundary for `json_insert()`, `json_set()`, `json_replace()`, and their
`jsonb_*` variants. Native `SQLiteJsonMutation::mutateSqlFunction()` reuses
the existing lane-local JSONB mutation engine, returning canonical JSON text
for `json_*` functions and SQLite JSONB blob bytes for `jsonb_*` functions.
SQL scalar mutation values stay scalar, while `SQLiteJsonSubtypeValue` and
SQLite JSONB blobs embed as JSON fragments. The slice also rejects invalid
function names, odd path/value argument shapes, non-string later paths,
malformed input JSON/JSON5/JSONB, and raw non-JSONB BLOB values.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON mutation and JSONB evidence for
the same upstream behavior cluster:

```sh
json101.test json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonMutation.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-jsonb-mutate-option-field.php
php lanes/libsqlite/examples/application-jsonb-mutate-option-field.php '{"plugin":{"enabled":false,"rules":["seo"]}}' json_set '$.plugin.enabled' true '$.plugin.settings' 'json:{"source":"native"}'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported a SQLite text
JSON result for `json_set()` with decoded output, focused PHP passed 1
selected test file, 1913 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5, JSONB, canonical JSON, JSON subtype, and BLOB
wrapper components; it counts no shared support-library progress.

## Focused Native Mapping: `json_extract()`/`jsonb_extract()` JSON-Argument Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite JSON-constructor argument
dispatch boundary for `json_extract()` and `jsonb_extract()`. Native
`SQLiteJsonExtract::extractJsonArgumentSqlFunction()` validates the function
name and reuses the existing lane-local extraction, subtype, JSONB, and
constructor paths. `json_extract()` object/array and multi-path results are
returned as JSON subtype text for constructor embedding; `jsonb_extract()`
object/array and multi-path results are returned as SQLite JSONB blobs.
Scalar paths, missing paths, and SQL NULL keep SQLite SQL argument typing.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON extraction, subtype, and JSONB
evidence for the same upstream behavior cluster:

```sh
json101.test json102.test subtype1.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonExtract.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-extract-subtype-option-diagnostics.php
php lanes/libsqlite/examples/application-json-extract-subtype-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported constructor
diagnostics for `json_extract()` JSON subtype arguments and `jsonb_extract()`
JSONB blob arguments, focused PHP passed 1 selected test file, 2000
assertions, and 0 failures, and `git diff --check -- lanes/libsqlite` passed.
This worker did not start the root aggregate harness because root verification
was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON extraction, JSON path, inspection, JSONB, BLOB,
subtype, and constructor components; it counts no shared support-library
progress.

## Focused Native Mapping: `json_quote()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL function dispatch
boundary for `json_quote()`. Native
`SQLiteJsonQuote::jsonQuoteSqlFunction()` validates the function name and
delegates to the existing lane-local SQL-value JSON renderer, preserving SQL
NULL as JSON `null` text, integer/real/boolean rendering, ordinary text
quoting, JSON subtype pass-through, JSONB BLOB decoding, raw BLOB rejection,
malformed JSONB rejection, and invalid function-name rejection.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused `json_quote()` and subtype evidence
for the same upstream behavior cluster:

```sh
json101.test json102.test subtype1.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonQuote.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-quote-option-preflight.php
php lanes/libsqlite/examples/application-json-quote-option-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported SQL-dispatch
`json_quote()` fields for SQL NULL, numeric values, copied text,
control-character text, JSONB blobs, and raw BLOB rejection, focused PHP
passed 1 selected test file, 1978 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON quote, JSON subtype, JSONB, and BLOB wrapper
components; it counts no shared support-library progress.

## Focused Native Mapping: `json()`/`jsonb()` Canonical SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL result-type dispatch
boundary for `json()` and `jsonb()`. Native
`SQLiteJsonCanonical::jsonSqlFunction()` reuses the existing lane-local
canonicalizer, returning canonical JSON text for `json()` and SQLite JSONB
blob bytes for `jsonb()`. SQL NULL propagates, strict JSON text and SQLite
JSON5 text are normalized, cast text BLOBs use the existing text fallback,
SQLite JSONB BLOB inputs decode and re-encode, and malformed JSON, raw BLOBs,
and invalid function names are rejected.

Priority-refill 2026-05-25T16:13Z rebases the canonical dispatch behavior on
top of the accepted JSON constructor evidence and adds SQLite-style
case-insensitive function lookup plus one-argument SQL vector dispatch for
`json()` and `jsonb()`. Invalid function names and invalid arities are
rejected before dispatch. This preserves the older deferred canonical rework
cluster without replaying stale manifest/status conflicts.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON canonicalization and JSONB
evidence for the same upstream behavior cluster:

```sh
json101.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonCanonical.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-canonical-option-preflight.php
php lanes/libsqlite/examples/application-json-canonical-option-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: latest focused verification is recorded in `lane-status.json`. The
Application example now reports uppercase argument-vector `JSON()` text output
plus decoded `JSONB()` output for strict JSON, JSON5, cast text BLOB, JSONB,
and SQL NULL inputs. This worker did not start the root aggregate harness
because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON canonicalizer, JSON5 parser, JSONB, and BLOB wrapper
components; it counts no shared support-library progress.

## Focused Native Mapping: `json_error_position()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL function-name dispatch
boundary for `json_error_position(X)`. Native
`SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction()` validates the
function name, preserves SQL NULL propagation, and delegates text, JSON5,
cast-text BLOB, and SQLite JSONB position behavior to the existing lane-local
error-position engine. It does not broaden malformed escape/depth offset
coverage or add table-valued JSON behavior.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused upstream JSON diagnostic evidence for
the same behavior cluster:

```sh
json101.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonErrorPosition.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-error-position-preflight.php
php lanes/libsqlite/examples/application-json-error-position-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported matching direct
and SQL-dispatch `json_error_position()` offsets for JSON5 text, malformed
copied text, cast-text BLOB, JSONB, superficial-only JSONB, and SQL NULL
inputs, focused PHP passed 1 selected test file, 1952 assertions, and
0 failures, and `git diff --check -- lanes/libsqlite` passed. This worker did
not start the root aggregate harness because root verification was not
assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5 parser, JSONB error-position checks, cast-text BLOB
fallback, and SQL NULL handling; it counts no shared support-library progress.

## Focused Native Mapping: `json_type()`/`json_array_length()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL function dispatch
boundary for `json_type(X[,P])` and `json_array_length(X[,P])`. Native
`SQLiteJsonInspection::inspectionSqlFunction()` reuses the existing
lane-local text/JSON5/cast-text-BLOB/JSONB inspection engine and returns
SQLite scalar results: JSON type names, integer array lengths, `0` for
non-array located values, and SQL NULL for NULL inputs, NULL paths, or missing
paths. The slice also rejects invalid function names, malformed paths, and
malformed input JSON/JSON5/JSONB.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON inspection and JSONB evidence
for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonInspection.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-inspection-preflight.php
php lanes/libsqlite/examples/application-json-inspection-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported SQL-dispatch
`json_type()` and `json_array_length()` fields for strict JSON, JSON5,
cast-text BLOB, JSONB, and SQL NULL inputs, focused PHP passed 1 selected
test file, 1936 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5, JSONB, cast-text BLOB, path parsing, and canonical
inspection components; it counts no shared support-library progress.

## Focused Native Mapping: `json_valid()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL function dispatch
boundary for `json_valid()`. Native
`SQLiteJsonValidity::jsonValidSqlFunction()` validates the function name,
delegates strict text, JSON5, cast text BLOB, superficial JSONB, and strict
JSONB flags to the existing lane-local validator, propagates SQL NULL input,
keeps nullable `FLAGS` aligned with SQLite invalid-flag rejection, and keeps the existing invalid flag
range errors.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON validity and JSONB evidence for
the same upstream behavior cluster:

```sh
json101.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonValidity.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-validity-preflight.php
php lanes/libsqlite/examples/application-json-validity-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported SQL-dispatch
`json_valid()` fields for strict JSON, JSON5, cast-text BLOB, JSONB,
SQL NULL inputs and NULL `FLAGS` rejection, focused PHP passed 1 selected test
file, 1946 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5, JSONB, cast-text BLOB, and validity flag
components; it counts no shared support-library progress.

## Focused Native Mapping: `json_valid()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice rebases the JSON validity dispatch behavior on top
of the accepted JSON scalar/path stack. Native
`SQLiteJsonValidity::jsonValidSqlFunction()` now accepts SQLite-style
case-insensitive function spelling, and
`SQLiteJsonValidity::jsonValidSqlFunctionArguments()` validates one-or-two
argument SQL vectors for `json_valid(X[,FLAGS])`. It preserves strict JSON
text, JSON5 flag acceptance, cast text BLOB fallback, superficial and strict
JSONB flag behavior, SQL NULL input propagation, NULL `FLAGS` rejection, and
invalid function-name rejection.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON validity and JSONB evidence for
the same upstream behavior cluster:

```sh
json101.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonValidity.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-validity-preflight.php
php lanes/libsqlite/examples/application-json-validity-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root
aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5, JSONB, cast-text BLOB, and validity flag
components; it counts no shared support-library progress.

## Focused Native Mapping: `json_array_insert()`/`jsonb_array_insert()` SQL Dispatch

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite SQL result-type dispatch
boundary for `json_array_insert()` and `jsonb_array_insert()`. Native
`SQLiteJsonArrayInsert::arrayInsertSqlFunction()` reuses the existing
lane-local JSONB array-insert engine, returning canonical JSON text for
`json_array_insert()` and SQLite JSONB blob bytes for `jsonb_array_insert()`.
SQL scalar insertion values stay scalar, while `SQLiteJsonSubtypeValue` and
SQLite JSONB blobs embed as JSON fragments. The slice also rejects invalid
function names, odd path/value argument shapes, non-string later paths,
malformed input JSON/JSON5/JSONB, raw non-JSONB BLOB values, and paths that
do not identify an array element.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON array-insert and JSONB evidence
for the same upstream behavior cluster:

```sh
json109.test json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonArrayInsert.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-jsonb-array-insert-option-field.php
php lanes/libsqlite/examples/application-jsonb-array-insert-option-field.php '{"queue":["scan","rewrite"]}' json_array_insert '$.queue[1]' 'json:{"task":"cache"}'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported a SQLite text
JSON result for `json_array_insert()` with decoded output, focused PHP passed
1 selected test file, 1923 assertions, and 0 failures, and
`git diff --check -- lanes/libsqlite` passed. This worker did not start the
root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON5, JSONB, canonical JSON, JSON subtype, and BLOB
wrapper components; it counts no shared support-library progress.

## Focused Native Mapping: `json_array_insert()`/`jsonb_array_insert()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated finisher rebases the accepted array-insert SQL-dispatch behavior
on top of the current lane evidence and adds SQLite-style case-insensitive
function-name dispatch plus SQL argument-vector validation for
`json_array_insert()` and `jsonb_array_insert()`. Native
`SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments()` now accepts the
SQL call shape `[JSON, PATH, VALUE, ...]`, preserves text versus JSONB result
typing, preserves SQL NULL input propagation, and rejects invalid arity,
invalid path types, invalid function names, raw BLOB insert values, malformed
input, and non-array-element paths.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON array-insert and JSONB evidence
for the same upstream behavior cluster:

```sh
json109.test json102.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence is recorded in `lane-status.json`. This worker did not
start the root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local JSON path, JSON5, JSONB, canonical JSON, JSON subtype, and
BLOB wrapper components; it counts no shared support-library progress.

## Focused Native Mapping: `json_each()`/`json_tree()` Hidden Columns

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite JSON table-valued hidden-column boundary for `json_each(X[,P])` and `json_tree(X[,P])`. Native row arrays now include the hidden `json` column as the original text/JSONB argument and the hidden `root` column as the effective root path used for the scan, while preserving the accepted visible `key`, `value`, `type`, `atom`, `id`, `parent`, `fullkey`, and `path` columns.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1/JSONB table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported recursive root/plugin/rules rows with hidden `json`/`root` summaries, focused PHP passed 1 selected test file, 2116 assertions, and 0 failures, and final diff/json checks are recorded in `lane-status.json`. This worker did not start the root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and SQL value typing support; it counts no shared support-library progress.

## Focused Native Mapping: `json_each()` Case-Insensitive SQL Dispatch

Date: 2026-05-25

This isolated micro-slice closes a bounded table-valued JSON dispatch gap left by the accepted hidden-column work: `SQLiteJsonEach::jsonEachSqlFunction()` now accepts SQLite-style case-insensitive `json_each` function names, matching the already accepted `json_tree` dispatcher while preserving invalid-function rejection.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1 table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support; it counts no shared support-library progress.

## Focused Native Mapping: JSON Constructor Argument-Vector Dispatch

Date: 2026-05-25

This isolated refill adds bounded SQL-style argument-vector dispatch for
`json_array()`, `jsonb_array()`, `json_object()`, and `jsonb_object()`.
Function-name validation is now case-insensitive for these constructor helpers,
while text JSON versus JSONB result typing, JSON subtype passthrough, JSONB
BLOB passthrough, raw BLOB rejection, invalid function-name rejection, and odd
`json_object()` arity rejection remain preserved.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON constructor evidence over
`json101.test` and `subtype1.test`, previously passing 305 tests with 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonConstructor.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-constructor-option-diagnostics.php
php lanes/libsqlite/examples/application-json-constructor-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application constructor smoke reported
uppercase argument-vector dispatch for JSON and JSONB constructors, focused PHP
passed 1 selected test file, 2178 assertions, and 0 failures. Root aggregate
harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses
existing lane-local JSONB, JSON subtype, BLOB wrapper, constructor value
coercion, and SQL NULL handling; it counts no shared support-library progress.

## Focused Native Mapping: `json_error_position()` Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice extends the accepted `json_error_position(X)` SQL
dispatch boundary with one-argument SQL-style vector dispatch and
case-insensitive function-name matching. Native
`SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments()` validates
arity, delegates the same TEXT, JSON5, cast-text BLOB, JSONB, superficial-only
JSONB, and SQL NULL behavior as direct dispatch, and preserves invalid
function-name rejection.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON diagnostic evidence for the same
upstream behavior cluster:

```sh
json101.test json102.test json501.test json502.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonErrorPosition.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-error-position-preflight.php
php lanes/libsqlite/examples/application-json-error-position-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root
aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses
existing lane-local JSON5 parser, JSONB error-position checks, cast-text BLOB
fallback, and SQL NULL handling; it counts no shared support-library progress.

## Focused Native Mapping: Table-Valued JSON Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice rebases the table-valued JSON dispatch rework on top
of the accepted hidden-column and case-insensitive function-name evidence.
`SQLiteJsonEach::jsonEachSqlFunctionArguments()` and
`SQLiteJsonTree::jsonTreeSqlFunctionArguments()` now validate SQLite-style
one-or-two argument vectors, dispatch the optional root path, preserve SQL
NULL empty-row behavior, reject invalid arity, reject non-text JSON arguments,
and reject non-text path arguments. Direct dispatch behavior and invalid
function-name rejection are unchanged.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice reuses prior focused JSON1/JSONB table-valued evidence for
the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, both Application examples reported uppercase
argument-vector dispatch for root/plugin/rules rows, focused PHP passed 1
selected test file, 2134 assertions, and 0 failures, and final diff/json
checks are recorded in `lane-status.json`. This worker did not start the root
aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses
existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, SQL
value typing, and table-valued row support; it counts no shared
support-library progress.

## Focused Native Mapping: Table-Valued JSON Case-Insensitive Dispatch

Date: 2026-05-25

This isolated rework makes the accepted `json_each()` and `json_tree()` table-valued SQL function-name dispatch explicitly case-insensitive via `strcasecmp()`. It preserves invalid-function rejection and the accepted hidden `json`/`root` virtual-table columns while adding mixed-case native assertions for both helpers and an uppercase `JSON_TREE` Application smoke path.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1/JSONB table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support; it counts no shared support-library progress.
## Focused Native Mapping: WAL Header and Frame Reader

Date: 2026-05-26

This isolated micro-slice adds a bounded read-only SQLite WAL parser. It maps
the WAL file header fields, validates supported WAL magic values and page
sizes, iterates fixed-size frames, rejects frame salt mismatches and truncated
frames, identifies commit frames via non-zero database-size fields, and exposes
page images through the last commit frame for recovery/import diagnostics.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice is mapped against the existing static focused WAL inventory
(`focusedWalTestScripts`: 42) and records one native WAL header/frame mapping
unit. Prior applicable runner evidence remains the complete SQLite `veryquick`
run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteWalHeader.php
php -l lanes/libsqlite/src/SQLiteWalFrame.php
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root
aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses
lane-local binary parsing, table/page assembly, `SQLiteDatabase` traversal, and
Application option decoding; it counts no shared support-library progress.

## Focused Native Mapping: LIKE and GLOB Option-Name Matching

Date: 2026-05-26

This isolated encoding/collation micro-slice adds bounded SQLite-style pattern
matching for decoded `wp_options.option_name` text. Native helpers now expose
ASCII case-folded `LIKE` with `%`, `_`, optional one-character `ESCAPE`, UTF-8
character wildcards when the input is well-formed, byte fallback for malformed
text, and case-sensitive `GLOB` with `*`, `?`, bracket classes, ASCII ranges,
and `^` negated classes.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against the existing focused collation/function
inventory and adds one native LIKE/GLOB pattern-matching unit. Prior applicable
runner evidence remains the complete SQLite `veryquick` run: 1235 scripts,
329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-option-name-like-glob.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks and the Application LIKE/GLOB smoke passed. The focused
test file reaches the new LIKE/GLOB assertions successfully; later root
acceptance restored the WAL classes that were missing in that isolated worktree.
Root aggregate harness was not assigned for this isolated micro-slice.

## Focused Native Mapping: `json_tree()` Quoted Selected-Root Labels

This isolated micro-slice closes a bounded JSON table-valued row-shape gap for
`json_tree(X, root)`. The selected root row now derives `key` and `path` by
scanning SQLite JSON path segments while respecting quoted object labels, so
paths like `$.plugin."dotted.key"` and
`$.plugin."bracket[0]"."nested.label"` are not split on punctuation inside the
quoted label. The hidden `root` column continues to report the caller-supplied
root path.

No hydrated upstream cache was present in this isolated worktree, so no new
SQLite Tcl/testfixture run was performed. The slice reuses the accepted focused
JSON table inventory and adds native PHP assertions for quoted selected-root
rows on JSONB input.

Focused local verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, focused PHP passed 1 selected file with 2391
assertions and 0 failures, the Application smoke reported quoted-root rows for
JSONB option settings, JSON metadata validation passed, and final diff check is
recorded in `lane-status.json`. Root aggregate harness was not assigned for
this isolated micro-slice.

Dependency closure: no new support component is needed; this reuses lane-local
JSON path decoding, JSON5 quoted-label decoding, JSONB decode, and existing
`json_tree()` row assembly.

Dependency closure: no new support component is needed. The slice reuses
lane-local UTF-8 text splitting, ASCII case folding, decoded Application options,
and table traversal; it counts no shared support-library progress.

## Focused Native Mapping: WAL Header and Frame Checksums

Date: 2026-05-26

This isolated micro-slice extends the read-only SQLite WAL parser with explicit
checksum verification. Callers can request validation of the 32-byte WAL header
checksum and each frame checksum using the WAL byte order, seeded across header
and frame content in SQLite's rolling checksum shape. The parser rejects header
checksum mismatches and per-frame checksum mismatches while preserving the
accepted non-validating parse path for existing diagnostics.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice is mapped against the existing static focused WAL inventory
(`focusedWalTestScripts`: 42) and records one native WAL checksum mapping unit.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/src/SQLiteWalHeader.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: focused PHP passed 1 selected test file, 2310 assertions, and 0
failures. Root aggregate harness was not assigned for this isolated
micro-slice.

Dependency closure: no new support component is needed. The slice reuses
lane-local binary parsing, WAL frame assembly/inspection, table/page assembly,
`SQLiteDatabase` traversal, and Application option decoding; it counts no shared
support-library progress.

## Focused Native Mapping: WAL Checkpoint Database Image Overlay

Date: 2026-05-26

This isolated WAL/rollback/savepoint micro-slice extends the bounded read-only
WAL parser with a checkpoint-style database image overlay helper. Native
`SQLiteWal::checkpointDatabaseImage()` now applies page images through the last
commit frame onto an existing database image, truncates or extends the image to
the committed database page count, ignores uncommitted tail frames, and rejects
base images that are not aligned to the WAL/database page size.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against the existing focused WAL inventory
(`focusedWalTestScripts`: 42) and adds one native checkpoint-image mapping unit.
Prior applicable runner evidence remains the complete SQLite `veryquick` run:
1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: focused PHP passed 1 selected test file, 2314 assertions, and 0
failures after the bounded stale merge onto the accepted WAL checksum slice.

Dependency closure: no new support component is needed. The slice reuses
lane-local WAL header/frame parsing, SQLite header parsing, page-image
assembly, `SQLiteDatabase` traversal, and Application option decoding; it counts
no shared support-library progress.

## Focused Native Mapping: Rollback Journal Header and Page Records

Date: 2026-05-26

This isolated dependency-closure micro-slice adds a bounded read-only SQLite
rollback journal parser. Native `SQLiteRollbackJournalHeader` validates the
28-byte journal header, magic bytes, sector size, page size, checksum nonce,
initial database page count, and declared page count. Native
`SQLiteRollbackJournal` parses page records from the first sector, supports
the SQLite unknown-page-count sentinel by reading records through EOF,
validates SQLite-style page checksums on request, exposes saved page images,
and can preview rollback by applying those saved page images to an aligned
dirty database image.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against the existing static focused pager/journal
inventory (`focusedPagerTestScripts`: 8) and records one native
rollback-journal header/page-record mapping unit. Prior applicable runner
evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670
tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteRollbackJournalHeader.php
php -l lanes/libsqlite/src/SQLiteRollbackJournalPage.php
php -l lanes/libsqlite/src/SQLiteRollbackJournal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; the Application rollback-journal smoke reported a
checksums-validated journal, recovered the clean `siteurl` option from the
journal page image, and produced a 1024-byte rolled-back database preview;
focused lane tests passed with 1 selected file, 2340 assertions, and 0
failures.

Dependency closure: no new support component is needed. The slice reuses
lane-local binary parsing, page-image assembly, `SQLiteDatabase` traversal,
and Application option decoding; it counts no shared support-library progress.

## Focused Native Mapping: Savepoint State Diagnostics

Date: 2026-05-26

This isolated dependency-closure micro-slice adds bounded SQLite savepoint
state tracking for recovery/import diagnostics. Native `SQLiteSavepointStack`
tracks an active transaction plus nested `SAVEPOINT` frames, records dirty
page numbers per frame, implements SQLite-style `ROLLBACK TO` by discarding
younger frames while keeping the named savepoint active, and implements
`RELEASE` by merging child dirty-page state upward or ending the outer
transaction.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against the existing static pager/journal and
transaction-state inventories and records one native savepoint-state mapping
unit. Prior applicable runner evidence remains the complete SQLite `veryquick`
run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; the Application savepoint smoke reported nested
`wp_options` import frames before rollback, preserved the named savepoint after
`ROLLBACK TO`, merged surviving page writes on `RELEASE`, and kept the outer
transaction active with pending pages `[1, 2, 6]`; focused lane tests passed
with 1 selected file, 2380 assertions, and 0 failures.

Dependency closure: no new support component is needed. The slice reuses
lane-local transaction/page-number bookkeeping for storage diagnostics and
counts no shared support-library progress.

## Focused Native Mapping: Commented CREATE TABLE Autoindex Inference

Date: 2026-05-26

This isolated dependency-closure micro-slice aligns bounded `sqlite_schema`
`CREATE TABLE` parsing with SQLite dump/schema SQL that contains line and block
comments. `SQLiteCreateTable` now ignores `-- ...` and `/* ... */` comments
outside quoted strings, identifiers, and bracket-quoted names before inferring
automatic `UNIQUE`/`PRIMARY KEY` index metadata. That keeps copied Application
schema rows from treating comment text such as `UNIQUE`, `PRIMARY KEY`, or
`WITHOUT ROWID` as live schema tokens.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated
`.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was
started. This slice maps against SQLite schema/index parser coverage and
records one native schema-comment autoindex mapping unit. Prior applicable
runner evidence remains the complete SQLite `veryquick` run: 1235 scripts,
329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteCreateTable.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-commented-schema-autoindex.php
php lanes/libsqlite/examples/application-commented-schema-autoindex.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: focused lane tests passed with 1 selected file, 2418 assertions, and 0
failures. The Application smoke reports automatic index metadata for commented
`wp_options` schema SQL without requiring the SQLite extension.

Dependency closure: no new support component is needed. The slice reuses the
lane-local `CREATE TABLE` parser and schema/index metadata helpers; it does not
activate shared parser-generator, SQL engine, or SQLite-extension support.
## Focused Native Mapping: `json_each()`/`json_tree()` Hidden Constraint Planning

This isolated json-table/window micro-slice maps a bounded SQLite JSON table-valued planner boundary for the hidden `json` and `root` columns. `SQLiteJsonTablePlan` now accepts usable equality constraints on hidden `json`/`root`, turns them into `json_each`/`json_tree` argument vectors, marks those constraints as omitted, preserves non-hidden or unusable predicates as residual constraints, and can execute the planned rows through the accepted native table-valued helpers.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count increases by 1 with `focusedJsonTableHiddenConstraintPlanScripts: 1`. This is a native planner-constraint helper only; it does not claim full virtual-table cursor lifecycle, join-order integration, visible-column pushdown, or broader SQL planner execution.

Verification run 2026-05-26T03:33Z:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local JSON table helpers, JSON path validation, and JSONB wrappers without shelling out or activating shared support-library work.

## Focused Native Mapping: Interior Right-Most Pointer Rebalance Diagnostics

This isolated btree-delete/rebalance micro-slice maps a bounded SQLite B-tree
delete/rebalance diagnostic boundary: when a replacement deletes an old
composite-index entry, underfills a leaf, merges that leaf with a sibling,
then merges adjacent non-root index parents under a multi-child root, the
surviving interior parent may keep the same page number while its right-most
pointer changes. `SQLiteDatabase::btreeRebalanceActionsForPageImages()` now
reports that pointer repair explicitly as
`index-interior-rightmost-pointer-update`.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped
count increases by 1 with `focusedApplicationInteriorRightmostPointerRebalanceScripts: 1`.
This reuses accepted upstream delete/rebalance evidence over `update.test`,
`index.test`, `btree01.test`, `delete2.test`, `delete3.test`, and
`delete4.test`; this isolated worktree did not contain the hydrated upstream
cache, so no fresh upstream `testfixture` run was started.

Verification run 2026-05-26T03:38Z:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-index-parent-merge-option-replacement-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-index-parent-merge-option-replacement-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
B-tree page headers, index replacement planning, page image overlays, freelist
mutation, and Application fixtures without shelling out or activating shared
support-library work.

## Focused Native Mapping: Interior Left-Child Pointer Rebalance Diagnostics

This isolated btree-delete/rebalance micro-slice extends the accepted
delete-triggered composite-index parent-merge diagnostic. Interior divider
insert/removal actions now include `before_left_children` and
`after_left_children`, so the surviving root/lower parent child slots can be
audited together with the accepted right-most pointer update.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped
count increases by 1 with `focusedApplicationInteriorLeftChildPointerRebalanceScripts: 1`.
This reuses accepted upstream delete/rebalance evidence over `update.test`,
`index.test`, `btree01.test`, `delete2.test`, `delete3.test`, and
`delete4.test`; this isolated worktree did not contain the hydrated upstream
cache, so no fresh upstream `testfixture` run was started.

Verification run 2026-05-26T04:04Z:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-index-parent-merge-option-replacement-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-index-parent-merge-option-replacement-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
B-tree page headers, index cell parsing, replacement planning, page image
overlays, freelist mutation, and Application fixtures without shelling out or
activating shared support-library work.

## Focused Native Mapping: Partial IN-List Subset Planner Implication

This isolated planner micro-slice maps a bounded SQLite partial-index predicate
case: a query constrained by `option_name IN (...)` may use a partial index
declared as `WHERE option_name IN (...)` when every requested non-null lookup
name is covered by the partial predicate. `SQLiteIndexPredicate` now evaluates
that implication as a set/subset check instead of requiring identical list
order and cardinality.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedPartialInListSubsetIndexScripts: 1`. This reuses
accepted upstream planner/index coverage (`index.test`, `where*.test`, and
partial-index evidence already inventoried for this lane). This isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture` run was started.

Verification run 2026-05-26T04:15Z:

```sh
php -l lanes/libsqlite/src/SQLiteIndexPredicate.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-by-name-list.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
schema parsing, partial-index predicate metadata, index b-tree traversal, and
scalar comparison semantics without activating shared support-library work.

## Focused Native Mapping: JSON Table Residual LIKE/GLOB Filters

This isolated json-table/window micro-slice maps a bounded SQLite JSON
table-valued residual predicate case: after hidden `json` and `root`
constraints make a `json_each()` or `json_tree()` scan runnable, visible text
columns can be filtered with SQL `LIKE` and `GLOB` predicates. The native helper
reuses the accepted SQLite pattern matchers, treats SQL NULL pattern comparisons
as non-matches, and rejects non-text operands instead of silently coercing JSON
table ids or parent ids.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedJsonTableResidualPatternScripts: 1`. This reuses
accepted static JSON table/window evidence over `json101.test`, `json102.test`,
`json501.test`, `json107.test`, and `jsonb01.test`; this isolated worktree did
not contain the hydrated upstream cache, so no fresh upstream `testfixture` run
was started.

Verification run 2026-05-26T04:41Z:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON table row assembly, hidden-column planning, JSON path validation, and the
existing SQLite LIKE/GLOB matchers without activating shared support-library
work.

## Focused Native Mapping: Index Leaf Delete Freeblock Reuse

This isolated planner/WAL/B-tree closure micro-slice maps a bounded SQLite
B-tree delete behavior for secondary indexes: removing one index-leaf record
updates the cell pointer array, releases the deleted cell bytes into the
page-local freeblock chain, coalesces adjacent freeblocks, and can clear the
released payload bytes for secure-delete diagnostics.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationIndexLeafDeleteFreeblockScripts: 1`. This
reuses accepted static B-tree/delete evidence over `delete*.test`,
`btree01.test`, `index.test`, and corrupt freeblock coverage; this isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture` run was started.

Verification run 2026-05-26T05:08Z:

```sh
php -l lanes/libsqlite/src/SQLiteIndexLeafPage.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-delete-option-index-leaf-freeblock.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-delete-option-index-leaf-freeblock.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
index cell parsing, record decoding, B-tree page headers, freeblock parsing,
and Application fixture helpers without activating shared support-library work.

## Focused Native Mapping: JSON Table Residual IN Lists

This bounded sql-exec/planner integration maps another SQLite JSON table
residual predicate case: after hidden `json` and `root` constraints make a
`json_each()` or `json_tree()` scan runnable, visible columns can be filtered
with SQL `IN` and `NOT IN` predicates. The native helper validates that the RHS
is a list, uses strict scalar membership for JSON table row values, treats SQL
NULL as a non-match for `IN`, and suppresses `NOT IN` when the RHS list
contains NULL.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedJsonTableResidualInListScripts: 1`. This reuses
accepted static JSON table/window evidence over `json101.test`, `json102.test`,
`json501.test`, `json107.test`, and `jsonb01.test`; this clean integration
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture` run was started.

Verification run 2026-05-26T05:30Z:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON table row assembly, hidden-column planning, JSONB wrappers, and scalar
residual comparison semantics without activating shared support-library work.

## Focused Native Mapping: Savepoint Rollback Page Preview

This isolated WAL/rollback/savepoint closure micro-slice extends the accepted
savepoint state tracker with a bounded `ROLLBACK TO` preview. Native
`SQLiteSavepointStack::rollbackToPageNumbers()` now reports the sorted unique
database page numbers that would be reverted by rolling back to the named
savepoint: dirty pages in the named savepoint plus all younger savepoints. The
existing `rollbackTo()` behavior remains unchanged: younger frames are
discarded and the named savepoint stays active with cleared dirty-page state.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedSavepointRollbackPreviewScripts: 1`. This reuses
the accepted static pager/journal and transaction-state inventories; this
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T05:42Z:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
transaction frame and page-number bookkeeping without activating shared
support-library work.

## Focused Native Mapping: Table Leaf Delete Freeblock Reuse

This isolated B-tree delete/rebalance micro-slice maps a bounded SQLite table
leaf delete behavior: removing one rowid entry updates the cell pointer array,
releases the deleted table cell bytes into the page-local freeblock chain,
coalesces adjacent freeblocks, and can clear the released payload bytes for
secure-delete diagnostics.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationTableLeafDeleteFreeblockScripts: 1`. This
reuses accepted static B-tree/delete evidence over `delete*.test`,
`btree01.test`, pager freeblock behavior, and corrupt freeblock coverage; this
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T05:55Z:

```sh
php -l lanes/libsqlite/src/SQLiteTableLeafPage.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-delete-option-table-leaf-freeblock.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-delete-option-table-leaf-freeblock.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
table leaf cell parsing, B-tree page headers, freeblock parsing, and Application
fixture helpers without activating shared support-library work.

## Focused Native Mapping: `json_group_array(DISTINCT X ORDER BY Y)`

This isolated SQL execution/planner micro-slice adds the combined aggregate
boundary for SQLite's `json_group_array(DISTINCT X ORDER BY Y)` behavior.
Native `SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy()` sorts rows by
the aggregate `ORDER BY` key with stable input-position ties, then applies
SQLite-style DISTINCT de-duplication to the aggregate argument before final
JSON text or JSONB dispatch. `SQLiteJsonAggregateState` now records the same
combined step/final path for aggregate executor-style scheduling.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedJsonAggregateDistinctOrderByScripts: 1`. This
reuses accepted static JSON aggregate evidence over the SQLite JSON aggregate
tests; this isolated worktree did not contain the hydrated upstream cache, so
no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T06:12Z:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonAggregateState.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-aggregate-option-summary.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON aggregate coercion, JSON subtype handling, JSONB encode/decode, and
ordered row scheduling helpers without activating shared support-library work.

## Focused Native Mapping: WAL Reader Page Map

This isolated WAL/rollback/savepoint micro-slice adds bounded read-side WAL
resolution diagnostics. `SQLiteWal::readerPageImage()` resolves one database
page as a SQLite reader would see it through the last committed WAL frame,
returning base-database versus WAL source provenance and the committed frame
index. `SQLiteWal::readerPageMap()` summarizes all committed pages while
excluding uncommitted tail frames.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedWalReaderPageMapScripts: 1`. This reuses accepted
static WAL/pager evidence over `wal*.test`; this isolated worktree did not
contain the hydrated upstream cache, so no fresh upstream `testfixture`, `make
test`, or `mptest` run was started.

Verification run 2026-05-26T06:22Z:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
WAL header/frame parsing, committed-frame tracking, SQLite header page-size
parsing, and Application page fixtures without activating shared support-library
work.

## Focused Native Mapping: `ORDER BY ... LIMIT/OFFSET` Result Rows

This isolated SQL execution/planner micro-slice adds a bounded decoded-row
result ordering path for `wp_options`: native `SQLiteDatabase` can sort decoded
option rows by `option_id`, `option_name`, `option_value`, `autoload`, or
`rowid`, then apply SQLite-style result `OFFSET` and `LIMIT` after ordering.
NULL sort keys compare before non-NULL values in ascending order, and rowid is
used as a stable tie-breaker for deterministic local result plans.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedOptionRowOrderLimitScripts: 1`. This reuses
accepted decoded table-scan and Application fixture evidence; this isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T06:35Z:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-order-limit.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-order-limit.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
table traversal, record decoding, Application option mapping, and PHP scalar
comparison helpers without activating shared support-library work.

## Focused Native Mapping: RTRIM Collation Option-Name Lookup

This isolated encoding/collation micro-slice makes SQLite's built-in `RTRIM`
collation boundary explicit for Application option recovery. `SQLiteDatabase`
now routes the `RTRIM` text comparison through a named helper and focused
coverage proves that an automatic `UNIQUE(option_name COLLATE RTRIM)` index can
recover a stored `option_name` with trailing U+0020 spaces from an unpadded
lookup key. The same slice checks exclusive versus inclusive range boundaries
where the lower and upper keys compare equal after SQLite RTRIM collation
normalization.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedApplicationRtrimCollationScripts: 1`. This reuses
accepted static encoding/collation evidence over SQLite `collate*.test` and
`index*.test`; this isolated worktree did not contain the hydrated upstream
cache, so no fresh upstream `testfixture`, `make test`, or `mptest` run was
started.

Verification run 2026-05-26T06:48Z:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-rtrim-collation-option-lookup.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-rtrim-collation-option-lookup.php --self-test cache_token --inclusive
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
automatic index metadata parsing, SQLite scalar comparison, record decoding,
and Application option traversal without activating shared support-library work.

## Focused Native Mapping: Bulk B-tree Leaf Deletes

This isolated B-tree delete/rebalance micro-slice adds bounded bulk deletion
helpers for table and index leaf pages. Native PHP can now remove multiple
adjacent `wp_options` table rowids or secondary-index records from one leaf,
update the pointer array, coalesce the freed cell bodies into a reusable
freeblock, and, when secure-delete is requested, clear stale interior
freeblock headers inside the coalesced payload before writing the surviving
freeblock header.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped
count is preserved at the current accepted 312 while adding
`focusedApplicationBulkLeafDeleteFreeblockScripts: 1`. This reuses accepted
static B-tree delete/rebalance evidence over `delete.test`, `delete2.test`,
`delete3.test`, `delete4.test`, and `btree01.test`; this isolated worktree did
not contain the hydrated upstream cache, so no fresh upstream `testfixture`,
`make test`, or `mptest` run was started.

Verification run 2026-05-26T07:17Z:

```sh
php -l lanes/libsqlite/src/SQLiteTableLeafPage.php
php -l lanes/libsqlite/src/SQLiteIndexLeafPage.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-delete-option-table-leaf-freeblock.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-delete-option-table-leaf-freeblock.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
B-tree page headers, table/index leaf cell parsing, record decoding, freeblock
accounting, and Application fixture helpers without activating shared
support-library work.

## Focused Native Mapping: Rollback Journal Recovery Plan

This isolated WAL/rollback/savepoint micro-slice adds bounded rollback journal
recovery diagnostics. Native PHP can now produce a recovery plan for a dirty
database image, restore pages captured by a rollback journal, skip journal
pages beyond the original database size, and truncate the rolled-back image to
the original page count.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional Application rollback recovery diagnostic script while preserving the
current accepted static SQLite upstream denominator. This reuses accepted
rollback/pager evidence over SQLite pager and journal tests; this isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T07:26Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteRollbackJournal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-rollback-journal-option-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
rollback journal parsing, checksum validation, SQLite page headers, and
Application fixture helpers without activating shared support-library work.

## Focused Native Mapping: Full-Suite Command Manifest

This isolated upstream-suite micro-slice adds a machine-readable command
manifest for the remaining SQLite full-suite gates. The manifest composes the
accepted veryquick baseline with release/all, declared permutation-suite,
`make test`, `mptest`, wildcard-expansion, and permutation-map gates, preserving
the exact runnable/blocked status, missing prerequisites, evidence source, and
next hydration/build gate for each command without claiming fresh upstream
execution.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional upstream-suite evidence script while preserving the current accepted
static SQLite upstream denominator. This isolated worktree did not contain the
hydrated upstream cache, configured build directory, `testfixture`, `Makefile`,
`mptest` directory, or `permutations.test`, so no fresh upstream `testfixture`,
`make test`, or `mptest` run was started.

Verification run 2026-05-26T07:36Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
manifest runner evidence, release-tier planning, wildcard expansion,
permutation-suite mapping, and SQLite testfixture/make command planning without
activating shared support-library work.

## Focused Native Mapping: WAL Checkpoint Plan

This isolated WAL micro-slice adds bounded checkpoint planning diagnostics. Native PHP can now report the final committed database byte count, the last commit frame, and per-frame checkpoint provenance for committed frames, frames superseded by later committed frames, frames beyond the committed database size, and uncommitted tail frames.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one additional focused WAL checkpoint-plan script while preserving the current accepted static SQLite upstream denominator and full-suite command-manifest evidence. This isolated worktree did not contain the hydrated upstream cache, so no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T07:42Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local WAL parsing, checksum validation, checkpoint overlay, SQLite header parsing, and Application fixture helpers without activating shared support-library work.

## Focused Native Mapping: Core Scalar Native UTF-8 Text Units

This isolated dependency-suite micro-slice closes the remaining mbstring edge
for bounded core scalar text dispatch. `length()`, `substr()`/`substring()`,
and `instr()` now use lane-local UTF-8 codepoint splitting for valid UTF-8
TEXT even when mbstring is absent, while BLOB inputs keep byte semantics and
malformed byte strings fall back to byte units.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional dependency-closure scalar evidence row while preserving the current
accepted static SQLite upstream denominator. This isolated worktree did not
contain the hydrated upstream cache, so no fresh upstream `testfixture`, `make
test`, or `mptest` run was started.

Verification run 2026-05-26T13:10Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php --self-test
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This removes the hard
mbstring dependency for UTF-8 character counting, slicing, and search positions
inside the bounded scalar helper by reusing PHP PCRE UTF-8 validation/splitting
and existing byte fallback behavior.

## Focused Native Mapping: Core Scalar Min/Max And Text Helpers

This isolated dependency-suite micro-slice closes a bounded SQL expression
semantics dependency inside the libsqlite lane. Native PHP core scalar dispatch
now supports scalar `min()`/`max()` with SQLite storage-class ordering, SQL
NULL propagation, BLOB comparisons, ASCII-only `lower()`/`upper()` case
mapping, and `length()` over text characters or BLOB bytes.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core scalar function script while preserving the current
accepted static SQLite upstream denominator. This isolated worktree did not
contain the hydrated upstream cache, so no fresh upstream `testfixture`, `make
test`, or `mptest` run was started.

Verification run 2026-05-26T08:05Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php --self-test
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
SQL scalar coercion, SQLite storage-class comparison, `SQLiteBlobValue`, and
existing expression-semantics helpers without activating shared
support-library work.

## Focused Native Mapping: Core Scalar Substring Dispatch

This isolated SQL execution/planner micro-slice closes a bounded scalar
function gap needed by expression dispatch and Application option repair previews.
Native PHP core scalar dispatch now supports `substr()` and `substring()` with
SQLite-style SQL NULL propagation, 1-based starts, the special start-zero
length rule, negative starts, negative lengths, UTF-8 text slicing when the
runtime exposes mbstring, and BLOB byte slicing.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` records one
additional focused core substring scalar evidence row while preserving the
current accepted static SQLite upstream denominator. This isolated worktree did
not contain the hydrated upstream cache, so no fresh upstream `testfixture`,
`make test`, or `mptest` run was started.

Verification run 2026-05-26T09:25Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php substr _plugin_cache 2 6
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
scalar coercion, UTF-8 helpers when available, `SQLiteBlobValue`, and existing
expression-semantics dispatch without activating shared support-library work.

## Focused Native Mapping: Core Sign/Zeroblob Scalar Functions

This isolated SQL execution/planner scalar micro-slice adds `sign()` and
`zeroblob()` to `SQLiteCoreScalarFunction::sqlFunctionArguments()`. Native PHP
now returns `-1`, `0`, or `1` for losslessly numeric `sign()` inputs, propagates
`NULL`, returns `NULL` for non-lossless numeric text and BLOB inputs, and
creates `SQLiteBlobValue` zero-filled BLOB placeholders for `zeroblob()` with
negative sizes clamped to an empty BLOB.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core sign/zeroblob scalar evidence row while preserving the
current accepted static SQLite upstream denominator. This isolated worktree did
not contain the hydrated upstream cache, so no fresh upstream `testfixture`,
`make test`, or `mptest` run was started.

Verification run 2026-05-26T13:02Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php sign -3
php lanes/libsqlite/examples/application-core-scalar-option-default.php zeroblob 4
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
scalar coercion and `SQLiteBlobValue` without activating shared
expression-semantics, binary-buffer, or SQLite-extension support.

## Focused Native Mapping: Core Encoding Scalar Functions

This isolated SQL execution/planner scalar micro-slice adds the bounded
core encoding and codepoint scalar dispatch cluster. Native PHP now supports
`hex()`, `unhex()`, `char()`, `unicode()`, and `octet_length()` through
`SQLiteCoreScalarFunction::sqlFunctionArguments()`, including SQL NULL
propagation, BLOB byte hex/unhex round trips, ignored-character filtering for
`unhex(X,Y)`, malformed `unhex()` NULL results, UTF-8 codepoint construction
and inspection, replacement-character handling for invalid `char()` codepoints,
and byte-length diagnostics distinct from character `length()`.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core encoding scalar evidence row while preserving the
current accepted static SQLite upstream denominator. This isolated worktree
did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T11:38Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php hex wp_options
php lanes/libsqlite/examples/application-core-scalar-option-default.php unhex 77705f6f7074696f6e73
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
scalar coercion, UTF-8 helpers when available, `SQLiteBlobValue`, and existing
expression-semantics dispatch without activating shared support-library work.

## Focused Native Mapping: JSON Table Residual BETWEEN Predicates

This isolated json-table/window micro-slice extends the bounded JSON
table-valued planner residual filter to accept SQL-level `BETWEEN` and
`NOT BETWEEN` predicates on visible `json_each`/`json_tree` columns. Native
PHP now applies inclusive two-bound scalar ordering after hidden `json`/`root`
constraint planning, returns no match when the actual value or either bound is
SQL NULL, and validates that `BETWEEN` receives exactly two bounds.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table residual BETWEEN evidence row while preserving
the current accepted static SQLite upstream denominator. This isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T10:18Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON row production, scalar residual ordering,
and Application fixture diagnostics without activating shared SQL expression
support.

## Focused Native Mapping: Bulk Secondary-Index Leaf Deletes

This isolated B-tree delete/rebalance micro-slice tightens the accepted
secondary-index deletion boundary. The native index leaf bulk-delete path is
now covered for multiple wp_options option_name records on the same page:
adjacent deleted cells coalesce into one reusable freeblock, non-adjacent
deleted cells remain a sorted freeblock chain, surviving index records stay
readable in key order, and secure-delete clearing removes stale payload bytes
before the freeblock headers are written.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional lane-local focused evidence row,
`focusedApplicationBulkIndexLeafDeleteFreeblockScripts: 1`. This reuses accepted
static B-tree delete/rebalance evidence over `delete.test`, `delete2.test`,
`delete3.test`, `delete4.test`, `btree01.test`, and `index.test`; this
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T10:09Z in the isolated worker:

```sh
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-delete-option-index-leaf-freeblock.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-delete-option-index-leaf-freeblock.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
B-tree page headers, index leaf cell parsing, record decoding, freeblock
parsing/mutation, secure-delete clearing, and Application fixture helpers without
activating shared support-library work.

## Focused Native Mapping: WAL Checkpoint Mode Plan

This isolated WAL/rollback/savepoint micro-slice extends read-only checkpoint
diagnostics with SQLite checkpoint-mode planning. Native PHP can now summarize
`PASSIVE`, `FULL`, `RESTART`, and `TRUNCATE` behavior over parsed WAL frames,
including active-reader frame limits, busy reporting for blocking modes,
uncommitted-tail preservation, and reset/truncate eligibility without writing
database or WAL files.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused WAL checkpoint-mode planning script while preserving the
current accepted static SQLite upstream denominator and full-suite runner
evidence. This isolated worktree did not contain the hydrated upstream cache,
so no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T09:47Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
WAL parsing, checkpoint/reset planning, SQLite header parsing, and Application
fixture helpers without activating shared pager, WAL-index, lock-manager, or
filesystem durability support.

## Focused Native Mapping: WAL Reset Plan

This isolated WAL/rollback/savepoint micro-slice adds bounded WAL reset and
truncation eligibility diagnostics. Native PHP can now report whether a parsed
WAL may be truncated or restarted after checkpoint, when uncommitted tail frames
or the absence of a committed transaction require preserving the WAL, how many
frames were actually checkpointed, and the next header salt value a restart
would use.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused WAL reset-plan script while preserving the current accepted
static SQLite upstream denominator and full-suite command-manifest evidence.
This isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T08:22Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
WAL parsing, checkpoint planning, SQLite header parsing, and Application fixture
helpers without activating shared support-library work.

## Focused Native Mapping: Savepoint Rollback/Release Plans

This isolated WAL/rollback/savepoint micro-slice adds read-only nested
savepoint planning diagnostics. Native PHP can now report the most-recent
matching savepoint frame, frames discarded by `ROLLBACK TO`, page numbers that
would be restored, frames released by `RELEASE`, dirty pages merged into the
parent frame, and whether releasing the target closes the outer transaction.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused savepoint rollback/release planning script while preserving
the current accepted static SQLite upstream denominator and full-suite runner
evidence. This isolated worktree did not contain the hydrated upstream cache,
so no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T09:05Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
savepoint state tracking and Application fixture diagnostics without activating
shared transaction, pager, or filesystem durability support.

## Focused Native Mapping: Core Trim/Replace/Instr Scalar Functions

This isolated SQL execution/planner scalar micro-slice adds another bounded
core text-scalar dispatch cluster. Native PHP now supports `trim()`,
`ltrim()`, `rtrim()`, `replace()`, and `instr()` through
`SQLiteCoreScalarFunction::sqlFunctionArguments()`, including SQL NULL
propagation, default-space and explicit-character trim sets, UTF-8 text units,
empty replace-pattern no-op behavior, text `instr()` 1-based positions, BLOB
byte `instr()` positions, and not-found `instr()` zero results.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core trim/replace/instr scalar evidence row while preserving
the current accepted static SQLite upstream denominator. This isolated worktree
did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T09:29Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php replace plugin-cache - _
php lanes/libsqlite/examples/application-core-scalar-option-default.php instr plugin_cache cache
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
scalar coercion, UTF-8 helpers when available, `SQLiteBlobValue`, and existing
expression-semantics dispatch without activating shared support-library work.

## Focused Native Mapping: Core Concat Scalar Functions

This isolated dependency-suite scalar micro-slice adds another bounded core
text-scalar dispatch cluster. Native PHP now supports `concat()` and
`concat_ws()` through `SQLiteCoreScalarFunction::sqlFunctionArguments()`,
including concat NULL-as-empty behavior, all-NULL empty-string output,
concat_ws NULL separator propagation, skipped NULL fields, BLOB byte text
coercion, and strict arity/type errors.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core concat scalar evidence row while preserving the current
accepted static SQLite upstream denominator and full-suite runner evidence.
This isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T13:13Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php concat plugin - cache null :v 2
php lanes/libsqlite/examples/application-core-scalar-option-default.php concat_ws / plugin null cache v2
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
scalar coercion, `SQLiteBlobValue`, and existing expression-semantics dispatch
without activating shared support-library work.

## Focused Native Mapping: B-tree Freeblock Integrity Report

This isolated planner/WAL/B-tree closure micro-slice adds a bounded native
B-tree repair diagnostic. `SQLiteBTreePageHeader::freeblockIntegrityReport()`
now returns a machine-readable `ok`/`corrupt` report for page-local freeblock
chains, freeblock byte totals, free-space accounting, and parser error text
without changing the existing throwing `freeblocks()` or `freeSpaceBytes()`
APIs.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused B-tree freeblock integrity evidence row while preserving the
current accepted static SQLite upstream denominator and runner evidence. This
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T13:20Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteBTreePageHeader.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-page-freeblocks.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-page-freeblocks.php /tmp/libsqlite-freeblock-integrity.sqlite 2
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local B-tree page header parsing, freeblock chain validation, and
Application page diagnostics without activating shared storage support work.

## Focused Native Mapping: JSON Table Residual NOT LIKE/NOT GLOB

This isolated json-table/window micro-slice extends the bounded JSON
table-valued planner residual-filter surface for visible `json_each()` and
`json_tree()` columns. After hidden `json` and `root` equality constraints make
the table-valued scan runnable, native `SQLiteJsonTablePlan::filteredRows()`
can now apply residual `NOT LIKE` and `NOT GLOB` predicates using the accepted
SQLite LIKE/GLOB matchers and the existing text/NULL validation boundary.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table residual-pattern evidence row while preserving the
current accepted static SQLite upstream denominator and runner evidence. This
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T13:27Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/table-valued helpers, and accepted
LIKE/GLOB scalar matching without activating shared support-library work.

## Focused Native Mapping: Core printf()/format() Scalar Dispatch

This isolated SQL execution/planner scalar micro-slice adds bounded native
`printf()`/`format()` dispatch to `SQLiteCoreScalarFunction` for Application
option diagnostics and future expression planning. It covers SQL NULL format
propagation, `printf`/`format` aliasing, `%s`, `%z`, `%d`, `%i`, `%u`, `%x`,
`%X`, `%o`, `%f`/`%g`-family numeric formatting through PHP's formatter,
SQLite-specific `%q`, `%Q`, and `%w` escaping, `%c` UTF-8 character output,
missing argument defaults, and literal `%%` output.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core format scalar evidence row while preserving the current
accepted static SQLite upstream denominator and runner evidence. This isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T13:48Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php format 'option=%Q autoload=%s rowid=%04d' plugin_cache yes 7
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion, `SQLiteBlobValue`, and existing
expression-semantics dispatch without activating shared support-library work.

## Focused Native Mapping: Savepoint RELEASE With Plan

This isolated planner/WAL/B-tree closure micro-slice extends the bounded
WAL/rollback/savepoint diagnostics with `SQLiteSavepointStack::releaseWithPlan()`.
The helper returns the same RELEASE provenance as `releasePlan()` and then
applies the savepoint transition, so callers can capture merged dirty-page
numbers, released frame names, result depth, and transaction-active state
without duplicating a plan/apply sequence.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused savepoint release-with-plan evidence row while preserving
the current accepted static SQLite upstream denominator and runner evidence.
This isolated worktree did not contain the hydrated upstream cache, so no
fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T14:02Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local savepoint state tracking and existing Application recovery diagnostics
without activating shared storage support work.

## Focused Native Mapping: Savepoint ROLLBACK TO Apply and COMMIT Plans

This isolated WAL/rollback/savepoint closure micro-slice extends bounded
savepoint diagnostics with `SQLiteSavepointStack::rollbackToWithPlan()`,
`commitPlan()`, and `commitWithPlan()`. The new helpers let callers capture
the exact ROLLBACK TO or COMMIT provenance and apply the transition without
duplicating state logic. Focused coverage includes duplicate savepoint names
resolving to the newest frame, retained outer dirty pages after ROLLBACK TO,
commit page aggregation, savepoint release count, and transaction clearing.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused savepoint rollback/commit plan evidence row while
preserving the current accepted static SQLite upstream denominator and runner
evidence. This isolated worktree did not contain the hydrated upstream cache,
so no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T17:34Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-savepoint-option-import-diagnostics.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local savepoint state tracking and existing Application recovery diagnostics
without activating shared storage support work.

## Upstream Runner Evidence: Foreground Release Snapshot Gate

This isolated upstream-suite runner micro-slice strengthens
`SQLiteUpstreamSuiteEvidence::activeFullSuiteRunnerGate()` for the currently
active guarded foreground release rerun. The active process sample uses
`ps -eo pid,ppid,stat,etime,pcpu,cmd`, so the parser now preserves `ppid`,
`stat`, and `pcpu` while still recognizing the bounded runner wrapper,
foreground `timeout`, and `testfixture ... --stop-on-error release` rows as a
single duplicate-runner blocker. The valgrind child remains visible in the
raw process evidence but is not counted as a separate broad suite tier because
it lacks an all/release/mptest launch command.

No broad upstream `all`, `release`, `make test`, or `mptest` run was launched
from this isolated worker. The active shared guarded runner observed for this
slice was `libsqlite-release-rerun-foreground-20260526T134619Z`; release/all
parity remains uncounted until that runner finishes and its bounded audit/log
artifacts pass the existing provenance/countability gates.

Verification run 2026-05-26T14:20Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local upstream runner evidence model and parses supplied process snapshots
only; it does not inspect secrets, mutate upstream caches, or execute upstream
tests.

## Focused Native Mapping: Core random()/randomblob() Scalar Dispatch

This isolated SQL execution/planner micro-slice adds `random()` and
`randomblob()` dispatch to `SQLiteCoreScalarFunction` for bounded
nondeterministic scalar expression support. Native PHP now returns signed
64-bit `random()` integers while excluding SQLite's minimum sentinel value, and
returns `SQLiteBlobValue` output for `randomblob(N)` with SQLite's minimum
one-byte behavior for non-positive lengths.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core random scalar evidence row while preserving the current
accepted static SQLite upstream denominator and runner evidence. This isolated
worktree did not contain the hydrated upstream cache, so no fresh upstream
`testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T14:32Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php randomblob 12
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion, `SQLiteBlobValue`, PHP CSPRNG primitives, and
existing expression-semantics dispatch without activating shared
support-library work.

## Focused Native Mapping: JSON Table REGEXP Residual Predicates

This isolated json-table/window micro-slice extends the bounded JSON
table-valued planner residual filter to accept SQL-level `REGEXP` and
`NOT REGEXP` predicates on visible `json_each`/`json_tree` columns. Native
`SQLiteJsonTablePlan::filteredRows()` now accepts an explicit residual payload
containing a text `pattern` and callable `regexp`, applies SQLite's existing
lane-local REGEXP callback validation, preserves SQL NULL false results, and
keeps hidden `json`/`root` constraints as planned table-valued arguments.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table REGEXP residual evidence row while preserving the
current accepted static SQLite upstream denominator and veryquick evidence.
This isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T14:39Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/JSONB decoding, `SQLiteDatabase`
REGEXP callback validation, and the existing Application JSON option smoke
without activating shared support-library work.

## Focused Native Mapping: JSON Table Numeric Equality Residual Predicates

This isolated json-table/window micro-slice tightens SQLite equality semantics
for visible-column residual filters after hidden `json` and `root` constraints
make a `json_each()` or `json_tree()` scan runnable. Native
`SQLiteJsonTablePlan::filteredRows()` now treats integer and real values as the
same numeric comparison class for `=`, `!=`, `IN`, and
`IS NOT DISTINCT FROM` / `IS DISTINCT FROM`, while preserving strict text
comparisons and SQL NULL behavior.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table numeric equality residual evidence row while
preserving the current accepted static SQLite upstream denominator and
veryquick evidence. This isolated worktree did not contain the hydrated
upstream cache, so no fresh upstream `testfixture`, `make test`, or `mptest`
run was started.

Verification run 2026-05-26T15:24Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/JSONB decoding, and existing SQL
numeric comparison helpers without activating shared support-library work.

## Focused Native Mapping: JSON Table MATCH Residual Predicates

This isolated json-table/window micro-slice extends the bounded JSON
table-valued planner residual filter to accept callback-backed `MATCH` and
`NOT MATCH` predicates on visible `json_each`/`json_tree` text columns. Native
`SQLiteJsonTablePlan::filteredRows()` accepts an explicit residual payload with
a text `pattern` and callable `match`, returns false for SQL NULL operands, and
keeps hidden `json`/`root` constraints as planned table-valued arguments.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table MATCH residual evidence row while preserving the
current accepted static SQLite upstream denominator and veryquick evidence.
This isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T15:50Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/JSONB decoding, and caller-supplied
application MATCH callbacks without activating shared support-library work.

## Focused Native Mapping: JSON Table IS NULL Residual Predicates

This isolated json-table/window micro-slice extends bounded residual predicate
execution for planned `json_each()` and `json_tree()` table-valued scans with
SQLite-style `IS NULL` and `IS NOT NULL` operators. Native
`SQLiteJsonTablePlan::filteredRows()` now filters visible `atom` values for
container rows whose SQL atom is NULL and scalar rows whose atom is not NULL,
while preserving hidden `json`/`root` constraint planning and existing
NULL-safe `IS DISTINCT FROM` behavior.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused JSON table IS NULL residual evidence row while preserving
the current accepted static SQLite upstream denominator and veryquick
evidence. This isolated worktree did not contain the hydrated upstream cache,
so no fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T16:12Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/JSONB decoding, and existing SQL
NULL comparison semantics without activating shared support-library work.

## Focused Native Mapping: Core timediff() Scalar Dispatch

This isolated SQL execution/planner scalar micro-slice adds bounded native
`timediff(A,B)` dispatch to `SQLiteCoreScalarFunction` for copied Application
`wp_options` timestamp diagnostics and future expression planning. Native PHP
now returns SQLite-style signed `+YYYY-MM-DD HH:MM:SS.SSS` interval strings,
propagates SQL NULL when either argument is NULL, and validates the two-argument
contract through the existing date/time parser.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core timediff scalar evidence row while preserving the
current accepted static SQLite upstream denominator and veryquick evidence.
This isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T16:34Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php timediff '2026-05-27 18:42:34' '2026-05-26 16:12:34'
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local date/time parsing and scalar dispatch without activating shared
support-library work.

## Focused Native Mapping: Text Aggregate group_concat/string_agg Helpers

This isolated SQL execution/planner aggregate micro-slice adds bounded native
`group_concat()`/`string_agg()` helper behavior for copied Application
`wp_options` summary diagnostics. Native PHP now covers NULL row skipping,
NULL separator propagation, scalar and BLOB text coercion, DISTINCT
de-duplication, ORDER BY scheduling, combined DISTINCT ORDER BY rows,
FILTER-style row selection, and ROWS-style rolling windows.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused text aggregate evidence row while preserving the current
accepted static SQLite upstream denominator and veryquick evidence. This
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T16:38Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteTextAggregate.php
php -l lanes/libsqlite/src/SQLiteTextAggregateState.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-group-concat-option-summary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-group-concat-option-summary.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion, `SQLiteBlobValue`, and accepted aggregate
scheduling patterns without activating shared support-library work.

## Focused Native Mapping: Core Math Scalar Dispatch

This isolated SQL execution/planner scalar micro-slice adds bounded native
math dispatch to `SQLiteCoreScalarFunction` for copied Application `wp_options`
diagnostics and future expression planning. Native PHP now covers
`ceil()`/`ceiling()`, `floor()`, `trunc()`, `sqrt()`, `pow()`/`power()`,
`mod()`, `ln()`, `log()`, `log10()`, `log2()`, `exp()`, `pi()`, `acos()`,
`asin()`, `atan()`, `atan2()`, `cos()`, `sin()`, and `tan()`, including SQL
NULL propagation, invalid-domain NULL results, lossless numeric coercion, and
strict arity/type errors.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional focused core math scalar evidence row while preserving the current
accepted static SQLite upstream denominator and veryquick evidence. This
isolated worktree did not contain the hydrated upstream cache, so no fresh
upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T17:08Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-core-scalar-option-default.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-core-scalar-option-default.php sqrt 16
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar coercion and PHP runtime math primitives without activating
shared support-library work.

## Focused Native Mapping: B-tree Overflow Delete Release Diagnostics

This isolated B-tree delete/rebalance micro-slice adds bounded native delete
diagnostics for overflow-backed table leaf rowids and index leaf records. The
new helpers preserve the rewritten leaf page and secure-delete freeblock
behavior while reporting obsolete overflow page numbers for a later
freelist/pointer-map release step.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps two
additional focused B-tree delete/rebalance evidence rows while preserving the
current accepted static SQLite upstream denominator and veryquick evidence.
This isolated worktree did not contain the hydrated upstream cache, so no
fresh upstream `testfixture`, `make test`, or `mptest` run was started.

Verification run 2026-05-26T17:41Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteTableLeafPage.php
php -l lanes/libsqlite/src/SQLiteIndexLeafPage.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-delete-overflow-option-release-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-delete-overflow-option-release-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local overflow-chain, freeblock, secure-delete, and freelist planning
primitives without activating shared support-library work.

## Upstream Runner: Release Parity Exclusion Decision Gate

This isolated upstream-suite runner micro-slice did not start a duplicate
broad `testfixture`, `release`, `all`, `make test`, or `mptest` run. Process
sampling showed only lane workers, not an active broad SQLite suite runner at
the time of selection. The implementation adds a machine-readable decision gate
for the persistent `ext/fts5/test/fts5aux.test` sanitizer blocker: repeated
broad release failures plus a clean focused repro are still blocked until the
supervisor explicitly accepts a non-portability exclusion, and accepted
exclusions still do not count as zero-error release/all parity.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional upstream-runner evidence gate while preserving the accepted static
SQLite denominator and veryquick evidence. No fresh upstream runner evidence is
claimed by this slice.

Verification run 2026-05-26T18:08Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest.php`
passed with 1 selected file, 576 assertions, and 0 failures, adding 17 focused
assertions for the exclusion decision gate. Manifest/status JSON decoded
successfully and lane diff check passed. The root harness was not run because
this was an isolated micro-slice.

Dependency closure: no new support component is needed. This composes existing
lane-local runner artifact, persistent blocker, focused repro, and supervisor
decision evidence only.

## Upstream Runner: Release Blocker Admission Record

This isolated release-blocker micro-slice did not start a duplicate broad
`testfixture`, `release`, `all`, `make test`, or `mptest` run. The
implementation adds the final machine-readable admission record for the
persistent `ext/fts5/test/fts5aux.test` release/all blocker. It composes the
existing bounded-runner countability gate with the supervisor exclusion decision
gate so an integrator has exactly two closure routes: a countable zero-error
release/all artifact, or an explicit supervisor non-portability exclusion. The
second route closes only the blocker and still does not count as zero-error
release/all parity.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` maps one
additional upstream-runner admission gate while preserving the accepted static
SQLite denominator and veryquick evidence. No fresh upstream runner evidence is
claimed by this slice.

Verification run 2026-05-26T18:14Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest.php`
passed with 1 selected file, 604 assertions, and 0 failures, adding 28 focused
assertions for the admission record. Manifest/status JSON decoded successfully
and lane diff check passed. The root harness was not run because this was an
isolated micro-slice.

Dependency closure: no new support component is needed. This composes existing
lane-local countability and explicit exclusion gates only.

## Encoding/Collation: Indexed GLOB Prefix Range

This isolated encoding/collation micro-slice did not start an upstream
testfixture because the worktree has no hydrated upstream checkout. It adds a
bounded native planner helper for copied Application `wp_options` scans:
`GLOB` patterns with a leading literal prefix now derive a binary
`option_name` index range, then apply the accepted SQLite `GLOB` matcher as a
residual predicate.

Focused upstream denominator impact: one lane-local focused Application
GLOB-prefix range script is mapped in `UPSTREAM_TEST_MANIFEST.json`; no fresh
upstream runner evidence is claimed.

Verification run 2026-05-26T18:37Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-option-name-like-glob.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteHeaderTest.php` passed with 1
selected file, 3124 assertions, and 0 failures, adding 13 focused assertions
for indexed GLOB-prefix range planning. The Application pattern smoke passed,
manifest/status JSON decoded successfully, and lane diff check passed. The
root harness was not run because this was an isolated micro-slice.

Dependency closure: no new support component is needed. This reuses
lane-local binary index range traversal and accepted GLOB residual matching.

## Upstream Runner: Release Rerun Admission Decision

This isolated upstream-suite micro-slice did not start a duplicate broad
`testfixture`, `release`, `all`, `make test`, or `mptest` run. It adds a
machine-readable release rerun-admission decision record over the existing
release admission ledger. The record keeps four handoff states distinct:
countable zero-error release/all parity means no rerun is needed, active broad
runner snapshots block duplicate launches, unresolved admission blockers remain
visible, and supervisor exclusion-only closure still does not count as
release/all parity or justify another broad run by itself.

Focused upstream denominator impact: one additional upstream-runner
rerun-admission decision gate is mapped in `UPSTREAM_TEST_MANIFEST.json`. No
fresh upstream runner evidence is claimed by this slice.

Verification run 2026-05-26T18:45Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest.php`
passed with 1 selected file, 652 assertions, and 0 failures, adding 48 focused
assertions for release rerun-admission decisions. Manifest/status JSON decoded
successfully and lane diff check passed. The root harness was not run because
this was an isolated micro-slice.

Dependency closure: no new support component is needed. This composes
lane-local admission ledger records and supplied active-runner snapshots only.

## Dependency Suite: Connection Counter Functions

This isolated dependency-suite micro-slice did not start an upstream
testfixture because the worktree has no hydrated upstream checkout. It adds a
bounded native connection-state helper for copied Application write paths:
`last_insert_rowid()`, `changes()`, and `total_changes()` now have lane-local
counter diagnostics that can be attached to insert/update/delete and savepoint
rollback previews without requiring ext-sqlite.

Focused upstream denominator impact: one lane-local focused core connection
counter script is mapped in `UPSTREAM_TEST_MANIFEST.json`; no fresh upstream
runner evidence is claimed.

Verification run 2026-05-26T19:14Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteConnectionCounters.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-connection-counter-option-insert.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-connection-counter-option-insert.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteHeaderTest.php` passed with 1
selected file, 3187 assertions, and 0 failures, adding 29 focused assertions
for connection-counter behavior. The Application connection-counter smoke passed,
manifest/status JSON decoded successfully, and lane diff check passed. The root
harness was not run because this was an isolated micro-slice.

Dependency closure: no new support component is needed. This reuses lane-local
write-plan and savepoint evidence and adds only a bounded native PHP
connection-state helper.

## Upstream Runner: Mixed Bounded Artifact Set Gate

This isolated upstream-suite micro-slice did not start a duplicate broad
`testfixture`, `release`, `all`, `make test`, or `mptest` run. It adds a
machine-readable artifact-set record over existing bounded runner audit/log
gates, so an integrator can supply multiple guarded artifacts and get one
countability summary: zero-error accepted-HEAD artifacts are publishable,
while missing files, active runners, failed sanitizer artifacts, and timeout
artifacts remain explicit blocked evidence.

Focused upstream denominator impact: one additional upstream-runner
artifact-set countability gate is mapped in `UPSTREAM_TEST_MANIFEST.json`. No
fresh upstream runner evidence is claimed by this slice.

Verification run 2026-05-26T19:25Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest.php`
passed with 1 selected file, 679 assertions, and 0 failures, adding 27
focused assertions for mixed bounded artifact-set classification. Manifest/status
JSON decoded successfully and lane diff check passed. The root harness was not
run because this was an isolated micro-slice.

Dependency closure: no new support component is needed. This composes existing
lane-local bounded runner file/countability gates only.

## Release Blocker: Closure Record Gate

This isolated release-blocker micro-slice did not start a duplicate broad
`testfixture`, `release`, `all`, `make test`, or `mptest` run. It adds
`SQLiteUpstreamSuiteEvidence::releaseBlockerClosureRecord()`, an
integrator-facing closure record that composes the existing bounded artifact-set
countability gate, release admission ledger, supervisor non-portability
exclusion decision, rerun decision, and active-runner duplicate gate.

The closure record has four explicit outcomes:

- `zero-error-release-parity-countable`: a guarded release/all artifact has
  parsed zero-error counts, accepted repository HEAD, matching SQLite manifest
  UUID, and no duplicate-runner blocker.
- `release-blocker-closed-by-exclusion`: supervisor policy explicitly accepts
  the persistent `fts5aux` sanitizer failure as exclusion-only closure; this
  does not count as zero-error release/all parity.
- `rerun-allowed`: no closure already exists, no active broad runner is visible,
  and supervisor approval allows one guarded broad rerun.
- `blocked-active-runner` or `blocked`: the integrator must wait for the active
  runner or resolve artifact/provenance/exclusion blockers before admission.

Focused upstream denominator impact: one additional release admission
blocker-removal gate is mapped in `UPSTREAM_TEST_MANIFEST.json`. No fresh
upstream runner evidence is claimed by this slice.

Verification run 2026-05-26T19:55Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteUpstreamSuiteEvidenceTest.php`
passed with 1 selected file, 714 assertions, and 0 failures, adding 35 focused
assertions for release-blocker closure-record composition. Manifest/status JSON
decoded successfully and lane diff check passed. The root harness was not run
because this was an isolated micro-slice.

Dependency closure: no new support component is needed. This composes existing
lane-local artifact-set countability, admission ledger, exclusion, rerun, and
active-runner gates only.

## Dependency Suite: File URI Open Preflight

This bounded dependency-suite micro-slice does not start a duplicate broad
SQLite `testfixture`, `release`, `all`, `make test`, or `mptest` run. It adds
`SQLiteFileUri::parse()` for lane-local SQLite `file:` URI filename preflight
before copied Application database repair/import/read-only inspection code opens
files.

Focused upstream denominator impact: one additional focused file/open evidence
row is mapped in `UPSTREAM_TEST_MANIFEST.json`. No fresh upstream runner
evidence is claimed by this slice.

Verification run 2026-05-26T19:53Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteFileUri.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-file-uri-open-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-file-uri-open-preflight.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteHeaderTest.php` passed with 1
selected file, 3278 assertions, and 0 failures, adding 27 focused assertions.
The Application file URI smoke passed, manifest/status JSON decoded successfully,
and lane diff check passed. The root harness was not run because this was an
isolated micro-slice.

Dependency closure: no new support component is needed. This parser is bounded
to SQLite filename semantics and does not activate the shared URL or
percent-encoding backlog.

## Focused Native Mapping: Incremental-vacuum Tail Truncation

This B-tree delete/rebalance micro-slice maps a bounded portion of SQLite
incremental-vacuum behavior: when the highest-numbered database pages are
already on the freelist, native planning can remove only the contiguous free
tail, rewrite freelist trunk/leaf metadata, and reduce the database page count
without touching non-tail free pages.

Focused upstream denominator impact: one additional B-tree/autovacuum evidence
row is mapped against `incrvacuum.test` and existing freelist mutation behavior.
No fresh upstream runner evidence is claimed by this isolated worktree slice.

Native coverage added 2026-05-26:

- truncates free tail leaves plus an empty first trunk page;
- rewrites the first freelist trunk pointer when the removed tail page was the
  old first trunk;
- removes truncated leaves from lower trunk arrays while preserving lower
  reusable pages;
- leaves the database unchanged when the highest page is not free;
- rejects invalid truncation counts.

Dependency closure: no new support component is needed; this reuses lane-local
SQLite header and freelist trunk parsing/assembly.

## Focused Native Mapping: JSON Table Visible And Hidden Projection

This JSON table/window micro-slice maps the SQLite JSON virtual table output
boundary between `SELECT *` visible columns and explicitly requested hidden
columns. Native `SQLiteJsonTablePlan::visibleRows()` now returns the visible
`json_each`/`json_tree` columns (`key`, `value`, `type`, `atom`, `id`,
`parent`, `fullkey`, `path`) without hidden `json` or `root`, while
`projectedRows()` supports explicit hidden `json`/`root` columns and
`rowid`/`_rowid_`/`oid` aliases after the accepted hidden-column planner and
residual filtering have run.

Focused upstream denominator impact: one additional JSON table output-shaping
evidence row is mapped against `json101.test`/`json102.test` behavior. No fresh
upstream runner evidence is claimed by this slice.

Verification run 2026-05-26T20:20Z in the isolated worker:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed; focused `SQLiteHeaderTest.php` passed with 1
selected file, 3363 assertions, and 0 failures, adding 49 focused assertions
over the pre-slice 3314 focused assertion count. The Application JSON table
smoke passed. The root harness was not run because this was an isolated
micro-slice.

Dependency closure: no new support component is needed; this reuses existing
lane-local JSON table row generation and residual filtering.

## Focused Native Mapping: SELECT EXISTS/IN Subquery Filters

Date: 2026-05-26

This isolated SQL execution/planner micro-slice adds bounded native result-row
filters for subquery-style SELECT predicates after row production: `EXISTS`,
`NOT EXISTS`, `IN`, and `NOT IN`. The helper preserves SQLite's important NULL
edges for `IN`/`NOT IN`: NULL left-hand values do not match, and `NOT IN` with a
NULL in the subquery result filters out non-matching rows as UNKNOWN.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
increases by 1 with `focusedCoreSelectSubqueryFilterScripts: 1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
started from this isolated worktree.

Verification run for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectResult.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-subquery-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-subquery-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This slice reuses
lane-local SQL value keys, BLOB wrappers, and pure PHP result-array dispatch.

## Focused Native Mapping: SELECT Join Row Production

Date: 2026-05-26

This isolated priority micro-slice maps one additional focused upstream
behavior row for bounded SELECT row production across INNER JOIN, CROSS JOIN,
LEFT JOIN, and JOIN USING semantics. It does not launch a fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run from this
worktree.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 390 to 391 by adding `focusedCoreSelectJoinScripts=1`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteSelectResult.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-join-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-join-preview.php
```

Result: focused PHP passed 1 selected test file, 3835 assertions, and 0
failures. The Application join preview smoke passed and reports copied
wp_options rows joined to option metadata without requiring ext/sqlite.

Dependency closure: no new support component is needed. This slice reuses
lane-local SQL value keys, BLOB wrappers, and pure PHP result-array dispatch.

## Focused Native Mapping: File Header Loader Preflight

Date: 2026-05-26

This isolated dependency-suite micro-slice maps one additional focused
file/open support row for bounded SQLite file-header loading after URI, open
admission, immutable/VFS, nolock, and busy-handler planning. It does not launch
a fresh upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run
from this worktree.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 395 to 396 by adding `focusedFileHeaderLoaderScripts=1`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteFileHeaderLoader.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-file-header-loader-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["loads bounded sqlite file headers after open admission"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-file-header-loader-preflight.php
```

Result: focused PHP passed with 60 assertions and 0 failures. The Application
smoke reports a copied database header read of 100 bytes, page-size and
declared-page checks, read-only immutable VFS admission, and dependency tags
without requiring ext/sqlite.

Dependency closure: no new shared support component is needed. This is a
lane-local bounded file-header helper that composes accepted file URI,
open-admission, busy-handler, and SQLite header parsing surfaces.

## Focused Native Mapping: Page Cache Preflight

Date: 2026-05-26

This isolated dependency-suite micro-slice maps one additional focused
file/open support row for bounded page-size-aligned SQLite page loading after
URI parsing, open admission, immutable/VFS propagation, busy-handler gating,
and header validation. It does not launch a fresh upstream `testfixture`,
`make test`, `mptest`, `all`, or `release` run from this worktree.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 398 to 399 by adding `focusedPageCacheLoaderScripts=1`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePageCache.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-page-cache-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["loads sqlite pages through a bounded page cache after open admission"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-page-cache-preflight.php
```

Result: focused selected PHP passed with 48 assertions and 0 failures. The
Application page-cache smoke passed and reports copied `wp_options` database
pages loaded by page number without requiring ext/sqlite.

Dependency closure: no new support component is needed. This is lane-local
bounded page-cache dispatch and reuses accepted file URI, open-plan,
busy-handler, file-header loader, and SQLite header parsing helpers.
### 2026-05-26 JSON table malformed JSONB planner diagnostic slice

This isolated JSON table/window micro-slice maps one additional focused
upstream behavior row for malformed JSONB handling at the JSON table hidden
`json` constraint boundary. No broad upstream runner was launched from this
worktree.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 388 to 389 by adding
`focusedJsonTableMalformedJsonbPlanScripts=1`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
```

Result: focused PHP passed 1 selected test file, 3721 assertions, and 0
failures. The Application smoke passed and includes validated planner diagnostics
for malformed JSONB payloads.
## Focused Native Mapping: SELECT CASE Projection Expressions

Date: 2026-05-26

This isolated SQL execution/planner micro-slice maps one additional focused
behavior row for bounded SELECT projection CASE expressions over produced
rows. `SQLiteSelectProjection` now evaluates simple CASE and searched CASE,
uses SQL-style truthiness for searched WHEN terms, treats NULL simple-CASE
comparisons as non-matches, and returns the first matching branch lazily before
falling back to ELSE or NULL.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 394 to 395 by adding
`focusedApplicationSelectCaseProjectionScripts=1`. No fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run was launched
from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteSelectProjection.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-case-preview.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["projects select result rows through case expressions"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-select-case-preview.php
```

Result: focused selected PHP passed with 40 assertions and 0 failures. The
Application CASE projection smoke passed and reports copied `wp_options` rows
bucketed through CASE expressions without requiring ext/sqlite.

Dependency closure: no new support component is needed. This is lane-local
SELECT expression dispatch and reuses existing scalar functions, BLOB wrappers,
and result ordering helpers.

## Focused Native Mapping: SELECT WHERE Expression-Index Planning

Date: 2026-05-26

This isolated SQL execution/planner micro-slice maps one additional focused
behavior row for bounded SELECT WHERE expression-index planning. The new
`SQLiteSelectExpressionIndexPlan` helper chooses usable expression indexes for
`lower(column)`, `upper(column)`, `length(column)`, and
`CAST(column AS INTEGER)` constraints over point, IN-list, BETWEEN, and simple
range predicates, while preserving residual predicate checks and safe
`IS NOT NULL` partial-index gating.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 400 to 401 by adding
`focusedApplicationSelectExpressionIndexPlanScripts=1`. No fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run was launched
from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-expression-index-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-expression-index-plan.php
```

Result: focused PHP passed 1 selected test file, 4242 assertions, and 0
failures, up from the accepted lane-status baseline of 4201 assertions. The
Application expression-index smoke passed and reports copied `wp_options`
expression-index dispatch without requiring ext/sqlite.

Dependency closure: no new support component is needed. This is lane-local
planner dispatch and reuses existing CREATE INDEX expression parsing,
partial-index predicate metadata, and scalar/BLOB value wrappers.

## Focused Native Mapping: SQLite Lock Coordination

Date: 2026-05-26

This isolated dependency/open micro-slice maps one additional focused behavior
row for bounded SQLite lock admission. The new `SQLiteLockCoordinator` models
shared, reserved, pending, and exclusive lock compatibility, composes blocked
lock attempts with the existing busy-handler planner, and combines lock
planning with file URI/open admission for copied Application database preflights.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 402 to 403 by adding
`focusedApplicationLockCoordinationScripts=1`. No fresh upstream `testfixture`,
`make test`, `mptest`, `all`, or `release` run was launched from this isolated
worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteLockCoordinator.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-lock-coordination-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["coordinates sqlite file locks for open admission without a vfs dependency"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-lock-coordination-preflight.php
```

Result: focused selected PHP passed with 53 assertions and 0 failures. The
Application lock-coordination smoke passed and reports copied database open
diagnostics without requiring ext/sqlite.

Dependency closure: no new support component is needed for this bounded slice.
It reuses lane-local file URI parsing, open planning, and busy-handler
diagnostics. A real shared VFS/process-lock implementation remains a future
activation gate requiring cross-process lock evidence.

## Focused Native Mapping: SQLite VFS Capability Planning

Date: 2026-05-27

This isolated dependency/open micro-slice maps one additional focused behavior
row for bounded SQLite VFS file-control and device-capability planning. The new
`SQLiteVfsCapabilityPlan` helper reports sector-size, device-characteristic,
powersafe-overwrite, persist-WAL, chunk-size, mmap-size, and sync-policy
decisions over accepted file URI/open admission results for copied Application
database preflights.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 409 to 410 by adding
`focusedVfsCapabilityPlanScripts=1`. No fresh upstream `testfixture`,
`make test`, `mptest`, `all`, or `release` run was launched from this isolated
worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCapabilityPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-capability-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-capability-preflight.php
```

Result: focused PHP passed 1 selected test file, 5073 assertions, and 0
failures, up from the accepted lane-status baseline of 5010 assertions. The
Application VFS capability smoke passed and reports copied database preflight
diagnostics without requiring ext/sqlite.

Dependency closure: no new support component is needed for this bounded slice.
It reuses lane-local file URI parsing and open planning. A real shared VFS
file-control implementation remains a future activation gate requiring native
file write, sync, mmap, and file-control execution evidence.

## Focused Native Mapping: VFS Lock Byte Ranges

Date: 2026-05-27

This isolated dependency/open micro-slice maps one additional focused VFS
locking behavior row. The new `SQLiteLockByteRangePlan` helper records
SQLite's pending byte, reserved byte, shared lock range, per-reader shared
slot, exclusive shared-range coverage, `nolock` suppression, and composition
with accepted open-admission dependencies for copied Application database paths.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 419 to 420 by adding `focusedVfsLockByteRangePlan=1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
launched from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteLockByteRangePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-lock-coordination-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["plans sqlite vfs lock byte ranges for open handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-lock-coordination-preflight.php
```

Result: selected focused PHP passed with 67 assertions and 0 failures. The
Application lock coordination smoke passed and reports copied database lock-byte
diagnostics without requiring ext/sqlite.

Dependency closure: no new shared support component is needed for this bounded
slice. It reuses lane-local file URI/open admission and lock coordination
evidence. A real native VFS file-lock executor remains a future activation gate
requiring process/file-handle lock acquisition and release evidence for these
byte ranges.

## Focused Native Mapping: WAL Checkpoint File-Write Coordination

Date: 2026-05-27

This isolated WAL rollback/savepoint micro-slice maps one additional focused
behavior row for bounded WAL checkpoint persistence. The new
`SQLiteWalFileWritePlan` helper composes accepted durable checkpoint database
and WAL bytes into ordered VFS-style database writes, database sync, WAL
preserve/restart/truncate operations, WAL sync, directory sync, and writable
handle guards for copied Application database paths.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 411 to 412 by adding `focusedWalFileWritePlanScripts=1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
launched from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalFileWritePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["plans sqlite wal durable checkpoint vfs file writes"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-wal-option-frame-diagnostics.php
```

Result: selected focused PHP passed with 68 assertions and 0 failures. The
Application WAL smoke passed and reports copied database checkpoint file-write
diagnostics without requiring ext/sqlite.

Dependency closure: no new shared support component is needed for this bounded
slice. It reuses lane-local WAL parsing, checkpoint mode result planning, and
durable sidecar byte materialization. A real VFS writer remains a future
activation gate requiring native file write, sync, and truncate execution.

## Focused Runner Gate: Ignore pgrep Probe Commands

Date: 2026-05-27

This isolated upstream-suite micro-slice removes a duplicate-runner gate false
positive: `SQLiteUpstreamSuiteEvidence::activeFullSuiteRunnerGate()` now ignores
`pgrep` probe command lines in supplied process snapshots. The gate still blocks
real broad `testfixture`, guarded bounded-runner wrapper, `make test`, and
`mptest` processes, but the safety probe used to check for those processes no
longer blocks launch/countability evidence by matching its own pattern text.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 412 to 413 by adding
`focusedUpstreamActiveRunnerPgrepSnapshotScripts=1`. No fresh upstream
`testfixture`, `make test`, `mptest`, `all`, or `release` run was launched from
this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
```

Dependency closure: no new support component is needed. This slice composes
lane-local runner evidence parsing only and does not require a shared process,
VFS, Tcl, or SQLite support component.

## Focused Native Mapping: LIKE Pattern Plan and NOCASE Prefix Bounds

Date: 2026-05-27

This isolated encoding/collation micro-slice maps one additional focused
behavior row for SQLite LIKE planner semantics over copied Application option
names. `SQLiteDatabase::likePatternPlan()` now reports escaped literal prefix
text, wildcard presence, UTF-8 character length, ASCII-only prefix status,
binary index bounds, and NOCASE index bounds. The existing NOCASE indexed LIKE
lookup now consumes that explicit plan instead of recomputing folded bounds
inline.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` gains one
bounded encoding/collation row for LIKE planner prefix evidence. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
launched from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-option-name-like-glob.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-option-name-like-glob.php --self-test
git diff --check -- lanes/libsqlite
```

Result: focused `SQLiteHeaderTest.php` passed with 8477 assertions and 0
failures. The Application LIKE/GLOB smoke passed and reports binary/NOCASE LIKE
plans, escaped wildcard literals, and ASCII-only non-ASCII matching for copied
`wp_options` rows.

Dependency closure: no new shared support component is needed. This reuses
lane-local UTF-8 text splitting, ASCII NOCASE folding, LIKE/GLOB matchers, and
copied Application option fixtures.

## Focused Native Mapping: PRAGMA locking_mode Current State

Date: 2026-05-27

This isolated libsqlite micro-slice maps one additional focused PRAGMA behavior
row for SQLite `PRAGMA locking_mode` current connection state. The bounded
`SQLitePragmaLockingMode` model reports default `normal`, applies
`exclusive`/`normal` assignments, preserves schema-qualified PRAGMA forms,
leaves unknown requested modes as no-ops, and reports the TEMP schema as
always `exclusive`.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 449 to 450 by adding `focusedPragmaLockingModeScripts=1`. No fresh
upstream `testfixture`, `make test`, `mptest`, `all`, or `release` run was
launched from this isolated worktree.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaLockingMode.php
php -l lanes/libsqlite/src/SQLitePragmaSnapshot.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-pragma-preflight.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-pragma-preflight.php /tmp/libsqlite-lockmode-smoke.sqlite
git diff --check -- lanes/libsqlite
```

Result: focused `SQLiteHeaderTest.php` passed with 9570 assertions and 0
failures, up from the prior lane-status focused count of 9516 assertions
(`+54`). The Application pragma smoke passed against a generated SQLite header
fixture and reports current locking mode transitions without requiring
ext/sqlite.

Dependency closure: no new shared support component is needed. This slice
reuses lane-local pragma/header state and pure PHP Application copy preflight
fixtures. Non-overlap: it avoids accepted VFS byte-range, process lock,
lock-state, locked-writer, pager checkpoint, rollback, WAL, B-tree, JSON, and
SELECT clusters by covering only `PRAGMA locking_mode` current connection
state.

## Focused Native Mapping: INSERT DEFAULT VALUES Generated Defaults

Date: 2026-05-27

This isolated libsqlite micro-slice adds bounded native PHP execution for
`INSERT INTO ... DEFAULT VALUES` over copied row arrays. The executor parses
the target table from SQL text, reads column defaults and generated-column
expressions from `CREATE TABLE` SQL, assigns the next INTEGER PRIMARY KEY rowid,
evaluates literal, signed numeric, `CURRENT_*`, `coalesce()`, `upper()`,
`lower()`, `length()`, concatenation, and simple arithmetic expressions, then
computes virtual/stored generated columns after defaulted base columns.

Focused evidence:
`php tools/run-tests.php lanes/libsqlite/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php`
reported `1 test files, 51 assertions, 0 failures` and 41 PASS lines. The
Application smoke
`php lanes/libsqlite/examples/application-insert-default-values-generated-default.php --self-test`
passed and reports a copied `wp_options`-style default row without requiring
`ext/sqlite`.

Dashboard impact: `lane-status.json` `phpPass` moved from 3796 to 3837. No
mapped denominator change is claimed; this is focused PHP behavior coverage,
not a new upstream inventory unit.

Dependency closure: no new shared support component is needed. This reuses
lane-local SQL/schema parsing and scalar expression helpers. Non-overlap: this
avoids accepted SELECT SQL text/JOIN/GROUP/subquery/ORDER expression clusters,
VFS lock/write/sync/rollback apply clusters, WAL checkpoint/savepoint byte
clusters, B-tree page-move/root-collapse/overflow clusters, JSON table
source/cursor/constraint clusters, Unicode GLOB, and PRAGMA locking-mode work by
covering only INSERT DEFAULT VALUES with generated/default column evaluation.
### 2026-05-27 ANALYZE sqlite_stat1 planner corpus

This isolated planner micro-slice adds a bounded native PHP corpus for SQLite
`ANALYZE` / `sqlite_stat1` planner behavior. It parses `sqlite_stat1` row-count
and per-prefix average cardinality values, ranks equality/`IN`/range constraints
across single-column and composite indexes, preserves deterministic fallback
table scans, and guards malformed stat rows. The focused Application smoke uses
copied `wp_options`, `wp_postmeta`, and `wp_posts` style stats to preview index
selection before row decoding without `ext/sqlite`.

Focused evidence:
`php tools/run-tests.php lanes/libsqlite/tests/SQLiteAnalyzeStatPlannerCorpusTest.php`
reported `1 test files, 105 assertions, 0 failures` and 50 PASS lines. The
`phpPass` status counter moved from 1336 to 1386. No mapped denominator change
was claimed; this is a new focused PHP planner corpus, not a new upstream
inventory hydration row. Root harness status: not run for this isolated
micro-slice.

Non-overlap: this avoids accepted expression-index range-cost ranking, parser
SELECT SQL text dispatch, grouped SELECT, subqueries, JSON table source/cursor
and visible/hidden constraint work, VFS file writer/sync/lock/rollback apply,
WAL savepoint byte truncation/checkpoint transaction work, B-tree page move/root
collapse/overflow freelist release, and Unicode GLOB range work. Dependency
closure: no new support component is needed.
## Focused Native Mapping: JSON Scalar SQL Input Mutation next13

Date: 2026-05-27

This isolated JSON mutation slice covers SQLite JSON SQL-function boundaries
where numeric SQL values are accepted as JSON documents. Native
`json_patch()`/`jsonb_patch()`, `json_set()`/`jsonb_set()` and related
mutation variants, and `json_remove()`/`jsonb_remove()` now normalize PHP
integer, finite float, and boolean inputs at the JSON-document argument
boundary. Booleans follow SQLite SQL truth literal behavior by entering the
JSON boundary as integer `1` or `0`; non-finite float inputs are rejected.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonScalarInputMutationNext13Test.php
php lanes/libsqlite/examples/application-json-scalar-input-mutation-next13.php
```

Result: the focused test file reported `1 test files, 54 assertions, 0
failures` with 54 PASS lines. The Application smoke reports copied numeric
`wp_options.option_value` rows promoted by `json_patch()`, root-replaced by
`json_set()`, left unchanged by nested set/remove paths, removed to SQL NULL at
`$`, and returned as JSONB blobs for `jsonb_patch()` without requiring
`ext/sqlite`.

Dashboard impact: `lane-status.json` `phpPass` moves from 3796 to 3850. No
mapped upstream denominator row is claimed because this is focused PHP
coverage for an already mapped JSON mutation family, not a new hydrated
upstream inventory unit.

Dependency closure: no new support component is needed. This reuses the
lane-local JSON5 parser, JSONB codec, canonical JSON encoder, JSON path
mutation/removal helpers, and copied Application option smoke fixtures.

Non-overlap: this avoids accepted JSON table cursor/source/hidden/visible
constraint work, JSON host joins, malformed JSONB planner diagnostics,
Unicode GLOB, VFS write/sync/lock/rollback clusters, WAL checkpoint/savepoint
clusters, B-tree page move/overflow/root-collapse clusters, and parser-level
SELECT SQL text/subquery/group/order work.

## Upstream Runner Evidence: Accepted-HEAD Artifact Directory Provenance current-next19

Date: 2026-05-27

This isolated upstream-runner micro-slice did not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::acceptedHeadArtifactProvenanceDirectoryRecord()`,
which scans a bounded-runner artifact directory for audit Markdown files,
pairs logs when available, parses each artifact, then feeds the records through
the existing accepted-HEAD and SQLite manifest UUID provenance gates.

The new record lets the integrator classify a whole current-source artifact
directory at once: current focused artifacts can be routed to focused evidence,
current release-like artifacts can be routed to release countability gates,
stale repository-head artifacts remain blocked, SQLite manifest mismatches
remain blocked, and missing log pairs stay explicit. Release/all parity is not
credited by this directory provenance gate.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from 456 to 457 by adding
`focusedUpstreamAcceptedHeadArtifactDirectoryScripts=1`. No fresh upstream
runner evidence is claimed.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
git diff --check -- lanes/libsqlite
```

Result: the focused upstream-suite evidence test passed with `1 test files,
894 assertions, 0 failures`. The pre-change baseline for the same file was
`1 test files, 859 assertions, 0 failures`, so this patch adds one verified
TestRunner PASS case and 35 focused assertions. `lane-status.json` `phpPass`
moves from 6444 to 6445.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner audit/log parsing, accepted-HEAD provenance, SQLite manifest
UUID gates, and release/focused countability routing. Non-overlap: it avoids
accepted release-blocker closure-record, artifact-set admission, focused-runner
admission, active-runner pgrep filtering, foreground snapshot parsing, and
release rerun/exclusion ledger work by covering only directory-level
accepted-HEAD provenance over already produced bounded-runner artifacts.

## Upstream Runner Evidence: veryquick shard current-source next164

Date: 2026-05-28

This isolated upstream-suite micro-slice did not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext164()`,
which admits one lane-local zero-error guarded veryquick shard row only when
the launcher Base accepted HEAD, current integration source heads, concrete
`.test` selections, duplicate-runner gate, removed-blocker classification, and
focused PHP PASS-line output all match the next164 evidence record.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from `610` to `611`. The slice records focused current-source veryquick
shard countability only; release/all parity remains gated on a separate
accepted zero-error broad artifact.

Focused verification: `php tools/run-tests.php
lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext164Test.php`
passed with `1 test files, 1122 assertions, 0 failures` and 71 PASS lines.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, accepted source provenance checks, active-runner
gating, and focused `TestRunner` output parsing.

Non-overlap: this avoids accepted batch153 next161 veryquick evidence,
suite155/157/159, exact-shard next148, queued runner106/jsonvt104 rebase work,
and accepted B-tree, JSON, VFS/WAL, planner, PRAGMA, ATTACH, window, and VDBE
behavior surfaces.
### 2026-05-28 - WAL hot-journal savepoint checkpoint current-source next250

This isolated WAL/pager micro-slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a current-source guard that admits pager cache/readmark invalidation only after an admitted next247 hot-journal checkpoint cleanup plan. The behavior covers stale page-cache dirty pages, WAL readmarks, schema-cookie refresh, WAL-index refresh, reader reopen, stale hot-journal visibility, stale WAL visibility, savepoint depth, shared-lock state, duplicate receipts, and missing reader/page/frame coverage before the checkpointed current source can be served.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 115 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next250.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next250.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass` +115, from `128615` to `128730`; no mapped upstream coverage claim. Dependency closure: no new support component needed; this reuses next247 cleanup admission plus lane-local pager cache, readmark, schema-cookie, and WAL-index refresh receipt modeling.

Non-overlap: next250 verifies stale pager cache and readmark invalidation after next247 cleanup. It does not repeat checkpoint publication, VFS durable handoff ordering, cleanup receipt admission, WAL byte truncation, rollback-journal apply/commit, VFS sync/apply, file locking, SELECT, JSON, or B-tree surfaces.

## Suite Upstream Veryquick Shard Current Source Next290

Date: 2026-05-28

This isolated upstream-suite micro-slice adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext290()`.
It admits one lane-local zero-error guarded veryquick shard row tied to
launcher Base accepted HEAD `2d826f3672d51185a8fc82f12ed43afe26d2c9d6` and
accepted batch220 source `d650b9d8bbcee54ae28d34c6f45fad861468f872`.

Focused upstream denominator impact: mapped coverage moves from `680 / 1589`
to `681 / 1589`; `phpPass` moves from `136435` to `136531` through exact
focused PASS-line admission. Release/all parity remains unclaimed.

Focused verification: `php tools/run-tests.php
lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext290Test.php`
passed with `1 test files, 1500 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, accepted source provenance checks, active-runner
gating, and focused `TestRunner` output parsing.

Non-overlap: this avoids accepted next155 through next276 veryquick evidence,
exact-shard next148, queued runner106/jsonvt104 rebase work, accepted
batch109-113 and batch220 behavior surfaces, and live B-tree, JSON, VFS, WAL,
planner, PRAGMA, ATTACH, window, and VDBE work.
