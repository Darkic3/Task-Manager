<div class="cu-list-head">
    <div>{{ __('Task') }}</div><div>{{ __('Project') }}</div><div>{{ __('Priority') }}</div>
    <div>{{ __('Assignee') }}</div><div>{{ __('Due Date') }}</div><div></div>
</div>
@foreach(collect($tasks)->flatten() as $task)
    @include('tasks._list-row', ['task' => $task])
@endforeach