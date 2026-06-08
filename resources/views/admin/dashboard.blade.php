@extends('layouts.app', ['title' => 'Admin Dashboard'])

@section('content')
<div class="ebook-page">
    <header class="ebook-page-header">
        <div>
            <p class="ebook-eyebrow">Library Management</p>
            <h1 class="ebook-title">Admin Dashboard</h1>
            <p class="ebook-subtitle">Manage eBooks, assign them to grade levels, and review secure reader activity.</p>
        </div>
        <div class="ebook-actions">
            <a href="{{ route('admin.books.create') }}" class="ebook-btn ebook-btn-primary">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                Upload eBook
            </a>
        </div>
    </header>

    <section class="ebook-stats">
        <article class="ebook-stat">
            <span class="ebook-stat-icon bg-emerald-50 text-emerald-700">
                <i data-lucide="book-open" class="w-6 h-6"></i>
            </span>
            <div>
                <span>Total eBooks</span>
                <strong>{{ $totalEbooks }}</strong>
            </div>
        </article>
        <article class="ebook-stat">
            <span class="ebook-stat-icon bg-sky-50 text-sky-700">
                <i data-lucide="eye" class="w-6 h-6"></i>
            </span>
            <div>
                <span>Reader Views</span>
                <strong>{{ $totalViews }}</strong>
            </div>
        </article>
        <article class="ebook-stat">
            <span class="ebook-stat-icon bg-violet-50 text-violet-700">
                <i data-lucide="file-down" class="w-6 h-6"></i>
            </span>
            <div>
                <span>PDF Streams</span>
                <strong>{{ $totalDownloads }}</strong>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="ebook-card">
                <div class="ebook-card-header">
                    <div>
                        <h2 class="ebook-card-title">eBooks Directory</h2>
                        <p class="ebook-card-subtitle">Uploaded books and publication status</p>
                    </div>
                </div>

                @if($books->isEmpty())
                    <div class="ebook-card-body">
                        <div class="ebook-empty">
                            <span class="ebook-empty-icon"><i data-lucide="book-open" class="w-7 h-7"></i></span>
                            <h3>No eBooks uploaded</h3>
                            <p>Upload your first PDF eBook to start building the digital library.</p>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($books as $book)
                            <article class="p-5 flex flex-col sm:flex-row gap-4 hover:bg-gray-50 transition-colors">
                                <div class="w-16 h-20 rounded-lg bg-gray-50 border border-gray-200 overflow-hidden flex items-center justify-center shrink-0">
                                    <i data-lucide="book" class="w-8 h-8 text-gray-300"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="m-0 text-base font-extrabold text-gray-950 truncate">{{ $book->title }}</h3>
                                        <span class="ebook-tag {{ $book->status === 'published' ? 'ebook-tag-emerald' : 'ebook-tag-slate' }}">
                                            {{ $book->status === 'published' ? 'Published' : 'Draft' }}
                                        </span>
                                    </div>
                                    <p class="ebook-book-desc mt-1">{{ $book->description ?: 'No description provided.' }}</p>
                                    @if($book->is_downloadable)
                                        <div class="ebook-tags mt-3">
                                            <span class="ebook-tag ebook-tag-amber">Downloads On</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('books.show', $book->id) }}" target="_blank" class="ebook-icon-btn" title="Open eBook">
                                        <i data-lucide="book-open" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('admin.books.edit', $book->id) }}" class="ebook-icon-btn" title="Edit eBook">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.books.destroy', $book->id) }}" onsubmit="return confirm('Delete this eBook and remove its files?');" class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ebook-icon-btn text-rose-600 hover:bg-rose-50" title="Delete eBook">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <aside class="ebook-card">
            <div class="ebook-card-header">
                <div>
                    <h2 class="ebook-card-title">Recent Activity</h2>
                    <p class="ebook-card-subtitle">Latest reader access logs</p>
                </div>
            </div>
            <div class="ebook-card-body">
                <div class="flex flex-col gap-4">
                    @forelse($recentLogs as $log)
                        <article class="flex gap-3">
                            <span class="ebook-stat-icon {{ $log->action === 'stream' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}" style="width:34px;height:34px;flex-basis:34px;border-radius:10px;">
                                <i data-lucide="{{ $log->action === 'stream' ? 'file-down' : 'eye' }}" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="m-0 text-sm font-bold text-gray-900">
                                    {{ $log->user->name ?? 'Unknown User' }}
                                    <span class="font-semibold text-gray-500">{{ $log->action === 'stream' ? 'streamed' : 'read' }}</span>
                                </p>
                                <p class="m-0 truncate text-xs font-semibold text-emerald-700">{{ $log->ebook->title ?? 'Deleted Book' }}</p>
                                <p class="m-0 text-[11px] font-bold text-gray-400">{{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </article>
                    @empty
                        <div class="ebook-empty" style="min-height:180px;">
                            <span class="ebook-empty-icon"><i data-lucide="inbox" class="w-7 h-7"></i></span>
                            <h3>No recent activity</h3>
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>
    </section>
</div>
@endsection
