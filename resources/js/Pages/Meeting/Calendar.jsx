import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

// Same status palette as the dashboard, kept consistent across the app.
const STATUS_DOT = {
    pending: 'bg-gray-400',
    processing: 'bg-yellow-400',
    completed: 'bg-green-500',
    failed: 'bg-red-500',
};

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Build the grid of days for the month, padded so the 1st lands on the right
// weekday (Monday-first). Leading/trailing nulls render as empty cells.
function buildCells(month) {
    const [year, mon] = month.split('-').map(Number);
    const first = new Date(year, mon - 1, 1);
    const daysInMonth = new Date(year, mon, 0).getDate();

    // JS getDay(): 0=Sun..6=Sat → convert to Monday-first 0=Mon..6=Sun.
    const leadingBlanks = (first.getDay() + 6) % 7;

    const cells = Array(leadingBlanks).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${year}-${String(mon).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ day: d, iso });
    }
    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
}

function MeetingChip({ entry }) {
    return (
        <Link
            href={route('meetings.show', entry.id)}
            className="flex items-center gap-1.5 rounded bg-gray-50 px-1.5 py-1 text-xs text-gray-700 ring-1 ring-gray-100 hover:bg-indigo-50 hover:ring-indigo-200"
            title={`${entry.time} · ${entry.title}`}
        >
            <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${STATUS_DOT[entry.status] ?? STATUS_DOT.pending}`} />
            <span className="shrink-0 tabular-nums text-gray-400">{entry.time}</span>
            <span className="truncate">{entry.title}</span>
        </Link>
    );
}

function TodoChip({ entry }) {
    const done = entry.status === 'completed';
    const inner = (
        <>
            <svg className="h-3 w-3 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M2.5 3A1.5 1.5 0 0 0 1 4.5v11A1.5 1.5 0 0 0 2.5 17h15a1.5 1.5 0 0 0 1.5-1.5v-11A1.5 1.5 0 0 0 17.5 3h-15Zm10.28 4.53a.75.75 0 0 0-1.06-1.06L8.75 9.19 7.28 7.72a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l3.5-3.25Z" clipRule="evenodd" />
            </svg>
            <span className={`truncate ${done ? 'text-gray-400 line-through' : ''}`}>{entry.title}</span>
        </>
    );
    const cls = 'flex items-center gap-1.5 rounded border border-dashed border-amber-200 bg-amber-50 px-1.5 py-1 text-xs text-amber-800';

    return entry.meeting_id ? (
        <Link href={route('meetings.show', entry.meeting_id)} className={`${cls} hover:bg-amber-100`} title={`Due · ${entry.title}`}>
            {inner}
        </Link>
    ) : (
        <div className={cls} title={`Due · ${entry.title}`}>{inner}</div>
    );
}

export default function Calendar({ month, monthLabel, prevMonth, nextMonth, today, byDay, teams = [], activeTeam }) {
    const cells = buildCells(month);

    const navMonth = (m) => ({ month: m, ...(activeTeam ? { team: activeTeam } : {}) });

    const changeTeam = (value) => {
        router.get(route('calendar'), {
            month,
            ...(value ? { team: value } : {}),
        });
    };

    const icsHref = route('calendar.export', activeTeam ? { team: activeTeam } : {});

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Calendar</h2>}
        >
            <Head title="Calendar" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        {/* Toolbar */}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                            <div className="flex items-center gap-3">
                                <h3 className="text-lg font-semibold text-gray-800">{monthLabel}</h3>
                                {teams.length > 0 && (
                                    <select
                                        value={activeTeam ?? ''}
                                        onChange={(e) => changeTeam(e.target.value)}
                                        className="rounded-md border-gray-300 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">My calendar</option>
                                        {teams.map((t) => (
                                            <option key={t.id} value={t.id}>{t.name}</option>
                                        ))}
                                    </select>
                                )}
                            </div>

                            <div className="flex items-center gap-1">
                                <a
                                    href={icsHref}
                                    className="mr-1 rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50"
                                >
                                    Export .ics
                                </a>
                                <Link
                                    href={route('calendar', navMonth(prevMonth))}
                                    className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    aria-label="Previous month"
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.24a.75.75 0 0 1 0-1.06l4.25-4.24a.75.75 0 0 1 1.06 0Z" clipRule="evenodd" /></svg>
                                </Link>
                                <button
                                    onClick={() => router.get(route('calendar'), activeTeam ? { team: activeTeam } : {})}
                                    className="rounded-md px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                                >
                                    Today
                                </button>
                                <Link
                                    href={route('calendar', navMonth(nextMonth))}
                                    className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    aria-label="Next month"
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.24a.75.75 0 0 1 0 1.06l-4.25 4.24a.75.75 0 0 1-1.06 0Z" clipRule="evenodd" /></svg>
                                </Link>
                            </div>
                        </div>

                        {/* Weekday header */}
                        <div className="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {WEEKDAYS.map((d) => (
                                <div key={d} className="py-2">{d}</div>
                            ))}
                        </div>

                        {/* Day grid */}
                        <div className="grid grid-cols-7">
                            {cells.map((cell, i) => {
                                if (!cell) {
                                    return <div key={`blank-${i}`} className="min-h-28 border-b border-r border-gray-100 bg-gray-50/50" />;
                                }
                                const entries = byDay[cell.iso] ?? [];
                                const isToday = cell.iso === today;

                                return (
                                    <div key={cell.iso} className="min-h-28 border-b border-r border-gray-100 p-1.5">
                                        <div className="flex justify-end">
                                            <span className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium ${
                                                isToday ? 'bg-indigo-600 text-white' : 'text-gray-500'
                                            }`}>
                                                {cell.day}
                                            </span>
                                        </div>

                                        <div className="mt-1 space-y-1">
                                            {entries.map((entry) =>
                                                entry.type === 'todo'
                                                    ? <TodoChip key={`t${entry.id}`} entry={entry} />
                                                    : <MeetingChip key={`m${entry.id}`} entry={entry} />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Legend */}
                    <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                        {Object.entries(STATUS_DOT).map(([status, dot]) => (
                            <span key={status} className="flex items-center gap-1.5 capitalize">
                                <span className={`h-2 w-2 rounded-full ${dot}`} />
                                {status}
                            </span>
                        ))}
                        <span className="flex items-center gap-1.5">
                            <span className="h-2 w-3 rounded-sm border border-dashed border-amber-300 bg-amber-50" />
                            Todo due
                        </span>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
