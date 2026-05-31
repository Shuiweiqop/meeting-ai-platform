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

function TodoRow({ todo }) {
    const done = todo.status === 'completed';

    const toggle = () => {
        const next = done ? 'pending' : 'completed';
        router.patch(route('todo-items.update', todo.id), { status: next }, { preserveScroll: true });
    };

    return (
        <li className="flex items-start gap-4 py-4">
            <button
                onClick={toggle}
                className={`mt-0.5 h-5 w-5 shrink-0 rounded border-2 transition ${
                    done ? 'border-green-500 bg-green-500' : 'border-gray-300 hover:border-indigo-400'
                }`}
                aria-label="Toggle"
            >
                {done && (
                    <svg viewBox="0 0 12 12" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-full w-full p-0.5">
                        <path d="M2 6l3 3 5-5" />
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
                {todo.meeting && (
                    <Link
                        href={route('meetings.show', todo.meeting.id)}
                        className="mt-1 inline-block text-xs text-indigo-500 hover:underline"
                    >
                        {todo.meeting.title}
                    </Link>
                )}
            </div>

            <span className={`shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[todo.status]}`}>
                {STATUS_LABELS[todo.status]}
            </span>
        </li>
    );
}

export default function Index({ todos, counts, activeStatus }) {
    const switchTab = (key) => {
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
                                const count = tab.key === 'all' ? counts?.all : counts?.[tab.key];
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

                        {/* List */}
                        <div className="px-6">
                            {todos.length === 0 ? (
                                <div className="py-16 text-center">
                                    <p className="text-sm text-gray-400 italic">
                                        {activeStatus === 'completed'
                                            ? 'No completed tasks yet.'
                                            : 'No tasks assigned to you.'}
                                    </p>
                                </div>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {todos.map((todo) => (
                                        <TodoRow key={todo.id} todo={todo} />
                                    ))}
                                </ul>
                            )}
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
