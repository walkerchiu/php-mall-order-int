<?php

namespace WalkerChiu\MallOrder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use WalkerChiu\MallOrder\Models\Constants\OrderState;
use WalkerChiu\MallOrder\Models\Entities\Order;
use WalkerChiu\MallOrder\Models\Repositories\OrderRepository;
use WalkerChiu\MallOrder\Models\Observers\OrderObserver;

class OrderRepositoryTest extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected $repository;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        //$this->loadLaravelMigrations(['--database' => 'mysql']);
        $this->loadMigrationsFrom(__DIR__ .'/../migrations');
        $this->withFactories(__DIR__ .'/../../src/database/factories');

        $this->repository = $this->app->make(OrderRepository::class);
    }

    /**
     * To load your package service provider, override the getPackageProviders.
     *
     * @param \Illuminate\Foundation\Application  $app
     * @return Array
     */
    protected function getPackageProviders($app)
    {
        return [\WalkerChiu\Core\CoreServiceProvider::class,
                \WalkerChiu\MallOrder\MallOrderServiceProvider::class,
                \WalkerChiu\MorphAddress\MorphAddressServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
    }

    /**
     * A basic functional test on OrderRepository.
     *
     * For WalkerChiu\Core\Models\Repositories\Repository
     *
     * @return void
     */
    public function testOrderRepository()
    {
        // Config
        Config::set('wk-core.onoff.core-lang_core', 0);
        Config::set('wk-mall-order.onoff.core-lang_core', 0);
        Config::set('wk-core.lang_log', 1);
        Config::set('wk-mall-order.lang_log', 1);
        Config::set('wk-core.soft_delete', 1);
        Config::set('wk-mall-order.soft_delete', 1);

        $faker = \Faker\Factory::create();

        DB::table(config('wk-core.table.user'))->insert([
            'name'     => $faker->username,
            'email'    => $faker->email,
            'password' => $faker->password
        ]);

        // Give
        $faker = \Faker\Factory::create();
        for ($i=1; $i<=3; $i++)
            $this->repository->save([
                'user_id'    => 1,
                'identifier' => $faker->slug,
                'state'      => $faker->randomElement(OrderState::getCodes())
            ]);

        // Get and Count records after creation
            // When
            $records = $this->repository->get();
            $count   = $this->repository->count();
            // Then
            $this->assertCount(3, $records);
            $this->assertEquals(3, $count);

        // Find someone
            // When
            $record = $this->repository->find(1);
            // Then
            $this->assertNotNull($record);

            // When
            $record = $this->repository->find(4);
            // Then
            $this->assertNull($record);

        // Delete someone
            // When
            $this->repository->deleteByIds([1]);
            $count = $this->repository->count();
            // Then
            $this->assertEquals(2, $count);

            // When
            $this->repository->deleteByExceptIds([3]);
            $count = $this->repository->count();
            $record = $this->repository->find(3);
            // Then
            $this->assertEquals(1, $count);
            $this->assertNotNull($record);

            // When
            $count = $this->repository->where('id', '>', 0)->count();
            // Then
            $this->assertEquals(1, $count);

            // When
            $count = $this->repository->whereWithTrashed('id', '>', 0)->count();
            // Then
            $this->assertEquals(3, $count);

            // When
            $count = $this->repository->whereOnlyTrashed('id', '>', 0)->count();
            // Then
            $this->assertEquals(2, $count);

        // Force delete someone
            // When
            $this->repository->forcedeleteByIds([3]);
            $records = $this->repository->get();
            // Then
            $this->assertCount(0, $records);

        // Restore records
            // When
            $this->repository->restoreByIds([1, 2]);
            $count = $this->repository->count();
            // Then
            $this->assertEquals(2, $count);
    }

    /**
     * Unit test about Query List on OrderRepository.
     *
     * For WalkerChiu\Core\Models\Repositories\RepositoryTrait
     *     WalkerChiu\MallOrder\Models\Repositories\OrderRepository
     *
     * @return void
     */
    public function testQueryList()
    {
        // Config
        Config::set('wk-core.onoff.core-lang_core', 0);
        Config::set('wk-mall-order.onoff.core-lang_core', 0);
        Config::set('wk-core.lang_log', 1);
        Config::set('wk-mall-order.lang_log', 1);
        Config::set('wk-core.soft_delete', 1);
        Config::set('wk-mall-order.soft_delete', 1);

        $faker = \Faker\Factory::create();

        // Give
        factory(Order::class, 4)->create();

        // Get query
            // When
            sleep(1);
            $this->repository->find(3)->touch();
            $records = $this->repository->get()
                                        ->sortByDESC('updated_at');
            // Then
            $this->assertCount(4, $records);

            // When
            $record = $records->first();
            // Then
            $this->assertArrayNotHasKey('deleted_at', $record->toArray());
            $this->assertEquals(3, $record->id);

        // Get query of trashed records
            // When
            $this->repository->deleteByIds([4]);
            $this->repository->deleteByIds([1]);
            $records = $this->repository->ofTrash(null, null)->get();
            // Then
            $this->assertCount(2, $records);

           // When
            $record = $records->first();
            // Then
            $this->assertArrayHasKey('deleted_at', $record);
            $this->assertEquals(1, $record->id);
    }

    /**
     * Unit test about Show Order on OrderRepository.
     *
     * For WalkerChiu\Core\Models\Repositories\RepositoryTrait
     *     WalkerChiu\MallOrder\Models\Repositories\OrderRepository
     *
     * @return void
     */
    public function testShowOrder()
    {
        // Config
        Config::set('wk-core.onoff.core-lang_core', 0);
        Config::set('wk-mall-order.onoff.core-lang_core', 0);
        Config::set('wk-core.lang_log', 1);
        Config::set('wk-mall-order.lang_log', 1);
        Config::set('wk-core.soft_delete', 1);
        Config::set('wk-mall-order.soft_delete', 1);

        $faker = \Faker\Factory::create();

        // Give
        $db_order = factory(Order::class)->create();
        // When
        $order = $this->repository->showById($db_order->id, 'en_us');
        // Then
        $this->assertArrayHasKey('profile', $order);
        $this->assertArrayHasKey('addresses', $order);
        $this->assertArrayHasKey('items', $order);

        $this->assertCount(3, $order['addresses']);
        $this->assertArrayHasKey('recipient', $order['addresses']);
        $this->assertArrayHasKey('bill', $order['addresses']);
        $this->assertArrayHasKey('invoice', $order['addresses']);
    }
}
