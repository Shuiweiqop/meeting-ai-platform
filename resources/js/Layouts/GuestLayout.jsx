import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

const FEATURES = [
    { title: 'Automatic transcription', body: 'Upload audio or video and get timestamped, speaker-labeled transcripts.' },
    { title: 'AI summaries & action items', body: 'Key points and follow-up tasks extracted the moment a meeting lands.' },
    { title: 'Share & collaborate', body: 'Private team spaces, shareable read-only links, and real-time progress.' },
];

export default function GuestLayout({ title, subtitle, children }) {
    return (
        <div className="flex min-h-screen bg-gray-100">
            {/* Brand panel — hidden on small screens */}
            <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 p-12 text-white lg:flex">
                <div
                    className="pointer-events-none absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 20% 20%, rgba(255,255,255,0.4) 0, transparent 40%), radial-gradient(circle at 80% 60%, rgba(255,255,255,0.25) 0, transparent 45%)',
                    }}
                />

                <Link href="/" className="relative flex items-center gap-3">
                    <ApplicationLogo className="h-10 w-10 fill-current text-white" />
                    <span className="text-lg font-semibold tracking-tight">Meeting AI</span>
                </Link>

                <div className="relative">
                    <h1 className="max-w-md text-4xl font-bold leading-tight tracking-tight">
                        Turn every meeting into notes, summaries, and next steps.
                    </h1>

                    <ul className="mt-10 space-y-6">
                        {FEATURES.map((f) => (
                            <li key={f.title} className="flex gap-4">
                                <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5">
                                        <path d="M2.5 6.5 5 9l4.5-5.5" />
                                    </svg>
                                </span>
                                <div>
                                    <p className="font-semibold">{f.title}</p>
                                    <p className="mt-0.5 text-sm text-indigo-100">{f.body}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-sm text-indigo-200">
                    Powered by Gemini · built for teams
                </p>
            </div>

            {/* Form panel */}
            <div className="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2">
                <div className="w-full max-w-md">
                    {/* Logo shown here only on small screens where the brand panel is hidden */}
                    <Link href="/" className="mb-8 flex items-center justify-center gap-3 lg:hidden">
                        <ApplicationLogo className="h-10 w-10 fill-current text-indigo-600" />
                        <span className="text-lg font-semibold tracking-tight text-gray-800">Meeting AI</span>
                    </Link>

                    <div className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200 sm:p-10">
                        {(title || subtitle) && (
                            <div className="mb-8">
                                {title && (
                                    <h2 className="text-2xl font-bold tracking-tight text-gray-900">{title}</h2>
                                )}
                                {subtitle && (
                                    <p className="mt-1 text-sm text-gray-500">{subtitle}</p>
                                )}
                            </div>
                        )}

                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
