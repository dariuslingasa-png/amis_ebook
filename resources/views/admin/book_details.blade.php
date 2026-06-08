@extends('layouts/app', ['title' => 'Ebook Pages Management'])

@section('content')
<div class="space-y-8">
    <!-- Breadcrumbs & Heading -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400">
                <a href="{{ route('admin.books.index') }}" class="hover:text-emerald-600 transition-colors">Admin Dashboard</a>
                <span>&middot;</span>
                <span class="text-slate-600">Book Details</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ $book->title }}</h1>
        </div>
        
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.books.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-950 bg-slate-100 hover:bg-slate-200 transition-colors">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                Back to Dashboard
            </a>
            @if($book->pages->isNotEmpty())
                <a href="{{ route('books.show', $book->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-600/10 transition-colors">
                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                    Preview Book
                </a>
            @endif
        </div>
    </div>

    <!-- main layout: upload panel and pages catalog -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Upload Panel -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                    </div>
                    <h2 class="font-extrabold text-slate-900 text-base">Upload Pages</h2>
                </div>

                <form method="POST" action="{{ route('admin.books.upload_pages', $book->id) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    
                    <div class="space-y-1.5">
                        <label for="pages" class="text-xs font-bold text-slate-600 uppercase tracking-wider">Select Page Images</label>
                        <input type="file" id="pages" name="pages[]" multiple required accept="image/*" class="w-full text-xs font-semibold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100/80 file:cursor-pointer transition-all duration-200">
                        <p class="text-[10px] text-slate-400 font-semibold">Max 10MB per image. Select multiple images to upload at once. Pages will be sorted by original filename.</p>
                        @error('pages')
                            <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                        @error('pages.*')
                            <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/10 hover:shadow-lg transition-all duration-200">
                        <i data-lucide="cloud-upload" class="w-4 h-4"></i>
                        Upload & Order Pages
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Book Pages list -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
                <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <h2 class="font-extrabold text-slate-900 text-base">Book Pages ({{ $book->pages->count() }})</h2>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Click a page to remove it</span>
                </div>

                @if($book->pages->isEmpty())
                    <div class="p-12 text-center flex flex-col items-center justify-center space-y-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                            <i data-lucide="files" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="font-bold text-slate-800 text-sm">No pages uploaded</h3>
                            <p class="text-xs text-slate-500 max-w-xs">Upload images using the form on the left. Make sure pages are formatted sequentially (page_01, page_02, etc.).</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        @foreach($book->pages as $page)
                            <div class="group relative bg-slate-50 border border-slate-200/60 rounded-xl overflow-hidden shadow-xs hover:border-slate-300 hover:shadow-sm transition-all duration-200">
                                <div class="aspect-[3/4] overflow-hidden flex items-center justify-center">
                                    <img src="{{ $page->image_path }}" alt="Page {{ $page->page_number }}" class="w-full h-full object-cover">
                                </div>
                                
                                <!-- Page Tag -->
                                <div class="absolute bottom-2 left-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-slate-900/80 text-white backdrop-blur-xs border border-white/5">
                                        Page {{ $page->page_number }}
                                    </span>
                                </div>

                                <!-- Hover delete overlay -->
                                <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-200">
                                    <form method="POST" action="{{ route('admin.books.pages.destroy', $page->id) }}" onsubmit="return confirm('Are you sure you want to delete page {{ $page->page_number }}? The remaining pages will be re-sequenced.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-9 h-9 bg-rose-600 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-105" title="Delete Page">
                                            <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
