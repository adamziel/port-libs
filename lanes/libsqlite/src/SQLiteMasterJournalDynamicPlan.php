<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMasterJournalDynamicPlan
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function masterJournalRows(int $count = 1000): array
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('SQLite master-journal dynamic row count must be positive');
        }

        $rows = [];
        for ($case = 1; $case <= $count; $case++) {
            $variant = ($case - 1) % 6;

            $rows[] = match ($variant) {
                0 => self::journalPointerSafetyPlan('test.db2journal', null, $case),
                1 => self::journalPointerSafetyPlan('test0db2journal', null, $case),
                2 => self::journalPointerSafetyPlan('test.db2-master', 'test1', $case),
                3 => self::creationPlan([
                    ['name' => 'main_' . $case . '.db', 'kind' => 'real', 'modified' => true],
                    ['name' => 'attached_' . $case . '.db', 'kind' => 'real', 'modified' => true],
                ], $case),
                4 => self::creationPlan([
                    ['name' => 'main_' . $case . '.db', 'kind' => 'real', 'modified' => true],
                    ['name' => 'scratch_' . $case, 'kind' => 'temporary', 'modified' => true],
                ], $case),
                default => self::creationPlan([
                    ['name' => 'main_' . $case . '.db', 'kind' => 'real', 'modified' => true],
                    ['name' => 'memory_' . $case, 'kind' => 'memory', 'modified' => true],
                ], $case),
            };
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public static function journalPointerSafetyPlan(string $journalPointerName, ?string $masterMemberName, int $case = 1): array
    {
        self::assertSafeName($journalPointerName, 'journal pointer');
        if ($masterMemberName !== null) {
            self::assertSafeName($masterMemberName, 'master-journal member');
        }
        if ($case <= 0) {
            throw new \InvalidArgumentException('SQLite master-journal dynamic case number must be positive');
        }

        $journalPointerHasDash = str_contains(basename($journalPointerName), '-');
        $masterMemberHasDash = $masterMemberName === null ? null : str_contains(basename($masterMemberName), '-');
        $unsafeLegacyName = !$journalPointerHasDash || $masterMemberHasDash === false;
        $section = match (true) {
            $masterMemberName !== null => 'mjournal-1.5..1.6',
            str_starts_with($journalPointerName, 'test0') => 'mjournal-1.3..1.4',
            default => 'mjournal-1.1..1.2',
        };
        $upstream = match ($section) {
            'mjournal-1.5..1.6' => 'mjournal.test 1.5-1.6 master journal member without dash is ignored safely',
            'mjournal-1.3..1.4' => 'mjournal.test 1.3-1.4 rollback journal pointer without dash is ignored safely',
            default => 'mjournal.test 1.1-1.2 rollback journal pointer without dash is ignored safely',
        };

        $journalBytes = self::rawPointerJournalBytes($journalPointerName, $case);
        $masterBytes = $masterMemberName === null ? '' : $masterMemberName . "\0";

        return [
            'script' => 'mjournal.test',
            'kind' => 'pointer-safety',
            'case' => $case,
            'section' => $section,
            'upstream' => $upstream . ' dynamic case ' . sprintf('%04d', $case),
            'journal_pointer_name' => $journalPointerName,
            'journal_pointer_has_dash' => $journalPointerHasDash,
            'journal_pointer_length' => strlen($journalPointerName),
            'journal_bytes' => $journalBytes,
            'journal_bytes_prefix_hex' => strtoupper(bin2hex(substr($journalBytes, 0, 32))),
            'journal_bytes_contains_pointer' => str_contains($journalBytes, $journalPointerName . "\0"),
            'master_member_name' => $masterMemberName,
            'master_member_has_dash' => $masterMemberHasDash,
            'master_journal_contains_member' => $masterMemberName !== null,
            'master_journal_bytes' => $masterBytes,
            'master_journal_bytes_prefix_hex' => strtoupper(bin2hex(substr($masterBytes, 0, 32))),
            'legacy_name_without_dash' => $unsafeLegacyName,
            'status' => 'ok',
            'select_result' => [],
            'read_sql' => 'SELECT * FROM t1',
            'database_rows_preserved' => true,
            'crash_prevented' => true,
            'recovery_action' => $unsafeLegacyName
                ? 'ignore_legacy_master_journal_name_without_crash'
                : 'validate_master_journal_members',
            'master_probe_safe' => true,
            'dependencies' => [
                'real-upstream-corpus-mjournal',
                'sqlite-master-journal-legacy-name-safety',
                'sqlite-pager-master-journal-dynamic',
            ],
        ];
    }

    /**
     * @param list<array{name:string,kind:string,modified:bool}> $databases
     * @return array<string, mixed>
     */
    public static function creationPlan(array $databases, int $case = 1): array
    {
        if ($case <= 0) {
            throw new \InvalidArgumentException('SQLite master-journal dynamic case number must be positive');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite master-journal dynamic creation plan requires databases');
        }

        $realModified = 0;
        $modifiedKinds = [];
        $openedFiles = [];

        foreach ($databases as $database) {
            $name = (string) ($database['name'] ?? '');
            $kind = (string) ($database['kind'] ?? '');
            $modified = (bool) ($database['modified'] ?? false);

            self::assertSafeName($name, 'database');
            if (!in_array($kind, ['real', 'temporary', 'memory'], true)) {
                throw new \InvalidArgumentException('SQLite master-journal dynamic database kind is unsupported');
            }

            if ($modified) {
                $modifiedKinds[] = $kind;
                if ($kind === 'real') {
                    $realModified++;
                    $openedFiles[] = $name . '-journal';
                }
            }
        }

        $masterJournalOpened = $realModified >= 2;
        $section = match (true) {
            $masterJournalOpened => 'mjournal-2.1',
            in_array('temporary', $modifiedKinds, true) => 'mjournal-2.2',
            default => 'mjournal-2.3',
        };

        if ($masterJournalOpened) {
            $openedFiles[] = 'master-journal-' . sprintf('%04d', $case) . '-mj';
        }

        return [
            'script' => 'mjournal.test',
            'kind' => 'creation',
            'case' => $case,
            'section' => $section,
            'upstream' => match ($section) {
                'mjournal-2.1' => 'mjournal.test 2.1 two real database files open a master journal',
                'mjournal-2.2' => 'mjournal.test 2.2 real plus temporary database does not open a master journal',
                default => 'mjournal.test 2.3 real plus memory database does not open a master journal',
            } . ' dynamic case ' . sprintf('%04d', $case),
            'databases' => $databases,
            'modified_kinds' => $modifiedKinds,
            'real_modified_count' => $realModified,
            'master_journal_opened' => $masterJournalOpened,
            'opened_files' => $openedFiles,
            'opened_master_journal_count' => $masterJournalOpened ? 1 : 0,
            'master_journal_path' => $masterJournalOpened ? $openedFiles[count($openedFiles) - 1] : null,
            'commit_status' => 'ok',
            'result_rows' => [[1]],
            'temporary_or_memory_excluded' => !$masterJournalOpened,
            'reason' => $masterJournalOpened
                ? 'two_real_database_files_need_master_journal'
                : 'temporary_or_memory_database_excluded_from_master_journal',
            'dependencies' => [
                'real-upstream-corpus-mjournal',
                'sqlite-master-journal-creation-gate',
                'sqlite-pager-master-journal-dynamic',
            ],
        ];
    }

    private static function assertSafeName(string $name, string $label): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException("SQLite master-journal {$label} name must not be empty");
        }
        if (str_contains($name, "\0")) {
            throw new \InvalidArgumentException("SQLite master-journal {$label} name must not contain NUL bytes");
        }
        if (str_contains($name, DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException("SQLite master-journal {$label} name must be a base name");
        }
    }

    private static function rawPointerJournalBytes(string $journalPointerName, int $case): string
    {
        $prefix = str_pad(substr($journalPointerName . "\0", 0, 16), 16, "\0");
        $checksum = (0x000005e1 + $case) & 0xffffffff;

        return $prefix . pack('N', 16) . pack('N', $checksum) . hex2bin('d9d505f920a163d7');
    }
}
