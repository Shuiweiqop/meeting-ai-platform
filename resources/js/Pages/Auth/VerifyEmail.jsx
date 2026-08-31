import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Verify your email"
            subtitle="Thanks for signing up! Click the link we just emailed you to verify your address — or resend it below."
        >
            <Head title="Email Verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 ring-1 ring-green-200">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <form onSubmit={submit}>
                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Resend verification email
                </PrimaryButton>

                <p className="mt-6 text-center">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded"
                    >
                        Log out
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
