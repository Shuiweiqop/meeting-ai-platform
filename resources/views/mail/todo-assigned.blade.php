@component('mail::message')
# You have a new action item

You've been assigned a task from the meeting **{{ $todo->meeting->title }}**.

@component('mail::panel')
**{{ $todo->title }}**
@if($todo->description)
{{ $todo->description }}
@endif
@endcomponent

@component('mail::button', ['url' => url('/meetings/' . $todo->meeting_id)])
View Meeting
@endcomponent

Thanks,
Meeting AI Platform
@endcomponent
