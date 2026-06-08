@extends('layouts.app', ['title' => 'eBook Catalog'])

@section('content')
<script src="{{ asset('js/pdf.min.js') }}"></script>

<div class="ebook-page">
    <header class="ebook-page-header">
        <div>
            <p class="ebook-eyebrow">Digital Library</p>
            <h1 class="ebook-title">AMIS eBooks</h1>
            <p class="ebook-subtitle">Open AMIS learning materials with secure reader access.</p>
        </div>
        <span class="ebook-tag ebook-tag-emerald">
            <i data-lucide="book-copy" class="w-3.5 h-3.5"></i>
            {{ $books->count() }} available
        </span>
    </header>

    @if($books->isEmpty())
        <section class="ebook-empty">
            <span class="ebook-empty-icon">
                <i data-lucide="folder-open" class="w-7 h-7"></i>
            </span>
            <h3>No eBooks found</h3>
            <p>There are no published eBooks available yet.</p>
        </section>
    @else
        @php
            $gradeTabs = [
                ['label' => 'KINDER 1', 'key' => 'kinder-1'],
                ['label' => 'KINDER 2', 'key' => 'kinder-2'],
                ['label' => 'G1', 'key' => 'grade-1'],
                ['label' => 'G2', 'key' => 'grade-2'],
                ['label' => 'G3', 'key' => 'grade-3'],
                ['label' => 'G4', 'key' => 'grade-4'],
                ['label' => 'G5', 'key' => 'grade-5'],
                ['label' => 'G6', 'key' => 'grade-6'],
                ['label' => 'G7', 'key' => 'grade-7'],
                ['label' => 'G8', 'key' => 'grade-8'],
                ['label' => 'G9', 'key' => 'grade-9'],
                ['label' => 'G10', 'key' => 'grade-10'],
                ['label' => 'K11', 'key' => 'grade-11'],
                ['label' => 'K12', 'key' => 'grade-12'],
            ];

            $gradeKey = function ($grade) {
                $grade = strtolower(trim($grade ?? ''));
                $grade = preg_replace('/\s+/', ' ', $grade);

                if (preg_match('/^kinder\s*([12])$/', $grade, $match)) {
                    return 'kinder-' . $match[1];
                }

                if (preg_match('/^(?:grade\s*)?([1-9]|1[0-2])$/', $grade, $match)) {
                    return 'grade-' . $match[1];
                }

                if (preg_match('/^k(?:11|12)$/', $grade)) {
                    return 'grade-' . substr($grade, 1);
                }

                return $grade;
            };
        @endphp

        <div x-data="{ selectedGrade: 'all' }" class="ebook-catalog-list">
            <div class="ebook-grade-tabs" role="tablist" aria-label="Grade filter">
                <button type="button"
                        class="ebook-grade-tab"
                        :class="selectedGrade === 'all' ? 'is-active' : ''"
                        @click="selectedGrade = 'all'">
                    All
                </button>
                @foreach($gradeTabs as $grade)
                    <button type="button"
                            class="ebook-grade-tab"
                            :class="selectedGrade === {{ Js::from($grade['key']) }} ? 'is-active' : ''"
                            @click="selectedGrade = {{ Js::from($grade['key']) }}">
                        {{ $grade['label'] }}
                    </button>
                @endforeach
            </div>

            <section class="ebook-grid">
                @foreach($books as $book)
                    @php
                        $bookGrade = trim($book->grade_level ?? '');
                        $bookGradeKey = $gradeKey($bookGrade);
                        $previewUrl = URL::temporarySignedRoute(
                            'ebooks.stream',
                            now()->addMinutes(30),
                            ['ebook' => $book->id, 'preview' => 1]
                        );
                    @endphp
                    <article class="ebook-card ebook-book-card"
                             x-show="selectedGrade === 'all' || selectedGrade === {{ Js::from($bookGradeKey) }}">
                        <div class="ebook-cover ebook-pdf-cover">
                            <canvas class="ebook-cover-canvas"
                                    data-pdf-preview="{{ $previewUrl }}"
                                    aria-label="{{ $book->title }} first page preview"></canvas>
                            <div class="ebook-cover-placeholder">
                                <i data-lucide="book-open" class="w-12 h-12"></i>
                                Loading
                            </div>
                        </div>

                        <div class="ebook-book-body">
                            <div>
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
                @endforeach
            </section>
        </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const previews = Array.from(document.querySelectorAll('[data-pdf-preview]'));
        if (!previews.length || !window.pdfjsLib) return;

        pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('js/pdf.worker.min.js') }}';

        const renderPreview = async (canvas) => {
            if (canvas.dataset.rendered === '1') return;
            canvas.dataset.rendered = '1';

            const cover = canvas.closest('.ebook-pdf-cover');
            const context = canvas.getContext('2d');
            let pdf = null;

            try {
                const loadingTask = pdfjsLib.getDocument({
                    url: canvas.dataset.pdfPreview,
                    disableAutoFetch: true,
                    disableStream: true
                });
                pdf = await loadingTask.promise;

                const page = await pdf.getPage(1);
                const baseViewport = page.getViewport({ scale: 1 });
                const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
                const targetWidth = Math.max(220, Math.ceil((cover?.clientWidth || 240) * pixelRatio));
                const viewport = page.getViewport({ scale: targetWidth / baseViewport.width });

                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);

                await page.render({
                    canvasContext: context,
                    viewport
                }).promise;

                cover?.classList.add('is-loaded');
            } catch (error) {
                console.error('Failed to render eBook preview:', error);
                cover?.classList.add('is-error');
            } finally {
                try {
                    await pdf?.destroy?.();
                } catch (error) {}
            }
        };

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    observer.unobserve(entry.target);
                    renderPreview(entry.target);
                });
            }, { rootMargin: '160px' });

            previews.forEach((canvas) => observer.observe(canvas));
            return;
        }

        previews.forEach(renderPreview);
    });
</script>
@endsection
