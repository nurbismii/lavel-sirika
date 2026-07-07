<form method="POST" action="{{ $action }}" class="form-stack">
    @csrf

    @isset($method)
        @method($method)
    @endisset

    <div class="form-grid">
        <div class="form-field">
            <label for="name">Nama</label>
            <input
                id="name"
                class="form-control"
                name="name"
                type="text"
                value="{{ old('name', $managedUser->name) }}"
                autocomplete="name"
                required
            >
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="email">Email</label>
            <input
                id="email"
                class="form-control"
                name="email"
                type="email"
                value="{{ old('email', $managedUser->email) }}"
                autocomplete="email"
                required
            >
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="role">Role</label>
            @if ($isSelf ?? false)
                <input type="hidden" name="role" value="{{ $managedUser->role }}">
            @endif
            <select id="role" class="form-control" name="role" required {{ ($isSelf ?? false) ? 'disabled' : '' }}>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" {{ old('role', $managedUser->role) === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <p class="muted-text">Role akun sendiri dikunci untuk mencegah kehilangan akses Super Admin.</p>
            @endif
            @error('role')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="status">Status</label>
            @if ($isSelf ?? false)
                <input type="hidden" name="status" value="{{ $managedUser->status }}">
            @endif
            <select id="status" class="form-control" name="status" required {{ ($isSelf ?? false) ? 'disabled' : '' }}>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" {{ old('status', $managedUser->status) === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <p class="muted-text">Status akun sendiri dikunci agar sesi admin tetap aman.</p>
            @endif
            @error('status')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="password">Password {{ ($passwordRequired ?? false) ? '' : 'baru' }}</label>
            <input
                id="password"
                class="form-control"
                name="password"
                type="password"
                autocomplete="{{ ($passwordRequired ?? false) ? 'new-password' : 'new-password' }}"
                {{ ($passwordRequired ?? false) ? 'required' : '' }}
            >
            <p class="muted-text">
                {{ ($passwordRequired ?? false) ? 'Minimal 8 karakter.' : 'Kosongkan jika password tidak diubah.' }}
            </p>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="password_confirmation">Konfirmasi Password</label>
            <input
                id="password_confirmation"
                class="form-control"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                {{ ($passwordRequired ?? false) ? 'required' : '' }}
            >
        </div>
    </div>

    <div class="form-actions">
        <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
        <a class="button" href="{{ $cancelUrl }}">Batal</a>
    </div>
</form>
