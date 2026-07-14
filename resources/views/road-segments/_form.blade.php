<form method="POST" action="{{ $formAction }}" class="form-stack">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="form-field">
        <label for="code">Kode Segmen</label>
        <input id="code" name="code" value="{{ old('code', $roadSegment->code ?? '') }}" required>
        @error('code')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="name">Nama Segmen</label>
        <input id="name" name="name" value="{{ old('name', $roadSegment->name ?? '') }}" required>
        @error('name')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="start_location">Lokasi Awal</label>
        <input id="start_location" name="start_location" value="{{ old('start_location', $roadSegment->start_location ?? '') }}" required>
        @error('start_location')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="end_location">Lokasi Akhir</label>
        <input id="end_location" name="end_location" value="{{ old('end_location', $roadSegment->end_location ?? '') }}" required>
        @error('end_location')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    <div class="table-actions">
        <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
        <a class="button" href="{{ route('road-segments.index') }}">Batal</a>
    </div>
</form>
