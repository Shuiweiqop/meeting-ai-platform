import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';

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

export default function Show({ team, auth }) {
    const isOwner = team.owner_id === auth?.user?.id;

    const { data, setData, post, processing, errors, reset } = useForm({ email: '' });

    const addMember = (e) => {
        e.preventDefault();
        post(route('teams.members.add', team.id), {
            onSuccess: () => reset('email'),
        });
    };

    const removeMember = (userId) => {
        if (!window.confirm('Remove this member from the team?')) return;
        router.delete(route('teams.members.remove', [team.id, userId]));
    };

    const deleteTeam = () => {
        if (!window.confirm(`Delete team "${team.name}"? This cannot be undone.`)) return;
        router.delete(route('teams.destroy', team.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{team.name}</h2>
                    <Link href={route('teams.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        ← My Teams
                    </Link>
                </div>
            }
        >
            <Head title={team.name} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">

                    {/* Members */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Members <span className="ml-1 text-gray-400 normal-case font-normal">({team.members?.length ?? 0})</span>
                        </h3>

                        <ul className="mt-4 divide-y divide-gray-100">
                            {team.members?.map((member) => (
                                <li key={member.id} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{member.name}</p>
                                        <p className="text-xs text-gray-400">{member.email}</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs capitalize text-gray-400">{member.pivot?.role}</span>
                                        {isOwner && member.id !== team.owner_id && (
                                            <button
                                                onClick={() => removeMember(member.id)}
                                                className="text-xs text-red-500 hover:text-red-700"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {/* Add member form — owner only */}
                        {isOwner && (
                            <form onSubmit={addMember} className="mt-5 flex gap-2">
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="Invite by email address"
                                    className="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                />
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    {processing ? 'Adding…' : 'Add'}
                                </button>
                            </form>
                        )}
                        {errors.email && <InputError className="mt-2" message={errors.email} />}
                    </div>

                    {/* Meetings */}
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Team Meetings
                            </h3>
                            <Link
                                href={route('meetings.create')}
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                + Upload
                            </Link>
                        </div>

                        {team.meetings?.length > 0 ? (
                            <ul className="mt-4 divide-y divide-gray-100">
                                {team.meetings.map((meeting) => (
                                    <li key={meeting.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <Link
                                                href={route('meetings.show', meeting.id)}
                                                className="text-sm font-medium text-gray-900 hover:text-indigo-600"
                                            >
                                                {meeting.title}
                                            </Link>
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {new Date(meeting.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            </p>
                                        </div>
                                        <StatusBadge status={meeting.status} />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-4 text-sm text-gray-400 italic">No meetings linked to this team yet.</p>
                        )}
                    </div>

                    {/* Danger zone — owner only */}
                    {isOwner && (
                        <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-red-200">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-red-500">Danger Zone</h3>
                            <p className="mt-2 text-sm text-gray-500">Deleting this team is permanent and cannot be undone.</p>
                            <button
                                onClick={deleteTeam}
                                className="mt-4 rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                Delete Team
                            </button>
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
