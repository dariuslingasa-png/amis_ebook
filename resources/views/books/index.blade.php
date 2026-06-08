@extends('layouts.app', ['title' => 'AMIS eBook Portal'])

@section('content')

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
                ['label' => 'GRADE 1', 'key' => 'grade-1'],
                ['label' => 'GRADE 2', 'key' => 'grade-2'],
                ['label' => 'GRADE 3', 'key' => 'grade-3'],
                ['label' => 'GRADE 4', 'key' => 'grade-4'],
                ['label' => 'GRADE 5', 'key' => 'grade-5'],
                ['label' => 'GRADE 6', 'key' => 'grade-6'],
                ['label' => 'GRADE 7', 'key' => 'grade-7'],
                ['label' => 'GRADE 8', 'key' => 'grade-8'],
                ['label' => 'GRADE 9', 'key' => 'grade-9'],
                ['label' => 'GRADE 10', 'key' => 'grade-10'],
                ['label' => 'GRADE 11', 'key' => 'grade-11'],
                ['label' => 'GRADE 12', 'key' => 'grade-12'],
            ];

            $gradeKey = function ($grade) {
                $grade = strtolower(trim($grade ?? ''));
                $grade = preg_replace('/\s+/', ' ', $grade);

                if ($grade === 'kindergarten') {
                    return 'kindergarten';
                }

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

            $gradeKeys = function ($grade) use ($gradeKey) {
                $key = $gradeKey($grade);

                return $key === 'kindergarten' ? ['kinder-1', 'kinder-2'] : [$key];
            };

            $gradeLabel = function ($grade, ?string $contextKey = null) use ($gradeKey) {
                $key = $contextKey ?: $gradeKey($grade);

                return match ($key) {
                    'kindergarten' => 'KINDER 1 / KINDER 2',
                    'kinder-1' => 'KINDER 1',
                    'kinder-2' => 'KINDER 2',
                    'grade-11' => 'GRADE 11',
                    'grade-12' => 'GRADE 12',
                    default => strtoupper(str_replace('-', ' ', (string) $key)),
                };
            };
        @endphp

        <div x-data="{ 
            selectedGrade: 'all', 
            viewMode: 'grouped', 
            search: '',
            booksList: {{ Js::from($books->map(fn($b) => [
                'id' => $b->id,
                'title' => strtolower($b->title),
                'desc' => strtolower($b->description ?? ''),
                'grades' => $gradeKeys($b->grade_level)
            ])->toArray()) }},
            hasMatches() {
                const q = this.search.toLowerCase().trim();
                if (q === '') return true;
                return this.booksList.some(b => {
                    if (this.selectedGrade !== 'all' && !b.grades.includes(this.selectedGrade)) {
                        return false;
                    }
                    return b.title.includes(q) || b.desc.includes(q);
                });
            }
        }" class="ebook-catalog-list">
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

            <!-- Search and View Mode Toggle -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <!-- Search bar -->
                <div class="relative w-full max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input type="text" 
                           x-model="search" 
                           placeholder="Search ebooks by title..." 
                           class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-200/80 bg-white text-sm font-semibold outline-none transition-all focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                    >
                    <button x-show="search" @click="search = ''" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Toggle (Only visible when 'All' tab is selected) -->
                <div x-show="selectedGrade === 'all'" class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/60 select-none shadow-sm self-start md:self-auto">
                    <button type="button" 
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all duration-150 cursor-pointer"
                            :class="viewMode === 'grouped' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                            @click="viewMode = 'grouped'">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                        Grouped by Grade
                    </button>
                    <button type="button" 
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all duration-150 cursor-pointer"
                            :class="viewMode === 'flat' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                            @click="viewMode = 'flat'">
                        <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                        All Books
                    </button>
                </div>
            </div>

            <!-- Grouped view for 'ALL' tab -->
            <div x-show="selectedGrade === 'all' && viewMode === 'grouped' && hasMatches()" class="space-y-10">
                @php
                    $hasBooks = false;
                @endphp
                @foreach($gradeTabs as $grade)
                    @php
                        $gradeBooks = $books->filter(fn($b) => in_array($grade['key'], $gradeKeys($b->grade_level), true));
                    @endphp
                    @if($gradeBooks->isNotEmpty())
                        @php $hasBooks = true; @endphp
                        <div class="ebook-grade-section border-t border-slate-100 pt-6 first:border-0 first:pt-0"
                             x-show="search === '' || {{ Js::from($gradeBooks->map(fn($b) => strtolower($b->title) . ' ' . strtolower($b->description ?? ''))->toArray()) }}.some(t => t.includes(search.toLowerCase()))">
                            <div class="flex items-center gap-3 mb-6 select-none">
                                <span class="h-px flex-1 bg-slate-200/80"></span>
                                <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full flex items-center gap-1.5 border border-slate-200/60 shadow-sm">
                                    <i data-lucide="graduation-cap" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    {{ $grade['label'] }}
                                </h2>
                                <span class="h-px flex-1 bg-slate-200/80"></span>
                            </div>
                            <section class="ebook-grid">
                                @foreach($gradeBooks as $book)
                                    @include('books.partials.card', [
                                        'book' => $book,
                                        'gradeKey' => $gradeKey,
                                        'displayGrade' => $gradeLabel($book->grade_level, $grade['key']),
                                    ])
                                @endforeach
                            </section>
                        </div>
                    @endif
                @endforeach
                
                @if(!$hasBooks)
                    <section class="ebook-empty">
                        <span class="ebook-empty-icon">
                            <i data-lucide="folder-open" class="w-7 h-7"></i>
                        </span>
                        <h3>No eBooks found</h3>
                        <p>There are no published eBooks available yet.</p>
                    </section>
                @endif
            </div>

            <!-- Flat view for 'ALL' tab (shows all books in a single grid without group dividers) -->
            <div x-show="selectedGrade === 'all' && viewMode === 'flat' && hasMatches()">
                <section class="ebook-grid">
                    @foreach($books as $book)
                        @include('books.partials.card', [
                            'book' => $book,
                            'gradeKey' => $gradeKey,
                            'displayGrade' => $gradeLabel($book->grade_level),
                        ])
                    @endforeach
                </section>
            </div>

            <!-- Filtered view for specific grade tabs -->
            <div x-show="selectedGrade !== 'all'">
                @foreach($gradeTabs as $grade)
                    @php
                        $gradeBooks = $books->filter(fn($b) => in_array($grade['key'], $gradeKeys($b->grade_level), true));
                    @endphp
                    <div x-show="selectedGrade === {{ Js::from($grade['key']) }} && hasMatches()">
                        @if($gradeBooks->isEmpty())
                            <section class="ebook-empty">
                                <span class="ebook-empty-icon">
                                    <i data-lucide="folder-open" class="w-7 h-7"></i>
                                </span>
                                <h3>No eBooks found</h3>
                                <p>There are no published eBooks available for {{ $grade['label'] }} yet.</p>
                            </section>
                        @else
                            <section class="ebook-grid">
                                @foreach($gradeBooks as $book)
                                    @include('books.partials.card', [
                                        'book' => $book,
                                        'gradeKey' => $gradeKey,
                                        'displayGrade' => $gradeLabel($book->grade_level, $grade['key']),
                                    ])
                                @endforeach
                            </section>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- No search results fallback -->
            <div x-show="!hasMatches()" x-cloak class="ebook-empty py-12">
                <span class="ebook-empty-icon">
                    <i data-lucide="search" class="w-7 h-7"></i>
                </span>
                <h3>No eBooks match your search</h3>
                <p>Try checking the spelling or searching for a different grade level.</p>
            </div>
        </div>
    @endif
</div>

@endsection
