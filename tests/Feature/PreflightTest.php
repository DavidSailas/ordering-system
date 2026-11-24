<?php

use App\Models\SchoolMember;
use App\Models\User;
use App\Support\SchoolId;

/* php artisan canteen:preflight: the go-live safety check. */

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.debug' => false]);
});

test('the safety check passes with safe settings', function () {
    $this->artisan('canteen:preflight')->assertExitCode(0);
});

test('the safety check fails while debug is on', function () {
    config(['app.debug' => true]);

    $this->artisan('canteen:preflight')->expectsOutputToContain('APP_DEBUG is on')->assertExitCode(1);
});

test('the safety check fails when there is no app key', function () {
    config(['app.key' => '']);

    $this->artisan('canteen:preflight')->expectsOutputToContain('APP_KEY is empty')->assertExitCode(1);
});

test('the safety check fails while a staff account still has the demo password', function () {
    User::factory()->cashier()->create(['email' => 'cashier@example.com', 'password' => 'password']);

    $this->artisan('canteen:preflight')->expectsOutputToContain('demo password')->assertExitCode(1);
});

test('strong passwords pass the safety check', function () {
    User::factory()->cashier()->create(['password' => 'a-Long-Unique-Passphrase-2026']);

    $this->artisan('canteen:preflight')->assertExitCode(0);
});

test('the safety check warns about the timezone but does not fail', function () {
    config(['app.timezone' => 'UTC']);

    $this->artisan('canteen:preflight')->expectsOutputToContain('Asia/Manila')->assertExitCode(0);
});

test('the safety check fails when the ID is compulsory but the school list is empty', function () {
    SchoolId::setRequired(true);

    $this->artisan('canteen:preflight')->expectsOutputToContain('school list is empty')->assertExitCode(1);

    SchoolMember::factory()->create();
    $this->artisan('canteen:preflight')->assertExitCode(0);
});
