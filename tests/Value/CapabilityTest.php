<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Tests\Value;

use Develate\AntigravityCli\Exception\UnsupportedCapability;
use Develate\AntigravityCli\Value\Capability;
use Develate\AntigravityCli\Value\CapabilitySet;
use PHPUnit\Framework\TestCase;

final class CapabilityTest extends TestCase
{
    public function test_supported_capabilities_are_reported(): void
    {
        $capabilities = new CapabilitySet;

        self::assertTrue($capabilities->supports(Capability::ResumeByConversationId));
        self::assertTrue($capabilities->supports(Capability::PersistentSession));
        self::assertTrue($capabilities->supports(Capability::Cancellation));
    }

    /**
     * These are missing from the CLI's headless interface. Naming them, rather
     * than approximating them, is the point of the list.
     */
    public function test_interactive_only_features_are_refused_with_a_reason(): void
    {
        $capabilities = new CapabilitySet;

        foreach (Capability::unsupported() as $capability) {
            self::assertFalse($capabilities->supports($capability));
            self::assertNotNull($capability->reason());
        }

        $this->expectException(UnsupportedCapability::class);
        $this->expectExceptionMessageMatches('/permission prompts at its own terminal/');

        $capabilities->require(Capability::InteractiveApproval);
    }

    public function test_requiring_a_supported_capability_passes(): void
    {
        (new CapabilitySet)->require(Capability::ModelSelection);

        $this->expectNotToPerformAssertions();
    }

    public function test_images_and_forking_are_not_emulated(): void
    {
        $capabilities = new CapabilitySet;

        self::assertFalse($capabilities->supports(Capability::ImageInput));
        self::assertFalse($capabilities->supports(Capability::ForkConversation));
        self::assertFalse($capabilities->supports(Capability::ManagedAuthentication));
    }
}
