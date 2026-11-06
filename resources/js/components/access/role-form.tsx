import { Form } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import AlertError from '@/components/alert-error';
import FormActionBar from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useFormDirty } from '@/hooks/use-form-dirty';
import type { PermissionGroup, RoleFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    role: RoleFormData;
    groups: PermissionGroup[];
    title: string;
    submitLabel: string;
};

/**
 * Name + a checkbox for every permission, grouped by area. Used to create and edit a role.
 * The ticked permissions are posted as `permissions[]`.
 */
export default function RoleForm({
    form,
    role,
    groups,
    title,
    submitLabel,
}: Props) {
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(role.permissions),
    );
    const { dirty, touch, clear, bindings } = useFormDirty();

    const change = (names: string[], on: boolean) => {
        setSelected((current) => {
            const next = new Set(current);

            names.forEach((name) => (on ? next.add(name) : next.delete(name)));

            return next;
        });
        touch();
    };

    const total = groups.reduce(
        (sum, group) => sum + group.permissions.length,
        0,
    );

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={clear}
            className="space-y-6"
            {...bindings}
        >
            {({ errors, processing, recentlySuccessful }) => {
                const messages = Object.values(errors);

                return (
                    <>
                        <FormActionBar
                            title={title}
                            subtitle={`${selected.size} of ${total} permissions`}
                            back={{
                                href: RoleController.index(),
                                label: 'Back to roles',
                            }}
                            submitLabel={submitLabel}
                            submitTest="save-role-button"
                            processing={processing}
                            dirty={dirty}
                            saved={recentlySuccessful}
                        />

                        {messages.length > 0 && (
                            <div role="alert">
                                <AlertError
                                    errors={messages}
                                    title="The role was not saved"
                                />
                            </div>
                        )}

                        {[...selected].map((name) => (
                            <input
                                key={name}
                                type="hidden"
                                name="permissions[]"
                                value={name}
                            />
                        ))}

                        <Card>
                            <CardContent className="pt-6">
                                <FormField
                                    name="name"
                                    label="Role name"
                                    defaultValue={role.name}
                                    placeholder="e.g. Content writer"
                                    maxLength={80}
                                    required
                                    help={
                                        role.users_count > 0
                                            ? `${role.users_count} ${role.users_count === 1 ? 'person has' : 'people have'} this role. Changes apply to them the next time they open a page.`
                                            : 'People get a role from the Users screen.'
                                    }
                                    error={errors.name}
                                />
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 md:grid-cols-2">
                            {groups.map((group) => {
                                const names = group.permissions.map(
                                    (permission) => permission.name,
                                );
                                const count = names.filter((name) =>
                                    selected.has(name),
                                ).length;
                                const all = count === names.length;

                                return (
                                    <Card key={group.key}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle className="text-base">
                                                        {group.label}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {group.description}
                                                    </CardDescription>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        change(names, !all)
                                                    }
                                                >
                                                    {all
                                                        ? 'Clear'
                                                        : 'Select all'}
                                                    <span className="sr-only">
                                                        {' '}
                                                        {group.label}
                                                    </span>
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="grid gap-3">
                                            {group.permissions.map(
                                                (permission) => {
                                                    const id = `perm-${permission.name}`;

                                                    return (
                                                        <div
                                                            key={
                                                                permission.name
                                                            }
                                                            className="flex items-start gap-3"
                                                        >
                                                            <Checkbox
                                                                id={id}
                                                                checked={selected.has(
                                                                    permission.name,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    change(
                                                                        [
                                                                            permission.name,
                                                                        ],
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
                                                                {
                                                                    permission.label
                                                                }
                                                            </Label>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </>
                );
            }}
        </Form>
    );
}
