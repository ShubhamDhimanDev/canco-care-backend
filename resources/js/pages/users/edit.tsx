import { Form, Head, usePage } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserForm from '@/components/access/user-form';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RoleOption, UserRow } from '@/types';

type Props = { user: UserRow; roles: RoleOption[] };

export default function EditUser({ user, roles }: Props) {
    const { auth, errors } = usePage().props;
    const isSelf = auth.user.id === user.id;

    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="mx-auto w-full max-w-5xl space-y-6 p-4">
                <UserForm
                    form={UserController.update.form(user.id)}
                    user={user}
                    roles={roles}
                    title={user.name}
                    submitLabel="Save changes"
                />

                {!isSelf && (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-destructive/30 p-4">
                        <div>
                            <h3 className="text-sm font-medium">
                                Delete this account
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                They can no longer sign in. Content they wrote
                                stays.
                            </p>
                            <InputError message={errors.delete} />
                        </div>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete account
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete {user.name}?</DialogTitle>
                                <DialogDescription>
                                    {user.email} will be removed and can no
                                    longer sign in. This cannot be undone.
                                </DialogDescription>
                                <Form {...UserController.destroy.form(user.id)}>
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Delete account
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [{ title: 'Users', href: UserController.index() }],
};
