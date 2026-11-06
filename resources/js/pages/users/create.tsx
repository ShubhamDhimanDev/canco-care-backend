import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserForm from '@/components/access/user-form';
import type { RoleOption } from '@/types';

export default function CreateUser({ roles }: { roles: RoleOption[] }) {
    return (
        <>
            <Head title="New account" />

            <div className="mx-auto w-full max-w-5xl p-4">
                <UserForm
                    form={UserController.store.form()}
                    user={null}
                    roles={roles}
                    title="New account"
                    submitLabel="Create account"
                />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: UserController.index() },
        { title: 'New account', href: UserController.create() },
    ],
};
