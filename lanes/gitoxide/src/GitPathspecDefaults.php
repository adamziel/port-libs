<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitPathspecDefaults
{
    public const MAGIC_SIGNATURE_NONE = 0;
    public const MAGIC_SIGNATURE_ICASE = 1 << 1;

    public const SEARCH_SHELL_GLOB = 'shell-glob';
    public const SEARCH_LITERAL = 'literal';
    public const SEARCH_PATH_AWARE_GLOB = 'path-aware-glob';

    public function __construct(
        public readonly int $signature = self::MAGIC_SIGNATURE_NONE,
        public readonly string $searchMode = self::SEARCH_SHELL_GLOB,
        public readonly bool $literal = false,
    ) {
        if (!in_array($searchMode, [self::SEARCH_SHELL_GLOB, self::SEARCH_LITERAL, self::SEARCH_PATH_AWARE_GLOB], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec search mode: {$searchMode}");
        }
    }

    /**
     * Build defaults from Git's pathspec environment variables without reading
     * process state directly.
     *
     * @param array<string, bool|int|string|null>|callable(string): bool|int|string|null $environment
     */
    public static function fromEnvironment(array|callable $environment): self
    {
        $envBool = static function (string $name) use ($environment): ?bool {
            $value = is_array($environment)
                ? ($environment[$name] ?? null)
                : $environment($name);
            if ($value === null) {
                return null;
            }
            if (is_bool($value)) {
                return $value;
            }
            if (is_int($value)) {
                return $value !== 0;
            }

            return GitConfigValue::parseBoolean((string) $value);
        };

        $literal = $envBool('GIT_LITERAL_PATHSPECS') ?? false;
        $signature = ($envBool('GIT_ICASE_PATHSPECS') ?? false)
            ? self::MAGIC_SIGNATURE_ICASE
            : self::MAGIC_SIGNATURE_NONE;

        if ($literal) {
            return new self($signature, self::SEARCH_LITERAL, true);
        }

        $glob = $envBool('GIT_GLOB_PATHSPECS');
        $searchMode = $glob === true ? self::SEARCH_PATH_AWARE_GLOB : self::SEARCH_SHELL_GLOB;

        $noGlob = $envBool('GIT_NOGLOB_PATHSPECS');
        if ($noGlob !== null) {
            if (($glob ?? false) && $noGlob) {
                throw new \InvalidArgumentException('Glob and no-glob settings are mutually exclusive');
            }
            $searchMode = self::SEARCH_LITERAL;
        }

        return new self($signature, $searchMode, false);
    }
}
