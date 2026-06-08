@extends('layouts.app', ['title' => 'Edit eBook'])

@section('content')
<div class="ebook-page">
    <header class="ebook-page-header">
        <div>
            <p class="ebook-eyebrow">Library Management</p>
            <h1 class="ebook-title">Edit eBook</h1>
            <p class="ebook-subtitle">Update metadata, file replacement, publication state, and download access.</p>
        </div>
        <a href="{{ route('admin.books.index') }}" class="ebook-btn ebook-btn-muted">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Dashboard
        </a>
    </header>

    <section class="ebook-panel max-w-3xl mx-auto w-full">
        <div class="ebook-card-header">
            <div>
                <h2 class="ebook-card-title">{{ $book->title }}</h2>
                <p class="ebook-card-subtitle">Leave file inputs blank to keep existing uploads.</p>
            </div>
        </div>
        <div class="ebook-card-body">
            @include('admin.partials.book_form', [
                'action' => route('admin.books.update', $book->id),
                'method' => 'PUT',
                'submitLabel' => 'Update eBook',
                'book' => $book,
            ])
        </div>
    </section>
</div>
@endsection
