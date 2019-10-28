# Styde / Laravel Test Helpers

This package contains additional helpers to test your Laravel applications.

## Installation

`composer require styde/laravel-test-helpers --dev`

## Faster RefreshDatabase

We provide a custom implementation of the trait `Illuminate\Foundation\Testing\RefreshDatabase` included with Laravel. 

Our trait caches the last time you modified a migration file and only reruns `migrate:fresh` if 
there are new files or changes in your migration paths.

In this way feature tests can run much faster, especially when running one or two tests instead of the whole test suite.

In order to use this trait just include it in your test class and call `$this->refreshDatabase();` in the `setUp`
method like the example below:

```
<?php

namespace Tests;

use Styde\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, TestHelpers, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->refreshDatabase();
    }
}
```
