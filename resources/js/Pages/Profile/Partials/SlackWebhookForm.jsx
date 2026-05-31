import { useForm, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';

export default function SlackWebhookForm({ className = '' }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        slack_webhook_url: user.slack_webhook_url ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Slack Notifications</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Paste your Slack Incoming Webhook URL to receive a summary in Slack when a meeting is processed.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="slack_webhook_url" value="Slack Webhook URL" />
                    <TextInput
                        id="slack_webhook_url"
                        type="url"
                        className="mt-1 block w-full"
                        value={data.slack_webhook_url}
                        onChange={(e) => setData('slack_webhook_url', e.target.value)}
                        placeholder="https://hooks.slack.com/services/..."
                    />
                    <InputError className="mt-2" message={errors.slack_webhook_url} />
                    <p className="mt-1 text-xs text-gray-400">
                        Get your webhook URL from Slack → Apps → Incoming Webhooks.
                    </p>
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-green-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
