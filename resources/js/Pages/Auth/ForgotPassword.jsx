import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="Forgot password?"
            subtitle="Enter your email and we'll send you a reset link so you can choose a new password."
        >
            <Head title="Forgot Password" />

            {status && (
                <div className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 ring-1 ring-green-200">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <PrimaryButton
                    className="mt-6 w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Email password reset link
                </PrimaryButton>

                <p className="mt-6 text-center text-sm text-gray-500">
                    <Link
                        href={route('login')}
                        className="font-semibold text-indigo-600 hover:text-indigo-500"
                    >
                        Back to log in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
