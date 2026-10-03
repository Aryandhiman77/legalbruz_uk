@extends('layouts.app')

@section('content')
    <div class="container-fluid py-2 admin-dashboard">
        <section class="admin-welcome">
            <div>
                <span class="admin-welcome-eyebrow">Admin workspace</span>
                <h1>Welcome back, {{ Auth::guard('admin')->user()?->name ?? 'Admin' }}</h1>
                <p>Manage UK trade mark filing applications, pricing, discounts, website content, and incoming enquiries.</p>
            </div>
            <div class="admin-welcome-mark"><i class="bi bi-command"></i></div>
        </section>

        <section class="admin-visitor-grid" aria-label="Visitor overview">
            <article class="admin-visitor-card">
                <span class="admin-visitor-icon website"><i class="bi bi-people"></i></span>
                <div>
                    <small>Website visitors</small>
                    <strong>{{ number_format($websiteVisitors) }}</strong>
                    <p>Unique browsers that visited the website</p>
                </div>
            </article>
            <article class="admin-visitor-card">
                <span class="admin-visitor-icon service"><i class="bi bi-briefcase"></i></span>
                <div>
                    <small>Service leads</small>
                    <strong>{{ number_format($serviceLeads) }}</strong>
                    <p>Unique visitors who opened a service</p>
                </div>
            </article>
            <a class="admin-visitor-card admin-metric-link" href="{{ route('admin.users.index') }}">
                <span class="admin-visitor-icon users"><i class="bi bi-person-check"></i></span>
                <div>
                    <small>Registered users</small>
                    <strong>{{ number_format($leadsCount) }}</strong>
                    <p>View registered users <i class="bi bi-arrow-right"></i></p>
                </div>
            </a>
            <a class="admin-visitor-card admin-review-card" href="{{ route('admin.reviews.index') }}">
                <span class="admin-visitor-icon reviews"><i class="bi bi-star"></i></span>
                <div>
                    <small>Published reviews</small>
                    <strong>{{ number_format($publishedReviewsCount) }}</strong>
                    <p>{{ number_format($reviewsCount) }} total reviews · Manage reviews <i class="bi bi-arrow-right"></i></p>
                </div>
            </a>
        </section>

        <section class="admin-service-panel" aria-labelledby="service-visitors-title">
            <div class="admin-service-heading">
                <div>
                    <span>Service analytics</span>
                    <h2 id="service-visitors-title">Visitors by service</h2>
                </div>
                <p>Unique visitors who opened each service.</p>
            </div>
            <div class="admin-service-grid">
                @foreach ($serviceVisitorCounts as $service)
                    <article class="admin-service-card">
                        <span class="admin-service-icon"><i class="bi {{ $service['icon'] }}"></i></span>
                        <div class="admin-service-copy">
                            <strong>{{ $service['label'] }}</strong>
                            <small>{{ $service['description'] }}</small>
                        </div>
                        <span class="admin-service-count">{{ number_format($service['visitors']) }}</span>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="admin-quicklinks-panel">
            <div class="admin-quicklinks-heading">
                <span class="admin-panel-icon"><i class="bi bi-lightning-charge"></i></span>
                <div>
                    <h2>Quick links</h2>
                    <p>Open any administration area directly.</p>
                </div>
            </div>

            @foreach (\App\Support\AdminNavigation::groups() as $group)
                <div class="admin-quicklinks-group">
                    <h3>{{ $group['label'] }}</h3>
                    <div class="admin-quicklinks-grid">
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route']) }}"
                                class="admin-quicklink {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">
                                <span class="admin-quicklink-icon"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span class="admin-quicklink-copy">
                                    <strong>{{ $item['label'] }}</strong>
                                    <small>{{ $item['description'] }}</small>
                                </span>
                                <i class="bi bi-arrow-up-right admin-quicklink-arrow"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>
    </div>

    <style>
        .admin-dashboard { max-width: 1450px; }
        .admin-welcome { position:relative;display:flex;align-items:center;justify-content:space-between;gap:28px;min-height:190px;padding:34px 38px;overflow:hidden;border-radius:18px;color:#fff;background:radial-gradient(circle at 88% 5%,rgba(89,224,204,.25),transparent 27%),linear-gradient(120deg,#071f48,#0c4565 66%,#128d83);box-shadow:0 18px 38px rgba(7,31,72,.13) }
        .admin-welcome::after { content:"";position:absolute;right:-70px;bottom:-160px;width:360px;height:300px;border:1px solid rgba(255,255,255,.12);border-radius:50%;box-shadow:0 0 0 28px rgba(255,255,255,.025) }
        .admin-welcome > * { position:relative;z-index:1 }
        .admin-welcome-eyebrow { color:#8ce8dc;font-size:.64rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase }
        .admin-welcome h1 { margin:10px 0 8px;color:#fff;font-size:clamp(1.55rem,2.5vw,2.25rem);letter-spacing:-.035em }
        .admin-welcome p { max-width:700px;margin:0;color:rgba(255,255,255,.76);font-size:.82rem;line-height:1.7 }
        .admin-welcome-mark { display:grid;place-items:center;width:82px;height:82px;border:1px solid rgba(255,255,255,.16);border-radius:22px;background:rgba(255,255,255,.08);font-size:2rem }
        .admin-visitor-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:22px }
        .admin-visitor-card { display:flex;align-items:center;gap:16px;min-height:126px;padding:23px;border:1px solid #dfe7ef;border-radius:15px;background:#fff;box-shadow:0 10px 28px rgba(7,31,72,.055) }
        .admin-review-card,.admin-metric-link { color:inherit;text-decoration:none;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease }
        .admin-review-card:hover,.admin-metric-link:hover { color:inherit;border-color:#a8d8d1;box-shadow:0 13px 30px rgba(7,31,72,.09);transform:translateY(-2px) }
        .admin-visitor-icon { display:grid;place-items:center;flex:0 0 52px;height:52px;border-radius:13px;font-size:1.25rem }
        .admin-visitor-icon.website { color:#11796f;background:#e6f7f4 }
        .admin-visitor-icon.service { color:#245b99;background:#eaf1fb }
        .admin-visitor-icon.users { color:#6941c6;background:#f0eaff }
        .admin-visitor-icon.reviews { color:#a56a00;background:#fff5d9 }
        .admin-visitor-card small,.admin-visitor-card strong { display:block }
        .admin-visitor-card small { color:#61738b;font-size:.72rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em }
        .admin-visitor-card strong { margin-top:5px;color:#102a4c;font-size:1.65rem;line-height:1 }
        .admin-visitor-card p { margin:7px 0 0;color:#7a8798;font-size:.74rem }
        .admin-service-panel { margin-top:16px;padding:22px 24px;border:1px solid #dfe7ef;border-radius:15px;background:#fff;box-shadow:0 10px 28px rgba(7,31,72,.055) }
        .admin-service-heading { display:flex;align-items:end;justify-content:space-between;gap:20px;padding-bottom:17px;border-bottom:1px solid #edf1f5 }
        .admin-service-heading span { color:#159485;font-size:.65rem;font-weight:900;letter-spacing:.11em;text-transform:uppercase }
        .admin-service-heading h2 { margin:3px 0 0;color:#102a4c;font-size:1rem }
        .admin-service-heading p { margin:0;color:#748196;font-size:.74rem }
        .admin-service-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-top:17px }
        .admin-service-card { display:flex;align-items:center;gap:13px;min-height:82px;padding:15px;border:1px solid #e2e9f0;border-radius:12px;background:#fbfdff }
        .admin-service-icon { display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:11px;color:#159485;background:#e7f7f4;font-size:1rem }
        .admin-service-copy { min-width:0;flex:1 }
        .admin-service-copy strong,.admin-service-copy small { display:block }
        .admin-service-copy strong { color:#172b46;font-size:.84rem }
        .admin-service-copy small { margin-top:3px;color:#748196;font-size:.7rem;line-height:1.4 }
        .admin-service-count { color:#0c4464;font-size:1.4rem;font-weight:900;line-height:1 }
        .admin-quicklinks-panel { margin-top:22px;padding:25px;border:1px solid #dfe7ef;border-radius:15px;background:#fff;box-shadow:0 10px 28px rgba(7,31,72,.055) }
        .admin-quicklinks-heading { display:flex;align-items:flex-start;gap:13px;padding-bottom:20px;border-bottom:1px solid #edf1f5 }
        .admin-panel-icon { display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:10px;color:#158f82;background:#e9f7f4 }
        .admin-quicklinks-heading h2 { margin:1px 0 4px;font-size:1rem }
        .admin-quicklinks-heading p { margin:0;color:#718096;font-size:.72rem }
        .admin-quicklinks-group { margin-top:23px }
        .admin-quicklinks-group h3 { margin:0 0 10px;color:#61738b;font-size:.69rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase }
        .admin-quicklinks-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px }
        .admin-quicklink { position:relative;display:flex;align-items:center;gap:12px;min-height:82px;padding:15px 42px 15px 15px;border:1px solid #e0e7ef;border-radius:12px;color:#172b46;background:#fff;text-decoration:none;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease }
        .admin-quicklink:hover { color:#172b46;border-color:#a8d8d1;box-shadow:0 9px 22px rgba(7,31,72,.075);transform:translateY(-2px) }
        .admin-quicklink.active { border-color:#83cfc4;background:#f0faf8 }
        .admin-quicklink-icon { display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:10px;color:#159f8d;background:#e9f7f4;font-size:1rem }
        .admin-quicklink-copy strong,.admin-quicklink-copy small { display:block }
        .admin-quicklink-copy strong { color:#172b46;font-size:.84rem }
        .admin-quicklink-copy small { margin-top:4px;color:#748196;font-size:.72rem;line-height:1.45 }
        .admin-quicklink-arrow { position:absolute;top:15px;right:15px;color:#9aabba;font-size:.72rem }
        @media(max-width:1100px){.admin-quicklinks-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:1100px){.admin-visitor-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:900px){.admin-welcome-mark{display:none}}
        @media(max-width:650px){.admin-quicklinks-grid,.admin-service-grid{grid-template-columns:1fr}.admin-service-heading{align-items:flex-start;flex-direction:column;gap:6px}}
        @media(max-width:575px){.admin-welcome{min-height:170px;padding:26px 22px}.admin-visitor-grid{grid-template-columns:1fr}.admin-quicklinks-panel{padding:20px}}
    </style>
@endsection
