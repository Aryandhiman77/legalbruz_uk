<header class="admin-topbar">
    <div class="admin-topbar-inner">
        <div class="d-flex align-items-center gap-3">
            <button class="admin-sidebar-toggle" type="button" aria-label="Toggle admin navigation"
                aria-controls="adminSidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <a class="admin-topbar-brand" href="{{ route('admin.dashboard') }}">
                <span class="admin-topbar-logo" aria-hidden="true">
                    <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="">
                </span>
                <span>
                    <strong>Legal Bruz Pvt. Ltd.</strong>
                    <small>Administration</small>
                </span>
            </a>
        </div>

        <div class="dropdown">
            <button class="admin-profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <span class="admin-profile-avatar">{{ strtoupper(substr(Auth::guard('admin')->user()?->name ?? 'A', 0, 1)) }}</span>
                <span class="admin-profile-copy">
                    <strong>{{ Auth::guard('admin')->user()?->name ?? 'Admin' }}</strong>
                    <small>Administrator</small>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end admin-profile-menu">
                <div class="px-3 py-2">
                    <small class="text-muted d-block">Signed in as</small>
                    <strong>{{ Auth::guard('admin')->user()?->email }}</strong>
                </div>
                <div class="dropdown-divider"></div>
                <form action="{{ route('admin.logout') }}" method="POST"
                    data-swal-confirm data-swal-title="Log out?"
                    data-swal-text="You will be signed out of the admin panel." data-swal-icon="question"
                    data-swal-confirm-text="Yes, log out">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit">
                        <i class="bi bi-box-arrow-right me-2"></i>Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
