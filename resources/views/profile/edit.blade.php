<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-50 text-cyan-700">
                <x-icon name="user" class="h-5 w-5" />
            </span>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Profile</h1>
                <p class="mt-0.5 text-sm text-slate-500">Manage your company account and password.</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-start">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
