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
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, indexes?:list<string>, table?:string, index?:string, object?:string, from?:string, to?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext193196(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next193-196', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next193',
            'sqlite-attach-temp-wal-schema-cache-current-source-next194',
            'sqlite-attach-temp-wal-schema-cache-current-source-next195',
            'sqlite-attach-temp-wal-schema-cache-current-source-next196',
            'sqlite-attach-temp-wal-schema-cache-current-source-next189',
            'sqlite-attach-temp-wal-schema-cache-current-source-next190',
            'sqlite-attach-temp-wal-schema-cache-current-source-next191',
            'sqlite-attach-temp-wal-schema-cache-current-source-next192',
            'sqlite-attach-temp-wal-schema-cache-current-source-next185',
            'sqlite-attach-temp-wal-schema-cache-current-source-next186',
            'sqlite-attach-temp-wal-schema-cache-current-source-next187',
            'sqlite-attach-temp-wal-schema-cache-current-source-next188',
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
    public static function currentSourceNext197200(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next197-200', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next197',
            'sqlite-attach-temp-wal-schema-cache-current-source-next198',
            'sqlite-attach-temp-wal-schema-cache-current-source-next199',
            'sqlite-attach-temp-wal-schema-cache-current-source-next200',
            'sqlite-attach-temp-wal-schema-cache-current-source-next193',
            'sqlite-attach-temp-wal-schema-cache-current-source-next194',
            'sqlite-attach-temp-wal-schema-cache-current-source-next195',
            'sqlite-attach-temp-wal-schema-cache-current-source-next196',
            'sqlite-attach-temp-wal-schema-cache-current-source-next189',
            'sqlite-attach-temp-wal-schema-cache-current-source-next190',
            'sqlite-attach-temp-wal-schema-cache-current-source-next191',
            'sqlite-attach-temp-wal-schema-cache-current-source-next192',
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
    public static function currentSourceNext201204(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next201-204', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next201',
            'sqlite-attach-temp-wal-schema-cache-current-source-next202',
            'sqlite-attach-temp-wal-schema-cache-current-source-next203',
            'sqlite-attach-temp-wal-schema-cache-current-source-next204',
            'sqlite-attach-temp-wal-schema-cache-current-source-next197',
            'sqlite-attach-temp-wal-schema-cache-current-source-next198',
            'sqlite-attach-temp-wal-schema-cache-current-source-next199',
            'sqlite-attach-temp-wal-schema-cache-current-source-next200',
            'sqlite-attach-temp-wal-schema-cache-current-source-next193',
            'sqlite-attach-temp-wal-schema-cache-current-source-next194',
            'sqlite-attach-temp-wal-schema-cache-current-source-next195',
            'sqlite-attach-temp-wal-schema-cache-current-source-next196',
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
    public static function currentSourceNext205208(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next205-208', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next205',
            'sqlite-attach-temp-wal-schema-cache-current-source-next206',
            'sqlite-attach-temp-wal-schema-cache-current-source-next207',
            'sqlite-attach-temp-wal-schema-cache-current-source-next208',
            'sqlite-attach-temp-wal-schema-cache-current-source-next201',
            'sqlite-attach-temp-wal-schema-cache-current-source-next202',
            'sqlite-attach-temp-wal-schema-cache-current-source-next203',
            'sqlite-attach-temp-wal-schema-cache-current-source-next204',
            'sqlite-attach-temp-wal-schema-cache-current-source-next197',
            'sqlite-attach-temp-wal-schema-cache-current-source-next198',
            'sqlite-attach-temp-wal-schema-cache-current-source-next199',
            'sqlite-attach-temp-wal-schema-cache-current-source-next200',
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
    public static function currentSourceNext209212(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next209-212', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next209',
            'sqlite-attach-temp-wal-schema-cache-current-source-next210',
            'sqlite-attach-temp-wal-schema-cache-current-source-next211',
            'sqlite-attach-temp-wal-schema-cache-current-source-next212',
            'sqlite-attach-temp-wal-schema-cache-current-source-next205',
            'sqlite-attach-temp-wal-schema-cache-current-source-next206',
            'sqlite-attach-temp-wal-schema-cache-current-source-next207',
            'sqlite-attach-temp-wal-schema-cache-current-source-next208',
            'sqlite-attach-temp-wal-schema-cache-current-source-next201',
            'sqlite-attach-temp-wal-schema-cache-current-source-next202',
            'sqlite-attach-temp-wal-schema-cache-current-source-next203',
            'sqlite-attach-temp-wal-schema-cache-current-source-next204',
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
    public static function currentSourceNext213220(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next213-220', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next213',
            'sqlite-attach-temp-wal-schema-cache-current-source-next214',
            'sqlite-attach-temp-wal-schema-cache-current-source-next215',
            'sqlite-attach-temp-wal-schema-cache-current-source-next216',
            'sqlite-attach-temp-wal-schema-cache-current-source-next217',
            'sqlite-attach-temp-wal-schema-cache-current-source-next218',
            'sqlite-attach-temp-wal-schema-cache-current-source-next219',
            'sqlite-attach-temp-wal-schema-cache-current-source-next220',
            'sqlite-attach-temp-wal-schema-cache-current-source-next209',
            'sqlite-attach-temp-wal-schema-cache-current-source-next210',
            'sqlite-attach-temp-wal-schema-cache-current-source-next211',
            'sqlite-attach-temp-wal-schema-cache-current-source-next212',
            'sqlite-attach-temp-wal-schema-cache-current-source-next205',
            'sqlite-attach-temp-wal-schema-cache-current-source-next206',
            'sqlite-attach-temp-wal-schema-cache-current-source-next207',
            'sqlite-attach-temp-wal-schema-cache-current-source-next208',
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
    public static function currentSourceNext221228(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next221-228', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next221',
            'sqlite-attach-temp-wal-schema-cache-current-source-next222',
            'sqlite-attach-temp-wal-schema-cache-current-source-next223',
            'sqlite-attach-temp-wal-schema-cache-current-source-next224',
            'sqlite-attach-temp-wal-schema-cache-current-source-next225',
            'sqlite-attach-temp-wal-schema-cache-current-source-next226',
            'sqlite-attach-temp-wal-schema-cache-current-source-next227',
            'sqlite-attach-temp-wal-schema-cache-current-source-next228',
            'sqlite-attach-temp-wal-schema-cache-current-source-next213',
            'sqlite-attach-temp-wal-schema-cache-current-source-next214',
            'sqlite-attach-temp-wal-schema-cache-current-source-next215',
            'sqlite-attach-temp-wal-schema-cache-current-source-next216',
            'sqlite-attach-temp-wal-schema-cache-current-source-next217',
            'sqlite-attach-temp-wal-schema-cache-current-source-next218',
            'sqlite-attach-temp-wal-schema-cache-current-source-next219',
            'sqlite-attach-temp-wal-schema-cache-current-source-next220',
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
    public static function currentSourceNext229236(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next229-236', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next229',
            'sqlite-attach-temp-wal-schema-cache-current-source-next230',
            'sqlite-attach-temp-wal-schema-cache-current-source-next231',
            'sqlite-attach-temp-wal-schema-cache-current-source-next232',
            'sqlite-attach-temp-wal-schema-cache-current-source-next233',
            'sqlite-attach-temp-wal-schema-cache-current-source-next234',
            'sqlite-attach-temp-wal-schema-cache-current-source-next235',
            'sqlite-attach-temp-wal-schema-cache-current-source-next236',
            'sqlite-attach-temp-wal-schema-cache-current-source-next221',
            'sqlite-attach-temp-wal-schema-cache-current-source-next222',
            'sqlite-attach-temp-wal-schema-cache-current-source-next223',
            'sqlite-attach-temp-wal-schema-cache-current-source-next224',
            'sqlite-attach-temp-wal-schema-cache-current-source-next225',
            'sqlite-attach-temp-wal-schema-cache-current-source-next226',
            'sqlite-attach-temp-wal-schema-cache-current-source-next227',
            'sqlite-attach-temp-wal-schema-cache-current-source-next228',
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
    public static function currentSourceNext237244(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next237-244', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next237',
            'sqlite-attach-temp-wal-schema-cache-current-source-next238',
            'sqlite-attach-temp-wal-schema-cache-current-source-next239',
            'sqlite-attach-temp-wal-schema-cache-current-source-next240',
            'sqlite-attach-temp-wal-schema-cache-current-source-next241',
            'sqlite-attach-temp-wal-schema-cache-current-source-next242',
            'sqlite-attach-temp-wal-schema-cache-current-source-next243',
            'sqlite-attach-temp-wal-schema-cache-current-source-next244',
            'sqlite-attach-temp-wal-schema-cache-current-source-next229',
            'sqlite-attach-temp-wal-schema-cache-current-source-next230',
            'sqlite-attach-temp-wal-schema-cache-current-source-next231',
            'sqlite-attach-temp-wal-schema-cache-current-source-next232',
            'sqlite-attach-temp-wal-schema-cache-current-source-next233',
            'sqlite-attach-temp-wal-schema-cache-current-source-next234',
            'sqlite-attach-temp-wal-schema-cache-current-source-next235',
            'sqlite-attach-temp-wal-schema-cache-current-source-next236',
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
    public static function currentSourceNext245252(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next245-252', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next245',
            'sqlite-attach-temp-wal-schema-cache-current-source-next246',
            'sqlite-attach-temp-wal-schema-cache-current-source-next247',
            'sqlite-attach-temp-wal-schema-cache-current-source-next248',
            'sqlite-attach-temp-wal-schema-cache-current-source-next249',
            'sqlite-attach-temp-wal-schema-cache-current-source-next250',
            'sqlite-attach-temp-wal-schema-cache-current-source-next251',
            'sqlite-attach-temp-wal-schema-cache-current-source-next252',
            'sqlite-attach-temp-wal-schema-cache-current-source-next237',
            'sqlite-attach-temp-wal-schema-cache-current-source-next238',
            'sqlite-attach-temp-wal-schema-cache-current-source-next239',
            'sqlite-attach-temp-wal-schema-cache-current-source-next240',
            'sqlite-attach-temp-wal-schema-cache-current-source-next241',
            'sqlite-attach-temp-wal-schema-cache-current-source-next242',
            'sqlite-attach-temp-wal-schema-cache-current-source-next243',
            'sqlite-attach-temp-wal-schema-cache-current-source-next244',
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
    public static function currentSourceNext253260(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next253-260', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next253',
            'sqlite-attach-temp-wal-schema-cache-current-source-next254',
            'sqlite-attach-temp-wal-schema-cache-current-source-next255',
            'sqlite-attach-temp-wal-schema-cache-current-source-next256',
            'sqlite-attach-temp-wal-schema-cache-current-source-next257',
            'sqlite-attach-temp-wal-schema-cache-current-source-next258',
            'sqlite-attach-temp-wal-schema-cache-current-source-next259',
            'sqlite-attach-temp-wal-schema-cache-current-source-next260',
            'sqlite-attach-temp-wal-schema-cache-current-source-next245',
            'sqlite-attach-temp-wal-schema-cache-current-source-next246',
            'sqlite-attach-temp-wal-schema-cache-current-source-next247',
            'sqlite-attach-temp-wal-schema-cache-current-source-next248',
            'sqlite-attach-temp-wal-schema-cache-current-source-next249',
            'sqlite-attach-temp-wal-schema-cache-current-source-next250',
            'sqlite-attach-temp-wal-schema-cache-current-source-next251',
            'sqlite-attach-temp-wal-schema-cache-current-source-next252',
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
    public static function currentSourceNext261268(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next261-268', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next261',
            'sqlite-attach-temp-wal-schema-cache-current-source-next262',
            'sqlite-attach-temp-wal-schema-cache-current-source-next263',
            'sqlite-attach-temp-wal-schema-cache-current-source-next264',
            'sqlite-attach-temp-wal-schema-cache-current-source-next265',
            'sqlite-attach-temp-wal-schema-cache-current-source-next266',
            'sqlite-attach-temp-wal-schema-cache-current-source-next267',
            'sqlite-attach-temp-wal-schema-cache-current-source-next268',
            'sqlite-attach-temp-wal-schema-cache-current-source-next253',
            'sqlite-attach-temp-wal-schema-cache-current-source-next254',
            'sqlite-attach-temp-wal-schema-cache-current-source-next255',
            'sqlite-attach-temp-wal-schema-cache-current-source-next256',
            'sqlite-attach-temp-wal-schema-cache-current-source-next257',
            'sqlite-attach-temp-wal-schema-cache-current-source-next258',
            'sqlite-attach-temp-wal-schema-cache-current-source-next259',
            'sqlite-attach-temp-wal-schema-cache-current-source-next260',
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
    public static function currentSourceNext269284(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next269-284', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next269',
            'sqlite-attach-temp-wal-schema-cache-current-source-next270',
            'sqlite-attach-temp-wal-schema-cache-current-source-next271',
            'sqlite-attach-temp-wal-schema-cache-current-source-next272',
            'sqlite-attach-temp-wal-schema-cache-current-source-next273',
            'sqlite-attach-temp-wal-schema-cache-current-source-next274',
            'sqlite-attach-temp-wal-schema-cache-current-source-next275',
            'sqlite-attach-temp-wal-schema-cache-current-source-next276',
            'sqlite-attach-temp-wal-schema-cache-current-source-next277',
            'sqlite-attach-temp-wal-schema-cache-current-source-next278',
            'sqlite-attach-temp-wal-schema-cache-current-source-next279',
            'sqlite-attach-temp-wal-schema-cache-current-source-next280',
            'sqlite-attach-temp-wal-schema-cache-current-source-next281',
            'sqlite-attach-temp-wal-schema-cache-current-source-next282',
            'sqlite-attach-temp-wal-schema-cache-current-source-next283',
            'sqlite-attach-temp-wal-schema-cache-current-source-next284',
            'sqlite-attach-temp-wal-schema-cache-current-source-next261',
            'sqlite-attach-temp-wal-schema-cache-current-source-next262',
            'sqlite-attach-temp-wal-schema-cache-current-source-next263',
            'sqlite-attach-temp-wal-schema-cache-current-source-next264',
            'sqlite-attach-temp-wal-schema-cache-current-source-next265',
            'sqlite-attach-temp-wal-schema-cache-current-source-next266',
            'sqlite-attach-temp-wal-schema-cache-current-source-next267',
            'sqlite-attach-temp-wal-schema-cache-current-source-next268',
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
    public static function currentSourceNext285300(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next285-300', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next285',
            'sqlite-attach-temp-wal-schema-cache-current-source-next286',
            'sqlite-attach-temp-wal-schema-cache-current-source-next287',
            'sqlite-attach-temp-wal-schema-cache-current-source-next288',
            'sqlite-attach-temp-wal-schema-cache-current-source-next289',
            'sqlite-attach-temp-wal-schema-cache-current-source-next290',
            'sqlite-attach-temp-wal-schema-cache-current-source-next291',
            'sqlite-attach-temp-wal-schema-cache-current-source-next292',
            'sqlite-attach-temp-wal-schema-cache-current-source-next293',
            'sqlite-attach-temp-wal-schema-cache-current-source-next294',
            'sqlite-attach-temp-wal-schema-cache-current-source-next295',
            'sqlite-attach-temp-wal-schema-cache-current-source-next296',
            'sqlite-attach-temp-wal-schema-cache-current-source-next297',
            'sqlite-attach-temp-wal-schema-cache-current-source-next298',
            'sqlite-attach-temp-wal-schema-cache-current-source-next299',
            'sqlite-attach-temp-wal-schema-cache-current-source-next300',
            'sqlite-attach-temp-wal-schema-cache-current-source-next269',
            'sqlite-attach-temp-wal-schema-cache-current-source-next270',
            'sqlite-attach-temp-wal-schema-cache-current-source-next271',
            'sqlite-attach-temp-wal-schema-cache-current-source-next272',
            'sqlite-attach-temp-wal-schema-cache-current-source-next273',
            'sqlite-attach-temp-wal-schema-cache-current-source-next274',
            'sqlite-attach-temp-wal-schema-cache-current-source-next275',
            'sqlite-attach-temp-wal-schema-cache-current-source-next276',
            'sqlite-attach-temp-wal-schema-cache-current-source-next277',
            'sqlite-attach-temp-wal-schema-cache-current-source-next278',
            'sqlite-attach-temp-wal-schema-cache-current-source-next279',
            'sqlite-attach-temp-wal-schema-cache-current-source-next280',
            'sqlite-attach-temp-wal-schema-cache-current-source-next281',
            'sqlite-attach-temp-wal-schema-cache-current-source-next282',
            'sqlite-attach-temp-wal-schema-cache-current-source-next283',
            'sqlite-attach-temp-wal-schema-cache-current-source-next284',
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
    public static function currentSourceNext301316(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next301-316', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next301',
            'sqlite-attach-temp-wal-schema-cache-current-source-next302',
            'sqlite-attach-temp-wal-schema-cache-current-source-next303',
            'sqlite-attach-temp-wal-schema-cache-current-source-next304',
            'sqlite-attach-temp-wal-schema-cache-current-source-next305',
            'sqlite-attach-temp-wal-schema-cache-current-source-next306',
            'sqlite-attach-temp-wal-schema-cache-current-source-next307',
            'sqlite-attach-temp-wal-schema-cache-current-source-next308',
            'sqlite-attach-temp-wal-schema-cache-current-source-next309',
            'sqlite-attach-temp-wal-schema-cache-current-source-next310',
            'sqlite-attach-temp-wal-schema-cache-current-source-next311',
            'sqlite-attach-temp-wal-schema-cache-current-source-next312',
            'sqlite-attach-temp-wal-schema-cache-current-source-next313',
            'sqlite-attach-temp-wal-schema-cache-current-source-next314',
            'sqlite-attach-temp-wal-schema-cache-current-source-next315',
            'sqlite-attach-temp-wal-schema-cache-current-source-next316',
            'sqlite-attach-temp-wal-schema-cache-current-source-next285',
            'sqlite-attach-temp-wal-schema-cache-current-source-next286',
            'sqlite-attach-temp-wal-schema-cache-current-source-next287',
            'sqlite-attach-temp-wal-schema-cache-current-source-next288',
            'sqlite-attach-temp-wal-schema-cache-current-source-next289',
            'sqlite-attach-temp-wal-schema-cache-current-source-next290',
            'sqlite-attach-temp-wal-schema-cache-current-source-next291',
            'sqlite-attach-temp-wal-schema-cache-current-source-next292',
            'sqlite-attach-temp-wal-schema-cache-current-source-next293',
            'sqlite-attach-temp-wal-schema-cache-current-source-next294',
            'sqlite-attach-temp-wal-schema-cache-current-source-next295',
            'sqlite-attach-temp-wal-schema-cache-current-source-next296',
            'sqlite-attach-temp-wal-schema-cache-current-source-next297',
            'sqlite-attach-temp-wal-schema-cache-current-source-next298',
            'sqlite-attach-temp-wal-schema-cache-current-source-next299',
            'sqlite-attach-temp-wal-schema-cache-current-source-next300',
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
    public static function currentSourceNext317332(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next317-332', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next317',
            'sqlite-attach-temp-wal-schema-cache-current-source-next318',
            'sqlite-attach-temp-wal-schema-cache-current-source-next319',
            'sqlite-attach-temp-wal-schema-cache-current-source-next320',
            'sqlite-attach-temp-wal-schema-cache-current-source-next321',
            'sqlite-attach-temp-wal-schema-cache-current-source-next322',
            'sqlite-attach-temp-wal-schema-cache-current-source-next323',
            'sqlite-attach-temp-wal-schema-cache-current-source-next324',
            'sqlite-attach-temp-wal-schema-cache-current-source-next325',
            'sqlite-attach-temp-wal-schema-cache-current-source-next326',
            'sqlite-attach-temp-wal-schema-cache-current-source-next327',
            'sqlite-attach-temp-wal-schema-cache-current-source-next328',
            'sqlite-attach-temp-wal-schema-cache-current-source-next329',
            'sqlite-attach-temp-wal-schema-cache-current-source-next330',
            'sqlite-attach-temp-wal-schema-cache-current-source-next331',
            'sqlite-attach-temp-wal-schema-cache-current-source-next332',
            'sqlite-attach-temp-wal-schema-cache-current-source-next301',
            'sqlite-attach-temp-wal-schema-cache-current-source-next302',
            'sqlite-attach-temp-wal-schema-cache-current-source-next303',
            'sqlite-attach-temp-wal-schema-cache-current-source-next304',
            'sqlite-attach-temp-wal-schema-cache-current-source-next305',
            'sqlite-attach-temp-wal-schema-cache-current-source-next306',
            'sqlite-attach-temp-wal-schema-cache-current-source-next307',
            'sqlite-attach-temp-wal-schema-cache-current-source-next308',
            'sqlite-attach-temp-wal-schema-cache-current-source-next309',
            'sqlite-attach-temp-wal-schema-cache-current-source-next310',
            'sqlite-attach-temp-wal-schema-cache-current-source-next311',
            'sqlite-attach-temp-wal-schema-cache-current-source-next312',
            'sqlite-attach-temp-wal-schema-cache-current-source-next313',
            'sqlite-attach-temp-wal-schema-cache-current-source-next314',
            'sqlite-attach-temp-wal-schema-cache-current-source-next315',
            'sqlite-attach-temp-wal-schema-cache-current-source-next316',
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
    public static function currentSourceNext333348(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next333-348', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next333',
            'sqlite-attach-temp-wal-schema-cache-current-source-next334',
            'sqlite-attach-temp-wal-schema-cache-current-source-next335',
            'sqlite-attach-temp-wal-schema-cache-current-source-next336',
            'sqlite-attach-temp-wal-schema-cache-current-source-next337',
            'sqlite-attach-temp-wal-schema-cache-current-source-next338',
            'sqlite-attach-temp-wal-schema-cache-current-source-next339',
            'sqlite-attach-temp-wal-schema-cache-current-source-next340',
            'sqlite-attach-temp-wal-schema-cache-current-source-next341',
            'sqlite-attach-temp-wal-schema-cache-current-source-next342',
            'sqlite-attach-temp-wal-schema-cache-current-source-next343',
            'sqlite-attach-temp-wal-schema-cache-current-source-next344',
            'sqlite-attach-temp-wal-schema-cache-current-source-next345',
            'sqlite-attach-temp-wal-schema-cache-current-source-next346',
            'sqlite-attach-temp-wal-schema-cache-current-source-next347',
            'sqlite-attach-temp-wal-schema-cache-current-source-next348',
            'sqlite-attach-temp-wal-schema-cache-current-source-next317',
            'sqlite-attach-temp-wal-schema-cache-current-source-next318',
            'sqlite-attach-temp-wal-schema-cache-current-source-next319',
            'sqlite-attach-temp-wal-schema-cache-current-source-next320',
            'sqlite-attach-temp-wal-schema-cache-current-source-next321',
            'sqlite-attach-temp-wal-schema-cache-current-source-next322',
            'sqlite-attach-temp-wal-schema-cache-current-source-next323',
            'sqlite-attach-temp-wal-schema-cache-current-source-next324',
            'sqlite-attach-temp-wal-schema-cache-current-source-next325',
            'sqlite-attach-temp-wal-schema-cache-current-source-next326',
            'sqlite-attach-temp-wal-schema-cache-current-source-next327',
            'sqlite-attach-temp-wal-schema-cache-current-source-next328',
            'sqlite-attach-temp-wal-schema-cache-current-source-next329',
            'sqlite-attach-temp-wal-schema-cache-current-source-next330',
            'sqlite-attach-temp-wal-schema-cache-current-source-next331',
            'sqlite-attach-temp-wal-schema-cache-current-source-next332',
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
    public static function currentSourceNext349364(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next349-364', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next349',
            'sqlite-attach-temp-wal-schema-cache-current-source-next350',
            'sqlite-attach-temp-wal-schema-cache-current-source-next351',
            'sqlite-attach-temp-wal-schema-cache-current-source-next352',
            'sqlite-attach-temp-wal-schema-cache-current-source-next353',
            'sqlite-attach-temp-wal-schema-cache-current-source-next354',
            'sqlite-attach-temp-wal-schema-cache-current-source-next355',
            'sqlite-attach-temp-wal-schema-cache-current-source-next356',
            'sqlite-attach-temp-wal-schema-cache-current-source-next357',
            'sqlite-attach-temp-wal-schema-cache-current-source-next358',
            'sqlite-attach-temp-wal-schema-cache-current-source-next359',
            'sqlite-attach-temp-wal-schema-cache-current-source-next360',
            'sqlite-attach-temp-wal-schema-cache-current-source-next361',
            'sqlite-attach-temp-wal-schema-cache-current-source-next362',
            'sqlite-attach-temp-wal-schema-cache-current-source-next363',
            'sqlite-attach-temp-wal-schema-cache-current-source-next364',
            'sqlite-attach-temp-wal-schema-cache-current-source-next333',
            'sqlite-attach-temp-wal-schema-cache-current-source-next334',
            'sqlite-attach-temp-wal-schema-cache-current-source-next335',
            'sqlite-attach-temp-wal-schema-cache-current-source-next336',
            'sqlite-attach-temp-wal-schema-cache-current-source-next337',
            'sqlite-attach-temp-wal-schema-cache-current-source-next338',
            'sqlite-attach-temp-wal-schema-cache-current-source-next339',
            'sqlite-attach-temp-wal-schema-cache-current-source-next340',
            'sqlite-attach-temp-wal-schema-cache-current-source-next341',
            'sqlite-attach-temp-wal-schema-cache-current-source-next342',
            'sqlite-attach-temp-wal-schema-cache-current-source-next343',
            'sqlite-attach-temp-wal-schema-cache-current-source-next344',
            'sqlite-attach-temp-wal-schema-cache-current-source-next345',
            'sqlite-attach-temp-wal-schema-cache-current-source-next346',
            'sqlite-attach-temp-wal-schema-cache-current-source-next347',
            'sqlite-attach-temp-wal-schema-cache-current-source-next348',
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
    public static function currentSourceNext365380(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next365-380', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next365',
            'sqlite-attach-temp-wal-schema-cache-current-source-next366',
            'sqlite-attach-temp-wal-schema-cache-current-source-next367',
            'sqlite-attach-temp-wal-schema-cache-current-source-next368',
            'sqlite-attach-temp-wal-schema-cache-current-source-next369',
            'sqlite-attach-temp-wal-schema-cache-current-source-next370',
            'sqlite-attach-temp-wal-schema-cache-current-source-next371',
            'sqlite-attach-temp-wal-schema-cache-current-source-next372',
            'sqlite-attach-temp-wal-schema-cache-current-source-next373',
            'sqlite-attach-temp-wal-schema-cache-current-source-next374',
            'sqlite-attach-temp-wal-schema-cache-current-source-next375',
            'sqlite-attach-temp-wal-schema-cache-current-source-next376',
            'sqlite-attach-temp-wal-schema-cache-current-source-next377',
            'sqlite-attach-temp-wal-schema-cache-current-source-next378',
            'sqlite-attach-temp-wal-schema-cache-current-source-next379',
            'sqlite-attach-temp-wal-schema-cache-current-source-next380',
            'sqlite-attach-temp-wal-schema-cache-current-source-next349',
            'sqlite-attach-temp-wal-schema-cache-current-source-next350',
            'sqlite-attach-temp-wal-schema-cache-current-source-next351',
            'sqlite-attach-temp-wal-schema-cache-current-source-next352',
            'sqlite-attach-temp-wal-schema-cache-current-source-next353',
            'sqlite-attach-temp-wal-schema-cache-current-source-next354',
            'sqlite-attach-temp-wal-schema-cache-current-source-next355',
            'sqlite-attach-temp-wal-schema-cache-current-source-next356',
            'sqlite-attach-temp-wal-schema-cache-current-source-next357',
            'sqlite-attach-temp-wal-schema-cache-current-source-next358',
            'sqlite-attach-temp-wal-schema-cache-current-source-next359',
            'sqlite-attach-temp-wal-schema-cache-current-source-next360',
            'sqlite-attach-temp-wal-schema-cache-current-source-next361',
            'sqlite-attach-temp-wal-schema-cache-current-source-next362',
            'sqlite-attach-temp-wal-schema-cache-current-source-next363',
            'sqlite-attach-temp-wal-schema-cache-current-source-next364',
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
    public static function currentSourceNext381396(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next381-396', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next381',
            'sqlite-attach-temp-wal-schema-cache-current-source-next382',
            'sqlite-attach-temp-wal-schema-cache-current-source-next383',
            'sqlite-attach-temp-wal-schema-cache-current-source-next384',
            'sqlite-attach-temp-wal-schema-cache-current-source-next385',
            'sqlite-attach-temp-wal-schema-cache-current-source-next386',
            'sqlite-attach-temp-wal-schema-cache-current-source-next387',
            'sqlite-attach-temp-wal-schema-cache-current-source-next388',
            'sqlite-attach-temp-wal-schema-cache-current-source-next389',
            'sqlite-attach-temp-wal-schema-cache-current-source-next390',
            'sqlite-attach-temp-wal-schema-cache-current-source-next391',
            'sqlite-attach-temp-wal-schema-cache-current-source-next392',
            'sqlite-attach-temp-wal-schema-cache-current-source-next393',
            'sqlite-attach-temp-wal-schema-cache-current-source-next394',
            'sqlite-attach-temp-wal-schema-cache-current-source-next395',
            'sqlite-attach-temp-wal-schema-cache-current-source-next396',
            'sqlite-attach-temp-wal-schema-cache-current-source-next365',
            'sqlite-attach-temp-wal-schema-cache-current-source-next366',
            'sqlite-attach-temp-wal-schema-cache-current-source-next367',
            'sqlite-attach-temp-wal-schema-cache-current-source-next368',
            'sqlite-attach-temp-wal-schema-cache-current-source-next369',
            'sqlite-attach-temp-wal-schema-cache-current-source-next370',
            'sqlite-attach-temp-wal-schema-cache-current-source-next371',
            'sqlite-attach-temp-wal-schema-cache-current-source-next372',
            'sqlite-attach-temp-wal-schema-cache-current-source-next373',
            'sqlite-attach-temp-wal-schema-cache-current-source-next374',
            'sqlite-attach-temp-wal-schema-cache-current-source-next375',
            'sqlite-attach-temp-wal-schema-cache-current-source-next376',
            'sqlite-attach-temp-wal-schema-cache-current-source-next377',
            'sqlite-attach-temp-wal-schema-cache-current-source-next378',
            'sqlite-attach-temp-wal-schema-cache-current-source-next379',
            'sqlite-attach-temp-wal-schema-cache-current-source-next380',
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
    public static function currentSourceNext397412(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next397-412', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next397',
            'sqlite-attach-temp-wal-schema-cache-current-source-next398',
            'sqlite-attach-temp-wal-schema-cache-current-source-next399',
            'sqlite-attach-temp-wal-schema-cache-current-source-next400',
            'sqlite-attach-temp-wal-schema-cache-current-source-next401',
            'sqlite-attach-temp-wal-schema-cache-current-source-next402',
            'sqlite-attach-temp-wal-schema-cache-current-source-next403',
            'sqlite-attach-temp-wal-schema-cache-current-source-next404',
            'sqlite-attach-temp-wal-schema-cache-current-source-next405',
            'sqlite-attach-temp-wal-schema-cache-current-source-next406',
            'sqlite-attach-temp-wal-schema-cache-current-source-next407',
            'sqlite-attach-temp-wal-schema-cache-current-source-next408',
            'sqlite-attach-temp-wal-schema-cache-current-source-next409',
            'sqlite-attach-temp-wal-schema-cache-current-source-next410',
            'sqlite-attach-temp-wal-schema-cache-current-source-next411',
            'sqlite-attach-temp-wal-schema-cache-current-source-next412',
            'sqlite-attach-temp-wal-schema-cache-current-source-next381',
            'sqlite-attach-temp-wal-schema-cache-current-source-next382',
            'sqlite-attach-temp-wal-schema-cache-current-source-next383',
            'sqlite-attach-temp-wal-schema-cache-current-source-next384',
            'sqlite-attach-temp-wal-schema-cache-current-source-next385',
            'sqlite-attach-temp-wal-schema-cache-current-source-next386',
            'sqlite-attach-temp-wal-schema-cache-current-source-next387',
            'sqlite-attach-temp-wal-schema-cache-current-source-next388',
            'sqlite-attach-temp-wal-schema-cache-current-source-next389',
            'sqlite-attach-temp-wal-schema-cache-current-source-next390',
            'sqlite-attach-temp-wal-schema-cache-current-source-next391',
            'sqlite-attach-temp-wal-schema-cache-current-source-next392',
            'sqlite-attach-temp-wal-schema-cache-current-source-next393',
            'sqlite-attach-temp-wal-schema-cache-current-source-next394',
            'sqlite-attach-temp-wal-schema-cache-current-source-next395',
            'sqlite-attach-temp-wal-schema-cache-current-source-next396',
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
    public static function currentSourceNext413428(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next413-428', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next413',
            'sqlite-attach-temp-wal-schema-cache-current-source-next414',
            'sqlite-attach-temp-wal-schema-cache-current-source-next415',
            'sqlite-attach-temp-wal-schema-cache-current-source-next416',
            'sqlite-attach-temp-wal-schema-cache-current-source-next417',
            'sqlite-attach-temp-wal-schema-cache-current-source-next418',
            'sqlite-attach-temp-wal-schema-cache-current-source-next419',
            'sqlite-attach-temp-wal-schema-cache-current-source-next420',
            'sqlite-attach-temp-wal-schema-cache-current-source-next421',
            'sqlite-attach-temp-wal-schema-cache-current-source-next422',
            'sqlite-attach-temp-wal-schema-cache-current-source-next423',
            'sqlite-attach-temp-wal-schema-cache-current-source-next424',
            'sqlite-attach-temp-wal-schema-cache-current-source-next425',
            'sqlite-attach-temp-wal-schema-cache-current-source-next426',
            'sqlite-attach-temp-wal-schema-cache-current-source-next427',
            'sqlite-attach-temp-wal-schema-cache-current-source-next428',
            'sqlite-attach-temp-wal-schema-cache-current-source-next397',
            'sqlite-attach-temp-wal-schema-cache-current-source-next398',
            'sqlite-attach-temp-wal-schema-cache-current-source-next399',
            'sqlite-attach-temp-wal-schema-cache-current-source-next400',
            'sqlite-attach-temp-wal-schema-cache-current-source-next401',
            'sqlite-attach-temp-wal-schema-cache-current-source-next402',
            'sqlite-attach-temp-wal-schema-cache-current-source-next403',
            'sqlite-attach-temp-wal-schema-cache-current-source-next404',
            'sqlite-attach-temp-wal-schema-cache-current-source-next405',
            'sqlite-attach-temp-wal-schema-cache-current-source-next406',
            'sqlite-attach-temp-wal-schema-cache-current-source-next407',
            'sqlite-attach-temp-wal-schema-cache-current-source-next408',
            'sqlite-attach-temp-wal-schema-cache-current-source-next409',
            'sqlite-attach-temp-wal-schema-cache-current-source-next410',
            'sqlite-attach-temp-wal-schema-cache-current-source-next411',
            'sqlite-attach-temp-wal-schema-cache-current-source-next412',
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
    public static function currentSourceNext429444(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next429-444', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next429',
            'sqlite-attach-temp-wal-schema-cache-current-source-next430',
            'sqlite-attach-temp-wal-schema-cache-current-source-next431',
            'sqlite-attach-temp-wal-schema-cache-current-source-next432',
            'sqlite-attach-temp-wal-schema-cache-current-source-next433',
            'sqlite-attach-temp-wal-schema-cache-current-source-next434',
            'sqlite-attach-temp-wal-schema-cache-current-source-next435',
            'sqlite-attach-temp-wal-schema-cache-current-source-next436',
            'sqlite-attach-temp-wal-schema-cache-current-source-next437',
            'sqlite-attach-temp-wal-schema-cache-current-source-next438',
            'sqlite-attach-temp-wal-schema-cache-current-source-next439',
            'sqlite-attach-temp-wal-schema-cache-current-source-next440',
            'sqlite-attach-temp-wal-schema-cache-current-source-next441',
            'sqlite-attach-temp-wal-schema-cache-current-source-next442',
            'sqlite-attach-temp-wal-schema-cache-current-source-next443',
            'sqlite-attach-temp-wal-schema-cache-current-source-next444',
            'sqlite-attach-temp-wal-schema-cache-current-source-next413',
            'sqlite-attach-temp-wal-schema-cache-current-source-next414',
            'sqlite-attach-temp-wal-schema-cache-current-source-next415',
            'sqlite-attach-temp-wal-schema-cache-current-source-next416',
            'sqlite-attach-temp-wal-schema-cache-current-source-next417',
            'sqlite-attach-temp-wal-schema-cache-current-source-next418',
            'sqlite-attach-temp-wal-schema-cache-current-source-next419',
            'sqlite-attach-temp-wal-schema-cache-current-source-next420',
            'sqlite-attach-temp-wal-schema-cache-current-source-next421',
            'sqlite-attach-temp-wal-schema-cache-current-source-next422',
            'sqlite-attach-temp-wal-schema-cache-current-source-next423',
            'sqlite-attach-temp-wal-schema-cache-current-source-next424',
            'sqlite-attach-temp-wal-schema-cache-current-source-next425',
            'sqlite-attach-temp-wal-schema-cache-current-source-next426',
            'sqlite-attach-temp-wal-schema-cache-current-source-next427',
            'sqlite-attach-temp-wal-schema-cache-current-source-next428',
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
    public static function currentSourceNext445460(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next445-460', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next445',
            'sqlite-attach-temp-wal-schema-cache-current-source-next446',
            'sqlite-attach-temp-wal-schema-cache-current-source-next447',
            'sqlite-attach-temp-wal-schema-cache-current-source-next448',
            'sqlite-attach-temp-wal-schema-cache-current-source-next449',
            'sqlite-attach-temp-wal-schema-cache-current-source-next450',
            'sqlite-attach-temp-wal-schema-cache-current-source-next451',
            'sqlite-attach-temp-wal-schema-cache-current-source-next452',
            'sqlite-attach-temp-wal-schema-cache-current-source-next453',
            'sqlite-attach-temp-wal-schema-cache-current-source-next454',
            'sqlite-attach-temp-wal-schema-cache-current-source-next455',
            'sqlite-attach-temp-wal-schema-cache-current-source-next456',
            'sqlite-attach-temp-wal-schema-cache-current-source-next457',
            'sqlite-attach-temp-wal-schema-cache-current-source-next458',
            'sqlite-attach-temp-wal-schema-cache-current-source-next459',
            'sqlite-attach-temp-wal-schema-cache-current-source-next460',
            'sqlite-attach-temp-wal-schema-cache-current-source-next429',
            'sqlite-attach-temp-wal-schema-cache-current-source-next430',
            'sqlite-attach-temp-wal-schema-cache-current-source-next431',
            'sqlite-attach-temp-wal-schema-cache-current-source-next432',
            'sqlite-attach-temp-wal-schema-cache-current-source-next433',
            'sqlite-attach-temp-wal-schema-cache-current-source-next434',
            'sqlite-attach-temp-wal-schema-cache-current-source-next435',
            'sqlite-attach-temp-wal-schema-cache-current-source-next436',
            'sqlite-attach-temp-wal-schema-cache-current-source-next437',
            'sqlite-attach-temp-wal-schema-cache-current-source-next438',
            'sqlite-attach-temp-wal-schema-cache-current-source-next439',
            'sqlite-attach-temp-wal-schema-cache-current-source-next440',
            'sqlite-attach-temp-wal-schema-cache-current-source-next441',
            'sqlite-attach-temp-wal-schema-cache-current-source-next442',
            'sqlite-attach-temp-wal-schema-cache-current-source-next443',
            'sqlite-attach-temp-wal-schema-cache-current-source-next444',
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
    public static function currentSourceNext461476(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next461-476', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next461',
            'sqlite-attach-temp-wal-schema-cache-current-source-next462',
            'sqlite-attach-temp-wal-schema-cache-current-source-next463',
            'sqlite-attach-temp-wal-schema-cache-current-source-next464',
            'sqlite-attach-temp-wal-schema-cache-current-source-next465',
            'sqlite-attach-temp-wal-schema-cache-current-source-next466',
            'sqlite-attach-temp-wal-schema-cache-current-source-next467',
            'sqlite-attach-temp-wal-schema-cache-current-source-next468',
            'sqlite-attach-temp-wal-schema-cache-current-source-next469',
            'sqlite-attach-temp-wal-schema-cache-current-source-next470',
            'sqlite-attach-temp-wal-schema-cache-current-source-next471',
            'sqlite-attach-temp-wal-schema-cache-current-source-next472',
            'sqlite-attach-temp-wal-schema-cache-current-source-next473',
            'sqlite-attach-temp-wal-schema-cache-current-source-next474',
            'sqlite-attach-temp-wal-schema-cache-current-source-next475',
            'sqlite-attach-temp-wal-schema-cache-current-source-next476',
            'sqlite-attach-temp-wal-schema-cache-current-source-next445',
            'sqlite-attach-temp-wal-schema-cache-current-source-next446',
            'sqlite-attach-temp-wal-schema-cache-current-source-next447',
            'sqlite-attach-temp-wal-schema-cache-current-source-next448',
            'sqlite-attach-temp-wal-schema-cache-current-source-next449',
            'sqlite-attach-temp-wal-schema-cache-current-source-next450',
            'sqlite-attach-temp-wal-schema-cache-current-source-next451',
            'sqlite-attach-temp-wal-schema-cache-current-source-next452',
            'sqlite-attach-temp-wal-schema-cache-current-source-next453',
            'sqlite-attach-temp-wal-schema-cache-current-source-next454',
            'sqlite-attach-temp-wal-schema-cache-current-source-next455',
            'sqlite-attach-temp-wal-schema-cache-current-source-next456',
            'sqlite-attach-temp-wal-schema-cache-current-source-next457',
            'sqlite-attach-temp-wal-schema-cache-current-source-next458',
            'sqlite-attach-temp-wal-schema-cache-current-source-next459',
            'sqlite-attach-temp-wal-schema-cache-current-source-next460',
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
    public static function currentSourceNext477492(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next477-492', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next477',
            'sqlite-attach-temp-wal-schema-cache-current-source-next478',
            'sqlite-attach-temp-wal-schema-cache-current-source-next479',
            'sqlite-attach-temp-wal-schema-cache-current-source-next480',
            'sqlite-attach-temp-wal-schema-cache-current-source-next481',
            'sqlite-attach-temp-wal-schema-cache-current-source-next482',
            'sqlite-attach-temp-wal-schema-cache-current-source-next483',
            'sqlite-attach-temp-wal-schema-cache-current-source-next484',
            'sqlite-attach-temp-wal-schema-cache-current-source-next485',
            'sqlite-attach-temp-wal-schema-cache-current-source-next486',
            'sqlite-attach-temp-wal-schema-cache-current-source-next487',
            'sqlite-attach-temp-wal-schema-cache-current-source-next488',
            'sqlite-attach-temp-wal-schema-cache-current-source-next489',
            'sqlite-attach-temp-wal-schema-cache-current-source-next490',
            'sqlite-attach-temp-wal-schema-cache-current-source-next491',
            'sqlite-attach-temp-wal-schema-cache-current-source-next492',
            'sqlite-attach-temp-wal-schema-cache-current-source-next461',
            'sqlite-attach-temp-wal-schema-cache-current-source-next462',
            'sqlite-attach-temp-wal-schema-cache-current-source-next463',
            'sqlite-attach-temp-wal-schema-cache-current-source-next464',
            'sqlite-attach-temp-wal-schema-cache-current-source-next465',
            'sqlite-attach-temp-wal-schema-cache-current-source-next466',
            'sqlite-attach-temp-wal-schema-cache-current-source-next467',
            'sqlite-attach-temp-wal-schema-cache-current-source-next468',
            'sqlite-attach-temp-wal-schema-cache-current-source-next469',
            'sqlite-attach-temp-wal-schema-cache-current-source-next470',
            'sqlite-attach-temp-wal-schema-cache-current-source-next471',
            'sqlite-attach-temp-wal-schema-cache-current-source-next472',
            'sqlite-attach-temp-wal-schema-cache-current-source-next473',
            'sqlite-attach-temp-wal-schema-cache-current-source-next474',
            'sqlite-attach-temp-wal-schema-cache-current-source-next475',
            'sqlite-attach-temp-wal-schema-cache-current-source-next476',
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
    public static function currentSourceNext493508(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next493-508', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next493',
            'sqlite-attach-temp-wal-schema-cache-current-source-next494',
            'sqlite-attach-temp-wal-schema-cache-current-source-next495',
            'sqlite-attach-temp-wal-schema-cache-current-source-next496',
            'sqlite-attach-temp-wal-schema-cache-current-source-next497',
            'sqlite-attach-temp-wal-schema-cache-current-source-next498',
            'sqlite-attach-temp-wal-schema-cache-current-source-next499',
            'sqlite-attach-temp-wal-schema-cache-current-source-next500',
            'sqlite-attach-temp-wal-schema-cache-current-source-next501',
            'sqlite-attach-temp-wal-schema-cache-current-source-next502',
            'sqlite-attach-temp-wal-schema-cache-current-source-next503',
            'sqlite-attach-temp-wal-schema-cache-current-source-next504',
            'sqlite-attach-temp-wal-schema-cache-current-source-next505',
            'sqlite-attach-temp-wal-schema-cache-current-source-next506',
            'sqlite-attach-temp-wal-schema-cache-current-source-next507',
            'sqlite-attach-temp-wal-schema-cache-current-source-next508',
            'sqlite-attach-temp-wal-schema-cache-current-source-next477',
            'sqlite-attach-temp-wal-schema-cache-current-source-next478',
            'sqlite-attach-temp-wal-schema-cache-current-source-next479',
            'sqlite-attach-temp-wal-schema-cache-current-source-next480',
            'sqlite-attach-temp-wal-schema-cache-current-source-next481',
            'sqlite-attach-temp-wal-schema-cache-current-source-next482',
            'sqlite-attach-temp-wal-schema-cache-current-source-next483',
            'sqlite-attach-temp-wal-schema-cache-current-source-next484',
            'sqlite-attach-temp-wal-schema-cache-current-source-next485',
            'sqlite-attach-temp-wal-schema-cache-current-source-next486',
            'sqlite-attach-temp-wal-schema-cache-current-source-next487',
            'sqlite-attach-temp-wal-schema-cache-current-source-next488',
            'sqlite-attach-temp-wal-schema-cache-current-source-next489',
            'sqlite-attach-temp-wal-schema-cache-current-source-next490',
            'sqlite-attach-temp-wal-schema-cache-current-source-next491',
            'sqlite-attach-temp-wal-schema-cache-current-source-next492',
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
    public static function currentSourceNext509524(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next509-524', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next509',
            'sqlite-attach-temp-wal-schema-cache-current-source-next510',
            'sqlite-attach-temp-wal-schema-cache-current-source-next511',
            'sqlite-attach-temp-wal-schema-cache-current-source-next512',
            'sqlite-attach-temp-wal-schema-cache-current-source-next513',
            'sqlite-attach-temp-wal-schema-cache-current-source-next514',
            'sqlite-attach-temp-wal-schema-cache-current-source-next515',
            'sqlite-attach-temp-wal-schema-cache-current-source-next516',
            'sqlite-attach-temp-wal-schema-cache-current-source-next517',
            'sqlite-attach-temp-wal-schema-cache-current-source-next518',
            'sqlite-attach-temp-wal-schema-cache-current-source-next519',
            'sqlite-attach-temp-wal-schema-cache-current-source-next520',
            'sqlite-attach-temp-wal-schema-cache-current-source-next521',
            'sqlite-attach-temp-wal-schema-cache-current-source-next522',
            'sqlite-attach-temp-wal-schema-cache-current-source-next523',
            'sqlite-attach-temp-wal-schema-cache-current-source-next524',
            'sqlite-attach-temp-wal-schema-cache-current-source-next493',
            'sqlite-attach-temp-wal-schema-cache-current-source-next494',
            'sqlite-attach-temp-wal-schema-cache-current-source-next495',
            'sqlite-attach-temp-wal-schema-cache-current-source-next496',
            'sqlite-attach-temp-wal-schema-cache-current-source-next497',
            'sqlite-attach-temp-wal-schema-cache-current-source-next498',
            'sqlite-attach-temp-wal-schema-cache-current-source-next499',
            'sqlite-attach-temp-wal-schema-cache-current-source-next500',
            'sqlite-attach-temp-wal-schema-cache-current-source-next501',
            'sqlite-attach-temp-wal-schema-cache-current-source-next502',
            'sqlite-attach-temp-wal-schema-cache-current-source-next503',
            'sqlite-attach-temp-wal-schema-cache-current-source-next504',
            'sqlite-attach-temp-wal-schema-cache-current-source-next505',
            'sqlite-attach-temp-wal-schema-cache-current-source-next506',
            'sqlite-attach-temp-wal-schema-cache-current-source-next507',
            'sqlite-attach-temp-wal-schema-cache-current-source-next508',
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
    public static function currentSourceNext525540(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next525-540', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next525',
            'sqlite-attach-temp-wal-schema-cache-current-source-next526',
            'sqlite-attach-temp-wal-schema-cache-current-source-next527',
            'sqlite-attach-temp-wal-schema-cache-current-source-next528',
            'sqlite-attach-temp-wal-schema-cache-current-source-next529',
            'sqlite-attach-temp-wal-schema-cache-current-source-next530',
            'sqlite-attach-temp-wal-schema-cache-current-source-next531',
            'sqlite-attach-temp-wal-schema-cache-current-source-next532',
            'sqlite-attach-temp-wal-schema-cache-current-source-next533',
            'sqlite-attach-temp-wal-schema-cache-current-source-next534',
            'sqlite-attach-temp-wal-schema-cache-current-source-next535',
            'sqlite-attach-temp-wal-schema-cache-current-source-next536',
            'sqlite-attach-temp-wal-schema-cache-current-source-next537',
            'sqlite-attach-temp-wal-schema-cache-current-source-next538',
            'sqlite-attach-temp-wal-schema-cache-current-source-next539',
            'sqlite-attach-temp-wal-schema-cache-current-source-next540',
            'sqlite-attach-temp-wal-schema-cache-current-source-next509',
            'sqlite-attach-temp-wal-schema-cache-current-source-next510',
            'sqlite-attach-temp-wal-schema-cache-current-source-next511',
            'sqlite-attach-temp-wal-schema-cache-current-source-next512',
            'sqlite-attach-temp-wal-schema-cache-current-source-next513',
            'sqlite-attach-temp-wal-schema-cache-current-source-next514',
            'sqlite-attach-temp-wal-schema-cache-current-source-next515',
            'sqlite-attach-temp-wal-schema-cache-current-source-next516',
            'sqlite-attach-temp-wal-schema-cache-current-source-next517',
            'sqlite-attach-temp-wal-schema-cache-current-source-next518',
            'sqlite-attach-temp-wal-schema-cache-current-source-next519',
            'sqlite-attach-temp-wal-schema-cache-current-source-next520',
            'sqlite-attach-temp-wal-schema-cache-current-source-next521',
            'sqlite-attach-temp-wal-schema-cache-current-source-next522',
            'sqlite-attach-temp-wal-schema-cache-current-source-next523',
            'sqlite-attach-temp-wal-schema-cache-current-source-next524',
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
    public static function currentSourceNext541556(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next541-556', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next541',
            'sqlite-attach-temp-wal-schema-cache-current-source-next542',
            'sqlite-attach-temp-wal-schema-cache-current-source-next543',
            'sqlite-attach-temp-wal-schema-cache-current-source-next544',
            'sqlite-attach-temp-wal-schema-cache-current-source-next545',
            'sqlite-attach-temp-wal-schema-cache-current-source-next546',
            'sqlite-attach-temp-wal-schema-cache-current-source-next547',
            'sqlite-attach-temp-wal-schema-cache-current-source-next548',
            'sqlite-attach-temp-wal-schema-cache-current-source-next549',
            'sqlite-attach-temp-wal-schema-cache-current-source-next550',
            'sqlite-attach-temp-wal-schema-cache-current-source-next551',
            'sqlite-attach-temp-wal-schema-cache-current-source-next552',
            'sqlite-attach-temp-wal-schema-cache-current-source-next553',
            'sqlite-attach-temp-wal-schema-cache-current-source-next554',
            'sqlite-attach-temp-wal-schema-cache-current-source-next555',
            'sqlite-attach-temp-wal-schema-cache-current-source-next556',
            'sqlite-attach-temp-wal-schema-cache-current-source-next525',
            'sqlite-attach-temp-wal-schema-cache-current-source-next526',
            'sqlite-attach-temp-wal-schema-cache-current-source-next527',
            'sqlite-attach-temp-wal-schema-cache-current-source-next528',
            'sqlite-attach-temp-wal-schema-cache-current-source-next529',
            'sqlite-attach-temp-wal-schema-cache-current-source-next530',
            'sqlite-attach-temp-wal-schema-cache-current-source-next531',
            'sqlite-attach-temp-wal-schema-cache-current-source-next532',
            'sqlite-attach-temp-wal-schema-cache-current-source-next533',
            'sqlite-attach-temp-wal-schema-cache-current-source-next534',
            'sqlite-attach-temp-wal-schema-cache-current-source-next535',
            'sqlite-attach-temp-wal-schema-cache-current-source-next536',
            'sqlite-attach-temp-wal-schema-cache-current-source-next537',
            'sqlite-attach-temp-wal-schema-cache-current-source-next538',
            'sqlite-attach-temp-wal-schema-cache-current-source-next539',
            'sqlite-attach-temp-wal-schema-cache-current-source-next540',
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
    public static function currentSourceNext557572(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next557-572', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next557',
            'sqlite-attach-temp-wal-schema-cache-current-source-next558',
            'sqlite-attach-temp-wal-schema-cache-current-source-next559',
            'sqlite-attach-temp-wal-schema-cache-current-source-next560',
            'sqlite-attach-temp-wal-schema-cache-current-source-next561',
            'sqlite-attach-temp-wal-schema-cache-current-source-next562',
            'sqlite-attach-temp-wal-schema-cache-current-source-next563',
            'sqlite-attach-temp-wal-schema-cache-current-source-next564',
            'sqlite-attach-temp-wal-schema-cache-current-source-next565',
            'sqlite-attach-temp-wal-schema-cache-current-source-next566',
            'sqlite-attach-temp-wal-schema-cache-current-source-next567',
            'sqlite-attach-temp-wal-schema-cache-current-source-next568',
            'sqlite-attach-temp-wal-schema-cache-current-source-next569',
            'sqlite-attach-temp-wal-schema-cache-current-source-next570',
            'sqlite-attach-temp-wal-schema-cache-current-source-next571',
            'sqlite-attach-temp-wal-schema-cache-current-source-next572',
            'sqlite-attach-temp-wal-schema-cache-current-source-next541',
            'sqlite-attach-temp-wal-schema-cache-current-source-next542',
            'sqlite-attach-temp-wal-schema-cache-current-source-next543',
            'sqlite-attach-temp-wal-schema-cache-current-source-next544',
            'sqlite-attach-temp-wal-schema-cache-current-source-next545',
            'sqlite-attach-temp-wal-schema-cache-current-source-next546',
            'sqlite-attach-temp-wal-schema-cache-current-source-next547',
            'sqlite-attach-temp-wal-schema-cache-current-source-next548',
            'sqlite-attach-temp-wal-schema-cache-current-source-next549',
            'sqlite-attach-temp-wal-schema-cache-current-source-next550',
            'sqlite-attach-temp-wal-schema-cache-current-source-next551',
            'sqlite-attach-temp-wal-schema-cache-current-source-next552',
            'sqlite-attach-temp-wal-schema-cache-current-source-next553',
            'sqlite-attach-temp-wal-schema-cache-current-source-next554',
            'sqlite-attach-temp-wal-schema-cache-current-source-next555',
            'sqlite-attach-temp-wal-schema-cache-current-source-next556',
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
    public static function currentSourceNext573588(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next573-588', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next573',
            'sqlite-attach-temp-wal-schema-cache-current-source-next574',
            'sqlite-attach-temp-wal-schema-cache-current-source-next575',
            'sqlite-attach-temp-wal-schema-cache-current-source-next576',
            'sqlite-attach-temp-wal-schema-cache-current-source-next577',
            'sqlite-attach-temp-wal-schema-cache-current-source-next578',
            'sqlite-attach-temp-wal-schema-cache-current-source-next579',
            'sqlite-attach-temp-wal-schema-cache-current-source-next580',
            'sqlite-attach-temp-wal-schema-cache-current-source-next581',
            'sqlite-attach-temp-wal-schema-cache-current-source-next582',
            'sqlite-attach-temp-wal-schema-cache-current-source-next583',
            'sqlite-attach-temp-wal-schema-cache-current-source-next584',
            'sqlite-attach-temp-wal-schema-cache-current-source-next585',
            'sqlite-attach-temp-wal-schema-cache-current-source-next586',
            'sqlite-attach-temp-wal-schema-cache-current-source-next587',
            'sqlite-attach-temp-wal-schema-cache-current-source-next588',
            'sqlite-attach-temp-wal-schema-cache-current-source-next557',
            'sqlite-attach-temp-wal-schema-cache-current-source-next558',
            'sqlite-attach-temp-wal-schema-cache-current-source-next559',
            'sqlite-attach-temp-wal-schema-cache-current-source-next560',
            'sqlite-attach-temp-wal-schema-cache-current-source-next561',
            'sqlite-attach-temp-wal-schema-cache-current-source-next562',
            'sqlite-attach-temp-wal-schema-cache-current-source-next563',
            'sqlite-attach-temp-wal-schema-cache-current-source-next564',
            'sqlite-attach-temp-wal-schema-cache-current-source-next565',
            'sqlite-attach-temp-wal-schema-cache-current-source-next566',
            'sqlite-attach-temp-wal-schema-cache-current-source-next567',
            'sqlite-attach-temp-wal-schema-cache-current-source-next568',
            'sqlite-attach-temp-wal-schema-cache-current-source-next569',
            'sqlite-attach-temp-wal-schema-cache-current-source-next570',
            'sqlite-attach-temp-wal-schema-cache-current-source-next571',
            'sqlite-attach-temp-wal-schema-cache-current-source-next572',
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
    public static function currentSourceNext589604(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next589-604', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next589',
            'sqlite-attach-temp-wal-schema-cache-current-source-next590',
            'sqlite-attach-temp-wal-schema-cache-current-source-next591',
            'sqlite-attach-temp-wal-schema-cache-current-source-next592',
            'sqlite-attach-temp-wal-schema-cache-current-source-next593',
            'sqlite-attach-temp-wal-schema-cache-current-source-next594',
            'sqlite-attach-temp-wal-schema-cache-current-source-next595',
            'sqlite-attach-temp-wal-schema-cache-current-source-next596',
            'sqlite-attach-temp-wal-schema-cache-current-source-next597',
            'sqlite-attach-temp-wal-schema-cache-current-source-next598',
            'sqlite-attach-temp-wal-schema-cache-current-source-next599',
            'sqlite-attach-temp-wal-schema-cache-current-source-next600',
            'sqlite-attach-temp-wal-schema-cache-current-source-next601',
            'sqlite-attach-temp-wal-schema-cache-current-source-next602',
            'sqlite-attach-temp-wal-schema-cache-current-source-next603',
            'sqlite-attach-temp-wal-schema-cache-current-source-next604',
            'sqlite-attach-temp-wal-schema-cache-current-source-next573',
            'sqlite-attach-temp-wal-schema-cache-current-source-next574',
            'sqlite-attach-temp-wal-schema-cache-current-source-next575',
            'sqlite-attach-temp-wal-schema-cache-current-source-next576',
            'sqlite-attach-temp-wal-schema-cache-current-source-next577',
            'sqlite-attach-temp-wal-schema-cache-current-source-next578',
            'sqlite-attach-temp-wal-schema-cache-current-source-next579',
            'sqlite-attach-temp-wal-schema-cache-current-source-next580',
            'sqlite-attach-temp-wal-schema-cache-current-source-next581',
            'sqlite-attach-temp-wal-schema-cache-current-source-next582',
            'sqlite-attach-temp-wal-schema-cache-current-source-next583',
            'sqlite-attach-temp-wal-schema-cache-current-source-next584',
            'sqlite-attach-temp-wal-schema-cache-current-source-next585',
            'sqlite-attach-temp-wal-schema-cache-current-source-next586',
            'sqlite-attach-temp-wal-schema-cache-current-source-next587',
            'sqlite-attach-temp-wal-schema-cache-current-source-next588',
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
    public static function currentSourceNext605620(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next605-620', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next605',
            'sqlite-attach-temp-wal-schema-cache-current-source-next606',
            'sqlite-attach-temp-wal-schema-cache-current-source-next607',
            'sqlite-attach-temp-wal-schema-cache-current-source-next608',
            'sqlite-attach-temp-wal-schema-cache-current-source-next609',
            'sqlite-attach-temp-wal-schema-cache-current-source-next610',
            'sqlite-attach-temp-wal-schema-cache-current-source-next611',
            'sqlite-attach-temp-wal-schema-cache-current-source-next612',
            'sqlite-attach-temp-wal-schema-cache-current-source-next613',
            'sqlite-attach-temp-wal-schema-cache-current-source-next614',
            'sqlite-attach-temp-wal-schema-cache-current-source-next615',
            'sqlite-attach-temp-wal-schema-cache-current-source-next616',
            'sqlite-attach-temp-wal-schema-cache-current-source-next617',
            'sqlite-attach-temp-wal-schema-cache-current-source-next618',
            'sqlite-attach-temp-wal-schema-cache-current-source-next619',
            'sqlite-attach-temp-wal-schema-cache-current-source-next620',
            'sqlite-attach-temp-wal-schema-cache-current-source-next589',
            'sqlite-attach-temp-wal-schema-cache-current-source-next590',
            'sqlite-attach-temp-wal-schema-cache-current-source-next591',
            'sqlite-attach-temp-wal-schema-cache-current-source-next592',
            'sqlite-attach-temp-wal-schema-cache-current-source-next593',
            'sqlite-attach-temp-wal-schema-cache-current-source-next594',
            'sqlite-attach-temp-wal-schema-cache-current-source-next595',
            'sqlite-attach-temp-wal-schema-cache-current-source-next596',
            'sqlite-attach-temp-wal-schema-cache-current-source-next597',
            'sqlite-attach-temp-wal-schema-cache-current-source-next598',
            'sqlite-attach-temp-wal-schema-cache-current-source-next599',
            'sqlite-attach-temp-wal-schema-cache-current-source-next600',
            'sqlite-attach-temp-wal-schema-cache-current-source-next601',
            'sqlite-attach-temp-wal-schema-cache-current-source-next602',
            'sqlite-attach-temp-wal-schema-cache-current-source-next603',
            'sqlite-attach-temp-wal-schema-cache-current-source-next604',
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
    public static function currentSourceNext621636(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next621-636', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next621',
            'sqlite-attach-temp-wal-schema-cache-current-source-next622',
            'sqlite-attach-temp-wal-schema-cache-current-source-next623',
            'sqlite-attach-temp-wal-schema-cache-current-source-next624',
            'sqlite-attach-temp-wal-schema-cache-current-source-next625',
            'sqlite-attach-temp-wal-schema-cache-current-source-next626',
            'sqlite-attach-temp-wal-schema-cache-current-source-next627',
            'sqlite-attach-temp-wal-schema-cache-current-source-next628',
            'sqlite-attach-temp-wal-schema-cache-current-source-next629',
            'sqlite-attach-temp-wal-schema-cache-current-source-next630',
            'sqlite-attach-temp-wal-schema-cache-current-source-next631',
            'sqlite-attach-temp-wal-schema-cache-current-source-next632',
            'sqlite-attach-temp-wal-schema-cache-current-source-next633',
            'sqlite-attach-temp-wal-schema-cache-current-source-next634',
            'sqlite-attach-temp-wal-schema-cache-current-source-next635',
            'sqlite-attach-temp-wal-schema-cache-current-source-next636',
            'sqlite-attach-temp-wal-schema-cache-current-source-next605',
            'sqlite-attach-temp-wal-schema-cache-current-source-next606',
            'sqlite-attach-temp-wal-schema-cache-current-source-next607',
            'sqlite-attach-temp-wal-schema-cache-current-source-next608',
            'sqlite-attach-temp-wal-schema-cache-current-source-next609',
            'sqlite-attach-temp-wal-schema-cache-current-source-next610',
            'sqlite-attach-temp-wal-schema-cache-current-source-next611',
            'sqlite-attach-temp-wal-schema-cache-current-source-next612',
            'sqlite-attach-temp-wal-schema-cache-current-source-next613',
            'sqlite-attach-temp-wal-schema-cache-current-source-next614',
            'sqlite-attach-temp-wal-schema-cache-current-source-next615',
            'sqlite-attach-temp-wal-schema-cache-current-source-next616',
            'sqlite-attach-temp-wal-schema-cache-current-source-next617',
            'sqlite-attach-temp-wal-schema-cache-current-source-next618',
            'sqlite-attach-temp-wal-schema-cache-current-source-next619',
            'sqlite-attach-temp-wal-schema-cache-current-source-next620',
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
    public static function currentSourceNext637652(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        return self::buildPlan($schemas, $statements, self::currentSourceNext118120Events($events), $sourceSchema, 'attach-wal-temp-schema-cache-current-source-next637-652', [
            'sqlite-attach-temp-wal-schema-cache-current-source-next637',
            'sqlite-attach-temp-wal-schema-cache-current-source-next638',
            'sqlite-attach-temp-wal-schema-cache-current-source-next639',
            'sqlite-attach-temp-wal-schema-cache-current-source-next640',
            'sqlite-attach-temp-wal-schema-cache-current-source-next641',
            'sqlite-attach-temp-wal-schema-cache-current-source-next642',
            'sqlite-attach-temp-wal-schema-cache-current-source-next643',
            'sqlite-attach-temp-wal-schema-cache-current-source-next644',
            'sqlite-attach-temp-wal-schema-cache-current-source-next645',
            'sqlite-attach-temp-wal-schema-cache-current-source-next646',
            'sqlite-attach-temp-wal-schema-cache-current-source-next647',
            'sqlite-attach-temp-wal-schema-cache-current-source-next648',
            'sqlite-attach-temp-wal-schema-cache-current-source-next649',
            'sqlite-attach-temp-wal-schema-cache-current-source-next650',
            'sqlite-attach-temp-wal-schema-cache-current-source-next651',
            'sqlite-attach-temp-wal-schema-cache-current-source-next652',
            'sqlite-attach-temp-wal-schema-cache-current-source-next621',
            'sqlite-attach-temp-wal-schema-cache-current-source-next622',
            'sqlite-attach-temp-wal-schema-cache-current-source-next623',
            'sqlite-attach-temp-wal-schema-cache-current-source-next624',
            'sqlite-attach-temp-wal-schema-cache-current-source-next625',
            'sqlite-attach-temp-wal-schema-cache-current-source-next626',
            'sqlite-attach-temp-wal-schema-cache-current-source-next627',
            'sqlite-attach-temp-wal-schema-cache-current-source-next628',
            'sqlite-attach-temp-wal-schema-cache-current-source-next629',
            'sqlite-attach-temp-wal-schema-cache-current-source-next630',
            'sqlite-attach-temp-wal-schema-cache-current-source-next631',
            'sqlite-attach-temp-wal-schema-cache-current-source-next632',
            'sqlite-attach-temp-wal-schema-cache-current-source-next633',
            'sqlite-attach-temp-wal-schema-cache-current-source-next634',
            'sqlite-attach-temp-wal-schema-cache-current-source-next635',
            'sqlite-attach-temp-wal-schema-cache-current-source-next636',
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
