Source-neutral trigger/upsert/view defaults cleanup

Slice: source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T134833Z-0
Base accepted HEAD: 9cec814218deb6c90aaec05ae00c825ef24541da

Production source neutralized:

- lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php
- lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php

Behavior-preserving rename scope:

- Legacy option/autoload/wp-shaped defaults were renamed to generic
  setting/load-policy/application terms.
- Direct trigger recursive view returning/upsert tests and application examples
  were updated to assert the same behavior through neutral fixture names.
- The source-neutral trigger defaults guard now scans both owned production
  source files.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturning*Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsert*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: 89 test files, 6768 assertions, 0 failures.
- Changed example self-tests:
  - Result: 11 changed examples with `--self-test`, all passed.
- Changed PHP lint:
  - Result: all changed PHP files passed `php -l`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

Dependency closure:

- No new support component is needed. This is a source-neutral naming cleanup
  over existing trigger/upsert/view behavior and directly coupled tests/examples.

Root harness:

- Not run - isolated micro-slice.
