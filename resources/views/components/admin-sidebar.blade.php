@php
    $navigation = \App\Support\AdminNavigation::groups();
@endphp

<aside id="adminSidebar" class="admin-sidebar" aria-label="Admin navigation">
    <nav class="admin-sidebar-nav">
        @foreach ($navigation as $group)
            <div class="admin-nav-group">
                <p class="admin-nav-label">{{ $group['label'] }}</p>
                @foreach ($group['items'] as $item)
                    @php
                        $itemIsActive = request()->routeIs(...$item['active'])
                            && (! isset($item['parameters']['key']) || request()->route('key') === $item['parameters']['key']);
                    @endphp
                    <a href="{{ route($item['route'], $item['parameters'] ?? []) }}"
                        class="admin-nav-link {{ $itemIsActive ? 'active' : '' }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="admin-sidebar-footer">
        <a href="{{ route('landing') }}" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>View public website</span>
        </a>
        <form action="{{ route('admin.logout') }}" method="POST"
            data-swal-confirm data-swal-title="Log out?"
            data-swal-text="You will be signed out of the admin panel." data-swal-icon="question"
            data-swal-confirm-text="Yes, log out">
            @csrf
            <button class="admin-sidebar-logout" type="submit">
                <i class="bi bi-box-arrow-right"></i>
                <span>Log out</span>
            </button>
        </form>
    </div>
</aside>
<button class="admin-sidebar-overlay" type="button" aria-label="Close admin navigation"></button>
