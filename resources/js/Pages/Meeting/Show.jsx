import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useVirtualizer } from '@tanstack/react-virtual';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAudioStore } from '@/stores/audioStore';

// ─── Status badge ──────────────────────────────────────────────────────────

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

// ─── Multi-stage status machine ────────────────────────────────────────────

const STAGES = [
    { key: 'extracting_audio', label: 'Extracting audio' },
    { key: 'transcribing',     label: 'Transcribing audio' },
    { key: 'mapping_speakers', label: 'Identifying speakers' },
    { key: 'summarizing',      label: 'Generating AI insights' },
];

function StageTracker({ status, processingStage, extractionProgress }) {
    const currentIdx = STAGES.findIndex(s => s.key === processingStage);
    const isPending  = status === 'pending';

    return (
        <div className="rounded-lg bg-yellow-50 p-5 ring-1 ring-yellow-200">
            <div className="flex items-center gap-2 mb-4">
                <svg className="h-4 w-4 animate-spin text-yellow-500" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
                </svg>
                <p className="text-sm font-semibold text-yellow-800">
                    {isPending ? 'Waiting in queue…' : 'Processing your meeting…'}
                </p>
            </div>

            <div className="space-y-2.5 pl-1">
                {STAGES.map((stage, i) => {
                    const isDone   = !isPending && currentIdx > i;
                    const isActive = !isPending && currentIdx === i;
                    const showBar  = isActive && stage.key === 'extracting_audio' && extractionProgress != null;
                    return (
                        <div
                            key={stage.key}
                            className={`flex items-start gap-3 text-sm transition-all duration-500 ${
                                isDone   ? 'text-green-700'  :
                                isActive ? 'text-yellow-900 font-medium' :
                                           'text-gray-400'
                            }`}
                        >
                            {isDone ? (
                                <svg className="mt-0.5 h-4 w-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            ) : isActive ? (
                                <svg className="mt-0.5 h-4 w-4 shrink-0 animate-spin text-yellow-500" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
                                </svg>
                            ) : (
                                <svg className="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            )}
                            <div className="flex-1 min-w-0">
                                <span>{stage.label}</span>
                                {showBar && (
                                    <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-yellow-200">
                                        <div
                                            className="h-1.5 rounded-full bg-yellow-500 transition-all duration-300"
                                            style={{ width: `${extractionProgress}%` }}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Audio ↔ Transcript sync ───────────────────────────────────────────────

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${String(s).padStart(2, '0')}`;
}

function escapeRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightText(text, query) {
    if (!query.trim()) return text;
    const re = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.split(re).map((part, i) =>
        i % 2 === 1
            ? <mark key={i} className="rounded-sm bg-yellow-200 px-0.5 text-gray-900">{part}</mark>
            : part
    );
}

/**
 * Synced transcript — powered by the global Zustand audio store.
 * The <audio> element lives in GlobalAudioPlayer (AuthenticatedLayout).
 * Virtual list renders only ~10 segments at a time regardless of transcript length.
 */
function AudioTranscriptSync({ meeting, segments }) {
    const scrollRef = useRef(null);
    const [query,    setQuery]    = useState('');
    const [matchIdx, setMatchIdx] = useState(0);

    const { currentTime, isPlaying, seekTo, setIsPlaying, load } = useAudioStore();

    // Register this meeting in the global player on mount
    useEffect(() => {
        load({
            meetingId:    meeting.id,
            meetingTitle: meeting.title,
            meetingHref:  route('meetings.show', meeting.id),
            audioUrl:     route('meetings.audio', meeting.id),
        });
    }, [meeting.id]);

    // Active segment: last segment whose start ≤ currentTime
    const activeIdx = useMemo(() => {
        for (let i = segments.length - 1; i >= 0; i--) {
            if (currentTime >= segments[i].start) return i;
        }
        return 0;
    }, [currentTime, segments]);

    // Segment indices whose text or speaker contains the query
    const matches = useMemo(() => {
        const q = query.trim();
        if (!q) return [];
        const re = new RegExp(escapeRegex(q), 'i');
        return segments.reduce((acc, seg, i) => {
            if (re.test(seg.text) || re.test(seg.speaker)) acc.push(i);
            return acc;
        }, []);
    }, [query, segments]);

    // Reset to first result whenever the query changes
    useEffect(() => { setMatchIdx(0); }, [query]);

    // Virtual list — only renders visible rows
    const virtualizer = useVirtualizer({
        count:            segments.length,
        getScrollElement: () => scrollRef.current,
        estimateSize:     () => 64,
        overscan:         5,
    });

    // Scroll to the current search result
    useEffect(() => {
        if (matches.length > 0) {
            virtualizer.scrollToIndex(matches[matchIdx], { behavior: 'smooth', align: 'center' });
        }
    }, [matchIdx, matches]);

    // Auto-scroll to active segment while playing
    useEffect(() => {
        if (isPlaying && activeIdx >= 0) {
            virtualizer.scrollToIndex(activeIdx, { behavior: 'smooth', align: 'nearest' });
        }
    }, [activeIdx, isPlaying]);

    const seek       = (t) => { seekTo(t); setIsPlaying(true); };
    const prevMatch  = () => setMatchIdx(i => (i - 1 + matches.length) % matches.length);
    const nextMatch  = () => setMatchIdx(i => (i + 1) % matches.length);

    return (
        <div className="space-y-3">
            <p className="text-xs text-gray-400">
                Click any segment to jump to that point · Audio plays in the bottom bar
            </p>

            {/* Search bar */}
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <svg className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') e.shiftKey ? prevMatch() : nextMatch();
                        }}
                        placeholder="Search transcript…"
                        className="w-full rounded-md border border-gray-200 py-1.5 pl-8 pr-3 text-sm placeholder-gray-400 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    />
                </div>

                {query.trim() && (
                    <span className="shrink-0 text-xs tabular-nums text-gray-500">
                        {matches.length > 0 ? `${matchIdx + 1} / ${matches.length}` : 'No results'}
                    </span>
                )}

                {matches.length > 1 && (
                    <>
                        <button onClick={prevMatch} title="Previous (Shift+Enter)"
                            className="rounded p-1.5 text-gray-500 hover:bg-gray-100 active:bg-gray-200">
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button onClick={nextMatch} title="Next (Enter)"
                            className="rounded p-1.5 text-gray-500 hover:bg-gray-100 active:bg-gray-200">
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </>
                )}
            </div>

            {/* Virtual scroll container */}
            <div
                ref={scrollRef}
                className="h-96 overflow-y-auto rounded-md border border-gray-100 bg-gray-50"
            >
                <div style={{ height: `${virtualizer.getTotalSize()}px`, position: 'relative' }}>
                    {virtualizer.getVirtualItems().map((virtualItem) => {
                        const seg            = segments[virtualItem.index];
                        const isActive       = virtualItem.index === activeIdx && isPlaying;
                        const isCurrentMatch = !!query.trim() && virtualItem.index === matches[matchIdx];

                        return (
                            <button
                                key={virtualItem.key}
                                data-index={virtualItem.index}
                                ref={virtualizer.measureElement}
                                onClick={() => seek(seg.start)}
                                style={{
                                    position:  'absolute',
                                    top:       0,
                                    left:      0,
                                    width:     '100%',
                                    transform: `translateY(${virtualItem.start}px)`,
                                }}
                                className={`w-full text-left px-4 py-2.5 border-b border-gray-100 transition-colors duration-150 group ${
                                    isCurrentMatch ? 'bg-yellow-50 ring-1 ring-inset ring-yellow-300' :
                                    isActive       ? 'bg-yellow-50' :
                                                     'hover:bg-white'
                                }`}
                            >
                                <div className="flex items-baseline gap-2">
                                    {seg.speaker && (
                                        <span className={`shrink-0 text-xs font-semibold ${
                                            isActive ? 'text-indigo-600' : 'text-indigo-400 group-hover:text-indigo-500'
                                        }`}>
                                            {highlightText(seg.speaker, query)}
                                        </span>
                                    )}
                                    <span className={`text-sm leading-relaxed ${
                                        isActive ? 'text-gray-900 font-medium' : 'text-gray-700'
                                    }`}>
                                        {highlightText(seg.text, query)}
                                    </span>
                                    <span className="ml-auto shrink-0 text-xs text-gray-300 group-hover:text-gray-400">
                                        {formatTime(seg.start)}
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

// ─── Transcript editor ─────────────────────────────────────────────────────

/**
 * Edit mode for the transcript. Renders a plain (non-virtualized) editable list
 * so every speaker/text field is a real input; virtualization is only needed
 * for the read-only synced view. Saves the whole segments array at once.
 */
function TranscriptEditor({ transcriptId, segments, onSaved, onCancel }) {
    const [draft, setDraft] = useState(() => segments.map(s => ({ ...s })));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const setField = (i, field, value) =>
        setDraft(d => d.map((s, idx) => (idx === i ? { ...s, [field]: value } : s)));

    const save = async () => {
        setSaving(true);
        setError(null);
        try {
            const res = await fetch(route('transcripts.update', transcriptId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ segments: draft }),
            });
            if (!res.ok) throw new Error('Save failed');
            const data = await res.json();
            onSaved(data.segments);
        } catch {
            setError('Could not save. Please try again.');
            setSaving(false);
        }
    };

    return (
        <div>
            <div className="max-h-96 space-y-2 overflow-y-auto rounded-md border border-gray-100 bg-gray-50 p-3">
                {draft.map((seg, i) => (
                    <div key={i} className="flex items-start gap-2">
                        <span className="mt-2 w-12 shrink-0 text-right text-xs tabular-nums text-gray-400">
                            {formatTime(seg.start)}
                        </span>
                        <input
                            type="text"
                            value={seg.speaker ?? ''}
                            onChange={(e) => setField(i, 'speaker', e.target.value)}
                            placeholder="Speaker"
                            className="w-28 shrink-0 rounded border-gray-200 py-1 text-xs font-semibold text-indigo-600 focus:border-indigo-400 focus:ring-indigo-400"
                        />
                        <textarea
                            value={seg.text ?? ''}
                            onChange={(e) => setField(i, 'text', e.target.value)}
                            rows={1}
                            className="flex-1 rounded border-gray-200 py-1 text-sm text-gray-700 focus:border-indigo-400 focus:ring-indigo-400"
                        />
                    </div>
                ))}
            </div>

            {error && <p className="mt-2 text-xs text-red-600">{error}</p>}

            <div className="mt-3 flex items-center justify-end gap-2">
                <button
                    onClick={onCancel}
                    disabled={saving}
                    className="rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-50"
                >
                    Cancel
                </button>
                <button
                    onClick={save}
                    disabled={saving}
                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    {saving ? 'Saving…' : 'Save changes'}
                </button>
            </div>
        </div>
    );
}

// ─── Optimistic todo row ───────────────────────────────────────────────────

function TodoRow({ todo: initial, canManage = false, assignableUsers = [] }) {
    const [todo, setTodo] = useState(initial);

    const toggle = () => {
        const next = todo.status === 'completed' ? 'pending' : 'completed';
        const prev = todo.status;
        setTodo(t => ({ ...t, status: next }));
        router.patch(
            route('todo-items.update', todo.id),
            { status: next },
            { preserveScroll: true, onError: () => setTodo(t => ({ ...t, status: prev })) },
        );
    };

    const reassign = (value) => {
        const nextId = value ? Number(value) : null;
        const prev = todo.assignee;
        const nextAssignee = assignableUsers.find(u => u.id === nextId) ?? null;
        setTodo(t => ({ ...t, assignee: nextAssignee }));
        router.patch(
            route('todo-items.update', todo.id),
            { assigned_to: nextId },
            { preserveScroll: true, onError: () => setTodo(t => ({ ...t, assignee: prev })) },
        );
    };

    const done = todo.status === 'completed';

    return (
        <li className="flex items-start gap-3 py-3">
            <button
                onClick={toggle}
                className={`mt-0.5 h-5 w-5 shrink-0 rounded border-2 transition-all duration-200 ${
                    done ? 'border-green-500 bg-green-500 text-white scale-110'
                         : 'border-gray-300 bg-white hover:border-indigo-400 hover:scale-110'
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
                <p className={`text-sm font-medium transition-all duration-200 ${done ? 'text-gray-400 line-through' : 'text-gray-900'}`}>
                    {todo.title}
                </p>
                {todo.description && <p className="mt-0.5 text-xs text-gray-500">{todo.description}</p>}
                {canManage && assignableUsers.length > 0 ? (
                    <select
                        value={todo.assignee?.id ?? ''}
                        onChange={(e) => reassign(e.target.value)}
                        className="mt-1 rounded border-gray-200 py-0.5 text-xs text-indigo-600 focus:border-indigo-400 focus:ring-indigo-400"
                    >
                        <option value="">— Unassigned —</option>
                        {assignableUsers.map(u => (
                            <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                    </select>
                ) : (
                    todo.assignee && <p className="mt-1 text-xs text-indigo-600">→ {todo.assignee.name}</p>
                )}
            </div>
        </li>
    );
}

// ─── Page ──────────────────────────────────────────────────────────────────

export default function Show({ meeting, canManage = false, assignableUsers = [] }) {
    const audioUrl       = meeting.audio_path ? route('meetings.audio', meeting.id) : null;
    const isProcessing   = meeting.status === 'pending' || meeting.status === 'processing';
    const occurredAt     = meeting.meeting_date ?? meeting.created_at;
    const occurredMonth  = occurredAt ? occurredAt.slice(0, 7) : null;
    const occurredLabel  = occurredAt ? new Date(occurredAt).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : null;
    // Segments are state so a saved transcript edit updates the view in place.
    const [segments, setSegments] = useState(meeting.transcript?.segments ?? null);
    const shareActive    = meeting.share_token && (!meeting.share_expires_at || new Date(meeting.share_expires_at) > new Date());
    const shareUrl       = shareActive ? `${window.location.origin}/share/${meeting.share_token}` : null;
    const shareExpiry    = shareActive && meeting.share_expires_at
        ? new Date(meeting.share_expires_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
        : null;
    const [copied, setCopied]                   = useState(false);
    const [extractionProgress, setExtractionProgress] = useState(null);
    const [editingTranscript, setEditingTranscript]   = useState(false);

    const copyShareUrl = (url) => {
        navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    // Reverb WebSocket — update extraction progress bar in-place;
    // only trigger a full reload when the stage/status actually changes.
    useEffect(() => {
        if (!isProcessing) return;

        const channel = window.Echo
            .private(`meetings.${meeting.id}`)
            .listen('.MeetingStatusUpdated', (e) => {
                if (e.extraction_progress != null) {
                    setExtractionProgress(e.extraction_progress);
                } else {
                    setExtractionProgress(null);
                    router.reload({ only: ['meeting'] });
                }
            });

        // Fallback: also poll every 15s in case WebSocket drops
        const fallback = setInterval(() => router.reload({ only: ['meeting'] }), 15000);

        return () => {
            channel.stopListening('.MeetingStatusUpdated');
            window.Echo.leave(`meetings.${meeting.id}`);
            clearInterval(fallback);
        };
    }, [isProcessing, meeting.id]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-xl font-semibold leading-tight text-gray-800">{meeting.title}</h2>
                            <StatusBadge status={meeting.status} />
                        </div>
                        {occurredLabel && (
                            <Link
                                href={route('calendar', { month: occurredMonth })}
                                className="mt-1 inline-flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600"
                            >
                                <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M6 2a1 1 0 0 0-1 1v1H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H7V3a1 1 0 0 0-1-1Zm10 6H4v8h12V8Z" clipRule="evenodd" /></svg>
                                {occurredLabel} · View in calendar
                            </Link>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {meeting.status === 'completed' && (
                            <>
                                {shareUrl ? (
                                    <button onClick={() => copyShareUrl(shareUrl)}
                                        title={shareExpiry ? `Link active until ${shareExpiry}` : undefined}
                                        className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">
                                        {copied ? '✓ Copied!' : '🔗 Copy Link'}
                                    </button>
                                ) : (
                                    <button onClick={() => router.post(route('meetings.share.generate', meeting.id))}
                                        className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">
                                        {meeting.share_token ? 'Renew share link' : 'Share'}
                                    </button>
                                )}
                                <a href={route('meetings.export', meeting.id)} target="_blank"
                                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                                    Export PDF
                                </a>
                            </>
                        )}
                        <Link href={route('meetings.edit', meeting.id)} className="text-sm font-medium text-gray-600 hover:text-gray-900">Edit</Link>
                        <Link href={route('meetings.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-500">← My Meetings</Link>
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
                        {/* Audio player shown here only when there are no synced segments */}
                        {audioUrl && !segments && (
                            <div className="mt-4">
                                <p className="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Recording</p>
                                <audio controls className="w-full rounded"><source src={audioUrl} /></audio>
                            </div>
                        )}
                    </div>

                    {/* Multi-stage status machine */}
                    {isProcessing && (
                        <StageTracker
                            status={meeting.status}
                            processingStage={meeting.processing_stage}
                            extractionProgress={extractionProgress}
                        />
                    )}

                    {meeting.status === 'failed' && (
                        <div className="flex items-center justify-between rounded-lg bg-red-50 p-4 ring-1 ring-red-200">
                            <p className="text-sm text-red-700">Processing failed. You can retry or re-upload the recording.</p>
                            <button onClick={() => router.post(route('meetings.retry', meeting.id))}
                                className="ml-4 shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">
                                Retry
                            </button>
                        </div>
                    )}

                    {/* Transcript — synced if segments available, plain text otherwise */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">Transcript</h3>
                            {canManage && segments?.length > 0 && !editingTranscript && (
                                <button
                                    onClick={() => setEditingTranscript(true)}
                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    Edit transcript
                                </button>
                            )}
                        </div>
                        {meeting.transcript ? (
                            segments?.length > 0 ? (
                                editingTranscript ? (
                                    <TranscriptEditor
                                        transcriptId={meeting.transcript.id}
                                        segments={segments}
                                        onSaved={(next) => { setSegments(next); setEditingTranscript(false); }}
                                        onCancel={() => setEditingTranscript(false)}
                                    />
                                ) : (
                                    <AudioTranscriptSync meeting={meeting} segments={segments} />
                                )
                            ) : (
                                <pre className="max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-gray-700 leading-relaxed font-sans">
                                    {meeting.transcript.content}
                                </pre>
                            )
                        ) : (
                            <p className="text-sm text-gray-400 italic">
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
                                {meeting.todo_items.map(todo => <TodoRow key={todo.id} todo={todo} canManage={canManage} assignableUsers={assignableUsers} />)}
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
