<?php

namespace App\Enums;

/**
 * Jenis aktivitas yang dicatat di activity_logs.
 */
enum ActivityAction: string
{
    // Auth
    case Login = 'auth.login';
    case Logout = 'auth.logout';
    case LoginFailed = 'auth.login_failed';

    // Settings
    case SettingsUpdate = 'settings.update';
    case SettingsDelete = 'settings.delete';

    // Katalog
    case BookCreate = 'book.create';
    case BookUpdate = 'book.update';
    case BookDelete = 'book.delete';
    case BookRestore = 'book.restore';
    case BookImport = 'book.import';
    case CategoryCreate = 'category.create';
    case CategoryUpdate = 'category.update';
    case CategoryDelete = 'category.delete';
    case CategoryImport = 'category.import';

    // Penjualan
    case OrderCreate = 'order.create';
    case OrderStatus = 'order.status';
    case OrderProcess = 'order.process';
    case ReceivablePay = 'receivable.pay';
    case SalesReturnCreate = 'sales_return.create';

    // Kas
    case CashEntry = 'cash.entry';
    case CashMonthCreate = 'cash.month_create';

    // Pembelian
    case SupplierCreate = 'supplier.create';
    case SupplierUpdate = 'supplier.update';
    case SupplierDelete = 'supplier.delete';
    case PurchaseCreate = 'purchase.create';
    case SupplierReturnCreate = 'supplier_return.create';
    case SupplierPaymentCreate = 'supplier.payment';

    // Stok & master
    case WarehouseCreate = 'warehouse.create';
    case WarehouseUpdate = 'warehouse.update';
    case WarehouseDelete = 'warehouse.delete';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';
    case CustomerImport = 'customer.import';
    case PromotionCreate = 'promotion.create';
    case PromotionUpdate = 'promotion.update';
    case PromotionDelete = 'promotion.delete';
    case PromotionImport = 'promotion.import';
    case TierDiscountImport = 'tier_discount.import';
    case VoucherCreate = 'voucher.create';
    case VoucherUpdate = 'voucher.update';
    case VoucherDelete = 'voucher.delete';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::LoginFailed => 'Login Gagal',
            self::SettingsUpdate => 'Ubah Pengaturan',
            self::SettingsDelete => 'Hapus Data Pengaturan',
            self::BookCreate => 'Buat Buku',
            self::BookUpdate => 'Ubah Buku',
            self::BookDelete => 'Hapus Buku',
            self::BookRestore => 'Pulihkan Buku',
            self::BookImport => 'Import Buku',
            self::CategoryCreate => 'Buat Kategori',
            self::CategoryUpdate => 'Ubah Kategori',
            self::CategoryDelete => 'Hapus Kategori',
            self::CategoryImport => 'Import Kategori',
            self::OrderCreate => 'Buat Pesanan',
            self::OrderStatus => 'Ubah Status Pesanan',
            self::OrderProcess => 'Proses Pesanan',
            self::ReceivablePay => 'Bayar Piutang',
            self::SalesReturnCreate => 'Retur Penjualan',
            self::CashEntry => 'Catat Kas',
            self::CashMonthCreate => 'Buka Bulan Kas',
            self::SupplierCreate => 'Buat Supplier',
            self::SupplierUpdate => 'Ubah Supplier',
            self::SupplierDelete => 'Hapus Supplier',
            self::PurchaseCreate => 'Barang Masuk',
            self::SupplierReturnCreate => 'Retur Supplier',
            self::SupplierPaymentCreate => 'Bayar Hutang Supplier',
            self::WarehouseCreate => 'Buat Gudang',
            self::WarehouseUpdate => 'Ubah Gudang',
            self::WarehouseDelete => 'Hapus Gudang',
            self::UserCreate => 'Buat User',
            self::UserUpdate => 'Ubah User',
            self::UserDelete => 'Hapus User',
            self::CustomerImport => 'Import Pelanggan',
            self::PromotionCreate => 'Buat Promosi',
            self::PromotionUpdate => 'Ubah Promosi',
            self::PromotionDelete => 'Hapus Promosi',
            self::PromotionImport => 'Import Promosi',
            self::TierDiscountImport => 'Import Tier Discount',
            self::VoucherCreate => 'Buat Voucher',
            self::VoucherUpdate => 'Ubah Voucher',
            self::VoucherDelete => 'Hapus Voucher',
        };
    }
}
