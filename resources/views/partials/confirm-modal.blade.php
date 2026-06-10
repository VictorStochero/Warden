{{-- Reusable confirmation modal. Any <form data-confirm="message"> on the page is
     intercepted and only submitted after the operator confirms. No external JS. --}}
<div id="obs-confirm" style="display:none"
     class="fixed inset-0 z-50 items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
    <div class="w-full max-w-md rounded-xl border border-ink-700 bg-ink-850 p-5 shadow-xl">
        <p id="obs-confirm-msg" class="text-sm leading-relaxed text-slate-200">Are you sure?</p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-confirm-cancel
                class="rounded-lg border border-ink-600 px-3 py-1.5 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white">
                Cancel
            </button>
            <button type="button" data-confirm-ok
                class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-500">
                Confirm
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
        msg.textContent = form.getAttribute('data-confirm') || 'Are you sure?';
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
