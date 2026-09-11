import { Head, Link } from '@inertiajs/react';

export default function Expired() {
    return (
        <>
            <Head title="Share link expired" />

            <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
                <div className="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg className="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>

                    <h1 className="mt-5 text-lg font-semibold text-gray-900">This share link has expired</h1>
                    <p className="mt-2 text-sm text-gray-500">
                        The link is no longer active. Ask the person who shared this meeting to send you a fresh link.
                    </p>

                    <Link
                        href="/"
                        className="mt-6 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Go to Meeting AI
                    </Link>
                </div>
            </div>
        </>
    );
}
