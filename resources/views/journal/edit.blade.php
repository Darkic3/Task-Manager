@extends('layouts.app')

@section('title', __('Edit journal entry'))

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('Edit journal entry') }}</h1>
    @include('journal._form', [
        'entry' => $entry,
        'books' => $books,
        'tags' => $tags,
        'moods' => $moods,
        'selectedTags' => $selectedTags,
        'selectedMoods' => $selectedMoods,
    ])
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action*="/journal/"]');
    if (!form) return;
    const url = form.getAttribute('action');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value;
    let timer = null;
    let indicator = document.createElement('div');
    indicator.className = 'text-muted small mt-2';
    indicator.textContent = '';
    form.appendChild(indicator);

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(save, 4000);
    }

    async function save() {
            const data = {
            title: form.querySelector('input[name="title"]')?.value || null,
            content: form.querySelector('textarea[name="content"]')?.value || '',
            entry_date: form.querySelector('input[name="entry_date"]')?.value,
            status: form.querySelector('select[name="status"]')?.value || 'active',
        };
        indicator.textContent = '{{ __('Saving…') }}';
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });
            if (res.ok) {
                indicator.textContent = '{{ __('Saved') }}';
            } else {
                indicator.textContent = '{{ __('Save failed') }}';
            }
        } catch (e) {
            indicator.textContent = '{{ __('Save failed') }}';
        }
    }

    form.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('input', schedule);
        el.addEventListener('change', schedule);
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            save();
        }
    });
});
</script>
@endpush

@endsection
