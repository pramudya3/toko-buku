<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stok Tersedia</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:24px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 8px;font-size:20px;font-weight:700;">
                                Halo {{ $order->nama_pembeli }},
                            </h1>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4b5563;">
                                Stok <strong>{{ $book->judul }}</strong> ({{ $qty }} buku)
                                yang Anda pesan sebelumnya telah tersedia.
                            </p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">
                                Pesanan <strong>{{ $order->no_order }}</strong> akan segera diproses oleh toko.
                                Pantau status pesanan Anda melalui menu <strong>Pesanan Saya</strong>.
                            </p>
                            <p style="margin:0;font-size:13px;color:#9ca3af;">
                                Terima kasih telah berbelanja di toko kami.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>