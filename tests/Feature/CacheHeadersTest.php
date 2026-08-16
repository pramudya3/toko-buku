<?php

use App\Models\User;

it('sets no-store cache headers on admin pages', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeader('Pragma', 'no-cache');
});

it('sets no-store cache headers on public pages', function (): void {
    $this->get('/')
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeader('Pragma', 'no-cache');
});
