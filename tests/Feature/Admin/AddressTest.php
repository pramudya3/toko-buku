<?php

use App\Models\User;
use Database\Seeders\WilayahSeeder;

beforeEach(function (): void {
    $this->seed(WilayahSeeder::class);

    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('lists all provinces', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('admin.address.provinces'))
        ->assertOk()
        ->assertJsonCount(34)
        ->assertJsonFragment(['code' => '35', 'name' => 'JAWA TIMUR']);
});

it('lists cities filtered by province code', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('admin.address.cities', ['province_code' => '35']))
        ->assertOk()
        ->assertJsonCount(38)
        ->assertJsonFragment(['code' => '3573', 'name' => 'KOTA MALANG']);
});

it('lists districts filtered by city code', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('admin.address.districts', ['city_code' => '3573']))
        ->assertOk()
        ->assertJsonCount(5)
        ->assertJsonFragment(['code' => '3573010', 'name' => 'KLOJEN']);
});

it('lists villages filtered by district code', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('admin.address.villages', ['district_code' => '3573010']))
        ->assertOk()
        ->assertJsonFragment(['code' => '3573010001']);
});

it('guards address endpoints for admins only', function (): void {
    $this->getJson(route('admin.address.provinces'))->assertUnauthorized();
});
