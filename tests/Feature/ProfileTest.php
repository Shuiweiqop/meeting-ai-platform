<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('a valid Slack webhook url is accepted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/xyz',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->slack_webhook_url)->toBe('https://hooks.slack.com/services/T000/B000/xyz');
});

test('a non-Slack webhook host is rejected (SSRF guard)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'slack_webhook_url' => 'http://169.254.169.254/latest/meta-data/',
        ])
        ->assertSessionHasErrors('slack_webhook_url');

    expect($user->fresh()->slack_webhook_url)->toBeNull();
});

test('a non-https Slack url is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'slack_webhook_url' => 'http://hooks.slack.com/services/T000/B000/xyz',
        ])
        ->assertSessionHasErrors('slack_webhook_url');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
