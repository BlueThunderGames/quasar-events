<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_an_invalid_password(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_a_failed_login_is_logged_without_credentials(): void
    {
        $events = [];
        Log::listen(function (MessageLogged $event) use (&$events) {
            $events[] = $event;
        });

        User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertHeader('traceparent');

        $failed = array_values(array_filter(
            $events,
            fn (MessageLogged $event) => $event->message === 'auth.login_failed',
        ));

        $this->assertCount(1, $failed);
        $this->assertSame('warning', $failed[0]->level);
        $this->assertTrue($failed[0]->context['user_found']);
        $this->assertIsString($failed[0]->context['trace_id']);
        $encoded = json_encode($failed[0]->context);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('ada@example.com', $encoded);
        $this->assertStringNotContainsString('wrong-password', $encoded);
    }

    public function test_logging_in_replaces_the_previous_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $first = $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $second = $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($first)->getJson('/api/user')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/user')->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_is_throttled_after_repeated_attempts(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_guests_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
