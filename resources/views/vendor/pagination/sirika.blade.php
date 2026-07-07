@if ($paginator->hasPages())
    <nav class="sirika-pagination" role="navigation" aria-label="Navigasi pagination">
        <p class="sirika-pagination__summary">
            Menampilkan
            <strong>{{ $paginator->firstItem() }}</strong>
            -
            <strong>{{ $paginator->lastItem() }}</strong>
            dari
            <strong>{{ $paginator->total() }}</strong>
            data
        </p>

        <ul class="sirika-pagination__list">
            @if ($paginator->onFirstPage())
                <li>
                    <span class="sirika-pagination__link sirika-pagination__link--disabled" aria-disabled="true">
                        Sebelumnya
                    </span>
                </li>
            @else
                <li>
                    <a class="sirika-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        Sebelumnya
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="sirika-pagination__link sirika-pagination__link--disabled" aria-disabled="true">
                            {{ $element }}
                        </span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="sirika-pagination__link sirika-pagination__link--active" aria-current="page">
                                    {{ $page }}
                                </span>
                            </li>
                        @else
                            <li>
                                <a class="sirika-pagination__link" href="{{ $url }}" aria-label="Buka halaman {{ $page }}">
                                    {{ $page }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <a class="sirika-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        Berikutnya
                    </a>
                </li>
            @else
                <li>
                    <span class="sirika-pagination__link sirika-pagination__link--disabled" aria-disabled="true">
                        Berikutnya
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
