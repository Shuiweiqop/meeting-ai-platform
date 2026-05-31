@component('mail::message')
# Your meeting is ready

**{{ $meeting->title }}** has been processed by AI.

@if($meeting->aiSummary)
## Summary
{{ $meeting->aiSummary->summary }}

@if($meeting->aiSummary->key_points && count($meeting->aiSummary->key_points) > 0)
## Key Points
@foreach($meeting->aiSummary->key_points as $point)
- {{ $point }}
@endforeach
@endif
@endif

@if($meeting->todoItems && $meeting->todoItems->count() > 0)
## Action Items ({{ $meeting->todoItems->count() }})
@foreach($meeting->todoItems as $todo)
- **{{ $todo->title }}**{{ $todo->assignee ? ' → ' . $todo->assignee->name : '' }}
@endforeach
@endif

@component('mail::button', ['url' => url('/meetings/' . $meeting->id)])
View Full Meeting
@endcomponent

Thanks,
Meeting AI Platform
@endcomponent
