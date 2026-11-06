import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleForm from '@/components/access/role-form';
import type { PermissionGroup, RoleFormData } from '@/types';

type Props = { role: RoleFormData; groups: PermissionGroup[] };

export default function EditRole({ role, groups }: Props) {
    return (
        <>
            <Head title={`Edit ${role.name}`} />

            <div className="mx-auto w-full max-w-5xl p-4">
                <RoleForm
                    form={RoleController.update.form(role.id as number)}
                    role={role}
                    groups={groups}
                    title={role.name}
                    submitLabel="Save role"
                />
            </div>
        </>
    );
}

EditRole.layout = {
    breadcrumbs: [{ title: 'Roles', href: RoleController.index() }],
};
