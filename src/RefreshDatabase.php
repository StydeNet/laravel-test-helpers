<?php

namespace Styde\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;

trait RefreshDatabase
{
    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function refreshDatabase()
    {
        $this->usingInMemoryDatabase()
            ? $this->refreshInMemoryDatabase()
            : $this->refreshTestDatabase();
    }

    /**
     * Determine if an in-memory database is being used.
     *
     * @return bool
     */
    protected function usingInMemoryDatabase()
    {
        $default = config('database.default');

        return config("database.connections.$default.database") === ':memory:';
    }

    /**
     * Refresh the in-memory database.
     *
     * @return void
     */
    protected function refreshInMemoryDatabase()
    {
        $this->artisan('migrate');

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refreshTestDatabase()
    {
        if ($this->shouldRefreshMirations()) {
            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Begin a database transaction on the testing database.
     *
     * @return void
     */
    public function beginDatabaseTransaction()
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->rollback();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connectionsToTransact()
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact : [null];
    }

    /**
     * Determine if views should be dropped when refreshing the database.
     *
     * @return bool
     */
    protected function shouldDropViews()
    {
        return property_exists($this, 'dropViews')
            ? $this->dropViews : false;
    }

    /**
     * Determine if types should be dropped when refreshing the database.
     *
     * @return bool
     */
    protected function shouldDropTypes()
    {
        return property_exists($this, 'dropTypes')
            ? $this->dropTypes : false;
    }

    /**
     * Determine if the migrations should be refreshed.
     *
     * @return bool
     */
    protected function shouldRefreshMirations()
    {
        if (RefreshDatabaseState::$migrated) {
            return false;
        }

        $lastModifiedMigration = collect($this->migrationPaths())
            ->flatMap(function ($dir) {
                return File::files($dir);
            })
            ->max(function ($file) {
                return $file->getMTime();
            });

        if ($this->app['cache']->store('file')->get('last_modified_migration') == $lastModifiedMigration) {
            return false;
        }

        $this->app['cache']->store('file')->put('last_modified_migration', $lastModifiedMigration);

        return true;
    }

    /**
     * Get all the migration paths.
     *
     * @return array
     */
    protected function migrationPaths()
    {
        return array_merge(
            $this->app['migrator']->paths(),
            [$this->app->databasePath().DIRECTORY_SEPARATOR.'migrations']
        );
    }
}
