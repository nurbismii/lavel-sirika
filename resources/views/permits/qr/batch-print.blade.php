@extends('layouts.app')

@php
    $pageTitle = 'Cetak Batch QR Aktif';
    $pageDescription = 'QR aktif yang siap dicetak, tanpa membuat atau memperbarui token.';
@endphp

@section('content')
    <style>
        .batch-qr-print__header { display: flex; justify-content: space-between; gap: 16px; align-items: center; }
        .batch-qr-print__filters { margin-bottom: 28px; }
        .batch-qr-print__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12mm 8mm; }
        .batch-qr-card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 5mm; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .batch-qr-card__code svg { display: block; width: 42mm; height: 42mm; margin: 0 auto 3mm; }
        .batch-qr-card__name, .batch-qr-card__nik { margin: 0; overflow-wrap: anywhere; }
        .batch-qr-card__name { font-size: 12px; font-weight: 700; }
        .batch-qr-card__nik { color: #475569; font-size: 11px; }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            .batch-qr-print { box-shadow: none; border: 0; }
            .batch-qr-print .panel-body { padding: 0; }
            .batch-qr-print__grid { gap: 8mm 6mm; }
            .batch-qr-card { padding: 4mm; }
        }
        @media (max-width: 640px) { .batch-qr-print__grid { grid-template-columns: 1fr; } }
    </style>

    <section class="page-section panel batch-qr-print">
        <div class="panel-body">
            <div class="no-print batch-qr-print__header">
                <div>
                    <h2 class="panel-title">Cetak Batch QR Aktif</h2>
                    <p class="panel-subtitle">{{ $cards->count() }} QR aktif siap dicetak. Nama dan NIK tercetak di bawah QR.</p>
                </div>
                <div class="quick-actions">
                    <button class="button button-primary" type="button" onclick="window.print()" @disabled($cards->isEmpty() || $errors->any())>Print</button>
                    <a class="button" href="{{ route('permits.index') }}">Kembali</a>
                </div>
            </div>

            <form class="filter-panel layout-gap no-print batch-qr-print__filters" method="POST" action="{{ route('permits.qr.batch-print') }}" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').textContent = 'Memproses QR…'; this.setAttribute('aria-busy', 'true');">
                @csrf
                @if ($errors->any())
                    <div role="alert">
                        @foreach ($errors->all() as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="form-field" style="margin-bottom: 16px;">
                    <label for="nik_list">Daftar NIK</label>
                    <textarea class="form-control" id="nik_list" name="nik_list" rows="5" maxlength="51000" aria-describedby="nik_list_help">{{ is_string(old('nik_list', $filters['nik_list'])) ? old('nik_list', $filters['nik_list']) : '' }}</textarea>
                    <small id="nik_list_help">Tempel NIK per baris atau pisahkan dengan koma, titik koma, atau spasi. Maksimal 500 NIK unik; duplikat diabaikan. Kosongkan untuk semua NIK. Semua QR yang memenuhi filter untuk setiap NIK akan ditampilkan.</small>
                </div>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="department">Departemen</label>
                        <select class="form-control" id="department" name="department">
                            <option value="">Semua departemen</option>
                            @foreach ($departments as $value => $label)
                                <option value="{{ $value }}" {{ old('department', $filters['department']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="division">Divisi</label>
                        <select class="form-control" id="division" name="division">
                            <option value="">Semua divisi</option>
                            @foreach ($divisions as $value => $label)
                                <option value="{{ $value }}" {{ old('division', $filters['division']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="permit_color">Warna kartu</label>
                        <select class="form-control" id="permit_color" name="permit_color">
                            <option value="">Semua warna</option>
                            @foreach ($permitColors as $value => $label)
                                <option value="{{ $value }}" {{ old('permit_color', $filters['permit_color']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="quick-actions" style="margin-top: 12px;">
                    <button class="button button-primary" type="submit">Terapkan Filter</button>
                    <a class="button" href="{{ route('permits.qr.batch-print') }}">Reset</a>
                </div>
            </form>

            @if ($missingNiks || $unavailableNiks)
                <div class="no-print layout-gap" role="status" style="margin-bottom: 20px; overflow-wrap: anywhere;">
                    @if ($missingNiks)
                        <p><strong>NIK tidak ditemukan ({{ count($missingNiks) }}):</strong> {{ implode(', ', $missingNiks) }}</p>
                    @endif
                    @if ($unavailableNiks)
                        <p><strong>NIK tanpa QR siap cetak ({{ count($unavailableNiks) }}):</strong> {{ implode(', ', $unavailableNiks) }}</p>
                        <p>Periksa status izin, ketersediaan dan masa berlaku QR, serta filter departemen, divisi, dan warna kartu. QR yang tidak dapat dibaca juga tidak ditampilkan.</p>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <p class="empty-state">Perbaiki filter lalu tekan Terapkan Filter untuk menampilkan QR.</p>
            @elseif ($cards->isEmpty())
                <p class="empty-state">Tidak ada QR aktif yang dapat dicetak.</p>
            @else
                <div class="batch-qr-print__grid">
                    @foreach ($cards as $card)
                        <article class="batch-qr-card">
                            <div class="batch-qr-card__code">{!! $card['qrSvg'] !!}</div>
                            <p class="batch-qr-card__name">{{ $card['name'] }}</p>
                            <p class="batch-qr-card__nik">NIK: {{ $card['nik'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
