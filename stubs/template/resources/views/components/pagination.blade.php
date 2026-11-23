{{--
    Previous, the page numbers and next, as plain links. Laravel's own pagination views
    assume Tailwind or Bootstrap; this one uses the template's classes. Renders nothing
    when everything fits on one page.

    "Page navigation" rather than "Pagination": a key that is one word is also read as
    the name of a translation file, and lang/{locale}/pagination.php is Laravel's own
    (on a case-insensitive disk the capital does not save it), so __() would hand back
    that whole file.

    A long run of pages is cut down to the first, the last and two either side of the
    current one, with an ellipsis for what is left out.
--}}
@props(['paginator'])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $shown = collect(range(1, $last))->filter(fn (int $number): bool => $number === 1 || $number === $last || abs($number - $current) <= 2)->values();
    @endphp

    <nav class="pagination" aria-label="@lang('Page navigation')">
        <ul>
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pagination-previous" aria-disabled="true">@lang('Previous')</span>
                @else
                    <a class="pagination-previous" href="{{ $paginator->previousPageUrl() }}" rel="prev">@lang('Previous')</a>
                @endif
            </li>

            @foreach ($shown as $number)
                @if ($loop->index > 0 && $number - $shown[$loop->index - 1] > 1)
                    <li><span class="pagination-gap" aria-hidden="true">…</span></li>
                @endif
                <li>
                    @if ($number === $current)
                        <span class="pagination-page" aria-current="page"><span class="visually-hidden">@lang('Page')</span> {{ $number }}</span>
                    @else
                        <a class="pagination-page" href="{{ $paginator->url($number) }}"><span class="visually-hidden">@lang('Page')</span> {{ $number }}</a>
                    @endif
                </li>
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a class="pagination-next" href="{{ $paginator->nextPageUrl() }}" rel="next">@lang('Next')</a>
                @else
                    <span class="pagination-next" aria-disabled="true">@lang('Next')</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
