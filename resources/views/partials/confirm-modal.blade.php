{{-- Reusable confirmation modal. Any <form data-confirm="message"> on the page is
     intercepted and only submitted after the operator confirms. No external JS. --}}
<div id="obs-confirm" style="display:none"
     class="fixed inset-0 z-50 items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="w-full max-w-md rounded-2xl border border-ink-700/80 bg-ink-900 p-6 shadow-2xl shadow-black/50">
        <div class="flex items-start gap-3.5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-500/10 text-rose-400 ring-1 ring-inset ring-rose-500/20">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </span>
            <p id="obs-confirm-msg" class="pt-1 text-sm leading-relaxed text-slate-200">{{ __('warden::admin.confirm_modal.default_message') }}</p>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-confirm-cancel
                class="rounded-lg border border-ink-600 px-3 py-1.5 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white">
                {{ __('warden::admin.confirm_modal.cancel') }}
            </button>
            <button type="button" data-confirm-ok
                class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-500">
                {{ __('warden::admin.confirm_modal.confirm') }}
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('obs-confirm');
    if (!modal) return;

    var msg = document.getElementById('obs-confirm-msg');
    var okBtn = modal.querySelector('[data-confirm-ok]');
    var pending = null;

    function close() { modal.style.display = 'none'; pending = null; }
    function open(form) {
        pending = form;
        msg.textContent = form.getAttribute('data-confirm') || '{{ __('warden::admin.confirm_modal.default_message') }}';
        modal.style.display = 'flex';
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') { return; } // already confirmed — let it through
            e.preventDefault();
            open(form);
        });
    });

    okBtn.addEventListener('click', function () {
        if (!pending) { return; }
        pending.dataset.confirmed = '1';
        pending.submit();
        close();
    });
    modal.querySelector('[data-confirm-cancel]').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
})();
</script>
