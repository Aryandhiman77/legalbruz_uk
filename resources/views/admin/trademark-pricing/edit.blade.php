@extends('layouts.app')

@section('content')
    @php
        $planMap = $plans->keyBy('key');
        $priceFields = [
            'uk_search' => [
                'title' => 'Trademark Search Report',
                'description' => 'Fee charged when a visitor requests a search report and proceeds to secure payment.',
                'featured' => false,
            ],
            'uk_application' => [
                'title' => 'UK Trade Mark Application',
                'description' => 'Professional fee used on the homepage and in the active UK filing journey.',
                'featured' => true,
            ],
            'consultation_call' => [
                'title' => 'Consultation Call',
                'description' => 'Fee charged when a visitor submits the Book a Call form and proceeds to payment.',
                'featured' => false,
            ],
        ];
        $homepageFeatureCards = [
            'opposition_service' => 'Opposition Service',
            'uk_application' => 'UK Trade Mark Application',
            'uk_examination_response' => 'Examination Report',
        ];
    @endphp

    <div class="admin-pricing-page">
        <header class="admin-pricing-hero">
            <span>UK website settings</span>
            <h1>Website Service Pricing</h1>
            <p>Set the professional fees shown on the UK website, including search reports, trade mark applications and paid consultation calls. Every amount is displayed in pounds sterling.</p>
        </header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.trademark-pricing.update') }}" class="admin-pricing-card">
            @csrf
            @method('PUT')

            <div class="admin-pricing-grid">
                @foreach ($priceFields as $key => $field)
                    @php
                        $amount = old("prices.{$key}", $planMap->get($key)->amount ?? $defaults[$key]['amount']);
                    @endphp
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

            <section class="admin-feature-section">
                <div class="admin-feature-heading">
                    <span>Homepage pricing card key points</span>
                    <p>Edit, add or remove the tick points shown inside each public pricing card.</p>
                </div>
                <div class="admin-feature-grid">
                    @foreach ($homepageFeatureCards as $key => $cardTitle)
                        @php
                            $storedFeatures = old("features.{$key}", $planMap->get($key)?->features ?? $defaults[$key]['features']);
                        @endphp
                        <div class="admin-feature-card">
                            <strong>{{ $cardTitle }}</strong>
                            <div data-feature-list="{{ $key }}">
                                @foreach ($storedFeatures as $index => $feature)
                                    <div class="admin-feature-row" data-feature-row>
                                        <label>
                                            <span data-feature-number>Key point {{ $index + 1 }}</span>
                                            <input type="text" name="features[{{ $key }}][]" value="{{ $feature }}" maxlength="120" required>
                                        </label>
                                        <button type="button" class="admin-feature-remove" data-remove-feature>Remove</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="admin-feature-add" data-add-feature="{{ $key }}">+ Add key point</button>
                            @error("features.{$key}")
                                <em>{{ $message }}</em>
                            @enderror
                            @error("features.{$key}.*")
                                <em>{{ $message }}</em>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="admin-pricing-note">
                <i class="bi bi-info-circle"></i>
                <p>These are professional fees only. UKIPO official fees are displayed separately on the public website, and existing completed payment records keep their original invoice amounts.</p>
            </div>

            <div class="admin-pricing-actions">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
                <button type="submit" class="btn btn-primary">Save Pricing</button>
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
        .admin-pricing-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
        @media(max-width:1000px){.admin-pricing-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
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
        .admin-feature-section{margin-top:24px;padding-top:24px;border-top:1px solid #e0e8f0}
        .admin-feature-heading span{display:block;color:#102a4c;font-size:1.05rem;font-weight:950}
        .admin-feature-heading p{margin:4px 0 16px;color:#66758b}
        .admin-feature-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
        .admin-feature-card{padding:18px;border:1px solid #e0e8f0;border-radius:14px;background:#fbfdff}
        .admin-feature-card>strong{display:block;margin-bottom:12px;color:#102a4c}
        .admin-feature-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;margin-top:10px}
        .admin-feature-card label{display:block}
        .admin-feature-card label span{display:block;margin-bottom:4px;color:#66758b;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .admin-feature-card input{width:100%;min-height:42px;padding:8px 10px;border:1px solid #cdd8e5;border-radius:9px;color:#102a4c}
        .admin-feature-card em{display:block;margin-top:8px;color:#b42318;font-style:normal;font-weight:800}
        .admin-feature-remove,.admin-feature-add{min-height:42px;border-radius:9px;font-size:.78rem;font-weight:850;cursor:pointer}
        .admin-feature-remove{padding:0 10px;border:1px solid #efc2c2;background:#fff7f7;color:#a32323}
        .admin-feature-remove:disabled,.admin-feature-add:disabled{cursor:not-allowed;opacity:.4}
        .admin-feature-add{width:100%;margin-top:12px;border:1px dashed #73bdb3;background:#f1fbf8;color:#087565}
        @media(max-width:1000px){.admin-feature-grid{grid-template-columns:1fr}}
        @media(max-width:760px){.admin-pricing-grid{grid-template-columns:1fr}.admin-price-field small{min-height:0}.admin-pricing-actions{display:grid}}
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const maximumFeatures = 8;

            const refreshFeatureList = list => {
                const rows = Array.from(list.querySelectorAll('[data-feature-row]'));
                rows.forEach((row, index) => {
                    const number = row.querySelector('[data-feature-number]');
                    const remove = row.querySelector('[data-remove-feature]');
                    if (number) number.textContent = `Key point ${index + 1}`;
                    if (remove) remove.disabled = rows.length === 1;
                });

                const add = document.querySelector(`[data-add-feature="${list.dataset.featureList}"]`);
                if (add) add.disabled = rows.length >= maximumFeatures;
            };

            document.querySelectorAll('[data-feature-list]').forEach(list => {
                list.addEventListener('click', event => {
                    const remove = event.target.closest('[data-remove-feature]');
                    if (!remove || list.querySelectorAll('[data-feature-row]').length <= 1) return;
                    remove.closest('[data-feature-row]')?.remove();
                    refreshFeatureList(list);
                });
                refreshFeatureList(list);
            });

            document.querySelectorAll('[data-add-feature]').forEach(button => {
                button.addEventListener('click', () => {
                    const key = button.dataset.addFeature;
                    const list = document.querySelector(`[data-feature-list="${key}"]`);
                    if (!list || list.querySelectorAll('[data-feature-row]').length >= maximumFeatures) return;

                    const row = document.createElement('div');
                    row.className = 'admin-feature-row';
                    row.dataset.featureRow = '';
                    row.innerHTML = `
                        <label>
                            <span data-feature-number></span>
                            <input type="text" name="features[${key}][]" maxlength="120" required>
                        </label>
                        <button type="button" class="admin-feature-remove" data-remove-feature>Remove</button>
                    `;
                    list.appendChild(row);
                    refreshFeatureList(list);
                    row.querySelector('input')?.focus();
                });
            });
        });
    </script>
@endsection
