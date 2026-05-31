import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_STYLES = {
    pending:    'bg-gray-100 text-gray-600',
    processing: 'bg-yellow-100 text-yellow-700',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
};

const STATUS_OPTIONS = [
    { value: '',           label: 'All statuses' },
    { value: 'pending',    label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed',  label: 'Completed' },
    { value: 'failed',     label: 'Failed' },
];

function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[status] ?? STATUS_STYLES.pending}`}>
            {status}
        </span>
    );
}

export default function Index({ meetings, filters = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const deleteMeeting = (meeting) => {
        if (!window.confirm(`Delete "${meeting.title}"? This cannot be undone.`)) return;
        router.delete(route('meetings.destroy', meeting.id));
    };

    const applyFilters = (newSearch, newStatus) => {
        const params = {};
        if (newSearch) params.search = newSearch;
        if (newStatus) params.status = newStatus;
        router.get(route('meetings.index'), params, { preserveState: true, replace: true });
    };

    // Debounce search — wait 350ms after user stops typing
    useEffect(() => {
        const timer = setTimeout(() => applyFilters(search, status), 350);
        return () => clearTimeout(timer);
    }, [search]);

    // Status fires immediately
    const handleStatus = (val) => {
        setStatus(val);
        applyFilters(search, val);
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        router.get(route('meetings.index'), {}, { preserveState: true, replace: true });
    };

    const hasFilters = search || status;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">My Meetings</h2>
                    <Link
                        href={route('meetings.create')}
                        className="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                    >
                        + Upload Meeting
                    </Link>
                </div>
            }
        >
            <Head title="My Meetings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">

                    {/* Search + Filter bar */}
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <svg className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search meetings…"
                                className="w-full rounded-md border-gray-300 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <select
                            value={status}
                            onChange={(e) => handleStatus(e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-44"
                        >
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>

                        {hasFilters && (
                            <button
                                onClick={clearFilters}
                                className="text-sm text-gray-400 hover:text-gray-600 whitespace-nowrap"
                            >
                                Clear ×
                            </button>
                        )}
                    </div>

                    {/* Results */}
                    {meetings.length === 0 ? (
                        <div className="rounded-lg border-2 border-dashed border-gray-300 bg-white p-16 text-center">
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            {hasFilters ? (
                                <>
                                    <p className="mt-4 text-sm text-gray-500">No meetings match your search.</p>
                                    <button onClick={clearFilters} className="mt-2 text-sm font-medium text-indigo-600 hover:underline">
                                        Clear filters
                                    </button>
                                </>
                            ) : (
                                <>
                                    <p className="mt-4 text-sm text-gray-500">No meetings yet.</p>
                                    <Link href={route('meetings.create')} className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:underline">
                                        Upload your first meeting →
                                    </Link>
                                </>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {meetings.map((meeting) => (
                                <div key={meeting.id} className="flex flex-col rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                                    <div className="flex-1 p-5">
                                        <div className="flex items-start justify-between gap-2">
                                            <h3 className="font-semibold text-gray-900 leading-snug">{meeting.title}</h3>
                                            <StatusBadge status={meeting.status} />
                                        </div>
                                        {meeting.description && (
                                            <p className="mt-2 text-sm text-gray-500 line-clamp-2">{meeting.description}</p>
                                        )}
                                        <p className="mt-3 text-xs text-gray-400">
                                            {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 border-t border-gray-100 px-5 py-3">
                                        <Link href={route('meetings.show', meeting.id)} className="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                            View
                                        </Link>
                                        <span className="text-gray-300">·</span>
                                        <Link href={route('meetings.edit', meeting.id)} className="text-sm font-medium text-gray-600 hover:text-gray-900">
                                            Edit
                                        </Link>
                                        <span className="text-gray-300">·</span>
                                        <button
                                            onClick={() => deleteMeeting(meeting)}
                                            className="text-sm font-medium text-red-500 hover:text-red-700"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
