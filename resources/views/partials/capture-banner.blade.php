@php
    /** @var array{reduced:bool, off:array<int,string>, query_min_ms:int, request_sample:float, needs_opt_in:bool, profile:?string} $captureStatus */
    $cs = $captureStatus ?? null;
@endphp

@if($cs)
    @if($cs['needs_opt_in'])
        @can('manageWarden')
            <div class="mb-6 rounded-lg border border-brand-500/40 bg-brand-600/15 px-4 py-3 text-sm text-slate-200">
                <p class="font-semibold text-white">{{ __('warden::capture.optin_title') }}</p>
                <p class="mt-1 text-slate-300">{{ __('warden::capture.optin_body') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('warden.admin.projects.capture.migrate', $project) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:brightness-110">
                            {{ __('warden::capture.optin_migrate') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('warden.admin.projects.capture.dismiss', $project) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-ink-600 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-ink-700">
                            {{ __('warden::capture.optin_keep') }}
                        </button>
                    </form>
                </div>
            </div>
        @endcan
    @elseif($cs['reduced'])
        @php
            $sig = $project->slug.':'.$project->config_version; // re-show when capture changes
        @endphp
        <div class="wdn-capture-banner mb-6 flex flex-wrap items-start justify-between gap-3 rounded-lg border border-ink-700 bg-ink-900 px-4 py-3 text-sm text-slate-300"
             data-capture-sig="{{ $sig }}">
            <div class="min-w-0">
                <p class="font-semibold text-slate-100">{{ __('warden::capture.reduced_title') }}</p>
                @if($cs['off'])
                    <p class="mt-1 text-slate-400">{{ __('warden::capture.reduced_body', ['list' => implode(', ', $cs['off'])]) }}</p>
                @endif
                @if($cs['query_min_ms'] > 0)
                    <p class="mt-1 text-slate-400">{{ __('warden::capture.query_note', ['ms' => $cs['query_min_ms']]) }}</p>
                @endif
                @if($cs['request_sample'] < 1.0)
                    <p class="mt-1 text-slate-400">{{ __('warden::capture.sample_note', ['pct' => rtrim(rtrim(number_format($cs['request_sample'] * 100, 1), '0'), '.')]) }}</p>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @can('manageWarden')
                    <a href="{{ route('warden.admin.projects.edit', $project->slug) }}"
                       class="rounded-md border border-ink-600 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-ink-700">
                        {{ __('warden::capture.manage') }}
                    </a>
                @endcan
                <button type="button" class="wdn-capture-dismiss rounded-md px-2 py-1.5 text-xs text-slate-500 transition hover:text-slate-300">
                    {{ __('warden::capture.dismiss_session') }}
                </button>
            </div>
        </div>
        <script>
            (function () {
                var el = document.currentScript.previousElementSibling;
                if (!el || !el.classList.contains('wdn-capture-banner')) { return; }
                var key = 'wdn-capture-dismissed:' + el.dataset.captureSig;
                try { if (localStorage.getItem(key)) { el.remove(); return; } } catch (e) {}
                var btn = el.querySelector('.wdn-capture-dismiss');
                if (btn) {
                    btn.addEventListener('click', function () {
                        try { localStorage.setItem(key, '1'); } catch (e) {}
                        el.remove();
                    });
                }
            })();
        </script>
    @endif
@endif
