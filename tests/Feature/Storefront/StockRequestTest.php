<?php

use App\Models\Book;
use App\Models\StockRequest;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
});

it('redirects guests to login when requesting stock', function (): void {
    $book = Book::factory()->create(['stok' => 0, 'aktif' => true]);

    $this->post(route('stock-requests.store', $book))
        ->assertRedirect(route('login'));
});

it('stores a stock request for a logged-in customer', function (): void {
    $book = Book::factory()->create(['stok' => 0, 'aktif' => true]);

    $this->actingAs($this->customer)
        ->post(route('stock-requests.store', $book), [
            'whatsapp_number' => '081234567890',
        ])
        ->assertRedirect();

    expect(StockRequest::where('book_id', $book->id)->count())->toBe(1)
        ->and(StockRequest::where('user_id', $this->customer->id)->exists())->toBeTrue()
        // Nomor WA tersimpan di profil user.
        ->and($this->customer->fresh()->whatsapp_number)->toBe('081234567890');
});

it('replaces the profile WhatsApp number when it changes on request', function (): void {
    $book = Book::factory()->create(['stok' => 0, 'aktif' => true]);
    $this->customer->update(['whatsapp_number' => '081111111111']);

    $this->actingAs($this->customer)
        ->post(route('stock-requests.store', $book), [
            'whatsapp_number' => '628999999999',
        ])
        ->assertRedirect();

    expect($this->customer->fresh()->whatsapp_number)->toBe('628999999999');
});

it('requires a valid WhatsApp number for a stock request', function (): void {
    $book = Book::factory()->create(['stok' => 0, 'aktif' => true]);

    $this->actingAs($this->customer)
        ->post(route('stock-requests.store', $book))
        ->assertSessionHasErrors('whatsapp_number');

    $this->actingAs($this->customer)
        ->post(route('stock-requests.store', $book), [
            'whatsapp_number' => 'salah-format',
        ])
        ->assertSessionHasErrors('whatsapp_number');

    expect(StockRequest::where('book_id', $book->id)->count())->toBe(0);
});

it('keeps a single stock request per user per book', function (): void {
    $book = Book::factory()->create(['stok' => 0, 'aktif' => true]);

    $this->actingAs($this->customer)->post(route('stock-requests.store', $book), ['whatsapp_number' => '081234567890']);
    $this->actingAs($this->customer)->post(route('stock-requests.store', $book), ['whatsapp_number' => '081234567890']);

    expect(StockRequest::where('book_id', $book->id)->count())->toBe(1);
});

it('shows request totals per book on the admin index', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Laris', 'stok' => 0, 'aktif' => true]);
    StockRequest::factory()->create(['book_id' => $book->id, 'user_id' => $this->customer->id]);
    StockRequest::factory()->create(['book_id' => $book->id]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.stock-requests.index')));

    expect($props['requests']['total'])->toBe(1)
        ->and($props['requests']['data'])->toHaveCount(1)
        ->and($props['requests']['data'][0]['book']['judul'])->toBe('Buku Laris')
        ->and($props['requests']['data'][0]['total_requests'])->toBe(2);
});

it('lists the requesting users on the admin detail page', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Laris', 'stok' => 0, 'aktif' => true]);
    StockRequest::factory()->create(['book_id' => $book->id, 'user_id' => $this->customer->id]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.stock-requests.show', $book)));

    expect($props['book']['judul'])->toBe('Buku Laris')
        ->and($props['requests']['total'])->toBe(1)
        ->and($props['requests']['data'])->toHaveCount(1)
        ->and($props['requests']['data'][0]['user']['name'])->toBe($this->customer->name);
});

it('paginates the requesting users on the admin detail page', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Laris', 'stok' => 0, 'aktif' => true]);
    StockRequest::factory()->count(12)->create(['book_id' => $book->id]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.stock-requests.show', $book)));

    expect($props['requests']['total'])->toBe(12)
        ->and($props['requests']['data'])->toHaveCount(10)
        ->and($props['requests']['last_page'])->toBe(2);
});
