@auth
    @if (auth()->user()->isAdmin())
        @include('layouts.AdminLayout')
    @else
        @include('layouts.employee')
    @endif
@endauth
