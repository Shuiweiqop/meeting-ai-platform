import { Head, useForm } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function Upload({ teams = [] }) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        title: '',
        description: '',
        team_id: '',
        audio_file: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('meetings.store'));
    };

    const handleFile = (e) => {
        setData('audio_file', e.target.files[0] ?? null);
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Upload Meeting
                </h2>
            }
        >
            <Head title="Upload Meeting" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="bg-white p-8 shadow sm:rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900">
                            New Meeting Recording
                        </h3>
                        <p className="mt-1 text-sm text-gray-600">
                            Upload your meeting audio and we'll handle the transcription and summary.
                        </p>

                        <form onSubmit={submit} className="mt-6 space-y-6">
                            {/* Title */}
                            <div>
                                <InputLabel htmlFor="title" value="Meeting Title" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g. Q2 Planning Sync"
                                    required
                                />
                                <InputError className="mt-2" message={errors.title} />
                            </div>

                            {/* Description */}
                            <div>
                                <InputLabel htmlFor="description" value="Description (optional)" />
                                <textarea
                                    id="description"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="What was this meeting about?"
                                />
                                <InputError className="mt-2" message={errors.description} />
                            </div>

                            {/* Team (optional) */}
                            {teams.length > 0 && (
                                <div>
                                    <InputLabel htmlFor="team_id" value="Team (optional)" />
                                    <select
                                        id="team_id"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        value={data.team_id}
                                        onChange={(e) => setData('team_id', e.target.value)}
                                    >
                                        <option value="">— No team —</option>
                                        {teams.map((team) => (
                                            <option key={team.id} value={team.id}>{team.name}</option>
                                        ))}
                                    </select>
                                    <InputError className="mt-2" message={errors.team_id} />
                                </div>
                            )}

                            {/* Audio File */}
                            <div>
                                <InputLabel htmlFor="audio_file" value="Audio File" />
                                <div className="mt-1 flex justify-center rounded-md border-2 border-dashed border-gray-300 px-6 py-8 transition hover:border-indigo-400">
                                    <div className="text-center">
                                        <svg
                                            className="mx-auto h-10 w-10 text-gray-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={1.5}
                                                d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
                                            />
                                        </svg>
                                        {data.audio_file ? (
                                            <p className="mt-2 text-sm font-medium text-indigo-600">
                                                {data.audio_file.name}
                                            </p>
                                        ) : (
                                            <p className="mt-2 text-sm text-gray-500">
                                                Click to select or drag &amp; drop
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-400">
                                            Audio: MP3, WAV, M4A, OGG · Video: MP4, MOV, WEBM — up to 2 GB
                                        </p>
                                        <input
                                            id="audio_file"
                                            type="file"
                                            className="mt-3 text-sm text-gray-500 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                                            accept=".mp3,.wav,.m4a,.ogg,.mp4,.mov,.webm,.mkv,.avi,audio/*,video/*"
                                            onChange={handleFile}
                                            required
                                        />
                                    </div>
                                </div>
                                <InputError className="mt-2" message={errors.audio_file} />
                            </div>

                            {/* Submit */}
                            <div className="flex items-center gap-4">
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Uploading…' : 'Upload Meeting'}
                                </PrimaryButton>

                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-green-600">Uploaded!</p>
                                </Transition>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
