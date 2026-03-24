<?php

declare(strict_types=1);

namespace Handlr\Ab;

use Handlr\Ab\Data\AbEventsTable;
use Handlr\Ab\Data\AbTestsTable;
use Handlr\Ab\Domain\AbEventRecord;
use Handlr\Ab\Domain\AbTestRecord;

class AbService
{
    /** @var AbTestRecord[]|null Cached active tests */
    private ?array $activeTests = null;

    public function __construct(
        private AbTestsTable $testsTable,
        private AbEventsTable $eventsTable,
    ) {}

    /**
     * Get variant assignments for a session across all active tests.
     *
     * @return array<string, string> Map of test name → assigned variant
     */
    public function getAssignments(string $sessionId): array
    {
        $tests = $this->getActiveTests();
        $assignments = [];

        foreach ($tests as $test) {
            $variants = $test->getVariants();
            if (empty($variants)) {
                continue;
            }

            $assignments[$test->name] = $this->assignVariant($sessionId, $test->name, $variants);
        }

        return $assignments;
    }

    /**
     * Deterministically assign a variant based on session and test name.
     * Same session + test name always produces the same variant.
     */
    public function assignVariant(string $sessionId, string $testName, array $variants): string
    {
        $hash = crc32($sessionId . ':' . $testName);
        $index = abs($hash) % count($variants);
        return $variants[$index];
    }

    /**
     * Record an A/B event (impression, signup, click, etc.).
     */
    public function recordEvent(string $sessionId, string $event, array $assignments): void
    {
        $tests = $this->getActiveTests();
        $testsByName = [];
        foreach ($tests as $test) {
            $testsByName[$test->name] = $test;
        }

        foreach ($assignments as $testName => $variant) {
            if (!isset($testsByName[$testName])) {
                continue;
            }

            $this->eventsTable->upsertEvent(
                $testsByName[$testName]->id,
                $variant,
                $sessionId,
                $event,
            );
        }
    }

    /**
     * Get all active tests (cached per request).
     *
     * @return AbTestRecord[]
     */
    public function getActiveTests(): array
    {
        if ($this->activeTests === null) {
            $this->activeTests = $this->testsTable->findWhere([], ['status' => 'active']);
        }

        return $this->activeTests;
    }
}
