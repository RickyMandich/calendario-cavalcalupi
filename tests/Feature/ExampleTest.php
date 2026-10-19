<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');
    $response->assertRedirect('/calendar');

    $calendar = $this->get('/calendar');
    $calendar->assertStatus(200);
});
