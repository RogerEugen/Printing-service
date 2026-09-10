<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold">{{ $printer->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Printer agent configuration and status.</p>
        </div>
    </x-slot>

    @if ($deviceToken)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
            <h2 class="font-bold text-amber-900">Copy this token now — shown once</h2>
            <code class="mt-3 block break-all rounded-xl bg-slate-950 p-4 text-sm text-cyan-300">{{ $deviceToken }}</code>
            <p class="mt-2 text-xs text-amber-800">Put it in the agent's PRINTER_TOKEN environment variable.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.printers.update', $printer) }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            @csrf
            @method('PUT')
            @include('admin.printers._fields', ['printer' => $printer])
            <div>
                <x-input-label for="status" value="Administrative status" />
                <select id="status" name="status" class="mt-1 block w-full rounded-xl border-slate-300">
                    <option value="offline" @selected(old('status', $printer->status->value) === 'offline')>Enabled (offline until heartbeat)</option>
                    <option value="disabled" @selected(old('status', $printer->status->value) === 'disabled')>Disabled</option>
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
            <button class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white">Save printer</button>
        </form>

        <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="font-semibold">Agent status</h2>
            <dl class="mt-5 space-y-4 text-sm">
                <div><dt class="text-slate-500">Effective status</dt><dd class="font-semibold capitalize">{{ $printer->displayedStatus()->value }}</dd></div>
                <div><dt class="text-slate-500">Last heartbeat</dt><dd class="font-semibold">{{ $printer->last_seen_at?->format('d M Y, H:i:s') ?? 'Never' }}</dd></div>
                <div><dt class="text-slate-500">Total jobs</dt><dd class="font-semibold">{{ $printer->print_jobs_count }}</dd></div>
            </dl>
            <p class="mt-6 text-xs text-slate-500">For security, the stored device-token hash cannot reveal the original token.</p>
        </section>
    </div>
</x-app-layout>
