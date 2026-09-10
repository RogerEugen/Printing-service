<nav x-data="{ open: false }" class="border-b border-slate-200 bg-white text-slate-700 shadow-sm">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-7">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-bold tracking-tight text-slate-900">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-cyan-600 text-xs font-extrabold text-white shadow-sm">EP</span>
                <span class="hidden sm:inline">Elegansky Print</span>
            </a>
            <div class="hidden items-center gap-1 md:flex">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-cyan-50 text-cyan-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"><x-icon name="dashboard" class="h-4 w-4" />Dashboard</a>
                <a href="{{ route('print-jobs.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('print-jobs.index', 'print-jobs.show') ? 'bg-cyan-50 text-cyan-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"><x-icon name="document" class="h-4 w-4" />My Jobs</a>
                <a href="{{ route('print-jobs.create') }}" class="flex items-center gap-2 rounded-xl bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700"><x-icon name="document" class="h-4 w-4" />Upload File</a>
            </div>
        </div>
        <div class="hidden items-center gap-3 md:flex">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-50 text-cyan-700"><x-icon name="user" class="h-4 w-4" /></span>
                <span>{{ auth()->user()->username }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"><x-icon name="logout" class="h-4 w-4" />Log out</button></form>
        </div>
        <button @click="open = !open" class="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 md:hidden" aria-label="Toggle navigation"><x-icon name="menu" /></button>
    </div>
    <div x-show="open" x-cloak class="space-y-1 border-t border-slate-200 bg-white px-4 py-3 md:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-cyan-50 hover:text-cyan-700"><x-icon name="dashboard" class="h-4 w-4" />Dashboard</a>
        <a href="{{ route('print-jobs.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-cyan-50 hover:text-cyan-700"><x-icon name="document" class="h-4 w-4" />My Jobs</a>
        <a href="{{ route('print-jobs.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-cyan-50 hover:text-cyan-700"><x-icon name="document" class="h-4 w-4" />Upload File</a>
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-cyan-50 hover:text-cyan-700"><x-icon name="user" class="h-4 w-4" />Profile</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-red-600 hover:bg-red-50"><x-icon name="logout" class="h-4 w-4" />Log out</button></form>
    </div>
</nav>
