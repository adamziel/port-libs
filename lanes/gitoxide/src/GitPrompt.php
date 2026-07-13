<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitPrompt
{
    public const MODE_VISIBLE = 'visible';
    public const MODE_HIDDEN = 'hidden';
    public const MODE_DISABLE = 'disable';

    public function __construct(
        public readonly ?string $askpass = null,
        public readonly string $mode = self::MODE_HIDDEN,
    ) {
        if (!in_array($this->mode, [self::MODE_VISIBLE, self::MODE_HIDDEN, self::MODE_DISABLE], true)) {
            throw new \InvalidArgumentException("Invalid Git prompt mode: {$this->mode}");
        }
    }

    /**
     * @param array<string, mixed> $environment
     */
    public function applyEnvironment(
        bool $useGitAskpass,
        bool $useSshAskpass,
        bool $useGitTerminalPrompt,
        array $environment,
    ): self {
        $askpass = $this->askpass;
        $mode = $this->mode;

        if ($useGitAskpass) {
            $gitAskpass = self::environmentValue($environment, 'GIT_ASKPASS');
            if ($gitAskpass !== null) {
                $askpass = $gitAskpass;
            }
        }

        if ($askpass === null && $useSshAskpass) {
            $sshAskpass = self::environmentValue($environment, 'SSH_ASKPASS');
            if ($sshAskpass !== null) {
                $askpass = $sshAskpass;
            }
        }

        if ($useGitTerminalPrompt) {
            $terminalPrompt = self::environmentValue($environment, 'GIT_TERMINAL_PROMPT');
            if ($terminalPrompt !== null) {
                try {
                    if (!GitConfigValue::parseBoolean($terminalPrompt)) {
                        $mode = self::MODE_DISABLE;
                    }
                } catch (\InvalidArgumentException) {
                }
            }
        }

        return new self($askpass, $mode);
    }

    /**
     * @param array<string, mixed> $environment
     */
    private static function environmentValue(array $environment, string $name): ?string
    {
        if (!array_key_exists($name, $environment) || $environment[$name] === null) {
            return null;
        }

        return (string) $environment[$name];
    }
}
