# Consolidate Final Numbered Methods Compound Select Seventy-Fourth Pass

Consolidated the compound UNION/LIMIT affinity helper family by replacing the retired worker-numbered public entrypoint and private helpers with stable descriptive method names on `SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan`.

Direct coverage was migrated from the numbered test/example filenames to stable names:

- `lanes/libsqlite/tests/SQLiteCompoundUnionLimitAffinityCurrentSourceTest.php`
- `lanes/libsqlite/examples/wordpress-compound-union-limit-affinity-current-source.php`

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundUnionLimitAffinityCurrentSourceTest.php
php -l lanes/libsqlite/examples/wordpress-compound-union-limit-affinity-current-source.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundUnionLimitAffinityCurrentSourceTest.php
php lanes/libsqlite/examples/wordpress-compound-union-limit-affinity-current-source.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed; this is a production API/name consolidation over existing compound SELECT parser/executor behavior.
