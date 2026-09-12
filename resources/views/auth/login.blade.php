<x-guest-layout>
    <div class="mb-8 text-center">
        <x-brand-logo class="mx-auto mb-5 h-20 w-20 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-900/10" />
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">Elegansky Print</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-500">Sign in using your company account.</p>
    </div>

    <x-auth-session-status class="mb-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="username" value="Username" />
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><x-icon name="user" class="h-5 w-5" /></span>
                <x-text-input id="username" class="block w-full py-3 pl-11 pr-4" type="text" name="username" :value="old('username')" placeholder="e.g. admin01" required autofocus autocomplete="username" />
            </div>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><x-icon name="lock" class="h-5 w-5" /></span>
                <x-text-input id="password" class="block w-full py-3 pl-11 pr-4" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600">
            <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" name="remember">
            <span>Remember Me</span>
        </label>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-3 font-semibold text-white shadow-lg shadow-cyan-600/20 transition hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
            <x-icon name="lock" class="h-4 w-4" />
            <span>Login</span>
        </button>
    </form>

    <div class="mt-7 flex items-center justify-center gap-2 border-t border-slate-100 pt-5 text-xs text-slate-500">
        <x-icon name="check" class="h-4 w-4 text-emerald-600" />
        <span>Private company printing portal</span>
    </div>
</x-guest-layout>
