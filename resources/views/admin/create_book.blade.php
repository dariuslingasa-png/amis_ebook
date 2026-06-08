@extends('layouts.app', ['title' => 'Upload eBook'])

@section('content')
<div class="ebook-page">
    <header class="ebook-page-header">
        <div>
            <p class="ebook-eyebrow">Library Management</p>
            <h1 class="ebook-title">Upload New eBook</h1>
            <p class="ebook-subtitle">Assign a private PDF to a grade level for secure student or teacher access.</p>
        </div>
        <a href="{{ route('admin.books.index') }}" class="ebook-btn ebook-btn-muted">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Dashboard
        </a>
    </header>

    <section class="ebook-panel max-w-3xl mx-auto w-full">
        <div class="ebook-card-header">
            <div>
                <h2 class="ebook-card-title">Book Details</h2>
                <p class="ebook-card-subtitle">PDF files stay private and are served with signed URLs.</p>
            </div>
        </div>
        <div class="ebook-card-body">
            @include('admin.partials.book_form', [
                'action' => route('admin.books.store'),
                'method' => 'POST',
                'submitLabel' => 'Upload eBook',
                'book' => null,
            ])
        </div>
    </section>
</div>
@endsection
