@extends('warden::layout')
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::admin.api_tokens.title'))
@section('heading', __('warden::admin.api_tokens.heading'))
@section('subheading', __('warden::admin.api_tokens.subheading'))

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-xl border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">{{ session('warden_status') }}</div>
    @endif
    @if(session('warden_error'))
        <div class="mb-5 rounded-xl border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">{{ session('warden_error') }}</div>
    @endif

    @if($plaintext ?? null)
        <div class="mb-5 rounded-xl border border-brand-600/50 bg-brand-600/10 p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ __('warden::admin.api_tokens.new_token') }}</p>
            <code class="block break-all rounded-lg bg-ink-950 px-3 py-2 font-mono text-[13px] text-emerald-300">{{ $plaintext }}</code>
        </div>
    @endif

    <form method="POST" action="{{ route('warden.admin.api-tokens.store') }}" class="mb-6 flex flex-wrap items-center gap-2">@csrf
        <input type="text" name="name" placeholder="{{ __('warden::admin.api_tokens.name_placeholder') }}"
               class="w-64 rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none focus:border-brand-500">
        <x-warden::button type="submit">{{ __('warden::admin.api_tokens.create') }}</x-warden::button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
        @if($tokens->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-500">{{ __('warden::admin.api_tokens.empty') }}</p>
        @else
            <table class="w-full text-left text-[13px]">
                <thead class="border-b border-ink-700/70 text-[10px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.api_tokens.col_name') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.api_tokens.col_created') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.api_tokens.col_last_used') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-700/60">
                    @foreach($tokens as $t)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="px-4 py-2.5 text-slate-200">{{ $t->name }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-400">{{ Format::at($t->created_at, 'Y-m-d H:i') }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-500">{{ $t->last_used_at ? Format::ago($t->last_used_at) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <form method="POST" action="{{ route('warden.admin.api-tokens.delete', $t->id) }}">@csrf
                                    <button type="submit" class="text-xs text-rose-400 transition hover:text-rose-300">{{ __('warden::admin.api_tokens.revoke') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
