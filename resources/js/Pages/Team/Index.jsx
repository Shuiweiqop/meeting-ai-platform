import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ teams }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">My Teams</h2>
                    <Link
                        href={route('teams.create')}
                        className="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                    >
                        + New Team
                    </Link>
                </div>
            }
        >
            <Head title="My Teams" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    {teams.length === 0 ? (
                        <div className="rounded-lg border-2 border-dashed border-gray-300 bg-white p-16 text-center">
                            <p className="text-sm text-gray-500">No teams yet.</p>
                            <Link href={route('teams.create')} className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:underline">
                                Create your first team →
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {teams.map((team) => (
                                <Link
                                    key={team.id}
                                    href={route('teams.show', team.id)}
                                    className="flex items-center justify-between rounded-lg bg-white px-6 py-4 shadow-sm ring-1 ring-gray-200 hover:ring-indigo-300 transition"
                                >
                                    <div>
                                        <p className="font-semibold text-gray-900">{team.name}</p>
                                        <p className="text-xs text-gray-400 mt-0.5">
                                            {team.members_count} member{team.members_count !== 1 ? 's' : ''} · {team.meetings_count} meeting{team.meetings_count !== 1 ? 's' : ''}
                                        </p>
                                    </div>
                                    <span className="text-xs text-gray-400">
                                        {team.owner_id === team.owner?.id ? 'Owner' : 'Member'}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
