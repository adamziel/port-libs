<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaIntegrityFreelistForeignKeyPreflight
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{
     *   status:string,
     *   integrity:array{pragma:string,limit:int,rows:list<array<string,string>>,errors:list<string>},
     *   freelist:array{page_count:int,freelist_count:int,first_trunk:int,auto_vacuum:string,integrity_errors:list<string>,status:string},
     *   foreign_keys:array{status:string,pragma:string,schemas:list<string>,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>},
     *   current:array{integrity_errors:int,foreign_key_violations:int,freelist_count:int},
     *   next:array{ready:bool,blocking:list<string>}
     * }
     */
    public static function plan(string $integritySql, string|SQLiteDatabase $database, array $schemas): array
    {
        $sqlite = is_string($database) ? SQLiteDatabase::fromBytes($database) : $database;
        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $sqlite);
        $snapshot = SQLitePragmaSnapshot::fromDatabase($sqlite)->toArray(['page_count', 'freelist_count', 'auto_vacuum']);
        $foreignKeys = SQLitePragmaForeignKeyIntegrity::executeAllSchemas($schemas);
        $freelistErrors = array_values(array_filter(
            $integrity['errors'],
            static fn (string $error): bool => str_contains($error, 'freelist') || str_contains($error, 'free-page') || str_contains($error, 'pointer-map type')
        ));
        $blocking = [];
        if ($integrity['errors'] !== []) {
            $blocking[] = 'integrity_check';
        }
        if ($foreignKeys['rows'] !== []) {
            $blocking[] = 'foreign_key_check';
        }

        $freelistCount = (int) ($snapshot['freelist_count'] ?? 0);

        return [
            'status' => $blocking === [] ? 'ready' : 'blocked',
            'integrity' => $integrity,
            'freelist' => [
                'page_count' => (int) ($snapshot['page_count'] ?? $sqlite->pageCount()),
                'freelist_count' => $freelistCount,
                'first_trunk' => $sqlite->header->firstFreelistTrunkPage,
                'auto_vacuum' => (string) ($snapshot['auto_vacuum'] ?? 'none'),
                'integrity_errors' => $freelistErrors,
                'status' => $freelistErrors === [] ? 'ok' : 'blocked',
            ],
            'foreign_keys' => $foreignKeys,
            'current' => [
                'integrity_errors' => count($integrity['errors']),
                'foreign_key_violations' => count($foreignKeys['rows']),
                'freelist_count' => $freelistCount,
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
        ];
    }
}
