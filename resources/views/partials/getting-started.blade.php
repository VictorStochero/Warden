<h2 class="text-sm font-semibold text-white">{{ __('warden::common.getting_started.title') }}</h2>
<p class="mt-1 text-sm text-slate-400">{{ __('warden::common.getting_started.intro') }}</p>
<ol class="mt-4 space-y-2 text-sm text-slate-300">
    <li><span class="mr-2 text-brand-400">1.</span>{{ __('warden::common.getting_started.step1') }}</li>
    <li><span class="mr-2 text-brand-400">2.</span>{!! __('warden::common.getting_started.step2', ['env' => '<code class="text-brand-400">.env</code>']) !!}</li>
    <li><span class="mr-2 text-brand-400">3.</span>{!! __('warden::common.getting_started.step3', ['ship' => '<code class="text-brand-400">warden:ship</code>']) !!}</li>
</ol>
@can('manageWarden')
    <x-warden::button :href="route('warden.admin.projects')" class="mt-5">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
        {{ __('warden::common.getting_started.cta') }}
    </x-warden::button>
@endcan
