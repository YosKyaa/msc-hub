@if(session('success') || session('error') || $errors->any())
    <div class="fixed inset-x-4 top-20 z-50 ml-auto flex max-w-md flex-col gap-3 sm:inset-x-auto sm:right-4" aria-label="Notifikasi">
        @if(session('success'))
            <x-atoms.alert variant="success" title="Berhasil" :auto-dismiss="8000">
                {{ session('success') }}
            </x-atoms.alert>
        @endif

        @if(session('error'))
            <x-atoms.alert variant="destructive" title="Belum dapat diproses" :auto-dismiss="12000">
                {{ session('error') }}
            </x-atoms.alert>
        @endif

        @if($errors->any())
            <x-atoms.alert variant="destructive" title="Periksa kembali data Anda">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-atoms.alert>
        @endif
    </div>
@endif
