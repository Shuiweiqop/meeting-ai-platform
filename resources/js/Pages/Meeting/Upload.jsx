import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

function uploadChunk(uploadId, index, total, blob, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const fd  = new FormData();
        fd.append('upload_id',    uploadId);
        fd.append('chunk_index',  index);
        fd.append('total_chunks', total);
        fd.append('chunk',        blob);

        xhr.open('POST', route('upload.chunk'), true);
        xhr.setRequestHeader('X-CSRF-TOKEN',     window.csrfToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                onProgress(Math.round(((index + e.loaded / e.total) / total) * 100));
            }
        };

        xhr.onload  = () => (xhr.status < 300 ? resolve() : reject(new Error(`Server error ${xhr.status}`)));
        xhr.onerror = () => reject(new Error('Network error — check your connection and retry.'));
        xhr.send(fd);
    });
}

function formatBytes(bytes) {
    if (bytes >= 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024 / 1024).toFixed(1)} GB`;
    if (bytes >= 1024 * 1024)        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    return `${(bytes / 1024).toFixed(0)} KB`;
}

export default function Upload({ teams = [] }) {
    const [title,       setTitle]       = useState('');
    const [description, setDescription] = useState('');
    const [teamId,      setTeamId]      = useState('');
    const [file,        setFile]        = useState(null);
    const [progress,    setProgress]    = useState(0);
    const [phase,       setPhase]       = useState('idle'); // idle | uploading | merging
    const [errors,      setErrors]      = useState({});

    const handleSubmit = async (e) => {
        e.preventDefault();

        const newErrors = {};
        if (!title.trim())  newErrors.title      = 'Title is required.';
        if (!file)          newErrors.audio_file  = 'Please select a file.';
        if (Object.keys(newErrors).length) { setErrors(newErrors); return; }

        setErrors({});
        setPhase('uploading');
        setProgress(0);

        const uploadId    = crypto.randomUUID();
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        try {
            for (let i = 0; i < totalChunks; i++) {
                const blob = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                await uploadChunk(uploadId, i, totalChunks, blob, setProgress);
            }

            setPhase('merging');

            const res = await fetch(route('upload.merge'), {
                method:  'POST',
                headers: {
                    'Content-Type':      'application/json',
                    'X-CSRF-TOKEN':      window.csrfToken,
                    'X-Requested-With':  'XMLHttpRequest',
                },
                body: JSON.stringify({
                    upload_id:    uploadId,
                    filename:     file.name,
                    total_chunks: totalChunks,
                    title:        title.trim(),
                    description:  description.trim() || null,
                    team_id:      teamId || null,
                }),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                if (data.errors) { setErrors(data.errors); setPhase('idle'); return; }
                throw new Error(data.message ?? `Server error ${res.status}`);
            }

            router.visit(route('meetings.index'));
        } catch (err) {
            setErrors({ audio_file: err.message });
            setPhase('idle');
        }
    };

    const uploading = phase === 'uploading' || phase === 'merging';

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
                        <h3 className="text-lg font-medium text-gray-900">New Meeting Recording</h3>
                        <p className="mt-1 text-sm text-gray-600">
                            Upload your meeting audio and we'll handle the transcription and summary.
                        </p>

                        <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                            {/* Title */}
                            <div>
                                <InputLabel htmlFor="title" value="Meeting Title" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    placeholder="e.g. Q2 Planning Sync"
                                    disabled={uploading}
                                />
                                <InputError className="mt-2" message={errors.title} />
                            </div>

                            {/* Description */}
                            <div>
                                <InputLabel htmlFor="description" value="Description (optional)" />
                                <textarea
                                    id="description"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                                    rows={3}
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    placeholder="What was this meeting about?"
                                    disabled={uploading}
                                />
                            </div>

                            {/* Team */}
                            {teams.length > 0 && (
                                <div>
                                    <InputLabel htmlFor="team_id" value="Team (optional)" />
                                    <select
                                        id="team_id"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:opacity-50"
                                        value={teamId}
                                        onChange={(e) => setTeamId(e.target.value)}
                                        disabled={uploading}
                                    >
                                        <option value="">— No team —</option>
                                        {teams.map((team) => (
                                            <option key={team.id} value={team.id}>{team.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* File picker */}
                            <div>
                                <InputLabel htmlFor="audio_file" value="Audio / Video File" />
                                <div className={`mt-1 flex justify-center rounded-md border-2 border-dashed px-6 py-8 transition ${
                                    uploading ? 'border-gray-200 opacity-60' : 'border-gray-300 hover:border-indigo-400'
                                }`}>
                                    <div className="text-center w-full">
                                        <svg className="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                        </svg>

                                        {file ? (
                                            <p className="mt-2 text-sm font-medium text-indigo-600">
                                                {file.name}
                                                <span className="ml-2 font-normal text-gray-400">({formatBytes(file.size)})</span>
                                            </p>
                                        ) : (
                                            <p className="mt-2 text-sm text-gray-500">Click to select or drag &amp; drop</p>
                                        )}

                                        <p className="mt-1 text-xs text-gray-400">
                                            MP3, WAV, M4A, OGG · MP4, MOV, WEBM — up to 2 GB
                                        </p>

                                        {/* Progress bar — shown while uploading */}
                                        {uploading && (
                                            <div className="mt-4 space-y-1.5">
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                                    <div
                                                        className="h-2 rounded-full bg-indigo-500 transition-all duration-200"
                                                        style={{ width: `${progress}%` }}
                                                    />
                                                </div>
                                                <p className="text-xs text-gray-500">
                                                    {phase === 'merging'
                                                        ? 'Finalising upload…'
                                                        : `Uploading… ${progress}%`}
                                                </p>
                                            </div>
                                        )}

                                        <input
                                            id="audio_file"
                                            type="file"
                                            className="mt-3 text-sm text-gray-500 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 disabled:pointer-events-none"
                                            accept=".mp3,.wav,.m4a,.ogg,.mp4,.mov,.webm,.mkv,.avi,audio/*,video/*"
                                            onChange={(e) => setFile(e.target.files[0] ?? null)}
                                            disabled={uploading}
                                        />
                                    </div>
                                </div>
                                <InputError className="mt-2" message={errors.audio_file} />
                            </div>

                            {/* Submit */}
                            <div>
                                <PrimaryButton disabled={uploading}>
                                    {phase === 'merging'  ? 'Finalising…'  :
                                     phase === 'uploading' ? `Uploading ${progress}%…` :
                                     'Upload Meeting'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
