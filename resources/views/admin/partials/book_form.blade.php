@if($errors->any())
    <div class="ebook-error">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
        <div>
            <p class="m-0 font-extrabold">Please correct the following errors:</p>
            <ul class="mt-1 mb-0 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
<div x-data="{ uploading: false, progress: 0 }" class="relative">
    
    <!-- Uploading Overlay with Loading Progress -->
    <div x-show="uploading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-slate-900/60 backdrop-blur-md" x-transition x-cloak>
        <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-sm w-full mx-4 text-center space-y-5">
            <div class="relative w-16 h-16 mx-auto">
                <div class="absolute inset-0 border-4 border-emerald-100 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div class="space-y-2">
                <h3 class="text-lg font-extrabold text-slate-900">Uploading Secure eBook...</h3>
                <p class="text-xs font-semibold text-slate-500">Please keep this page open. Uploading and encrypting PDF pages.</p>
            </div>
            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                <div class="bg-emerald-600 h-full transition-all duration-300 rounded-full" :style="`width: ${progress}%`"></div>
            </div>
            <div class="text-xs font-black text-emerald-600 uppercase tracking-widest" x-text="`Uploading: ${Math.floor(progress)}%`"></div>
        </div>
    </div>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="ebook-form" @submit="uploading = true; let interval = setInterval(() => { if (progress < 92) { progress += Math.floor(Math.random() * 8) + 1; } else if (progress < 99) { progress += 0.2; } else { clearInterval(interval); } }, 450);">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="ebook-field">
        <label for="title" class="ebook-label">Book Title</label>
        <input type="text" name="title" id="title" value="{{ old('title', $book?->title) }}" required placeholder="e.g. Science Grade 4 Curriculum" class="ebook-input">
    </div>

    <div class="ebook-field">
        <label for="description" class="ebook-label">Description</label>
        <textarea name="description" id="description" placeholder="Provide a short summary of the book content." class="ebook-textarea">{{ old('description', $book?->description) }}</textarea>
    </div>

    @php
        $gradeOptions = [
            'Kinder 1',
            'Kinder 2',
            'Grade 1',
            'Grade 2',
            'Grade 3',
            'Grade 4',
            'Grade 5',
            'Grade 6',
            'Grade 7',
            'Grade 8',
            'Grade 9',
            'Grade 10',
            'K11',
            'K12',
        ];
        $selectedGrade = old('grade_level', $book?->grade_level);
        $normalizedSelectedGrade = match (strtolower(trim($selectedGrade ?? ''))) {
            'grade 11' => 'K11',
            'grade 12' => 'K12',
            default => $selectedGrade,
        };
    @endphp

    <div class="ebook-field">
        <label for="grade_level" class="ebook-label">Grade Level</label>
        <select name="grade_level" id="grade_level" required class="ebook-input">
            <option value="" disabled {{ blank($normalizedSelectedGrade) ? 'selected' : '' }}>Select grade level</option>
            @foreach($gradeOptions as $grade)
                <option value="{{ $grade }}" {{ $normalizedSelectedGrade === $grade ? 'selected' : '' }}>
                    {{ $grade }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="ebook-field">
        <label for="pdf_file" class="ebook-label">{{ $book ? 'Replace PDF Document' : 'PDF Document' }}</label>
        <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf" {{ $book ? '' : 'required' }} class="ebook-input">
        <p class="ebook-help">Maximum 50MB. Stored privately and opened through signed routes.</p>
    </div>

    <div class="ebook-form-grid">
        <div class="ebook-field">
            <span class="ebook-label">Publishing Status</span>
            <div class="ebook-choice-row">
                <label class="ebook-choice">
                    <input type="radio" name="status" value="published" class="ebook-radio" {{ old('status', $book?->status ?? 'published') === 'published' ? 'checked' : '' }}>
                    Published
                </label>
                <label class="ebook-choice">
                    <input type="radio" name="status" value="draft" class="ebook-radio" {{ old('status', $book?->status) === 'draft' ? 'checked' : '' }}>
                    Draft
                </label>
            </div>
        </div>

        <div class="ebook-field">
            <span class="ebook-label">Downloads</span>
            <label class="ebook-choice">
                <input type="checkbox" name="is_downloadable" value="1" class="ebook-check" {{ old('is_downloadable', $book?->is_downloadable) ? 'checked' : '' }}>
                Enable PDF download
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
        <a href="{{ route('admin.books.index') }}" class="ebook-btn ebook-btn-muted">Cancel</a>
        <button type="submit" class="ebook-btn ebook-btn-primary">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            {{ $submitLabel }}
        </button>
    </div>
</form>
</div>
