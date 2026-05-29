<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, table?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, $events, $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next92', [
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-attach-detach-search-order-cache-expiry',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext169172(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next169-172', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next169',
            'sqlite-attach-temp-wal-schema-cache-current-source-next170',
            'sqlite-attach-temp-wal-schema-cache-current-source-next171',
            'sqlite-attach-temp-wal-schema-cache-current-source-next172',
            'sqlite-attach-temp-wal-schema-cache-current-source-next165',
            'sqlite-attach-temp-wal-schema-cache-current-source-next166',
            'sqlite-attach-temp-wal-schema-cache-current-source-next167',
            'sqlite-attach-temp-wal-schema-cache-current-source-next168',
            'sqlite-attach-temp-wal-schema-cache-current-source-next161',
            'sqlite-attach-temp-wal-schema-cache-current-source-next162',
            'sqlite-attach-temp-wal-schema-cache-current-source-next163',
            'sqlite-attach-temp-wal-schema-cache-current-source-next164',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext173176(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next173-176', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next173',
            'sqlite-attach-temp-wal-schema-cache-current-source-next174',
            'sqlite-attach-temp-wal-schema-cache-current-source-next175',
            'sqlite-attach-temp-wal-schema-cache-current-source-next176',
            'sqlite-attach-temp-wal-schema-cache-current-source-next169',
            'sqlite-attach-temp-wal-schema-cache-current-source-next170',
            'sqlite-attach-temp-wal-schema-cache-current-source-next171',
            'sqlite-attach-temp-wal-schema-cache-current-source-next172',
            'sqlite-attach-temp-wal-schema-cache-current-source-next165',
            'sqlite-attach-temp-wal-schema-cache-current-source-next166',
            'sqlite-attach-temp-wal-schema-cache-current-source-next167',
            'sqlite-attach-temp-wal-schema-cache-current-source-next168',
            'sqlite-attach-temp-wal-schema-cache-current-source-next161',
            'sqlite-attach-temp-wal-schema-cache-current-source-next162',
            'sqlite-attach-temp-wal-schema-cache-current-source-next163',
            'sqlite-attach-temp-wal-schema-cache-current-source-next164',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext177180(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next177-180', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next177',
            'sqlite-attach-temp-wal-schema-cache-current-source-next178',
            'sqlite-attach-temp-wal-schema-cache-current-source-next179',
            'sqlite-attach-temp-wal-schema-cache-current-source-next180',
            'sqlite-attach-temp-wal-schema-cache-current-source-next173',
            'sqlite-attach-temp-wal-schema-cache-current-source-next174',
            'sqlite-attach-temp-wal-schema-cache-current-source-next175',
            'sqlite-attach-temp-wal-schema-cache-current-source-next176',
            'sqlite-attach-temp-wal-schema-cache-current-source-next169',
            'sqlite-attach-temp-wal-schema-cache-current-source-next170',
            'sqlite-attach-temp-wal-schema-cache-current-source-next171',
            'sqlite-attach-temp-wal-schema-cache-current-source-next172',
            'sqlite-attach-temp-wal-schema-cache-current-source-next165',
            'sqlite-attach-temp-wal-schema-cache-current-source-next166',
            'sqlite-attach-temp-wal-schema-cache-current-source-next167',
            'sqlite-attach-temp-wal-schema-cache-current-source-next168',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext181184(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next181-184', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next181',
            'sqlite-attach-temp-wal-schema-cache-current-source-next182',
            'sqlite-attach-temp-wal-schema-cache-current-source-next183',
            'sqlite-attach-temp-wal-schema-cache-current-source-next184',
            'sqlite-attach-temp-wal-schema-cache-current-source-next177',
            'sqlite-attach-temp-wal-schema-cache-current-source-next178',
            'sqlite-attach-temp-wal-schema-cache-current-source-next179',
            'sqlite-attach-temp-wal-schema-cache-current-source-next180',
            'sqlite-attach-temp-wal-schema-cache-current-source-next173',
            'sqlite-attach-temp-wal-schema-cache-current-source-next174',
            'sqlite-attach-temp-wal-schema-cache-current-source-next175',
            'sqlite-attach-temp-wal-schema-cache-current-source-next176',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext185188(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next185-188', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next185',
            'sqlite-attach-temp-wal-schema-cache-current-source-next186',
            'sqlite-attach-temp-wal-schema-cache-current-source-next187',
            'sqlite-attach-temp-wal-schema-cache-current-source-next188',
            'sqlite-attach-temp-wal-schema-cache-current-source-next181',
            'sqlite-attach-temp-wal-schema-cache-current-source-next182',
            'sqlite-attach-temp-wal-schema-cache-current-source-next183',
            'sqlite-attach-temp-wal-schema-cache-current-source-next184',
            'sqlite-attach-temp-wal-schema-cache-current-source-next177',
            'sqlite-attach-temp-wal-schema-cache-current-source-next178',
            'sqlite-attach-temp-wal-schema-cache-current-source-next179',
            'sqlite-attach-temp-wal-schema-cache-current-source-next180',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext189192(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next189-192', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next189',
            'sqlite-attach-temp-wal-schema-cache-current-source-next190',
            'sqlite-attach-temp-wal-schema-cache-current-source-next191',
            'sqlite-attach-temp-wal-schema-cache-current-source-next192',
            'sqlite-attach-temp-wal-schema-cache-current-source-next185',
            'sqlite-attach-temp-wal-schema-cache-current-source-next186',
            'sqlite-attach-temp-wal-schema-cache-current-source-next187',
            'sqlite-attach-temp-wal-schema-cache-current-source-next188',
            'sqlite-attach-temp-wal-schema-cache-current-source-next181',
            'sqlite-attach-temp-wal-schema-cache-current-source-next182',
            'sqlite-attach-temp-wal-schema-cache-current-source-next183',
            'sqlite-attach-temp-wal-schema-cache-current-source-next184',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext116(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, $events, $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next116', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext117(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::consolidateDuplicateEvents($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next117', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext118120(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next118-120', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext121124(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next121-124', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext125128(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next125-128', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext129132(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next129-132', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext133136(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next133-136', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext137140(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next137-140', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext141144(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next141-144', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext145148(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next145-148', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext149152(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next149-152', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next149',
            'sqlite-attach-temp-wal-schema-cache-current-source-next150',
            'sqlite-attach-temp-wal-schema-cache-current-source-next151',
            'sqlite-attach-temp-wal-schema-cache-current-source-next152',
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext153156(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next153-156', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next153',
            'sqlite-attach-temp-wal-schema-cache-current-source-next154',
            'sqlite-attach-temp-wal-schema-cache-current-source-next155',
            'sqlite-attach-temp-wal-schema-cache-current-source-next156',
            'sqlite-attach-temp-wal-schema-cache-current-source-next149',
            'sqlite-attach-temp-wal-schema-cache-current-source-next150',
            'sqlite-attach-temp-wal-schema-cache-current-source-next151',
            'sqlite-attach-temp-wal-schema-cache-current-source-next152',
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext157160(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next157-160', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next157',
            'sqlite-attach-temp-wal-schema-cache-current-source-next158',
            'sqlite-attach-temp-wal-schema-cache-current-source-next159',
            'sqlite-attach-temp-wal-schema-cache-current-source-next160',
            'sqlite-attach-temp-wal-schema-cache-current-source-next153',
            'sqlite-attach-temp-wal-schema-cache-current-source-next154',
            'sqlite-attach-temp-wal-schema-cache-current-source-next155',
            'sqlite-attach-temp-wal-schema-cache-current-source-next156',
            'sqlite-attach-temp-wal-schema-cache-current-source-next149',
            'sqlite-attach-temp-wal-schema-cache-current-source-next150',
            'sqlite-attach-temp-wal-schema-cache-current-source-next151',
            'sqlite-attach-temp-wal-schema-cache-current-source-next152',
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext161164(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next161-164', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next161',
            'sqlite-attach-temp-wal-schema-cache-current-source-next162',
            'sqlite-attach-temp-wal-schema-cache-current-source-next163',
            'sqlite-attach-temp-wal-schema-cache-current-source-next164',
            'sqlite-attach-temp-wal-schema-cache-current-source-next157',
            'sqlite-attach-temp-wal-schema-cache-current-source-next158',
            'sqlite-attach-temp-wal-schema-cache-current-source-next159',
            'sqlite-attach-temp-wal-schema-cache-current-source-next160',
            'sqlite-attach-temp-wal-schema-cache-current-source-next153',
            'sqlite-attach-temp-wal-schema-cache-current-source-next154',
            'sqlite-attach-temp-wal-schema-cache-current-source-next155',
            'sqlite-attach-temp-wal-schema-cache-current-source-next156',
            'sqlite-attach-temp-wal-schema-cache-current-source-next149',
            'sqlite-attach-temp-wal-schema-cache-current-source-next150',
            'sqlite-attach-temp-wal-schema-cache-current-source-next151',
            'sqlite-attach-temp-wal-schema-cache-current-source-next152',
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext165168(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next165-168', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next165',
            'sqlite-attach-temp-wal-schema-cache-current-source-next166',
            'sqlite-attach-temp-wal-schema-cache-current-source-next167',
            'sqlite-attach-temp-wal-schema-cache-current-source-next168',
            'sqlite-attach-temp-wal-schema-cache-current-source-next161',
            'sqlite-attach-temp-wal-schema-cache-current-source-next162',
            'sqlite-attach-temp-wal-schema-cache-current-source-next163',
            'sqlite-attach-temp-wal-schema-cache-current-source-next164',
            'sqlite-attach-temp-wal-schema-cache-current-source-next157',
            'sqlite-attach-temp-wal-schema-cache-current-source-next158',
            'sqlite-attach-temp-wal-schema-cache-current-source-next159',
            'sqlite-attach-temp-wal-schema-cache-current-source-next160',
            'sqlite-attach-temp-wal-schema-cache-current-source-next153',
            'sqlite-attach-temp-wal-schema-cache-current-source-next154',
            'sqlite-attach-temp-wal-schema-cache-current-source-next155',
            'sqlite-attach-temp-wal-schema-cache-current-source-next156',
            'sqlite-attach-temp-wal-schema-cache-current-source-next149',
            'sqlite-attach-temp-wal-schema-cache-current-source-next150',
            'sqlite-attach-temp-wal-schema-cache-current-source-next151',
            'sqlite-attach-temp-wal-schema-cache-current-source-next152',
            'sqlite-attach-temp-wal-schema-cache-current-source-next145',
            'sqlite-attach-temp-wal-schema-cache-current-source-next146',
            'sqlite-attach-temp-wal-schema-cache-current-source-next147',
            'sqlite-attach-temp-wal-schema-cache-current-source-next148',
            'sqlite-attach-temp-wal-schema-cache-current-source-next141',
            'sqlite-attach-temp-wal-schema-cache-current-source-next142',
            'sqlite-attach-temp-wal-schema-cache-current-source-next143',
            'sqlite-attach-temp-wal-schema-cache-current-source-next144',
            'sqlite-attach-temp-wal-schema-cache-current-source-next137',
            'sqlite-attach-temp-wal-schema-cache-current-source-next138',
            'sqlite-attach-temp-wal-schema-cache-current-source-next139',
            'sqlite-attach-temp-wal-schema-cache-current-source-next140',
            'sqlite-attach-temp-wal-schema-cache-current-source-next133',
            'sqlite-attach-temp-wal-schema-cache-current-source-next134',
            'sqlite-attach-temp-wal-schema-cache-current-source-next135',
            'sqlite-attach-temp-wal-schema-cache-current-source-next136',
            'sqlite-attach-temp-wal-schema-cache-current-source-next129',
            'sqlite-attach-temp-wal-schema-cache-current-source-next130',
            'sqlite-attach-temp-wal-schema-cache-current-source-next131',
            'sqlite-attach-temp-wal-schema-cache-current-source-next132',
            'sqlite-attach-temp-wal-schema-cache-current-source-next125',
            'sqlite-attach-temp-wal-schema-cache-current-source-next126',
            'sqlite-attach-temp-wal-schema-cache-current-source-next127',
            'sqlite-attach-temp-wal-schema-cache-current-source-next128',
            'sqlite-attach-temp-wal-schema-cache-current-source-next121',
            'sqlite-attach-temp-wal-schema-cache-current-source-next122',
            'sqlite-attach-temp-wal-schema-cache-current-source-next123',
            'sqlite-attach-temp-wal-schema-cache-current-source-next124',
            'sqlite-attach-temp-wal-schema-cache-current-source-next118',
            'sqlite-attach-temp-wal-schema-cache-current-source-next119',
            'sqlite-attach-temp-wal-schema-cache-current-source-next120',
            'sqlite-attach-temp-wal-schema-cache-current-source-next117',
            'sqlite-attach-temp-wal-schema-cache-current-source-next116',
            'sqlite-indexed-by-schema-cache-expiry',
            'sqlite-attach-wal-temp-schema-cache-current-source-next92',
            'sqlite-wal-page-one-schema-cookie-current-source',
            'sqlite-temp-schema-shadow-cache-expiry',
        ]);
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @param list<string> $dependencies
     * @return array<string,mixed>
     */
    private static function buildPlan(array $schemas, array $statements, array $events, string $sourceSchema, string $operation, array $dependencies): array
    {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp schema-cache current-source next92 requires statements');
        }

        $source = self::name($sourceSchema, 'SQLite source schema');
        $current = self::normalizeSchemas($schemas);
        if (!isset($current[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $currentOrder = self::searchOrder($current);
        $prepared = self::prepareStatements($current, $currentOrder, $statements);
        [$next, $eventLog] = self::applyEvents($current, $events);
        $nextOrder = self::searchOrder($next);

        $statementPlans = [];
        $expired = [];
        $stable = [];
        $active = [];
        $retryable = [];
        $writeBlocked = [];
        foreach ($prepared as $statement) {
            $transitions = [];
            $requiresReprepare = false;
            $nextSchemas = [];
            foreach ($statement['tables'] as $table) {
                $before = $statement['resolutions'][$table];
                $after = self::resolve($next, $nextOrder, $table);
                $beforeCookie = $current[$before['schema']]['schema_cookie'] ?? null;
                $afterCookie = $next[$after['schema']]['schema_cookie'] ?? null;
                $resolutionChanged = $before['schema'] !== $after['schema']
                    || $before['found'] !== $after['found']
                    || $before['name'] !== $after['name'];
                $cookieChanged = $beforeCookie !== $afterCookie;
                $changed = $resolutionChanged || $cookieChanged;
                $requiresReprepare = $requiresReprepare || $changed;
                if (!in_array($after['schema'], $nextSchemas, true)) {
                    $nextSchemas[] = $after['schema'];
                }
                $transitions[] = [
                    'table' => $table,
                    'current_schema' => $before['schema'],
                    'next_schema' => $after['schema'],
                    'current_found' => $before['found'],
                    'next_found' => $after['found'],
                    'current_schema_cookie' => $beforeCookie,
                    'next_schema_cookie' => $afterCookie,
                    'resolution_changed' => $resolutionChanged,
                    'schema_cookie_changed' => $cookieChanged,
                    'requires_reprepare' => $changed,
                ];
            }

            $indexTransitions = [];
            foreach ($statement['indexed_by'] as $table => $index) {
                $beforeTable = $statement['resolutions'][$table] ?? self::resolve($current, $currentOrder, $table);
                $afterTable = self::resolve($next, $nextOrder, $table);
                $beforeIndex = self::resolveIndex($current, $beforeTable['schema'], $index);
                $afterIndex = self::resolveIndex($next, $afterTable['schema'], $index);
                $indexChanged = $beforeIndex['schema'] !== $afterIndex['schema']
                    || $beforeIndex['found'] !== $afterIndex['found']
                    || $beforeIndex['name'] !== $afterIndex['name'];
                $requiresReprepare = $requiresReprepare || $indexChanged;
                $indexTransitions[] = [
                    'table' => $table,
                    'index' => $index,
                    'current_schema' => $beforeIndex['schema'],
                    'next_schema' => $afterIndex['schema'],
                    'current_found' => $beforeIndex['found'],
                    'next_found' => $afterIndex['found'],
                    'resolution_changed' => $indexChanged,
                    'requires_reprepare' => $indexChanged,
                ];
            }

            $name = $statement['name'];
            if ($requiresReprepare) {
                $expired[] = $name;
                if ($statement['active']) {
                    $active[] = $name;
                }
                if ($statement['read_only']) {
                    $retryable[] = $name;
                } else {
                    $writeBlocked[] = $name;
                }
            } else {
                $stable[] = $name;
            }

            $statementPlans[$name] = [
                'name' => $name,
                'sql' => $statement['sql'],
                'active' => $statement['active'],
                'read_only' => $statement['read_only'],
                'tables' => $statement['tables'],
                'indexed_by' => $statement['indexed_by'],
                'current_schemas' => $statement['schemas'],
                'next_schemas' => $nextSchemas,
                'schema_transitions' => $transitions,
                'index_transitions' => $indexTransitions,
                'requires_reprepare' => $requiresReprepare,
                'sqlite_result_on_current_step' => $statement['active'] ? 'SQLITE_OK' : ($requiresReprepare ? 'SQLITE_SCHEMA' : 'SQLITE_OK'),
                'next_step_action' => self::action($statement['active'], $statement['read_only'], $requiresReprepare),
            ];
        }

        $currentCookies = self::cookies($current);
        $nextCookies = self::cookies($next);
        $changedSchemas = self::changedSchemas($currentCookies, $nextCookies, $current, $next);

        return [
            'status' => $expired === [] ? 'schema_cache_stable' : 'schema_cache_expired',
            'operation' => $operation,
            'source' => $source,
            'event_count' => count($events),
            'statement_count' => count($statementPlans),
            'search_order_current' => $currentOrder,
            'search_order_next' => $nextOrder,
            'schema_cookies_current' => $currentCookies,
            'schema_cookies_next' => $nextCookies,
            'changed_schemas' => $changedSchemas,
            'events' => $eventLog,
            'statements' => $statementPlans,
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'active_current_snapshot_statements' => $active,
            'retryable_read_statements' => $retryable,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'requires_reprepare' => $expired !== [],
            'dependencies' => $dependencies,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{schema_cookie:int,tables:list<string>,indexes:list<string>,file:string|null,temp:bool}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::name((string) $schema, 'SQLite schema');
            $tables = [];
            foreach (($entry['tables'] ?? []) as $table) {
                $tables[] = self::name((string) $table, 'SQLite table');
            }
            sort($tables);
            $indexes = [];
            foreach (($entry['indexes'] ?? []) as $index) {
                $indexes[] = self::name((string) $index, 'SQLite index');
            }
            sort($indexes);
            $normalized[$name] = [
                'schema_cookie' => self::currentCookie($entry),
                'tables' => array_values(array_unique($tables)),
                'indexes' => array_values(array_unique($indexes)),
                'file' => isset($entry['file']) ? (string) $entry['file'] : null,
                'temp' => (bool) ($entry['temp'] ?? $name === 'temp'),
            ];
        }

        foreach (['main', 'temp'] as $schema) {
            $normalized[$schema] ??= [
                'schema_cookie' => 0,
                'tables' => [],
                'indexes' => [],
                'file' => $schema === 'temp' ? '' : null,
                'temp' => $schema === 'temp',
            ];
        }

        uksort($normalized, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return $normalized;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function currentCookie(array $entry): int
    {
        if (!isset($entry['schema_cookie']) || !is_int($entry['schema_cookie'])) {
            throw new \InvalidArgumentException('SQLite schema requires an integer schema cookie');
        }
        $cookie = $entry['schema_cookie'];
        if (isset($entry['wal_schema_cookie'])) {
            if (!is_int($entry['wal_schema_cookie'])) {
                throw new \InvalidArgumentException('SQLite WAL schema cookie must be an integer');
            }
            $cookie = $entry['wal_schema_cookie'];
        }
        foreach (($entry['wal_frames'] ?? []) as $frame) {
            if (($frame['page'] ?? null) === 1 && ($frame['commit'] ?? false) === true && isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                $cookie = $frame['schema_cookie'];
            }
        }

        return $cookie;
    }

    /**
     * @param array<string,array{temp:bool}> $schemas
     * @return list<string>
     */
    private static function searchOrder(array $schemas): array
    {
        $order = [];
        if (isset($schemas['temp'])) {
            $order[] = 'temp';
        }
        if (isset($schemas['main'])) {
            $order[] = 'main';
        }
        foreach ($schemas as $schema => $_entry) {
            if ($schema !== 'temp' && $schema !== 'main') {
                $order[] = $schema;
            }
        }

        return $order;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @param list<string> $order
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @return list<array{name:string,sql:string,active:bool,read_only:bool,tables:list<string>,indexed_by:array<string,string>,schemas:list<string>,resolutions:array<string,array{schema:string,name:string,found:bool}>}>
     */
    private static function prepareStatements(array $schemas, array $order, array $statements): array
    {
        $prepared = [];
        foreach ($statements as $index => $statement) {
            $sql = trim((string) ($statement['sql'] ?? ''));
            if ($sql === '') {
                throw new \InvalidArgumentException('SQLite prepared statement SQL cannot be empty');
            }
            $tables = self::tables($sql);
            $indexedBy = self::indexedBy($sql);
            $resolutions = [];
            $schemasRead = [];
            foreach ($tables as $table) {
                $resolution = self::resolve($schemas, $order, $table);
                $resolutions[$table] = $resolution;
                if (!in_array($resolution['schema'], $schemasRead, true)) {
                    $schemasRead[] = $resolution['schema'];
                }
            }
            $prepared[] = [
                'name' => isset($statement['name']) && trim((string) $statement['name']) !== '' ? (string) $statement['name'] : 'stmt-' . $index,
                'sql' => $sql,
                'active' => (bool) ($statement['active'] ?? false),
                'read_only' => (bool) ($statement['read_only'] ?? self::readOnly($sql)),
                'tables' => $tables,
                'indexed_by' => $indexedBy,
                'schemas' => $schemasRead,
                'resolutions' => $resolutions,
            ];
        }

        return $prepared;
    }

    /**
     * @return list<string>
     */
    private static function tables(string $sql): array
    {
        $tables = [];
        if (preg_match_all('/\b(?:from|join|update|into|table)\s+((?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)/i', $sql, $matches)) {
            foreach ($matches[1] as $raw) {
                $table = self::compoundName($raw);
                if (!in_array($table, $tables, true)) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * @return array<string,string>
     */
    private static function indexedBy(string $sql): array
    {
        $indexed = [];
        if (preg_match('/\bindexed\s+by\s+\[\s*\]/i', $sql) === 1) {
            throw new \InvalidArgumentException('SQLite INDEXED BY index cannot be empty');
        }
        $identifier = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $tablePattern = '(' . $identifier . '(?:\s*\.\s*' . $identifier . ')?)';
        $aliasPattern = '(?:(?:as\s+)?(?!(?:indexed|not)\b)' . $identifier . '\s+)?';
        $patterns = [
            '/\b(?:from|join|update)\s+' . $tablePattern . '\s+' . $aliasPattern . 'indexed\s+by\s+(' . $identifier . ')/i',
            '/\bdelete\s+from\s+' . $tablePattern . '\s+' . $aliasPattern . 'indexed\s+by\s+(' . $identifier . ')/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                $indexed[self::compoundName($match[1])] = self::name($match[2], 'SQLite INDEXED BY index');
            }
        }

        return $indexed;
    }

    private static function compoundName(string $raw): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($raw));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }
        $names = array_map(static fn (string $part): string => self::name($part, 'SQLite table name'), $parts);
        if (count($names) > 2) {
            throw new \InvalidArgumentException('SQLite table name has too many qualifiers');
        }

        return implode('.', $names);
    }

    /**
     * @param array<string,array{tables:list<string>}> $schemas
     * @param list<string> $order
     * @return array{schema:string,name:string,found:bool}
     */
    private static function resolve(array $schemas, array $order, string $table): array
    {
        if (str_contains($table, '.')) {
            [$schema, $name] = explode('.', $table, 2);
            return [
                'schema' => isset($schemas[$schema]) ? $schema : '__detached__',
                'name' => $name,
                'found' => isset($schemas[$schema]) && in_array($name, $schemas[$schema]['tables'], true),
            ];
        }

        foreach ($order as $schema) {
            if (isset($schemas[$schema]) && in_array($table, $schemas[$schema]['tables'], true)) {
                return ['schema' => $schema, 'name' => $table, 'found' => true];
            }
        }

        return ['schema' => 'main', 'name' => $table, 'found' => false];
    }

    /**
     * @param array<string,array{indexes:list<string>}> $schemas
     * @return array{schema:string,name:string,found:bool}
     */
    private static function resolveIndex(array $schemas, string $schema, string $index): array
    {
        if ($schema === '__detached__' || !isset($schemas[$schema])) {
            return ['schema' => '__detached__', 'name' => $index, 'found' => false];
        }

        return [
            'schema' => $schema,
            'name' => $index,
            'found' => in_array($index, $schemas[$schema]['indexes'], true),
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $current
     * @param list<array<string,mixed>> $events
     * @return array{0:array<string,array{schema_cookie:int,tables:list<string>,indexes:list<string>,file:string|null,temp:bool}>,1:list<array<string,mixed>>}
     */
    private static function applyEvents(array $current, array $events): array
    {
        $next = $current;
        $log = [];
        foreach ($events as $index => $event) {
            $op = strtolower(trim((string) ($event['op'] ?? '')));
            if ($op === 'attach') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite ATTACH schema');
                if ($schema === 'main' || $schema === 'temp' || isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} cannot be attached");
                }
                $tables = [];
                foreach (($event['tables'] ?? []) as $table) {
                    $tables[] = self::name((string) $table, 'SQLite attached table');
                }
                sort($tables);
                $next[$schema] = [
                    'schema_cookie' => isset($event['schema_cookie']) ? self::integer($event['schema_cookie'], 'SQLite ATTACH schema cookie') : 1,
                    'tables' => array_values(array_unique($tables)),
                    'indexes' => array_values(array_map(
                        static fn (string $index): string => self::name($index, 'SQLite attached index'),
                        $event['indexes'] ?? [],
                    )),
                    'file' => isset($event['file']) ? (string) $event['file'] : null,
                    'temp' => false,
                ];
                sort($next[$schema]['indexes']);
                $log[] = ['index' => $index, 'op' => 'attach', 'schema' => $schema, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'detach') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite DETACH schema');
                if ($schema === 'main' || $schema === 'temp' || !isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} cannot be detached");
                }
                unset($next[$schema]);
                $log[] = ['index' => $index, 'op' => 'detach', 'schema' => $schema, 'schema_cookie' => null];
                continue;
            }

            if ($op === 'schema_write' || $op === 'wal_commit') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite schema write target');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                if (($event['commit'] ?? true) === true) {
                    $next[$schema]['schema_cookie'] = isset($event['schema_cookie'])
                        ? self::integer($event['schema_cookie'], 'SQLite schema cookie')
                        : $next[$schema]['schema_cookie'] + 1;
                    $table = $event['table'] ?? $event['object'] ?? null;
                    if (is_string($table) && trim($table) !== '') {
                        $name = self::name($table, 'SQLite schema object');
                        if (!in_array($name, $next[$schema]['tables'], true)) {
                            $next[$schema]['tables'][] = $name;
                            sort($next[$schema]['tables']);
                        }
                    }
                    foreach (($event['indexes'] ?? []) as $indexName) {
                        $normalizedIndex = self::name((string) $indexName, 'SQLite schema index');
                        if (!in_array($normalizedIndex, $next[$schema]['indexes'], true)) {
                            $next[$schema]['indexes'][] = $normalizedIndex;
                            sort($next[$schema]['indexes']);
                        }
                    }
                }
                $log[] = ['index' => $index, 'op' => $op, 'schema' => $schema, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'create_index') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite CREATE INDEX schema');
                $indexName = self::name((string) ($event['index'] ?? $event['object'] ?? ''), 'SQLite CREATE INDEX name');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                if (!in_array($indexName, $next[$schema]['indexes'], true)) {
                    $next[$schema]['indexes'][] = $indexName;
                    sort($next[$schema]['indexes']);
                }
                $log[] = ['index' => $index, 'op' => 'create_index', 'schema' => $schema, 'index_name' => $indexName, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'drop_index') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite DROP INDEX schema');
                $indexName = self::name((string) ($event['index'] ?? $event['object'] ?? ''), 'SQLite DROP INDEX name');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                $next[$schema]['indexes'] = array_values(array_filter(
                    $next[$schema]['indexes'],
                    static fn (string $existing): bool => $existing !== $indexName,
                ));
                $log[] = ['index' => $index, 'op' => 'drop_index', 'schema' => $schema, 'index_name' => $indexName, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'drop_table') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite DROP TABLE schema');
                $table = self::name((string) ($event['table'] ?? $event['object'] ?? ''), 'SQLite DROP TABLE name');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                $next[$schema]['tables'] = array_values(array_filter(
                    $next[$schema]['tables'],
                    static fn (string $existing): bool => $existing !== $table,
                ));
                $log[] = ['index' => $index, 'op' => 'drop_table', 'schema' => $schema, 'table' => $table, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'rename_table') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite RENAME TABLE schema');
                $from = self::name((string) ($event['from'] ?? $event['table'] ?? $event['object'] ?? ''), 'SQLite RENAME TABLE source');
                $to = self::name((string) ($event['to'] ?? ''), 'SQLite RENAME TABLE target');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                $next[$schema]['tables'] = array_values(array_filter(
                    $next[$schema]['tables'],
                    static fn (string $existing): bool => $existing !== $from,
                ));
                if (!in_array($to, $next[$schema]['tables'], true)) {
                    $next[$schema]['tables'][] = $to;
                    sort($next[$schema]['tables']);
                }
                $log[] = ['index' => $index, 'op' => 'rename_table', 'schema' => $schema, 'from' => $from, 'to' => $to, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'rename_index') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite RENAME INDEX schema');
                $from = self::name((string) ($event['from'] ?? $event['index'] ?? $event['object'] ?? ''), 'SQLite RENAME INDEX source');
                $to = self::name((string) ($event['to'] ?? ''), 'SQLite RENAME INDEX target');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                $next[$schema]['indexes'] = array_values(array_filter(
                    $next[$schema]['indexes'],
                    static fn (string $existing): bool => $existing !== $from,
                ));
                if (!in_array($to, $next[$schema]['indexes'], true)) {
                    $next[$schema]['indexes'][] = $to;
                    sort($next[$schema]['indexes']);
                }
                $log[] = ['index' => $index, 'op' => 'rename_index', 'schema' => $schema, 'from' => $from, 'to' => $to, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            throw new \InvalidArgumentException("SQLite attach WAL temp schema-cache next92 event {$op} is not supported");
        }

        uksort($next, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return [$next, $log];
    }

    /**
     * @param list<array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    private static function consolidateDuplicateEvents(array $events): array
    {
        $seen = [];
        $consolidated = [];
        foreach ($events as $event) {
            $key = self::eventConsolidationKey($event);
            if ($key !== null && isset($seen[$key])) {
                continue;
            }
            if ($key !== null) {
                $seen[$key] = true;
            }
            $consolidated[] = $event;
        }

        return $consolidated;
    }

    /**
     * @param list<array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    private static function currentSourceNext118120Events(array $events): array
    {
        $committed = [];
        foreach ($events as $event) {
            $op = strtolower(trim((string) ($event['op'] ?? '')));
            if (($op === 'schema_write' || $op === 'wal_commit') && ($event['commit'] ?? true) !== true) {
                continue;
            }
            $committed[] = $event;
        }

        return self::consolidateDuplicateEvents($committed);
    }

    /**
     * @param array<string,mixed> $event
     */
    private static function eventConsolidationKey(array $event): ?string
    {
        $op = strtolower(trim((string) ($event['op'] ?? '')));
        if (!in_array($op, ['schema_write', 'wal_commit', 'create_index', 'drop_index', 'drop_table', 'rename_table', 'rename_index'], true)) {
            return null;
        }

        $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite schema event target');
        $object = match ($op) {
            'create_index', 'drop_index' => self::name((string) ($event['index'] ?? $event['object'] ?? ''), 'SQLite index event object'),
            'drop_table' => self::name((string) ($event['table'] ?? $event['object'] ?? ''), 'SQLite table event object'),
            'rename_table' => self::name((string) ($event['from'] ?? $event['table'] ?? $event['object'] ?? ''), 'SQLite table event object') . '>' . self::name((string) ($event['to'] ?? ''), 'SQLite table event target'),
            'rename_index' => self::name((string) ($event['from'] ?? $event['index'] ?? $event['object'] ?? ''), 'SQLite index event object') . '>' . self::name((string) ($event['to'] ?? ''), 'SQLite index event target'),
            default => self::schemaWriteObject($event),
        };

        return $op . ':' . $schema . ':' . $object;
    }

    /**
     * @param array<string,mixed> $event
     */
    private static function schemaWriteObject(array $event): string
    {
        $object = $event['table'] ?? $event['object'] ?? null;
        if (is_string($object) && trim($object) !== '') {
            return self::name($object, 'SQLite schema write object');
        }

        $indexes = [];
        foreach (($event['indexes'] ?? []) as $index) {
            $indexes[] = self::name((string) $index, 'SQLite schema write index');
        }
        sort($indexes);

        return implode(',', $indexes);
    }

    /**
     * @param array<string,array{schema_cookie:int}> $schemas
     * @return array<string,int>
     */
    private static function cookies(array $schemas): array
    {
        $cookies = [];
        foreach ($schemas as $schema => $entry) {
            $cookies[$schema] = $entry['schema_cookie'];
        }

        return $cookies;
    }

    /**
     * @param array<string,int> $currentCookies
     * @param array<string,int> $nextCookies
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<string>
     */
    private static function changedSchemas(array $currentCookies, array $nextCookies, array $current, array $next): array
    {
        $schemas = array_values(array_unique(array_merge(array_keys($currentCookies), array_keys($nextCookies))));
        $changed = [];
        foreach ($schemas as $schema) {
            if (($currentCookies[$schema] ?? null) !== ($nextCookies[$schema] ?? null) || !array_key_exists($schema, $current) || !array_key_exists($schema, $next)) {
                $changed[] = $schema;
            }
        }
        usort($changed, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return $changed;
    }

    private static function action(bool $active, bool $readOnly, bool $requiresReprepare): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement_current_source';
        }
        if ($active) {
            return 'finish_current_source_then_sqlite_schema_on_reset';
        }
        if ($readOnly) {
            return 'sqlite_schema_then_reprepare_read_statement';
        }

        return 'sqlite_schema_before_write_retry';
    }

    private static function readOnly(string $sql): bool
    {
        return preg_match('/^\s*(?:select|with|pragma)\b/i', $sql) === 1;
    }

    private static function integer(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("{$label} must be an integer");
        }

        return $value;
    }

    private static function name(string $name, string $label): string
    {
        $trimmed = trim($name);
        if (preg_match('/^(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|\'([^\']+)\')$/', $trimmed, $match) === 1) {
            $trimmed = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]));
        }
        $normalized = strtolower(trim($trimmed));
        if ($normalized === '') {
            throw new \InvalidArgumentException("{$label} cannot be empty");
        }

        return $normalized;
    }
}
