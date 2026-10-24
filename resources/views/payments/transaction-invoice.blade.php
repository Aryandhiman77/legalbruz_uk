<!doctype html><html><head><meta charset="utf-8"><title>Invoice {{ $invoiceNumber }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;color:#17324d;font-size:12px}.header{border-bottom:3px solid #129486;padding-bottom:18px;margin-bottom:24px}.brand{font-size:24px;font-weight:bold}.meta{float:right;text-align:right}.clear{clear:both}.box{background:#f3f8fa;padding:16px;margin:18px 0}.row{border-bottom:1px solid #dde7ed;padding:12px 0}.total{font-size:18px;font-weight:bold;text-align:right;padding-top:18px}.muted{color:#66788a}code{font-family:DejaVu Sans,sans-serif;font-size:10px}</style></head>
<body><div class="header"><div class="meta"><strong>Invoice {{ $invoiceNumber }}</strong><br>{{ $issuedAt->format('d M Y') }}</div><div class="brand">{{ $firmName }}</div><div>{{ $firmEmail }}</div><div class="clear"></div></div>
<div class="box"><strong>Billed to</strong><br>{{ $transaction->user?->name ?: 'Customer' }}<br>{{ $transaction->user?->email }}</div>
<div class="row"><strong>{{ $transaction->service_label }}</strong><span style="float:right">{{ $transaction->currency }} {{ number_format((float)$transaction->amount, 2) }}</span></div>
<div class="total">Total paid: {{ $transaction->currency }} {{ number_format((float)$transaction->amount, 2) }}</div>
<p class="muted">Payment provider: Revolut<br>Order reference: <code>{{ $transaction->provider_order_id }}</code><br>Payment reference: <code>{{ $transaction->provider_payment_id }}</code></p>
</body></html>
