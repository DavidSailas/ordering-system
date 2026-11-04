<?php
use App\Models\User;
test('the homepage shows the storefront for guests and staff', function () {
    $this->get('/')->assertOk()->assertSee('Your order');
    $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->assertSee('Your order');
});
