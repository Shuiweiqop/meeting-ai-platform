import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_STYLES = {
    pending:    'bg-gray-100 text-gray-600',
    processing: 'bg-yellow-100 text-yellow-700',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
};

function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[status] ?? STATUS_STYLES.pending}`}>
            {status}
        </span>
    );
}

function TodoRow({ todo: initial }) {
    const [todo, setTodo] = useState(initial);
    const [saving, setSaving] = useState(false);

    const toggle = () => {
        const next = todo.status === 'completed' ? 'pending' : 'completed';
        setSaving(true);
        router.patch(
            route('todo-items.update', todo.id),
            { status: next },
            {
                preserveScroll: true,
                onSuccess: () => setTodo({ ...todo, status: next }),
                onFinish: () => setSaving(false),
            },
        );
    };

    const done = todo.status === 'completed';

    return (
        <li className="flex items-start gap-3 py-3">
            <button
                onClick={toggle}
                disabled={saving}
                className={`mt-0.5 h-5 w-5 shrink-0 rounded border-2 transition ${
                    done
                        ? 'border-green-500 bg-green-500 text-white'
                        : 'border-gray-300 bg-white hover:border-indigo-400'
                }`}
                aria-label="Toggle task"
            >
                {done && (
                    <svg viewBox="0 0 12 12" fill="currentColor" className="h-full w-full p-0.5">
                        <path d="M10 3L5 8.5 2 5.5" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                )}
            </button>
            <div className="flex-1 min-w-0">
                <p className={`text-sm font-medium ${done ? 'text-gray-400 line-through' : 'text-gray-900'}`}>
                    {todo.title}
                </p>
                {todo.description && (
                    <p className="mt-0.5 text-xs text-gray-500">{todo.description}</p>
                )}
                {todo.assignee && (
                    <p className="mt-1 text-xs text-indigo-600">→ {todo.assignee.name}</p>
                )}
            </div>
        </li>
    );
}

export default function Show({ meeting }) {
    const { props } = usePage();
    const audioUrl = meeting.audio_path ? `/storage/${meeting.audio_path}` : null;
    const isProcessing = meeting.status === 'pending' || meeting.status === 'processing';
    const shareUrl = meeting.share_token ? `${window.location.origin}/share/${meeting.share_token}` : null;
    const [copied, setCopied] = useState(false);

    const copyShareUrl = (url) => {
        navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    useEffect(() => {
        if (!isProcessing) return;
        const id = setInterval(() => router.reload({ only: ['meeting'] }), 3000);
        return () => clearInterval(id);
    }, [isProcessing]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">{meeting.title}</h2>
                        <StatusBadge status={meeting.status} />
                    </div>
                    <div className="flex items-center gap-3">
                        {meeting.status === 'completed' && (
                            <>
                                {shareUrl ? (
                                    <button
                                        onClick={() => copyShareUrl(shareUrl)}
                                        className="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200"
                                    >
                                        {copied ? '✓ Copied!' : '🔗 Copy Link'}
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => router.post(route('meetings.share.generate', meeting.id))}
                                        className="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200"
                                    >
                                        Share
                                    </button>
                                )}
                                <a
                                    href={route('meetings.export', meeting.id)}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                                    target="_blank"
                                >
                                    Export PDF
                                </a>
                            </>
                        )}
                        <Link href={route('meetings.edit', meeting.id)} className="text-sm font-medium text-gray-600 hover:text-gray-900">
                            Edit
                        </Link>
                        <Link href={route('meetings.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            ← My Meetings
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={meeting.title} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">

                    {/* Meta */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <p className="text-xs text-gray-400">
                            {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </p>
                        {meeting.description && (
                            <p className="mt-2 text-sm text-gray-600">{meeting.description}</p>
                        )}
                        {audioUrl && (
                            <div className="mt-4">
                                <p className="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Recording</p>
                                <audio controls className="w-full rounded">
                                    <source src={audioUrl} />
                                </audio>
                            </div>
                        )}
                    </div>

                    {/* Processing banner */}
                    {isProcessing && (
                        <div className="flex items-center gap-3 rounded-lg bg-yellow-50 p-4 ring-1 ring-yellow-200">
                            <svg className="h-5 w-5 animate-spin text-yellow-500" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
                            </svg>
                            <p className="text-sm text-yellow-800">
                                {meeting.status === 'processing'
                                    ? 'AI is processing your recording — this may take a few minutes.'
                                    : 'Waiting in queue to be processed…'}
                            </p>
                        </div>
                    )}

                    {meeting.status === 'failed' && (
                        <div className="flex items-center justify-between rounded-lg bg-red-50 p-4 ring-1 ring-red-200">
                            <p className="text-sm text-red-700">Processing failed. You can retry or re-upload the recording.</p>
                            <button
                                onClick={() => router.post(route('meetings.retry', meeting.id))}
                                className="ml-4 shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500"
                            >
                                Retry
                            </button>
                        </div>
                    )}

                    {/* Transcript */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">Transcript</h3>
                        {meeting.transcript ? (
                            <pre className="mt-3 max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-gray-700 leading-relaxed font-sans">
                                {meeting.transcript.content}
                            </pre>
                        ) : (
                            <p className="mt-3 text-sm text-gray-400 italic">
                                {isProcessing ? 'Being transcribed…' : 'No transcript yet.'}
                            </p>
                        )}
                    </div>

                    {/* AI Summary */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">AI Summary</h3>
                        {meeting.ai_summary ? (
                            <div className="mt-3 space-y-4">
                                <p className="text-sm text-gray-700 leading-relaxed">{meeting.ai_summary.summary}</p>
                                {meeting.ai_summary.key_points?.length > 0 && (
                                    <div>
                                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Key Points</p>
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
                        ) : (
                            <p className="mt-3 text-sm text-gray-400 italic">
                                {isProcessing ? 'Generating summary…' : 'No summary yet.'}
                            </p>
                        )}
                    </div>

                    {/* Action Items */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Action Items
                            {meeting.todo_items?.length > 0 && (
                                <span className="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">
                                    {meeting.todo_items.length}
                                </span>
                            )}
                        </h3>
                        {meeting.todo_items?.length > 0 ? (
                            <ul className="mt-2 divide-y divide-gray-100">
                                {meeting.todo_items.map((todo) => (
                                    <TodoRow key={todo.id} todo={todo} />
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 text-sm text-gray-400 italic">
                                {isProcessing ? 'Extracting action items…' : 'No action items found.'}
                            </p>
                        )}
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
