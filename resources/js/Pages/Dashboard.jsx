import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_STYLES = {
    pending:    'bg-gray-100 text-gray-600',
    processing: 'bg-yellow-100 text-yellow-700',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
};

function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[status] ?? STATUS_STYLES.pending}`}>
            {status}
        </span>
    );
}

function SectionCard({ title, action, children }) {
    return (
        <div className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">{title}</h3>
                {action}
            </div>
            <div className="px-6 py-4">{children}</div>
        </div>
    );
}

function EmptyState({ message }) {
    return <p className="text-sm text-gray-400 italic py-2">{message}</p>;
}

function TodoToggle({ todo }) {
    const toggle = () => {
        const next = todo.status === 'completed' ? 'pending' : 'completed';
        router.patch(route('todo-items.update', todo.id), { status: next }, { preserveScroll: true });
    };

    const done = todo.status === 'completed';
    return (
        <li className="flex items-start gap-3 py-2.5">
            <button
                onClick={toggle}
                className={`mt-0.5 h-4 w-4 shrink-0 rounded border-2 transition ${done ? 'border-green-500 bg-green-500' : 'border-gray-300 hover:border-indigo-400'}`}
                aria-label="Toggle"
            >
                {done && (
                    <svg viewBox="0 0 12 12" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-full w-full p-0.5">
                        <path d="M2 6l3 3 5-5" />
                    </svg>
                )}
            </button>
            <div className="min-w-0 flex-1">
                <p className={`text-sm ${done ? 'text-gray-400 line-through' : 'text-gray-800'}`}>{todo.title}</p>
                {todo.meeting && (
                    <Link href={route('meetings.show', todo.meeting.id)} className="text-xs text-indigo-500 hover:underline">
                        {todo.meeting.title}
                    </Link>
                )}
            </div>
        </li>
    );
}

export default function Dashboard({ recentMeetings, myTodos, teamActivity }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                    {/* Top row: Recent Meetings + My Todos */}
                    <div className="grid gap-6 lg:grid-cols-2">

                        {/* Recent Meetings */}
                        <SectionCard
                            title="Recent Meetings"
                            action={
                                <Link href={route('meetings.index')} className="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                    View all →
                                </Link>
                            }
                        >
                            {recentMeetings.length === 0 ? (
                                <EmptyState message="No meetings yet." />
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {recentMeetings.map((meeting) => (
                                        <li key={meeting.id} className="flex items-center justify-between py-2.5">
                                            <Link
                                                href={route('meetings.show', meeting.id)}
                                                className="truncate text-sm font-medium text-gray-800 hover:text-indigo-600 max-w-[200px]"
                                            >
                                                {meeting.title}
                                            </Link>
                                            <div className="flex items-center gap-3 ml-3 shrink-0">
                                                <span className="text-xs text-gray-400">
                                                    {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                                </span>
                                                <StatusBadge status={meeting.status} />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Link
                                href={route('meetings.create')}
                                className="mt-4 inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-indigo-600"
                            >
                                + Upload new meeting
                            </Link>
                        </SectionCard>

                        {/* My Todos */}
                        <SectionCard
                            title="My Action Items"
                            action={
                                <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                    {myTodos.length} pending
                                </span>
                            }
                        >
                            {myTodos.length === 0 ? (
                                <EmptyState message="No pending tasks assigned to you." />
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {myTodos.map((todo) => (
                                        <TodoToggle key={todo.id} todo={todo} />
                                    ))}
                                </ul>
                            )}
                        </SectionCard>
                    </div>

                    {/* Team Activity */}
                    <SectionCard
                        title="Team Activity"
                        action={
                            <Link href={route('teams.index')} className="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                Manage teams →
                            </Link>
                        }
                    >
                        {teamActivity.length === 0 ? (
                            <div className="py-2">
                                <EmptyState message="No team meetings yet." />
                                <Link href={route('teams.create')} className="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">
                                    Create a team →
                                </Link>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {teamActivity.map((meeting) => (
                                    <li key={meeting.id} className="flex items-center justify-between py-2.5">
                                        <div className="min-w-0">
                                            <Link
                                                href={route('meetings.show', meeting.id)}
                                                className="text-sm font-medium text-gray-800 hover:text-indigo-600 truncate block max-w-xs"
                                            >
                                                {meeting.title}
                                            </Link>
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {meeting.team?.name} · uploaded by {meeting.user?.name} · {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                            </p>
                                        </div>
                                        <StatusBadge status={meeting.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
