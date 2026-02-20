import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';

export default function TermsOfService() {
    return (
        <AuthLayout
            title="Terms of Service"
            description="Please read our terms carefully"
        >
            <Head title="Terms of Service" />

            <div
                data-test="tos-content"
                className="flex flex-col gap-6 text-sm text-muted-foreground"
            >
                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Introduction
                    </h2>
                    <p>
                        By creating an account and using our service, you agree
                        to be bound by these Terms of Service. Please read them
                        carefully before registering. If you do not agree to
                        these terms, you may not use our service.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Account Terms
                    </h2>
                    <p>
                        You must provide accurate and complete information when
                        creating your account. You are responsible for
                        maintaining the security of your account credentials and
                        for all activity that occurs under your account.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Acceptable Use
                    </h2>
                    <p>
                        You agree not to use the service for any unlawful
                        purpose or in any way that could damage, disable, or
                        impair the service. You may not attempt to gain
                        unauthorized access to any part of the service or its
                        related systems.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Intellectual Property
                    </h2>
                    <p>
                        All content, features, and functionality of the service
                        are owned by us and are protected by applicable
                        intellectual property laws. You may not reproduce,
                        distribute, or create derivative works without our
                        express written permission.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Termination
                    </h2>
                    <p>
                        We reserve the right to suspend or terminate your
                        account at any time for violation of these terms or for
                        any other reason at our sole discretion. You may also
                        delete your account at any time through your account
                        settings.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Limitation of Liability
                    </h2>
                    <p>
                        To the maximum extent permitted by law, we shall not be
                        liable for any indirect, incidental, special, or
                        consequential damages arising from your use of the
                        service, even if we have been advised of the possibility
                        of such damages.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        Changes to Terms
                    </h2>
                    <p>
                        We may update these Terms of Service from time to time.
                        We will notify you of any significant changes by posting
                        a notice on our site or by email. Continued use of the
                        service after changes constitutes acceptance of the
                        updated terms.
                    </p>
                </div>

                <div className="text-center text-sm">
                    Ready to create an account?{' '}
                    <TextLink href={register()}>Sign up</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
