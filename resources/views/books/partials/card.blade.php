@php
    $bookGrade = trim($book->grade_level ?? '');
    $bookGradeKey = $gradeKey($bookGrade);
    $coverUrl = $book->cover_url;
@endphp
<article class="ebook-card ebook-book-card"
         x-show="search === '' || {{ Js::from(strtolower($book->title)) }}.includes(search.toLowerCase()) || {{ Js::from(strtolower($book->description ?? '')) }}.includes(search.toLowerCase())">
    <div class="ebook-cover">
        @if($coverUrl)
            <img src="{{ $coverUrl }}"
                 alt="{{ $book->title }} cover"
                 loading="lazy"
                 width="400" height="533"
                 class="ebook-cover-img"
                 onload="this.parentElement.classList.add('is-loaded')"
                 onerror="this.style.display='none'; this.parentElement.classList.add('is-error')">
            <div class="ebook-cover-placeholder"></div>
        @else
            <div class="ebook-cover-fallback">
                <span class="ebook-cover-fallback-icon">
                    <i data-lucide="book-open" class="w-8 h-8"></i>
                </span>
                <span class="ebook-cover-fallback-title">{{ $book->title }}</span>
            </div>
        @endif
    </div>

    <div class="ebook-book-body">
        <div>
            <div class="mb-2 flex flex-wrap gap-1">
                <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-700 bg-emerald-50/80 border border-emerald-100/60 px-2 py-0.5 rounded-md select-none">
                    {{ $book->grade_level }}
                </span>
            </div>
            <h2 class="ebook-book-title" title="{{ $book->title }}">{{ $book->title }}</h2>
            <p class="ebook-book-desc">{{ $book->description ?: 'No description provided.' }}</p>
        </div>

        <div class="flex flex-col gap-2 mt-auto">
            <a href="{{ route('books.show', $book->id) }}" class="ebook-btn ebook-btn-primary w-full">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                Open eBook
            </a>

            @if($book->is_downloadable || Auth::user()?->role === 'admin')
                @php
                    $downloadUrl = URL::temporarySignedRoute(
                        'ebooks.stream',
                        now()->addMinutes(15),
                        ['ebook' => $book->id]
                    );
                @endphp
                <a href="{{ $downloadUrl }}" download class="ebook-btn ebook-btn-muted w-full">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Download PDF
                </a>
            @endif
        </div>
    </div>
</article>
