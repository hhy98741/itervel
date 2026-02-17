// Components
import { Form, Head, usePage } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import type { SharedData } from '@/types';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AuthLayout
            title="Verify email"
            description={`We've sent a verification email to ${auth.user.email}. Please click the link in the email to verify your account.`}
        >
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <p className="text-center text-sm text-muted-foreground">
                You need to verify your email address before you can create
                videos. You can still browse the platform and update your
                settings while unverified.
            </p>

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>

                        <p className="text-center text-xs text-muted-foreground">
                            You can request up to 3 verification emails per
                            hour.
                        </p>

                        <div className="flex flex-col gap-2">
                            <TextLink
                                href={profileEdit()}
                                className="mx-auto block text-sm"
                            >
                                Update your email address
                            </TextLink>

                            <TextLink
                                href={logout()}
                                className="mx-auto block text-sm"
                            >
                                Log out
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
