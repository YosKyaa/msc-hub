@props([
    'label' => 'Kirim',
    'heading' => 'Kirim pengajuan?',
    'description' => 'Periksa kembali data Anda sebelum dikirim.',
    'confirmLabel' => 'Ya, Kirim',
    'tone' => 'indigo',
    // Ringkasan yang ditampilkan di dialog: ['Label' => 'nama_input', ...]
    'summary' => [],
])

{{-- Konfirmasi sebelum kirim: pengajuan peminjaman menghasilkan kode booking
     dan memberitahu tim MSC, jadi salah kirim merepotkan dua pihak. Ringkasan
     dibaca langsung dari elemen formulir supaya tidak perlu menduplikasi
     state tiap isian. --}}
<div
    x-data="{
        showing: false,
        sending: false,
        rows: [],
        fields: @js($summary),

        form() {
            return this.$el.closest('form');
        },

        readable(name) {
            const field = this.form()?.elements[name];

            if (! field) return '';

            if (field.tagName === 'SELECT') {
                return field.options[field.selectedIndex]?.text?.trim() ?? '';
            }

            if (field instanceof RadioNodeList || (field.length && ! field.value)) {
                return Array.from(field).filter(i => i.checked).length + ' dipilih';
            }

            return (field.value ?? '').trim();
        },

        open() {
            if (! this.form().reportValidity()) return;

            this.rows = Object.entries(this.fields).map(([label, name]) => ({
                label,
                value: this.readable(name) || '—',
            }));

            this.showing = true;
        },

        send() {
            if (this.sending) return;

            this.sending = true;
            this.form().submit();
        },
    }"
    @keydown.escape.window="showing = false"
>
    <button type="button" @click="open()"
        class="px-6 py-2 bg-{{ $tone }}-600 text-white rounded-lg hover:bg-{{ $tone }}-700 font-medium">
        {{ $label }}
    </button>

    <div x-show="showing" x-cloak
         class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
         @click.self="showing = false"
         role="dialog" aria-modal="true">
        <div x-show="showing"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">{{ $heading }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>

            <dl class="mt-4 divide-y rounded-xl border text-sm" x-show="rows.length">
                <template x-for="row in rows" :key="row.label">
                    <div class="flex gap-3 px-4 py-2.5">
                        <dt class="w-2/5 shrink-0 text-gray-500" x-text="row.label"></dt>
                        <dd class="font-medium text-gray-900 break-words" x-text="row.value"></dd>
                    </div>
                </template>
            </dl>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="showing = false" x-bind:disabled="sending"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    Batal
                </button>
                <button type="button" @click="send()" x-bind:disabled="sending"
                    class="px-5 py-2 rounded-lg bg-{{ $tone }}-600 text-white font-medium hover:bg-{{ $tone }}-700 disabled:opacity-60">
                    <span x-show="! sending">{{ $confirmLabel }}</span>
                    {{-- Tombol dikunci saat mengirim agar tidak menghasilkan
                         dua booking dari satu klik ganda. --}}
                    <span x-show="sending" x-cloak>Mengirim…</span>
                </button>
            </div>
        </div>
    </div>
</div>
