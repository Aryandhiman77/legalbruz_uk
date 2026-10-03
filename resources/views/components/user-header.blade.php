<!-- User Header -->
<nav class="navbar navbar-expand-lg user-topbar">
    <div class="container-fluid user-topbar-inner">
        <a class="user-brand" href="{{ route('home') }}">
            <span class="user-brand-logo">
                <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="Legal Bruz Pvt. Ltd. logo" class="navbar-logo">
            </span>
        </a>

        <button class="user-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar"
            aria-controls="userNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse" id="userNavbar">
            <ul class="navbar-nav user-navbar ms-auto">
                <li class="nav-item">
                    <a class="user-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        href="{{ route('dashboard') }}">
                        <i class="bi bi-grid-1x2" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="user-nav-link {{ request()->routeIs('user.documents') ? 'active' : '' }}"
                        href="{{ route('user.documents') }}">
                        <i class="bi bi-folder2-open" aria-hidden="true"></i>
                        <span>Documents</span>
                    </a>
                </li>

                <li class="nav-item dropdown">
                    @php
                        $unreadCount = \App\Services\NotificationService::getUnreadCount(Auth::id());
                    @endphp
                    <a class="user-nav-link user-notification-toggle" href="#" id="notificationsDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <span>Notifications</span>
                        @if ($unreadCount > 0)
                            <span class="user-notification-count">
                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            </span>
                        @endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown-menu"
                        aria-labelledby="notificationsDropdown">
                        <li class="notification-menu-head">
                            <div>
                                <strong>Notifications</strong>
                                <small>{{ $unreadCount }} unread</small>
                            </div>
                        </li>
                        @php
                            $notifications = \App\Services\NotificationService::getRecentNotifications(Auth::id(), 100);
                        @endphp

                        @if ($notifications->count() > 0)
                            @foreach ($notifications as $notif)
                                @php
                                    $notificationData = $notif->data ?? [];
                                    $notificationUrl = $notificationData['action_url'] ?? null;

                                    if (!$notificationUrl && !empty($notificationData['application_id'])) {
                                        $notificationUrl = route(
                                            'trademark.status',
                                            $notificationData['application_id'],
                                        );
                                    }
                                @endphp
                                <li>
                                    <a class="dropdown-item notification-dropdown-item {{ $notif->isUnread() ? 'bg-light' : '' }}"
                                        href="{{ $notificationUrl ?: 'javascript:void(0)' }}"
                                        data-notification-url="{{ $notificationUrl }}"
                                        onclick="markNotificationRead(event, {{ $notif->id }}, this)">
                                        <div class="notification-row">
                                            <div class="notification-copy">
                                                <strong>{{ $notif->title }}</strong>
                                                <p class="text-muted mb-0">
                                                    {{ $notif->message }}
                                                </p>
                                            </div>
                                            @if ($notif->isUnread())
                                                <span class="badge bg-primary">New</span>
                                            @endif
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider m-0">
                                </li>
                            @endforeach
                        @else
                            <li class="text-center p-3">
                                <small class="text-muted">No notifications</small>
                            </li>
                        @endif
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="user-account-toggle" href="#" id="userProfileDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-account-avatar">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                        </span>
                        <span class="user-account-copy">
                            <strong>{{ Auth::user()->name ?? 'User' }}</strong>
                            <small>My account</small>
                        </span>
                        <i class="bi bi-chevron-down user-account-chevron" aria-hidden="true"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end user-account-menu" aria-labelledby="userProfileDropdown">
                        <li>
                            <form action="{{ route('logout') }}" method="POST"
                                data-swal-confirm data-swal-title="Log out?"
                                data-swal-text="You will be signed out of your account." data-swal-icon="question"
                                data-swal-confirm-text="Yes, log out">
                                @csrf
                                <button class="user-logout-button" type="submit">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                    <span>Log out</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
    function markNotificationRead(event, notificationId, trigger = null) {
        event?.preventDefault();

        const notificationUrl = trigger?.dataset?.notificationUrl || trigger?.getAttribute('href');
        const shouldRedirect = notificationUrl && notificationUrl !== 'javascript:void(0)' && notificationUrl !== '#';

        window.LegalBruzButtonLoading?.set(trigger, 'Opening...');

        fetch(`{{ url('/api/notifications') }}/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).finally(() => {
            if (shouldRedirect) {
                window.location.href = notificationUrl;
                return;
            }

            location.reload();
        });
    }
</script>

<style>
    .user-topbar {
        position: sticky;
        top: 0;
        z-index: 1035;
        min-height: 74px;
        padding: 0;
        border-bottom: 1px solid #dfe8f0;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 8px 28px rgba(8, 36, 72, .08);
        backdrop-filter: blur(14px);
    }

    .user-topbar-inner {
        min-height: 74px;
        padding: 0 clamp(16px, 3vw, 44px);
    }

    .user-brand {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        color: #0b2a50;
        text-decoration: none;
    }

    .user-brand:hover {
        color: #0b2a50;
    }

    .user-brand-logo {
        display: grid;
        place-items: center;
        width: 68px;
        height: 60px;
        overflow: hidden;
        border: 0;
        border-radius: 0;
        background: transparent;
    }

    .navbar-logo {
        width: 64px;
        height: 56px;
        object-fit: contain;
    }

    .user-navbar {
        align-items: center;
        gap: 7px;
    }

    .user-nav-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 13px;
        border-radius: 10px;
        color: #53647a;
        font-size: .78rem;
        font-weight: 780;
        text-decoration: none;
        transition: color .18s ease, background .18s ease, transform .18s ease;
    }

    .user-nav-link i {
        color: #8494a8;
        font-size: .98rem;
    }

    .user-nav-link:hover {
        color: #0b6f68;
        background: #f0faf8;
    }

    .user-nav-link.active {
        color: #087d72;
        background: #e9f8f5;
    }

    .user-nav-link.active i {
        color: #159b8d;
    }

    .user-notification-toggle {
        padding-right: 9px;
    }

    .user-notification-count {
        display: inline-grid;
        place-items: center;
        min-width: 24px;
        height: 22px;
        padding: 0 6px;
        border-radius: 999px;
        color: #fff;
        background: #e04452;
        font-size: .63rem;
        font-weight: 900;
        line-height: 1;
    }

    .user-account-toggle {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr) auto;
        align-items: center;
        gap: 9px;
        min-width: 190px;
        min-height: 48px;
        margin-left: 4px;
        padding: 5px 10px 5px 6px;
        border: 1px solid #dce5ee;
        border-radius: 12px;
        color: #18304f;
        background: #fff;
        text-decoration: none;
        box-shadow: 0 5px 16px rgba(14, 43, 76, .06);
    }

    .user-account-avatar {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        color: #fff;
        background: linear-gradient(135deg, #0d5167, #159f8d);
        font-size: .78rem;
        font-weight: 900;
    }

    .user-account-copy {
        min-width: 0;
    }

    .user-account-copy strong,
    .user-account-copy small {
        display: block;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .user-account-copy strong {
        font-size: .74rem;
        line-height: 1.2;
    }

    .user-account-copy small {
        margin-top: 2px;
        color: #8190a3;
        font-size: .61rem;
        font-weight: 700;
    }

    .user-account-chevron {
        color: #8b99aa;
        font-size: .7rem;
    }

    .user-navbar-toggler {
        display: none;
        place-items: center;
        width: 40px;
        height: 40px;
        padding: 0;
        border: 1px solid #dce5ee;
        border-radius: 10px;
        color: #173253;
        background: #f8fafc;
        font-size: 1.35rem;
    }

    .notification-dropdown-menu {
        width: min(420px, calc(100vw - 1.5rem));
        height: auto;
        max-height: min(72vh, 560px);
        overflow-y: scroll;
        overflow-x: hidden;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        padding: 0;
        border: 1px solid #dfe7ef;
        border-radius: 13px;
        box-shadow: 0 18px 45px rgba(8, 30, 62, .16);
    }

    .notification-menu-head {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 14px 16px;
        border-bottom: 1px solid #e5ebf1;
        background: #fff;
    }

    .notification-menu-head strong,
    .notification-menu-head small {
        display: block;
    }

    .notification-menu-head strong {
        color: #173253;
        font-size: .86rem;
    }

    .notification-menu-head small {
        margin-top: 2px;
        color: #8491a2;
        font-size: .66rem;
    }

    .notification-dropdown-menu::-webkit-scrollbar {
        width: 8px;
    }

    .notification-dropdown-menu::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 999px;
    }

    .notification-dropdown-menu::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .notification-dropdown-item {
        padding: 12px 15px;
        white-space: normal;
        overflow: hidden;
    }

    .notification-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: start;
    }

    .notification-copy {
        min-width: 0;
    }

    .notification-copy strong,
    .notification-copy p {
        display: block;
        max-width: 100%;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.25;
    }

    .notification-copy p {
        margin-top: 0.25rem;
        font-size: 0.78rem;
    }

    .notification-row .badge {
        justify-self: end;
        white-space: nowrap;
    }

    .user-account-menu {
        min-width: 190px;
        margin-top: 8px !important;
        padding: 7px;
        border: 1px solid #dfe7ef;
        border-radius: 12px;
        box-shadow: 0 16px 38px rgba(8, 30, 62, .15);
    }

    .user-account-menu form {
        width: 100%;
    }

    .user-logout-button {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        min-height: 40px;
        padding: 0 11px;
        border: 0;
        border-radius: 8px;
        color: #c93445;
        background: transparent;
        font-size: .76rem;
        font-weight: 800;
        text-align: left;
    }

    .user-logout-button:hover {
        background: #fff1f2;
    }

    @media (max-width: 991.98px) {
        .user-navbar-toggler {
            display: grid;
        }

        .user-navbar {
            align-items: stretch;
            gap: 5px;
            margin: 12px 0 16px !important;
            padding: 12px;
            border: 1px solid #e0e8f0;
            border-radius: 14px;
            background: #f8fafc;
        }

        .user-nav-link {
            width: 100%;
            min-height: 44px;
        }

        .user-account-toggle {
            width: 100%;
            min-width: 0;
            margin: 4px 0 0;
        }

        .user-account-copy strong,
        .user-account-copy small {
            max-width: none;
        }

        .notification-dropdown-menu,
        .user-account-menu {
            width: 100%;
            max-width: none;
        }
    }

    @media (max-width: 480px) {
        .user-topbar,
        .user-topbar-inner {
            min-height: 66px;
        }

        .user-topbar-inner {
            padding: 0 12px;
        }

        .user-brand-logo {
            width: 58px;
            height: 52px;
        }

        .navbar-logo {
            width: 54px;
            height: 48px;
        }
    }
</style>
