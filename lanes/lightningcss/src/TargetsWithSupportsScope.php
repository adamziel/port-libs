<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class TargetsWithSupportsScope
{
    /** @var array<string, mixed> */
    private array $targets;

    /** @var list<array<string, true>> */
    private array $stack = [];

    /**
     * @param array<string, mixed> $targets
     */
    public function __construct(array $targets = [])
    {
        $this->targets = $targets;
        $this->targets['exclude'] = self::normalizeFeatureSet($targets['exclude'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $current = $this->targets;
        $current['exclude'] = array_keys($this->targets['exclude']);

        return $current;
    }

    public function excludes(string $feature): bool
    {
        return isset($this->targets['exclude'][self::normalizeFeatureName($feature)]);
    }

    /**
     * @param string|array<array-key, mixed> $features
     */
    public function enterSupports(string|array $features): bool
    {
        $featureSet = self::normalizeFeatureSet($features);
        if ($featureSet === []) {
            return false;
        }

        $newlyExcluded = array_diff_key($featureSet, $this->targets['exclude']);
        if ($newlyExcluded === []) {
            return false;
        }

        $this->stack[] = $newlyExcluded;
        foreach ($newlyExcluded as $feature => $_) {
            $this->targets['exclude'][$feature] = true;
        }

        return true;
    }

    public function exitSupports(): void
    {
        $last = array_pop($this->stack);
        if ($last === null) {
            return;
        }

        foreach ($last as $feature => $_) {
            unset($this->targets['exclude'][$feature]);
        }
    }

    private static function normalizeFeatureName(string $feature): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($feature)) ?? strtolower($feature);
    }

    /**
     * @param mixed $features
     * @return array<string, true>
     */
    private static function normalizeFeatureSet(mixed $features): array
    {
        if (is_string($features)) {
            $feature = self::normalizeFeatureName($features);

            return $feature === '' ? [] : [$feature => true];
        }

        if (!is_array($features)) {
            return [];
        }

        $normalized = [];
        foreach ($features as $name => $enabled) {
            if (is_string($name)) {
                if ((bool) $enabled) {
                    $feature = self::normalizeFeatureName($name);
                    if ($feature !== '') {
                        $normalized[$feature] = true;
                    }
                }
                continue;
            }

            if (is_string($enabled)) {
                $feature = self::normalizeFeatureName($enabled);
                if ($feature !== '') {
                    $normalized[$feature] = true;
                }
            }
        }

        return $normalized;
    }
}
