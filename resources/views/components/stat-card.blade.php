@props([
    'label',
    'value',
    'note' => null,
])

<section class="panel stat-card">
    <div class="panel-body">
        <p class="stat-card__label">{{ $label }}</p>
        <p class="stat-card__value">{{ $value }}</p>

        @if ($note)
            <p class="stat-card__note">{{ $note }}</p>
        @endif
    </div>
</section>
