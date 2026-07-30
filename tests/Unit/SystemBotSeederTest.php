<?php

namespace Tests\Unit;

use App\Models\SystemBot;
use Database\Seeders\SystemBotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemBotSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
    }

    public function test_skips_when_token_is_empty(): void
    {
        config(['services.telegram.register_bot.token' => '']);

        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseCount('system_bots', 0);
    }

    public function test_skips_when_token_is_whitespace_only(): void
    {
        config(['services.telegram.register_bot.token' => "  \t\n  "]);

        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseCount('system_bots', 0);
    }

    public function test_skips_when_token_is_invalid_format(): void
    {
        config(['services.telegram.register_bot.token' => 'not-a-valid-token']);

        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseCount('system_bots', 0);
    }

    public function test_creates_register_bot_when_missing(): void
    {
        config([
            'services.telegram.register_bot.token' => '123456789:ABCdef_test-token',
            'services.telegram.register_bot.name' => '  Test Register Bot  ',
            'services.telegram.register_bot.username' => ' @test_bot ',
        ]);

        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseHas('system_bots', [
            'type' => 'register',
            'name' => 'Test Register Bot',
            'username' => 'test_bot',
            'token' => '123456789:ABCdef_test-token',
            'is_active' => true,
        ]);
    }

    public function test_does_not_overwrite_existing_register_bot(): void
    {
        SystemBot::query()->create([
            'type' => 'register',
            'name' => 'Production Bot',
            'username' => 'prod_bot',
            'token' => '999888777:ExistingProductionToken',
            'is_active' => true,
        ]);

        config([
            'services.telegram.register_bot.token' => '111222333:NewSeederToken',
            'services.telegram.register_bot.name' => 'New Name',
            'services.telegram.register_bot.username' => 'new_bot',
        ]);

        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseCount('system_bots', 1);

        $bot = SystemBot::query()->where('type', 'register')->firstOrFail();

        $this->assertSame('Production Bot', $bot->name);
        $this->assertSame('prod_bot', $bot->username);
        $this->assertSame('999888777:ExistingProductionToken', $bot->token);
    }

    public function test_seeder_is_idempotent(): void
    {
        config(['services.telegram.register_bot.token' => '555666777:IdempotentToken']);

        $this->seed(SystemBotSeeder::class);
        $this->seed(SystemBotSeeder::class);

        $this->assertDatabaseCount('system_bots', 1);
        $this->assertSame(
            '555666777:IdempotentToken',
            SystemBot::query()->where('type', 'register')->value('token')
        );
    }
}
