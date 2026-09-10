<section>
    <header class="flex items-start gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-700">
            <x-icon name="user" class="h-5 w-5" />
        </span>
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Profile Information</h2>
            <p class="mt-1 text-sm text-slate-500">Update your company username.</p>
        </div>
    </header>
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" required autofocus autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>
        <div class="flex items-center gap-4">
            <x-primary-button>
                <x-icon name="check" class="mr-2 h-4 w-4" />
                Save changes
            </x-primary-button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2000)" class="flex items-center gap-1.5 text-sm font-medium text-emerald-600"><x-icon name="check" class="h-4 w-4" />Saved.</p>
            @endif
        </div>
    </form>
</section>
