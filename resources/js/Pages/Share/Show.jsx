import { Head } from '@inertiajs/react';

const STATUS_STYLES = {
    pending:    'bg-gray-100 text-gray-600',
    processing: 'bg-yellow-100 text-yellow-700',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
};

export default function Show({ meeting }) {
    return (
        <>
            <Head title={`${meeting.title} — Meeting Summary`} />

            <div className="min-h-screen bg-gray-50 py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

                    {/* Header */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="flex items-start justify-between gap-3">
                            <h1 className="text-2xl font-bold text-gray-900">{meeting.title}</h1>
                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[meeting.status]}`}>
                                {meeting.status}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-gray-400">
                            {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </p>
                        {meeting.description && (
                            <p className="mt-3 text-sm text-gray-600">{meeting.description}</p>
                        )}
                        <p className="mt-4 text-xs text-gray-400 border-t border-gray-100 pt-3">
                            Shared via <span className="font-medium text-indigo-600">Meeting AI Platform</span>
                        </p>
                    </div>

                    {/* AI Summary */}
                    {meeting.ai_summary && (
                        <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">AI Summary</h2>
                            <p className="mt-3 text-sm text-gray-700 leading-relaxed">{meeting.ai_summary.summary}</p>
                            {meeting.ai_summary.key_points?.length > 0 && (
                                <div className="mt-4">
                                    <p className="text-xs font-medium uppercase tracking-wide text-gray-400 mb-2">Key Points</p>
                                    <ul className="space-y-1.5">
                                        {meeting.ai_summary.key_points.map((point, i) => (
                                            <li key={i} className="flex items-start gap-2 text-sm text-gray-700">
                                                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" />
                                                {point}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Action Items */}
                    {meeting.todo_items?.length > 0 && (
                        <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Action Items
                                <span className="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">{meeting.todo_items.length}</span>
                            </h2>
                            <ul className="mt-3 divide-y divide-gray-100">
                                {meeting.todo_items.map((todo) => (
                                    <li key={todo.id} className="flex items-start justify-between gap-3 py-3">
                                        <div>
                                            <p className={`text-sm font-medium ${todo.status === 'completed' ? 'line-through text-gray-400' : 'text-gray-900'}`}>
                                                {todo.title}
                                            </p>
                                            {todo.assignee && (
                                                <p className="mt-0.5 text-xs text-indigo-600">→ {todo.assignee.name}</p>
                                            )}
                                        </div>
                                        <span className="text-xs capitalize text-gray-400 shrink-0">{todo.status.replace('_', ' ')}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Transcript */}
                    {meeting.transcript && (
                        <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">Transcript</h2>
                            <pre className="mt-3 max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-gray-700 leading-relaxed font-sans">
                                {meeting.transcript.content}
                            </pre>
                        </div>
                    )}

                </div>
            </div>
        </>
    );
}
