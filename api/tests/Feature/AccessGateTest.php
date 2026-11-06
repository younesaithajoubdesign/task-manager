<?php

use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaskManagerSeeder::class);
});

function enableGate(string $password = 'correct horse'): void
{
    config(['gate.password_hash' => Hash::make($password)]);
}

function tokenFor(string $password = 'correct horse'): string
{
    return test()->postJson(route('gate.store'), ['password' => $password])->assertOk()->json('data.token');
}

it('leaves the API open while no password is configured', function () {
    $this->getJson(route('gate.show'))->assertJsonPath('data.enabled', false);
    $this->getJson(route('tasks.index'))->assertOk();
});

it('returns 401 on every protected route without a token', function (string $method, string $uri) {
    enableGate();

    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('message', 'Password required.');
})->with([
    ['GET', '/api/tasks'],
    ['GET', '/api/today'],
    ['GET', '/api/categories'],
    ['GET', '/api/timer/running'],
    ['POST', '/api/tasks'],
]);

it('returns 422 for a wrong password', function () {
    enableGate();

    $this->postJson(route('gate.store'), ['password' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Wrong password.']);
});

it('opens the API with the token given for the right password', function () {
    enableGate();
    $token = tokenFor();

    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$token}"])->assertOk();
    $this->getJson(route('gate.show'), ['Authorization' => "Bearer {$token}"])
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.authorized', true);
});

it('rejects a made-up token', function () {
    enableGate();

    $this->getJson(route('tasks.index'), ['Authorization' => 'Bearer '.str_repeat('a', 64)])->assertUnauthorized();
});

it('signs every browser out when the password changes', function () {
    enableGate('first password');
    $oldToken = tokenFor('first password');

    enableGate('second password');

    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$oldToken}"])->assertUnauthorized();
});

it('returns 429 after five attempts in a minute', function () {
    enableGate();

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('gate.store'), ['password' => 'nope'])->assertUnprocessable();
    }

    $this->postJson(route('gate.store'), ['password' => 'nope'])->assertTooManyRequests();
});

it('expires a token after 90 days', function () {
    enableGate();
    $token = tokenFor();

    $this->travel(89)->days();
    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->travel(2)->days();
    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
});

it('rejects a token whose expiry was pushed back by hand', function () {
    enableGate();
    [, $signature] = explode('.', tokenFor());
    $forged = now()->addYears(5)->getTimestamp().'.'.$signature;

    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$forged}"])->assertUnauthorized();
});

it('logs a browser out without touching the others', function () {
    enableGate();
    $phone = tokenFor();
    $this->travel(1)->seconds();
    $laptop = tokenFor();

    $this->deleteJson(route('gate.destroy'), [], ['Authorization' => "Bearer {$phone}"])->assertNoContent();

    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$phone}"])->assertUnauthorized();
    $this->getJson(route('tasks.index'), ['Authorization' => "Bearer {$laptop}"])->assertOk();
});

describe('gate:password command', function () {
    beforeEach(function () {
        $this->envDir = sys_get_temp_dir().'/gate-test-'.uniqid();
        mkdir($this->envDir);
        file_put_contents("{$this->envDir}/.env", "APP_NAME=Test\nAPP_PASSWORD_HASH='old'\n");
        $this->app->useEnvironmentPath($this->envDir);
    });

    afterEach(function () {
        @unlink("{$this->envDir}/.env");
        @rmdir($this->envDir);
    });

    it('writes a bcrypt hash of the typed password, replacing the old one', function () {
        $this->artisan('gate:password')
            ->expectsQuestion('New password', 'long enough secret')
            ->expectsQuestion('Repeat it', 'long enough secret')
            ->assertSuccessful();

        $env = file_get_contents("{$this->envDir}/.env");
        preg_match("/^APP_PASSWORD_HASH='(.+)'$/m", $env, $match);

        expect(substr_count($env, 'APP_PASSWORD_HASH='))->toBe(1)
            ->and($env)->toStartWith('APP_NAME=Test')
            ->and($env)->not->toContain('long enough secret')
            ->and(Hash::check('long enough secret', $match[1]))->toBeTrue();
    });

    it('changes nothing when the two entries differ', function () {
        $this->artisan('gate:password')
            ->expectsQuestion('New password', 'long enough secret')
            ->expectsQuestion('Repeat it', 'something else')
            ->assertFailed();

        expect(file_get_contents("{$this->envDir}/.env"))->toContain("APP_PASSWORD_HASH='old'");
    });

    it('accepts a 4-digit code', function () {
        $this->artisan('gate:password')
            ->expectsQuestion('New password', '4821')
            ->expectsQuestion('Repeat it', '4821')
            ->assertSuccessful();

        preg_match("/^APP_PASSWORD_HASH='(.+)'$/m", file_get_contents("{$this->envDir}/.env"), $match);

        expect(Hash::check('4821', $match[1]))->toBeTrue();
    });

    it('refuses fewer than 4 characters', function () {
        $this->artisan('gate:password')
            ->expectsQuestion('New password', '482')
            ->expectsOutputToContain('Use at least 4 characters.')
            ->assertFailed();

        expect(file_get_contents("{$this->envDir}/.env"))->toContain("APP_PASSWORD_HASH='old'");
    });

    it('removes the password with --clear', function () {
        $this->artisan('gate:password', ['--clear' => true])->assertSuccessful();

        expect(file_get_contents("{$this->envDir}/.env"))->not->toContain('APP_PASSWORD_HASH');
    });
});
