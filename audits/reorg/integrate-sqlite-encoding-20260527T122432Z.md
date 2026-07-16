# libsqlite Encoding/Collation LIKE Integration

Ready marker: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-encoding-20260527T111647Z.ready`
Review audit: `audits/reorg/review-sqlite-encoding-20260527T112820Z.md`
Lane: `libsqlite`
Slice: `encoding-collation-like-glob-affinity`
Decision: integrated

## Source Verification

- Verified review decision: `accepted`.
- Verified handoff ledger row `291` decision before integration: `accepted`.
- Verified shared `refs/heads/main` before worktree creation:
  `20a4279d60eab6b65e16f46c92e15b643358b640`.
- Created clean integration worktree outside the shared checkout:
  `/home/claude/port-libs-integrate-sqlite-encoding-20260527T122432Z`.
- Worktree base: `20a4279d60eab6b65e16f46c92e15b643358b640`.
- Handoff patch base: `1761991a9ce963014b13916c4128e7a6d09d4b2b`.

## Rebase Notes

`git apply --check` failed only for current-source drift in:

- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/wordpress-scenarios.md`

Applied the non-conflicting patch hunks mechanically and manually merged only
those shared status/context files. Existing JSON, B-tree, VFS, WAL, and pager
status/scenario text from current source was preserved while adding the LIKE
planner slice.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteDatabase.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-option-name-like-glob.php
```

Result: no syntax errors detected.

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: `1 test files, 8810 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/wordpress-option-name-like-glob.php --self-test
```

Result: exited 0 and emitted LIKE pattern, binary/NOCASE prefix range, escaped
wildcard, indexed LIKE, Unicode LIKE, and GLOB diagnostics.

```sh
php -r 'foreach (["lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json","lanes/libsqlite/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'
git diff --check -- lanes/libsqlite
```

Result: JSON validation passed and lane diff check was clean.

Serialized root gate:

```sh
TMPDIR="$PWD/.tmp-root" php tools/run-tests.php
```

Result: `215 test files, 34090 assertions, 0 failures`.

## Outcome

Integrated exactly one accepted handoff. No other handoff was applied, and the
shared dirty checkout files were not reset, cleaned, or checked out.
