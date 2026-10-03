@extends('layouts.app')

@section('content')
    @php
        $planMap = $plans->keyBy('key');
        $priceFields = [
            'uk_application' => [
                'title' => 'UK Trade Mark Application',
                'description' => 'Professional fee used on the homepage and in the active UK filing journey.',
                'featured' => true,
            ],
        ];
    @endphp

    <div class="admin-pricing-page">
        <header class="admin-pricing-hero">
            <span>UK website settings</span>
            <h1>UK Trade Mark Pricing</h1>
            <p>Set the professional fees shown on the UK website. Every amount is displayed in pounds sterling and UKIPO official fees remain separate.</p>
        </header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.trademark-pricing.update') }}" class="admin-pricing-card">
            @csrf
            @method('PUT')

            <div class="admin-pricing-grid">
                @foreach ($priceFields as $key => $field)
                    @php($amount = old("prices.{$key}", $planMap->get($key)->amount ?? $defaults[$key]['amount']))
                    <label class="admin-price-field {{ !empty($field['featured']) ? 'featured' : '' }}">
                        <span>{{ $field['title'] }}</span>
                        <small>{{ $field['description'] }}</small>
                        <div class="admin-price-input">
                            <b>£</b>
                            <input type="number" name="prices[{{ $key }}]" value="{{ $amount }}" min="1" max="1000000" step="0.01" required>
                        </div>
                        @error("prices.{$key}")
                            <em>{{ $message }}</em>
                        @enderror
                    </label>
                @endforeach
            </div>

            <div class="admin-pricing-note">
                <i class="bi bi-info-circle"></i>
                <p>These are professional fees only. UKIPO official fees are displayed separately on the public website, and existing completed payment records keep their original invoice amounts.</p>
            </div>

            <div class="admin-pricing-actions">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
                <button type="submit" class="btn btn-primary">Save Trademark Pricing</button>
            </div>
        </form>
    </div>

    <style>
        .admin-pricing-page{max-width:1120px;margin:0 auto 38px;padding:0 18px;color:#172b46}
        .admin-pricing-hero{padding:30px;border-radius:18px;background:linear-gradient(135deg,#071f48,#0d6b69);color:#fff;box-shadow:0 18px 40px rgba(7,31,72,.13)}
        .admin-pricing-hero span{display:block;color:#8de8dc;font-size:.68rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}
        .admin-pricing-hero h1{margin:8px 0;color:#fff;font-size:clamp(1.6rem,3vw,2.35rem);font-weight:950}
        .admin-pricing-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.78);line-height:1.65}
        .admin-pricing-card{margin-top:18px;padding:24px;border:1px solid #dfe8f4;border-radius:16px;background:#fff;box-shadow:0 14px 32px rgba(8,36,90,.07)}
        .admin-pricing-grid{display:grid;grid-template-columns:minmax(0,560px);gap:18px}
        .admin-price-field{display:block;padding:20px;border:1px solid #e0e8f0;border-radius:14px;background:#fbfdff}
        .admin-price-field.featured{border-color:#a9d8d1;background:#f3fbf9}
        .admin-price-field span{display:block;color:#102a4c;font-size:1rem;font-weight:950}
        .admin-price-field small{display:block;margin-top:6px;min-height:42px;color:#66758b;line-height:1.45}
        .admin-price-field em{display:block;margin-top:8px;color:#b42318;font-style:normal;font-weight:800}
        .admin-price-input{display:flex;align-items:center;margin-top:16px;border:1px solid #cdd8e5;border-radius:12px;background:#fff;overflow:hidden}
        .admin-price-input b{display:grid;place-items:center;align-self:stretch;width:54px;background:#eef7f5;color:#0c7f73;font-size:1.15rem}
        .admin-price-input input{width:100%;min-height:54px;border:0;padding:0 15px;color:#102a4c;font-size:1.45rem;font-weight:900;outline:0}
        .admin-pricing-note{display:flex;gap:10px;margin-top:18px;padding:14px;border-radius:12px;background:#fff8e6;color:#73510d}
        .admin-pricing-note p{margin:0;line-height:1.5}
        .admin-pricing-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:22px}
        @media(max-width:760px){.admin-pricing-grid{grid-template-columns:1fr}.admin-price-field small{min-height:0}.admin-pricing-actions{display:grid}}
    </style>
@endsection
