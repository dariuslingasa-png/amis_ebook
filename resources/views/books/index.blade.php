@extends('layouts.app', ['title' => 'eBook Catalog'])

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

        <div x-data="{ selectedGrade: 'all', viewMode: 'grouped' }" class="ebook-catalog-list">
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

            <!-- View Mode Toggle (Only visible when 'All' tab is selected) -->
            <div x-show="selectedGrade === 'all'" class="flex justify-end mb-6">
                <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/60 select-none shadow-sm">
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
            <div x-show="selectedGrade === 'all' && viewMode === 'grouped'" class="space-y-10">
                @php
                    $hasBooks = false;
                @endphp
                @foreach($gradeTabs as $grade)
                    @php
                        $gradeBooks = $books->filter(fn($b) => $gradeKey($b->grade_level) === $grade['key']);
                    @endphp
                    @if($gradeBooks->isNotEmpty())
                        @php $hasBooks = true; @endphp
                        <div class="ebook-grade-section border-t border-slate-100 pt-6 first:border-0 first:pt-0">
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
                                    @include('books.partials.card', ['book' => $book, 'gradeKey' => $gradeKey])
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
            <div x-show="selectedGrade === 'all' && viewMode === 'flat'">
                <section class="ebook-grid">
                    @foreach($books as $book)
                        @include('books.partials.card', ['book' => $book, 'gradeKey' => $gradeKey])
                    @endforeach
                </section>
            </div>

            <!-- Filtered view for specific grade tabs -->
            <div x-show="selectedGrade !== 'all'">
                @foreach($gradeTabs as $grade)
                    @php
                        $gradeBooks = $books->filter(fn($b) => $gradeKey($b->grade_level) === $grade['key']);
                    @endphp
                    <div x-show="selectedGrade === {{ Js::from($grade['key']) }}">
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
                                    @include('books.partials.card', ['book' => $book, 'gradeKey' => $gradeKey])
                                @endforeach
                            </section>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@endsection
