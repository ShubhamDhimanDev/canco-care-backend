import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormActionBar from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useFormDirty } from '@/hooks/use-form-dirty';
import type { RoleOption, UserRow } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    user: UserRow | null;
    roles: RoleOption[];
    title: string;
    submitLabel: string;
};

/** Create or edit a sub-admin: details, password, and which roles they hold. */
export default function UserForm({
    form,
    user,
    roles,
    title,
    submitLabel,
}: Props) {
    const editing = user !== null;
    const [superAdmin, setSuperAdmin] = useState(user?.is_super_admin ?? false);
    const [chosen, setChosen] = useState<Set<number>>(
        () => new Set(user?.role_ids ?? []),
    );
    const { dirty, touch, clear, bindings } = useFormDirty();

    const toggle = (id: number, on: boolean) => {
        setChosen((current) => {
            const next = new Set(current);

            if (on) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });
        touch();
    };

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={clear}
            // Never leave a typed password sitting in the form after a save.
            resetOnSuccess={['password', 'password_confirmation']}
            className="space-y-6"
            {...bindings}
        >
            {({ errors, processing, recentlySuccessful }) => {
                const messages = Object.values(errors);

                return (
                    <>
                        <FormActionBar
                            title={title}
                            subtitle={editing ? user.email : undefined}
                            back={{
                                href: UserController.index(),
                                label: 'Back to users',
                            }}
                            submitLabel={submitLabel}
                            submitTest="save-user-button"
                            processing={processing}
                            dirty={dirty}
                            saved={recentlySuccessful}
                        />

                        {messages.length > 0 && (
                            <div role="alert">
                                <AlertError
                                    errors={messages}
                                    title="The account was not saved"
                                />
                            </div>
                        )}

                        {!superAdmin &&
                            [...chosen].map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="roles[]"
                                    value={id}
                                />
                            ))}

                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Sign-in details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        name="name"
                                        label="Name"
                                        defaultValue={user?.name}
                                        maxLength={255}
                                        required
                                        autoComplete="off"
                                        error={errors.name}
                                    />
                                    <FormField
                                        name="email"
                                        label="Email"
                                        type="email"
                                        defaultValue={user?.email}
                                        maxLength={255}
                                        required
                                        autoComplete="off"
                                        error={errors.email}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {editing
                                                ? 'New password'
                                                : 'Password'}
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            required={!editing}
                                            aria-invalid={
                                                errors.password
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {editing
                                                ? 'Leave empty to keep the current password.'
                                                : 'Tell them this password securely. They can change it under Settings → Security after signing in.'}
                                        </p>
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            required={!editing}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        What they can do
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <CheckField
                                        name="is_super_admin"
                                        label="Super administrator"
                                        description="Full access to everything, including users and roles. Give this to as few people as possible."
                                        checked={superAdmin}
                                        onCheckedChange={(on) => {
                                            setSuperAdmin(on);
                                            touch();
                                        }}
                                    />
                                    <InputError
                                        message={errors.is_super_admin}
                                    />

                                    <fieldset
                                        className="grid gap-3"
                                        disabled={superAdmin}
                                    >
                                        <legend className="mb-1 text-sm font-medium">
                                            Roles
                                        </legend>
                                        {superAdmin && (
                                            <p className="text-xs text-muted-foreground">
                                                A super administrator can do
                                                everything, so roles do not
                                                apply.
                                            </p>
                                        )}
                                        {roles.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                There are no roles yet.{' '}
                                                <Link
                                                    href={RoleController.create()}
                                                    className="underline"
                                                >
                                                    Create one
                                                </Link>{' '}
                                                first.
                                            </p>
                                        )}
                                        {roles.map((role) => {
                                            const id = `role-${role.id}`;

                                            return (
                                                <div
                                                    key={role.id}
                                                    className="flex items-start gap-3"
                                                >
                                                    <Checkbox
                                                        id={id}
                                                        checked={chosen.has(
                                                            role.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggle(
                                                                role.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        className="mt-0.5"
                                                    />
                                                    <Label
                                                        htmlFor={id}
                                                        className="cursor-pointer leading-snug font-normal"
                                                    >
                                                        {role.name}
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            {
                                                                role.permissions_count
                                                            }{' '}
                                                            permissions
                                                        </span>
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                        <InputError message={errors.roles} />
                                    </fieldset>
                                </CardContent>
                            </Card>
                        </div>
                    </>
                );
            }}
        </Form>
    );
}
