<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealDateAffinityDynamicCorpusPlan
{
    /**
     * @return array{upstream:string,expr:string,result:string}
     */
    public static function unixepochFractionCase(int $millisecond): array
    {
        if ($millisecond < 0 || $millisecond > 999) {
            throw new \InvalidArgumentException('SQLite date.test unixepoch millisecond must be between 0 and 999');
        }

        return [
            'upstream' => sprintf('date.test date-2.2c-%d', $millisecond),
            'expr' => sprintf("strftime('%%H:%%M:%%f',1237962480.%03d,'unixepoch')", $millisecond),
            'result' => sprintf('06:28:00.%03d', $millisecond),
        ];
    }

    /**
     * @return array{upstream:string,expr:string,result:string|null,deterministic:bool}
     */
    public static function dateModifierCase(string $base, string $modifier, string $upstream): array
    {
        $result = self::applyDateModifier($base, $modifier);

        return [
            'upstream' => $upstream,
            'expr' => "date('{$base}','{$modifier}')",
            'result' => $result,
            'deterministic' => $result !== null,
        ];
    }

    /**
     * @param list<mixed> $arguments
     * @return array{ok:bool,error:string|null,context:string,function:string,arguments:list<mixed>,upstream:string}
     */
    public static function dateSchemaUse(string $function, array $arguments, string $context, string $upstream): array
    {
        $function = strtolower($function);
        if (!in_array($function, ['date', 'datetime', 'julianday'], true)) {
            throw new \InvalidArgumentException('SQLite date2 schema use supports date, datetime, and julianday');
        }

        $context = strtolower($context);
        $contextLabel = match ($context) {
            'check' => 'CHECK constraint',
            'index' => 'index',
            'generated' => 'generated column',
            default => throw new \InvalidArgumentException('SQLite date2 schema context must be check, index, or generated'),
        };
        $article = $context === 'index' ? 'an' : 'a';

        $nonDeterministic = $arguments === [];
        foreach ($arguments as $argument) {
            if (!is_scalar($argument) && $argument !== null) {
                throw new \InvalidArgumentException('SQLite date2 schema arguments must be scalar or NULL');
            }
            $text = strtolower(trim((string) $argument));
            if ($text === 'now' || $text === 'localtime' || $text === 'utc') {
                $nonDeterministic = true;
            }
        }

        return [
            'ok' => !$nonDeterministic,
            'error' => $nonDeterministic ? "non-deterministic use of {$function}() in {$article} {$contextLabel}" : null,
            'context' => $context,
            'function' => $function,
            'arguments' => $arguments,
            'upstream' => $upstream,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,string> $affinities
     * @return list<array<string,array{value:mixed,typeof:string}>>
     */
    public static function affinity2InsertedRows(array $rows, array $affinities): array
    {
        $coerced = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rows, $affinities);
        $out = [];
        foreach ($coerced as $row) {
            $typed = [];
            foreach ($row as $column => $value) {
                $typed[$column] = [
                    'value' => $value,
                    'typeof' => SQLiteRealExpressionAffinityCorpusPlan::storageClass($value),
                ];
            }
            $out[] = $typed;
        }

        return $out;
    }

    /**
     * @return array{result:bool|null,left:mixed,right:mixed,leftStorageClass:string,rightStorageClass:string,upstream:string}
     */
    public static function affinity2Comparison(
        mixed $left,
        mixed $right,
        string $operator,
        string $leftAffinity,
        string $rightAffinity,
        string $upstream
    ): array {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
            $left,
            $right,
            $operator,
            $leftAffinity,
            $rightAffinity
        );
        $comparison['upstream'] = $upstream;

        return $comparison;
    }

    private static function applyDateModifier(string $base, string $modifier): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $base, new \DateTimeZone('UTC'));
        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $base) {
            return null;
        }

        $modifier = trim($modifier);
        if (preg_match('/^weekday\s+([0-6])$/', $modifier, $matches) === 1) {
            $target = (int) $matches[1];
            $current = (int) $date->format('w');
            $delta = ($target - $current + 7) % 7;

            return $date->modify("+{$delta} days")->format('Y-m-d');
        }

        if ($modifier === 'start of month') {
            return $date->modify('first day of this month')->format('Y-m-01');
        }
        if ($modifier === 'start of year') {
            return $date->format('Y') . '-01-01';
        }
        if ($modifier === 'start of day') {
            return $date->format('Y-m-d');
        }

        if (preg_match('/^([+-]?\d+)\s+(day|days|hour|hours|minute|minutes|second|seconds)$/', $modifier, $matches) === 1) {
            $amount = (int) $matches[1];
            $unit = rtrim($matches[2], 's');
            $changed = $date->modify(sprintf('%+d %s', $amount, $unit));

            return $changed instanceof \DateTimeImmutable ? $changed->format('Y-m-d') : null;
        }

        return null;
    }
}
