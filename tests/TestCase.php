<?php

namespace StuDocu\CacheableEntities\Tests;

use Mockery;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        $this->cleanupMockery();

        parent::tearDown();
    }

    private function cleanupMockery(): void
    {
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        Mockery::close();
    }
}
