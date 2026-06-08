@php
    $bookGrade = trim($book->grade_level ?? '');
    $bookGradeKey = $gradeKey($bookGrade);
    $previewUrl = URL::temporarySignedRoute(
        'ebooks.stream',
        now()->addMinutes(30),
        ['ebook' => $book->id, 'preview' => 1]
    );
@endphp
<article class="ebook-card ebook-book-card">
    <div class="ebook-cover ebook-pdf-cover">
        <canvas class="ebook-cover-canvas"
                data-pdf-preview="{{ $previewUrl }}"
                aria-label="{{ $book->title }} first page preview"></canvas>
        <div class="ebook-cover-placeholder"></div>
    </div>

    <div class="ebook-book-body">
        <div>
            <div class="mb-2 flex flex-wrap gap-1">
                <span class="ebook-tag ebook-tag-emerald text-[10px] uppercase font-bold tracking-wider">
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
