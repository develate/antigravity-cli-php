<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Value;

use Develate\AntigravityCli\Exception\UnsupportedCapability;

final readonly class CapabilitySet
{
    public function supports(Capability $capability): bool
    {
        return $capability->isSupported();
    }

    /**
     * @throws UnsupportedCapability when the CLI offers no way to do this
     */
    public function require(Capability $capability): void
    {
        if (!$this->supports($capability)) {
            throw new UnsupportedCapability(
                $capability->reason() ?? sprintf('Antigravity does not support "%s".', $capability->value),
            );
        }
    }

    /** @return list<Capability> */
    public function all(): array
    {
        return Capability::supported();
    }

    /** @return list<Capability> */
    public function unsupported(): array
    {
        return Capability::unsupported();
    }
}
