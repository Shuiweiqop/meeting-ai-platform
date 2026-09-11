import { useDeferredValue, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_LABELS = {
    pending:     'Pending',
    in_progress: 'In Progress',
    completed:   'Completed',
};

const STATUS_STYLES = {
    pending:     'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-100 text-blue-700',
    completed:   'bg-green-100 text-green-700',
};

const TABS = [
    { key: 'all',         label: 'All' },
    { key: 'pending',     label: 'Pending' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'completed',   label: 'Completed' },
];

async function patchTodo(id, payload) {
    const res = await fetch(route('todo-items.update', id), {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Request failed');
    return res.json();
}

function TodoRow({ todo: initial }) {
    const [todo, setTodo] = useState(initial);
    const [editingDue, setEditingDue] = useState(false);

    const toggle = async () => {
        const next = todo.status === 'completed' ? 'pending' : 'completed';
        const prev = todo.status;

        // Optimistic update — instant, no Inertia page refresh
        setTodo(t => ({ ...t, status: next }));

        try {
            await patchTodo(todo.id, { status: next });
        } catch {
            // Rollback on network error or server error
            setTodo(t => ({ ...t, status: prev }));
        }
    };

    const changeDueDate = async (value) => {
        const next = value || null;
        const prev = todo.due_date ?? null;
        setEditingDue(false);
        setTodo(t => ({ ...t, due_date: next }));

        try {
            await patchTodo(todo.id, { due_date: next });
        } catch {
            setTodo(t => ({ ...t, due_date: prev }));
        }
    };

    const done = todo.status === 'completed';

    return (
        <li className="flex items-start gap-4 py-4">
            <button
                onClick={toggle}
                className={`mt-0.5 h-5 w-5 shrink-0 rounded border-2 transition-all duration-200 ${
                    done
                        ? 'border-green-500 bg-green-500 scale-110'
                        : 'border-gray-300 hover:border-indigo-400 hover:scale-110'
                }`}
                aria-label="Toggle"
            >
                {done && (
                    <svg viewBox="0 0 12 12" fill="none" stroke="white" strokeWidth="2"
                        strokeLinecap="round" strokeLinejoin="round" className="h-full w-full p-0.5">
                        <path d="M2 6l3 3 5-5" />
                    </svg>
                )}
            </button>

            <div className="flex-1 min-w-0">
                <p className={`text-sm font-medium transition-all duration-200 ${
                    done ? 'text-gray-400 line-through' : 'text-gray-900'
                }`}>
                    {todo.title}
                </p>
                {todo.description && (
                    <p className="mt-0.5 text-xs text-gray-500">{todo.description}</p>
                )}
                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                    {todo.meeting && (
                        <Link
                            href={route('meetings.show', todo.meeting.id)}
                            className="text-xs text-indigo-500 hover:underline"
                        >
                            {todo.meeting.title}
                        </Link>
                    )}

                    {editingDue ? (
                        <input
                            type="date"
                            autoFocus
                            defaultValue={todo.due_date ?? ''}
                            onBlur={(e) => changeDueDate(e.target.value)}
                            onChange={(e) => e.target.value && changeDueDate(e.target.value)}
                            className="rounded border-gray-200 py-0.5 text-xs focus:border-indigo-400 focus:ring-indigo-400"
                        />
                    ) : todo.due_date ? (
                        <button
                            onClick={() => setEditingDue(true)}
                            className="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600"
                        >
                            <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M6 2a1 1 0 0 0-1 1v1H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H7V3a1 1 0 0 0-1-1Zm10 6H4v8h12V8Z" clipRule="evenodd" /></svg>
                            Due {todo.due_date}
                        </button>
                    ) : (
                        <button
                            onClick={() => setEditingDue(true)}
                            className="text-xs text-gray-400 hover:text-indigo-600"
                        >
                            + Add due date
                        </button>
                    )}
                </div>
            </div>

            <span className={`shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[todo.status]}`}>
                {STATUS_LABELS[todo.status]}
            </span>
        </li>
    );
}

export default function Index({ todos, counts, activeStatus }) {
    const [search, setSearch] = useState('');

    // useDeferredValue — React schedules this at lower priority so typing stays snappy
    const deferredSearch = useDeferredValue(search);
    const isStale = search !== deferredSearch;

    // Local client-side filter — no server round-trip
    const filteredTodos = useMemo(() => {
        const q = deferredSearch.trim().toLowerCase();
        if (!q) return todos.data;
        return todos.data.filter(t =>
            t.title.toLowerCase().includes(q) ||
            t.description?.toLowerCase().includes(q) ||
            t.meeting?.title.toLowerCase().includes(q)
        );
    }, [todos.data, deferredSearch]);

    const switchTab = (key) => {
        setSearch('');
        router.get(route('todos.index'), key === 'all' ? {} : { status: key }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">My Action Items</h2>}
        >
            <Head title="My Todos" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">

                        {/* Filter tabs */}
                        <div className="flex border-b border-gray-200">
                            {TABS.map((tab) => {
                                const count = tab.key === 'all' ? counts?.total : counts?.[tab.key];
                                const isActive = activeStatus === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        onClick={() => switchTab(tab.key)}
                                        className={`flex items-center gap-1.5 px-5 py-3.5 text-sm font-medium border-b-2 transition ${
                                            isActive
                                                ? 'border-indigo-600 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}
                                    >
                                        {tab.label}
                                        {count > 0 && (
                                            <span className={`rounded-full px-1.5 py-0.5 text-xs font-semibold ${
                                                isActive ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'
                                            }`}>
                                                {count}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Local search — useDeferredValue keeps typing instant */}
                        <div className="border-b border-gray-100 px-6 py-3">
                            <div className="relative">
                                <svg className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                                </svg>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder="Filter tasks on this page…"
                                    className={`w-full rounded-md border-gray-200 pl-9 pr-4 py-1.5 text-sm transition-opacity
                                        focus:border-indigo-400 focus:ring-indigo-400
                                        ${isStale ? 'opacity-60' : 'opacity-100'}`}
                                />
                                {search && (
                                    <button
                                        onClick={() => setSearch('')}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        ×
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* List */}
                        <div className="px-6">
                            {filteredTodos.length === 0 ? (
                                <div className="py-16 text-center">
                                    <p className="text-sm text-gray-400 italic">
                                        {search
                                            ? `No tasks matching "${deferredSearch}".`
                                            : activeStatus === 'completed'
                                                ? 'No completed tasks yet.'
                                                : 'No tasks assigned to you.'}
                                    </p>
                                    {search && (
                                        <button onClick={() => setSearch('')} className="mt-2 text-xs text-indigo-500 hover:underline">
                                            Clear search
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <ul className="divide-y divide-gray-100">
                                        {filteredTodos.map((todo) => (
                                            <TodoRow key={todo.id} todo={todo} />
                                        ))}
                                    </ul>
                                    {/* Pagination — only shown when no local search active */}
                                    {!search && todos.links.length > 3 && (
                                        <div className="flex justify-center gap-1 py-4">
                                            {todos.links.map((link, i) => (
                                                <Link
                                                    key={i}
                                                    href={link.url ?? '#'}
                                                    preserveScroll
                                                    className={`px-3 py-1.5 rounded text-sm ${
                                                        link.active
                                                            ? 'bg-indigo-600 text-white font-semibold'
                                                            : link.url
                                                                ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                                : 'text-gray-300 cursor-default'
                                                    }`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </div>
                                    )}
                                    {search && (
                                        <p className="py-3 text-xs text-gray-400 text-center">
                                            {filteredTodos.length} result{filteredTodos.length !== 1 ? 's' : ''} on this page
                                        </p>
                                    )}
                                </>
                            )}
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
