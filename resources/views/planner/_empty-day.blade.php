{{-- Empty state of the "Today's Tasks" box: message + the two add CTAs.
      Rendered by the day view when nothing is pending, and re-inserted
      client-side (Vue island or plSyncEmptyState) whenever the box ends up
      with zero open tasks — no refresh needed. Kept in one partial so the
      server and the client always show the exact same block. --}}
<div class="pl-empty"><i class="bi bi-cup-hot"></i>{{ __('Nothing scheduled for this day. Enjoy!') }}</div>
<div class="pl-qadd" style="display:flex;flex-direction:column;gap:8px;">
    <button type="button" class="pl-qadd-btn" onclick="openTaskAddModal('existing')">
        <span class="pl-qadd-glow" aria-hidden="true"></span>
        <span class="pl-qadd-ico"><i class="bi bi-inboxes" aria-hidden="true"></i></span>
        <span class="pl-qadd-txt">
            <b>{{ __('Pull an existing task into today') }}</b>
            <small>{{ __('Pick from your unscheduled tasks — nothing gets created') }}</small>
        </span>
        <span class="pl-qadd-arrow"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
    </button>
    <button type="button" class="pl-qadd-btn" onclick="openTaskAddModal('new')">
        <span class="pl-qadd-glow" aria-hidden="true"></span>
        <span class="pl-qadd-ico"><i class="bi bi-journal-plus" aria-hidden="true"></i></span>
        <span class="pl-qadd-txt">
            <b>{{ __('Create a new task for today') }}</b>
            <small>{{ __('Add a new task directly to this day') }}</small>
        </span>
        <span class="pl-qadd-arrow"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
    </button>
</div>
