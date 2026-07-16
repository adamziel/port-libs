<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialHelperInvocation
{
    /**
     * @param callable(string, string): (?string) $helper
     */
    public static function get(CredentialContext $context, callable $helper): CredentialHelperOutcome
    {
        $stdout = $helper('get', $context->storageBytes());
        if (!is_string($stdout)) {
            throw new \LogicException('Credential helper get invocation must return stdout bytes');
        }

        $context = CredentialContext::fromBytes($stdout);

        return new CredentialHelperOutcome(
            username: $context->username,
            password: $context->password,
            oauthRefreshToken: $context->oauthRefreshToken,
            quit: $context->quit ?? false,
            nextActionBytes: $stdout,
        );
    }

    /**
     * @param callable(string, string): mixed $helper
     */
    public static function store(CredentialHelperOutcome|CredentialContext|string $target, callable $helper): void
    {
        $helper('store', self::payloadFor($target) . "\n");
    }

    /**
     * @param callable(string, string): mixed $helper
     */
    public static function erase(CredentialHelperOutcome|CredentialContext|string $target, callable $helper): void
    {
        $helper('erase', self::payloadFor($target) . "\n");
    }

    private static function payloadFor(CredentialHelperOutcome|CredentialContext|string $target): string
    {
        if ($target instanceof CredentialHelperOutcome) {
            return $target->nextActionBytes();
        }
        if ($target instanceof CredentialContext) {
            return $target->storageBytes();
        }

        return $target;
    }
}
