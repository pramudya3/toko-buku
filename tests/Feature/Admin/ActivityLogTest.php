<?php

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\KasCategory;
use App\Models\KasSubCategory;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    // Seed kategori Kas untuk test manual cash entries (OPSI A + kategori wajib)
    $this->kasCategory = KasCategory::create(['nama' => 'Kategori Log '.uniqid(), 'sort_order' => 1]);
    $this->kasSub = KasSubCategory::create(['cash_flow_category_id' => $this->kasCategory->id, 'nama' => 'Sub Log '.uniqid(), 'sort_order' => 1]);
});

it('records login and logout events', function (): void {
    $this->actingAs($this->admin)
        ->post(route('logout'));

    expect(ActivityLog::where('action', ActivityAction::Logout->value)->exists())->toBeTrue();
});

it('records login failures with ip address', function (): void {
    $this->post(route('login'), [
        'email' => 'tidak-ada@tokobuku.test',
        'password' => 'salah',
    ]);

    $log = ActivityLog::where('action', ActivityAction::LoginFailed->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('tidak-ada@tokobuku.test');
});

it('records book creation via observer', function (): void {
    $this->actingAs($this->admin);

    Book::factory()->create(['judul' => 'Buku Observasi']);

    $log = ActivityLog::where('action', ActivityAction::BookCreate->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe("Buku 'Buku Observasi' dibuat")
        ->and($log->subject_type)->toBe(Book::class);
});

it('records settings updates but never the api key value', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.api-key.update'), [
            'api_key' => 'biteship_production_rahasia_super_sekali_123456',
        ]);

    $logs = ActivityLog::where('action', ActivityAction::SettingsUpdate->value)->get();

    expect($logs)->not->toBeEmpty()
        ->and($logs->pluck('description')->implode(' '))->not->toContain('biteship_production_rahasia');
});

it('records manual cash entries', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'income',
            'entry_date' => now()->toDateString(),
            'amount' => 100000,
            'description' => 'Tunai toko',
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
        ]);

    $log = ActivityLog::where('action', ActivityAction::CashEntry->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('Rp 100.000')
        ->and($log->metadata['amount'])->toBe(100000);
});

it('records order status transitions with metadata', function (): void {
    $order = Order::factory()->create(['status' => 'menunggu_konfirmasi']);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => 'batal',
        ]);

    $log = ActivityLog::where('action', ActivityAction::OrderStatus->value)
        ->where('subject_id', $order->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['from'])->toBe('menunggu_konfirmasi')
        ->and($log->metadata['to'])->toBe('batal');
});

it('filters the activity log by date, user, action and search', function (): void {
    $other = User::factory()->admin()->create();

    ActivityLog::create(['user_id' => $this->admin->id, 'action' => ActivityAction::BookCreate, 'description' => "Buku 'A' dibuat", 'created_at' => now()->subDays(2)]);
    ActivityLog::create(['user_id' => $other->id, 'action' => ActivityAction::CashEntry, 'description' => 'Uang masuk dicatat', 'created_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.aktivitas.index', ['action' => ActivityAction::BookCreate->value]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.user.id', $this->admin->id));

    $this->actingAs($this->admin)
        ->get(route('admin.aktivitas.index', ['user_id' => $other->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('logs.total', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.aktivitas.index', ['search' => 'Uang masuk']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.description', 'Uang masuk dicatat'));
});

it('blocks customers from the activity log', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.aktivitas.index'))
        ->assertForbidden();
});
