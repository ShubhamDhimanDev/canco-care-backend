import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleForm from '@/components/access/role-form';
import type { PermissionGroup, RoleFormData } from '@/types';

type Props = { role: RoleFormData; groups: PermissionGroup[] };

export default function CreateRole({ role, groups }: Props) {
    return (
        <>
            <Head title="New role" />

            <div className="mx-auto w-full max-w-5xl p-4">
                <RoleForm
                    form={RoleController.store.form()}
                    role={role}
                    groups={groups}
                    title="New role"
                    submitLabel="Create role"
                />
            </div>
        </>
    );
}

CreateRole.layout = {
    breadcrumbs: [
        { title: 'Roles', href: RoleController.index() },
        { title: 'New role', href: RoleController.create() },
    ],
};
